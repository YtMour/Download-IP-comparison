<?php
session_start();

// 检查登录状态
if (!isset($_SESSION['master_admin']) || $_SESSION['master_admin'] !== true) {
    header('Location: index.php');
    exit;
}

// 加载配置
$configFile = __DIR__ . '/config_master.php';
if (!file_exists($configFile)) {
    die('配置文件不存在: ' . $configFile);
}

$config = require $configFile;
if (!$config || !is_array($config)) {
    die('配置文件格式错误');
}

$backupDir = __DIR__ . '/../backups/';
$action = $_POST['action'] ?? '';
$error = '';
$success = '';

// 创建备份目录
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// 处理操作
if ($action === 'create_backup') {
    try {
        $dbConfig = $config['database'];
        
        $backupFile = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backupPath = $backupDir . $backupFile;
        
        // 创建数据库备份
        $command = sprintf(
            'mysqldump -h%s -P%d -u%s -p%s %s > %s 2>&1',
            escapeshellarg($dbConfig['host']),
            $dbConfig['port'],
            escapeshellarg($dbConfig['username']),
            escapeshellarg($dbConfig['password']),
            escapeshellarg($dbConfig['database']),
            escapeshellarg($backupPath)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($backupPath) && filesize($backupPath) > 0) {
            $_SESSION['success'] = "备份创建成功：$backupFile";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = "备份创建失败：" . implode("\n", $output);
        }
    } catch (Exception $e) {
        $error = "备份创建失败：" . $e->getMessage();
    }
}

if ($action === 'delete_backup') {
    $filename = $_POST['filename'] ?? '';
    $filepath = $backupDir . basename($filename);

    if (file_exists($filepath) && unlink($filepath)) {
        $_SESSION['success'] = "备份文件已删除：$filename";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = "删除备份文件失败";
    }
}

if ($action === 'restore_backup') {
    $filename = $_POST['filename'] ?? '';
    $filepath = $backupDir . basename($filename);
    
    if (file_exists($filepath)) {
        try {
            $dbConfig = $config['database'];
            
            $command = sprintf(
                'mysql -h%s -P%d -u%s -p%s %s < %s 2>&1',
                escapeshellarg($dbConfig['host']),
                $dbConfig['port'],
                escapeshellarg($dbConfig['username']),
                escapeshellarg($dbConfig['password']),
                escapeshellarg($dbConfig['database']),
                escapeshellarg($filepath)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0) {
                $_SESSION['success'] = "数据库恢复成功：$filename";
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $error = "数据库恢复失败：" . implode("\n", $output);
            }
        } catch (Exception $e) {
            $error = "数据库恢复失败：" . $e->getMessage();
        }
    } else {
        $error = "备份文件不存在";
    }
}

// 获取会话中的成功消息
$success = $_SESSION['success'] ?? '';
if ($success) {
    unset($_SESSION['success']);
}

// 获取备份文件列表
$backupFiles = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '*.sql');
    foreach ($files as $file) {
        $backupFiles[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }
    
    // 按时间倒序排列
    usort($backupFiles, function($a, $b) {
        return strcmp($b['date'], $a['date']);
    });
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>备份管理 - 多站点下载系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .nav { margin-bottom: 20px; }
        .nav a { color: #007bff; text-decoration: none; margin-right: 15px; }
        .nav a:hover { text-decoration: underline; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .backup-info { background: #e3f2fd; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💾 备份管理</h1>
            <div class="nav">
                <a href="index.php">← 返回主面板</a>
                <a href="database.php">数据库管理</a>
                <a href="logs.php">系统日志</a>
                <a href="api-docs.php">API文档</a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- 备份信息 -->
        <div class="backup-info">
            <strong>备份目录:</strong> <?= htmlspecialchars($backupDir) ?><br>
            <strong>备份数量:</strong> <?= count($backupFiles) ?> 个文件<br>
            <strong>总大小:</strong> <?= number_format(array_sum(array_column($backupFiles, 'size')) / 1024 / 1024, 2) ?> MB
        </div>

        <!-- 创建备份 -->
        <div class="card">
            <h2>🔧 备份操作</h2>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="create_backup">
                <button type="submit" class="btn btn-success">📦 创建新备份</button>
            </form>
            <p style="margin-top: 10px; color: #666;">
                💡 提示：备份将包含所有站点数据、下载记录和IP验证记录
            </p>
        </div>

        <!-- 备份文件列表 -->
        <div class="card">
            <h2>📋 备份文件列表</h2>
            <?php if (empty($backupFiles)): ?>
                <p style="color: #666; text-align: center; padding: 40px;">
                    📁 暂无备份文件<br>
                    点击"创建新备份"开始备份数据
                </p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>文件名</th>
                            <th>创建时间</th>
                            <th>文件大小</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backupFiles as $file): ?>
                            <tr>
                                <td><?= htmlspecialchars($file['name']) ?></td>
                                <td><?= htmlspecialchars($file['date']) ?></td>
                                <td><?= number_format($file['size'] / 1024, 1) ?> KB</td>
                                <td>
                                    <a href="<?= '../backups/' . urlencode($file['name']) ?>" class="btn btn-primary" download>📥 下载</a>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="restore_backup">
                                        <input type="hidden" name="filename" value="<?= htmlspecialchars($file['name']) ?>">
                                        <button type="submit" class="btn btn-warning" onclick="return confirm('⚠️ 警告：恢复备份将覆盖当前所有数据！\n\n确定要恢复此备份吗？')">
                                            🔄 恢复
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_backup">
                                        <input type="hidden" name="filename" value="<?= htmlspecialchars($file['name']) ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('确定要删除此备份文件吗？')">
                                            🗑️ 删除
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- 备份建议 -->
        <div class="card">
            <h2>💡 备份建议</h2>
            <ul style="line-height: 1.6;">
                <li><strong>定期备份：</strong> 建议每天或每周创建备份</li>
                <li><strong>多地存储：</strong> 将备份文件下载到本地或云存储</li>
                <li><strong>测试恢复：</strong> 定期测试备份文件的完整性</li>
                <li><strong>清理旧备份：</strong> 定期删除过期的备份文件</li>
                <li><strong>重要提醒：</strong> 恢复备份前请先创建当前数据的备份</li>
            </ul>
        </div>

        <!-- 自动备份设置 -->
        <div class="card">
            <h2>⚙️ 自动备份设置</h2>
            <p style="margin-bottom: 15px;">可以通过crontab设置自动备份：</p>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace;">
                # 每天凌晨2点自动备份<br>
                0 2 * * * cd <?= dirname(__DIR__) ?> && php -r "require 'admin/backup.php';"
            </div>
        </div>
    </div>
</body>
</html>

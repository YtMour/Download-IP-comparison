<?php
session_start();
require_once 'database_manager.php';

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

$dbManager = new MultiSiteDatabaseManager($config);

$action = $_POST['action'] ?? '';
$error = '';
$success = '';

// 处理操作
if ($action === 'optimize') {
    try {
        $pdo = $dbManager->getPDO();
        $tables = ['sites', 'downloads', 'ip_verifications', 'system_config'];
        
        foreach ($tables as $table) {
            $tableName = $dbManager->getTableName($table);
            $pdo->exec("OPTIMIZE TABLE $tableName");
        }
        
        $_SESSION['success'] = "数据库优化完成";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        $error = "优化失败：" . $e->getMessage();
    }
}

if ($action === 'backup') {
    try {
        $backupFile = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backupPath = __DIR__ . '/../backups/' . $backupFile;
        
        // 创建备份目录
        if (!is_dir(dirname($backupPath))) {
            mkdir(dirname($backupPath), 0755, true);
        }
        
        // 执行备份
        $dbConfig = $config['database'];
        $command = sprintf(
            'mysqldump -h%s -u%s -p%s %s > %s',
            $dbConfig['host'],
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['database'],
            $backupPath
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            $_SESSION['success'] = "备份完成：$backupFile";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = "备份失败";
        }
    } catch (Exception $e) {
        $error = "备份失败：" . $e->getMessage();
    }
}

// 获取会话中的成功消息
$success = $_SESSION['success'] ?? '';
if ($success) {
    unset($_SESSION['success']);
}

// 获取数据库统计
try {
    $pdo = $dbManager->getPDO();
    $stats = [];
    
    $tables = ['sites', 'downloads', 'ip_verifications'];
    foreach ($tables as $table) {
        $tableName = $dbManager->getTableName($table);
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $tableName");
            $stats[$table] = $stmt->fetchColumn();
        } catch (Exception $e) {
            $stats[$table] = 0;
        }
    }
    
    // 获取数据库大小
    $stmt = $pdo->prepare("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'size_mb' FROM information_schema.tables WHERE table_schema = ?");
    $stmt->execute([$config['database']['database']]);
    $dbSize = $stmt->fetchColumn() ?: 0;
    
} catch (Exception $e) {
    $error = "获取统计信息失败：" . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据库管理 - 多站点下载系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .nav { margin-bottom: 20px; }
        .nav a { color: #007bff; text-decoration: none; margin-right: 15px; }
        .nav a:hover { text-decoration: underline; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; color: #007bff; }
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗄️ 数据库管理</h1>
            <div class="nav">
                <a href="index.php">← 返回主面板</a>
                <a href="logs.php">系统日志</a>
                <a href="backup.php">备份管理</a>
                <a href="api-docs.php">API文档</a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- 数据库统计 -->
        <div class="card">
            <h2>📊 数据库统计</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['sites'] ?? 0 ?></div>
                    <div>站点数量</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['downloads'] ?? 0) ?></div>
                    <div>下载记录</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['ip_verifications'] ?? 0) ?></div>
                    <div>IP验证记录</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $dbSize ?> MB</div>
                    <div>数据库大小</div>
                </div>
            </div>
        </div>

        <!-- 数据库操作 -->
        <div class="card">
            <h2>🔧 数据库操作</h2>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="optimize">
                <button type="submit" class="btn btn-primary">🚀 优化数据库</button>
            </form>
            
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="backup">
                <button type="submit" class="btn btn-success">💾 创建备份</button>
            </form>
            
            <a href="index.php" class="btn btn-warning">🔄 重置数据库</a>
        </div>

        <!-- 表结构信息 -->
        <div class="card">
            <h2>📋 表结构信息</h2>
            <table>
                <thead>
                    <tr>
                        <th>表名</th>
                        <th>记录数</th>
                        <th>状态</th>
                        <th>说明</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= $dbManager->getTableName('sites') ?></td>
                        <td><?= number_format($stats['sites'] ?? 0) ?></td>
                        <td><span style="color: green;">✅ 正常</span></td>
                        <td>站点信息表</td>
                    </tr>
                    <tr>
                        <td><?= $dbManager->getTableName('downloads') ?></td>
                        <td><?= number_format($stats['downloads'] ?? 0) ?></td>
                        <td><span style="color: green;">✅ 正常</span></td>
                        <td>下载记录表</td>
                    </tr>
                    <tr>
                        <td><?= $dbManager->getTableName('ip_verifications') ?></td>
                        <td><?= number_format($stats['ip_verifications'] ?? 0) ?></td>
                        <td><span style="color: green;">✅ 正常</span></td>
                        <td>IP验证记录表</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 连接信息 -->
        <div class="card">
            <h2>🔗 连接信息</h2>
            <table>
                <tr>
                    <td><strong>数据库类型</strong></td>
                    <td><?= htmlspecialchars($config['database']['type']) ?></td>
                </tr>
                <tr>
                    <td><strong>服务器地址</strong></td>
                    <td><?= htmlspecialchars($config['database']['host']) ?>:<?= $config['database']['port'] ?></td>
                </tr>
                <tr>
                    <td><strong>数据库名</strong></td>
                    <td><?= htmlspecialchars($config['database']['database']) ?></td>
                </tr>
                <tr>
                    <td><strong>表前缀</strong></td>
                    <td><?= htmlspecialchars($config['database']['prefix']) ?></td>
                </tr>
                <tr>
                    <td><strong>字符集</strong></td>
                    <td><?= htmlspecialchars($config['database']['charset']) ?></td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>

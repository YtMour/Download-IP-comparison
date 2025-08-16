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

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .nav {
            margin-top: 20px;
        }

        .nav a {
            color: white;
            text-decoration: none;
            margin-right: 20px;
            padding: 8px 16px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .nav a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .content {
            padding: 30px;
        }

        .card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 2px solid #e9ecef;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            border-color: #4facfe;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 2px solid #e9ecef;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: #4facfe;
        }

        .stat-card h3 {
            color: #4facfe;
            margin-bottom: 10px;
            font-size: 1.2em;
        }

        .stat-card .number {
            font-size: 2.5em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-card .label {
            color: #666;
            font-size: 0.9em;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #fc466b 0%, #3f5efb 100%);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .alert {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            font-weight: 600;
        }

        tr:hover {
            background: #f8f9fa;
        }
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

        <div class="content">

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
    </div>
</body>
</html>

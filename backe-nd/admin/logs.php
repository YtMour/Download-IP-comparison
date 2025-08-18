<?php
session_start();

// 检查登录状态
if (!isset($_SESSION['master_admin']) || $_SESSION['master_admin'] !== true) {
    header('Location: index.php');
    exit;
}

$action = $_GET['action'] ?? '';
$logType = $_GET['type'] ?? 'system';

// 日志文件路径 - 使用与admin同级的logs文件夹
$logsDir = dirname(__DIR__) . '/logs';

// 确保logs目录存在
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// 创建.htaccess文件保护logs目录
$htaccessFile = $logsDir . '/.htaccess';
if (!file_exists($htaccessFile)) {
    file_put_contents($htaccessFile, "Order Deny,Allow\nDeny from all\n");
}

$logFiles = [
    'system' => $logsDir . '/download_system.log',
    'access' => $logsDir . '/access.log',
    'error' => $logsDir . '/error.log',
    'php' => $logsDir . '/php_errors.log',
    'api' => $logsDir . '/api.log',
    'download' => $logsDir . '/download.log'
];

// 初始化日志文件（如果不存在）
foreach ($logFiles as $type => $logFile) {
    if (!file_exists($logFile)) {
        $initialContent = "# " . strtoupper($type) . " LOG - Created on " . date('Y-m-d H:i:s') . "\n";
        $initialContent .= "# This log file tracks " . $type . " events for the download system\n\n";
        file_put_contents($logFile, $initialContent);
    }
}

// 获取日志内容
function getLogContent($file, $lines = 100) {
    if (!file_exists($file)) {
        return "日志文件不存在: $file";
    }
    
    if (!is_readable($file)) {
        return "无法读取日志文件: $file";
    }
    
    // 使用tail命令获取最后N行
    $command = "tail -n $lines " . escapeshellarg($file);
    $output = shell_exec($command);
    
    return $output ?: "日志文件为空";
}

// 清空日志
if ($action === 'clear' && isset($logFiles[$logType])) {
    $file = $logFiles[$logType];
    if (file_exists($file) && is_writable($file)) {
        file_put_contents($file, '');
        $_SESSION['success'] = "日志已清空";
        header('Location: ' . $_SERVER['PHP_SELF'] . '?type=' . urlencode($logType));
        exit;
    } else {
        $error = "无法清空日志文件";
    }
}

// 获取会话中的成功消息
$success = $_SESSION['success'] ?? '';
if ($success) {
    unset($_SESSION['success']);
}

$currentLog = $logFiles[$logType] ?? $logFiles['system'];
$logContent = getLogContent($currentLog);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统日志 - 多站点下载系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .nav { margin-bottom: 20px; }
        .nav a { color: #007bff; text-decoration: none; margin-right: 15px; }
        .nav a:hover { text-decoration: underline; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .log-tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .log-tab { padding: 10px 20px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333; }
        .log-tab.active { background: #007bff; color: white; }
        .log-content { background: #1e1e1e; color: #f8f8f2; padding: 20px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 14px; line-height: 1.6; min-height: 600px; max-height: 1200px; overflow-y: auto; white-space: pre-wrap; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .log-info { background: #e3f2fd; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 系统日志</h1>
            <div class="nav">
                <a href="index.php">← 返回主面板</a>
                <a href="database.php">数据库管理</a>
                <a href="backup.php">备份管理</a>
                <a href="api-docs.php">API文档</a>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>



        <!-- 日志类型选择 -->
        <div class="card">
            <h2>📂 日志类型选择</h2>
            <div style="margin-bottom: 15px;">
                <?php foreach ($logFiles as $type => $file): ?>
                    <a href="?type=<?= $type ?>"
                       class="btn <?= $type === $logType ? 'btn-primary' : 'btn-secondary' ?>"
                       style="margin-right: 10px; margin-bottom: 5px;">
                        <?php
                        $typeNames = [
                            'system' => '🖥️ 系统日志',
                            'access' => '🌐 访问日志',
                            'error' => '❌ 错误日志',
                            'php' => '🐘 PHP日志',
                            'api' => '🔌 API日志',
                            'download' => '📥 下载日志'
                        ];
                        echo $typeNames[$type] ?? strtoupper($type);
                        ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 日志信息 -->
        <div class="log-info">
            <strong>当前日志文件:</strong> <?= htmlspecialchars($currentLog) ?><br>
            <strong>文件状态:</strong>
            <?php if (file_exists($currentLog)): ?>
                <span style="color: green;">✅ 存在</span>
                (大小: <?= number_format(filesize($currentLog) / 1024, 1) ?> KB)
            <?php else: ?>
                <span style="color: red;">❌ 不存在</span>
            <?php endif; ?>
        </div>

        <!-- 日志操作 -->
        <div class="card">
            <h2>🔧 日志操作</h2>
            <a href="?type=<?= $logType ?>" class="btn btn-primary">🔄 刷新日志</a>
            <a href="?type=<?= $logType ?>&action=clear" class="btn btn-danger" onclick="return confirm('确定要清空日志吗？')">🗑️ 清空日志</a>
            <button onclick="downloadLog()" class="btn btn-success">💾 下载日志</button>
        </div>

        <!-- 日志内容 -->
        <div class="card">
            <h2>📄 日志内容 (最近100行)</h2>
            <div class="log-content" id="logContent"><?= htmlspecialchars($logContent) ?></div>
        </div>

        <!-- 实时日志监控 -->
        <div class="card">
            <h2>⚡ 实时监控</h2>
            <button onclick="toggleAutoRefresh()" class="btn btn-primary" id="autoRefreshBtn">▶️ 开启自动刷新</button>
            <span id="refreshStatus">自动刷新已关闭</span>
        </div>
    </div>

    <script>
        let autoRefreshInterval = null;
        let isAutoRefreshing = false;

        function toggleAutoRefresh() {
            const btn = document.getElementById('autoRefreshBtn');
            const status = document.getElementById('refreshStatus');
            
            if (isAutoRefreshing) {
                clearInterval(autoRefreshInterval);
                btn.textContent = '▶️ 开启自动刷新';
                status.textContent = '自动刷新已关闭';
                isAutoRefreshing = false;
            } else {
                autoRefreshInterval = setInterval(refreshLog, 5000);
                btn.textContent = '⏸️ 关闭自动刷新';
                status.textContent = '自动刷新已开启 (每5秒)';
                isAutoRefreshing = true;
            }
        }

        function refreshLog() {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newContent = doc.getElementById('logContent');
                    if (newContent) {
                        document.getElementById('logContent').innerHTML = newContent.innerHTML;
                    }
                })
                .catch(error => {
                    console.error('刷新日志失败:', error);
                });
        }

        function downloadLog() {
            const content = document.getElementById('logContent').textContent;
            const blob = new Blob([content], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'log_<?= $logType ?>_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // 自动滚动到底部
        document.addEventListener('DOMContentLoaded', function() {
            const logContent = document.getElementById('logContent');
            logContent.scrollTop = logContent.scrollHeight;
        });
    </script>
</body>
</html>

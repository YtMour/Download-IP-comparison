<?php
/**
 * 多站点管理系统 - 总控制台
 */

session_start();

// 加载配置和数据库
require_once 'database_manager.php';

// 加载配置文件
$configFile = __DIR__ . '/config_master.php';
if (!file_exists($configFile)) {
    die('配置文件不存在: ' . $configFile);
}

$config = require $configFile;

if (!$config || !is_array($config)) {
    die('配置文件格式错误');
}

// 验证必要的配置项
if (!isset($config['database']) || !isset($config['system'])) {
    die('配置文件缺少必要配置项');
}

// 检查登录状态
$logged_in = isset($_SESSION['master_admin']) && $_SESSION['master_admin'] === true;

// 简单身份验证
$action = $_POST['action'] ?? '';

if ($action === 'login') {
    $password = $_POST['password'] ?? '';
    if ($password === $config['system']['master_admin_password']) {
        $_SESSION['master_admin'] = true;
        // 重定向防止刷新重复提交
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = '密码错误';
    }
}

if ($action === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// 站点管理功能
if ($logged_in && $action === 'add_site') {
    $siteName = trim($_POST['site_name'] ?? '');
    $siteDomain = trim($_POST['site_domain'] ?? '');

    if ($siteName && $siteDomain) {
        try {
            $dbManager = new MultiSiteDatabaseManager($config);
            $pdo = $dbManager->getPDO();

            // 从域名生成站点key
            $host = parse_url($siteDomain, PHP_URL_HOST);
            $siteKey = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', str_replace(['www.', '.'], '', $host)));

            // 如果生成的key为空或太短，使用随机字符串
            if (strlen($siteKey) < 3) {
                $siteKey = 'site_' . bin2hex(random_bytes(4));
            }

            // 生成API密钥
            $apiKey = $siteKey . '_' . bin2hex(random_bytes(16)) . '_' . date('Ymd');

            // 插入站点数据
            $stmt = $pdo->prepare("INSERT INTO {$dbManager->getTableName('sites')}
                (site_key, name, domain, api_key, status, created_at)
                VALUES (?, ?, ?, ?, 'active', NOW())");

            $stmt->execute([$siteKey, $siteName, $siteDomain, $apiKey]);

            $_SESSION['success'] = "站点 $siteName 添加成功！API密钥：$apiKey";
            // 重定向防止刷新重复提交
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $error = "添加站点失败：" . $e->getMessage();
        }
    } else {
        $error = "请填写站点名称和域名";
    }
}

if ($logged_in && $action === 'delete_site') {
    $siteId = $_POST['site_id'] ?? '';
    if ($siteId) {
        try {
            $dbManager = new MultiSiteDatabaseManager($config);
            $pdo = $dbManager->getPDO();

            // 先获取站点名称
            $stmt = $pdo->prepare("SELECT name FROM {$dbManager->getTableName('sites')} WHERE id = ?");
            $stmt->execute([$siteId]);
            $siteName = $stmt->fetchColumn();

            // 删除站点
            $stmt = $pdo->prepare("DELETE FROM {$dbManager->getTableName('sites')} WHERE id = ?");
            $result = $stmt->execute([$siteId]);

            if ($result && $stmt->rowCount() > 0) {
                $_SESSION['success'] = "站点 \"$siteName\" 删除成功！";
                // 重定向防止刷新重复提交
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $error = "站点不存在或删除失败";
            }
        } catch (Exception $e) {
            $error = "删除站点失败：" . $e->getMessage();
        }
    } else {
        $error = "缺少站点ID";
    }
}

// 获取会话中的成功消息
$success = $_SESSION['success'] ?? '';
if ($success) {
    unset($_SESSION['success']);
}

if ($logged_in && $action === 'toggle_ip_verification') {
    try {
        // 直接修改配置文件
        $configFile = __DIR__ . '/config_master.php';
        $currentConfig = require $configFile;

        // 切换IP验证状态
        $currentStatus = $currentConfig['ip_verification']['enabled'] ?? true;
        $newStatus = !$currentStatus;
        $currentConfig['ip_verification']['enabled'] = $newStatus;

        // 重新生成配置文件
        $configContent = "<?php\n/**\n * 多站点管理系统 - 主配置文件\n * 更新于: " . date('Y-m-d H:i:s') . "\n */\n\nreturn " . var_export($currentConfig, true) . ";\n?>";

        if (file_put_contents($configFile, $configContent)) {
            // 重新加载配置
            $config = $currentConfig;
            $success = "IP验证功能已" . ($newStatus ? '启用' : '禁用');
        } else {
            $error = "无法更新配置文件";
        }
    } catch (Exception $e) {
        $error = "切换IP验证状态失败：" . $e->getMessage();
    }
}

if ($logged_in && $action === 'reset_database') {
    try {
        $dbManager = new MultiSiteDatabaseManager($config);
        $pdo = $dbManager->getPDO();

        // 禁用外键检查
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // 按正确顺序删除表（先删除有外键的表）
        $tables = ['ip_verifications', 'downloads', 'system_config', 'sites'];
        foreach ($tables as $table) {
            $tableName = $dbManager->getTableName($table);
            $pdo->exec("DROP TABLE IF EXISTS $tableName");
        }

        // 重新启用外键检查
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        // 重新创建表
        $dbManager->createTables();

        $success = "数据库已重置，所有表已重新创建";
    } catch (Exception $e) {
        $error = "数据库重置失败：" . $e->getMessage();
    }
}

$logged_in = $_SESSION['master_admin'] ?? false;

// 初始化数据库
$dbManager = null;
$stats = [];

if ($logged_in) {
    try {
        $dbManager = new MultiSiteDatabaseManager($config);
        $pdo = $dbManager->getPDO();
        
        // 初始化统计数据
        $stats = [
            'total_sites' => 0,
            'active_sites' => 0,
            'total_downloads' => 0,
            'today_downloads' => 0,
            'total_verifications' => 0,
            'success_rate' => 0
        ];

        // 检查表是否存在并获取统计
        try {
            $stats['total_sites'] = $pdo->query("SELECT COUNT(*) FROM {$dbManager->getTableName('sites')}")->fetchColumn();
            $stats['active_sites'] = $pdo->query("SELECT COUNT(*) FROM {$dbManager->getTableName('sites')} WHERE status = 'active'")->fetchColumn();
        } catch (Exception $e) {
            // sites表可能不存在，使用默认值
        }

        try {
            $stats['total_downloads'] = $pdo->query("SELECT COUNT(*) FROM {$dbManager->getTableName('downloads')}")->fetchColumn();
            $stats['today_downloads'] = $pdo->query("SELECT COUNT(*) FROM {$dbManager->getTableName('downloads')} WHERE DATE(created_at) = CURDATE()")->fetchColumn();
        } catch (Exception $e) {
            // downloads表可能不存在，使用默认值
        }

        try {
            $stats['total_verifications'] = $pdo->query("SELECT COUNT(*) FROM {$dbManager->getTableName('ip_verifications')}")->fetchColumn();
            $successCount = $pdo->query("SELECT COUNT(*) FROM {$dbManager->getTableName('ip_verifications')} WHERE result IN ('IP_MATCH', 'IP_MISMATCH_ALLOWED', 'IP_VERIFICATION_DISABLED')")->fetchColumn();
            if ($stats['total_verifications'] > 0) {
                $stats['success_rate'] = round(($successCount / $stats['total_verifications']) * 100, 2);
            }
        } catch (Exception $e) {
            // ip_verifications表可能不存在，使用默认值
        }

        // 获取站点列表
        try {
            $sites = $pdo->query("SELECT * FROM {$dbManager->getTableName('sites')} ORDER BY created_at DESC")->fetchAll();
        } catch (Exception $e) {
            $sites = [];
        }
        
    } catch (Exception $e) {
        $error = '数据库连接失败: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>多站点下载系统 - 总控制台</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
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
        
        .content {
            padding: 30px;
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
        
        .sites-section {
            margin-top: 40px;
        }
        
        .sites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .site-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border: 2px solid #e9ecef;
        }
        
        .site-card.active {
            border-color: #28a745;
        }
        
        .site-card.inactive {
            border-color: #dc3545;
        }
        
        .site-card.planning {
            border-color: #ffc107;
        }
        
        .site-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .site-name {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
        }
        
        .site-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-planning {
            background: #fff3cd;
            color: #856404;
        }
        
        .site-info {
            margin-bottom: 15px;
        }
        
        .site-info p {
            margin: 5px 0;
            color: #666;
            font-size: 0.9em;
        }
        
        .site-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: #4facfe;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .nav-tabs {
            display: flex;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .nav-tab {
            padding: 10px 20px;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
        }
        
        .nav-tab.active {
            background: #4facfe;
            color: white;
        }
    </style>
    <script>
        function toggleApiKey(siteId, fullApiKey) {
            const element = document.getElementById('api-key-' + siteId);
            const button = element.nextElementSibling;

            if (button.textContent === '显示') {
                element.textContent = fullApiKey;
                button.textContent = '隐藏';
            } else {
                element.textContent = fullApiKey.substring(0, 20) + '...';
                button.textContent = '显示';
            }
        }

        function copyApiKey(apiKey) {
            navigator.clipboard.writeText(apiKey).then(function() {
                alert('API密钥已复制到剪贴板');
            });
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏢 多站点下载系统</h1>
            <p>总控制台 - 统一管理所有软件库站点</p>
        </div>
        
        <div class="content">
            <?php if (!$logged_in): ?>
                <!-- 登录表单 -->
                <h2>管理员登录</h2>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label for="password">主管理员密码:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">登录</button>
                </form>
                
            <?php else: ?>
                <!-- 管理面板 -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                    <h2>系统总览</h2>
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="btn btn-danger">退出登录</button>
                    </form>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <!-- 统计卡片 -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>总站点数</h3>
                        <div class="number"><?= $stats['total_sites'] ?? 0 ?></div>
                        <div class="label">已注册站点</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>活跃站点</h3>
                        <div class="number"><?= $stats['active_sites'] ?? 0 ?></div>
                        <div class="label">正在运行</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>总下载数</h3>
                        <div class="number"><?= number_format($stats['total_downloads'] ?? 0) ?></div>
                        <div class="label">累计下载</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>今日下载</h3>
                        <div class="number"><?= $stats['today_downloads'] ?? 0 ?></div>
                        <div class="label">24小时内</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>验证成功率</h3>
                        <div class="number"><?= $stats['success_rate'] ?? 0 ?>%</div>
                        <div class="label">IP验证通过</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>系统状态</h3>
                        <div class="number">🟢</div>
                        <div class="label">运行正常</div>
                    </div>
                </div>

                <!-- 系统控制面板 -->
                <div class="control-panel" style="margin: 30px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <h3>🎛️ 系统控制</h3>
                    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_ip_verification">
                            <button type="submit" class="btn <?= ($config['ip_verification']['enabled'] ?? true) ? 'btn-warning' : 'btn-success' ?>">
                                <?= ($config['ip_verification']['enabled'] ?? true) ? '🔒 禁用IP验证' : '🔓 启用IP验证' ?>
                            </button>
                        </form>

                        <span style="color: #666; font-size: 14px;">
                            当前状态: <?= ($config['ip_verification']['enabled'] ?? true) ? '✅ IP验证已启用' : '❌ IP验证已禁用' ?>
                        </span>

                        <span style="color: #666; font-size: 14px;">
                            严格模式: <?= ($config['ip_verification']['strict_mode'] ?? false) ? '✅ 已启用' : '❌ 已禁用' ?>
                        </span>

                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="reset_database">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('⚠️ 警告：这将删除所有站点数据！\n\n确定要重置数据库吗？')">
                                🔄 重置数据库
                            </button>
                        </form>
                    </div>
                </div>

                <!-- 站点管理 -->
                <div class="sites-section">
                    <h2>站点管理</h2>
                    
                    <div class="sites-grid">
                        <?php if (isset($sites)): ?>
                            <?php foreach ($sites as $site): ?>
                                <div class="site-card <?= $site['status'] ?>">
                                    <div class="site-header">
                                        <div class="site-name"><?= htmlspecialchars($site['name']) ?></div>
                                        <div class="site-status status-<?= $site['status'] ?>">
                                            <?= $site['status'] ?>
                                        </div>
                                    </div>
                                    
                                    <div class="site-info">
                                        <p><strong>域名:</strong> <?= htmlspecialchars($site['domain']) ?></p>
                                        <p><strong>API Key:</strong>
                                            <span id="api-key-<?= $site['id'] ?>" style="font-family: monospace; background: #f5f5f5; padding: 2px 4px; border-radius: 3px;">
                                                <?= substr($site['api_key'], 0, 20) ?>...
                                            </span>
                                            <button type="button" onclick="toggleApiKey(<?= $site['id'] ?>, '<?= htmlspecialchars($site['api_key']) ?>')" class="btn" style="padding: 2px 8px; margin-left: 5px; font-size: 12px;">显示</button>
                                            <button type="button" onclick="copyApiKey('<?= htmlspecialchars($site['api_key']) ?>')" class="btn btn-success" style="padding: 2px 8px; margin-left: 5px; font-size: 12px;">复制</button>
                                        </p>
                                        <p><strong>创建时间:</strong> <?= htmlspecialchars($site['created_at']) ?></p>
                                    </div>
                                    
                                    <div class="site-actions">
                                        <a href="<?= $site['domain'] ?>" target="_blank" class="btn btn-primary">访问站点</a>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_site">
                                            <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('确定要删除这个站点吗？')">删除</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- 添加新站点表单 -->
                        <div class="site-card" style="border: 2px dashed #4facfe;">
                            <h3 style="margin-bottom: 20px; color: #4facfe;">➕ 添加新站点</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_site">

                                <div class="form-group">
                                    <label for="site_name">站点名称:</label>
                                    <input type="text" name="site_name" id="site_name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                                </div>

                                <div class="form-group">
                                    <label for="site_domain">站点域名:</label>
                                    <input type="url" name="site_domain" id="site_domain" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                                </div>

                                <button type="submit" class="btn btn-success" style="width: 100%;">🚀 创建站点</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- 快速操作 -->
                <div style="margin-top: 40px; text-align: center;">
                    <h3>快速操作</h3>
                    <div style="margin-top: 20px;">
                        <a href="database.php" class="btn btn-primary">数据库管理</a>
                        <a href="logs.php" class="btn btn-warning">系统日志</a>
                        <a href="backup.php" class="btn btn-success">备份管理</a>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
/**
 * 管理面板测试脚本
 * 用于测试管理面板的各项功能
 */

// 设置错误显示
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🧪 管理面板功能测试</h1>";

// 1. 测试配置文件加载
echo "<h2>1. 配置文件测试</h2>";
$configFile = __DIR__ . '/admin/config_master.php';
if (file_exists($configFile)) {
    echo "✅ 配置文件存在<br>";
    
    try {
        $config = require $configFile;
        echo "✅ 配置文件加载成功<br>";
        
        if (isset($config['system']['master_admin_password'])) {
            echo "✅ 管理员密码已设置<br>";
        } else {
            echo "❌ 管理员密码未设置<br>";
        }
        
        if (isset($config['database'])) {
            echo "✅ 数据库配置存在<br>";
        } else {
            echo "❌ 数据库配置缺失<br>";
        }
        
        echo "站点数量: " . count($config['sites'] ?? []) . "<br>";
        
    } catch (Exception $e) {
        echo "❌ 配置文件加载失败: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ 配置文件不存在<br>";
}

// 2. 测试数据库连接
echo "<h2>2. 数据库连接测试</h2>";
if (isset($config)) {
    try {
        require_once __DIR__ . '/admin/database_manager.php';
        $dbManager = new MultiSiteDatabaseManager($config);
        $pdo = $dbManager->getPDO();
        
        echo "✅ 数据库连接成功<br>";
        
        // 测试表是否存在
        $tables = ['sites', 'downloads', 'ip_verifications'];
        foreach ($tables as $table) {
            $tableName = $dbManager->getTableName($table);
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM $tableName");
                $count = $stmt->fetchColumn();
                echo "✅ 表 $tableName 存在，记录数: $count<br>";
            } catch (Exception $e) {
                echo "❌ 表 $tableName 不存在或无法访问<br>";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ 数据库连接失败: " . $e->getMessage() . "<br>";
    }
}

// 3. 测试站点添加功能
echo "<h2>3. 站点管理功能测试</h2>";
if (isset($pdo) && isset($dbManager)) {
    try {
        // 测试添加站点
        $testSiteName = "测试站点_" . date('His');
        $testSiteDomain = "https://test" . date('His') . ".example.com";
        
        // 生成站点key
        $host = parse_url($testSiteDomain, PHP_URL_HOST);
        $siteKey = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', str_replace(['www.', '.'], '', $host)));
        if (strlen($siteKey) < 3) {
            $siteKey = 'site_' . bin2hex(random_bytes(4));
        }
        
        // 生成API密钥
        $apiKey = $siteKey . '_' . bin2hex(random_bytes(16)) . '_' . date('Ymd');
        
        // 插入测试站点
        $stmt = $pdo->prepare("INSERT INTO {$dbManager->getTableName('sites')} 
            (site_key, name, domain, api_key, status, created_at) 
            VALUES (?, ?, ?, ?, 'active', NOW())");
        
        $result = $stmt->execute([$siteKey, $testSiteName, $testSiteDomain, $apiKey]);
        
        if ($result) {
            $testSiteId = $pdo->lastInsertId();
            echo "✅ 测试站点添加成功，ID: $testSiteId<br>";
            echo "站点Key: $siteKey<br>";
            echo "API密钥: $apiKey<br>";
            
            // 测试删除站点
            $stmt = $pdo->prepare("DELETE FROM {$dbManager->getTableName('sites')} WHERE id = ?");
            $deleteResult = $stmt->execute([$testSiteId]);
            
            if ($deleteResult && $stmt->rowCount() > 0) {
                echo "✅ 测试站点删除成功<br>";
            } else {
                echo "❌ 测试站点删除失败<br>";
            }
        } else {
            echo "❌ 测试站点添加失败<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ 站点管理功能测试失败: " . $e->getMessage() . "<br>";
    }
}

// 4. 测试IP验证配置
echo "<h2>4. IP验证配置测试</h2>";
if (isset($config)) {
    $ipVerificationEnabled = $config['ip_verification']['enabled'] ?? true;
    $strictMode = $config['ip_verification']['strict_mode'] ?? false;
    
    echo "IP验证状态: " . ($ipVerificationEnabled ? '✅ 已启用' : '❌ 已禁用') . "<br>";
    echo "严格模式: " . ($strictMode ? '✅ 已启用' : '❌ 已禁用') . "<br>";
    echo "最大下载次数: " . ($config['ip_verification']['max_downloads_per_token'] ?? '未设置') . "<br>";
    echo "令牌过期时间: " . ($config['ip_verification']['token_expiry_hours'] ?? '未设置') . " 小时<br>";
}

// 5. 测试管理面板访问
echo "<h2>5. 管理面板访问测试</h2>";
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$adminUrl = "$protocol://$host" . dirname($_SERVER['REQUEST_URI']) . "/admin/";

echo "管理面板地址: <a href='$adminUrl' target='_blank'>$adminUrl</a><br>";

// 测试管理面板是否可访问
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $adminUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✅ 管理面板可以正常访问<br>";
} else {
    echo "❌ 管理面板访问异常，HTTP状态码: $httpCode<br>";
}

// 6. 生成测试报告
echo "<h2>6. 测试总结</h2>";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>";
echo "<h3>✅ 测试完成</h3>";
echo "<p><strong>配置状态:</strong> " . (isset($config) ? '正常' : '异常') . "</p>";
echo "<p><strong>数据库状态:</strong> " . (isset($pdo) ? '正常' : '异常') . "</p>";
echo "<p><strong>管理面板:</strong> <a href='$adminUrl' target='_blank'>点击访问</a></p>";

if (isset($config['system']['master_admin_password'])) {
    echo "<p><strong>管理员密码:</strong> " . $config['system']['master_admin_password'] . "</p>";
}

echo "<p><strong>下一步操作:</strong></p>";
echo "<ol>";
echo "<li>访问管理面板并登录</li>";
echo "<li>添加实际的站点</li>";
echo "<li>配置各个分站</li>";
echo "<li>测试下载功能</li>";
echo "</ol>";
echo "</div>";

echo "<p><strong>⚠️ 安全提醒:</strong> 测试完成后请删除此文件！</p>";
?>

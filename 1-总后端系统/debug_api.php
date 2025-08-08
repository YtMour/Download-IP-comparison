<?php
/**
 * API调试工具
 * 用于检查API配置和连接状态
 */

// 设置错误显示
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 API调试工具</h1>";

// 1. 检查配置文件
echo "<h2>1. 配置文件检查</h2>";
$configFile = __DIR__ . '/admin/config_master.php';
if (file_exists($configFile)) {
    echo "✅ 配置文件存在: $configFile<br>";
    
    try {
        $config = require $configFile;
        if (is_array($config)) {
            echo "✅ 配置文件格式正确<br>";
            
            // 检查必要配置项
            if (isset($config['database'])) {
                echo "✅ 数据库配置存在<br>";
                echo "数据库类型: " . ($config['database']['type'] ?? '未设置') . "<br>";
                echo "数据库主机: " . ($config['database']['host'] ?? '未设置') . "<br>";
                echo "数据库名称: " . ($config['database']['database'] ?? '未设置') . "<br>";
            } else {
                echo "❌ 数据库配置缺失<br>";
            }
            
            if (isset($config['sites'])) {
                echo "✅ 站点配置存在<br>";
                echo "注册站点数量: " . count($config['sites']) . "<br>";
                foreach ($config['sites'] as $key => $site) {
                    echo "- $key: " . ($site['name'] ?? '未命名') . " (API Key: " . (isset($site['api_key']) ? substr($site['api_key'], 0, 10) . '...' : '未设置') . ")<br>";
                }
            } else {
                echo "❌ 站点配置缺失<br>";
            }
        } else {
            echo "❌ 配置文件格式错误<br>";
        }
    } catch (Exception $e) {
        echo "❌ 配置文件加载失败: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ 配置文件不存在: $configFile<br>";
}

// 2. 检查数据库连接
echo "<h2>2. 数据库连接检查</h2>";
if (isset($config) && isset($config['database'])) {
    try {
        require_once __DIR__ . '/admin/database_manager.php';
        $dbManager = new MultiSiteDatabaseManager($config);
        $pdo = $dbManager->getPDO();
        
        echo "✅ 数据库连接成功<br>";
        
        // 检查表是否存在
        $tables = ['sites', 'downloads', 'ip_verifications'];
        foreach ($tables as $table) {
            $fullTableName = $dbManager->getTableName($table);
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM $fullTableName");
                $count = $stmt->fetchColumn();
                echo "✅ 表 $fullTableName 存在，记录数: $count<br>";
            } catch (Exception $e) {
                echo "❌ 表 $fullTableName 不存在或无法访问<br>";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ 数据库连接失败: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ 无法检查数据库连接，配置缺失<br>";
}

// 3. 检查文件权限
echo "<h2>3. 文件权限检查</h2>";
$checkDirs = [
    __DIR__ . '/downloads',
    __DIR__ . '/files'
];

foreach ($checkDirs as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "✅ 目录 $dir 可写<br>";
        } else {
            echo "❌ 目录 $dir 不可写<br>";
        }
    } else {
        echo "❌ 目录 $dir 不存在<br>";
    }
}

// 检查下载器文件
$downloaderFile = __DIR__ . '/downloader.exe';
if (file_exists($downloaderFile)) {
    echo "✅ 下载器文件存在: " . round(filesize($downloaderFile) / 1024 / 1024, 2) . " MB<br>";
} else {
    echo "❌ 下载器文件不存在: $downloaderFile<br>";
}

// 4. 测试API接口
echo "<h2>4. API接口测试</h2>";
if (isset($config['sites']) && !empty($config['sites'])) {
    $firstSite = array_values($config['sites'])[0];
    $apiKey = $firstSite['api_key'] ?? '';
    
    if ($apiKey) {
        echo "使用API Key: " . substr($apiKey, 0, 10) . "...<br>";
        
        // 构建测试URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $apiUrl = "$protocol://$host" . dirname($_SERVER['REQUEST_URI']) . "/api/download_api.php?action=stats";
        
        echo "测试URL: $apiUrl<br>";
        
        // 使用cURL测试
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['api_key' => $apiKey]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $apiKey,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "❌ cURL错误: $error<br>";
        } else {
            echo "HTTP状态码: $httpCode<br>";
            if ($httpCode === 200) {
                echo "✅ API响应成功<br>";
                $data = json_decode($response, true);
                if ($data) {
                    echo "响应数据: <pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
                } else {
                    echo "❌ 响应不是有效的JSON: <pre>" . htmlspecialchars($response) . "</pre>";
                }
            } else {
                echo "❌ API响应错误<br>";
                echo "响应内容: <pre>" . htmlspecialchars($response) . "</pre>";
            }
        }
    } else {
        echo "❌ 无法测试API，API Key未设置<br>";
    }
} else {
    echo "❌ 无法测试API，站点配置缺失<br>";
}

// 5. PHP环境检查
echo "<h2>5. PHP环境检查</h2>";
echo "PHP版本: " . PHP_VERSION . "<br>";

$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'zip', 'curl'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ 扩展 $ext 已加载<br>";
    } else {
        echo "❌ 扩展 $ext 未加载<br>";
    }
}

echo "<h2>6. 建议</h2>";
echo "<ul>";
echo "<li>如果看到任何❌错误，请先解决这些问题</li>";
echo "<li>确保数据库连接正常</li>";
echo "<li>确保文件权限正确设置</li>";
echo "<li>如果API测试失败，检查配置文件中的API密钥</li>";
echo "<li>调试完成后，请删除此文件以确保安全</li>";
echo "</ul>";

echo "<p><strong>调试完成时间:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>

<?php
/**
 * 分站调试工具
 * 用于检查分站配置和连接状态
 */

// 设置错误显示
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 分站调试工具</h1>";

// 1. 检查download_handler.php文件
echo "<h2>1. 文件检查</h2>";
$handlerFile = __DIR__ . '/download_handler.php';
if (file_exists($handlerFile)) {
    echo "✅ download_handler.php 文件存在<br>";
    
    // 检查文件内容
    $content = file_get_contents($handlerFile);
    if (strpos($content, 'class SiteDownloadHandler') !== false) {
        echo "✅ 文件包含SiteDownloadHandler类<br>";
    } else {
        echo "❌ 文件不包含SiteDownloadHandler类<br>";
    }
    
    // 检查配置
    if (preg_match("/site_key['\"]?\s*=>\s*['\"]([^'\"]+)['\"]/",$content, $matches)) {
        echo "✅ 站点标识: " . $matches[1] . "<br>";
    } else {
        echo "❌ 未找到站点标识配置<br>";
    }
    
    if (preg_match("/api_key['\"]?\s*=>\s*['\"]([^'\"]+)['\"]/",$content, $matches)) {
        echo "✅ API密钥: " . substr($matches[1], 0, 10) . "...<br>";
    } else {
        echo "❌ 未找到API密钥配置<br>";
    }
    
    if (preg_match("/storage_server['\"]?\s*=>\s*['\"]([^'\"]+)['\"]/",$content, $matches)) {
        echo "✅ 存储服务器: " . $matches[1] . "<br>";
    } else {
        echo "❌ 未找到存储服务器配置<br>";
    }
    
} else {
    echo "❌ download_handler.php 文件不存在<br>";
}

// 2. 检查intercept.js文件
echo "<h2>2. JavaScript文件检查</h2>";
$jsFile = __DIR__ . '/intercept.js';
if (file_exists($jsFile)) {
    echo "✅ intercept.js 文件存在<br>";
    
    $jsContent = file_get_contents($jsFile);
    if (strpos($jsContent, 'SITE_CONFIG') !== false) {
        echo "✅ 文件包含SITE_CONFIG配置<br>";
    } else {
        echo "❌ 文件不包含SITE_CONFIG配置<br>";
    }
} else {
    echo "❌ intercept.js 文件不存在<br>";
}

// 3. 测试download_handler.php
echo "<h2>3. 处理器测试</h2>";
if (file_exists($handlerFile)) {
    echo "测试stats接口...<br>";
    
    // 构建测试URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $testUrl = "$protocol://$host" . dirname($_SERVER['REQUEST_URI']) . "/download_handler.php?action=stats";
    
    echo "测试URL: $testUrl<br>";
    
    // 使用cURL测试
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ cURL错误: $error<br>";
    } else {
        echo "HTTP状态码: $httpCode<br>";
        if ($httpCode === 200) {
            echo "✅ 处理器响应成功<br>";
            $data = json_decode($response, true);
            if ($data) {
                echo "响应数据: <pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            } else {
                echo "❌ 响应不是有效的JSON<br>";
                echo "原始响应: <pre>" . htmlspecialchars($response) . "</pre>";
            }
        } else {
            echo "❌ 处理器响应错误<br>";
            echo "响应内容: <pre>" . htmlspecialchars($response) . "</pre>";
        }
    }
}

// 4. 测试到总后端的连接
echo "<h2>4. 总后端连接测试</h2>";
if (isset($matches) && !empty($matches)) {
    // 从配置中提取存储服务器地址
    $content = file_get_contents($handlerFile);
    if (preg_match("/storage_server['\"]?\s*=>\s*['\"]([^'\"]+)['\"]/",$content, $serverMatches)) {
        $storageServer = $serverMatches[1];
        $backendUrl = $storageServer . "/api/download_api.php?action=stats";
        
        echo "总后端URL: $backendUrl<br>";
        
        // 提取API密钥
        if (preg_match("/api_key['\"]?\s*=>\s*['\"]([^'\"]+)['\"]/",$content, $keyMatches)) {
            $apiKey = $keyMatches[1];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $backendUrl);
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
                echo "❌ 连接总后端失败: $error<br>";
            } else {
                echo "总后端HTTP状态码: $httpCode<br>";
                if ($httpCode === 200) {
                    echo "✅ 总后端连接成功<br>";
                    $data = json_decode($response, true);
                    if ($data) {
                        echo "总后端响应: <pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
                    } else {
                        echo "❌ 总后端响应不是有效的JSON<br>";
                        echo "原始响应: <pre>" . htmlspecialchars($response) . "</pre>";
                    }
                } else {
                    echo "❌ 总后端响应错误<br>";
                    echo "响应内容: <pre>" . htmlspecialchars($response) . "</pre>";
                }
            }
        } else {
            echo "❌ 无法提取API密钥<br>";
        }
    } else {
        echo "❌ 无法提取存储服务器地址<br>";
    }
}

// 5. PHP环境检查
echo "<h2>5. PHP环境检查</h2>";
echo "PHP版本: " . PHP_VERSION . "<br>";

$requiredExtensions = ['curl', 'json'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ 扩展 $ext 已加载<br>";
    } else {
        echo "❌ 扩展 $ext 未加载<br>";
    }
}

// 6. 网络连接检查
echo "<h2>6. 网络连接检查</h2>";
$testHosts = ['google.com', 'baidu.com'];
foreach ($testHosts as $host) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://$host");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "✅ 可以连接到 $host<br>";
    } else {
        echo "❌ 无法连接到 $host (HTTP: $httpCode)<br>";
    }
}

echo "<h2>7. 建议</h2>";
echo "<ul>";
echo "<li>如果处理器测试失败，检查download_handler.php的配置</li>";
echo "<li>如果总后端连接失败，检查API密钥和网络连接</li>";
echo "<li>确保站点标识、API密钥与总后端配置一致</li>";
echo "<li>调试完成后，请删除此文件以确保安全</li>";
echo "</ul>";

echo "<p><strong>调试完成时间:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>

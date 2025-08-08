<?php
/**
 * 部署检查脚本
 * 检查系统是否正确部署和配置
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>部署检查 - 下载拦截系统</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 40px; padding: 20px; background: #007bff; color: white; border-radius: 10px; }
        .check-item { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .btn { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; display: inline-block; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔍 部署检查</h1>
        <p>检查下载拦截系统是否正确部署</p>
    </div>

    <?php
    $checks = [];
    
    // 1. 检查核心文件
    $coreFiles = [
        'handler.php' => '处理器',
        'interceptor.js' => '拦截器',
        'config.html' => '配置页面',
        'test.html' => '测试页面'
    ];
    
    echo "<div class='check-item'>";
    echo "<h3>📁 核心文件检查</h3>";
    
    $allFilesExist = true;
    foreach ($coreFiles as $file => $desc) {
        if (file_exists($file)) {
            echo "<div class='success'>✅ $desc ($file) - 存在</div>";
        } else {
            echo "<div class='error'>❌ $desc ($file) - 缺失</div>";
            $allFilesExist = false;
        }
    }
    
    if ($allFilesExist) {
        echo "<div class='success'><strong>✅ 所有核心文件都存在</strong></div>";
    } else {
        echo "<div class='error'><strong>❌ 缺少核心文件，请重新上传</strong></div>";
    }
    echo "</div>";
    
    // 2. 检查PHP环境
    echo "<div class='check-item'>";
    echo "<h3>🔧 PHP环境检查</h3>";
    
    $phpVersion = phpversion();
    if (version_compare($phpVersion, '7.0', '>=')) {
        echo "<div class='success'>✅ PHP版本: $phpVersion (支持)</div>";
    } else {
        echo "<div class='error'>❌ PHP版本: $phpVersion (需要7.0+)</div>";
    }
    
    $requiredExtensions = ['json', 'curl'];
    foreach ($requiredExtensions as $ext) {
        if (extension_loaded($ext)) {
            echo "<div class='success'>✅ $ext 扩展 - 已加载</div>";
        } else {
            echo "<div class='error'>❌ $ext 扩展 - 未加载</div>";
        }
    }
    
    echo "</div>";
    
    // 3. 检查文件权限
    echo "<div class='check-item'>";
    echo "<h3>🔐 文件权限检查</h3>";
    
    $currentDir = __DIR__;
    if (is_writable($currentDir)) {
        echo "<div class='success'>✅ 目录可写 - 可以保存配置</div>";
    } else {
        echo "<div class='warning'>⚠️ 目录不可写 - 配置保存可能失败</div>";
    }
    
    $configFile = 'config.json';
    if (file_exists($configFile)) {
        if (is_writable($configFile)) {
            echo "<div class='success'>✅ 配置文件可写</div>";
        } else {
            echo "<div class='warning'>⚠️ 配置文件不可写</div>";
        }
    } else {
        echo "<div class='info'>ℹ️ 配置文件不存在（首次运行正常）</div>";
    }
    
    echo "</div>";
    
    // 4. 检查处理器
    echo "<div class='check-item'>";
    echo "<h3>⚙️ 处理器检查</h3>";
    
    if (file_exists('handler.php')) {
        try {
            $url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/handler.php?action=stats';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HEADER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($response === false) {
                echo "<div class='error'>❌ 处理器连接失败: $error</div>";
            } else if ($httpCode === 200) {
                $data = json_decode($response, true);
                if ($data && isset($data['success']) && $data['success']) {
                    echo "<div class='success'>✅ 处理器工作正常</div>";
                    echo "<div class='info'>站点: {$data['data']['site_name']}</div>";
                    echo "<div class='info'>模式: " . ($data['data']['demo_mode'] ? '演示模式' : '生产模式') . "</div>";
                    echo "<div class='info'>API密钥: {$data['data']['api_key']}</div>";
                } else {
                    echo "<div class='error'>❌ 处理器返回错误: $response</div>";
                }
            } else {
                echo "<div class='error'>❌ 处理器HTTP错误: $httpCode</div>";
                echo "<div class='info'>响应: " . htmlspecialchars(substr($response, 0, 200)) . "</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>❌ 处理器测试异常: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='error'>❌ 处理器文件不存在</div>";
    }
    
    echo "</div>";
    
    // 5. 检查网络连接
    echo "<div class='check-item'>";
    echo "<h3>🌐 网络连接检查</h3>";
    
    $testUrls = [
        'https://dw.ytmour.art/api/download_api.php' => '总后台API',
        'https://api.ipify.org?format=json' => 'IP获取服务'
    ];
    
    foreach ($testUrls as $url => $name) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            echo "<div class='error'>❌ $name 连接失败: $error</div>";
        } else if ($httpCode === 200) {
            echo "<div class='success'>✅ $name 连接正常</div>";
        } else {
            echo "<div class='warning'>⚠️ $name HTTP $httpCode</div>";
        }
    }
    
    echo "</div>";
    
    // 6. 部署建议
    echo "<div class='check-item info'>";
    echo "<h3>💡 部署建议</h3>";
    echo "<ul>";
    echo "<li><strong>如果所有检查都通过：</strong> 系统已正确部署，可以开始使用</li>";
    echo "<li><strong>如果有错误：</strong> 请根据错误信息修复问题后重新检查</li>";
    echo "<li><strong>配置系统：</strong> 访问 <a href='config.html'>config.html</a> 进行配置</li>";
    echo "<li><strong>测试功能：</strong> 访问 <a href='test.html'>test.html</a> 测试功能</li>";
    echo "</ul>";
    echo "</div>";
    
    // 7. 快速操作
    echo "<div class='check-item'>";
    echo "<h3>🚀 快速操作</h3>";
    echo "<p>";
    echo "<a href='config.html' class='btn'>⚙️ 配置系统</a> ";
    echo "<a href='test.html' class='btn'>🧪 测试功能</a> ";
    echo "<a href='check.php' class='btn'>🔄 重新检查</a>";
    echo "</p>";
    echo "</div>";
    ?>
    
    <script>
        // 自动刷新功能
        function autoRefresh() {
            if (confirm('是否每30秒自动刷新检查结果？')) {
                setInterval(() => {
                    window.location.reload();
                }, 30000);
                alert('自动刷新已启用');
            }
        }
        
        // 页面加载完成后询问
        window.addEventListener('load', () => {
            setTimeout(autoRefresh, 2000);
        });
    </script>
</body>
</html>

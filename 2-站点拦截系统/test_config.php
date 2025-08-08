<?php
/**
 * 配置测试工具
 */

header('Content-Type: text/html; charset=utf-8');

// 包含处理器的配置函数
$configFile = __DIR__ . '/config.json';

$defaultConfig = [
    'site_name' => '演示站点',
    'site_key' => 'demo',
    'api_key' => '',
    'storage_server' => 'https://dw.ytmour.art',
    'demo_mode' => true
];

function loadConfig() {
    global $configFile, $defaultConfig;
    
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if ($config) {
            $merged = array_merge($defaultConfig, $config);
            return $merged;
        }
    }
    
    return $defaultConfig;
}

function saveConfig($config) {
    global $configFile;
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$config = loadConfig();

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>配置测试</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .info { background: #d1ecf1; color: #0c5460; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .btn { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; margin: 5px; }
        input { padding: 8px; margin: 5px; border: 1px solid #ddd; border-radius: 3px; width: 300px; }
    </style>
</head>
<body>
    <h1>🔧 配置测试工具</h1>
    
    <div class="section">
        <h3>📁 配置文件状态</h3>
        <?php if (file_exists($configFile)): ?>
            <div class="success">✅ 配置文件存在: <?= $configFile ?></div>
            <div class="info">文件大小: <?= filesize($configFile) ?> 字节</div>
            <div class="info">修改时间: <?= date('Y-m-d H:i:s', filemtime($configFile)) ?></div>
        <?php else: ?>
            <div class="error">❌ 配置文件不存在: <?= $configFile ?></div>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h3>⚙️ 当前配置</h3>
        <pre><?= json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
        
        <h4>配置分析:</h4>
        <ul>
            <li><strong>API密钥:</strong> <?= empty($config['api_key']) ? '❌ 未配置' : '✅ 已配置 (' . strlen($config['api_key']) . ' 字符)' ?></li>
            <li><strong>演示模式:</strong> <?= $config['demo_mode'] ? '🧪 是' : '🚀 否' ?></li>
            <li><strong>服务器地址:</strong> <?= $config['storage_server'] ?></li>
        </ul>
    </div>
    
    <div class="section">
        <h3>🧪 测试配置保存</h3>
        <form method="post">
            <p>
                <label>服务器地址:</label><br>
                <input type="url" name="storage_server" value="<?= htmlspecialchars($config['storage_server']) ?>" required>
            </p>
            <p>
                <label>API密钥:</label><br>
                <input type="text" name="api_key" value="<?= htmlspecialchars($config['api_key']) ?>" placeholder="留空为演示模式">
            </p>
            <p>
                <button type="submit" name="action" value="save" class="btn">💾 保存配置</button>
                <button type="submit" name="action" value="reset" class="btn" style="background: #dc3545;">🔄 重置配置</button>
            </p>
        </form>
        
        <?php
        if ($_POST['action'] ?? '' === 'save') {
            $newConfig = [
                'site_name' => '分站系统',
                'site_key' => 'site_' . substr(md5($_POST['storage_server']), 0, 8),
                'api_key' => trim($_POST['api_key']),
                'storage_server' => trim($_POST['storage_server']),
                'demo_mode' => empty(trim($_POST['api_key']))
            ];
            
            if (saveConfig($newConfig)) {
                echo '<div class="success">✅ 配置保存成功！</div>';
                echo '<script>setTimeout(() => location.reload(), 1000);</script>';
            } else {
                echo '<div class="error">❌ 配置保存失败！</div>';
            }
        }
        
        if ($_POST['action'] ?? '' === 'reset') {
            if (file_exists($configFile)) {
                unlink($configFile);
                echo '<div class="success">✅ 配置已重置！</div>';
                echo '<script>setTimeout(() => location.reload(), 1000);</script>';
            }
        }
        ?>
    </div>
    
    <div class="section">
        <h3>🔍 处理器测试</h3>
        <button onclick="testHandler()" class="btn">测试处理器</button>
        <div id="handler-result"></div>
        
        <script>
        async function testHandler() {
            const resultDiv = document.getElementById('handler-result');
            
            try {
                resultDiv.innerHTML = '<div class="info">正在测试...</div>';
                
                const response = await fetch('./handler.php?action=stats');
                const result = await response.json();
                
                if (result.success) {
                    resultDiv.innerHTML = `
                        <div class="success">✅ 处理器工作正常</div>
                        <pre>${JSON.stringify(result, null, 2)}</pre>
                    `;
                } else {
                    resultDiv.innerHTML = `<div class="error">❌ 处理器错误: ${result.message}</div>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<div class="error">❌ 测试失败: ${error.message}</div>`;
            }
        }
        </script>
    </div>
    
    <div class="section">
        <h3>📋 文件权限检查</h3>
        <?php
        $dir = __DIR__;
        $writable = is_writable($dir);
        ?>
        <ul>
            <li><strong>目录:</strong> <?= $dir ?></li>
            <li><strong>可写:</strong> <?= $writable ? '✅ 是' : '❌ 否' ?></li>
            <?php if (file_exists($configFile)): ?>
            <li><strong>配置文件可写:</strong> <?= is_writable($configFile) ? '✅ 是' : '❌ 否' ?></li>
            <?php endif; ?>
        </ul>
        
        <?php if (!$writable): ?>
        <div class="error">❌ 目录不可写，无法保存配置文件！</div>
        <?php endif; ?>
    </div>
</body>
</html>

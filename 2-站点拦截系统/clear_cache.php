<?php
/**
 * 缓存清理工具
 * 用于清理PHP和浏览器缓存
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🧹 缓存清理工具</h1>";

// 1. 清理PHP OPcache
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "<p>✅ PHP OPcache 已清理</p>";
    } else {
        echo "<p>❌ PHP OPcache 清理失败</p>";
    }
} else {
    echo "<p>ℹ️ PHP OPcache 未启用</p>";
}

// 2. 清理会话
session_start();
session_destroy();
echo "<p>✅ PHP Session 已清理</p>";

// 3. 输出浏览器缓存清理指令
echo "<h2>🌐 浏览器缓存清理</h2>";
echo "<p>请按以下步骤清理浏览器缓存：</p>";
echo "<ol>";
echo "<li>按 <strong>Ctrl + F5</strong> 强制刷新页面</li>";
echo "<li>或按 <strong>F12</strong> 打开开发者工具，右键刷新按钮选择"清空缓存并硬性重新加载"</li>";
echo "<li>或在浏览器设置中清理缓存和Cookie</li>";
echo "</ol>";

// 4. 输出JavaScript缓存清理
echo "<h2>📜 JavaScript缓存清理</h2>";
echo "<script>";
echo "console.log('🧹 清理本地存储...');";
echo "localStorage.clear();";
echo "sessionStorage.clear();";
echo "console.log('✅ 本地存储已清理');";
echo "</script>";
echo "<p>✅ JavaScript 本地存储已清理</p>";

// 5. 检查文件修改时间
echo "<h2>📁 文件状态检查</h2>";
$files = [
    'interceptor.js',
    'download_interceptor_new.js',
    'handler.php',
    'test.html'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $mtime = filemtime($file);
        echo "<p>📄 $file - 修改时间: " . date('Y-m-d H:i:s', $mtime) . "</p>";
    } else {
        echo "<p>❌ $file - 文件不存在</p>";
    }
}

// 6. 生成带时间戳的链接
echo "<h2>🔗 带时间戳的测试链接</h2>";
$timestamp = time();
echo "<p><a href='test.html?v=8.0&t=$timestamp' target='_blank'>🧪 测试页面 (v8.0)</a></p>";
echo "<p><a href='test_download_v8.html?v=8.0&t=$timestamp' target='_blank'>🚀 新版测试页面 (v8.0)</a></p>";

echo "<h2>✅ 缓存清理完成</h2>";
echo "<p>当前时间: " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>建议：</strong>清理完成后，请关闭浏览器重新打开，然后访问测试页面。</p>";

// 7. 自动刷新选项
echo "<h2>🔄 自动刷新</h2>";
echo "<button onclick='location.reload(true)'>强制刷新本页面</button>";
echo "<button onclick='clearBrowserCache()'>清理浏览器缓存</button>";

echo "<script>";
echo "function clearBrowserCache() {";
echo "  if ('caches' in window) {";
echo "    caches.keys().then(function(names) {";
echo "      names.forEach(function(name) {";
echo "        caches.delete(name);";
echo "      });";
echo "    });";
echo "    console.log('✅ Service Worker 缓存已清理');";
echo "  }";
echo "  localStorage.clear();";
echo "  sessionStorage.clear();";
echo "  alert('浏览器缓存清理完成！请按 Ctrl+F5 刷新页面。');";
echo "}";
echo "</script>";
?>

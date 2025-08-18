<?php
/**
 * 数据库升级脚本 - 修复token字段长度
 */

require_once '../config.php';
require_once 'database_manager.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $dbManager = new MultiSiteDatabaseManager($config);
    $pdo = $dbManager->getPDO();
    
    echo "<h1>🔧 数据库升级 - 修复Token字段长度</h1>";
    
    // 检查当前字段长度
    $stmt = $pdo->query("DESCRIBE {$dbManager->getTableName('downloads')} token");
    $tokenField = $stmt->fetch();
    
    echo "<h3>📋 当前状态</h3>";
    echo "<p><strong>Token字段类型:</strong> {$tokenField['Type']}</p>";
    
    if (strpos($tokenField['Type'], 'varchar(32)') !== false) {
        echo "<p style='color: red;'>❌ 字段长度不足，需要升级</p>";
        
        // 执行升级
        echo "<h3>🔄 执行升级</h3>";
        
        // 修改downloads表的token字段
        $sql1 = "ALTER TABLE {$dbManager->getTableName('downloads')} MODIFY COLUMN token VARCHAR(64) UNIQUE NOT NULL";
        $pdo->exec($sql1);
        echo "<p>✅ 修改downloads表token字段: VARCHAR(32) → VARCHAR(64)</p>";
        
        // 修改ip_verifications表的token字段
        $sql2 = "ALTER TABLE {$dbManager->getTableName('ip_verifications')} MODIFY COLUMN token VARCHAR(64) NOT NULL";
        $pdo->exec($sql2);
        echo "<p>✅ 修改ip_verifications表token字段: VARCHAR(32) → VARCHAR(64)</p>";
        
        // 验证修改结果
        $stmt = $pdo->query("DESCRIBE {$dbManager->getTableName('downloads')} token");
        $newTokenField = $stmt->fetch();
        
        echo "<h3>✅ 升级完成</h3>";
        echo "<p><strong>新的Token字段类型:</strong> {$newTokenField['Type']}</p>";
        
        if (strpos($newTokenField['Type'], 'varchar(64)') !== false) {
            echo "<p style='color: green;'>✅ 升级成功！Token字段现在支持64字符长度</p>";
        } else {
            echo "<p style='color: red;'>❌ 升级失败，请检查数据库权限</p>";
        }
        
    } else if (strpos($tokenField['Type'], 'varchar(64)') !== false) {
        echo "<p style='color: green;'>✅ 字段长度已经正确，无需升级</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ 字段类型异常: {$tokenField['Type']}</p>";
    }
    
    echo "<h3>📊 Token长度分析</h3>";
    echo "<ul>";
    echo "<li><strong>站点前缀:</strong> 3字符 (如: abc)</li>";
    echo "<li><strong>分隔符:</strong> 1字符 (_)</li>";
    echo "<li><strong>时间戳:</strong> 10字符 (如: 1704672000)</li>";
    echo "<li><strong>分隔符:</strong> 1字符 (_)</li>";
    echo "<li><strong>随机字符串:</strong> 24字符 (12字节的hex)</li>";
    echo "<li><strong>总长度:</strong> 39字符</li>";
    echo "</ul>";
    echo "<p><strong>结论:</strong> 需要至少40字符的字段长度，设置为64字符提供充足余量。</p>";
    
    echo "<h3>🧪 测试Token生成</h3>";
    
    // 模拟token生成
    $timestamp = time();
    $random = bin2hex(random_bytes(12));
    $sitePrefix = 'abc'; // 示例前缀
    $testToken = $sitePrefix . '_' . $timestamp . '_' . $random;
    
    echo "<p><strong>示例Token:</strong> <code>$testToken</code></p>";
    echo "<p><strong>长度:</strong> " . strlen($testToken) . " 字符</p>";
    
    if (strlen($testToken) <= 64) {
        echo "<p style='color: green;'>✅ Token长度在允许范围内</p>";
    } else {
        echo "<p style='color: red;'>❌ Token长度超出字段限制</p>";
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ 升级失败</h3>";
    echo "<p>错误信息: " . $e->getMessage() . "</p>";
    echo "<p>请检查数据库连接和权限设置。</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>← 返回管理面板</a></p>";
?>

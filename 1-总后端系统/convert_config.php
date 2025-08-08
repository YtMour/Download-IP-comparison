<?php
/**
 * 配置转换工具
 * 将简化的INI配置转换为PHP配置文件
 */

echo "🔧 配置转换工具\n";
echo "================\n\n";

$iniFile = __DIR__ . '/config_simple.ini';
$phpFile = __DIR__ . '/admin/config_master.php';

if (!file_exists($iniFile)) {
    die("❌ 配置文件不存在: $iniFile\n");
}

$config = parse_ini_file($iniFile, true);

if (!$config) {
    die("❌ 配置文件格式错误\n");
}

echo "✅ 读取配置文件成功\n";

// 转换站点配置
$sites = [];
if (isset($config['sites'])) {
    foreach ($config['sites'] as $key => $value) {
        $parts = explode('|', $value);
        if (count($parts) === 3) {
            $sites[$key] = [
                'name' => trim($parts[0]),
                'domain' => trim($parts[1]),
                'status' => 'active',
                'api_key' => trim($parts[2]),
                'storage_path' => $key,
                'admin_email' => 'admin@example.com',
                'created_at' => date('Y-m-d')
            ];
        }
    }
}

echo "✅ 转换站点配置: " . count($sites) . " 个站点\n";

// 生成PHP配置文件
$phpConfig = <<<PHP
<?php
/**
 * 多站点管理系统 - 主配置文件
 * 自动生成于: {date('Y-m-d H:i:s')}
 */

return [
    // 存储服务器配置
    'storage_server' => [
        'domain' => '{$config['server']['domain']}',
        'admin_path' => '/admin',
        'api_path' => '/api',
        'files_path' => '/files',
        'downloads_path' => '/downloads'
    ],
    
    // 数据库配置
    'database' => [
        'type' => 'mysql',
        'host' => '{$config['database']['host']}',
        'port' => {$config['database']['port']},
        'database' => '{$config['database']['database']}',
        'username' => '{$config['database']['username']}',
        'password' => '{$config['database']['password']}',
        'charset' => 'utf8mb4',
        'prefix' => 'msd_'
    ],
    
    // 注册的站点列表
    'sites' => [
PHP;

foreach ($sites as $key => $site) {
    $phpConfig .= "\n        '$key' => [\n";
    foreach ($site as $prop => $value) {
        $phpConfig .= "            '$prop' => '$value',\n";
    }
    $phpConfig .= "        ],";
}

$phpConfig .= <<<PHP

    ],
    
    // 系统配置
    'system' => [
        'master_admin_password' => '{$config['server']['admin_password']}',
        'auto_create_tables' => true,
        'auto_backup_enabled' => true,
        'backup_retention_days' => 30,
        'log_retention_days' => 90,
        'max_file_size' => '5GB'
    ],
    
    // IP验证全局配置
    'ip_verification' => [
        'enabled' => {($config['ip_verification']['enabled'] === 'true' ? 'true' : 'false')},
        'strict_mode' => {($config['ip_verification']['strict_mode'] === 'true' ? 'true' : 'false')},
        'max_downloads_per_token' => {$config['ip_verification']['max_downloads']},
        'token_expiry_hours' => {$config['ip_verification']['token_expiry_hours']},
        'cleanup_expired_hours' => 48
    ],
    
    // 安全配置
    'security' => [
        'api_rate_limit' => 1000,
        'max_concurrent_downloads' => 50,
        'require_https' => true
    ]
];
?>
PHP;

// 备份原配置文件
if (file_exists($phpFile)) {
    $backupFile = $phpFile . '.backup.' . date('YmdHis');
    if (copy($phpFile, $backupFile)) {
        echo "✅ 原配置文件已备份: $backupFile\n";
    }
}

// 写入新配置文件
if (file_put_contents($phpFile, $phpConfig)) {
    echo "✅ PHP配置文件生成成功: $phpFile\n";
} else {
    die("❌ 无法写入PHP配置文件\n");
}

echo "\n🎉 配置转换完成！\n\n";

echo "📋 站点配置摘要：\n";
foreach ($sites as $key => $site) {
    echo "- $key: {$site['name']} ({$site['domain']})\n";
    echo "  API Key: {$site['api_key']}\n\n";
}

echo "🔗 下一步操作：\n";
echo "1. 访问管理面板: {$config['server']['domain']}/admin/\n";
echo "2. 使用密码登录: {$config['server']['admin_password']}\n";
echo "3. 检查站点配置是否正确\n";
echo "4. 部署各个分站\n\n";

echo "⚠️ 安全提醒：\n";
echo "- 请及时修改管理员密码\n";
echo "- 请妥善保管API密钥\n";
echo "- 部署完成后删除此工具\n";
?>

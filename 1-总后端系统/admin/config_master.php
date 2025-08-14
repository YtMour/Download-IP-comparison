<?php
/**
 * 多站点管理系统 - 主配置文件
 * 简化版配置，只包含必要的设置
 */

return [
    // 存储服务器配置
    'storage_server' => [
        'domain' => 'https://dw.ytmour.art',
        'admin_path' => '/admin',
        'api_path' => '/api',
        'files_path' => '/files',
        'downloads_path' => '/downloads'
    ],

    // 数据库配置
    'database' => [
        'type' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'multi_site_downloads',
        'username' => 'download_admin',
        'password' => 'your_password',
        'charset' => 'utf8mb4',
        'prefix' => 'msd_'
    ],

    // 注册的站点列表 (通过管理面板动态添加)
    'sites' => [
        // 站点将通过管理面板添加，这里保持空数组
    ],

    // 系统配置
    'system' => [
        'master_admin_password' => 'admin123456',
        'auto_create_tables' => true,
        'max_file_size' => '5GB',
        'timezone' => 'Asia/Shanghai'
    ],

    // IP验证全局配置
    'ip_verification' => [
        'enabled' => true,
        'strict_mode' => false,
        'max_downloads_per_token' => 5,
        'token_expiry_hours' => 24,
        'cleanup_expired_hours' => 48
    ],

    // 下载器配置
    'downloader' => [
        'show_log' => true  // 控制下载器是否显示操作日志窗口
    ],

    // 安全配置
    'security' => [
        'api_rate_limit' => 1000,
        'max_concurrent_downloads' => 50,
        'require_https' => true
    ]
];
?>
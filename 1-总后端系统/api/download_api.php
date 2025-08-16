<?php
/**
 * 统一下载API - 服务所有分站
 * 部署在存储服务器 dw.ytmour.art/api/
 */

// 关闭错误显示，防止HTML错误信息混入JSON响应
error_reporting(0);
ini_set('display_errors', 0);

// 设置JSON响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 全局错误处理函数
function sendJsonError($message, $code = 500) {
    // 简单记录错误到PHP错误日志，避免递归调用
    error_log("API Error: $message (Code: $code)");

    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// 日志记录函数 - 简化版本避免复杂字符串处理
function writeLog($type, $action, $details = []) {
    try {
        $logsDir = dirname(__DIR__) . '/logs';

        // 确保logs目录存在
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }

        // 确保.htaccess保护文件存在
        $htaccessFile = $logsDir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Order Deny,Allow\nDeny from all\n");
        }

        $logFiles = [
            'system' => $logsDir . '/download_system.log',
            'access' => $logsDir . '/access.log',
            'error' => $logsDir . '/error.log',
            'api' => $logsDir . '/api.log',
            'download' => $logsDir . '/download.log'
        ];

        $logFile = $logFiles[$type] ?? $logFiles['system'];

        $timestamp = date('Y-m-d H:i:s');
        // 使用传递的IP信息，如果没有则使用服务器检测的IP
        $clientIP = $details['client_ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $siteName = isset($details['site']) ? $details['site'] : 'unknown';

        // 简单安全的日志格式 - 只使用ASCII字符
        $logEntry = "[$timestamp] $siteName $clientIP $action";

        // 添加重要信息
        if (!empty($details)) {
            $info = [];

            if (isset($details['software_name'])) {
                $info[] = "software=" . $details['software_name'];
            }
            if (isset($details['token'])) {
                $tokenShort = substr($details['token'], 0, 12) . "...";
                $info[] = "token=$tokenShort";
            }
            if (isset($details['result'])) {
                $info[] = "result=" . $details['result'];
            }
            if (isset($details['original_ip'])) {
                $info[] = "original_ip=" . $details['original_ip'];
            }
            if (isset($details['current_ip'])) {
                $info[] = "current_ip=" . $details['current_ip'];
            }
            if (isset($details['expires_at'])) {
                $info[] = "expires=" . $details['expires_at'];
            }
            if (isset($details['error'])) {
                $info[] = "error=" . $details['error'];
            }

            if (!empty($info)) {
                $logEntry .= " | " . implode(" ", $info);
            }
        }

        $logEntry .= "\n";

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    } catch (Exception $e) {
        // 如果日志记录失败，记录到PHP错误日志
        error_log("WriteLog Error: " . $e->getMessage());
    }
}

// 设置异常处理器
set_exception_handler(function($exception) {
    sendJsonError('系统错误: ' . $exception->getMessage());
});

// 设置错误处理器
set_error_handler(function($severity, $message, $file, $line) {
    sendJsonError('PHP错误: ' . $message);
});

// 引入配置和数据库管理
require_once '../admin/config_master.php';
require_once '../admin/database_manager.php';

class UnifiedDownloadAPI {
    private $db;
    private $config;
    private $dbManager;
    private $currentSite;
    
    public function __construct() {
        $this->loadConfig();
        $this->initDatabase();
        $this->identifySite();
        $this->handleRequest();
    }
    
    private function loadConfig() {
        $configFile = __DIR__ . '/../admin/config_master.php';
        if (!file_exists($configFile)) {
            sendJsonError('配置文件不存在: ' . $configFile);
        }

        $this->config = require $configFile;

        if (!$this->config || !is_array($this->config)) {
            sendJsonError('配置文件格式错误');
        }

        // 验证必要的配置项
        if (!isset($this->config['database']) || !isset($this->config['sites'])) {
            sendJsonError('配置文件缺少必要配置项');
        }
    }
    
    private function initDatabase() {
        try {
            $this->dbManager = new MultiSiteDatabaseManager($this->config);
            $this->db = $this->dbManager->getPDO();

            // 测试数据库连接
            $this->db->query('SELECT 1');
        } catch (Exception $e) {
            sendJsonError('数据库连接失败: ' . $e->getMessage());
        }
    }
    
    private function identifySite() {
        // 通过HTTP_HOST、API_KEY或TOKEN识别站点
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? $_GET['api_key'] ?? '';
        $token = $_POST['token'] ?? $_GET['token'] ?? '';

        // 优先通过API Key识别
        if ($apiKey) {
            $stmt = $this->db->prepare("SELECT * FROM {$this->dbManager->getTableName('sites')} WHERE api_key = ?");
            $stmt->execute([$apiKey]);
            $this->currentSite = $stmt->fetch();
        }

        // 通过token识别站点（用于IP验证）
        if (!$this->currentSite && $token) {
            $stmt = $this->db->prepare("
                SELECT s.*
                FROM {$this->dbManager->getTableName('sites')} s
                JOIN {$this->dbManager->getTableName('downloads')} d ON s.id = d.site_id
                WHERE d.token = ?
            ");
            $stmt->execute([$token]);
            $this->currentSite = $stmt->fetch();
        }

        // 通过域名识别
        if (!$this->currentSite) {
            foreach ($this->config['sites'] as $key => $site) {
                if (strpos($site['domain'], $host) !== false) {
                    $stmt = $this->db->prepare("SELECT * FROM {$this->dbManager->getTableName('sites')} WHERE site_key = ?");
                    $stmt->execute([$key]);
                    $this->currentSite = $stmt->fetch();
                    break;
                }
            }
        }

        // 如果是IP验证请求且有token，允许通过（因为token已经验证了站点）
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        if (!$this->currentSite && $action === 'verify' && $token) {
            // 创建一个临时站点对象用于验证
            $this->currentSite = ['id' => 0, 'name' => 'Token验证', 'site_key' => 'token_verify'];
            return;
        }

        if (!$this->currentSite) {
            sendJsonError('未识别的站点', 401);
        }
    }
    
    private function handleRequest() {
        // 头部已在文件开头设置，这里不需要重复设置
        
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'create':
                    $this->createDownload();
                    break;
                case 'verify':
                    $this->verifyIP();
                    break;
                case 'stats':
                    $this->getStats();
                    break;
                default:
                    sendJsonError('无效的操作', 400);
            }
        } catch (Exception $e) {
            sendJsonError($e->getMessage());
        }
    }
    
    public function createDownload() {
        // 先获取参数
        $fileUrl = $_POST['file_url'] ?? '';
        $softwareName = $_POST['software_name'] ?? '';

        // 直接使用前端传递的用户IP
        $clientIP = $_POST['user_ip'] ?? $this->getClientIP();

        // 记录用户请求创建下载器
        writeLog('access', '请求下载', [
            'site' => $this->currentSite['name'],
            'software_name' => $softwareName,
            'client_ip' => $clientIP
        ]);

        // 记录IP使用情况
        $this->log("使用IP地址: $clientIP (来源: " . (isset($_POST['user_ip']) ? '前端检测' : '服务器检测') . ")");

        // 记录系统日志
        writeLog('system', 'IP地址确认', [
            'site' => $this->currentSite['name'],
            'frontend_ip' => $_POST['user_ip'] ?? 'none',
            'server_ip' => $this->getClientIP(),
            'final_ip' => $clientIP,
            'client_ip' => $clientIP
        ]);
        
        if (empty($fileUrl) || empty($softwareName)) {
            throw new Exception('缺少必要参数');
        }
        
        // 验证文件URL是否属于当前存储服务器
        if (!$this->isValidFileUrl($fileUrl)) {
            throw new Exception('无效的文件地址');
        }
        
        // 生成唯一令牌
        $token = $this->generateToken();
        
        // 计算过期时间
        $expiresAt = date('Y-m-d H:i:s', time() + ($this->config['ip_verification']['token_expiry_hours'] * 3600));
        
        // 保存到数据库
        $data = [
            'site_id' => $this->currentSite['id'],
            'token' => $token,
            'software_name' => $softwareName,
            'file_url' => $fileUrl,
            'original_ip' => $clientIP,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'expires_at' => $expiresAt,
            'metadata' => json_encode([
                'site_name' => $this->currentSite['name'],
                'created_via' => 'api'
            ])
        ];
        
        $stmt = $this->db->prepare("
            INSERT INTO {$this->dbManager->getTableName('downloads')} 
            (site_id, token, software_name, file_url, original_ip, user_agent, referrer, expires_at, metadata) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['site_id'], $data['token'], $data['software_name'], 
            $data['file_url'], $data['original_ip'], $data['user_agent'], 
            $data['referrer'], $data['expires_at'], $data['metadata']
        ]);
        
        // 创建配置文件
        $configContent = $this->generateConfigFile($token, $softwareName, $fileUrl);
        
        // 创建zip文件
        $zipPath = $this->createDownloadPackage($token, $configContent);
        
        $this->log("创建下载: Token=$token, IP=$clientIP, Software=$softwareName");

        // 记录下载器创建成功
        writeLog('download', '下载器创建成功', [
            'site' => $this->currentSite['name'],
            'software_name' => $softwareName,
            'token' => $token,
            'expires_at' => $expiresAt,
            'client_ip' => $clientIP
        ]);

        $this->sendSuccess([
            'token' => $token,
            'download_url' => $zipPath,
            'expires_at' => $expiresAt,
            'site' => $this->currentSite['name'],
            'message' => '下载器生成成功'
        ]);
    }
    
    public function verifyIP() {
        $token = $_POST['token'] ?? '';
        $currentIP = $_POST['current_ip'] ?? '';

        // 记录IP验证请求
        writeLog('access', '验证下载权限', [
            'site' => $this->currentSite['name'],
            'token' => $token,
            'client_ip' => $currentIP
        ]);

        if (empty($token)) {
            $this->sendResponse([
                'S' => 0,
                'result' => 'INVALID_TOKEN',
                'message' => '缺少验证令牌'
            ]);
            return;
        }
        
        // 查询下载记录
        $stmt = $this->db->prepare("
            SELECT d.*, s.name as site_name 
            FROM {$this->dbManager->getTableName('downloads')} d
            JOIN {$this->dbManager->getTableName('sites')} s ON d.site_id = s.id
            WHERE d.token = ?
        ");
        $stmt->execute([$token]);
        $record = $stmt->fetch();
        
        if (!$record) {
            $this->sendResponse([
                'S' => 0,
                'result' => 'TOKEN_NOT_FOUND',
                'message' => '令牌不存在'
            ]);
            return;
        }
        
        // 检查令牌是否过期
        if (strtotime($record['expires_at']) < time()) {
            $this->sendResponse([
                'S' => 0,
                'result' => 'TOKEN_EXPIRED',
                'message' => '下载令牌已过期'
            ]);
            return;
        }
        
        // 检查下载次数
        if ($record['download_count'] >= $this->config['ip_verification']['max_downloads_per_token']) {
            $this->sendResponse([
                'S' => 0,
                'result' => 'MAX_DOWNLOADS_EXCEEDED',
                'message' => '下载次数已达上限'
            ]);
            return;
        }
        
        // 检查IP验证功能是否启用
        if (!$this->config['ip_verification']['enabled']) {
            $this->executeSuccessActions($record['id'], $token, $currentIP, 'IP_VERIFICATION_DISABLED');
            
            $this->sendResponse([
                'S' => 1,
                'result' => 'IP_VERIFICATION_DISABLED',
                'message' => 'IP验证已禁用，直接通过',
                'file_url' => $record['file_url'],
                'software_name' => $record['software_name'],
                'site' => $record['site_name']
            ]);
            return;
        }
        
        if (empty($currentIP)) {
            $this->sendResponse([
                'S' => 0,
                'result' => 'INVALID_IP',
                'message' => '缺少当前IP地址'
            ]);
            return;
        }
        
        // 执行IP验证 - 修复逻辑：正确的IP验证流程
        // 1. 检查当前IP与原始IP是否匹配
        if ($currentIP === $record['original_ip']) {
            // IP完全匹配 - 验证通过
            $this->log("IP地址匹配，验证通过: 原始IP={$record['original_ip']}, 当前IP=$currentIP");

            writeLog('download', '验证通过(IP对比一致)', [
                'site' => $record['site_name'],
                'software_name' => $record['software_name'],
                'token' => $token,
                'original_ip' => $record['original_ip'],
                'current_ip' => $currentIP,
                'result' => 'IP一致允许下载',
                'client_ip' => $currentIP
            ]);

            $this->executeSuccessActions($record['id'], $token, $currentIP, 'IP_MATCH');

            $this->sendResponse([
                'S' => 1,
                'result' => 'IP_MATCH',
                'message' => 'IP地址验证通过',
                'file_url' => $record['file_url'],
                'software_name' => $record['software_name'],
                'site' => $record['site_name']
            ]);
            return;
        }

        // 2. IP不匹配 - 检查是否允许不匹配的IP下载
        $allowMismatch = $this->config['ip_verification']['allow_ip_mismatch'] ?? true;

        if ($allowMismatch) {
            // 允许IP不匹配的下载
            $this->log("IP地址不匹配但允许下载: 原始IP={$record['original_ip']}, 当前IP=$currentIP");

            writeLog('download', '验证通过(IP对比不一致)', [
                'site' => $record['site_name'],
                'software_name' => $record['software_name'],
                'token' => $token,
                'original_ip' => $record['original_ip'],
                'current_ip' => $currentIP,
                'result' => 'IP不一致但允许下载',
                'client_ip' => $currentIP
            ]);

            $this->executeSuccessActions($record['id'], $token, $currentIP, 'IP_MISMATCH_ALLOWED');

            $this->sendResponse([
                'S' => 1,
                'result' => 'IP_MISMATCH_ALLOWED',
                'message' => 'IP地址不匹配，但允许下载',
                'file_url' => $record['file_url'],
                'software_name' => $record['software_name'],
                'site' => $record['site_name']
            ]);
        } else {
            // 严格模式 - 拒绝IP不匹配的下载
            $this->log("IP地址不匹配，拒绝下载: 原始IP={$record['original_ip']}, 当前IP=$currentIP");

            writeLog('download', '验证失败(IP对比不一致)', [
                'site' => $record['site_name'],
                'software_name' => $record['software_name'],
                'token' => $token,
                'original_ip' => $record['original_ip'],
                'current_ip' => $currentIP,
                'result' => 'IP不一致拒绝下载',
                'client_ip' => $currentIP
            ]);

            $this->recordVerification($record['id'], $token, $currentIP, 'IP_MISMATCH_STRICT');

            $this->sendResponse([
                'S' => 0,
                'result' => 'IP_MISMATCH_STRICT',
                'message' => 'IP地址不匹配，下载被拒绝'
            ]);
        }
    }
    
    private function executeSuccessActions($downloadId, $token, $currentIP, $result) {
        try {
            // 记录验证结果
            $this->recordVerification($downloadId, $token, $currentIP, $result);
            
            // 更新下载次数和时间
            $stmt = $this->db->prepare("
                UPDATE {$this->dbManager->getTableName('downloads')} 
                SET download_count = download_count + 1, downloaded_at = NOW(), status = 'completed'
                WHERE token = ?
            ");
            $stmt->execute([$token]);
            
            $this->log("验证成功: Token=$token, IP=$currentIP, Result=$result");
            
        } catch (Exception $e) {
            $this->log("执行成功操作失败: " . $e->getMessage(), 'error');
        }
    }
    
    private function recordVerification($downloadId, $token, $currentIP, $result) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO {$this->dbManager->getTableName('ip_verifications')} 
                (download_id, token, verify_ip, result, user_agent) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $downloadId, $token, $currentIP, $result, 
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            $this->log("记录验证失败: " . $e->getMessage(), 'error');
        }
    }
    
    public function getStats() {
        // 记录统计查询
        writeLog('access', '查询统计', [
            'site' => $this->currentSite['name'],
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'none',
            'cf_connecting_ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? 'none',
            'x_forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'none'
        ]);

        try {
            $siteId = $this->currentSite['id'];
            
            // 站点统计
            $totalDownloads = $this->db->query("
                SELECT COUNT(*) as count 
                FROM {$this->dbManager->getTableName('downloads')} 
                WHERE site_id = $siteId
            ")->fetch()['count'];
            
            $todayDownloads = $this->db->query("
                SELECT COUNT(*) as count 
                FROM {$this->dbManager->getTableName('downloads')} 
                WHERE site_id = $siteId AND DATE(created_at) = CURDATE()
            ")->fetch()['count'];
            
            $successfulVerifications = $this->db->query("
                SELECT COUNT(*) as count 
                FROM {$this->dbManager->getTableName('ip_verifications')} v
                JOIN {$this->dbManager->getTableName('downloads')} d ON v.download_id = d.id
                WHERE d.site_id = $siteId AND v.result IN ('IP_MATCH', 'IP_MISMATCH_ALLOWED', 'IP_VERIFICATION_DISABLED')
            ")->fetch()['count'];
            
            $totalVerifications = $this->db->query("
                SELECT COUNT(*) as count 
                FROM {$this->dbManager->getTableName('ip_verifications')} v
                JOIN {$this->dbManager->getTableName('downloads')} d ON v.download_id = d.id
                WHERE d.site_id = $siteId
            ")->fetch()['count'];
            
            $successRate = $totalVerifications > 0 ? round(($successfulVerifications / $totalVerifications) * 100, 2) : 0;
            
            // 临时调试：检查配置文件内容
            $configFile = __DIR__ . '/../admin/config_master.php';
            $configContent = file_get_contents($configFile);
            $downloaderConfig = $this->config['downloader'] ?? [];
            $ipVerificationConfig = $this->config['ip_verification'] ?? [];
            $showLogValue = $this->config['downloader']['show_log'] ?? true;
            $ipEnabledValue = $this->config['ip_verification']['enabled'] ?? true;
            $strictModeValue = $this->config['ip_verification']['strict_mode'] ?? false;

            $this->sendSuccess([
                'site' => $this->currentSite['name'],
                'total_downloads' => (int)$totalDownloads,
                'today_downloads' => (int)$todayDownloads,
                'success_rate' => $successRate,
                'ip_verification_enabled' => $ipEnabledValue,
                'strict_mode' => $strictModeValue,
                'downloader_show_log' => $showLogValue,
                // 临时调试字段 - 配置文件信息
                'debug_config_file' => $configFile,
                'debug_config_exists' => file_exists($configFile),
                'debug_config_size' => strlen($configContent),
                'debug_config_modified' => date('Y-m-d H:i:s', filemtime($configFile)),
                // 临时调试字段 - 下载器配置
                'debug_downloader_config' => $downloaderConfig,
                'debug_show_log_raw' => $this->config['downloader']['show_log'] ?? 'NOT_SET',
                // 临时调试字段 - IP验证配置
                'debug_ip_verification_config' => $ipVerificationConfig,
                'debug_ip_enabled_raw' => $this->config['ip_verification']['enabled'] ?? 'NOT_SET',
                'debug_strict_mode_raw' => $this->config['ip_verification']['strict_mode'] ?? 'NOT_SET'
            ]);
            
        } catch (Exception $e) {
            sendJsonError('获取统计失败: ' . $e->getMessage());
        }
    }
    
    private function isValidFileUrl($url) {
        $parsedUrl = parse_url($url);
        $domain = $parsedUrl['host'] ?? '';
        
        // 检查是否是存储服务器域名
        $storageDomain = parse_url($this->config['storage_server']['domain'], PHP_URL_HOST);
        return $domain === $storageDomain;
    }
    
    private function generateToken() {
        // 生成更加唯一的token：时间戳 + 随机字符串 + 站点标识
        $timestamp = time();
        $random = bin2hex(random_bytes(12));
        $sitePrefix = substr($this->currentSite['site_key'], 0, 3);

        return $sitePrefix . '_' . $timestamp . '_' . $random;
    }
    
    private function generateConfigFile($token, $softwareName, $fileUrl) {
        $verifyUrl = $this->config['storage_server']['domain'] . '/api/download_api.php?action=verify';

        // 不再生成[ui]配置，完全依赖后台动态控制
        return "[download]
token = $token
software_name = $softwareName
file_url = $fileUrl

[server]
verify_url = $verifyUrl
api_key = {$this->currentSite['api_key']}

[info]
created_at = " . date('Y-m-d H:i:s') . "
expires_at = " . date('Y-m-d H:i:s', time() + ($this->config['ip_verification']['token_expiry_hours'] * 3600)) . "
site = {$this->currentSite['name']}
site_key = {$this->currentSite['site_key']}";
    }
    
    private function createDownloadPackage($token, $configContent) {
        $downloadsDir = '../downloads';
        if (!is_dir($downloadsDir)) {
            mkdir($downloadsDir, 0755, true);
        }

        // 从配置内容中提取软件名称
        $softwareName = 'Unknown';
        if (preg_match('/software_name = (.+)/', $configContent, $matches)) {
            $softwareName = trim($matches[1]);
        }

        // 清理软件名称，移除扩展名和特殊字符
        $cleanName = preg_replace('/\.(exe|msi|zip|rar|7z|dmg|pkg|deb|rpm|tar\.gz|iso|img)$/i', '', $softwareName);
        $cleanName = preg_replace('/[^\w\s-]/', '', $cleanName);
        $cleanName = preg_replace('/\s+/', '', $cleanName); // 移除所有空格
        $cleanName = substr($cleanName, 0, 20); // 限制长度

        // 生成6位时间戳 (HHMMSS)
        $timestamp6 = date('His');

        $zipFilename = "{$cleanName}-{$timestamp6}.zip";
        $zipPath = "$downloadsDir/$zipFilename";
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            // 修复下载器路径 - 指向正确的downloader.exe位置
            $downloaderPath = '../downloader/Downloader.exe';

            // 检查文件是否存在
            if (!file_exists($downloaderPath)) {
                throw new Exception("下载器文件不存在: $downloaderPath");
            }

            $zip->addFile($downloaderPath, 'Downloader.exe');
            $zip->addFromString('config.ini', $configContent);
            $zip->close();

            return "downloads/$zipFilename";
        } else {
            throw new Exception('创建ZIP文件失败');
        }
    }
    
    private function getClientIP() {
        // 优先级顺序：Cloudflare -> X-Forwarded-For -> X-Real-IP -> 直连
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // 处理多个IP的情况（通常第一个是真实IP）
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // 验证IP格式（包括IPv6），但不排除私有IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    private function log($message, $level = 'info') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO {$this->dbManager->getTableName('system_logs')} 
                (site_id, level, message, context, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $this->currentSite['id'],
                $level,
                $message,
                json_encode($_POST),
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("日志记录失败: " . $e->getMessage());
        }
    }
    
    private function sendSuccess($data) {
        $this->sendResponse(array_merge(['success' => true], $data));
    }
    
    private function sendError($message) {
        $this->sendResponse(['success' => false, 'message' => $message]);
    }
    
    private function sendResponse($data) {
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 启动API
new UnifiedDownloadAPI();
?>

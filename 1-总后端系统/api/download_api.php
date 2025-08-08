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
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
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
        // 通过HTTP_HOST或API_KEY识别站点
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? $_GET['api_key'] ?? '';
        
        // 优先通过API Key识别
        if ($apiKey) {
            $stmt = $this->db->prepare("SELECT * FROM {$this->dbManager->getTableName('sites')} WHERE api_key = ?");
            $stmt->execute([$apiKey]);
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
        $fileUrl = $_POST['file_url'] ?? '';
        $softwareName = $_POST['software_name'] ?? '';
        $clientIP = $this->getClientIP();
        
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
        
        // 执行IP验证
        if ($record['original_ip'] === $currentIP) {
            // IP匹配成功
            $this->executeSuccessActions($record['id'], $token, $currentIP, 'IP_MATCH');
            
            $this->sendResponse([
                'S' => 1,
                'result' => 'IP_MATCH',
                'message' => 'IP验证通过',
                'file_url' => $record['file_url'],
                'software_name' => $record['software_name'],
                'site' => $record['site_name']
            ]);
            
        } else {
            // IP不匹配
            $this->recordVerification($record['id'], $token, $currentIP, 'IP_MISMATCH');
            
            if ($this->config['ip_verification']['strict_mode']) {
                $this->sendResponse([
                    'S' => 0,
                    'result' => 'IP_MISMATCH_STRICT',
                    'message' => "IP地址不匹配，拒绝下载 (原始: {$record['original_ip']}, 当前: $currentIP)"
                ]);
            } else {
                $this->executeSuccessActions($record['id'], $token, $currentIP, 'IP_MISMATCH_ALLOWED');
                
                $this->sendResponse([
                    'S' => 1,
                    'result' => 'IP_MISMATCH_ALLOWED',
                    'message' => "IP地址不匹配但允许下载 (原始: {$record['original_ip']}, 当前: $currentIP)",
                    'file_url' => $record['file_url'],
                    'software_name' => $record['software_name'],
                    'site' => $record['site_name']
                ]);
            }
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
            
            $this->sendSuccess([
                'site' => $this->currentSite['name'],
                'total_downloads' => (int)$totalDownloads,
                'today_downloads' => (int)$todayDownloads,
                'success_rate' => $successRate,
                'ip_verification_enabled' => $this->config['ip_verification']['enabled'],
                'strict_mode' => $this->config['ip_verification']['strict_mode']
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

        return "[download]
token = $token
software_name = $softwareName
file_url = $fileUrl

[server]
verify_url = $verifyUrl

[info]
created_at = " . date('Y-m-d H:i:s') . "
expires_at = " . date('Y-m-d H:i:s', time() + ($this->config['ip_verification']['token_expiry_hours'] * 3600)) . "
site = {$this->currentSite['name']}";
    }
    
    private function createDownloadPackage($token, $configContent) {
        $downloadsDir = '../downloads';
        if (!is_dir($downloadsDir)) {
            mkdir($downloadsDir, 0755, true);
        }

        // 生成更友好的文件名
        $shortToken = substr($token, -8); // 取token最后8位
        $timestamp = time(); // Unix时间戳
        $zipFilename = "SecureDownloader_{$timestamp}_{$shortToken}.zip";
        $zipPath = "$downloadsDir/$zipFilename";
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            $zip->addFile('../downloader.exe', 'downloader.exe');
            $zip->addFromString('config.ini', $configContent);
            $zip->close();
            
            return "downloads/$zipFilename";
        } else {
            throw new Exception('创建ZIP文件失败');
        }
    }
    
    private function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
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

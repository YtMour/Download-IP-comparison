<?php
/**
 * 多站点数据库管理器
 * 自动创建表、索引、触发器等
 */

class MultiSiteDatabaseManager {
    private $pdo;
    private $config;
    private $prefix;
    
    public function __construct($config) {
        $this->config = $config;
        $this->prefix = $config['database']['prefix'];
        $this->connect();
        
        if ($config['system']['auto_create_tables']) {
            $this->autoSetupDatabase();
        }
    }
    
    private function connect() {
        $db = $this->config['database'];
        
        try {
            $dsn = "{$db['type']}:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";
            $this->pdo = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            $this->log("数据库连接成功");
            
        } catch (PDOException $e) {
            throw new Exception("数据库连接失败: " . $e->getMessage());
        }
    }
    
    public function autoSetupDatabase() {
        $this->log("开始自动设置数据库...");
        
        // 创建所有表
        $this->createTables();
        
        // 创建索引
        $this->createIndexes();
        
        // 创建触发器
        $this->createTriggers();
        
        // 插入初始数据
        $this->insertInitialData();
        
        // 创建存储过程
        $this->createStoredProcedures();
        
        $this->log("数据库自动设置完成");
    }
    
    public function createTables() {
        $tables = [
            // 站点管理表
            'sites' => "
                CREATE TABLE IF NOT EXISTS {$this->prefix}sites (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    site_key VARCHAR(50) UNIQUE NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    domain VARCHAR(255) NOT NULL,
                    status ENUM('active', 'inactive', 'maintenance', 'planning') DEFAULT 'active',
                    api_key VARCHAR(64) UNIQUE NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            // 下载记录表 (多站点)
            'downloads' => "
                CREATE TABLE IF NOT EXISTS {$this->prefix}downloads (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    site_id INT NOT NULL,
                    token VARCHAR(64) UNIQUE NOT NULL,
                    software_name VARCHAR(255) NOT NULL,
                    file_url TEXT NOT NULL,
                    file_size BIGINT DEFAULT 0,
                    original_ip VARCHAR(45) NOT NULL,
                    user_agent TEXT,
                    referrer TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    downloaded_at TIMESTAMP NULL,
                    download_count INT DEFAULT 0,
                    status ENUM('active', 'expired', 'blocked', 'completed') DEFAULT 'active',
                    expires_at TIMESTAMP NOT NULL,
                    metadata JSON,
                    FOREIGN KEY (site_id) REFERENCES {$this->prefix}sites(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            // IP验证记录表
            'ip_verifications' => "
                CREATE TABLE IF NOT EXISTS {$this->prefix}ip_verifications (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    download_id BIGINT NOT NULL,
                    token VARCHAR(64) NOT NULL,
                    verify_ip VARCHAR(45) NOT NULL,
                    result ENUM('IP_MATCH', 'IP_MISMATCH', 'IP_MISMATCH_ALLOWED', 'IP_MISMATCH_STRICT', 'TOKEN_EXPIRED', 'MAX_DOWNLOADS_EXCEEDED', 'IP_VERIFICATION_DISABLED') NOT NULL,
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (download_id) REFERENCES {$this->prefix}downloads(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            // 系统日志表
            'system_logs' => "
                CREATE TABLE IF NOT EXISTS {$this->prefix}system_logs (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    site_id INT NULL,
                    level ENUM('debug', 'info', 'warning', 'error', 'critical') NOT NULL,
                    message TEXT NOT NULL,
                    context JSON,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (site_id) REFERENCES {$this->prefix}sites(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            // 统计数据表
            'statistics' => "
                CREATE TABLE IF NOT EXISTS {$this->prefix}statistics (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    site_id INT NOT NULL,
                    date DATE NOT NULL,
                    downloads_created INT DEFAULT 0,
                    downloads_completed INT DEFAULT 0,
                    downloads_failed INT DEFAULT 0,
                    unique_ips INT DEFAULT 0,
                    total_bandwidth BIGINT DEFAULT 0,
                    avg_download_time DECIMAL(10,2) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_site_date (site_id, date),
                    FOREIGN KEY (site_id) REFERENCES {$this->prefix}sites(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            // API访问日志表
            'api_logs' => "
                CREATE TABLE IF NOT EXISTS {$this->prefix}api_logs (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    site_id INT NULL,
                    api_key VARCHAR(64),
                    endpoint VARCHAR(255) NOT NULL,
                    method VARCHAR(10) NOT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    user_agent TEXT,
                    request_data JSON,
                    response_code INT,
                    response_time DECIMAL(8,3),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (site_id) REFERENCES {$this->prefix}sites(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            // 文件管理表
            'files' => "
                CREATE TABLE IF NOT EXISTS {$this->prefix}files (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    site_id INT NOT NULL,
                    filename VARCHAR(255) NOT NULL,
                    original_name VARCHAR(255) NOT NULL,
                    file_path TEXT NOT NULL,
                    file_size BIGINT NOT NULL,
                    mime_type VARCHAR(100),
                    md5_hash VARCHAR(32),
                    sha256_hash VARCHAR(64),
                    download_count BIGINT DEFAULT 0,
                    status ENUM('active', 'inactive', 'deleted') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (site_id) REFERENCES {$this->prefix}sites(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            "
        ];
        
        foreach ($tables as $name => $sql) {
            try {
                $this->pdo->exec($sql);
                $this->log("创建表: {$this->prefix}$name");
            } catch (PDOException $e) {
                $this->log("创建表失败 {$this->prefix}$name: " . $e->getMessage(), 'error');
            }
        }
    }
    
    private function createIndexes() {
        $indexes = [
            // downloads表索引
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}downloads_site_token ON {$this->prefix}downloads(site_id, token)",
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}downloads_ip ON {$this->prefix}downloads(original_ip)",
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}downloads_created ON {$this->prefix}downloads(created_at)",
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}downloads_status ON {$this->prefix}downloads(status)",
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}downloads_expires ON {$this->prefix}downloads(expires_at)",
            
            // ip_verifications表索引
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}verifications_token ON {$this->prefix}ip_verifications(token)",
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}verifications_ip ON {$this->prefix}ip_verifications(verify_ip)",
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}verifications_result ON {$this->prefix}ip_verifications(result)",
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}verifications_created ON {$this->prefix}ip_verifications(created_at)",
            
            // system_logs表索引
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}logs_site_level ON {$this->prefix}system_logs(site_id, level)",
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}logs_created ON {$this->prefix}system_logs(created_at)",
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}logs_ip ON {$this->prefix}system_logs(ip_address)",
            
            // statistics表索引
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}stats_site_date ON {$this->prefix}statistics(site_id, date)",
            
            // api_logs表索引
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}api_logs_site ON {$this->prefix}api_logs(site_id)",
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}api_logs_endpoint ON {$this->prefix}api_logs(endpoint)",
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}api_logs_ip ON {$this->prefix}api_logs(ip_address)",
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}api_logs_created ON {$this->prefix}api_logs(created_at)",
            
            // files表索引
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}files_site ON {$this->prefix}files(site_id)",
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}files_hash ON {$this->prefix}files(md5_hash)",
            "CREATE INDEX IF NOT EXISTS idx_{$this->prefix}files_status ON {$this->prefix}files(status)"
        ];
        
        foreach ($indexes as $index) {
            try {
                $this->pdo->exec($index);
            } catch (PDOException $e) {
                // 索引可能已存在，忽略错误
            }
        }
        
        $this->log("数据库索引创建完成");
    }
    
    private function createTriggers() {
        // 自动更新统计数据的触发器
        $triggers = [
            "
            CREATE TRIGGER IF NOT EXISTS tr_{$this->prefix}downloads_stats_insert
            AFTER INSERT ON {$this->prefix}downloads
            FOR EACH ROW
            BEGIN
                INSERT INTO {$this->prefix}statistics (site_id, date, downloads_created)
                VALUES (NEW.site_id, DATE(NEW.created_at), 1)
                ON DUPLICATE KEY UPDATE downloads_created = downloads_created + 1;
            END
            ",
            
            "
            CREATE TRIGGER IF NOT EXISTS tr_{$this->prefix}downloads_stats_update
            AFTER UPDATE ON {$this->prefix}downloads
            FOR EACH ROW
            BEGIN
                IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
                    INSERT INTO {$this->prefix}statistics (site_id, date, downloads_completed)
                    VALUES (NEW.site_id, DATE(NEW.downloaded_at), 1)
                    ON DUPLICATE KEY UPDATE downloads_completed = downloads_completed + 1;
                END IF;
            END
            "
        ];
        
        foreach ($triggers as $trigger) {
            try {
                $this->pdo->exec($trigger);
            } catch (PDOException $e) {
                $this->log("创建触发器失败: " . $e->getMessage(), 'warning');
            }
        }
        
        $this->log("数据库触发器创建完成");
    }
    
    private function insertInitialData() {
        // 插入配置的站点数据
        foreach ($this->config['sites'] as $key => $site) {
            $this->insertSite($key, $site);
        }
    }
    
    public function insertSite($key, $siteData) {
        $sql = "INSERT IGNORE INTO {$this->prefix}sites 
                (site_key, name, domain, status, api_key, storage_path, admin_email, settings) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $settings = json_encode([
            'created_at' => $siteData['created_at'],
            'auto_cleanup' => true,
            'max_file_size' => $this->config['system']['max_file_size']
        ]);
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $key,
                $siteData['name'],
                $siteData['domain'],
                $siteData['status'],
                $siteData['api_key'],
                $siteData['storage_path'],
                $siteData['admin_email'],
                $settings
            ]);
            
            $this->log("插入站点数据: $key - {$siteData['name']}");
            
        } catch (PDOException $e) {
            $this->log("插入站点数据失败: " . $e->getMessage(), 'error');
        }
    }
    
    private function createStoredProcedures() {
        // 清理过期记录的存储过程
        $procedures = [
            "
            CREATE PROCEDURE IF NOT EXISTS sp_{$this->prefix}cleanup_expired()
            BEGIN
                DECLARE done INT DEFAULT FALSE;
                DECLARE expired_count INT;
                
                -- 删除过期的下载记录
                DELETE FROM {$this->prefix}downloads 
                WHERE expires_at < NOW() AND status = 'active';
                
                GET DIAGNOSTICS expired_count = ROW_COUNT;
                
                -- 记录清理日志
                INSERT INTO {$this->prefix}system_logs (level, message, context)
                VALUES ('info', 'Cleanup expired downloads', JSON_OBJECT('deleted_count', expired_count));
                
                -- 清理旧日志 (保留90天)
                DELETE FROM {$this->prefix}system_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
                
                -- 清理旧API日志 (保留30天)
                DELETE FROM {$this->prefix}api_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
            END
            "
        ];
        
        foreach ($procedures as $procedure) {
            try {
                $this->pdo->exec($procedure);
            } catch (PDOException $e) {
                $this->log("创建存储过程失败: " . $e->getMessage(), 'warning');
            }
        }
        
        $this->log("存储过程创建完成");
    }
    
    public function getPDO() {
        return $this->pdo;
    }
    
    public function getTableName($table) {
        return $this->prefix . $table;
    }
    
    private function log($message, $level = 'info') {
        error_log("[" . date('Y-m-d H:i:s') . "] [$level] MultiSiteDB: $message");
    }
}
?>

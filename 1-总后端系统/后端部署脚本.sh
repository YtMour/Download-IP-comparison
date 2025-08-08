#!/bin/bash
# 多站点IP验证下载系统 - 宝塔面板部署脚本

echo "🚀 多站点IP验证下载系统 - 宝塔面板部署脚本"
echo "=============================================="

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 检查是否为root用户
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}请使用root用户运行此脚本${NC}"
    exit 1
fi

# 检查宝塔面板
if ! command -v bt &> /dev/null; then
    echo -e "${RED}未检测到宝塔面板，请先安装宝塔面板${NC}"
    echo "安装命令: curl -sSO http://download.bt.cn/install/install_panel.sh && bash install_panel.sh"
    exit 1
fi

echo -e "${GREEN}✅ 检测到宝塔面板${NC}"

# 获取配置信息
echo ""
echo "请输入配置信息："
read -p "总存储服务器域名 (如: dw.ytmour.art): " STORAGE_DOMAIN
read -p "数据库名称 [multi_site_downloads]: " DB_NAME
DB_NAME=${DB_NAME:-multi_site_downloads}
read -p "数据库用户名 [download_admin]: " DB_USER
DB_USER=${DB_USER:-download_admin}
read -s -p "数据库密码: " DB_PASS
echo ""
read -s -p "设置管理员密码: " ADMIN_PASS
echo ""

# 验证必要参数
if [ -z "$STORAGE_DOMAIN" ]; then
    echo -e "${RED}错误：必须输入存储服务器域名${NC}"
    exit 1
fi

if [ -z "$DB_PASS" ]; then
    echo -e "${RED}错误：必须输入数据库密码${NC}"
    exit 1
fi

if [ -z "$ADMIN_PASS" ]; then
    echo -e "${RED}错误：必须设置管理员密码${NC}"
    exit 1
fi

# 不再自动生成API密钥，将在管理面板中手动创建站点

# 确认部署
echo ""
read -p "确认开始部署? (y/N): " CONFIRM
if [[ ! $CONFIRM =~ ^[Yy]$ ]]; then
    echo "部署已取消"
    exit 0
fi

echo ""
echo -e "${YELLOW}开始部署...${NC}"

# 1. 创建目录结构
STORAGE_ROOT="/www/wwwroot/$STORAGE_DOMAIN"
echo -e "${BLUE}1. 创建目录结构...${NC}"

mkdir -p "$STORAGE_ROOT"/{admin,api,downloads,files}
chmod 755 "$STORAGE_ROOT"/{admin,api}
chmod 777 "$STORAGE_ROOT"/{downloads,files}

echo -e "${GREEN}✅ 目录结构创建完成${NC}"

# 2. 创建数据库
echo -e "${BLUE}2. 创建数据库...${NC}"

# 获取MySQL root密码
MYSQL_ROOT_PASS=$(cat /www/server/mysql/default.pl 2>/dev/null || echo "")

if [ -z "$MYSQL_ROOT_PASS" ]; then
    echo -e "${YELLOW}⚠️ 无法自动获取MySQL密码，请手动创建数据库${NC}"
    echo "数据库名: $DB_NAME"
    echo "用户名: $DB_USER"
    echo "密码: $DB_PASS"
else
    mysql -u root -p"$MYSQL_ROOT_PASS" << EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ 数据库创建成功${NC}"
    else
        echo -e "${RED}❌ 数据库创建失败，请手动创建${NC}"
    fi
fi

# 3. 生成配置文件
echo -e "${BLUE}3. 生成配置文件...${NC}"

cat > "$STORAGE_ROOT/admin/config_master.php" << EOF
<?php
/**
 * 多站点管理系统 - 主配置文件
 * 自动生成于: $(date '+%Y-%m-%d %H:%M:%S')
 */

return [
    // 存储服务器配置
    'storage_server' => [
        'domain' => 'https://$STORAGE_DOMAIN',
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
        'database' => '$DB_NAME',
        'username' => '$DB_USER',
        'password' => '$DB_PASS',
        'charset' => 'utf8mb4',
        'prefix' => 'msd_'
    ],
    
    // 注册的站点列表 (将在管理面板中添加)
    'sites' => [
        // 站点将通过管理面板动态添加
    ],
    
    // 系统配置
    'system' => [
        'master_admin_password' => '$ADMIN_PASS',
        'auto_create_tables' => true,
        'auto_backup_enabled' => true,
        'backup_retention_days' => 30,
        'log_retention_days' => 90,
        'max_file_size' => '5GB'
    ],
    
    // IP验证全局配置
    'ip_verification' => [
        'enabled' => true,
        'strict_mode' => false,
        'max_downloads_per_token' => 5,
        'token_expiry_hours' => 24,
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
EOF

echo -e "${GREEN}✅ 配置文件生成完成${NC}"

# 4. 复制核心文件
echo -e "${BLUE}4. 复制核心文件...${NC}"

# 检查文件是否存在
if [ -f "admin/database_manager.php" ]; then
    cp admin/database_manager.php "$STORAGE_ROOT/admin/"
    cp admin/index.php "$STORAGE_ROOT/admin/"
    cp api/download_api.php "$STORAGE_ROOT/api/"
    echo -e "${GREEN}✅ 核心文件复制完成${NC}"
else
    echo -e "${YELLOW}⚠️ 请手动上传以下文件到对应目录：${NC}"
    echo "- admin/database_manager.php → $STORAGE_ROOT/admin/"
    echo "- admin/index.php → $STORAGE_ROOT/admin/"
    echo "- api/download_api.php → $STORAGE_ROOT/api/"
fi

if [ -f "downloader/downloader.exe" ]; then
    cp downloader/downloader.exe "$STORAGE_ROOT/"
    echo -e "${GREEN}✅ downloader.exe 复制完成${NC}"
else
    echo -e "${YELLOW}⚠️ downloader/downloader.exe 未找到，请手动上传${NC}"
fi

# 5. 设置文件权限
echo -e "${BLUE}5. 设置文件权限...${NC}"
chown -R www:www "$STORAGE_ROOT"
chmod 644 "$STORAGE_ROOT/admin"/*.php 2>/dev/null
chmod 644 "$STORAGE_ROOT/api"/*.php 2>/dev/null

echo -e "${GREEN}✅ 文件权限设置完成${NC}"

# 6. 创建基础目录结构
echo -e "${BLUE}6. 创建基础目录结构...${NC}"

# 创建文件存储目录（不预设站点）
mkdir -p "$STORAGE_ROOT/files"

echo -e "${GREEN}✅ 基础目录结构创建完成${NC}"

# 7. 创建部署信息文件
cat > "$STORAGE_ROOT/部署信息.txt" << EOF
多站点IP验证下载系统部署信息
==============================

部署时间: $(date '+%Y-%m-%d %H:%M:%S')

总存储服务器:
- 域名: https://$STORAGE_DOMAIN
- 管理面板: https://$STORAGE_DOMAIN/admin/
- API接口: https://$STORAGE_DOMAIN/api/download_api.php

数据库信息:
- 数据库名: $DB_NAME
- 用户名: $DB_USER
- 密码: $DB_PASS

管理员信息:
- 管理面板: https://$STORAGE_DOMAIN/admin/
- 管理员密码: $ADMIN_PASS

站点管理:
- 请在管理面板中添加站点
- 系统会自动生成API密钥

站点管理:
- 在管理面板中添加站点
- 系统会自动生成API密钥

下一步操作:
1. 在宝塔面板中为 $STORAGE_DOMAIN 申请SSL证书
2. 访问 https://$STORAGE_DOMAIN/admin/ 完成初始化
3. 在管理面板中添加站点
4. 部署各个分站
5. 测试下载功能

注意事项:
- 请妥善保管API密钥和数据库密码
- 定期备份数据库
- 监控磁盘空间使用
EOF

echo ""
echo -e "${GREEN}🎉 部署完成！${NC}"
echo ""
echo -e "${YELLOW}重要信息：${NC}"
echo "- 管理面板: https://$STORAGE_DOMAIN/admin/"
echo "- 管理员密码: $ADMIN_PASS"
echo "- 部署信息: $STORAGE_ROOT/部署信息.txt"
echo ""
echo -e "${BLUE}下一步操作：${NC}"
echo "1. 在宝塔面板中为域名申请SSL证书"
echo "2. 访问管理面板完成系统初始化"
echo "3. 在管理面板中添加站点"
echo "4. 部署各个分站"
echo ""
echo -e "${RED}请妥善保管管理员密码和数据库密码！${NC}"

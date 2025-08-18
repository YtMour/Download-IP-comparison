# 🏢 总后端系统部署指南

## 📋 系统说明

总后端系统是整个多站点IP验证下载系统的核心，负责：
- 统一的数据库管理
- API接口服务
- 下载器生成
- 系统管理面板

**部署位置**：存储服务器 (如: dw.ytmour.art)  
**部署次数**：只需部署一次，服务所有分站

## 📁 文件说明

```
1-总后端系统/
├── README_后端部署.md           # 本部署指南
├── 后端部署脚本.sh              # 自动部署脚本
├── admin/                       # 管理面板
│   ├── config_master.php        # 主配置文件
│   ├── database_manager.php     # 数据库管理类
│   └── index.php               # 管理界面
├── api/                        # API接口
│   └── download_api.php        # 核心API处理器
└── downloader.exe              # 预生成下载器 (13MB)
```

## 🚀 快速部署 (推荐)

### 方法一：自动部署脚本

1. **上传整个 `1-总后端系统/` 目录到服务器**

2. **运行自动部署脚本**
   ```bash
   cd /path/to/1-总后端系统/
   chmod +x 后端部署脚本.sh
   ./后端部署脚本.sh
   ```

3. **按提示输入配置信息**
   - 存储服务器域名 (如: dw.ytmour.art)
   - 数据库名称
   - 数据库用户名和密码

4. **访问管理面板完成初始化**
   - 地址：https://dw.ytmour.art/admin/
   - 默认密码：脚本生成的随机密码

## 🛠️ 手动部署

### 第一步：宝塔面板设置

1. **创建网站**
   - 网站 → 添加站点
   - 域名：`dw.ytmour.art`
   - 根目录：`/www/wwwroot/dw.ytmour.art`
   - PHP版本：7.4 或更高

2. **创建数据库**
   - 数据库 → 添加数据库
   - 数据库名：`multi_site_downloads`
   - 用户名：`download_admin`
   - 密码：设置强密码

3. **检查PHP扩展**
   确保已安装：PDO、PDO_MySQL、ZIP、JSON、OpenSSL

### 第二步：上传文件

将以下文件上传到网站根目录：
```
/www/wwwroot/dw.ytmour.art/
├── admin/              # 上传整个admin目录
├── api/                # 上传整个api目录
├── downloader.exe      # 上传下载器文件
├── downloads/          # 手动创建，权限777
└── files/              # 手动创建，权限777
    ├── home/          # 手动创建子目录
    ├── games/         # 手动创建子目录
    └── tools/         # 手动创建子目录
```

### 第三步：配置数据库

编辑 `admin/config_master.php`，修改数据库配置：
```php
'database' => [
    'type' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'multi_site_downloads',  // 您创建的数据库名
    'username' => 'download_admin',        // 您创建的用户名
    'password' => 'your_password',         // 您设置的密码
    'charset' => 'utf8mb4',
    'prefix' => 'msd_'
],
```

同时修改站点配置：
```php
'sites' => [
    'home' => [
        'name' => '主软件库',
        'domain' => 'https://home.ytmour.art',  // 修改为您的分站域名
        'api_key' => 'home_your_api_key',       // 设置API密钥
        // ...
    ],
    // 添加更多分站配置
],
```

### 第四步：设置权限

在宝塔面板终端执行：
```bash
chmod 777 /www/wwwroot/dw.ytmour.art/downloads/
chmod 777 /www/wwwroot/dw.ytmour.art/files/
chmod -R 777 /www/wwwroot/dw.ytmour.art/files/*/
chown -R www:www /www/wwwroot/dw.ytmour.art/
```

### 第五步：SSL证书

在宝塔面板为域名申请SSL证书

### 第六步：初始化系统

访问 `https://dw.ytmour.art/admin/` 完成系统初始化：
- 系统会自动创建数据库表
- 插入站点配置数据
- 创建索引和触发器

## 🔧 配置说明

### 主要配置项

1. **数据库配置** - 连接信息
2. **站点配置** - 注册的分站列表和API密钥
3. **IP验证配置** - 全局验证设置
4. **安全配置** - API限制和安全选项

### API密钥生成

建议格式：`站点标识_随机字符串_日期`
```
home_a1b2c3d4e5f6g7h8_20250101
games_x9y8z7w6v5u4t3s2_20250101
```

## 🧪 测试验证

### 1. 管理面板测试
- 访问：`https://dw.ytmour.art/admin/`
- 检查数据库连接状态
- 查看站点列表

### 2. API接口测试
- 访问：`https://dw.ytmour.art/api/download_api.php?action=stats`
- 应该返回JSON格式的统计数据

### 3. 文件访问测试
- 确保 `https://dw.ytmour.art/downloader.exe` 可以访问

## 🚨 故障排除

### 常见问题

1. **数据库连接失败**
   - 检查宝塔面板数据库服务状态
   - 验证配置文件中的数据库信息
   - 确认数据库用户权限

2. **文件权限错误**
   - 确保downloads和files目录权限为777
   - 检查PHP进程用户权限

3. **SSL证书问题**
   - 确保域名有有效的SSL证书
   - 检查证书是否过期

## 📊 管理功能

部署完成后，您可以在管理面板中：
- 查看所有分站状态
- 控制IP验证功能开关
- 查看下载统计数据
- 管理系统配置

## ✅ 部署完成标志

当您看到以下内容时，说明后端部署成功：
- ✅ 管理面板可以正常访问
- ✅ 数据库连接状态正常
- ✅ 站点列表显示正确
- ✅ API接口返回正常数据

## 🔗 下一步

后端部署完成后，请继续部署站点拦截系统到各个分站。

---

**重要提醒**：
- 请妥善保管数据库密码和API密钥
- 定期备份数据库数据
- 监控服务器磁盘空间使用情况

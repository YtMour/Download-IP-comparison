# 📥 安全下载器 - 使用说明

## 🎯 下载器概述

安全下载器是多站点IP验证下载系统的客户端组件，负责：
- IP地址验证
- 安全文件下载
- 用户友好的图形界面
- 下载进度显示

## 📁 文件说明

### 核心文件
- `downloader.exe` - 编译好的可执行文件 (13MB)
- `downloader.py` - Python源代码
- `config.ini` - 配置文件模板
- `README_下载器.md` - 本说明文档

### 配置文件格式 (config.ini)
```ini
[download]
token = dyn_a8f3e9c2b1d4567890abcdef12345678    # 下载令牌
software_name = Photoshop 2024 v25.0.0          # 软件名称
file_url = https://dw.ytmour.art/files/xxx.exe   # 下载链接

[server]
verify_url = https://dw.ytmour.art/api/download_api.php?action=verify

[info]
created_at = 2025-01-01 12:00:00                # 创建时间
expires_at = 2025-01-02 12:00:00                # 过期时间
```

## 🚀 使用流程

### 1. 用户获取下载器
1. 用户在网站点击下载链接
2. 系统显示安全下载提示框
3. 用户点击"生成下载器"
4. 系统生成包含配置的ZIP文件
5. 用户下载并解压ZIP文件

### 2. 运行下载器
1. 双击 `downloader.exe` 启动程序
2. 程序自动加载 `config.ini` 配置
3. 显示软件信息和下载详情

### 3. IP验证
1. 点击"验证IP"按钮
2. 程序获取当前IP地址
3. 向服务器发送验证请求
4. 显示验证结果

### 4. 开始下载
1. IP验证成功后，"开始下载"按钮激活
2. 点击开始下载
3. 选择文件保存位置
4. 显示下载进度
5. 下载完成提示

## 🔧 技术特性

### 安全特性
- **IP验证**: 确保下载者IP与申请者一致
- **令牌验证**: 防止未授权下载
- **时效控制**: 下载链接有时间限制
- **HTTPS传输**: 确保数据传输安全

### 用户体验
- **图形界面**: 友好的GUI界面
- **进度显示**: 实时下载进度
- **错误处理**: 详细的错误信息
- **日志记录**: 完整的操作日志

### 技术实现
- **Python 3.x**: 跨平台支持
- **Tkinter**: 原生GUI框架
- **Requests**: HTTP请求处理
- **多线程**: 非阻塞下载

## 🛠️ 开发和编译

### 环境要求
```bash
Python 3.7+
pip install requests
pip install pyinstaller  # 用于编译
```

### 编译为可执行文件
```bash
# 基本编译
pyinstaller --onefile --windowed downloader.py

# 带图标编译
pyinstaller --onefile --windowed --icon=icon.ico downloader.py

# 优化编译（减小文件大小）
pyinstaller --onefile --windowed --strip --optimize=2 downloader.py
```

### 测试运行
```bash
# 直接运行Python脚本
python downloader.py

# 测试配置文件
python -c "import configparser; c=configparser.ConfigParser(); c.read('config.ini'); print('配置正常')"
```

## 🔍 故障排除

### 常见问题

**1. 配置文件错误**
- 检查 `config.ini` 是否存在
- 验证配置文件格式是否正确
- 确认所有必要字段都已填写

**2. 网络连接问题**
- 检查网络连接
- 验证服务器地址是否正确
- 确认防火墙设置

**3. IP验证失败**
- 确认IP地址未发生变化
- 检查令牌是否有效
- 验证服务器IP验证设置

**4. 下载失败**
- 检查磁盘空间
- 验证文件权限
- 确认下载链接有效

### 调试模式
在Python环境中运行可以看到详细错误信息：
```bash
python downloader.py
```

### 日志分析
程序运行时会在界面显示详细日志，包括：
- 配置加载状态
- IP验证过程
- 下载进度信息
- 错误详情

## 📊 性能优化

### 下载优化
- 使用流式下载减少内存占用
- 8KB块大小平衡速度和内存
- 支持断点续传（需服务器支持）

### 界面优化
- 多线程避免界面冻结
- 实时进度更新
- 响应式布局设计

## 🔒 安全考虑

### 客户端安全
- 不存储敏感信息
- 令牌一次性使用
- 本地配置文件加密（可选）

### 服务器通信
- HTTPS加密传输
- 请求签名验证
- 防重放攻击

## 📞 技术支持

### 文档参考
- 查看服务器端API文档
- 参考系统部署指南
- 阅读故障排除清单

### 开发工具
- 使用 `debug_api.php` 测试服务器
- 使用 `test_admin.php` 检查系统状态
- 查看服务器日志分析问题

---

**版本**: 2.0  
**更新**: 2025-01-07  
**兼容**: Windows/Linux/macOS

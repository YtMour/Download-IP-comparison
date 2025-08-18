# IP验证下载器

一个安全的IP验证下载工具，支持多站点下载和进度显示。

## 🚀 功能特点

- 🔐 **IP验证**: 安全的后端通信验证
- 📥 **多站点下载**: 支持多个下载源
- 📊 **进度显示**: 实时下载进度和速度
- 🎨 **现代界面**: 暗色主题，用户友好
- 📋 **日志记录**: 详细的操作日志
- 🛡️ **安全通信**: SSL/TLS加密连接

## 📦 使用方法

1. 运行 `Downloader.exe`
2. 程序自动验证IP并获取下载信息
3. 点击下载按钮开始下载
4. 可随时取消或重新开始下载

## 🔧 开发构建

### 环境要求
- Python 3.10+
- Nuitka编译器

### 构建步骤
```bash
# 安装依赖
pip install nuitka

# 构建exe
python build_optimized.py
```

### 数字签名
构建完成后，使用您的签名程序对 `Downloader.exe` 进行数字签名以避免杀毒软件误报。

## ⚙️ 配置文件

程序使用 `config.ini` 配置文件：
```ini
[download]
server_url = https://example.com/api/
api_key = your_api_key
software_name = Software Name
download_url = https://example.com/download/
access_token = your_token
```

## 🛡️ 安全说明

- 程序可能被杀毒软件误报，这是打包工具的常见问题
- 建议将程序添加到杀毒软件白名单
- 使用数字签名可显著减少误报率

## 📋 系统要求

- Windows 10/11 (64位)
- 稳定的网络连接
- 至少50MB可用磁盘空间

## 📄 许可证

MIT License - 详见 LICENSE.txt

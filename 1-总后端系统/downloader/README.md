# 🔒 IP验证下载器

## 🎯 项目概述
智能IP验证下载器，支持多种验证模式和额外验证逻辑触发。

## ✅ 核心功能
- **智能IP验证**: 对比下载时IP与运行时IP，支持多种验证模式
- **验证逻辑触发**: 在IP匹配或验证禁用时显示验证提示
- **全文件类型支持**: 智能识别文件扩展名，支持所有文件类型
- **自动下载**: 自动保存到Downloads目录，避免文件名重复
- **网络优化**: 多重IP获取服务，解决代理和SSL问题

## 📋 使用方法
1. 将配置文件放置在下载器同目录
2. 双击运行 `downloader.exe`
3. 点击"开始下载"按钮
4. 查看操作日志了解下载状态

## 📄 配置文件格式
```ini
[download]
token = hom_1754721058_894037ecafaeab03bef524b7
software_name = SteamSetup.exe
file_url = https://dw.ytmour.art/windows/games/SteamSetup.exe

[server]
verify_url = https://dw.ytmour.art/api/download_api.php?action=verify
api_key = homeytmourart_0fd0a2df780e2c910304b7f04c25ffcc_20250809

[info]
created_at = 2025-08-09 14:30:58
expires_at = 2025-08-10 14:30:58
site = 1
site_key = homeytmourart
```

## 🎯 验证状态说明
- **IP_MATCH**: 🎯 IP地址验证通过 (IP: xxx.xxx.xxx.xxx)
- **IP_MISMATCH_ALLOWED**: ⚠️ IP地址不匹配，但允许下载 (当前IP: xxx.xxx.xxx.xxx)
- **IP_VERIFICATION_DISABLED**: ⚠️ 跳过验证，尝试直接下载... (IP: xxx.xxx.xxx.xxx)
- **TOKEN_EXPIRED**: ⏰ 下载令牌已过期，请重新获取下载器
- **MAX_DOWNLOADS_EXCEEDED**: ❌ IP验证失败，下载终止 (IP: xxx.xxx.xxx.xxx)

## 🚀 重新编译
如需修改代码后重新编译：
```bash
python compile.py
```

## 📁 项目结构
```
downloader/
├── downloader.exe      # 主程序 (14.4 MB)
├── downloader.py       # 源代码
├── compile.py          # 编译脚本
├── config.ini          # 配置文件
└── README_修复版.md    # 说明文档
```

## 🔍 测试日志示例
```
[16:57:03] ✅ 配置加载成功: SteamSetup.exe
[16:57:04] 🔐 步骤 1/2: IP地址验证
[16:57:04] 🔍 正在验证下载权限...
[16:57:06] ✅ 验证通过
[16:57:06] ⚠️ IP地址不匹配，但允许下载 (当前IP: 148.135.187.111)
[16:57:06] 📁 文件地址已更新
[16:57:06] 📥 步骤 2/2: 文件下载
[16:57:16] ✅ 下载完成: SteamSetup.exe
[16:57:16] ==================================================
[16:57:16] 🎉 下载任务完成！
[16:57:16] 📁 文件位置: C:\Users\Yt\Downloads\SteamSetup.exe
```

## 📋 部署到服务器
1. 将整个 `downloader` 目录上传到服务器
2. 确保服务器有Python 3.10环境（如需重新编译）
3. 配置文件可以动态生成或预配置
4. 下载器支持控制台模式和GUI模式

## ⚠️ 注意事项
- 下载器会自动禁用SSL验证和代理设置以解决网络问题
- 支持多种IP获取服务，确保在各种网络环境下都能正常工作
- 文件会自动保存到用户的Downloads目录
- 如果文件名重复，会自动添加数字后缀

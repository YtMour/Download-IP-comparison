@echo off
echo ========================================
echo 安全下载器编译脚本
echo ========================================
echo.

:: 检查Python环境
python --version >nul 2>&1
if errorlevel 1 (
    echo 错误: 未找到Python环境
    echo 请先安装Python 3.7+
    pause
    exit /b 1
)

echo ✅ Python环境检查通过

:: 检查依赖包
echo 📦 检查依赖包...
python -c "import requests" >nul 2>&1
if errorlevel 1 (
    echo 安装requests包...
    pip install requests
)

python -c "import tkinter" >nul 2>&1
if errorlevel 1 (
    echo 错误: tkinter未安装
    echo 请安装完整的Python环境
    pause
    exit /b 1
)

:: 检查PyInstaller
python -c "import PyInstaller" >nul 2>&1
if errorlevel 1 (
    echo 安装PyInstaller...
    pip install pyinstaller
)

echo ✅ 依赖包检查完成

:: 清理旧文件
echo 🧹 清理旧文件...
if exist "dist" rmdir /s /q "dist"
if exist "build" rmdir /s /q "build"
if exist "*.spec" del "*.spec"

:: 编译程序
echo 🔨 开始编译...
echo.

:: 基本编译命令
pyinstaller --onefile --windowed --name="SecureDownloader" downloader.py

if errorlevel 1 (
    echo ❌ 编译失败
    pause
    exit /b 1
)

:: 复制文件到输出目录
echo 📁 整理输出文件...
if not exist "output" mkdir "output"

copy "dist\SecureDownloader.exe" "output\downloader.exe"
copy "config.ini" "output\config.ini"
copy "README_下载器.md" "output\README.md"

:: 创建使用说明
echo 📝 创建使用说明...
echo. > "output\使用说明.txt"
echo 安全下载器 v2.0 >> "output\使用说明.txt"
echo. >> "output\使用说明.txt"
echo 使用方法: >> "output\使用说明.txt"
echo 1. 确保config.ini配置正确 >> "output\使用说明.txt"
echo 2. 双击downloader.exe启动程序 >> "output\使用说明.txt"
echo 3. 点击"验证IP"进行身份验证 >> "output\使用说明.txt"
echo 4. 验证成功后点击"开始下载" >> "output\使用说明.txt"
echo. >> "output\使用说明.txt"
echo 技术支持: 查看README.md >> "output\使用说明.txt"

:: 显示结果
echo.
echo ========================================
echo ✅ 编译完成！
echo ========================================
echo.
echo 输出文件位置: output\
echo - downloader.exe     (主程序)
echo - config.ini         (配置模板)
echo - README.md          (详细说明)
echo - 使用说明.txt       (快速指南)
echo.

:: 获取文件大小
for %%A in ("output\downloader.exe") do (
    set size=%%~zA
    set /a sizeMB=!size!/1024/1024
    echo 程序大小: !sizeMB! MB
)

echo.
echo 🎉 编译成功！可以分发output目录中的文件
echo.
pause

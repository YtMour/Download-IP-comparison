#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
优化构建脚本 - 构建修改后的downloader.py

使用urllib替代requests，避免certifi依赖问题
"""

import os
import sys
import subprocess
import shutil
from pathlib import Path

def clean_build():
    """清理构建文件"""
    print("🧹 清理构建文件...")
    
    patterns = ["*.build", "*.dist", "*.onefile-build", "Downloader.exe"]
    for pattern in patterns:
        for path in Path(".").glob(pattern):
            if path.is_dir():
                shutil.rmtree(path)
                print(f"   删除目录: {path}")
            elif path.is_file():
                path.unlink()
                print(f"   删除文件: {path}")

def build_optimized_downloader():
    """构建优化版下载器"""
    print("🔨 构建优化版下载器...")
    
    cmd = [
        sys.executable, "-m", "nuitka",
        
        # 基本选项
        "--onefile",
        "--standalone", 
        "--assume-yes-for-downloads",
        
        # 输出配置
        "--output-filename=Downloader.exe",
        
        # Windows配置
        "--windows-disable-console",  # 禁用控制台
        "--windows-company-name=SecureDownload Technologies",
        "--windows-product-name=IP Verification Downloader",
        "--windows-file-version=2.1.0.0",
        "--windows-product-version=2.1.0.0",
        "--windows-file-description=Professional IP Verification Download Tool",
        
        # 图标
        "--windows-icon-from-ico=downloader.ico" if Path("downloader.ico").exists() else "",
        
        # 插件
        "--enable-plugin=tk-inter",
        
        # 包含必要模块
        "--include-module=tkinter",
        "--include-module=tkinter.ttk",
        "--include-module=tkinter.messagebox",
        "--include-module=tkinter.filedialog",
        "--include-module=urllib.request",
        "--include-module=urllib.parse",
        "--include-module=urllib.error",
        "--include-module=json",
        "--include-module=socket",
        "--include-module=ssl",
        "--include-module=threading",
        "--include-module=configparser",
        "--include-module=webbrowser",
        "--include-module=platform",
        "--include-module=subprocess",
        "--include-module=hashlib",
        "--include-module=ctypes",
        "--include-module=ctypes.wintypes",
        
        # 排除问题模块
        "--nofollow-import-to=requests",
        "--nofollow-import-to=certifi",
        "--nofollow-import-to=urllib3",
        "--nofollow-import-to=charset_normalizer",
        "--nofollow-import-to=idna",
        "--nofollow-import-to=cryptography",
        "--nofollow-import-to=OpenSSL",
        
        # 性能优化
        "--lto=yes",
        "--jobs=2",
        
        # 调试选项
        "--show-progress",
        
        # 源文件
        "downloader.py"
    ]
    
    # 过滤空字符串
    cmd = [arg for arg in cmd if arg]
    
    print("📝 开始构建...")
    print("⏳ 这可能需要5-15分钟，请耐心等待...")
    
    try:
        result = subprocess.run(cmd, check=True)
        
        # 检查输出文件
        if Path("Downloader.exe").exists():
            file_size = Path("Downloader.exe").stat().st_size / (1024 * 1024)
            print(f"✅ 构建成功! 文件大小: {file_size:.1f} MB")
            return True
        else:
            print("❌ 构建失败: 未找到输出文件")
            return False
            
    except subprocess.CalledProcessError as e:
        print(f"❌ 构建失败: {e}")
        return False

def copy_config_files():
    """复制配置文件"""
    print("📋 复制配置文件...")
    
    files_to_copy = ["config.ini", "README.md", "LICENSE.txt"]
    
    for filename in files_to_copy:
        if Path(filename).exists():
            print(f"   ✅ {filename} 已存在")
        else:
            print(f"   ⚠️ {filename} 不存在")



def test_exe():
    """测试生成的exe文件"""
    print("🧪 测试exe文件...")

    exe_path = Path("Downloader.exe")
    if not exe_path.exists():
        print("❌ exe文件不存在")
        return False

    print(f"✅ 文件存在: {exe_path}")
    print(f"📦 文件大小: {exe_path.stat().st_size / (1024 * 1024):.1f} MB")

    # 尝试运行exe文件（非阻塞）
    try:
        print("🚀 尝试启动程序...")
        subprocess.Popen([str(exe_path)], cwd=Path.cwd())
        print("✅ 程序已启动，请检查是否正常运行")
        return True
    except Exception as e:
        print(f"❌ 启动失败: {e}")
        return False

def main():
    """主函数"""
    print("🚀 优化版下载器构建工具")
    print("=" * 50)
    
    # 检查Nuitka
    try:
        result = subprocess.run([sys.executable, "-m", "nuitka", "--version"], 
                              capture_output=True, text=True, check=True)
        print(f"✅ Nuitka版本: {result.stdout.strip()}")
    except:
        print("❌ Nuitka未安装，请运行: pip install nuitka")
        return False
    
    # 检查源文件
    if not Path("downloader.py").exists():
        print("❌ 源文件 downloader.py 不存在")
        return False
    
    # 清理构建
    clean_build()
    
    # 构建
    if not build_optimized_downloader():
        return False

    # 复制文件
    copy_config_files()

    # 测试
    test_exe()
    
    print("\n🎉 构建完成!")
    print("📋 优化特点:")
    print("1. ✅ 使用urllib替代requests，避免certifi问题")
    print("2. ✅ 保持所有原有功能")
    print("3. ✅ 真正的单文件exe")
    print("4. ✅ 更稳定的网络处理")
    print("5. ✅ 完整的GUI界面")
    
    print("\n📋 功能保持:")
    print("- IP验证和后端通信")
    print("- 多站点下载支持")
    print("- 进度显示和取消功能")
    print("- 暗色主题界面")
    print("- 日志和调试功能")
    print("- 文件管理功能")
    
    return True

if __name__ == "__main__":
    success = main()
    if not success:
        input("按回车键退出...")
        sys.exit(1)

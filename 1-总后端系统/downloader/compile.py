#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
下载器编译脚本
重新编译包含最新验证逻辑的下载器
"""

import os
import sys
import subprocess
import shutil
from datetime import datetime

def print_message(emoji, message):
    """打印带表情的消息"""
    print(f"{emoji} {message}")

def check_environment():
    """检查编译环境"""
    print_message("🔍", "检查编译环境...")
    
    # 检查Python版本
    python_version = sys.version_info
    print(f"   Python版本: {python_version.major}.{python_version.minor}.{python_version.micro}")
    
    if python_version.major < 3 or (python_version.major == 3 and python_version.minor < 7):
        print_message("❌", "需要Python 3.7或更高版本")
        return False
    
    # 检查PyInstaller
    try:
        import PyInstaller
        print(f"   PyInstaller: {PyInstaller.__version__}")
    except ImportError:
        print_message("📦", "安装PyInstaller...")
        try:
            subprocess.check_call([sys.executable, "-m", "pip", "install", "pyinstaller"])
            print_message("✅", "PyInstaller安装成功")
        except subprocess.CalledProcessError:
            print_message("❌", "PyInstaller安装失败")
            return False
    
    return True

def backup_old_version():
    """备份旧版本"""
    if os.path.exists("downloader.exe"):
        backup_name = f"downloader_backup_{datetime.now().strftime('%Y%m%d_%H%M%S')}.exe"
        shutil.copy2("downloader.exe", backup_name)
        print_message("📦", f"旧版本已备份为: {backup_name}")
        os.remove("downloader.exe")
    else:
        print_message("ℹ️", "未找到旧版本文件")

def compile_downloader():
    """编译下载器"""
    print_message("🔨", "开始编译下载器...")
    
    # 检查源文件
    if not os.path.exists("downloader.py"):
        print_message("❌", "未找到downloader.py源文件")
        return False
    
    # PyInstaller编译命令
    cmd = [
        "pyinstaller",
        "--onefile",                    # 打包成单个文件
        "--windowed",                   # 无控制台窗口
        "--name=downloader",            # 输出文件名
        "downloader.py"
    ]
    
    print_message("⚙️", f"执行编译命令...")
    
    try:
        # 执行编译
        result = subprocess.run(cmd, capture_output=True, text=True, encoding='utf-8')
        
        if result.returncode == 0:
            print_message("✅", "编译成功")
            
            # 移动编译结果
            if os.path.exists("dist/downloader.exe"):
                shutil.move("dist/downloader.exe", "downloader.exe")
                print_message("📁", "下载器已生成: downloader.exe")
                
                # 清理临时文件
                cleanup_temp_files()
                
                return True
            else:
                print_message("❌", "编译输出文件未找到")
                return False
        else:
            print_message("❌", "编译失败")
            if result.stderr:
                print("错误信息:")
                print(result.stderr)
            return False
            
    except Exception as e:
        print_message("❌", f"编译过程出错: {str(e)}")
        return False

def cleanup_temp_files():
    """清理临时文件"""
    temp_dirs = ["build", "dist"]
    temp_files = ["downloader.spec"]
    
    for temp_dir in temp_dirs:
        if os.path.exists(temp_dir):
            shutil.rmtree(temp_dir)
    
    for temp_file in temp_files:
        if os.path.exists(temp_file):
            os.remove(temp_file)
    
    print_message("🧹", "临时文件已清理")

def get_file_info():
    """获取文件信息"""
    if os.path.exists("downloader.exe"):
        file_size = os.path.getsize("downloader.exe")
        file_time = datetime.fromtimestamp(os.path.getmtime("downloader.exe"))
        
        size_mb = file_size / (1024 * 1024)
        print_message("📊", f"文件大小: {size_mb:.1f} MB")
        print_message("🕒", f"编译时间: {file_time.strftime('%Y-%m-%d %H:%M:%S')}")

def main():
    """主函数"""
    print("=" * 50)
    print("🚀 下载器编译脚本")
    print("=" * 50)
    print(f"📅 当前时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"📁 工作目录: {os.getcwd()}")
    print()
    
    try:
        # 检查环境
        if not check_environment():
            print_message("❌", "环境检查失败")
            return False
        
        print()
        
        # 备份旧版本
        print_message("📦", "备份旧版本...")
        backup_old_version()
        
        print()
        
        # 编译新版本
        if not compile_downloader():
            print_message("❌", "编译失败")
            return False
        
        print()
        
        # 获取文件信息
        get_file_info()
        
        print()
        print("=" * 50)
        print_message("🎉", "编译完成！")
        print("=" * 50)
        print()
        print("📋 新版本特性:")
        print("   ✅ 自动执行IP验证和下载流程")
        print("   ✅ 包含最新的数据库查询验证逻辑")
        print("   ✅ 优化的日志显示格式")
        print("   ✅ 智能跳过验证机制")
        print()
        print("🔍 新的验证逻辑:")
        print("   - IP存在于数据库 → 执行验证逻辑")
        print("   - IP不存在于数据库 → 跳过验证直接下载")
        print()
        print_message("🚀", "新的下载器已准备就绪！")
        
        return True
        
    except KeyboardInterrupt:
        print()
        print_message("⚠️", "用户中断操作")
        return False
    except Exception as e:
        print()
        print_message("❌", f"发生未预期的错误: {str(e)}")
        return False

if __name__ == "__main__":
    success = main()
    
    print()
    if success:
        print_message("✅", "编译成功完成")
    else:
        print_message("❌", "编译失败")
    
    input("\n按回车键退出...")

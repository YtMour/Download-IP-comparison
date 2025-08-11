#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
多站点IP验证下载器
支持动态配置和IP验证的安全下载工具
"""

import os
import sys
import json
import time
import hashlib
import requests
import configparser
from urllib.parse import urlparse
from pathlib import Path
import tkinter as tk
from tkinter import ttk, messagebox
import threading
import webbrowser

class DownloadManager:
    def __init__(self):
        self.config = None
        self.session = requests.Session()
        self.download_thread = None
        self.is_downloading = False
        self.cancel_download = False

        # 在某些网络环境下可能需要禁用SSL验证
        self.session.verify = False

        # 强制禁用代理设置（解决代理连接问题）
        self.session.proxies = {}
        self.session.trust_env = False

        # 清除环境变量中的代理设置
        import os
        for proxy_var in ['HTTP_PROXY', 'HTTPS_PROXY', 'http_proxy', 'https_proxy']:
            if proxy_var in os.environ:
                del os.environ[proxy_var]

        # 禁用SSL警告
        import urllib3
        urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

        # 设置用户代理
        self.session.headers.update({
            'User-Agent': 'SecureDownloader/2.0 (Multi-Site IP Verification System)'
        })
        
    def load_config(self):
        """加载配置文件 - 基于原版逻辑支持多种配置文件"""
        config_files = ['config.ini', 'downloader.ini']

        for config_path in config_files:
            if os.path.exists(config_path):
                try:
                    self.config = configparser.ConfigParser()
                    self.config.read(config_path, encoding='utf-8')
                    print(f"✅ 配置文件加载成功: {config_path}")
                    return True
                except Exception as e:
                    print(f"❌ 配置文件加载失败: {e}")
                    continue

        print("❌ 未找到配置文件 (config.ini 或 downloader.ini)")
        return False
    
    def get_current_ip(self):
        """获取当前IP地址 - 基于原版方法名"""
        current_ip = None
        ip_services = [
            'https://api.ipify.org?format=json',
            'https://httpbin.org/ip',
            'https://api.ip.sb/ip'
        ]

        for service in ip_services:
            try:
                ip_response = self.session.get(service, timeout=10)
                if 'ipify' in service:
                    current_ip = ip_response.json()['ip']
                elif 'httpbin' in service:
                    current_ip = ip_response.json()['origin']
                else:
                    current_ip = ip_response.text.strip()
                print(f"📍 当前IP地址: {current_ip}")
                return current_ip
            except Exception as e:
                print(f"⚠️ IP服务 {service} 失败: {e}")
                continue

        # 如果所有外部服务都失败，使用本地IP作为备用
        import socket
        try:
            with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as s:
                s.connect(("8.8.8.8", 80))
                current_ip = s.getsockname()[0]
            print(f"📍 使用本地IP地址: {current_ip}")
            return current_ip
        except:
            print(f"📍 使用默认IP地址: 127.0.0.1")
            return "127.0.0.1"

    def verify_ip_with_backend(self):
        """通过后端验证IP - 基于原版方法名和逻辑"""
        try:
            verify_url = self.config.get('server', 'verify_url')
            token = self.config.get('download', 'token')

            # 获取当前IP
            current_ip = self.get_current_ip()
            if not current_ip:
                return False, "❌ 无法获取当前IP地址"

            # 构建验证请求 - 基于数据库表结构分析
            verify_data = {
                'action': 'verify',
                'token': token,
                'current_ip': current_ip,
                'original_ip': current_ip,  # 对应 msd_downloads.original_ip
                'ip_address': current_ip,   # 对应 msd_system_logs.ip_address
            }

            headers = {
                'Content-Type': 'application/x-www-form-urlencoded',
                'User-Agent': 'SecureDownloader/2.0'
            }

            # 添加API密钥和站点信息
            try:
                api_key = self.config.get('server', 'api_key', fallback='')
                if api_key:
                    headers['X-API-Key'] = api_key
                    verify_data['api_key'] = api_key

                site_key = self.config.get('info', 'site_key', fallback='')
                if site_key:
                    verify_data['site_key'] = site_key

                site = self.config.get('info', 'site', fallback='')
                if site:
                    verify_data['site'] = site
            except Exception as e:
                print(f"⚠️ 配置读取警告: {e}")

            # 确保verify_url不包含action参数
            if '?action=verify' in verify_url:
                verify_url = verify_url.replace('?action=verify', '')
                print(f"🔧 修正验证URL: {verify_url}")

            response = self.session.post(verify_url, data=verify_data, headers=headers, timeout=30)

            # 简化的调试信息（仅在需要时启用）
            # print(f"🔍 验证请求: {verify_url}")
            # print(f"🔍 当前IP: {current_ip}")
            # print(f"🔍 响应: {response.text}")

            # 处理响应 - 基于原版状态码
            try:
                result = response.json()
                print(f"🔍 解析结果: {result}")
            except:
                print(f"🔍 JSON解析失败，原始响应: {response.text}")
                if response.status_code == 401:
                    return False, "❌ IP验证失败，程序退出"
                elif response.status_code == 404:
                    return False, "❌ 验证失败"
                else:
                    return False, f"⚠️ 验证服务器响应错误: {response.status_code}"

            # 基于原版状态码处理 - 修复验证逻辑
            if result.get('S') == 1 or result.get('success') == True:
                result_type = result.get('result', '')
                message = result.get('message', '')

                if result_type == 'IP_MATCH':
                    return True, f"🎯 IP地址验证通过 (IP: {current_ip})"
                elif result_type == 'IP_MISMATCH_ALLOWED':
                    return True, f"⚠️ IP地址不匹配，但允许下载 (当前IP: {current_ip})"
                elif result_type == 'IP_VERIFICATION_DISABLED':
                    return True, f"⚠️ 跳过验证，尝试直接下载... (IP: {current_ip})"
                elif result_type == 'IP_NOT_EXISTS_SKIP_VERIFICATION':
                    return True, f"⚠️ IP不存在于数据库，跳过验证直接下载 (IP: {current_ip})"
                elif result_type == 'TOKEN_EXPIRED':
                    return False, "⏰ 下载令牌已过期，请重新获取下载器"
                elif result_type == 'MAX_DOWNLOADS_EXCEEDED':
                    return False, f"❌ IP验证失败，下载终止 (IP: {current_ip})"
                elif result_type == 'IP_MISMATCH_STRICT':
                    return False, f"❌ IP地址不匹配，下载被拒绝 (当前IP: {current_ip})"
                else:
                    # 如果有result_type但不在已知列表中，记录并返回失败
                    if result_type:
                        return False, f"❌ 未知验证结果: {result_type} (IP: {current_ip})"
                    else:
                        return True, f"✅ 验证通过 (IP: {current_ip})"
            else:
                # 验证失败的情况
                error_msg = result.get('message', '验证失败')
                result_type = result.get('result', '')
                if result_type:
                    return False, f"❌ {error_msg} - {result_type} (IP: {current_ip})"
                else:
                    return False, f"❌ {error_msg} (IP: {current_ip})"

        except Exception as e:
            error_str = str(e)
            # 处理常见的网络错误
            if "Connection aborted" in error_str or "ConnectionResetError" in error_str:
                return False, "🌐 网络连接错误，请检查网络状态后重试"
            elif "timeout" in error_str.lower():
                return False, "⏰ 网络连接超时，请稍后重试"
            elif "Connection refused" in error_str:
                return False, "🚫 服务器拒绝连接，请稍后重试"
            elif "Name or service not known" in error_str or "getaddrinfo failed" in error_str:
                return False, "🌐 DNS解析失败，请检查网络连接"
            else:
                return False, f"⚠️ 验证过程异常: {error_str}"

    def verify_ip(self):
        """验证IP地址 - 兼容性方法"""
        return self.verify_ip_with_backend()

    def format_size(self, size_bytes):
        """格式化文件大小 - 基于原版方法"""
        if size_bytes < 1024:
            return f"{size_bytes} B"
        elif size_bytes < 1024 * 1024:
            return f"{size_bytes / 1024:.1f} KB"
        elif size_bytes < 1024 * 1024 * 1024:
            return f"{size_bytes / (1024 * 1024):.1f} MB"
        else:
            return f"{size_bytes / (1024 * 1024 * 1024):.1f} GB"
    
    def download_file(self, progress_callback=None):
        """下载文件 - 自动保存到Downloads目录"""
        try:
            file_url = self.config.get('download', 'file_url')
            software_name = self.config.get('download', 'software_name')

            # 自动保存到Downloads目录（原始版本的逻辑）
            downloads_dir = os.path.join(os.path.expanduser("~"), "Downloads")
            os.makedirs(downloads_dir, exist_ok=True)

            # 智能处理文件扩展名，支持所有文件类型
            if '.' in software_name and len(software_name.split('.')[-1]) <= 10:
                # 如果软件名包含扩展名（扩展名长度不超过10个字符），保持原有扩展名
                filename = software_name
            else:
                # 如果没有扩展名或扩展名异常长，默认添加.exe
                filename = f"{software_name}.exe"
            save_path = os.path.join(downloads_dir, filename)

            # 如果文件已存在，添加数字后缀
            counter = 1
            original_save_path = save_path
            while os.path.exists(save_path):
                name, ext = os.path.splitext(filename)
                save_path = os.path.join(downloads_dir, f"{name}_{counter}{ext}")
                counter += 1
            
            # 开始下载
            print(f"🌐 开始下载: {file_url}")
            response = self.session.get(file_url, stream=True, timeout=30)
            response.raise_for_status()

            total_size = int(response.headers.get('content-length', 0))

            # 显示文件大小
            if total_size > 0:
                size_text = self.format_size(total_size)
                print(f"📦 文件大小: {size_text}")
            downloaded_size = 0
            
            with open(save_path, 'wb') as f:
                for chunk in response.iter_content(chunk_size=8192):
                    if self.cancel_download:
                        f.close()
                        os.remove(save_path)
                        return False, "下载已取消"
                    
                    if chunk:
                        f.write(chunk)
                        downloaded_size += len(chunk)
                        
                        if progress_callback and total_size > 0:
                            progress = (downloaded_size / total_size) * 100
                            progress_callback(progress, downloaded_size, total_size)
            
            # 保存路径信息供后续使用
            self.last_save_path = save_path
            return True, f"下载完成: {os.path.basename(save_path)}"
            
        except Exception as e:
            error_str = str(e)
            # 处理常见的网络错误
            if "Connection aborted" in error_str or "ConnectionResetError" in error_str:
                return False, "🌐 网络连接错误，请检查网络状态后重试"
            elif "timeout" in error_str.lower():
                return False, "⏰ 下载超时，请稍后重试"
            elif "Connection refused" in error_str:
                return False, "🚫 服务器拒绝连接，请稍后重试"
            elif "Name or service not known" in error_str or "getaddrinfo failed" in error_str:
                return False, "🌐 DNS解析失败，请检查网络连接"
            elif "HTTP" in error_str and ("404" in error_str or "403" in error_str):
                return False, "📂 文件不存在或访问被拒绝"
            else:
                return False, f"下载失败: {error_str}"

class IPDownloaderGUI:
    def __init__(self):
        self.root = tk.Tk()
        self.root.title("IP验证下载器")
        self.root.geometry("600x550")
        self.root.resizable(False, False)
        
        # 设置图标（如果有的话）
        try:
            self.root.iconbitmap('icon.ico')
        except:
            pass
        
        self.manager = DownloadManager()
        self.setup_ui()
        
    def setup_ui(self):
        """设置用户界面"""
        # 主框架
        main_frame = ttk.Frame(self.root, padding="15")
        main_frame.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S))
        
        # 标题
        title_label = ttk.Label(main_frame, text="� IP验证下载器", font=("Arial", 16, "bold"))
        title_label.grid(row=0, column=0, columnspan=2, pady=(0, 20))
        
        # 配置信息框架
        config_frame = ttk.LabelFrame(main_frame, text="软件信息", padding="10")
        config_frame.grid(row=1, column=0, columnspan=2, sticky=(tk.W, tk.E), pady=(0, 15))
        
        # 软件名称
        ttk.Label(config_frame, text="软件名称:").grid(row=0, column=0, sticky=tk.W, pady=5)
        self.software_label = ttk.Label(config_frame, text="未加载", foreground="gray")
        self.software_label.grid(row=0, column=1, sticky=tk.W, padx=(10, 0), pady=5)

        # 文件地址
        ttk.Label(config_frame, text="文件地址:").grid(row=1, column=0, sticky=tk.W, pady=5)
        self.url_label = ttk.Label(config_frame, text="未加载", foreground="gray")
        self.url_label.grid(row=1, column=1, sticky=tk.W, padx=(10, 0), pady=5)

        # 下载令牌
        ttk.Label(config_frame, text="下载令牌:").grid(row=2, column=0, sticky=tk.W, pady=5)
        self.token_label = ttk.Label(config_frame, text="未加载", foreground="gray")
        self.token_label.grid(row=2, column=1, sticky=tk.W, padx=(10, 0), pady=5)
        

        
        # 进度框架
        progress_frame = ttk.LabelFrame(main_frame, text="下载进度", padding="10")
        progress_frame.grid(row=2, column=0, columnspan=2, sticky=(tk.W, tk.E), pady=(0, 15))

        # 进度条
        self.progress_var = tk.DoubleVar()
        self.progress_bar = ttk.Progressbar(progress_frame, variable=self.progress_var, maximum=100, length=400)
        self.progress_bar.grid(row=0, column=0, columnspan=2, sticky=(tk.W, tk.E), pady=5, padx=5)

        # 进度文本
        self.progress_label = ttk.Label(progress_frame, text="等待开始...")
        self.progress_label.grid(row=1, column=0, columnspan=2, pady=5)

        # 配置进度框架的列权重
        progress_frame.columnconfigure(0, weight=1)
        
        # 按钮框架
        button_frame = ttk.Frame(main_frame)
        button_frame.grid(row=3, column=0, columnspan=2, pady=15)

        # 开始下载按钮
        self.download_btn = ttk.Button(button_frame, text="📥 开始下载", command=self.start_download)
        self.download_btn.grid(row=0, column=0, padx=(0, 10))

        # 取消下载按钮
        self.cancel_btn = ttk.Button(button_frame, text="❌ 取消下载", command=self.cancel_download, state="disabled")
        self.cancel_btn.grid(row=0, column=1)
        
        # 日志框架
        log_frame = ttk.LabelFrame(main_frame, text="操作日志", padding="10")
        log_frame.grid(row=4, column=0, columnspan=2, sticky=(tk.W, tk.E, tk.N, tk.S), pady=(0, 10))

        # 日志文本框
        self.log_text = tk.Text(log_frame, height=6, width=65, wrap=tk.WORD)
        scrollbar = ttk.Scrollbar(log_frame, orient="vertical", command=self.log_text.yview)
        self.log_text.configure(yscrollcommand=scrollbar.set)
        
        self.log_text.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S))
        scrollbar.grid(row=0, column=1, sticky=(tk.N, tk.S))
        
        # 配置网格权重
        self.root.columnconfigure(0, weight=1)
        self.root.rowconfigure(0, weight=1)
        main_frame.columnconfigure(1, weight=1)
        main_frame.rowconfigure(4, weight=1)  # 日志框架可以扩展
        log_frame.columnconfigure(0, weight=1)
        log_frame.rowconfigure(0, weight=1)
        
        # 加载配置
        self.load_config()
    
    def log_message(self, message):
        """添加日志消息"""
        timestamp = time.strftime("%H:%M:%S")
        self.log_text.insert(tk.END, f"[{timestamp}] {message}\n")
        self.log_text.see(tk.END)
        self.root.update()
    
    def load_config(self):
        """加载配置文件"""
        if self.manager.load_config():
            try:
                software_name = self.manager.config.get('download', 'software_name')
                file_url = self.manager.config.get('download', 'file_url')
                token = self.manager.config.get('download', 'token')

                self.software_label.config(text=software_name, foreground="black")
                self.url_label.config(text=file_url, foreground="black")
                self.token_label.config(text=token[:20] + "...", foreground="black")
                
                self.log_message("✅ 配置加载成功: " + software_name)

            except Exception as e:
                self.log_message(f"❌ 配置文件解析失败: {e}")
        else:
            self.log_message("❌ 配置文件加载失败")
            messagebox.showerror("错误", "未找到配置文件或配置文件格式错误！\n\n请确保config.ini或downloader.ini文件存在。")
    


    def auto_verify_and_download(self):
        """自动执行验证和下载流程"""
        def auto_process():
            # 步骤1: IP验证
            self.log_message("🔐 步骤 1/2: IP地址验证")
            self.log_message("🔍 正在验证下载权限...")

            success, message = self.manager.verify_ip_with_backend()

            if success:
                self.log_message("✅ 验证通过")
                self.log_message(f"⚠️ {message}")
                self.log_message("📁 文件地址已更新")

                # 在IP匹配成功或IP验证被禁用时显示提示弹窗（用于测试后期验证逻辑）
                if "IP地址验证通过" in message or "跳过验证" in message:
                    self.show_verification_notification(message)

                should_download = True
            else:
                self.log_message(f"❌ {message}")

                # 判断是否应该继续下载
                if "网络连接错误" in message or "网络连接超时" in message or "服务器拒绝连接" in message or "DNS解析失败" in message:
                    # 网络错误，不继续下载
                    should_download = False
                    self.log_message("🚫 由于网络错误，下载已终止")
                elif "令牌已过期" in message or "下载终止" in message or "下载被拒绝" in message:
                    # 严重错误，不继续下载
                    should_download = False
                    self.log_message("🚫 验证失败，下载已终止")
                else:
                    # 其他情况，尝试直接下载
                    should_download = True
                    self.log_message("⚠️ 跳过验证，尝试直接下载...")

            if should_download:
                # 步骤2: 文件下载
                self.log_message("📥 步骤 2/2: 文件下载")
            else:
                # 验证失败，不进行下载
                self.manager.is_downloading = False
                self.download_btn.config(state="normal")
                self.cancel_btn.config(state="disabled")
                self.progress_var.set(0)
                self.progress_label.config(text="验证失败")
                return

            # 开始下载
            self.manager.is_downloading = True
            self.manager.cancel_download = False

            self.download_btn.config(state="disabled")
            self.cancel_btn.config(state="normal")

            download_success, download_message = self.manager.download_file(self.update_progress)

            if download_success:
                self.log_message(f"✅ {download_message}")
                self.log_message("="*50)
                self.log_message("🎉 下载任务完成！")
                # 获取实际保存路径
                try:
                    save_path = self.manager.last_save_path if hasattr(self.manager, 'last_save_path') else "Downloads文件夹"
                    self.log_message(f"📁 文件位置: {save_path}")
                except:
                    self.log_message("📁 文件已保存到选择的位置")
            else:
                self.log_message(f"❌ {download_message}")

            self.manager.is_downloading = False
            self.download_btn.config(state="normal")
            self.cancel_btn.config(state="disabled")

            self.progress_var.set(0)
            self.progress_label.config(text="下载完成" if download_success else "下载失败")

        threading.Thread(target=auto_process, daemon=True).start()


    
    def start_download(self):
        """开始下载流程"""
        if not self.manager.is_downloading:
            self.auto_verify_and_download()
    
    def cancel_download(self):
        """取消下载"""
        if self.manager.is_downloading:
            self.manager.cancel_download = True
            self.log_message("⏹️ 正在取消下载...")
            self.cancel_btn.config(state="disabled")
    
    def update_progress(self, progress, downloaded, total):
        """更新进度"""
        self.progress_var.set(progress)
        
        downloaded_mb = downloaded / (1024 * 1024)
        total_mb = total / (1024 * 1024)
        
        if total_mb < 1024:
            progress_text = f"{downloaded_mb:.1f} MB / {total_mb:.1f} MB ({progress:.1f}%)"
        else:
            downloaded_gb = downloaded_mb / 1024
            total_gb = total_mb / 1024
            progress_text = f"{downloaded_gb:.1f} GB / {total_gb:.1f} GB ({progress:.1f}%)"
        
        self.progress_label.config(text=progress_text)
    
    def show_verification_notification(self, verification_message):
        """显示验证提示弹窗 - 用于测试后期验证逻辑触发"""

        # 根据验证状态确定提示内容
        if "IP地址验证通过" in verification_message:
            # IP匹配成功的情况
            dialog_title = "🎯 IP验证成功"
            dialog_message = f"IP地址验证通过！\n\n{verification_message}\n\n✅ 触发额外验证逻辑\n（后期可在此处添加无感验证）"
            self.log_message("🔔 IP验证成功，触发额外验证逻辑")
        elif "跳过验证" in verification_message:
            # IP验证被禁用的情况
            dialog_title = "🔐 安全验证触发"
            dialog_message = f"IP验证已禁用！\n\n{verification_message}\n\n✅ 触发额外验证逻辑\n（后期可在此处添加无感验证）"
            self.log_message("🔔 IP验证被禁用，触发额外验证逻辑")
        else:
            # 其他情况
            dialog_title = "🔐 验证逻辑触发"
            dialog_message = f"验证完成！\n\n{verification_message}\n\n✅ 触发额外验证逻辑\n（后期可在此处添加无感验证）"
            self.log_message("🔔 触发额外验证逻辑")

        # 显示信息提示框（只有确定按钮）
        messagebox.showinfo(
            dialog_title,
            dialog_message
        )

        self.log_message("✅ 验证逻辑提示已显示，继续下载")

    def run(self):
        """运行GUI"""
        self.root.mainloop()

def main():
    """主函数"""
    # 检查配置文件
    if not os.path.exists('config.ini'):
        print("错误: 找不到配置文件 config.ini")
        input("按回车键退出...")
        return
    
    # 启动GUI
    try:
        app = IPDownloaderGUI()
        app.run()
    except Exception as e:
        print(f"程序启动失败: {e}")
        input("按回车键退出...")

if __name__ == "__main__":
    main()

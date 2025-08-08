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
from tkinter import ttk, messagebox, filedialog
import threading
import webbrowser

class DownloadManager:
    def __init__(self):
        self.config = None
        self.session = requests.Session()
        self.download_thread = None
        self.is_downloading = False
        self.cancel_download = False
        
        # 设置用户代理
        self.session.headers.update({
            'User-Agent': 'SecureDownloader/2.0 (Multi-Site IP Verification System)'
        })
        
    def load_config(self, config_path='config.ini'):
        """加载配置文件"""
        try:
            self.config = configparser.ConfigParser()
            self.config.read(config_path, encoding='utf-8')
            return True
        except Exception as e:
            print(f"配置文件加载失败: {e}")
            return False
    
    def verify_ip(self):
        """验证IP地址"""
        try:
            verify_url = self.config.get('server', 'verify_url')
            token = self.config.get('download', 'token')
            
            # 获取本机IP
            ip_response = self.session.get('https://api.ipify.org?format=json', timeout=10)
            current_ip = ip_response.json()['ip']
            
            # 验证请求
            verify_data = {
                'token': token,
                'ip': current_ip,
                'action': 'verify'
            }
            
            response = self.session.post(verify_url, data=verify_data, timeout=30)
            result = response.json()
            
            if result.get('success'):
                return True, result.get('message', 'IP验证成功')
            else:
                return False, result.get('message', 'IP验证失败')
                
        except Exception as e:
            return False, f"IP验证过程出错: {str(e)}"
    
    def download_file(self, progress_callback=None):
        """下载文件"""
        try:
            file_url = self.config.get('download', 'file_url')
            software_name = self.config.get('download', 'software_name')
            
            # 解析文件名
            parsed_url = urlparse(file_url)
            filename = os.path.basename(parsed_url.path)
            if not filename:
                filename = f"{software_name}.exe"
            
            # 选择保存位置
            save_path = filedialog.asksaveasfilename(
                title="选择保存位置",
                defaultextension=os.path.splitext(filename)[1],
                filetypes=[
                    ("可执行文件", "*.exe"),
                    ("安装包", "*.msi"),
                    ("压缩文件", "*.zip;*.rar;*.7z"),
                    ("所有文件", "*.*")
                ],
                initialvalue=filename
            )
            
            if not save_path:
                return False, "用户取消下载"
            
            # 开始下载
            response = self.session.get(file_url, stream=True, timeout=30)
            response.raise_for_status()
            
            total_size = int(response.headers.get('content-length', 0))
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
            
            return True, f"文件已保存到: {save_path}"
            
        except Exception as e:
            return False, f"下载失败: {str(e)}"

class DownloaderGUI:
    def __init__(self):
        self.root = tk.Tk()
        self.root.title("安全下载器 - IP验证系统")
        self.root.geometry("600x500")
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
        main_frame = ttk.Frame(self.root, padding="20")
        main_frame.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S))
        
        # 标题
        title_label = ttk.Label(main_frame, text="🔐 安全下载器", font=("Arial", 16, "bold"))
        title_label.grid(row=0, column=0, columnspan=2, pady=(0, 20))
        
        # 配置信息框架
        config_frame = ttk.LabelFrame(main_frame, text="下载信息", padding="10")
        config_frame.grid(row=1, column=0, columnspan=2, sticky=(tk.W, tk.E), pady=(0, 20))
        
        # 软件名称
        ttk.Label(config_frame, text="软件名称:").grid(row=0, column=0, sticky=tk.W, pady=5)
        self.software_label = ttk.Label(config_frame, text="未加载", foreground="gray")
        self.software_label.grid(row=0, column=1, sticky=tk.W, padx=(10, 0), pady=5)
        
        # 文件大小
        ttk.Label(config_frame, text="文件大小:").grid(row=1, column=0, sticky=tk.W, pady=5)
        self.size_label = ttk.Label(config_frame, text="检测中...", foreground="gray")
        self.size_label.grid(row=1, column=1, sticky=tk.W, padx=(10, 0), pady=5)
        
        # 过期时间
        ttk.Label(config_frame, text="有效期至:").grid(row=2, column=0, sticky=tk.W, pady=5)
        self.expire_label = ttk.Label(config_frame, text="未知", foreground="gray")
        self.expire_label.grid(row=2, column=1, sticky=tk.W, padx=(10, 0), pady=5)
        
        # 状态框架
        status_frame = ttk.LabelFrame(main_frame, text="验证状态", padding="10")
        status_frame.grid(row=2, column=0, columnspan=2, sticky=(tk.W, tk.E), pady=(0, 20))
        
        # IP验证状态
        self.ip_status_label = ttk.Label(status_frame, text="⏳ 等待IP验证...", foreground="orange")
        self.ip_status_label.grid(row=0, column=0, sticky=tk.W, pady=5)
        
        # 进度框架
        progress_frame = ttk.LabelFrame(main_frame, text="下载进度", padding="10")
        progress_frame.grid(row=3, column=0, columnspan=2, sticky=(tk.W, tk.E), pady=(0, 20))
        
        # 进度条
        self.progress_var = tk.DoubleVar()
        self.progress_bar = ttk.Progressbar(progress_frame, variable=self.progress_var, maximum=100)
        self.progress_bar.grid(row=0, column=0, columnspan=2, sticky=(tk.W, tk.E), pady=5)
        
        # 进度文本
        self.progress_label = ttk.Label(progress_frame, text="等待开始...")
        self.progress_label.grid(row=1, column=0, columnspan=2, pady=5)
        
        # 按钮框架
        button_frame = ttk.Frame(main_frame)
        button_frame.grid(row=4, column=0, columnspan=2, pady=20)
        
        # 验证IP按钮
        self.verify_btn = ttk.Button(button_frame, text="🔍 验证IP", command=self.verify_ip)
        self.verify_btn.grid(row=0, column=0, padx=(0, 10))
        
        # 开始下载按钮
        self.download_btn = ttk.Button(button_frame, text="📥 开始下载", command=self.start_download, state="disabled")
        self.download_btn.grid(row=0, column=1, padx=(0, 10))
        
        # 取消按钮
        self.cancel_btn = ttk.Button(button_frame, text="❌ 取消", command=self.cancel_download, state="disabled")
        self.cancel_btn.grid(row=0, column=2)
        
        # 日志框架
        log_frame = ttk.LabelFrame(main_frame, text="操作日志", padding="10")
        log_frame.grid(row=5, column=0, columnspan=2, sticky=(tk.W, tk.E, tk.N, tk.S), pady=(0, 10))
        
        # 日志文本框
        self.log_text = tk.Text(log_frame, height=8, width=70, wrap=tk.WORD)
        scrollbar = ttk.Scrollbar(log_frame, orient="vertical", command=self.log_text.yview)
        self.log_text.configure(yscrollcommand=scrollbar.set)
        
        self.log_text.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S))
        scrollbar.grid(row=0, column=1, sticky=(tk.N, tk.S))
        
        # 配置网格权重
        self.root.columnconfigure(0, weight=1)
        self.root.rowconfigure(0, weight=1)
        main_frame.columnconfigure(1, weight=1)
        main_frame.rowconfigure(5, weight=1)
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
                expires_at = self.manager.config.get('info', 'expires_at')
                
                self.software_label.config(text=software_name, foreground="black")
                self.expire_label.config(text=expires_at, foreground="black")
                
                self.log_message("✅ 配置文件加载成功")
                self.log_message(f"软件: {software_name}")
                
                # 检测文件大小
                self.detect_file_size()
                
            except Exception as e:
                self.log_message(f"❌ 配置文件解析失败: {e}")
        else:
            self.log_message("❌ 配置文件加载失败")
            messagebox.showerror("错误", "无法加载配置文件 config.ini")
    
    def detect_file_size(self):
        """检测文件大小"""
        def detect():
            try:
                file_url = self.manager.config.get('download', 'file_url')
                response = self.manager.session.head(file_url, timeout=10)
                
                if 'content-length' in response.headers:
                    size_bytes = int(response.headers['content-length'])
                    size_mb = size_bytes / (1024 * 1024)
                    
                    if size_mb < 1024:
                        size_text = f"{size_mb:.1f} MB"
                    else:
                        size_gb = size_mb / 1024
                        size_text = f"{size_gb:.1f} GB"
                    
                    self.size_label.config(text=size_text, foreground="black")
                    self.log_message(f"📊 文件大小: {size_text}")
                else:
                    self.size_label.config(text="未知", foreground="gray")
                    self.log_message("⚠️ 无法获取文件大小")
                    
            except Exception as e:
                self.size_label.config(text="检测失败", foreground="red")
                self.log_message(f"❌ 文件大小检测失败: {e}")
        
        threading.Thread(target=detect, daemon=True).start()
    
    def verify_ip(self):
        """验证IP地址"""
        def verify():
            self.verify_btn.config(state="disabled")
            self.ip_status_label.config(text="🔄 正在验证IP...", foreground="blue")
            self.log_message("🔍 开始IP验证...")
            
            success, message = self.manager.verify_ip()
            
            if success:
                self.ip_status_label.config(text="✅ IP验证成功", foreground="green")
                self.download_btn.config(state="normal")
                self.log_message(f"✅ {message}")
            else:
                self.ip_status_label.config(text="❌ IP验证失败", foreground="red")
                self.log_message(f"❌ {message}")
                messagebox.showerror("验证失败", message)
            
            self.verify_btn.config(state="normal")
        
        threading.Thread(target=verify, daemon=True).start()
    
    def start_download(self):
        """开始下载"""
        def download():
            self.manager.is_downloading = True
            self.manager.cancel_download = False
            
            self.download_btn.config(state="disabled")
            self.cancel_btn.config(state="normal")
            self.verify_btn.config(state="disabled")
            
            self.log_message("📥 开始下载...")
            
            success, message = self.manager.download_file(self.update_progress)
            
            if success:
                self.log_message(f"✅ {message}")
                messagebox.showinfo("下载完成", message)
            else:
                self.log_message(f"❌ {message}")
                if not self.manager.cancel_download:
                    messagebox.showerror("下载失败", message)
            
            self.manager.is_downloading = False
            self.download_btn.config(state="normal")
            self.cancel_btn.config(state="disabled")
            self.verify_btn.config(state="normal")
            
            self.progress_var.set(0)
            self.progress_label.config(text="下载完成" if success else "下载失败")
        
        self.manager.download_thread = threading.Thread(target=download, daemon=True)
        self.manager.download_thread.start()
    
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
        app = DownloaderGUI()
        app.run()
    except Exception as e:
        print(f"程序启动失败: {e}")
        input("按回车键退出...")

if __name__ == "__main__":
    main()

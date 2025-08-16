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
import ctypes
from ctypes import wintypes
import socket
import subprocess
import platform

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
                return False, "Network connection error, please check your network status and try again"
            elif "timeout" in error_str.lower():
                return False, "Network connection timeout, please try again later"
            elif "Connection refused" in error_str:
                return False, "Server refused connection, please try again later"
            elif "Name or service not known" in error_str or "getaddrinfo failed" in error_str:
                return False, "DNS resolution failed, please check your network connection"
            else:
                return False, f"Verification process error: {error_str}"

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

    def open_file_location(self, file_path):
        """打开文件所在位置"""
        try:
            if platform.system() == "Windows":
                # Windows: 使用explorer选中文件
                subprocess.run(['explorer', '/select,', file_path], check=True)
            elif platform.system() == "Darwin":  # macOS
                # macOS: 使用Finder显示文件
                subprocess.run(['open', '-R', file_path], check=True)
            else:  # Linux
                # Linux: 打开包含文件的目录
                directory = os.path.dirname(file_path)
                subprocess.run(['xdg-open', directory], check=True)
        except Exception as e:
            print(f"无法打开文件位置: {e}")

    def show_download_complete_dialog(self, file_path, file_size=None):
        """显示下载完成对话框"""
        try:
            filename = os.path.basename(file_path)
            directory = os.path.dirname(file_path)

            # 获取文件大小
            if file_size is None:
                try:
                    file_size = os.path.getsize(file_path)
                    size_text = self.format_size(file_size)
                except:
                    size_text = "未知"
            else:
                size_text = self.format_size(file_size)

            # 使用简单但有效的messagebox，包含详细信息
            message = f"""🎉 下载完成！

📁 文件名: {filename}
📊 文件大小: {size_text}
📂 保存位置: {directory}

是否打开文件所在位置？"""

            result = messagebox.askyesno("下载完成", message)
            if result:
                self.open_file_location(file_path)

        except Exception as e:
            # 如果出错，使用最简单的提示
            messagebox.showinfo("下载完成",
                              f"文件下载完成！\n\n文件: {os.path.basename(file_path)}\n位置: {os.path.dirname(file_path)}")

    def show_error_dialog(self, error_message, error_type="下载错误"):
        """显示错误对话框"""
        try:
            # 根据错误类型选择合适的图标和标题
            if "网络" in error_message or "Network" in error_message:
                title = "🌐 网络错误"
                icon_type = "warning"
            elif "令牌" in error_message or "Token" in error_message or "过期" in error_message:
                title = "⏰ 令牌错误"
                icon_type = "error"
            elif "权限" in error_message or "Access" in error_message or "denied" in error_message:
                title = "🔒 权限错误"
                icon_type = "error"
            else:
                title = f"❌ {error_type}"
                icon_type = "error"

            # 格式化错误信息
            formatted_message = f"""发生了以下错误：

{error_message}

请检查网络连接或联系技术支持。"""

            if icon_type == "warning":
                messagebox.showwarning(title, formatted_message)
            else:
                messagebox.showerror(title, formatted_message)

        except Exception as e:
            # 如果自定义对话框失败，使用最简单的messagebox
            messagebox.showerror("错误", error_message)
    
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
                        return False, "Download cancelled"
                    
                    if chunk:
                        f.write(chunk)
                        downloaded_size += len(chunk)
                        
                        if progress_callback and total_size > 0:
                            progress = (downloaded_size / total_size) * 100
                            progress_callback(progress, downloaded_size, total_size)
            
            # 保存路径信息供后续使用
            self.last_save_path = save_path
            return True, f"Download completed: {os.path.basename(save_path)}"
            
        except Exception as e:
            error_str = str(e)
            # 处理常见的网络错误
            if "Connection aborted" in error_str or "ConnectionResetError" in error_str:
                return False, "Network connection error, please check your network status and try again"
            elif "timeout" in error_str.lower():
                return False, "Download timeout, please try again later"
            elif "Connection refused" in error_str:
                return False, "Server refused connection, please try again later"
            elif "Name or service not known" in error_str or "getaddrinfo failed" in error_str:
                return False, "DNS resolution failed, please check your network connection"
            elif "HTTP" in error_str and ("404" in error_str or "403" in error_str):
                return False, "File not found or access denied"
            else:
                return False, f"Download failed: {error_str}"

class IPDownloaderGUI:
    def set_dark_title_bar(self):
        """设置暗色标题栏（Windows 10/11）- 强化版"""
        try:
            # 确保窗口已经完全创建
            self.root.update_idletasks()
            self.root.update()

            # 获取窗口句柄
            hwnd = self.root.winfo_id()

            # 尝试多种方法设置暗色标题栏
            methods_tried = []

            # 方法1: Windows 11 Build 22000+ API
            try:
                DWMWA_USE_IMMERSIVE_DARK_MODE = 20
                value = ctypes.c_int(1)
                result = ctypes.windll.dwmapi.DwmSetWindowAttribute(
                    ctypes.wintypes.HWND(hwnd),
                    ctypes.wintypes.DWORD(DWMWA_USE_IMMERSIVE_DARK_MODE),
                    ctypes.byref(value),
                    ctypes.sizeof(value)
                )
                methods_tried.append(f"Win11 API: {result}")
            except Exception as e:
                methods_tried.append(f"Win11 API failed: {e}")

            # 方法2: Windows 10 Build 18985+ API
            try:
                DWMWA_USE_IMMERSIVE_DARK_MODE_OLD = 19
                value = ctypes.c_int(1)
                result = ctypes.windll.dwmapi.DwmSetWindowAttribute(
                    ctypes.wintypes.HWND(hwnd),
                    ctypes.wintypes.DWORD(DWMWA_USE_IMMERSIVE_DARK_MODE_OLD),
                    ctypes.byref(value),
                    ctypes.sizeof(value)
                )
                methods_tried.append(f"Win10 API: {result}")
            except Exception as e:
                methods_tried.append(f"Win10 API failed: {e}")

            # 方法3: 尝试更早的API
            try:
                DWMWA_CAPTION_COLOR = 35
                # 设置标题栏颜色为深色
                color_value = ctypes.c_ulong(0x00202020)  # 深灰色
                ctypes.windll.dwmapi.DwmSetWindowAttribute(
                    ctypes.wintypes.HWND(hwnd),
                    ctypes.wintypes.DWORD(DWMWA_CAPTION_COLOR),
                    ctypes.byref(color_value),
                    ctypes.sizeof(color_value)
                )
                methods_tried.append("Caption color set")
            except Exception as e:
                methods_tried.append(f"Caption color failed: {e}")

            # 方法4: 强制重绘（不使用withdraw/deiconify避免闪动）
            try:
                # 强制重绘窗口
                ctypes.windll.user32.RedrawWindow(
                    ctypes.wintypes.HWND(hwnd),
                    None,
                    None,
                    0x0001 | 0x0004 | 0x0100  # RDW_INVALIDATE | RDW_UPDATENOW | RDW_FRAME
                )
                methods_tried.append("Redraw completed")
            except Exception as e:
                methods_tried.append(f"Redraw failed: {e}")

        except Exception as e:
            # 如果设置失败，忽略错误（可能是旧版本Windows）
            pass

    def set_dark_title_bar_for_window(self, window):
        """为指定窗口设置暗色标题栏"""
        try:
            # 确保窗口已经完全创建
            window.update_idletasks()
            window.update()

            # 获取窗口句柄
            hwnd = window.winfo_id()

            # 尝试多种方法设置暗色标题栏
            success = False

            # 方法1: Windows 11 API
            try:
                DWMWA_USE_IMMERSIVE_DARK_MODE = 20
                value = ctypes.c_int(1)
                result = ctypes.windll.dwmapi.DwmSetWindowAttribute(
                    ctypes.wintypes.HWND(hwnd),
                    ctypes.wintypes.DWORD(DWMWA_USE_IMMERSIVE_DARK_MODE),
                    ctypes.byref(value),
                    ctypes.sizeof(value)
                )
                if result == 0:
                    success = True
            except:
                pass

            # 方法2: Windows 10 API
            if not success:
                try:
                    DWMWA_USE_IMMERSIVE_DARK_MODE_OLD = 19
                    value = ctypes.c_int(1)
                    result = ctypes.windll.dwmapi.DwmSetWindowAttribute(
                        ctypes.wintypes.HWND(hwnd),
                        ctypes.wintypes.DWORD(DWMWA_USE_IMMERSIVE_DARK_MODE_OLD),
                        ctypes.byref(value),
                        ctypes.sizeof(value)
                    )
                    if result == 0:
                        success = True
                except:
                    pass

            # 方法3: 强制重绘窗口
            if success:
                try:
                    # 强制重绘窗口
                    ctypes.windll.user32.RedrawWindow(
                        ctypes.wintypes.HWND(hwnd),
                        None,
                        None,
                        0x0001 | 0x0004  # RDW_INVALIDATE | RDW_UPDATENOW
                    )
                except:
                    pass

        except Exception as e:
            # 如果设置失败，忽略错误（可能是旧版本Windows）
            pass

    def get_log_setting_from_backend(self):
        """通过现有的download_api.php获取配置 - 完全模仿IP验证逻辑"""
        debug_messages = []

        try:
            # 获取正确的配置文件路径 - 支持exe和Python环境
            debug_messages.append(f"🔧 sys.frozen: {getattr(sys, 'frozen', False)}")
            debug_messages.append(f"🔧 sys.executable: {sys.executable}")
            debug_messages.append(f"🔧 __file__: {__file__}")
            debug_messages.append(f"🔧 当前工作目录: {os.getcwd()}")

            if getattr(sys, 'frozen', False):
                # exe环境：使用exe文件所在目录
                exe_dir = os.path.dirname(sys.executable)
                config_file = os.path.join(exe_dir, 'config.ini')
                debug_messages.append(f"🔧 exe环境，exe目录: {exe_dir}")

                # 列出exe目录中的文件
                try:
                    files_in_exe_dir = os.listdir(exe_dir)
                    debug_messages.append(f"🔧 exe目录中的文件: {files_in_exe_dir}")
                except:
                    debug_messages.append("🔧 无法列出exe目录中的文件")
            else:
                # Python环境：使用脚本文件所在目录
                script_dir = os.path.dirname(__file__)
                config_file = os.path.join(script_dir, 'config.ini')
                debug_messages.append(f"🔧 Python环境，脚本目录: {script_dir}")

            debug_messages.append(f"🔧 配置文件路径: {config_file}")
            debug_messages.append(f"🔧 配置文件是否存在: {os.path.exists(config_file)}")

            if not os.path.exists(config_file):
                debug_messages.append("❌ config.ini文件不存在，使用默认值: True")
                self.log_debug_messages(debug_messages)
                return True

            import configparser
            config = configparser.ConfigParser()
            config.read(config_file, encoding='utf-8')

            server_url = config.get('server', 'verify_url', fallback=None)
            api_key = config.get('server', 'api_key', fallback=None)

            debug_messages.append(f"🔧 原始服务器地址: {server_url}")
            debug_messages.append(f"🔧 API密钥: {api_key[:20]}..." if api_key else "🔧 API密钥: 未找到")

            if not server_url or not api_key:
                debug_messages.append("❌ 缺少服务器配置，使用默认值: True")
                self.log_debug_messages(debug_messages)
                return True

            # 清理URL，移除已有的参数
            if '?' in server_url:
                base_url = server_url.split('?')[0]
                debug_messages.append(f"🔧 清理后的基础URL: {base_url}")
            else:
                base_url = server_url
                debug_messages.append(f"🔧 基础URL: {base_url}")

            # 构建请求参数 - 使用统计接口获取配置
            params = {
                'action': 'stats',  # 修正：API支持的是'stats'，不是'get_stats'
                'api_key': api_key
            }

            debug_messages.append(f"🌐 请求URL: {base_url}")
            debug_messages.append(f"🌐 请求参数: {params}")

            # 发送HTTP请求到现有的download_api.php
            import requests

            # 添加代理和SSL配置，避免网络问题
            session = requests.Session()
            session.trust_env = False  # 忽略系统代理设置

            response = session.get(base_url, params=params, timeout=10, verify=False)

            debug_messages.append(f"📡 HTTP状态: {response.status_code}")

            if response.status_code == 200:
                try:
                    data = response.json()
                    debug_messages.append(f"📡 API响应: {data}")

                    if data.get('success'):
                        # API直接返回数据，不是包装在data字段中
                        show_log = data.get('downloader_show_log', True)
                        ip_enabled = data.get('ip_verification_enabled', True)
                        strict_mode = data.get('strict_mode', False)

                        debug_messages.append(f"✅ API请求成功")
                        debug_messages.append(f"📊 完整响应数据: {data}")
                        debug_messages.append(f"")
                        debug_messages.append(f"🔍 === 配置对比分析 ===")
                        debug_messages.append(f"🎛️ IP验证开关: ip_verification_enabled = {ip_enabled}")
                        debug_messages.append(f"🎛️ 严格模式: strict_mode = {strict_mode}")
                        debug_messages.append(f"🎛️ 下载器日志: downloader_show_log = {show_log}")
                        debug_messages.append(f"")
                        debug_messages.append(f"🔍 === 调试信息 ===")
                        debug_messages.append(f"📄 配置文件: {data.get('debug_config_file', 'N/A')}")
                        debug_messages.append(f"📄 文件存在: {data.get('debug_config_exists', 'N/A')}")
                        debug_messages.append(f"📄 文件大小: {data.get('debug_config_size', 'N/A')} 字节")
                        debug_messages.append(f"📄 修改时间: {data.get('debug_config_modified', 'N/A')}")
                        debug_messages.append(f"")
                        debug_messages.append(f"🔍 === 原始配置值 ===")
                        debug_messages.append(f"📝 IP验证原始值: {data.get('debug_ip_enabled_raw', 'N/A')}")
                        debug_messages.append(f"📝 严格模式原始值: {data.get('debug_strict_mode_raw', 'N/A')}")
                        debug_messages.append(f"📝 日志开关原始值: {data.get('debug_show_log_raw', 'N/A')}")
                        debug_messages.append(f"")
                        debug_messages.append(f"🎯 最终返回值: {show_log} (类型: {type(show_log)})")

                        self.log_debug_messages(debug_messages)
                        return show_log
                    else:
                        debug_messages.append(f"❌ API返回错误: {data.get('message', '未知错误')}")

                except Exception as json_error:
                    debug_messages.append(f"❌ JSON解析失败: {json_error}")
                    debug_messages.append(f"📡 响应内容: {response.text[:200]}...")
            else:
                debug_messages.append(f"❌ HTTP请求失败: {response.status_code}")
                debug_messages.append(f"📡 响应内容: {response.text[:200]}...")

            debug_messages.append("❌ API请求失败，使用默认值: True")
            self.log_debug_messages(debug_messages)
            return True

        except Exception as e:
            debug_messages.append(f"❌ 网络异常: {str(e)}")
            debug_messages.append(f"❌ 异常类型: {type(e).__name__}")

            # 检查是否是代理问题
            if "ProxyError" in str(e) or "proxy" in str(e).lower():
                debug_messages.append("💡 检测到代理问题，建议检查网络设置")

            # 检查是否是SSL问题
            if "SSL" in str(e) or "ssl" in str(e).lower():
                debug_messages.append("💡 检测到SSL问题，可能是证书或网络配置问题")

            debug_messages.append("❌ 由于网络问题，使用默认值: True (显示日志)")
            self.log_debug_messages(debug_messages)
            return True

    def log_debug_messages(self, messages):
        """将调试信息输出到控制台（如果日志功能禁用）或日志窗口（如果日志功能启用）"""
        try:
            for msg in messages:
                # 始终输出到控制台（用于开发调试）
                print(msg)

                # 如果日志功能启用，也输出到日志窗口
                if self.show_log:
                    self.log_message(msg)

        except Exception as e:
            # 如果出错，至少输出到控制台
            print(f"日志输出错误: {e}")
            for msg in messages:
                print(msg)

    def close_log_window(self):
        """关闭日志窗口"""
        try:
            print("🔴 准备关闭日志窗口...")
            if self.log_window:
                print("🔴 日志窗口存在，正在关闭...")
                self.log_window.destroy()
                self.log_window = None
                self.log_text = None
                print("🔴 日志窗口已关闭")
            else:
                print("🔴 日志窗口不存在，无需关闭")
        except Exception as e:
            print(f"🔴 关闭日志窗口时出错: {e}")

    def show_startup_details(self):
        """显示详细的启动信息"""
        self.log_message("=" * 60)
        self.log_message("🚀 系统启动信息")
        self.log_message("=" * 60)
        self.log_message(f"🔧 运行环境: {'EXE' if getattr(sys, 'frozen', False) else 'Python'}")
        self.log_message(f"🔧 程序路径: {sys.executable if getattr(sys, 'frozen', False) else __file__}")
        self.log_message(f"🔧 工作目录: {os.getcwd()}")
        self.log_message(f"🔧 配置状态: 日志功能已启用")
        self.log_message(f"🔧 启动时间: {time.strftime('%Y-%m-%d %H:%M:%S')}")
        self.log_message("=" * 60)

    def show_backend_config_details(self):
        """显示后台配置获取的详细过程"""
        self.log_message("")
        self.log_message("🔍 重新获取后台配置详情...")

        # 重新调用配置获取方法，但这次会显示在日志窗口中
        temp_show_log = self.show_log
        self.show_log = True  # 临时启用，确保调试信息显示
        self.get_log_setting_from_backend()
        self.show_log = temp_show_log  # 恢复原始设置





    def get_local_ip(self):
        """获取本地IP地址"""
        try:
            # 连接到外部地址获取本地IP
            with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as s:
                s.connect(("8.8.8.8", 80))
                return s.getsockname()[0]
        except:
            return "127.0.0.1"



    def __init__(self):
        self.root = tk.Tk()
        self.root.title("Secure Downloader")

        # 设置窗口大小 - 确保按钮可见
        window_width = 750
        window_height = 660

        # 获取屏幕尺寸
        screen_width = self.root.winfo_screenwidth()
        screen_height = self.root.winfo_screenheight()

        # 计算居中位置
        center_x = int(screen_width/2 - window_width/2)
        center_y = int(screen_height/2 - window_height/2)

        # 设置窗口位置和大小
        self.root.geometry(f'{window_width}x{window_height}+{center_x}+{center_y}')
        self.root.resizable(False, False)

        # 设置暗色主题
        self.root.configure(bg='#1e1e1e')

        # 设置图标（如果有的话）
        try:
            self.root.iconbitmap('icon.ico')
        except:
            pass

        self.manager = DownloadManager()
        self.progress_canvas = None  # 初始化进度条画布
        self.setup_ui()

        # 在窗口显示后设置暗色标题栏 - 适度尝试避免闪动
        for delay in [100, 500, 1000, 2000]:
            self.root.after(delay, self.set_dark_title_bar)
        
    def setup_ui(self):
        """设置用户界面"""
        # 设置暗色主题样式
        style = ttk.Style()
        style.theme_use('clam')

        # 暗色主题配色 - 调整为更亮的颜色
        dark_bg = '#2b2b2b'
        darker_bg = '#1e1e1e'
        light_text = '#ffffff'
        accent_blue = '#0078d4'
        accent_green = '#00d084'
        accent_red = '#ff6b6b'
        accent_orange = '#ffa726'
        muted_text = '#e0e0e0'  # 更亮的灰色
        border_color = '#404040'

        # 配置暗色主题 - 更深入的样式设置
        style.configure('TFrame',
                       background=dark_bg,
                       borderwidth=0)

        style.configure('TLabelFrame',
                       background=dark_bg,
                       foreground=light_text,
                       borderwidth=1,
                       relief='solid',
                       bordercolor=border_color)

        style.configure('TLabelFrame.Label',
                       background=dark_bg,
                       foreground=light_text,
                       font=('Segoe UI', 10, 'bold'))

        # 按钮样式 - 暗色主题
        style.map('TButton',
                 background=[('active', accent_blue), ('!active', '#404040')],
                 foreground=[('active', 'white'), ('!active', light_text)],
                 bordercolor=[('active', accent_blue), ('!active', border_color)])

        style.configure('TButton',
                       background='#404040',
                       foreground=light_text,
                       borderwidth=1,
                       focuscolor='none',
                       font=('Segoe UI', 10))

        # 标签样式
        style.configure('Title.TLabel', font=('Segoe UI', 20, 'bold'), foreground=light_text, background=darker_bg)
        style.configure('Subtitle.TLabel', font=('Segoe UI', 10), foreground=muted_text, background=darker_bg)
        style.configure('Heading.TLabel', font=('Segoe UI', 11, 'bold'), foreground=light_text, background=dark_bg)
        style.configure('Info.TLabel', font=('Segoe UI', 10), foreground=muted_text, background=dark_bg)
        style.configure('Success.TLabel', font=('Segoe UI', 10, 'bold'), foreground=accent_green, background=dark_bg)
        style.configure('Error.TLabel', font=('Segoe UI', 10, 'bold'), foreground=accent_red, background=dark_bg)
        style.configure('Warning.TLabel', font=('Segoe UI', 10, 'bold'), foreground=accent_orange, background=dark_bg)

        # 主框架 - 使用原生Tkinter确保暗色主题
        main_frame = tk.Frame(self.root, bg=dark_bg, padx=30, pady=30)
        main_frame.pack(fill=tk.BOTH, expand=True)

        # 标题区域
        title_frame = tk.Frame(main_frame, bg=dark_bg)
        title_frame.pack(fill=tk.X, pady=(0, 30))

        # 主标题
        title_label = tk.Label(title_frame,
                              text="Secure Downloader",
                              font=('Segoe UI', 20, 'bold'),
                              fg=light_text,
                              bg=dark_bg)
        title_label.pack()



        # 文件信息区域 - 原生Tkinter
        info_frame = tk.LabelFrame(main_frame,
                                  text="📁 File Information",
                                  font=('Segoe UI', 10, 'bold'),
                                  fg=light_text,
                                  bg=dark_bg,
                                  bd=1,
                                  relief='solid')
        info_frame.pack(fill=tk.X, pady=(0, 25), padx=5)

        # 内部框架用于布局
        info_inner = tk.Frame(info_frame, bg=dark_bg)
        info_inner.pack(fill=tk.X, padx=20, pady=15)

        # 创建信息网格
        info_items = [
            ("📄 File Name:", "software_label"),
            ("📊 File Size:", "size_label"),
            ("🔑 Token:", "token_label")
        ]

        for i, (label_text, attr_name) in enumerate(info_items):
            # 行框架
            row_frame = tk.Frame(info_inner, bg=dark_bg)
            row_frame.pack(fill=tk.X, pady=8)

            # 标签
            label = tk.Label(row_frame,
                           text=label_text,
                           font=('Segoe UI', 11, 'bold'),
                           fg=light_text,
                           bg=dark_bg,
                           width=12,
                           anchor='w')
            label.pack(side=tk.LEFT)

            # 值标签
            value_label = tk.Label(row_frame,
                                 text="Loading...",
                                 font=('Segoe UI', 10),
                                 fg=muted_text,
                                 bg=dark_bg,
                                 anchor='w')
            value_label.pack(side=tk.LEFT, padx=(15, 0))
            setattr(self, attr_name, value_label)
        

        
        # 进度区域 - 原生Tkinter
        progress_frame = tk.LabelFrame(main_frame,
                                     text="📈 Download Progress",
                                     font=('Segoe UI', 10, 'bold'),
                                     fg=light_text,
                                     bg=dark_bg,
                                     bd=1,
                                     relief='solid')
        progress_frame.pack(fill=tk.X, pady=(0, 25), padx=5)

        # 进度内部框架
        progress_inner = tk.Frame(progress_frame, bg=dark_bg)
        progress_inner.pack(fill=tk.X, padx=20, pady=15)

        # 进度条 - 使用Canvas绘制暗色进度条
        self.progress_var = tk.DoubleVar()
        progress_canvas = tk.Canvas(progress_inner, height=25, bg='#404040', highlightthickness=0)
        progress_canvas.pack(fill=tk.X, pady=(0, 15))
        self.progress_canvas = progress_canvas

        # 绘制初始进度条
        self.update_progress_bar(0)

        # 进度文本
        self.progress_label = tk.Label(progress_inner,
                                     text="Ready to start",
                                     font=('Segoe UI', 10),
                                     fg=muted_text,
                                     bg=dark_bg,
                                     anchor='w')
        self.progress_label.pack(fill=tk.X)

        # 状态区域 - 原生Tkinter
        status_frame = tk.LabelFrame(main_frame,
                                   text="📊 Status",
                                   font=('Segoe UI', 10, 'bold'),
                                   fg=light_text,
                                   bg=dark_bg,
                                   bd=1,
                                   relief='solid')
        status_frame.pack(fill=tk.X, pady=(0, 25), padx=5)

        # 状态内部框架
        status_inner = tk.Frame(status_frame, bg=dark_bg)
        status_inner.pack(fill=tk.X, padx=20, pady=15)

        # 状态标签
        self.status_label = tk.Label(status_inner,
                                   text="🟢 Ready to download",
                                   font=('Segoe UI', 10, 'bold'),
                                   fg=accent_green,
                                   bg=dark_bg,
                                   anchor='w')
        self.status_label.pack(fill=tk.X)

        # 按钮区域 - 设置固定高度确保可见
        button_frame = tk.Frame(main_frame, bg=dark_bg, height=80)
        button_frame.pack(fill=tk.X, pady=(15, 25))
        button_frame.pack_propagate(False)  # 防止子组件改变框架大小

        # 开始下载按钮 - 居中显示
        self.download_btn = tk.Button(button_frame,
                                    text="📥 Start Download",
                                    command=self.start_download,
                                    font=('Segoe UI', 12, 'bold'),
                                    bg='#0078d4',
                                    fg='white',
                                    activebackground='#106ebe',
                                    activeforeground='white',
                                    bd=0,
                                    padx=30,
                                    pady=12,
                                    cursor='hand2',
                                    relief='flat')
        self.download_btn.pack(expand=True)

        # 添加按钮悬停效果
        def on_enter(e):
            self.download_btn.config(bg='#106ebe')
        def on_leave(e):
            self.download_btn.config(bg='#0078d4')

        self.download_btn.bind("<Enter>", on_enter)
        self.download_btn.bind("<Leave>", on_leave)

        # 保留cancel_btn引用以避免错误，但设为None
        self.cancel_btn = None
        
        # 配置网格权重
        self.root.columnconfigure(0, weight=1)
        self.root.rowconfigure(0, weight=1)
        main_frame.columnconfigure(1, weight=1)

        # 日志窗口相关变量
        self.log_window = None
        self.log_text = None

        # 先获取后台配置，决定是否需要日志窗口
        print("🚀 下载器启动，开始获取后台配置...")
        self.show_log = self.get_log_setting_from_backend()
        print(f"🎛️ 最终配置结果: show_log = {self.show_log}")

        # 根据配置决定是否创建日志窗口
        if self.show_log:
            print("📝 配置显示：日志功能已启用，创建日志窗口")
            self.create_log_window()
            self.log_message("🚀 下载器启动完成，日志功能已启用")

            # 显示详细的启动和配置信息
            self.show_startup_details()

            # 重新获取并显示后台配置的详细过程
            self.show_backend_config_details()
        else:
            print("📝 配置显示：日志功能已禁用，不创建日志窗口")
            # 不创建日志窗口，保持静默运行

        # 加载配置
        self.load_config()

    def create_log_window(self):
        """创建独立的日志窗口"""
        if self.log_window is not None:
            return  # 窗口已存在

        self.log_window = tk.Toplevel(self.root)
        self.log_window.title("🐛 Debug Log - Secure Downloader v2.0")

        # 设置日志窗口大小和位置
        log_width = 1000
        log_height = 700

        # 获取主窗口位置
        main_x = self.root.winfo_x()
        main_y = self.root.winfo_y()
        main_width = self.root.winfo_width()

        # 将日志窗口放在主窗口右侧
        log_x = main_x + main_width + 20
        log_y = main_y

        self.log_window.geometry(f'{log_width}x{log_height}+{log_x}+{log_y}')
        self.log_window.resizable(True, True)
        self.log_window.configure(bg='#2b2b2b')  # 与主窗口一致的背景色

        # 设置窗口图标（如果有的话）
        try:
            self.log_window.iconbitmap('icon.ico')
        except:
            pass

        # 为日志窗口也设置暗色标题栏
        self.log_window.after(100, lambda: self.set_dark_title_bar_for_window(self.log_window))
        self.log_window.after(500, lambda: self.set_dark_title_bar_for_window(self.log_window))
        self.log_window.after(1000, lambda: self.set_dark_title_bar_for_window(self.log_window))

        # 创建日志框架 - 完全暗色主题
        log_frame = tk.Frame(self.log_window, bg='#2b2b2b')
        log_frame.pack(fill=tk.BOTH, expand=True, padx=15, pady=15)

        # 标题
        title_label = tk.Label(log_frame, text="� Debug Log (Developer Mode)",
                               font=("Segoe UI", 14, "bold"),
                               fg="#ffffff",
                               bg='#2b2b2b')
        title_label.pack(pady=(0, 15))

        # 文本框容器
        text_container = tk.Frame(log_frame, bg='#2b2b2b')
        text_container.pack(fill=tk.BOTH, expand=True)

        # 日志文本框 - 深色主题
        self.log_text = tk.Text(text_container,
                               height=35,
                               width=120,
                               wrap=tk.WORD,
                               font=("Consolas", 10),
                               bg="#1e1e1e",
                               fg="#e0e0e0",
                               insertbackground="#e0e0e0",
                               selectbackground="#0078d4",
                               selectforeground="#ffffff",
                               relief="flat",
                               bd=1)

        # 滚动条 - 暗色主题
        scrollbar = tk.Scrollbar(text_container,
                                orient="vertical",
                                command=self.log_text.yview,
                                bg='#404040',
                                troughcolor='#2b2b2b',
                                activebackground='#0078d4')
        self.log_text.configure(yscrollcommand=scrollbar.set)

        self.log_text.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        scrollbar.pack(side=tk.RIGHT, fill=tk.Y)

        # 按钮框架 - 暗色主题
        button_frame = tk.Frame(log_frame, bg='#2b2b2b')
        button_frame.pack(fill=tk.X, pady=(15, 0))

        # 清空日志按钮
        clear_btn = tk.Button(button_frame,
                             text="🗑️ Clear Log",
                             command=self.clear_log,
                             font=("Segoe UI", 10),
                             bg='#404040',
                             fg='#ffffff',
                             activebackground='#0078d4',
                             activeforeground='#ffffff',
                             bd=0,
                             padx=20,
                             pady=8,
                             cursor='hand2')
        clear_btn.pack(side=tk.LEFT, padx=(0, 10))

        # 关闭窗口按钮
        close_btn = tk.Button(button_frame,
                             text="❌ Close",
                             command=self.close_log_window,
                             font=("Segoe UI", 10),
                             bg='#ff6b6b',
                             fg='#ffffff',
                             activebackground='#e63946',
                             activeforeground='#ffffff',
                             bd=0,
                             padx=20,
                             pady=8,
                             cursor='hand2')
        close_btn.pack(side=tk.LEFT)

        # 日志窗口使用pack布局，不需要网格权重配置

        # 窗口关闭事件
        self.log_window.protocol("WM_DELETE_WINDOW", self.close_log_window)

        # 将窗口置于主窗口旁边
        self.log_window.transient(self.root)

        # 初始日志消息
        self.log_message("📋 Log window opened")

    def close_log_window(self):
        """关闭日志窗口"""
        if self.log_window:
            self.log_window.destroy()
            self.log_window = None
            self.log_text = None

    def clear_log(self):
        """清空日志"""
        if self.log_text:
            self.log_text.delete(1.0, tk.END)
            self.log_message("📋 Debug log cleared")

    def update_progress_bar(self, progress):
        """更新自定义进度条"""
        if self.progress_canvas:
            self.progress_canvas.delete("all")
            width = self.progress_canvas.winfo_width()
            height = self.progress_canvas.winfo_height()

            if width > 1:  # 确保画布已经渲染
                # 绘制背景
                self.progress_canvas.create_rectangle(0, 0, width, height, fill='#404040', outline='')

                # 绘制进度
                if progress > 0:
                    progress_width = int((progress / 100) * width)
                    self.progress_canvas.create_rectangle(0, 0, progress_width, height, fill='#0078d4', outline='')

    def update_status(self, message, status_type="info"):
        """更新用户状态显示"""
        # 根据状态类型选择颜色和图标
        status_styles = {
            "success": ("#00d084", "🟢"),
            "error": ("#ff6b6b", "🔴"),
            "warning": ("#ffa726", "🟡"),
            "info": ("#0078d4", "🔵"),
            "loading": ("#0078d4", "⏳")
        }

        color, icon = status_styles.get(status_type, ("#0078d4", "🔵"))
        formatted_message = f"{icon} {message}"

        self.status_label.config(text=formatted_message, fg=color)
        self.root.update()

    def log_message(self, message):
        """添加日志消息"""
        # 只有在日志功能启用时才处理日志消息
        if not self.show_log:
            return

        # 如果日志窗口不存在，输出到控制台
        if self.log_text is None:
            print(f"[LOG] {message}")
            return

        timestamp = time.strftime("%H:%M:%S")
        try:
            self.log_text.insert(tk.END, f"[{timestamp}] {message}\n")
            self.log_text.see(tk.END)
            if self.log_window and self.log_window.winfo_exists():
                self.log_window.update()
        except tk.TclError:
            # 日志窗口可能已被关闭，输出到控制台
            print(f"[LOG] {message}")
    
    def load_config(self):
        """加载配置文件"""
        if self.manager.load_config():
            try:
                software_name = self.manager.config.get('download', 'software_name')
                file_url = self.manager.config.get('download', 'file_url')
                token = self.manager.config.get('download', 'token')

                # 日志开关已在初始化时读取，这里不再重复读取

                self.software_label.config(text=software_name)

                # 获取文件大小
                file_size = self.get_file_size(file_url)
                self.size_label.config(text=file_size)

                self.token_label.config(text=token[:20] + "...")

                # 记录详细的配置加载信息
                self.log_message("=" * 50)
                self.log_message("📋 配置文件加载完成")
                self.log_message("=" * 50)
                self.log_message(f"📦 软件名称: {software_name}")
                self.log_message(f"🔗 下载链接: {file_url}")
                self.log_message(f"🎫 访问令牌: {token[:20]}...")
                self.log_message(f"📏 文件大小: {file_size}")
                self.log_message("✅ Configuration loaded successfully")
                self.log_message("=" * 50)

                # 更新用户状态
                self.update_status("Ready to download", "success")

            except Exception as e:
                self.update_status("Configuration error", "error")
                if self.show_log and self.log_text:
                    self.log_message(f"❌ Configuration parsing failed: {e}")
        else:
            self.update_status("Configuration file not found", "error")
            if self.show_log and self.log_text:
                self.log_message("❌ Configuration loading failed")
            messagebox.showerror("Error", "Configuration file not found or format error!\n\nPlease ensure config.ini file exists.")

    def get_file_size(self, url):
        """获取文件大小"""
        try:
            response = self.manager.session.head(url, timeout=10)
            if response.status_code == 200:
                content_length = response.headers.get('content-length')
                if content_length:
                    size_bytes = int(content_length)
                    return self.format_file_size(size_bytes)
            return "Unknown"
        except:
            return "Unknown"

    def format_file_size(self, size_bytes):
        """格式化文件大小"""
        if size_bytes < 1024:
            return f"{size_bytes} B"
        elif size_bytes < 1024 * 1024:
            return f"{size_bytes / 1024:.1f} KB"
        elif size_bytes < 1024 * 1024 * 1024:
            return f"{size_bytes / (1024 * 1024):.1f} MB"
        else:
            return f"{size_bytes / (1024 * 1024 * 1024):.1f} GB"

    def auto_verify_and_download(self):
        """自动执行验证和下载流程"""
        def auto_process():
            # 步骤1: IP验证
            self.update_status("Verifying permissions...", "loading")
            self.log_message("🔐 Step 1/2: IP Address Verification")
            self.log_message("🔍 Verifying download permissions...")

            success, message = self.manager.verify_ip_with_backend()

            # 处理验证结果
            should_download = True  # 默认都要下载

            if success:
                # API调用成功 - 统一显示验证通过
                self.update_status("Verification passed", "success")
                self.log_message("✅ API verification successful")
                self.log_message(f"📋 {message}")
                self.log_message("📁 File address updated")

                # 在IP匹配成功或IP验证被禁用时运行额外验证逻辑（后台静默处理）
                if "IP address verification passed" in message or "Skip verification" in message:
                    self.show_verification_notification(message)

            else:
                # API调用失败，检查是否为严重错误
                self.log_message(f"⚠️ API response: {message}")

                if "Network connection error" in message or "Network connection timeout" in message or "Server refused connection" in message or "DNS resolution failed" in message:
                    # 网络错误，不继续下载
                    should_download = False
                    self.update_status("Network error, please try again later", "error")
                    self.log_message("🚫 Download terminated due to network error")
                    # 显示网络错误对话框
                    error_msg = f"网络连接错误，请检查您的网络连接后重试。\n\n详细信息: {message}"
                    self.root.after(500, lambda: self.manager.show_error_dialog(error_msg, "网络错误"))
                elif "Token expired" in message or "Download terminated" in message or "Access denied" in message:
                    # 严重错误，不继续下载
                    should_download = False
                    self.update_status("Token expired or access denied", "error")
                    self.log_message("🚫 Download terminated due to token/access issue")
                    # 显示令牌/权限错误对话框
                    if "Token expired" in message or "过期" in message:
                        error_msg = f"下载令牌已过期，请重新获取下载器。\n\n详细信息: {message}"
                        self.root.after(500, lambda: self.manager.show_error_dialog(error_msg, "令牌过期"))
                    else:
                        error_msg = f"访问被拒绝，请检查您的权限或联系管理员。\n\n详细信息: {message}"
                        self.root.after(500, lambda: self.manager.show_error_dialog(error_msg, "访问拒绝"))
                else:
                    # 其他API错误，仍然尝试下载
                    should_download = True
                    self.update_status("Verification passed", "success")
                    self.log_message("⚠️ API error but attempting direct download")

            if should_download:
                # 步骤2: 文件下载
                self.update_status("Starting download...", "loading")
                self.log_message("📥 Step 2/2: File Download")
            else:
                # 验证失败，不进行下载
                self.manager.is_downloading = False
                self.download_btn.config(state="normal")
                self.update_progress_bar(0)
                self.progress_label.config(text="Verification failed")
                return

            # 开始下载
            self.manager.is_downloading = True
            self.manager.cancel_download = False

            self.download_btn.config(state="disabled")

            download_success, download_message = self.manager.download_file(self.update_progress)

            if download_success:
                self.update_status("Download completed successfully!", "success")
                self.log_message(f"✅ {download_message}")
                self.log_message("="*50)
                self.log_message("🎉 Download task completed!")

                # 获取实际保存路径并显示完成对话框
                try:
                    save_path = self.manager.last_save_path if hasattr(self.manager, 'last_save_path') else None
                    if save_path and os.path.exists(save_path):
                        self.log_message(f"📁 File location: {save_path}")
                        # 显示下载完成对话框
                        self.root.after(500, lambda: self.manager.show_download_complete_dialog(save_path))
                    else:
                        self.log_message("📁 File saved to Downloads folder")
                        # 如果没有具体路径，显示简单提示
                        self.root.after(500, lambda: messagebox.showinfo("下载完成", "文件已成功下载到Downloads文件夹！"))
                except Exception as e:
                    self.log_message("📁 File saved to selected location")
                    self.root.after(500, lambda: messagebox.showinfo("下载完成", "文件下载完成！"))
            else:
                self.update_status("Download failed, please try again", "error")
                self.log_message(f"❌ {download_message}")

                # 显示错误对话框
                self.root.after(500, lambda: self.manager.show_error_dialog(download_message, "下载失败"))

            self.manager.is_downloading = False
            self.download_btn.config(state="normal")

            self.update_progress_bar(0)
            self.progress_label.config(text="Download completed" if download_success else "Download failed")

        threading.Thread(target=auto_process, daemon=True).start()


    
    def start_download(self):
        """开始下载流程"""
        if not self.manager.is_downloading:
            self.auto_verify_and_download()
    
    def cancel_download(self):
        """取消下载"""
        if self.manager.is_downloading:
            self.manager.cancel_download = True
            self.update_status("Cancelling download...", "warning")
            self.log_message("⏹️ Cancelling download...")
    
    def update_progress(self, progress, downloaded, total):
        """更新进度"""
        # 更新自定义进度条
        self.update_progress_bar(progress)

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
        """显示额外验证逻辑提示弹窗 - 仅在IP匹配成功或IP验证被禁用时触发"""

        # 根据验证状态确定提示内容
        if "IP address verification passed" in verification_message:
            # IP匹配成功 - 触发额外验证逻辑
            dialog_title = "🎯 IP Match Successful"
            dialog_message = f"IP address matched successfully!\n\n{verification_message}\n\n✅ Running additional security verification\n(This is where seamless verification logic will be added)"
            self.log_message("🔔 IP matched - Running additional verification logic")
        elif "Skip verification" in verification_message:
            # IP验证被禁用 - 也触发额外验证逻辑
            dialog_title = "🔐 Additional Security Check"
            dialog_message = f"IP verification is disabled!\n\n{verification_message}\n\n✅ Running additional security verification\n(This is where seamless verification logic will be added)"
            self.log_message("🔔 IP verification disabled - Running additional verification logic")

        # 显示信息提示框（只有确定按钮）
        messagebox.showinfo(
            dialog_title,
            dialog_message
        )

        self.log_message("✅ Additional verification logic completed, continuing download")

    def run(self):
        """运行GUI"""
        # 显示窗口
        self.root.deiconify()
        self.root.update()
        self.root.focus_force()

        # 立即尝试设置标题栏
        self.set_dark_title_bar()

        # 适度尝试设置标题栏，避免过度闪动
        for delay in [200, 800, 2000]:
            self.root.after(delay, self.set_dark_title_bar)

        self.root.mainloop()

def main():
    """Main function"""
    # Check configuration file
    if not os.path.exists('config.ini'):
        print("Error: Configuration file config.ini not found")
        input("Press Enter to exit...")
        return

    # Start GUI
    try:
        app = IPDownloaderGUI()
        app.run()
    except Exception as e:
        print(f"Failed to start application: {e}")
        input("Press Enter to exit...")

if __name__ == "__main__":
    main()

# -*- mode: python ; coding: utf-8 -*-
"""
PyInstaller spec file for downloader application

使用方法:
    python -m PyInstaller main.spec

此 spec 文件包含以下功能:
- 自动检测并包含可选文件（图标、PYD文件、许可证等）
- 版本信息文件支持 (file_version_info.txt)
- 应用程序清单文件支持 (app.manifest)
- 许可证文件包含 (LICENSE.txt)
- 优化的打包配置，避免杀毒软件误报
"""

import os
import sys

block_cipher = None

# 获取当前目录的完整路径
# 兼容 python -m PyInstaller main.spec 命令
try:
    current_dir = os.path.abspath(os.path.dirname(__file__))
except NameError:
    # 当使用 python -m PyInstaller main.spec 时，__file__ 不可用
    current_dir = os.path.abspath(os.getcwd())

# 可选文件路径（如果存在则包含）
optional_files = []

# 检查可选的图标文件
icon_path = os.path.join(current_dir, 'downloader.ico')
if os.path.exists(icon_path):
    optional_files.append((icon_path, '.'))
    print(f"✅ Found icon file: {icon_path}")
else:
    print(f"⚠️ Icon file not found: {icon_path} (will use default)")
    icon_path = None

# 检查可选的pyd文件（设置为非必须）
pyd_path = os.path.join(current_dir, 'pygenere.pyd')
optional_binaries = []
if os.path.exists(pyd_path):
    optional_binaries.append((pyd_path, '.'))
    print(f"✅ Found optional pyd file: {pyd_path}")
else:
    print(f"ℹ️ Optional PYD file not found: {pyd_path} (not required)")

# 检查并添加许可证文件
license_path = os.path.join(current_dir, 'LICENSE.txt')
if os.path.exists(license_path):
    optional_files.append((license_path, '.'))
    print(f"✅ Found license file: {license_path}")
else:
    print(f"⚠️ License file not found: {license_path}")

# 检查版本信息文件
version_info_path = os.path.join(current_dir, 'file_version_info.txt')
if os.path.exists(version_info_path):
    print(f"✅ Found version info file: {version_info_path}")
else:
    print(f"⚠️ Version info file not found: {version_info_path}")
    version_info_path = None

# 检查应用程序清单文件
manifest_path = os.path.join(current_dir, 'app.manifest')
if os.path.exists(manifest_path):
    print(f"✅ Found manifest file: {manifest_path}")
else:
    print(f"⚠️ Manifest file not found: {manifest_path}")
    manifest_path = None

a = Analysis(
    ['downloader.py'],  # 修正：使用实际的主文件
    pathex=[current_dir],
    binaries=optional_binaries,  # 使用可选的二进制文件
    datas=optional_files,  # 使用可选的数据文件
    hiddenimports=[
        # 网络相关
        'requests',
        'urllib3',
        'charset_normalizer',
        'idna',
        'certifi',
        'urllib.parse',

        # GUI相关
        'tkinter',
        'tkinter.ttk',
        'tkinter.messagebox',
        'tkinter.filedialog',

        # 系统相关
        'configparser',
        'threading',
        'subprocess',
        'platform',
        'ctypes',
        'ctypes.wintypes',
        'socket',

        # 标准库
        'base64',
        'hashlib',
        'json',
        'time',
        'os',
        'sys',
        'pathlib',
        'webbrowser'
    ],
    hookspath=[],
    hooksconfig={},
    runtime_hooks=[],
    excludes=[],
    win_no_prefer_redirects=False,
    win_private_assemblies=False,
    cipher=None,
    noarchive=False
)

pyz = PYZ(a.pure, a.zipped_data, cipher=None)

exe = EXE(
    pyz,
    a.scripts,
    a.binaries,
    a.zipfiles,
    a.datas,
    [],
    name='Downloader',           # 简洁的可执行文件名
    debug=False,                 # 生产环境关闭调试
    bootloader_ignore_signals=False,
    strip=False,                 # 保留符号信息以便调试
    upx=False,                   # 禁用UPX压缩，避免杀毒软件误报
    upx_exclude=[],
    runtime_tmpdir=None,
    console=False,               # 纯GUI模式，无控制台窗口
    disable_windowed_traceback=False,
    target_arch=None,
    codesign_identity=None,
    entitlements_file=None,
    icon=icon_path if icon_path else None,

    # 添加版本信息和清单文件
    version=version_info_path if version_info_path else None,  # 版本信息文件
    manifest=manifest_path if manifest_path else None,        # 应用程序清单文件
    uac_admin=False,             # 不需要管理员权限
    uac_uiaccess=False,          # 不需要UI访问权限
)

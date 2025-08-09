/**
 * 下载拦截器 - 修复版本 v8.0
 * 真正的自动下载，无二次点击
 * 修复了浏览器兼容性问题，直接触发下载
 * 新增版本号支持和缓存清理
 */

console.log('🚀 下载拦截器启动 v8.0 - 强制自动下载版本');

class DownloadInterceptor {
    constructor() {
        this.config = null;
        this.handlerUrl = './handler.php';
        this.isProcessing = false; // 防止重复处理
        this.init();
    }

    async init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
        await this.loadConfig();
    }

    async loadConfig() {
        try {
            const response = await fetch(this.handlerUrl + '?action=config');
            const result = await response.json();
            if (result.success) {
                this.config = result.data;
                console.log('✅ 配置加载成功:', this.config.site_name);
            }
        } catch (error) {
            console.error('❌ 配置加载失败:', error);
        }
    }

    setup() {
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a');
            if (link && this.shouldIntercept(link.href)) {
                e.preventDefault();
                this.handleDownloadClick(link);
            }
        });
        console.log('✅ 下载拦截器已启动');
    }

    shouldIntercept(url) {
        if (!url) {
            return false;
        }

        // 如果正在处理，不再拦截
        if (this.isProcessing) {
            console.log('🔄 正在处理中，跳过拦截:', url);
            return false;
        }

        // 绝对不拦截的链接
        const excludePatterns = [
            '/downloads/',
            'downloader',
            'Downloader',
            'SecureDownloader',
            '.php',
            'javascript:',
            'mailto:',
            '#'
        ];

        for (const pattern of excludePatterns) {
            if (url.includes(pattern)) {
                console.log('🚫 排除链接:', url, '(匹配:', pattern, ')');
                return false;
            }
        }

        // 只拦截特定的文件类型和路径
        const interceptPatterns = [
            /https:\/\/dw\.ytmour\.art\/windows\/.*\.(exe|msi|zip|rar|7z)$/i
        ];

        for (const pattern of interceptPatterns) {
            if (pattern.test(url)) {
                console.log('✅ 拦截下载链接:', url);
                return true;
            }
        }

        console.log('🔍 不匹配拦截规则:', url);
        return false;
    }

    handleDownloadClick(link) {
        if (this.isProcessing) {
            console.log('🔄 已在处理中，忽略重复点击');
            return;
        }

        this.isProcessing = true;
        
        const fileUrl = link.href;
        const softwareName = this.extractSoftwareName(link);
        
        console.log('🔍 开始处理下载请求:', softwareName, fileUrl);
        
        // 3秒后重置处理状态
        setTimeout(() => {
            this.isProcessing = false;
            console.log('🔄 处理状态已重置');
        }, 3000);
        
        this.generateDownloader(fileUrl, softwareName);
    }

    extractSoftwareName(link) {
        try {
            const urlParts = link.href.split('/');
            const fileName = urlParts[urlParts.length - 1];
            
            let name = fileName
                .replace(/\?.*$/, '')
                .replace(/#.*$/, '')
                .replace(/[^\x20-\x7E]/g, '_')
                .trim();
            
            if (name.length > 200) {
                const lastDot = name.lastIndexOf('.');
                if (lastDot > 0) {
                    const ext = name.substring(lastDot);
                    const baseName = name.substring(0, lastDot);
                    name = baseName.substring(0, 200 - ext.length) + ext;
                } else {
                    name = name.substring(0, 200);
                }
            }
            
            if (!name || name.length < 3) {
                name = 'Unknown_Software.exe';
            }
            
            console.log('🔍 提取的软件名称:', name);
            return name;
            
        } catch (error) {
            console.error('❌ 名称提取失败:', error);
            return 'Unknown_Software.exe';
        }
    }

    async generateDownloader(fileUrl, softwareName) {
        try {
            console.log('📤 开始生成下载器...');

            const userIP = await this.getUserIP();
            
            const requestData = {
                file_url: fileUrl,
                software_name: softwareName,
                user_ip: userIP
            };
            
            console.log('📤 发送请求:', requestData);

            const response = await fetch(this.handlerUrl + '?action=generate', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestData)
            });

            const responseText = await response.text();
            console.log('📥 服务器响应:', responseText);
            
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error('服务器响应格式错误: ' + responseText.substring(0, 100));
            }

            if (result.success) {
                console.log('✅ 下载器生成成功！');
                console.log('令牌:', result.token || '未知');
                console.log('过期时间:', result.expires_at || '未知');
                
                // 获取下载链接
                const downloadUrl = result.download_url;
                if (downloadUrl) {
                    const fullUrl = downloadUrl.startsWith('http') 
                        ? downloadUrl 
                        : (this.config.storage_server || 'https://dw.ytmour.art') + '/' + downloadUrl;
                    
                    console.log('📥 下载链接:', fullUrl);

                    // 直接自动下载，不显示对话框
                    this.autoDownload(fullUrl, softwareName);
                } else {
                    console.error('❌ 响应中没有下载链接');
                    throw new Error('服务器响应中缺少下载链接');
                }
            } else {
                throw new Error(result.message || '生成失败');
            }

        } catch (error) {
            console.error('❌ 生成失败:', error);
        } finally {
            // 确保处理状态被重置
            setTimeout(() => {
                this.isProcessing = false;
            }, 1000);
        }
    }

    autoDownload(downloadUrl, softwareName) {
        console.log('🚀 强制自动下载 - 单一方法版本');
        console.log('📁 软件名称:', softwareName);
        console.log('🔗 下载链接:', downloadUrl);

        // 添加缓存破坏参数
        const cacheBuster = Date.now() + '_' + Math.random().toString(36).substring(2, 11);
        const finalUrl = downloadUrl + (downloadUrl.includes('?') ? '&' : '?') + 'cb=' + cacheBuster;

        console.log('🔗 最终下载链接:', finalUrl);

        // 使用最可靠的方法：创建隐藏的 <a> 标签并自动点击
        const link = document.createElement('a');
        link.href = finalUrl;
        link.download = this.generateDownloadFilename(softwareName);
        link.style.display = 'none';
        link.target = '_self'; // 改为_self避免打开新页面

        document.body.appendChild(link);

        // 延迟点击以确保DOM已添加
        setTimeout(() => {
            try {
                link.click();
                console.log('✅ 自动下载已触发');
            } catch (error) {
                console.error('❌ 下载触发失败:', error);
                // 备用方法：直接设置location
                window.location.href = finalUrl;
            }

            // 清理DOM
            setTimeout(() => {
                if (link.parentNode) {
                    document.body.removeChild(link);
                }
            }, 1000);
        }, 200);

        // 显示成功提示
        this.showSuccessNotice(softwareName);
    }

    generateFilename(softwareName) {
        const cleanName = softwareName
            .replace(/\.(exe|msi|zip|rar|7z|dmg|pkg|deb|rpm|tar\.gz|iso|img)$/i, '')
            .replace(/[^\w\s-]/g, '')
            .replace(/\s+/g, '_')
            .substring(0, 25);

        const timestamp = Math.floor(Date.now() / 1000);
        return `${cleanName}_${timestamp}.zip`;
    }

    generateDownloadFilename(softwareName) {
        // 生成带版本号的下载文件名
        const cleanName = softwareName
            .replace(/\.(exe|msi|zip|rar|7z|dmg|pkg|deb|rpm|tar\.gz|iso|img)$/i, '')
            .replace(/[^\w\s-]/g, '')
            .replace(/\s+/g, '_')
            .substring(0, 20); // 稍微短一点为版本号留空间

        // 生成版本号 (格式: v年月日_时分)
        const now = new Date();
        const version = `v${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(now.getDate()).padStart(2, '0')}_${String(now.getHours()).padStart(2, '0')}${String(now.getMinutes()).padStart(2, '0')}`;

        const timestamp = Math.floor(Date.now() / 1000);
        return `${cleanName}_${version}_${timestamp}.zip`;
    }

    showSuccessNotice(softwareName) {
        // 移除已存在的通知
        const existing = document.getElementById('success-notice');
        if (existing) existing.remove();

        // 创建成功通知
        const notice = document.createElement('div');
        notice.id = 'success-notice';
        notice.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 10000;
            font-family: Arial, sans-serif;
            max-width: 350px;
        `;

        notice.innerHTML = `
            <div style="font-weight: bold; margin-bottom: 5px;">✅ 下载已开始！</div>
            <div style="font-size: 14px;">软件: ${softwareName}</div>
            <div style="font-size: 12px; margin-top: 5px; opacity: 0.9;">请检查浏览器下载文件夹</div>
        `;

        document.body.appendChild(notice);

        // 5秒后自动消失
        setTimeout(() => {
            if (notice.parentElement) {
                notice.style.transition = 'opacity 0.5s';
                notice.style.opacity = '0';
                setTimeout(() => {
                    if (notice.parentElement) {
                        notice.remove();
                    }
                }, 500);
            }
        }, 5000);
    }

    async getUserIP() {
        try {
            const response = await fetch('https://api.ipify.org?format=json');
            const data = await response.json();
            return data.ip;
        } catch (error) {
            return '0.0.0.0';
        }
    }
}

// 初始化
window.downloadInterceptor = new DownloadInterceptor();
console.log('✅ 下载拦截器 v8.0 已加载 - 强制自动下载版本');

<?php
session_start();

// 检查登录状态
if (!isset($_SESSION['master_admin']) || $_SESSION['master_admin'] !== true) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API文档 - 多站点下载系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .nav { margin-bottom: 20px; }
        .nav a { color: #007bff; text-decoration: none; margin-right: 15px; }
        .nav a:hover { text-decoration: underline; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .endpoint { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0; border-left: 4px solid #007bff; }
        .method { display: inline-block; padding: 4px 8px; border-radius: 4px; color: white; font-weight: bold; margin-right: 10px; }
        .method.get { background: #28a745; }
        .method.post { background: #007bff; }
        .method.put { background: #ffc107; color: black; }
        .method.delete { background: #dc3545; }
        .code { background: #1e1e1e; color: #f8f8f2; padding: 15px; border-radius: 4px; font-family: 'Courier New', monospace; overflow-x: auto; margin: 10px 0; }
        .params { background: #e3f2fd; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .response { background: #e8f5e8; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .toc { background: #f8f9fa; padding: 20px; border-radius: 4px; margin-bottom: 20px; }
        .toc ul { list-style: none; }
        .toc li { margin: 5px 0; }
        .toc a { color: #007bff; text-decoration: none; }
        .toc a:hover { text-decoration: underline; }
        h1, h2, h3 { color: #333; margin: 20px 0 10px 0; }
        h1 { border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h2 { border-bottom: 1px solid #ddd; padding-bottom: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 API文档</h1>
            <div class="nav">
                <a href="index.php">← 返回主面板</a>
                <a href="database.php">数据库管理</a>
                <a href="logs.php">系统日志</a>
                <a href="backup.php">备份管理</a>
            </div>
        </div>

        <!-- 目录 -->
        <div class="toc">
            <h3>📋 目录</h3>
            <ul>
                <li><a href="#overview">1. API概述</a></li>
                <li><a href="#authentication">2. 身份验证</a></li>
                <li><a href="#endpoints">3. API端点</a>
                    <ul style="margin-left: 20px;">
                        <li><a href="#generate-token">3.1 生成下载令牌</a></li>
                        <li><a href="#verify-ip">3.2 IP验证</a></li>
                        <li><a href="#download-file">3.3 文件下载</a></li>
                        <li><a href="#get-stats">3.4 获取统计</a></li>
                    </ul>
                </li>
                <li><a href="#errors">4. 错误处理</a></li>
                <li><a href="#examples">5. 使用示例</a></li>
            </ul>
        </div>

        <!-- API概述 -->
        <div class="card" id="overview">
            <h2>1. API概述</h2>
            <p>多站点IP验证下载系统提供RESTful API，支持：</p>
            <ul>
                <li>🔐 安全的下载令牌生成</li>
                <li>🌐 IP地址验证</li>
                <li>📥 文件下载管理</li>
                <li>📊 下载统计查询</li>
            </ul>
            
            <h3>基础信息</h3>
            <div class="params">
                <strong>API基础URL:</strong> https://dw.ytmour.art/api/<br>
                <strong>数据格式:</strong> JSON<br>
                <strong>字符编码:</strong> UTF-8<br>
                <strong>请求方法:</strong> GET, POST
            </div>
        </div>

        <!-- 身份验证 -->
        <div class="card" id="authentication">
            <h2>2. 身份验证</h2>
            <p>API使用站点API密钥进行身份验证。每个注册的站点都有唯一的API密钥。</p>
            
            <h3>认证方式</h3>
            <div class="code">
// 在请求头中包含API密钥
X-API-Key: your_site_api_key

// 或在POST数据中包含
{
    "api_key": "your_site_api_key",
    "other_params": "..."
}</div>
        </div>

        <!-- API端点 -->
        <div class="card" id="endpoints">
            <h2>3. API端点</h2>

            <!-- 生成下载令牌 -->
            <div class="endpoint" id="generate-token">
                <h3>3.1 生成下载令牌</h3>
                <div>
                    <span class="method post">POST</span>
                    <code>/api/download_api.php?action=generate</code>
                </div>
                
                <p>为指定文件生成安全下载令牌和下载器。</p>
                
                <h4>请求参数</h4>
                <div class="params">
                    <strong>api_key</strong> (string, 必需) - 站点API密钥<br>
                    <strong>file_url</strong> (string, 必需) - 文件下载URL<br>
                    <strong>software_name</strong> (string, 必需) - 软件名称<br>
                    <strong>user_ip</strong> (string, 可选) - 用户IP地址
                </div>
                
                <h4>请求示例</h4>
                <div class="code">
POST /api/download_api.php?action=generate
Content-Type: application/json

{
    "api_key": "home_1234567890abcdef_20250107",
    "file_url": "https://dw.ytmour.art/files/home/software.exe",
    "software_name": "Adobe Photoshop 2024",
    "user_ip": "192.168.1.100"
}</div>
                
                <h4>响应示例</h4>
                <div class="response">
                    <div class="code">
{
    "success": true,
    "message": "下载器生成成功",
    "data": {
        "token": "dyn_a8f3e9c2b1d4567890abcdef12345678",
        "download_url": "https://dw.ytmour.art/downloads/downloader_abc123.zip",
        "expires_at": "2025-01-08 12:00:00",
        "file_size": "1.2 GB"
    }
}</div>
                </div>
            </div>

            <!-- IP验证 -->
            <div class="endpoint" id="verify-ip">
                <h3>3.2 IP验证</h3>
                <div>
                    <span class="method post">POST</span>
                    <code>/api/download_api.php?action=verify</code>
                </div>
                
                <p>验证用户IP地址是否与令牌绑定的IP一致。</p>
                
                <h4>请求参数</h4>
                <div class="params">
                    <strong>token</strong> (string, 必需) - 下载令牌<br>
                    <strong>ip</strong> (string, 必需) - 当前IP地址
                </div>
                
                <h4>请求示例</h4>
                <div class="code">
POST /api/download_api.php?action=verify
Content-Type: application/json

{
    "token": "dyn_a8f3e9c2b1d4567890abcdef12345678",
    "ip": "192.168.1.100"
}</div>
                
                <h4>响应示例</h4>
                <div class="response">
                    <div class="code">
{
    "success": true,
    "message": "IP验证成功",
    "data": {
        "verification_result": "IP_MATCH",
        "download_allowed": true,
        "remaining_downloads": 4
    }
}</div>
                </div>
            </div>

            <!-- 文件下载 -->
            <div class="endpoint" id="download-file">
                <h3>3.3 文件下载</h3>
                <div>
                    <span class="method get">GET</span>
                    <code>/api/download_api.php?action=download&token={token}</code>
                </div>
                
                <p>使用验证通过的令牌下载文件。</p>
                
                <h4>请求参数</h4>
                <div class="params">
                    <strong>token</strong> (string, 必需) - 已验证的下载令牌
                </div>
                
                <h4>请求示例</h4>
                <div class="code">
GET /api/download_api.php?action=download&token=dyn_a8f3e9c2b1d4567890abcdef12345678</div>
                
                <h4>响应</h4>
                <div class="response">
                    成功时返回文件流，失败时返回JSON错误信息。
                </div>
            </div>

            <!-- 获取统计 -->
            <div class="endpoint" id="get-stats">
                <h3>3.4 获取统计</h3>
                <div>
                    <span class="method get">GET</span>
                    <code>/api/download_api.php?action=stats</code>
                </div>
                
                <p>获取站点下载统计信息。</p>
                
                <h4>请求参数</h4>
                <div class="params">
                    <strong>api_key</strong> (string, 必需) - 站点API密钥
                </div>
                
                <h4>响应示例</h4>
                <div class="response">
                    <div class="code">
{
    "success": true,
    "data": {
        "total_downloads": 1250,
        "today_downloads": 45,
        "active_tokens": 12,
        "success_rate": 95.2
    }
}</div>
                </div>
            </div>
        </div>

        <!-- 错误处理 -->
        <div class="card" id="errors">
            <h2>4. 错误处理</h2>
            <p>API使用标准HTTP状态码和JSON格式返回错误信息。</p>
            
            <h3>常见错误码</h3>
            <div class="params">
                <strong>400 Bad Request</strong> - 请求参数错误<br>
                <strong>401 Unauthorized</strong> - API密钥无效<br>
                <strong>403 Forbidden</strong> - IP验证失败<br>
                <strong>404 Not Found</strong> - 资源不存在<br>
                <strong>429 Too Many Requests</strong> - 请求频率超限<br>
                <strong>500 Internal Server Error</strong> - 服务器内部错误
            </div>
            
            <h3>错误响应格式</h3>
            <div class="code">
{
    "success": false,
    "error": "INVALID_API_KEY",
    "message": "API密钥无效或已过期",
    "code": 401
}</div>
        </div>

        <!-- 使用示例 -->
        <div class="card" id="examples">
            <h2>5. 使用示例</h2>
            
            <h3>JavaScript示例</h3>
            <div class="code">
// 生成下载令牌
async function generateDownloadToken(fileUrl, softwareName) {
    const response = await fetch('/api/download_api.php?action=generate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-API-Key': 'your_api_key'
        },
        body: JSON.stringify({
            file_url: fileUrl,
            software_name: softwareName,
            user_ip: await getUserIP()
        })
    });
    
    const result = await response.json();
    if (result.success) {
        return result.data.token;
    } else {
        throw new Error(result.message);
    }
}

// 获取用户IP
async function getUserIP() {
    const response = await fetch('https://api.ipify.org?format=json');
    const data = await response.json();
    return data.ip;
}</div>
            
            <h3>PHP示例</h3>
            <div class="code">
// 生成下载令牌
function generateDownloadToken($apiKey, $fileUrl, $softwareName, $userIP) {
    $data = [
        'api_key' => $apiKey,
        'file_url' => $fileUrl,
        'software_name' => $softwareName,
        'user_ip' => $userIP
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://dw.ytmour.art/api/download_api.php?action=generate');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}</div>
        </div>

        <!-- 测试工具 -->
        <div class="card">
            <h2>🧪 API测试工具</h2>
            <p>可以使用以下工具测试API：</p>
            <ul>
                <li><a href="../debug_api.php" target="_blank">📊 API调试工具</a> - 在线测试API功能</li>
                <li><a href="https://www.postman.com/" target="_blank">📮 Postman</a> - 专业API测试工具</li>
                <li><a href="https://curl.se/" target="_blank">🌐 cURL</a> - 命令行HTTP客户端</li>
            </ul>
        </div>
    </div>
</body>
</html>

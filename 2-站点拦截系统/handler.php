<?php
/**
 * 分站下载处理器
 * 简化版本，支持两种模式：演示模式和真实模式
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 配置文件路径
$configFile = __DIR__ . '/config.json';

// 默认配置
$defaultConfig = [
    'site_name' => '分站系统',
    'site_key' => 'site',
    'api_key' => '', // 需要配置API密钥
    'storage_server' => 'https://dw.ytmour.art',
    'debug_mode' => false // 控制台调试日志开关
];

// 加载配置
function loadConfig() {
    global $configFile, $defaultConfig;

    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if ($config) {
            $merged = array_merge($defaultConfig, $config);
            error_log("加载配置文件: " . json_encode($merged));
            return $merged;
        }
    }

    error_log("使用默认配置: " . json_encode($defaultConfig));
    return $defaultConfig;
}

// 保存配置
function saveConfig($config) {
    global $configFile;
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$config = loadConfig();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

error_log("收到请求: action='$action', method=" . $_SERVER['REQUEST_METHOD']);

switch ($action) {
    case 'generate':
        handleGenerate($config);
        break;
    case 'stats':
        handleStats($config);
        break;
    case 'config':
        handleConfig();
        break;
    default:
        error_log("无效操作: '$action', 可用操作: generate, stats, config");
        sendError('无效的操作: ' . $action, 400);
}

function handleGenerate($config) {
    // 获取原始输入
    $rawInput = file_get_contents('php://input');
    error_log("原始输入数据: " . $rawInput);

    // 尝试解析JSON
    $input = json_decode($rawInput, true);

    // 如果JSON解析失败，尝试POST数据
    if (!$input) {
        $input = $_POST;
        error_log("JSON解析失败，使用POST数据: " . json_encode($_POST));
    }

    $fileUrl = trim($input['file_url'] ?? '');
    $softwareName = trim($input['software_name'] ?? '');
    $userIP = $input['user_ip'] ?? $_SERVER['REMOTE_ADDR'];

    // 记录详细调试信息
    error_log("处理器收到参数: " . json_encode([
        'raw_input_length' => strlen($rawInput),
        'json_decode_result' => $input,
        'file_url' => $fileUrl,
        'software_name' => $softwareName,
        'user_ip' => $userIP,
        'file_url_length' => strlen($fileUrl),
        'software_name_length' => strlen($softwareName)
    ]));

    if (empty($fileUrl) || empty($softwareName)) {
        error_log("参数验证失败: fileUrl='$fileUrl' (" . strlen($fileUrl) . "), softwareName='$softwareName' (" . strlen($softwareName) . ")");
        sendError('缺少必要参数: file_url=' . $fileUrl . ', software_name=' . $softwareName, 400);
        return;
    }
    
    // 检查API密钥配置
    $apiKey = trim($config['api_key'] ?? '');

    if (empty($apiKey)) {
        error_log("❌ API密钥未配置");
        sendError('API密钥未配置，请联系管理员', 500);
        return;
    }

    error_log("🔗 连接到总后台: " . $config['storage_server']);
    handleRealGenerate($config, $fileUrl, $softwareName, $userIP);
}



function handleRealGenerate($config, $fileUrl, $softwareName, $userIP) {
    // 使用正确的API格式 - 根据总后台API代码
    $postData = [
        'file_url' => $fileUrl,
        'software_name' => $softwareName,
        'user_ip' => $userIP  // 添加用户IP字段
    ];

    // 正确的API端点
    $url = rtrim($config['storage_server'], '/') . '/api/download_api.php?action=create';

    error_log("🔗 连接到总后台API: $url");
    error_log("📤 发送数据: " . json_encode($postData, JSON_UNESCAPED_UNICODE));
    error_log("🔑 使用API密钥: " . substr($config['api_key'], 0, 8) . '...');
    error_log("📏 软件名称长度: " . strlen($softwareName) . " 字符");
    error_log("📏 文件URL长度: " . strlen($fileUrl) . " 字符");

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData), // 使用表单格式，不是JSON
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'X-API-Key: ' . $config['api_key'],
            'User-Agent: Site-Handler/1.0'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    error_log("HTTP状态码: $httpCode");
    error_log("cURL错误: $curlError");
    error_log("响应内容: " . substr($response, 0, 500));

    if ($response === false) {
        error_log("❌ 网络连接失败: $curlError");
        sendError('网络连接失败: ' . $curlError, 503);
        return;
    }

    if ($httpCode !== 200) {
        error_log("❌ HTTP错误 $httpCode: " . substr($response, 0, 200));

        // 尝试解析错误响应
        $errorData = json_decode($response, true);
        if ($errorData && isset($errorData['message'])) {
            sendError('总后台错误: ' . $errorData['message'], 503);
        } else {
            sendError('服务器返回错误 HTTP ' . $httpCode . ': ' . substr($response, 0, 100), 503);
        }
        return;
    }

    error_log("✅ 总后台响应成功: " . substr($response, 0, 200));
    echo $response;
}

function handleStats($config) {
    $response = [
        'success' => true,
        'data' => [
            'site_name' => $config['site_name'],
            'site_key' => $config['site_key'],
            'server_status' => 'online',
            'api_status' => !empty($config['api_key']) ? 'connected' : 'not_configured',
            'last_check' => date('Y-m-d H:i:s'),
            'configured' => !empty($config['api_key']),
            'api_key' => !empty($config['api_key']) ? '已配置' : '未配置'
        ]
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

function handleConfig() {
    global $config;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 保存配置
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $storageServer = trim($input['storage_server'] ?? $config['storage_server']);
        $apiKey = trim($input['api_key'] ?? $config['api_key']);

        $newConfig = [
            'site_name' => '分站系统',
            'site_key' => 'site_' . substr(md5($storageServer), 0, 8),
            'api_key' => $apiKey,
            'storage_server' => $storageServer
        ];

        error_log("保存配置: api_key='" . (empty($apiKey) ? '未配置' : '已配置') . "'");
        
        if (saveConfig($newConfig)) {
            echo json_encode(['success' => true, 'message' => '配置保存成功'], JSON_UNESCAPED_UNICODE);
        } else {
            sendError('配置保存失败', 500);
        }
    } else {
        // 获取配置
        echo json_encode(['success' => true, 'data' => $config], JSON_UNESCAPED_UNICODE);
    }
}

function sendError($message, $code = 500) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => 'HANDLER_ERROR',
        'message' => $message,
        'code' => $code
    ], JSON_UNESCAPED_UNICODE);
}
?>

<?php
session_start();
require_once 'config.php';

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => '无权限访问'
    ]);
    exit;
}

// 接收POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['token'] ?? '';

        if (empty($token)) {
            throw new Exception('Token不能为空');
        }

        // 读取配置文件
        $configFile = 'config.php';
        $configContent = file_get_contents($configFile);

        // 更新MEITUAN_TOKEN的值
        $pattern = "/define\('MEITUAN_TOKEN',\s*'.*?'\);/";
        $replacement = "define('MEITUAN_TOKEN', '" . addslashes($token) . "');";
        
        if (preg_match($pattern, $configContent)) {
            $newContent = preg_replace($pattern, $replacement, $configContent);
        } else {
            // 如果没有找到MEITUAN_TOKEN定义，添加到文件末尾
            $newContent = $configContent . "\n" . $replacement . "\n";
        }

        // 写入文件
        if (file_put_contents($configFile, $newContent) !== false) {
            echo json_encode([
                'success' => true,
                'message' => 'Token更新成功'
            ]);
        } else {
            throw new Exception('无法写入配置文件');
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => '不支持的请求方法'
    ]);
}
?> 
<?php
session_start();
require_once 'config.php';

// 如果已经登录，直接跳转到管理页面
if (isLoggedIn()) {
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (validateAdminLogin($username, $password)) {
        // 设置会话变量
        $_SESSION['logged_in'] = true;
        $_SESSION['is_admin'] = true;
        $_SESSION['username'] = $username;
        
        // 设置会话cookie参数
        session_set_cookie_params([
            'lifetime' => 86400, // 24小时
            'path' => '/',
            'secure' => true,    // 只在HTTPS下传输
            'httponly' => true,  // 防止XSS攻击
            'samesite' => 'Lax' // 防止CSRF攻击
        ]);
        
        // 重新生成会话ID以防止会话固定攻击
        session_regenerate_id(true);
        
        header('Location: admin.php');
        exit;
    } else {
        $error = '用户名或密码错误';
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 美团图床</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', sans-serif;
            background-color: #f0f4f8;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #666;
        }
        input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 0.75rem;
            background: #2ecc71;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            background: #27ae60;
        }
        .error {
            color: #e74c3c;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>管理员登录</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <input type="text" name="username" placeholder="用户名" required>
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="密码" required>
            </div>
            <button type="submit">登录</button>
        </form>
    </div>
</body>
</html> 
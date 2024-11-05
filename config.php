<?php
// 默认管理员账号配置
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');

// 图床配置
define('MEITUAN_TOKEN', ''); // 默认为空，通过后台配置

// 用户认证
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && 
           isset($_SESSION['username']) && !empty($_SESSION['username']);
}

function isAdmin() {
    return isLoggedIn() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

// 验证管理员登录
function validateAdminLogin($username, $password) {
    return $username === ADMIN_USERNAME && $password === ADMIN_PASSWORD;
}
?> 
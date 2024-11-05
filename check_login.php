<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// 检查会话是否有效
if (isLoggedIn()) {
    echo json_encode([
        'logged_in' => true,
        'username' => $_SESSION['username'] ?? 'admin'
    ]);
} else {
    echo json_encode([
        'logged_in' => false
    ]);
} 
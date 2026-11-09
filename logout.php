
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Xóa toàn bộ thông tin đăng nhập
$_SESSION = [];

// Xóa session
session_destroy();

// Quay về trang chủ thư mục gốc
header("Location: /dat-san-bong-chuyen/index.php");
exit;

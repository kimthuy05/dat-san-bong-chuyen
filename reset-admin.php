
<?php
require_once __DIR__ . '/includes/db.php';

// Đổi thành mật khẩu bạn muốn sử dụng
$matKhauMoi = '123456';

// Mã hóa mật khẩu đúng chuẩn PHP
$matKhauHash = password_hash($matKhauMoi, PASSWORD_DEFAULT);

// Cập nhật mật khẩu tài khoản Admin
$sql = "UPDATE nguoidung
        SET MatKhau = ?
        WHERE Email = ?
        AND VaiTro = 'Admin'";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Lỗi chuẩn bị truy vấn: " . $conn->error);
}

$email = 'admin@gmail.com';

$stmt->bind_param("ss", $matKhauHash, $email);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "Đã cập nhật mật khẩu Admin thành công!";
} else {
    echo "Không cập nhật được. Hãy kiểm tra lại tài khoản Admin.";
}

$stmt->close();
$conn->close();
?>

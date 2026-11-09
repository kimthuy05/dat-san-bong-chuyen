<?php
require_once '../includes/db.php';
if (!isset($_SESSION['ma_tai_khoan'])) { header("Location: ../login.php"); exit(); }

if (isset($_GET['madon'])) {
    $maDon = intval($_GET['madon']);
    $maUser = $_SESSION['ma_tai_khoan'];

    $sqlCheck = "SELECT * FROM don_dat_san WHERE ma_don_dat = ? AND ma_tai_khoan = ?";
    $stmt = $conn->prepare($sqlCheck);
    $stmt->bind_param("ii", $maDon, $maUser);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        $sqlUpdate = "UPDATE don_dat_san SET trang_thai = 'DaHuy' WHERE ma_don_dat = ?";
        $stmtUp = $conn->prepare($sqlUpdate);
        $stmtUp->bind_param("i", $maDon);
        $stmtUp->execute();
        
        echo "<script>alert('Đã hủy lịch thành công!'); window.location.href='lich-cua-toi.php';</script>";
        exit();
    }
}
?>
<?php
require_once '../includes/db.php';
if (!isset($_SESSION['ma_tai_khoan'])) { header("Location: ../login.php"); exit(); }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $maTaiKhoan = $_SESSION['ma_tai_khoan'];
    $maSan = intval($_POST['ma_san']);
    $ngayDat = trim($_POST['ngay_dat']);
    $danhSachCa = isset($_POST['ma_khung_gio']) ? $_POST['ma_khung_gio'] : [];

    if (empty($danhSachCa)) {
        echo "<script>alert('Vui lòng chọn ít nhất 1 khung giờ!'); window.history.back();</script>";
        exit();
    }

    // 1. Kiểm tra chống trùng lịch (Anti-Double Booking)
    foreach ($danhSachCa as $maKhungGio) {
        $sqlCheck = "SELECT dt.ma_chi_tiet FROM chi_tiet_dat_san dt 
                     JOIN don_dat_san d ON dt.ma_don_dat = d.ma_don_dat 
                     WHERE dt.ma_san = ? AND dt.ngay_dat = ? AND dt.ma_khung_gio = ? AND d.trang_thai != 'DaHuy'";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bind_param("isi", $maSan, $ngayDat, $maKhungGio);
        $stmtCheck->execute();
        
        if ($stmtCheck->get_result()->num_rows > 0) {
            echo "<script>alert('Khung giờ có ca đã bị người khác đặt mất! Vui lòng chọn lại.'); window.history.back();</script>";
            exit();
        }
    }

    // 2. Tính tổng tiền cộng dồn theo đơn giá từng ca
    $tongTien = 0;
    foreach ($danhSachCa as $maKhungGio) {
        $sqlGia = "SELECT gia_tien FROM khung_gio WHERE ma_khung_gio = ?";
        $stmtGia = $conn->prepare($sqlGia);
        $stmtGia->bind_param("i", $maKhungGio);
        $stmtGia->execute();
        $resGia = $stmtGia->get_result()->fetch_assoc();
        if ($resGia) {
            $tongTien += $resGia['gia_tien'];
        }
    }
    $tienCoc = $tongTien * 0.5; // Cọc 50%

    // 3. Dùng Transaction lưu đơn vào CSDL
    $conn->begin_transaction();
    try {
        $sqlDon = "INSERT INTO don_dat_san (ma_tai_khoan, tong_tien, tien_coc, trang_thai) VALUES (?, ?, ?, 'ChoThanhToan')";
        $stmtDon = $conn->prepare($sqlDon);
        $stmtDon->bind_param("idd", $maTaiKhoan, $tongTien, $tienCoc);
        $stmtDon->execute();
        $maDonMoi = $stmtDon->insert_id;

        $sqlCT = "INSERT INTO chi_tiet_dat_san (ma_don_dat, ma_san, ngay_dat, ma_khung_gio) VALUES (?, ?, ?, ?)";
        $stmtCT = $conn->prepare($sqlCT);
        foreach ($danhSachCa as $maKhungGio) {
            $stmtCT->bind_param("iisi", $maDonMoi, $maSan, $ngayDat, $maKhungGio);
            $stmtCT->execute();
        }

        $conn->commit();
        header("Location: qr-payment.php?madon=" . $maDonMoi);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Lỗi hệ thống!'); window.history.back();</script>";
    }
}
?>
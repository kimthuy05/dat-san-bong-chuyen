<?php
require_once '../includes/db.php';
$ngayDat = isset($_GET['ngay']) ? $_GET['ngay'] : date('Y-m-d');
$maSan = isset($_GET['ma_san']) ? intval($_GET['ma_san']) : 1;

$resultKhungGio = $conn->query("SELECT * FROM khung_gio");
$danhSachCa = [];

while ($row = $resultKhungGio->fetch_assoc()) {
    $maKhungGio = $row['ma_khung_gio'];
    $sqlCheck = "SELECT dt.ma_chi_tiet FROM chi_tiet_dat_san dt 
                 JOIN don_dat_san d ON dt.ma_don_dat = d.ma_don_dat 
                 WHERE dt.ma_san = ? AND dt.ngay_dat = ? AND dt.ma_khung_gio = ? AND d.trang_thai != 'DaHuy'";
    $stmt = $conn->prepare($sqlCheck);
    $stmt->bind_param("isi", $maSan, $ngayDat, $maKhungGio);
    $stmt->execute();
    
    $trangThai = ($stmt->get_result()->num_rows > 0) ? "DaDat" : "ConTrong";
    $danhSachCa[] = [
        "ma_khung_gio" => $row['ma_khung_gio'],
        "ten_khung_gio" => $row['ten_khung_gio'],
        "gio_bat_dau" => $row['gio_bat_dau'],
        "gio_ket_thuc" => $row['gio_ket_thuc'],
        "gia_tien" => $row['gia_tien'],
        "trang_thai" => $trangThai
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($danhSachCa);
?>
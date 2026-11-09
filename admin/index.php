<?php
session_start();
include '../includes/db.php';

// Kiểm tra đăng nhập Admin
if (!isset($_SESSION['MaND']) || $_SESSION['VaiTro'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Lấy số liệu thống kê
$tong_san = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM san"))['total'];
$tong_khach = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM nguoidung WHERE VaiTro = 'Khách hàng'"))['total'];
$tong_lich = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM lichdat"))['total'];
$lich_hom_nay = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM lichdat WHERE NgayDat = CURDATE()"))['total'];

// Lấy 5 lịch đặt mới nhất
$sql_lich = "SELECT ld.*, nd.HoTen, s.TenSan, kg.ThoiGianBatDau, kg.ThoiGianKetThuc 
             FROM lichdat ld
             JOIN nguoidung nd ON ld.MaND = nd.MaND
             JOIN san s ON ld.MaSan = s.MaSan
             JOIN khunggio kg ON ld.MaKhungGio = kg.MaKhungGio
             ORDER BY ld.NgayTao DESC LIMIT 5";
$result_lich = mysqli_query($conn, $sql_lich);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Đặt sân bóng chuyền</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body { background: #f1f5f9; display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: #1e293b;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100%;
        }
        .sidebar h2 {
            text-align: center;
            padding: 10px;
            margin-bottom: 30px;
            font-size: 20px;
            border-bottom: 1px solid #334155;
            padding-bottom: 20px;
        }
        .sidebar a {
            display: block;
            color: #cbd5e1;
            text-decoration: none;
            padding: 14px 25px;
            transition: 0.3s;
        }
        .sidebar a:hover, .sidebar a.active {
            background: #334155;
            color: white;
        }
        
        /* Main content */
        .main {
            margin-left: 260px;
            flex: 1;
            padding: 30px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .header h1 { color: #1e293b; font-size: 26px; }
        .header .user { color: #64748b; }
        
        /* Thẻ thống kê */
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .stat-card h3 {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 10px;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #1e293b;
        }
        
        /* Bảng */
        .table-box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .table-box h2 {
            margin-bottom: 20px;
            color: #1e293b;
            font-size: 18px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 14px;
        }
        td { color: #334155; font-size: 14px; }
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #dcfce7; color: #16a34a; }
        .badge-warning { background: #fef9c3; color: #ca8a04; }
        .badge-danger { background: #fee2e2; color: #dc2626; }
        .badge-info { background: #e0f2fe; color: #0284c7; }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <a href="index.php" class="active">Dashboard</a>
        <a href="quan-ly-san.php">Quản lý sân</a>
        <a href="quan-ly-lich.php">Quản lý lịch đặt</a>
        <a href="quan-ly-yeu-cau.php">Quản lý yêu cầu</a>
        <a href="quan-ly-khach.php">Quản lý khách hàng</a>
        <a href="thong-ke.php">Thống kê</a>
        <a href="../logout.php">Đăng xuất</a>
    </div>

    <!-- Main content -->
    <div class="main">
        <div class="header">
            <h1>Dashboard</h1>
            <div class="user">Xin chào, <strong><?php echo $_SESSION['HoTen']; ?></strong></div>
        </div>

        <!-- Thẻ thống kê -->
        <div class="stats">
            <div class="stat-card">
                <h3>Tổng số sân</h3>
                <div class="number"><?php echo $tong_san; ?></div>
            </div>
            <div class="stat-card">
                <h3>Khách hàng</h3>
                <div class="number"><?php echo $tong_khach; ?></div>
            </div>
            <div class="stat-card">
                <h3>Tổng lịch đặt</h3>
                <div class="number"><?php echo $tong_lich; ?></div>
            </div>
            <div class="stat-card">
                <h3>Lịch hôm nay</h3>
                <div class="number"><?php echo $lich_hom_nay; ?></div>
            </div>
        </div>

        <!-- Bảng lịch mới nhất -->
        <div class="table-box">
            <h2>Lịch đặt mới nhất</h2>
            <table>
                <thead>
                    <tr>
                        <th>Mã lịch</th>
                        <th>Khách hàng</th>
                        <th>Sân</th>
                        <th>Ngày</th>
                        <th>Giờ</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result_lich)) { ?>
                    <tr>
                        <td>#<?php echo $row['MaLichDat']; ?></td>
                        <td><?php echo $row['HoTen']; ?></td>
                        <td><?php echo $row['TenSan']; ?></td>
                        <td><?php echo date('d/m/Y', strtotime($row['NgayDat'])); ?></td>
                        <td><?php echo substr($row['ThoiGianBatDau'], 0, 5) . ' - ' . substr($row['ThoiGianKetThuc'], 0, 5); ?></td>
                        <td>
                            <?php 
                            $trangthai = $row['TrangThai'];
                            if ($trangthai == 'Đã đặt') echo '<span class="badge badge-success">Đã đặt</span>';
                            elseif ($trangthai == 'Đang giữ chỗ') echo '<span class="badge badge-warning">Đang giữ chỗ</span>';
                            elseif ($trangthai == 'Đã hủy') echo '<span class="badge badge-danger">Đã hủy</span>';
                            else echo '<span class="badge badge-info">Hoàn thành</span>';
                            ?>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
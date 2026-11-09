<?php
session_start();
include '../includes/db.php';

// Kiểm tra đăng nhập Admin
if (!isset($_SESSION['MaND']) || $_SESSION['VaiTro'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Tổng số liệu
$tong_san = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM san"))['total'];
$tong_khach = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM nguoidung WHERE VaiTro = 'Khách hàng'"))['total'];
$tong_lich = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM lichdat"))['total'];
$lich_da_dat = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM lichdat WHERE TrangThai = 'Đã đặt'"))['total'];
$lich_huy = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM lichdat WHERE TrangThai = 'Đã hủy'"))['total'];
$lich_hoan_thanh = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM lichdat WHERE TrangThai = 'Hoàn thành'"))['total'];

// Doanh thu (tính từ các lịch đã đặt + hoàn thành)
$doanh_thu = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT SUM(TongTien) as total FROM lichdat WHERE TrangThai IN ('Đã đặt', 'Hoàn thành')"
))['total'] ?? 0;

// Doanh thu hôm nay
$doanh_thu_hom_nay = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT SUM(TongTien) as total FROM lichdat WHERE NgayDat = CURDATE() AND TrangThai IN ('Đã đặt', 'Hoàn thành')"
))['total'] ?? 0;

// Top sân được đặt nhiều nhất
$sql_top_san = "SELECT s.TenSan, COUNT(ld.MaLichDat) as so_luot
                FROM lichdat ld
                JOIN san s ON ld.MaSan = s.MaSan
                GROUP BY ld.MaSan
                ORDER BY so_luot DESC
                LIMIT 5";
$result_top_san = mysqli_query($conn, $sql_top_san);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống kê - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body { background: #f1f5f9; display: flex; min-height: 100vh; }
        
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
        
        .main {
            margin-left: 260px;
            flex: 1;
            padding: 30px;
        }
        .header {
            margin-bottom: 30px;
        }
        .header h1 { color: #1e293b; font-size: 26px; }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
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
            font-size: 28px;
            font-weight: bold;
            color: #1e293b;
        }
        .stat-card.highlight {
            background: #2563eb;
            color: white;
        }
        .stat-card.highlight h3 { color: #bfdbfe; }
        .stat-card.highlight .number { color: white; }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .card h2 {
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
        }
        
        .status-list {
            list-style: none;
        }
        .status-list li {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 15px;
        }
        .status-list li:last-child { border-bottom: none; }
        .status-list .label { color: #475569; }
        .status-list .value { font-weight: bold; color: #1e293b; }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <a href="index.php">Dashboard</a>
        <a href="quan-ly-san.php">Quản lý sân</a>
        <a href="quan-ly-lich.php">Quản lý lịch đặt</a>
        <a href="quan-ly-khach.php">Quản lý khách hàng</a>
        <a href="thong-ke.php" class="active">Thống kê</a>
        <a href="logout.php">Đăng xuất</a>
    </div>

    <!-- Main -->
    <div class="main">
        <div class="header">
            <h1>Thống kê hệ thống</h1>
        </div>

        <!-- Thẻ số liệu -->
        <div class="stats">
            <div class="stat-card highlight">
                <h3>Tổng doanh thu</h3>
                <div class="number"><?php echo number_format($doanh_thu, 0, ',', '.'); ?>đ</div>
            </div>
            <div class="stat-card">
                <h3>Doanh thu hôm nay</h3>
                <div class="number"><?php echo number_format($doanh_thu_hom_nay, 0, ',', '.'); ?>đ</div>
            </div>
            <div class="stat-card">
                <h3>Tổng lịch đặt</h3>
                <div class="number"><?php echo $tong_lich; ?></div>
            </div>
            <div class="stat-card">
                <h3>Tổng khách hàng</h3>
                <div class="number"><?php echo $tong_khach; ?></div>
            </div>
        </div>

        <div class="grid-2">
            <!-- Thống kê theo trạng thái -->
            <div class="card">
                <h2>Thống kê theo trạng thái lịch</h2>
                <ul class="status-list">
                    <li>
                        <span class="label">Đã đặt</span>
                        <span class="value"><?php echo $lich_da_dat; ?></span>
                    </li>
                    <li>
                        <span class="label">Hoàn thành</span>
                        <span class="value"><?php echo $lich_hoan_thanh; ?></span>
                    </li>
                    <li>
                        <span class="label">Đã hủy</span>
                        <span class="value"><?php echo $lich_huy; ?></span>
                    </li>
                    <li>
                        <span class="label">Tổng số sân</span>
                        <span class="value"><?php echo $tong_san; ?></span>
                    </li>
                </ul>
            </div>

            <!-- Top sân đặt nhiều nhất -->
            <div class="card">
                <h2>Top sân được đặt nhiều nhất</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Tên sân</th>
                            <th>Số lượt đặt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result_top_san) > 0) { ?>
                            <?php while ($row = mysqli_fetch_assoc($result_top_san)) { ?>
                            <tr>
                                <td><?php echo $row['TenSan']; ?></td>
                                <td><strong><?php echo $row['so_luot']; ?></strong></td>
                            </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="2" style="text-align: center; color: #94a3b8;">Chưa có dữ liệu</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>
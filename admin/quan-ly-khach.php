<?php
session_start();
include '../includes/db.php';

// Kiểm tra đăng nhập Admin
if (!isset($_SESSION['MaND']) || $_SESSION['VaiTro'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Xử lý khóa / mở khóa tài khoản
if (isset($_GET['khoa'])) {
    $ma_nd = intval($_GET['khoa']);
    mysqli_query($conn, "UPDATE nguoidung SET TrangThai = 'Bị khóa' WHERE MaND = $ma_nd AND VaiTro = 'Khách hàng'");
    header("Location: quan-ly-khach.php");
    exit();
}

if (isset($_GET['mo_khoa'])) {
    $ma_nd = intval($_GET['mo_khoa']);
    mysqli_query($conn, "UPDATE nguoidung SET TrangThai = 'Hoạt động' WHERE MaND = $ma_nd AND VaiTro = 'Khách hàng'");
    header("Location: quan-ly-khach.php");
    exit();
}

// Tìm kiếm
$search = "";
if (isset($_GET['search']) && $_GET['search'] != '') {
    $keyword = mysqli_real_escape_string($conn, $_GET['search']);
    $search = "AND (HoTen LIKE '%$keyword%' OR Email LIKE '%$keyword%' OR SoDienThoai LIKE '%$keyword%')";
}

// Lấy danh sách khách hàng
$sql = "SELECT * FROM nguoidung 
        WHERE VaiTro = 'Khách hàng' $search
        ORDER BY NgayTao DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý khách hàng - Admin</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .header h1 { color: #1e293b; font-size: 26px; }
        
        .btn {
            padding: 7px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
            color: white;
        }
        .btn-danger { background: #dc2626; }
        .btn-success { background: #16a34a; }
        .btn-primary { background: #2563eb; }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .search-box input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            width: 300px;
            font-size: 14px;
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
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #dcfce7; color: #16a34a; }
        .badge-danger { background: #fee2e2; color: #dc2626; }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <a href="index.php">Dashboard</a>
        <a href="quan-ly-san.php">Quản lý sân</a>
        <a href="quan-ly-lich.php">Quản lý lịch đặt</a>
        <a href="quan-ly-khach.php" class="active">Quản lý khách hàng</a>
        <a href="thong-ke.php">Thống kê</a>
        <a href="logout.php">Đăng xuất</a>
    </div>

    <!-- Main -->
    <div class="main">
        <div class="header">
            <h1>Quản lý khách hàng</h1>
        </div>

        <div class="card">
            <!-- Tìm kiếm -->
            <div class="search-box">
                <form method="GET">
                    <input type="text" name="search" placeholder="Tìm theo tên, email, số điện thoại..." 
                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <button type="submit" class="btn btn-primary">Tìm kiếm</button>
                </form>
            </div>

            <!-- Bảng khách hàng -->
            <table>
                <thead>
                    <tr>
                        <th>Mã</th>
                        <th>Họ tên</th>
                        <th>Email</th>
                        <th>Số điện thoại</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0) { ?>
                        <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                        <tr>
                            <td>#<?php echo $row['MaND']; ?></td>
                            <td><?php echo $row['HoTen']; ?></td>
                            <td><?php echo $row['Email']; ?></['Email']; ?></td>
                            <td><?php echo $row['SoDienThoai'] ?? '—'; ?></td>
                            <td>
                                <?php if ($row['TrangThai'] == 'Hoạt động') { ?>
                                    <span class="badge badge-success">Hoạt động</span>
                                <?php } else { ?>
                                    <span class="badge badge-danger">Bị khóa</span>
                                <?php } ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($row['NgayTao'])); ?></td>
                            <td>
                                <?php if ($row['TrangThai'] == 'Hoạt động') { ?>
                                    <a href="?khoa=<?php echo $row['MaND']; ?>" 
                                       class="btn btn-danger"
                                       onclick="return confirm('Bạn có chắc muốn khóa tài khoản này?')">
                                        Khóa
                                    </a>
                                <?php } else { ?>
                                    <a href="?mo_khoa=<?php echo $row['MaND']; ?>" 
                                       class="btn btn-success">
                                        Mở khóa
                                    </a>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #94a3b8;">
                                Không có khách hàng nào
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
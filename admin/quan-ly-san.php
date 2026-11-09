<?php
session_start();
include '../includes/db.php';

// Kiểm tra đăng nhập Admin
if (!isset($_SESSION['MaND']) || $_SESSION['VaiTro'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Xử lý thêm sân
if (isset($_POST['them_san'])) {
    $ten_san = mysqli_real_escape_string($conn, $_POST['ten_san']);
    $loai_san = mysqli_real_escape_string($conn, $_POST['loai_san']);
    $mo_ta = mysqli_real_escape_string($conn, $_POST['mo_ta']);
    $trang_thai = $_POST['trang_thai'];

    $sql = "INSERT INTO san (TenSan, LoaiSan, MoTa, TrangThai) 
            VALUES ('$ten_san', '$loai_san', '$mo_ta', '$trang_thai')";
    mysqli_query($conn, $sql);
    header("Location: quan-ly-san.php");
    exit();
}

// Xử lý xóa sân
if (isset($_GET['xoa'])) {
    $ma_san = intval($_GET['xoa']);
    mysqli_query($conn, "DELETE FROM san WHERE MaSan = $ma_san");
    header("Location: quan-ly-san.php");
    exit();
}

// Xử lý đổi trạng thái
if (isset($_GET['doi_trang_thai'])) {
    $ma_san = intval($_GET['doi_trang_thai']);
    $sql = "SELECT TrangThai FROM san WHERE MaSan = $ma_san";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);

    $trang_thai_moi = ($row['TrangThai'] == 'Hoạt động') ? 'Bảo trì' : 'Hoạt động';
    mysqli_query($conn, "UPDATE san SET TrangThai = '$trang_thai_moi' WHERE MaSan = $ma_san");
    header("Location: quan-ly-san.php");
    exit();
}

// Lấy danh sách sân
$result = mysqli_query($conn, "SELECT * FROM san ORDER BY MaSan DESC");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sân - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body { background: #f1f5f9; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #1e293b; color: white; padding: 20px 0; position: fixed; height: 100%; }
        .sidebar h2 { text-align: center; padding: 10px; margin-bottom: 30px; font-size: 20px; border-bottom: 1px solid #334155; padding-bottom: 20px; }
        .sidebar a { display: block; color: #cbd5e1; text-decoration: none; padding: 14px 25px; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background: #334155; color: white; }
        .main { margin-left: 260px; flex: 1; padding: 30px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .header h1 { color: #1e293b; font-size: 26px; }
        .btn { padding: 8px 14px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; color: white; }
        .btn-primary { background: #2563eb; }
        .btn-danger { background: #dc2626; }
        .btn-warning { background: #f59e0b; }
        .card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 25px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; color: #475569; font-weight: 600; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #dcfce7; color: #16a34a; }
        .badge-warning { background: #fef9c3; color: #ca8a04; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #334155; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <a href="index.php">Dashboard</a>
        <a href="quan-ly-san.php" class="active">Quản lý sân</a>
        <a href="quan-ly-lich.php">Quản lý lịch đặt</a>
        <a href="quan-ly-khach.php">Quản lý khách hàng</a>
        <a href="thong-ke.php">Thống kê</a>
        <a href="logout.php">Đăng xuất</a>
    </div>

    <div class="main">
        <div class="header">
            <h1>Quản lý sân</h1>
        </div>

        <!-- Form thêm sân -->
        <div class="card">
            <h2 style="margin-bottom: 20px; color: #1e293b;">Thêm sân mới</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Tên sân</label>
                        <input type="text" name="ten_san" required placeholder="Nhập tên sân">
                    </div>
                    <div class="form-group">
                        <label>Loại sân</label>
                        <input type="text" name="loai_san" value="Sân bóng chuyền tiêu chuẩn">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Trạng thái</label>
                        <select name="trang_thai">
                            <option value="Hoạt động">Hoạt động</option>
                            <option value="Bảo trì">Bảo trì</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Mô tả</label>
                        <input type="text" name="mo_ta" placeholder="Mô tả sân">
                    </div>
                </div>
                <button type="submit" name="them_san" class="btn btn-primary">Thêm sân</button>
            </form>
        </div>

        <!-- Danh sách sân -->
        <div class="card">
            <h2 style="margin-bottom: 20px; color: #1e293b;">Danh sách sân</h2>
            <table>
                <thead>
                    <tr>
                        <th>Mã sân</th>
                        <th>Tên sân</th>
                        <th>Loại sân</th>
                        <th>Trạng thái</th>
                        <th>Mô tả</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                    <tr>
                        <td>#<?php echo $row['MaSan']; ?></td>
                        <td><?php echo $row['TenSan']; ?></td>
                        <td><?php echo $row['LoaiSan']; ?></td>
                        <td>
                            <?php if ($row['TrangThai'] == 'Hoạt động') { ?>
                                <span class="badge badge-success">Hoạt động</span>
                            <?php } else { ?>
                                <span class="badge badge-warning">Bảo trì</span>
                            <?php } ?>
                        </td>
                        <td><?php echo $row['MoTa']; ?></td>
                        <td>
                            <a href="quan-ly-san.php?doi_trang_thai=<?php echo $row['MaSan']; ?>" class="btn btn-warning">Đổi trạng thái</a>
                            <a href="quan-ly-san.php?xoa=<?php echo $row['MaSan']; ?>" class="btn btn-danger" onclick="return confirm('Bạn có chắc muốn xóa sân này?')">Xóa</a>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
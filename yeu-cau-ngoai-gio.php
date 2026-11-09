
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

// Đổi tên này thành file kết nối database thực tế
require_once __DIR__ . "/includes/db.php";

// Kiểm tra đăng nhập
if (!isset($_SESSION['MaND'])) {
    die("Bạn cần đăng nhập trước khi gửi yêu cầu đặt sân.");
}

$maND = (int)$_SESSION['MaND'];

$thongBao = "";
$loaiThongBao = "";

// Lấy danh sách sân đang hoạt động
$sqlSan = "SELECT MaSan, TenSan
           FROM san
           WHERE TrangThai = 'Hoạt động'";

$resultSan = mysqli_query($conn, $sqlSan);

if (!$resultSan) {
    die("Lỗi lấy danh sách sân: " . mysqli_error($conn));
}

// Xử lý khi khách nhấn Gửi yêu cầu
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $maSan = (int)($_POST['MaSan'] ?? 0);
    $ngayDat = $_POST['NgayDat'] ?? '';
    $gioBatDau = $_POST['ThoiGianBatDau'] ?? '';
    $gioKetThuc = $_POST['ThoiGianKetThuc'] ?? '';
    $lyDo = trim($_POST['LyDo'] ?? '');

    if (
        $maSan <= 0 ||
        $ngayDat == '' ||
        $gioBatDau == '' ||
        $gioKetThuc == '' ||
        $lyDo == ''
    ) {
        $thongBao = "Vui lòng nhập đầy đủ thông tin.";
        $loaiThongBao = "error";

    } elseif ($ngayDat < date('Y-m-d')) {

        $thongBao = "Không thể chọn ngày trong quá khứ.";
        $loaiThongBao = "error";

    } elseif ($gioKetThuc <= $gioBatDau) {

        $thongBao = "Giờ kết thúc phải sau giờ bắt đầu.";
        $loaiThongBao = "error";

    } else {

        // Kiểm tra sân có tồn tại và đang hoạt động
        $sqlKiemTra = "SELECT MaSan
                       FROM san
                       WHERE MaSan = ?
                       AND TrangThai = 'Hoạt động'";

        $stmtKiemTra = mysqli_prepare($conn, $sqlKiemTra);

        mysqli_stmt_bind_param(
            $stmtKiemTra,
            "i",
            $maSan
        );

        mysqli_stmt_execute($stmtKiemTra);

        $resultKiemTra = mysqli_stmt_get_result($stmtKiemTra);

        if (mysqli_num_rows($resultKiemTra) == 0) {

            $thongBao = "Sân không tồn tại hoặc không hoạt động.";
            $loaiThongBao = "error";

        } else {

            // Lưu yêu cầu vào database
            $sqlThem = "INSERT INTO yeucau_ngoai_gio
                        (
                            MaND,
                            MaSan,
                            NgayDat,
                            ThoiGianBatDau,
                            ThoiGianKetThuc,
                            LyDo
                        )
                        VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $sqlThem);

            mysqli_stmt_bind_param(
                $stmt,
                "iissss",
                $maND,
                $maSan,
                $ngayDat,
                $gioBatDau,
                $gioKetThuc,
                $lyDo
            );

            if (mysqli_stmt_execute($stmt)) {

                $thongBao = "Gửi yêu cầu thành công! Vui lòng chờ Admin duyệt.";
                $loaiThongBao = "success";

            } else {

                $thongBao = "Gửi yêu cầu thất bại.";
                $loaiThongBao = "error";
            }

            mysqli_stmt_close($stmt);
        }

        mysqli_stmt_close($stmtKiemTra);
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Yêu cầu đặt sân ngoài giờ</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 15px;
            background: #f3f6fb;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 600px;
            margin: auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }

        h2 {
            color: #2563eb;
            text-align: center;
        }

        .mo-ta {
            color: #666;
            text-align: center;
            line-height: 1.6;
        }

        label {
            display: block;
            margin-top: 18px;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-size: 15px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        button {
            width: 100%;
            margin-top: 22px;
            padding: 13px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .thong-bao {
            padding: 12px;
            margin-top: 15px;
            border-radius: 7px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .luu-y {
            margin-top: 20px;
            padding: 13px;
            background: #eff6ff;
            color: #1e40af;
            line-height: 1.6;
            border-radius: 7px;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Yêu cầu đặt sân ngoài giờ</h2>

    <p class="mo-ta">
        Vui lòng điền thông tin để gửi yêu cầu
        đặt sân ngoài khung giờ hoạt động.
    </p>

    <?php if ($thongBao != ''): ?>
        <div class="thong-bao <?= $loaiThongBao ?>">
            <?= htmlspecialchars($thongBao) ?>
        </div>
    <?php endif; ?>

    <div class="luu-y">
        <strong>Lưu ý:</strong>
        Giờ hoạt động thông thường là 06:00–22:00.
        Yêu cầu ngoài giờ cần được Admin xét duyệt.
        Nếu được duyệt, khách hàng cần thanh toán cọc
        theo quy định trước khi lịch được xác nhận.
    </div>

    <form method="POST">

        <label>Chọn sân</label>

        <select name="MaSan" required>
            <option value="">-- Chọn sân --</option>

            <?php
            mysqli_data_seek($resultSan, 0);

            while ($san = mysqli_fetch_assoc($resultSan)):
            ?>
                <option value="<?= $san['MaSan'] ?>">
                    <?= htmlspecialchars($san['TenSan']) ?>
                </option>
            <?php endwhile; ?>

        </select>

        <label>Ngày muốn đặt</label>

        <input
            type="date"
            name="NgayDat"
            min="<?= date('Y-m-d') ?>"
            required
        >

        <label>Giờ bắt đầu</label>

        <input
            type="time"
            name="ThoiGianBatDau"
            required
        >

        <label>Giờ kết thúc</label>

        <input
            type="time"
            name="ThoiGianKetThuc"
            required
        >

        <label>Lý do đặt ngoài giờ</label>

        <textarea
            name="LyDo"
            placeholder="Nhập lý do muốn đặt sân ngoài giờ..."
            required
        ></textarea>

        <button type="submit">
            Gửi yêu cầu
        </button>

    </form>

</div>

</body>
</html>

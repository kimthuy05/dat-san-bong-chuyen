<?php
// customer/lich-su-dat-san.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

mysqli_set_charset($conn, 'utf8mb4');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ========================================
// 1. KIỂM TRA ĐĂNG NHẬP
// ========================================

// Đồng bộ session nếu hệ thống đang dùng ma_tai_khoan
if (!isset($_SESSION['MaND']) && isset($_SESSION['ma_tai_khoan'])) {
    $_SESSION['MaND'] = $_SESSION['ma_tai_khoan'];
}

if (!isset($_SESSION['MaND'])) {
    header('Location: ../login.php');
    exit();
}

if (($_SESSION['VaiTro'] ?? '') !== 'Khách hàng') {
    header('Location: ../login.php');
    exit();
}

$maND = (int)$_SESSION['MaND'];

// ========================================
// 1.1. CSRF TOKEN VÀ XỬ LÝ YÊU CẦU HỦY
// ========================================

if (empty($_SESSION['csrf_huy_lich'])) {
    $_SESSION['csrf_huy_lich'] = bin2hex(random_bytes(32));
}

$thongBao = '';
$loaiThongBao = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gui_yeu_cau_huy'])) {

    $maLichDat = (int)($_POST['MaLichDat'] ?? 0);
    $lyDoHuy = trim($_POST['LyDoHuy'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_huy_lich'], $csrf)) {

        $thongBao = 'Phiên gửi yêu cầu không hợp lệ. Vui lòng tải lại trang!';
        $loaiThongBao = 'error';

    } elseif ($maLichDat <= 0 || $lyDoHuy === '') {

        $thongBao = 'Vui lòng nhập lý do hủy lịch!';
        $loaiThongBao = 'error';

    } elseif (mb_strlen($lyDoHuy, 'UTF-8') > 1000) {

        $thongBao = 'Lý do hủy không được vượt quá 1000 ký tự!';
        $loaiThongBao = 'error';

    } else {

        try {
            $conn->begin_transaction();

            // Khóa lịch đặt để tránh gửi yêu cầu trùng cùng lúc
            $check = $conn->prepare("
                SELECT
                    MaLichDat,
                    TrangThai,
                    NgayDat,
                    COALESCE(
                        ThoiGianBatDau,
                        (
                            SELECT ThoiGianBatDau
                            FROM khunggio
                            WHERE khunggio.MaKhungGio = lichdat.MaKhungGio
                        )
                    ) AS GioBatDau
                FROM lichdat
                WHERE MaLichDat = ?
                  AND MaND = ?
                FOR UPDATE
            ");

            $check->bind_param("ii", $maLichDat, $maND);
            $check->execute();

            $lichCheck = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$lichCheck) {

                throw new Exception('Không tìm thấy lịch đặt của bạn!');

            } elseif (!in_array(
                $lichCheck['TrangThai'],
                ['Đang giữ chỗ', 'Đã đặt'],
                true
            )) {

                throw new Exception('Lịch này không thể gửi yêu cầu hủy!');

            } else {

                $thoiDiemBatDau = strtotime(
                    $lichCheck['NgayDat'] . ' ' . $lichCheck['GioBatDau']
                );

                if (!$thoiDiemBatDau || $thoiDiemBatDau <= time()) {
                    throw new Exception('Lịch đã đến giờ hoặc đã qua giờ chơi, không thể gửi yêu cầu hủy!');
                }

                // Kiểm tra yêu cầu hủy đang chờ
                $checkYeuCau = $conn->prepare("
                    SELECT MaYeuCauHuy
                    FROM yeucau_huy_lich
                    WHERE MaLichDat = ?
                      AND TrangThai = 'Chờ duyệt'
                    LIMIT 1
                ");

                $checkYeuCau->bind_param("i", $maLichDat);
                $checkYeuCau->execute();

                $daCoYeuCau = $checkYeuCau->get_result()->num_rows > 0;
                $checkYeuCau->close();

                if ($daCoYeuCau) {

                    throw new Exception('Lịch này đã có yêu cầu hủy đang chờ Admin xử lý!');

                } else {

                    $insert = $conn->prepare("
                        INSERT INTO yeucau_huy_lich
                            (MaLichDat, MaND, LyDoHuy, TrangThai, NgayGui)
                        VALUES (?, ?, ?, 'Chờ duyệt', NOW())
                    ");

                    $insert->bind_param(
                        "iis",
                        $maLichDat,
                        $maND,
                        $lyDoHuy
                    );

                    $insert->execute();
                    $insert->close();

                    $conn->commit();

                    $thongBao = 'Gửi yêu cầu hủy lịch thành công! Vui lòng chờ Admin xét duyệt.';
                    $loaiThongBao = 'success';
                }
            }

            if ($conn->errno === 0) {
                // Giao dịch đã commit ở nhánh gửi thành công.
            }

        } catch (Throwable $e) {

            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
                // Không cần xử lý thêm
            }

            $thongBao = $e->getMessage();
            $loaiThongBao = 'error';
        }
    }
}

// ========================================
// 2. HÀM HỖ TRỢ
// ========================================

function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function tien($value)
{
    return number_format((float)$value, 0, ',', '.') . 'đ';
}

function badgeLich($status)
{
    $classes = [
        'Đang giữ chỗ' => 'warning',
        'Đã đặt' => 'success',
        'Đã hủy' => 'danger',
        'Hoàn thành' => 'info'
    ];

    $class = $classes[$status] ?? 'secondary';

    return '<span class="badge badge-' . $class . '">' .
        e($status ?: 'Không xác định') .
        '</span>';
}

function badgeCoc($status)
{
    $classes = [
        'Chưa cọc' => 'warning',
        'Đang chờ xác nhận' => 'pending',
        'Đã cọc' => 'success',
        'Đã hoàn cọc' => 'info'
    ];

    $class = $classes[$status] ?? 'secondary';

    return '<span class="badge badge-' . $class . '">' .
        e($status) .
        '</span>';
}

// ========================================
// 3. BỘ LỌC
// ========================================

$trangThaiLoc = $_GET['trang_thai'] ?? '';

if (!is_string($trangThaiLoc)) {
    $trangThaiLoc = '';
}

$cacTrangThai = [
    'Đang giữ chỗ',
    'Đã đặt',
    'Đã hủy',
    'Hoàn thành'
];

$where = "WHERE ld.MaND = ?";

if (in_array($trangThaiLoc, $cacTrangThai, true)) {
    $where .= " AND ld.TrangThai = ?";
}

// ========================================
// 4. LẤY LỊCH SỬ ĐẶT SÂN
// ========================================

$sql = "
    SELECT
        ld.MaLichDat,
        ld.MaND,
        ld.MaSan,
        ld.NgayDat,
        ld.TongTien,
        ld.TrangThai,
        ld.LoaiDat,
        ld.GhiChu,
        ld.NgayTao,

        s.TenSan,

        COALESCE(
            ld.ThoiGianBatDau,
            kg.ThoiGianBatDau
        ) AS GioBatDau,

        COALESCE(
            ld.ThoiGianKetThuc,
            kg.ThoiGianKetThuc
        ) AS GioKetThuc,

        COALESCE(tt.TongCocThanhCong, 0) AS TongCocThanhCong,
        COALESCE(tt.TongCocChoXuLy, 0) AS TongCocChoXuLy,
        COALESCE(tt.TongHoanTien, 0) AS TongHoanTien,

        yh.MaYeuCauHuy,
        yh.LyDoHuy,
        yh.TrangThai AS TrangThaiYeuCauHuy,
        yh.GhiChuAdmin,
        yh.NgayGui,
        yh.NgayXuLy

    FROM lichdat ld

    JOIN san s
        ON ld.MaSan = s.MaSan

    LEFT JOIN khunggio kg
        ON ld.MaKhungGio = kg.MaKhungGio

    LEFT JOIN (
        SELECT
            MaLichDat,

            SUM(
                CASE
                    WHEN LoaiGiaoDich = 'Đặt cọc'
                     AND TrangThaiThanhToan = 'Thành công'
                    THEN SoTien
                    ELSE 0
                END
            ) AS TongCocThanhCong,

            SUM(
                CASE
                    WHEN LoaiGiaoDich = 'Đặt cọc'
                     AND TrangThaiThanhToan = 'Đang xử lý'
                    THEN SoTien
                    ELSE 0
                END
            ) AS TongCocChoXuLy,

            SUM(
                CASE
                    WHEN LoaiGiaoDich = 'Hoàn tiền'
                     AND TrangThaiThanhToan = 'Thành công'
                    THEN SoTien
                    ELSE 0
                END
            ) AS TongHoanTien

        FROM thanhtoan
        GROUP BY MaLichDat
    ) tt
        ON ld.MaLichDat = tt.MaLichDat

            LEFT JOIN (
        SELECT y.*
        FROM yeucau_huy_lich y
        INNER JOIN (
            SELECT
                MaLichDat,
                MAX(MaYeuCauHuy) AS MaMoiNhat
            FROM yeucau_huy_lich
            GROUP BY MaLichDat
        ) moi
            ON y.MaYeuCauHuy = moi.MaMoiNhat
    ) yh
        ON ld.MaLichDat = yh.MaLichDat

    $where

    ORDER BY ld.NgayTao DESC, ld.MaLichDat DESC
";

$stmt = $conn->prepare($sql);

if ($trangThaiLoc !== '' && in_array($trangThaiLoc, $cacTrangThai, true)) {
    $stmt->bind_param('is', $maND, $trangThaiLoc);
} else {
    $stmt->bind_param('i', $maND);
}

$stmt->execute();

$result = $stmt->get_result();

$danhSachLich = [];

while ($row = $result->fetch_assoc()) {
    $danhSachLich[] = $row;
}

$stmt->close();

// ========================================
// 5. TÍNH TRẠNG THÁI CỌC
// ========================================

foreach ($danhSachLich as &$lich) {
    $tongCoc = (float)$lich['TongCocThanhCong'];
    $tongChoXuLy = (float)$lich['TongCocChoXuLy'];
    $tongHoanTien = (float)$lich['TongHoanTien'];

    if ($tongHoanTien > 0) {
        $lich['TrangThaiCoc'] = 'Đã hoàn cọc';
    } elseif ($tongCoc > 0) {
        $lich['TrangThaiCoc'] = 'Đã cọc';
    } elseif ($tongChoXuLy > 0) {
        $lich['TrangThaiCoc'] = 'Đang chờ xác nhận';
    } else {
        $lich['TrangThaiCoc'] = 'Chưa cọc';
    }

    $lich['TienConLai'] = max(
        0,
        (float)$lich['TongTien'] - $tongCoc
    );
}
unset($lich);

// ========================================
// 6. THỐNG KÊ
// ========================================

$tongLich = count($danhSachLich);
$soLichDaDat = 0;
$soLichCho = 0;
$soLichDaHuy = 0;

foreach ($danhSachLich as $lich) {
    if ($lich['TrangThai'] === 'Đã đặt') {
        $soLichDaDat++;
    }

    if ($lich['TrangThai'] === 'Đang giữ chỗ') {
        $soLichCho++;
    }

    if ($lich['TrangThai'] === 'Đã hủy') {
        $soLichDaHuy++;
    }
}

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Lịch sử đặt sân</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: 27px;
            margin-bottom: 7px;
        }

        .header p {
            color: #64748b;
            font-size: 14px;
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 7px;
            border: none;
            text-decoration: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .stat-card p {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .stat-card h2 {
            font-size: 25px;
        }

        .filter {
            background: white;
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter select {
            padding: 10px 13px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            min-width: 210px;
            background: white;
        }

        .booking-card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .booking-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .booking-code {
            font-weight: bold;
            font-size: 17px;
        }

        .booking-content {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px 25px;
        }

        .info-item {
            font-size: 14px;
            color: #64748b;
        }

        .info-item strong {
            display: block;
            color: #334155;
            margin-bottom: 6px;
        }

        .payment-box {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin-top: 18px;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin: 8px 0;
            font-size: 14px;
        }

        .payment-row.total {
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            margin-top: 12px;
            font-weight: bold;
            color: #1e293b;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-warning {
            background: #fef9c3;
            color: #a16207;
        }

        .badge-danger {
            background: #fee2e2;
            color: #b91c1c;
        }

        .badge-info {
            background: #e0f2fe;
            color: #0369a1;
        }

        .badge-pending {
            background: #ffedd5;
            color: #c2410c;
        }

        .badge-secondary {
            background: #e2e8f0;
            color: #475569;
        }

        .empty {
            background: white;
            text-align: center;
            padding: 55px 20px;
            border-radius: 12px;
            color: #64748b;
        }

        .empty h3 {
            margin-bottom: 10px;
            color: #334155;
        }

        .empty a {
            margin-top: 18px;
        }

        @media (max-width: 768px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .booking-content {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 22px;
            }
        }

        @media (max-width: 420px) {
            .stats {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .stat-card {
                padding: 13px;
            }

            .stat-card h2 {
                font-size: 21px;
            }

            .booking-card {
                padding: 15px;
            }

            .alert {
    padding: 14px 18px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    font-weight: 600;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
}

.alert-error {
    background: #fee2e2;
    color: #b91c1c;
}

.cancel-box {
    margin-top: 18px;
    padding: 16px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    border-radius: 9px;
}

.cancel-box h3 {
    font-size: 15px;
    margin-bottom: 10px;
    color: #9a3412;
}

.cancel-box textarea {
    width: 100%;
    min-height: 85px;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    resize: vertical;
    margin: 10px 0;
}

.btn-danger {
    background: #dc2626;
    color: white;
}

.btn-danger:hover {
    background: #b91c1c;
}

.cancel-info {
    margin-top: 15px;
    padding: 13px;
    background: #f8fafc;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.7;
}
        }
    </style>
</head>

<body>

<div class="container">

<?php if ($thongBao !== ''): ?>
    <div class="alert <?= $loaiThongBao === 'success' ? 'alert-success' : 'alert-error' ?>">
        <?= e($thongBao) ?>
    </div>
<?php endif; ?>

    <div class="header">
        <div>
            <h1>Lịch sử đặt sân</h1>
            <p>Theo dõi các lịch đặt sân và tình trạng thanh toán của bạn.</p>
        </div>

        <a href="booking_demo.php" class="btn btn-primary">
            + Đặt sân mới
        </a>
    </div>

    <!-- THỐNG KÊ -->

    <div class="stats">

        <div class="stat-card">
            <p>Tổng lịch đặt</p>
            <h2><?php echo $tongLich; ?></h2>
        </div>

        <div class="stat-card">
            <p>Đã đặt</p>
            <h2><?php echo $soLichDaDat; ?></h2>
        </div>

        <div class="stat-card">
            <p>Đang giữ chỗ</p>
            <h2><?php echo $soLichCho; ?></h2>
        </div>

        <div class="stat-card">
            <p>Đã hủy</p>
            <h2><?php echo $soLichDaHuy; ?></h2>
        </div>

    </div>

    <!-- BỘ LỌC -->

    <form method="GET" class="filter">

        <select name="trang_thai" onchange="this.form.submit()">

            <option value="">Tất cả lịch đặt</option>

            <?php foreach ($cacTrangThai as $status) { ?>
                <option
                    value="<?php echo e($status); ?>"
                    <?php echo $trangThaiLoc === $status ? 'selected' : ''; ?>
                >
                    <?php echo e($status); ?>
                </option>
            <?php } ?>

        </select>

        <a href="lich-su-dat-san.php" class="btn btn-primary">
            Xóa bộ lọc
        </a>

    </form>

    <!-- DANH SÁCH LỊCH -->

    <?php if (count($danhSachLich) > 0) { ?>

        <?php foreach ($danhSachLich as $lich) { ?>

            <div class="booking-card">

                <div class="booking-top">

                    <div class="booking-code">
                        Mã lịch đặt #<?php echo (int)$lich['MaLichDat']; ?>
                    </div>

                    <div>
                        <?php echo badgeLich($lich['TrangThai']); ?>
                        <?php echo badgeCoc($lich['TrangThaiCoc']); ?>
                    </div>

                </div>

                <div class="booking-content">

                    <div class="info-item">
                        <strong>Sân</strong>
                        <?php echo e($lich['TenSan']); ?>
                    </div>

                    <div class="info-item">
                        <strong>Loại đặt</strong>
                        <?php echo e($lich['LoaiDat'] ?: 'Đặt thường'); ?>
                    </div>

                    <div class="info-item">
                        <strong>Ngày chơi</strong>
                        <?php
                        echo !empty($lich['NgayDat'])
                            ? e(date('d/m/Y', strtotime($lich['NgayDat'])))
                            : '—';
                        ?>
                    </div>

                    <div class="info-item">
                        <strong>Giờ chơi</strong>

                        <?php
                        echo !empty($lich['GioBatDau'])
                            ? e(substr($lich['GioBatDau'], 0, 5))
                            : '--:--';
                        ?>

                        -

                        <?php
                        echo !empty($lich['GioKetThuc'])
                            ? e(substr($lich['GioKetThuc'], 0, 5))
                            : '--:--';
                        ?>
                    </div>

                    <div class="info-item">
                        <strong>Ngày tạo lịch</strong>

                        <?php
                        echo !empty($lich['NgayTao'])
                            ? e(date('d/m/Y H:i', strtotime($lich['NgayTao'])))
                            : '—';
                        ?>
                    </div>

                    <div class="info-item">
                        <strong>Ghi chú</strong>
                        <?php echo e($lich['GhiChu'] ?: 'Không có'); ?>
                    </div>

                </div>

                <div class="payment-box">

                    <div class="payment-row">
                        <span>Tổng tiền sân</span>
                        <strong><?php echo tien($lich['TongTien']); ?></strong>
                    </div>

                    <div class="payment-row">
                        <span>Tiền cọc đã thanh toán</span>
                        <strong><?php echo tien($lich['TongCocThanhCong']); ?></strong>
                    </div>

                    <?php if ((float)$lich['TongCocChoXuLy'] > 0) { ?>
                        <div class="payment-row">
                            <span>Tiền cọc đang chờ Admin xác nhận</span>
                            <strong>
                                <?php echo tien($lich['TongCocChoXuLy']); ?>
                            </strong>
                        </div>
                    <?php } ?>

                    <?php if ((float)$lich['TongHoanTien'] > 0) { ?>
                        <div class="payment-row">
                            <span>Tiền đã hoàn</span>
                            <strong>
                                <?php echo tien($lich['TongHoanTien']); ?>
                            </strong>
                        </div>
                    <?php } ?>

                    <div class="payment-row total">
                        <span>Số tiền còn lại theo tiền cọc</span>
                        <strong><?php echo tien($lich['TienConLai']); ?></strong>
                    </div>

<?php
$checkYeuCauHuy = $lich['TrangThaiYeuCauHuy'] ?? '';

$duocGuiYeuCauHuy = in_array(
    $lich['TrangThai'],
    ['Đang giữ chỗ', 'Đã đặt'],
    true
);
?>

<?php if ($checkYeuCauHuy === 'Chờ duyệt'): ?>

    <div class="cancel-info">
        <strong>Yêu cầu hủy lịch đang chờ Admin duyệt.</strong>
        <p>Lý do: <?= e($lich['LyDoHuy'] ?? '') ?></p>
    </div>

<?php elseif ($duocGuiYeuCauHuy): ?>

    <div class="cancel-box">

        <h3>Yêu cầu hủy lịch đặt #<?= (int)$lich['MaLichDat'] ?></h3>

        <form method="POST">

            <input
                type="hidden"
                name="MaLichDat"
                value="<?= (int)$lich['MaLichDat'] ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_huy_lich']) ?>"
            >

            <textarea
                name="LyDoHuy"
                required
                maxlength="1000"
                placeholder="Nhập lý do bạn muốn hủy lịch..."
            ></textarea>

            <button
                type="submit"
                name="gui_yeu_cau_huy"
                value="1"
                class="btn btn-danger"
                onclick="return confirm('Bạn có chắc muốn gửi yêu cầu hủy lịch này không?')"
            >
                Gửi yêu cầu hủy
            </button>

        </form>

    </div>

<?php endif; ?>

                </div>

            </div>

        <?php } ?>

                        <!-- YÊU CẦU HỦY LỊCH -->

                <?php
                $coTheGuiYeuCau = in_array(
                    $lich['TrangThai'],
                    ['Đang giữ chỗ', 'Đã đặt'],
                    true
                );

                $gioBatDauHuy = strtotime(
                    $lich['NgayDat'] . ' ' . ($lich['GioBatDau'] ?? '00:00:00')
                );

                $lichConTuongLai = $gioBatDauHuy && $gioBatDauHuy > time();

                $dangChoDuyet = ($lich['TrangThaiYeuCauHuy'] ?? '') === 'Chờ duyệt';
                ?>

                <?php if (!empty($lich['MaYeuCauHuy'])): ?>

                    <div class="cancel-info">

                        <strong>Thông tin yêu cầu hủy lịch</strong>

                        <p>
                            Trạng thái:
                            <?php
                            if ($lich['TrangThaiYeuCauHuy'] === 'Chờ duyệt') {
                                echo '<span class="badge badge-warning">Chờ Admin duyệt</span>';
                            } elseif ($lich['TrangThaiYeuCauHuy'] === 'Đã duyệt') {
                                echo '<span class="badge badge-success">Đã duyệt hủy</span>';
                            } else {
                                echo '<span class="badge badge-danger">Đã từ chối</span>';
                            }
                            ?>
                        </p>

                        <p>
                            <strong>Lý do khách gửi:</strong>
                            <?= e($lich['LyDoHuy']) ?>
                        </p>

                        <?php if (!empty($lich['GhiChuAdmin'])): ?>
                            <p>
                                <strong>Phản hồi của Admin:</strong>
                                <?= e($lich['GhiChuAdmin']) ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($lich['NgayGui'])): ?>
                            <p>
                                <strong>Ngày gửi:</strong>
                                <?= e(date('d/m/Y H:i', strtotime($lich['NgayGui']))) ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($lich['TrangThaiYeuCauHuy'] === 'Đã duyệt'): ?>
                            <p>
                                <strong>Trạng thái lịch:</strong>
                                <?= e($lich['TrangThai']) ?>
                            </p>

                            <p>
                                <strong>Trạng thái tiền cọc:</strong>
                                <?= e($lich['TrangThaiCoc']) ?>
                            </p>
                        <?php endif; ?>

                    </div>

                <?php endif; ?>

                <?php if (
                    $coTheGuiYeuCau &&
                    $lichConTuongLai &&
                    !$dangChoDuyet
                ): ?>

                    <div class="cancel-box">

                        <h3>Gửi yêu cầu hủy lịch đặt</h3>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="MaLichDat"
                                value="<?= (int)$lich['MaLichDat'] ?>"
                            >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($_SESSION['csrf_huy_lich']) ?>"
                            >

                            <textarea
                                name="LyDoHuy"
                                required
                                maxlength="1000"
                                placeholder="Nhập lý do bạn muốn hủy lịch..."
                            ></textarea>

                            <button
                                type="submit"
                                name="gui_yeu_cau_huy"
                                value="1"
                                class="btn btn-danger"
                                onclick="return confirm('Bạn có chắc muốn gửi yêu cầu hủy lịch này không?');"
                            >
                                Gửi yêu cầu hủy
                            </button>

                        </form>

                        <p style="font-size:12px;color:#9a3412;margin-top:9px;">
                            Yêu cầu cần được Admin xét duyệt. Lịch chưa bị hủy ngay khi gửi.
                        </p>

                    </div>

                <?php elseif ($dangChoDuyet): ?>

                    <div class="cancel-info">
                        Yêu cầu hủy lịch đang chờ Admin xử lý. Bạn chưa thể gửi yêu cầu khác cho lịch này.
                    </div>

                <?php endif; ?>

    <?php } else { ?>

        <div class="empty">

            <h3>Chưa có lịch đặt sân</h3>

            <p>
                <?php
                echo $trangThaiLoc !== ''
                    ? 'Không tìm thấy lịch đặt phù hợp với bộ lọc.'
                    : 'Bạn chưa thực hiện đặt sân lần nào.';
                ?>
            </p>

            <a href="booking_demo.php" class="btn btn-primary">
                Đặt sân ngay
            </a>

        </div>

    <?php } ?>

</div>

</body>
</html>
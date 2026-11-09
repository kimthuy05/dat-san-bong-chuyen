
<?php
// customer/lich-su-yeu-cau.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../includes/db.php";

mysqli_set_charset($conn, "utf8mb4");

// ========================================
// 1. KIỂM TRA ĐĂNG NHẬP
// ========================================

if (!isset($_SESSION['MaND']) && isset($_SESSION['ma_tai_khoan'])) {
    $_SESSION['MaND'] = $_SESSION['ma_tai_khoan'];
}

if (!isset($_SESSION['MaND'])) {
    header("Location: ../login.php");
    exit();
}

if (($_SESSION['VaiTro'] ?? '') === 'Admin') {
    header("Location: ../admin/index.php");
    exit();
}

$maND = (int)$_SESSION['MaND'];

// ========================================
// 2. HÀM HỖ TRỢ
// ========================================

function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function tinhTienDuKien($row)
{
    $tongTien = (float)($row['TongTienDuKien'] ?? 0);

    if ($tongTien > 0) {
        return $tongTien;
    }

    $batDau = strtotime($row['ThoiGianBatDau'] ?? '');
    $ketThuc = strtotime($row['ThoiGianKetThuc'] ?? '');

    if (!$batDau || !$ketThuc || $ketThuc <= $batDau) {
        return 0;
    }

    $soGio = (int)ceil(($ketThuc - $batDau) / 3600);

    return $soGio * 50000;
}

// ========================================
// 3. LẤY LỊCH SỬ VÀ TRẠNG THÁI THANH TOÁN
// ========================================

$sql = "
    SELECT
        yc.*,
        s.TenSan,

        COALESCE(tt.TongCocThanhCong, 0) AS TongCocThanhCong,
        COALESCE(tt.TongCocChoXuLy, 0) AS TongCocChoXuLy

    FROM yeucau_ngoai_gio yc

    INNER JOIN san s
        ON yc.MaSan = s.MaSan

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
            ) AS TongCocChoXuLy

        FROM thanhtoan
        GROUP BY MaLichDat
    ) tt
        ON yc.MaLichDat = tt.MaLichDat

    WHERE yc.MaND = ?

    ORDER BY yc.NgayTao DESC, yc.MaYeuCau DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Không thể tải lịch sử yêu cầu: " . h($conn->error));
}

$stmt->bind_param("i", $maND);
$stmt->execute();

$result = $stmt->get_result();

// ========================================
// 4. THỐNG KÊ
// ========================================

$tongYeuCau = 0;
$choDuyet = 0;
$daDuyet = 0;
$tuChoi = 0;

$dsYeuCau = [];

while ($row = $result->fetch_assoc()) {

    $dsYeuCau[] = $row;
    $tongYeuCau++;

    $trangThai = $row['TrangThaiYeuCau'];
    $daCoc = (float)$row['TongCocThanhCong'] > 0;

    if ($trangThai === 'Chờ duyệt') {
        $choDuyet++;

    } elseif (
        $daCoc ||
        in_array(
            $trangThai,
            ['Đã duyệt', 'Chờ thanh toán cọc', 'Đã đặt'],
            true
        )
    ) {
        $daDuyet++;

    } elseif (
        in_array(
            $trangThai,
            ['Đã từ chối', 'Từ chối'],
            true
        )
    ) {
        $tuChoi++;
    }
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Lịch sử yêu cầu ngoài giờ</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #1e293b;
        }

        .container {
            max-width: 1150px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .back {
            display: inline-block;
            color: #2563eb;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: bold;
        }

        h1 {
            margin-bottom: 8px;
        }

        .subtitle {
            color: #64748b;
            margin-bottom: 25px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
        }

        .stat span {
            display: block;
            color: #64748b;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .stat strong {
            font-size: 25px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .card-header h3 {
            margin: 0;
        }

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            background: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background: #dcfce7;
            color: #166534;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-pending-payment {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-paid {
            background: #dcfce7;
            color: #166534;
        }

        .status-processing {
            background: #fef3c7;
            color: #92400e;
        }

        .info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        .info-item {
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .info-item span {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .info-item strong {
            overflow-wrap: anywhere;
        }

        .note {
            margin-top: 15px;
            padding: 13px;
            border-radius: 8px;
            background: #f8fafc;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .money {
            color: #15803d;
        }

        .empty {
            text-align: center;
            padding: 45px 20px;
            background: white;
            border-radius: 12px;
        }

        .empty p {
            color: #64748b;
        }

        .btn {
            display: inline-block;
            margin-top: 15px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: bold;
        }

        .btn-payment {
            display: inline-block;
            margin-top: 15px;
            background: #059669;
            color: white;
            text-decoration: none;
            padding: 14px 20px;
            border-radius: 9px;
            font-weight: bold;
            font-size: 13px;
            transition: .2s;
        }

        .btn-payment:hover {
            background: #047857;
        }

        .payment-warning {
            color: #92400e;
            background: #fffbeb;
            border: 1px solid #fde68a;
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 13px;
            line-height: 1.6;
        }

        .payment-success {
            color: #166534;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            padding: 13px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 13px;
            line-height: 1.7;
        }

        .date-created {
            color: #94a3b8;
            font-size: 12px;
            margin-top: 15px;
        }

        @media (max-width: 700px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .info {
                grid-template-columns: 1fr;
            }

            .container {
                margin-top: 20px;
                padding: 0 12px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <a href="booking_demo.php" class="back">
        ← Quay lại đặt sân
    </a>

    <h1>Lịch sử yêu cầu ngoài giờ</h1>

    <p class="subtitle">
        Theo dõi tình trạng yêu cầu và thanh toán cọc đặt sân ngoài giờ.
    </p>

    <!-- THỐNG KÊ -->

    <div class="stats">

        <div class="stat">
            <span>Tổng yêu cầu</span>
            <strong><?= $tongYeuCau ?></strong>
        </div>

        <div class="stat">
            <span>Chờ duyệt</span>
            <strong><?= $choDuyet ?></strong>
        </div>

        <div class="stat">
            <span>Được duyệt / đã cọc</span>
            <strong><?= $daDuyet ?></strong>
        </div>

        <div class="stat">
            <span>Đã từ chối</span>
            <strong><?= $tuChoi ?></strong>
        </div>

    </div>

    <!-- DANH SÁCH YÊU CẦU -->

    <?php if (count($dsYeuCau) > 0): ?>

        <?php foreach ($dsYeuCau as $row): ?>

            <?php

            $trangThaiGoc = $row['TrangThaiYeuCau'];

            $tongCocThanhCong = (float)($row['TongCocThanhCong'] ?? 0);
            $tongCocChoXuLy = (float)($row['TongCocChoXuLy'] ?? 0);

            $daCoc = $tongCocThanhCong > 0;
            $dangChoXacNhan = $tongCocChoXuLy > 0;

            // Ưu tiên trạng thái giao dịch thực tế
            if ($daCoc) {
                $trangThaiHienThi = 'Đã cọc';
                $classStatus = 'status-paid';

            } elseif ($dangChoXacNhan) {
                $trangThaiHienThi = 'Đang chờ Admin xác nhận cọc';
                $classStatus = 'status-processing';

            } else {
                $trangThaiHienThi = $trangThaiGoc;
                $classStatus = '';

                if (in_array(
                    $trangThaiGoc,
                    ['Đã từ chối', 'Từ chối'],
                    true
                )) {
                    $classStatus = 'status-rejected';

                } elseif ($trangThaiGoc === 'Chờ thanh toán cọc') {
                    $classStatus = 'status-pending-payment';

                } elseif (in_array(
                    $trangThaiGoc,
                    ['Đã duyệt', 'Đã đặt'],
                    true
                )) {
                    $classStatus = 'status-approved';
                }
            }

            $tongTien = tinhTienDuKien($row);
            $tienCoc = round($tongTien * 0.5);

            $maLichDat = (int)($row['MaLichDat'] ?? 0);

            ?>

            <div class="card">

                <div class="card-header">

                    <h3>
                        Yêu cầu #<?= (int)$row['MaYeuCau'] ?>
                    </h3>

                    <span class="status <?= h($classStatus) ?>">
                        <?= h($trangThaiHienThi) ?>
                    </span>

                </div>

                <!-- THÔNG TIN YÊU CẦU -->

                <div class="info">

                    <div class="info-item">
                        <span>Sân</span>
                        <strong><?= h($row['TenSan']) ?></strong>
                    </div>

                    <div class="info-item">
                        <span>Ngày đặt</span>
                        <strong>
                            <?= !empty($row['NgayDat'])
                                ? date('d/m/Y', strtotime($row['NgayDat']))
                                : 'Chưa có thông tin' ?>
                        </strong>
                    </div>

                    <div class="info-item">
                        <span>Giờ bắt đầu</span>
                        <strong>
                            <?= h(substr($row['ThoiGianBatDau'] ?? '', 0, 5)) ?>
                        </strong>
                    </div>

                    <div class="info-item">
                        <span>Giờ kết thúc</span>
                        <strong>
                            <?= h(substr($row['ThoiGianKetThuc'] ?? '', 0, 5)) ?>
                        </strong>
                    </div>

                    <div class="info-item">
                        <span>Tổng tiền dự kiến / đã tính</span>
                        <strong class="money">
                            <?= number_format($tongTien, 0, ',', '.') ?>đ
                        </strong>
                    </div>

                    <div class="info-item">
                        <span>Tiền cọc 50%</span>
                        <strong class="money">
                            <?= number_format($tienCoc, 0, ',', '.') ?>đ
                        </strong>
                    </div>

                    <?php if ($daCoc): ?>
                        <div class="info-item">
                            <span>Đã thanh toán cọc</span>
                            <strong class="money">
                                <?= number_format(
                                    $tongCocThanhCong,
                                    0,
                                    ',',
                                    '.'
                                ) ?>đ
                            </strong>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- LÝ DO -->

                <div class="note">
                    <strong>Lý do đặt sân:</strong><br>
                    <?= nl2br(h($row['LyDo'])) ?>
                </div>

                <!-- PHẢN HỒI ADMIN -->

                <?php if (!empty($row['GhiChuAdmin'])): ?>

                    <div class="note">
                        <strong>Phản hồi từ Admin:</strong><br>
                        <?= nl2br(h($row['GhiChuAdmin'])) ?>
                    </div>

                <?php endif; ?>

                <!-- TRẠNG THÁI VÀ THANH TOÁN -->

                <?php if ($daCoc): ?>

                    <div class="payment-success">
                        <strong>✓ Bạn đã thanh toán tiền cọc thành công!</strong><br>

                        Số tiền đã cọc:
                        <strong>
                            <?= number_format(
                                $tongCocThanhCong,
                                0,
                                ',',
                                '.'
                            ) ?>đ
                        </strong>
                        <br>

                        Lịch đặt ngoài giờ của bạn đã được ghi nhận.
                        Bạn không cần thanh toán cọc thêm lần nữa.
                    </div>

                <?php elseif ($dangChoXacNhan): ?>

                    <div class="payment-warning">
                        <strong>Đã nhận thông tin giao dịch cọc.</strong><br>
                        Giao dịch đang chờ Admin kiểm tra và xác nhận.
                        Bạn chưa cần thanh toán lại.
                    </div>

                <?php elseif ($trangThaiGoc === 'Chờ duyệt'): ?>

                    <div class="note">
                        Yêu cầu của bạn đang được Admin xem xét.
                        Vui lòng chờ kết quả xử lý.
                    </div>

                <?php elseif ($trangThaiGoc === 'Chờ thanh toán cọc'): ?>

                    <div class="payment-warning">
                        <strong>Yêu cầu đã được Admin duyệt!</strong><br>
                        Bạn cần thanh toán tiền cọc 50% qua mã QR
                        để hoàn tất việc đặt sân.
                    </div>

                    <?php if ($maLichDat > 0): ?>

                        <a
                            href="qr_payment.php?id=<?= $maLichDat ?>"
                            class="btn-payment"
                        >
                            THANH TOÁN CỌC 50% QUA QR
                        </a>

                    <?php else: ?>

                        <div class="payment-warning">
                            Yêu cầu đã được duyệt nhưng chưa liên kết
                            với mã lịch đặt. Vui lòng liên hệ Admin
                            để kiểm tra.
                        </div>

                    <?php endif; ?>

                <?php elseif (in_array(
                    $trangThaiGoc,
                    ['Đã từ chối', 'Từ chối'],
                    true
                )): ?>

                    <div class="note">
                        Yêu cầu chưa được chấp nhận.
                        Bạn có thể liên hệ Admin để được giải đáp.
                    </div>

                <?php elseif (in_array(
                    $trangThaiGoc,
                    ['Đã đặt', 'Đã duyệt'],
                    true
                )): ?>

                    <div class="note">
                        Lịch đặt của bạn đã được xác nhận.
                    </div>

                <?php endif; ?>

                <!-- NGÀY GỬI -->

                <div class="date-created">
                    Ngày gửi:
                    <?= !empty($row['NgayTao'])
                        ? date('d/m/Y H:i', strtotime($row['NgayTao']))
                        : 'Chưa có thông tin' ?>
                </div>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <div class="empty">

            <h3>Bạn chưa có yêu cầu nào</h3>

            <p>
                Khi gửi yêu cầu đặt sân ngoài giờ,
                thông tin sẽ xuất hiện tại đây.
            </p>

            <a href="yeu-cau-ngoai-gio.php" class="btn">
                Gửi yêu cầu ngoài giờ
            </a>

        </div>

    <?php endif; ?>

</div>

</body>
</html>

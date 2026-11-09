
<?php
// =====================================================
// ADMIN - QUẢN LÝ YÊU CẦU ĐẶT SÂN NGOÀI GIỜ
// File: admin/quan-ly-yeu-cau.php
// =====================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

mysqli_set_charset($conn, 'utf8mb4');

// Bắt lỗi MySQL bằng Exception
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// =====================================================
// 1. KIỂM TRA QUYỀN ADMIN
// =====================================================

if (!isset($_SESSION['MaND']) && isset($_SESSION['ma_tai_khoan'])) {
    $_SESSION['MaND'] = $_SESSION['ma_tai_khoan'];
}

if (
    !isset($_SESSION['MaND']) ||
    ($_SESSION['VaiTro'] ?? '') !== 'Admin'
) {
    header('Location: ../login.php?role=admin');
    exit();
}

// =====================================================
// 2. CẤU HÌNH
// =====================================================

$GIA_NGOAI_GIO = 50000;

$thongBao = '';
$loaiThongBao = '';

function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// =====================================================
// 3. CSRF TOKEN
// =====================================================

if (empty($_SESSION['csrf_ngoai_gio'])) {
    $_SESSION['csrf_ngoai_gio'] = bin2hex(random_bytes(32));
}

// =====================================================
// 4. XỬ LÝ FORM DUYỆT / TỪ CHỐI
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf = $_POST['csrf_token'] ?? '';

    if (
        empty($csrf) ||
        !hash_equals($_SESSION['csrf_ngoai_gio'], $csrf)
    ) {
        $thongBao = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
        $loaiThongBao = 'error';

    } else {

        $maYeuCau = (int)($_POST['MaYeuCau'] ?? 0);
        $hanhDong = $_POST['hanhDong'] ?? '';
        $ghiChuAdmin = trim($_POST['GhiChuAdmin'] ?? '');

        try {

            if ($maYeuCau <= 0) {
                throw new Exception('Mã yêu cầu không hợp lệ.');
            }

            // =================================================
            // 4.1. TỪ CHỐI YÊU CẦU
            // =================================================

            if ($hanhDong === 'tu_choi') {

                $sql = "
                    UPDATE yeucau_ngoai_gio
                    SET
                        TrangThaiYeuCau = 'Đã từ chối',
                        GhiChuAdmin = ?,
                        NgayXuLy = NOW()
                    WHERE MaYeuCau = ?
                      AND TrangThaiYeuCau = 'Chờ duyệt'
                ";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    'si',
                    $ghiChuAdmin,
                    $maYeuCau
                );

                mysqli_stmt_execute($stmt);

                $soDong = mysqli_stmt_affected_rows($stmt);

                mysqli_stmt_close($stmt);

                if ($soDong > 0) {
                    $thongBao = 'Đã từ chối yêu cầu đặt sân ngoài giờ.';
                    $loaiThongBao = 'success';
                } else {
                    throw new Exception(
                        'Yêu cầu không tồn tại hoặc đã được xử lý trước đó.'
                    );
                }

            }

            // =================================================
            // 4.2. DUYỆT YÊU CẦU
            // =================================================

            elseif ($hanhDong === 'duyet') {

                mysqli_begin_transaction($conn);

                // ---------------------------------------------
                // LẤY YÊU CẦU VÀ KHÓA DÒNG
                // ---------------------------------------------

                $sql = "
                    SELECT *
                    FROM yeucau_ngoai_gio
                    WHERE MaYeuCau = ?
                    FOR UPDATE
                ";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    'i',
                    $maYeuCau
                );

                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);
                $yeuCau = mysqli_fetch_assoc($result);

                mysqli_stmt_close($stmt);

                if (!$yeuCau) {
                    throw new Exception('Không tìm thấy yêu cầu.');
                }

                if ($yeuCau['TrangThaiYeuCau'] !== 'Chờ duyệt') {
                    throw new Exception(
                        'Yêu cầu này đã được xử lý. Không thể duyệt lại.'
                    );
                }

                // ---------------------------------------------
                // LẤY THÔNG TIN YÊU CẦU
                // ---------------------------------------------

                $maND = (int)$yeuCau['MaND'];
                $maSan = (int)$yeuCau['MaSan'];

                $ngayDat = $yeuCau['NgayDat'];
                $gioBatDau = $yeuCau['ThoiGianBatDau'];
                $gioKetThuc = $yeuCau['ThoiGianKetThuc'];

                if (
                    empty($ngayDat) ||
                    empty($gioBatDau) ||
                    empty($gioKetThuc)
                ) {
                    throw new Exception('Thông tin ngày hoặc giờ không hợp lệ.');
                }

                // ---------------------------------------------
                // KIỂM TRA NGÀY ĐẶT
                // ---------------------------------------------

                if ($ngayDat < date('Y-m-d')) {
                    throw new Exception('Ngày đặt sân đã qua.');
                }

                // ---------------------------------------------
                // KIỂM TRA THỜI GIAN
                // ---------------------------------------------

                $batDau = strtotime('1970-01-01 ' . $gioBatDau);
                $ketThuc = strtotime('1970-01-01 ' . $gioKetThuc);

                if ($batDau === false || $ketThuc === false) {
                    throw new Exception('Định dạng giờ không hợp lệ.');
                }

                if ($ketThuc <= $batDau) {
                    throw new Exception(
                        'Giờ kết thúc phải sau giờ bắt đầu.'
                    );
                }

                $soGiay = $ketThuc - $batDau;
                $soGio = (int)ceil($soGiay / 3600);

                if ($soGio <= 0) {
                    throw new Exception('Thời gian thuê không hợp lệ.');
                }

                // ---------------------------------------------
                // TÍNH TIỀN
                // ---------------------------------------------

                $tongTien = $soGio * $GIA_NGOAI_GIO;
                $tienCoc = $tongTien * 0.5;

                // ---------------------------------------------
                // KIỂM TRA SÂN CÒN HOẠT ĐỘNG
                // ---------------------------------------------

                $sql = "
                    SELECT MaSan
                    FROM san
                    WHERE MaSan = ?
                      AND TrangThai = 'Hoạt động'
                    FOR UPDATE
                ";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    'i',
                    $maSan
                );

                mysqli_stmt_execute($stmt);

                $resultSan = mysqli_stmt_get_result($stmt);
                $sanHopLe = mysqli_fetch_assoc($resultSan);

                mysqli_stmt_close($stmt);

                if (!$sanHopLe) {
                    throw new Exception('Sân hiện không hoạt động.');
                }

                // ---------------------------------------------
                // KIỂM TRA TRÙNG LỊCH ĐẶT
                // ---------------------------------------------

                $sql = "
                    SELECT ld.MaLichDat
                    FROM lichdat ld
                    LEFT JOIN khunggio kg
                        ON ld.MaKhungGio = kg.MaKhungGio
                    WHERE ld.MaSan = ?
                      AND ld.NgayDat = ?
                      AND ld.TrangThai IN (
                          'Đang giữ chỗ',
                          'Đã đặt'
                      )
                      AND COALESCE(
                            ld.ThoiGianBatDau,
                            kg.ThoiGianBatDau
                      ) < ?
                      AND COALESCE(
                            ld.ThoiGianKetThuc,
                            kg.ThoiGianKetThuc
                      ) > ?
                    LIMIT 1
                    FOR UPDATE
                ";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    'isss',
                    $maSan,
                    $ngayDat,
                    $gioKetThuc,
                    $gioBatDau
                );

                mysqli_stmt_execute($stmt);

                $resultTrung = mysqli_stmt_get_result($stmt);
                $lichTrung = mysqli_fetch_assoc($resultTrung);

                mysqli_stmt_close($stmt);

                if ($lichTrung) {
                    throw new Exception(
                        'Khung giờ này đã có lịch đặt hoặc đang được giữ chỗ.'
                    );
                }

                // ---------------------------------------------
                // KIỂM TRA TRÙNG YÊU CẦU NGOÀI GIỜ KHÁC
                // ---------------------------------------------

                $sql = "
                    SELECT MaYeuCau
                    FROM yeucau_ngoai_gio
                    WHERE MaSan = ?
                      AND NgayDat = ?
                      AND MaYeuCau <> ?
                      AND TrangThaiYeuCau IN (
                          'Chờ duyệt',
                          'Đã duyệt',
                          'Chờ thanh toán cọc'
                      )
                      AND ThoiGianBatDau < ?
                      AND ThoiGianKetThuc > ?
                    LIMIT 1
                    FOR UPDATE
                ";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    'isiss',
                    $maSan,
                    $ngayDat,
                    $maYeuCau,
                    $gioKetThuc,
                    $gioBatDau
                );

                mysqli_stmt_execute($stmt);

                $resultYeuCau = mysqli_stmt_get_result($stmt);
                $yeuCauTrung = mysqli_fetch_assoc($resultYeuCau);

                mysqli_stmt_close($stmt);

                if ($yeuCauTrung) {
                    throw new Exception(
                        'Thời gian này đang có yêu cầu ngoài giờ khác.'
                    );
                }

                // ---------------------------------------------
                // TẠO LỊCH ĐẶT
                // ---------------------------------------------

                $ghiChuLich =
                    'Đặt sân ngoài giờ - Yêu cầu #' . $maYeuCau .
                    '. Tổng tiền: ' .
                    number_format($tongTien, 0, ',', '.') .
                    ' VNĐ. Chờ thanh toán cọc 50%.';

                $sql = "
                    INSERT INTO lichdat (
                        MaND,
                        MaSan,
                        MaKhungGio,
                        NgayDat,
                        TongTien,
                        TrangThai,
                        GhiChu,
                        ThoiGianBatDau,
                        ThoiGianKetThuc,
                        LoaiDat
                    )
                    VALUES (
                        ?, ?, NULL, ?, ?,
                        'Đang giữ chỗ',
                        ?, ?, ?,
                        'Ngoài giờ'
                    )
                ";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    'iisdsss',
                    $maND,
                    $maSan,
                    $ngayDat,
                    $tongTien,
                    $ghiChuLich,
                    $gioBatDau,
                    $gioKetThuc
                );

                mysqli_stmt_execute($stmt);

                $maLichDatMoi = mysqli_insert_id($conn);

                mysqli_stmt_close($stmt);

                if ($maLichDatMoi <= 0) {
                    throw new Exception('Không tạo được lịch đặt.');
                }

                // ---------------------------------------------
                // CẬP NHẬT YÊU CẦU VÀ LIÊN KẾT LỊCH ĐẶT
                // ---------------------------------------------

                $ghiChuXuLy = $ghiChuAdmin;

                if ($ghiChuXuLy !== '') {
                    $ghiChuXuLy .= "\n";
                }

                $ghiChuXuLy .=
                    'Đã duyệt. Mã lịch đặt: ' . $maLichDatMoi .
                    '. Tổng tiền: ' .
                    number_format($tongTien, 0, ',', '.') .
                    ' VNĐ. Tiền cọc 50%: ' .
                    number_format($tienCoc, 0, ',', '.') .
                    ' VNĐ.';

                $sql = "
                    UPDATE yeucau_ngoai_gio
                    SET
                        MaLichDat = ?,
                        TrangThaiYeuCau = 'Chờ thanh toán cọc',
                        TongTienDuKien = ?,
                        GhiChuAdmin = ?,
                        NgayXuLy = NOW()
                    WHERE MaYeuCau = ?
                      AND TrangThaiYeuCau = 'Chờ duyệt'
                ";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    'idsi',
                    $maLichDatMoi,
                    $tongTien,
                    $ghiChuXuLy,
                    $maYeuCau
                );

                mysqli_stmt_execute($stmt);

                $soDong = mysqli_stmt_affected_rows($stmt);

                mysqli_stmt_close($stmt);

                if ($soDong <= 0) {
                    throw new Exception(
                        'Không thể cập nhật yêu cầu. Vui lòng thử lại.'
                    );
                }

                // ---------------------------------------------
                // LƯU TOÀN BỘ THAY ĐỔI
                // ---------------------------------------------

                mysqli_commit($conn);

                $thongBao =
                    'Đã duyệt yêu cầu và tạo lịch đặt thành công. Mã lịch đặt: ' .
                    $maLichDatMoi;

                $loaiThongBao = 'success';

            } else {
                throw new Exception('Thao tác không hợp lệ.');
            }

        } catch (Throwable $e) {

            // Hoàn tác nếu đang có transaction
            try {
                mysqli_rollback($conn);
            } catch (Throwable $rollbackError) {
                // Không làm gián đoạn thông báo lỗi chính
            }

            $thongBao = 'Lỗi xử lý: ' . $e->getMessage();
            $loaiThongBao = 'error';
        }

        // Đổi CSRF token sau khi xử lý
        $_SESSION['csrf_ngoai_gio'] = bin2hex(random_bytes(32));
    }
}

// =====================================================
// 5. LẤY DANH SÁCH YÊU CẦU
// =====================================================

$sql = "
    SELECT
        yc.*,
        nd.HoTen,
        nd.Email,
        nd.SoDienThoai,
        s.TenSan
    FROM yeucau_ngoai_gio yc
    INNER JOIN nguoidung nd
        ON yc.MaND = nd.MaND
    INNER JOIN san s
        ON yc.MaSan = s.MaSan
    ORDER BY
        CASE
            WHEN yc.TrangThaiYeuCau = 'Chờ duyệt' THEN 1
            WHEN yc.TrangThaiYeuCau = 'Chờ thanh toán cọc' THEN 2
            ELSE 3
        END,
        yc.NgayTao DESC,
        yc.MaYeuCau DESC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die('Lỗi lấy danh sách: ' . h(mysqli_error($conn)));
}

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Quản lý yêu cầu ngoài giờ</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #333;
        }

        .container {
            max-width: 1300px;
            margin: auto;
        }

        h2 {
            color: #1e293b;
            margin-bottom: 8px;
        }

        .mo-ta {
            color: #64748b;
            margin-bottom: 20px;
        }

        .quay-lai {
            display: inline-block;
            margin-bottom: 20px;
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
        }

        .gia-info {
            background: #eff6ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #1e40af;
            line-height: 1.8;
        }

        .thong-bao {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .bang-wrapper {
            overflow-x: auto;
            border-radius: 10px;
        }

        .bang {
            width: 100%;
            min-width: 1100px;
            border-collapse: collapse;
            background: white;
        }

        .bang th,
        .bang td {
            padding: 13px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }

        .bang th {
            background: #2563eb;
            color: white;
            white-space: nowrap;
        }

        .bang tr:hover {
            background: #f8fafc;
        }

        .trang-thai {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            background: #fef3c7;
            color: #92400e;
            white-space: nowrap;
        }

        .da-duyet {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .tu-choi {
            background: #fee2e2;
            color: #b91c1c;
        }

        .form-duyet {
            min-width: 190px;
        }

        .form-duyet textarea {
            width: 100%;
            min-height: 65px;
            padding: 9px;
            margin-bottom: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            resize: vertical;
            font-family: Arial, sans-serif;
        }

        button {
            border: none;
            padding: 9px 12px;
            border-radius: 6px;
            cursor: pointer;
            color: white;
            margin-bottom: 5px;
            width: 100%;
            font-weight: bold;
        }

        .btn-duyet {
            background: #16a34a;
        }

        .btn-tu-choi {
            background: #dc2626;
        }

        button:hover {
            opacity: 0.85;
        }

        .ghi-chu {
            color: #64748b;
            font-size: 13px;
            margin-top: 5px;
            line-height: 1.5;
        }

        .da-xu-ly {
            color: #64748b;
            font-size: 13px;
        }

        .khong-co {
            text-align: center;
            padding: 30px;
            background: white;
        }

        .ma-lich {
            color: #2563eb;
            font-weight: bold;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <a class="quay-lai" href="quan-ly-lich.php">
        ← Quay lại quản lý lịch đặt
    </a>

    <h2>Quản lý yêu cầu đặt sân ngoài giờ</h2>

    <p class="mo-ta">
        Xem xét, duyệt hoặc từ chối các yêu cầu đặt sân ngoài giờ của khách hàng.
    </p>

    <div class="gia-info">
        <strong>Giá thuê ngoài giờ:</strong>
        <?= number_format($GIA_NGOAI_GIO, 0, ',', '.') ?>đ/giờ/sân
        <br>

        <strong>Tiền cọc:</strong> 50% tổng tiền thuê
        <br>

        <strong>Cách tính:</strong>
        Số giờ thuê (làm tròn lên) × giá thuê ngoài giờ.
    </div>

    <?php if ($thongBao !== ''): ?>
        <div class="thong-bao <?= h($loaiThongBao) ?>">
            <?= h($thongBao) ?>
        </div>
    <?php endif; ?>

    <div class="bang-wrapper">

        <table class="bang">

            <thead>
                <tr>
                    <th>Mã YC</th>
                    <th>Khách hàng</th>
                    <th>Sân</th>
                    <th>Ngày và giờ</th>
                    <th>Lý do</th>
                    <th>Giá dự kiến</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>

            <tbody>

            <?php if (mysqli_num_rows($result) > 0): ?>

                <?php while ($row = mysqli_fetch_assoc($result)): ?>

                    <?php
                    $batDau = strtotime(
                        '1970-01-01 ' . $row['ThoiGianBatDau']
                    );

                    $ketThuc = strtotime(
                        '1970-01-01 ' . $row['ThoiGianKetThuc']
                    );

                    $soGiay = (
                        $batDau !== false && $ketThuc !== false
                    ) ? $ketThuc - $batDau : 0;

                    $soGio = $soGiay > 0
                        ? (int)ceil($soGiay / 3600)
                        : 0;

                    $giaDuKien = $soGio * $GIA_NGOAI_GIO;

                    // Ưu tiên số tiền đã được Admin duyệt
                    if ($row['TongTienDuKien'] !== null) {
                        $giaDuKien = (float)$row['TongTienDuKien'];
                    }

                    $trangThai = $row['TrangThaiYeuCau'];

                    $classTrangThai = '';

                    if ($trangThai === 'Đã từ chối') {
                        $classTrangThai = 'tu-choi';
                    } elseif ($trangThai !== 'Chờ duyệt') {
                        $classTrangThai = 'da-duyet';
                    }
                    ?>

                    <tr>

                        <td>
                            #<?= (int)$row['MaYeuCau'] ?>
                        </td>

                        <td>
                            <strong><?= h($row['HoTen']) ?></strong>

                            <div class="ghi-chu">
                                <?= h($row['SoDienThoai']) ?>
                            </div>

                            <div class="ghi-chu">
                                <?= h($row['Email']) ?>
                            </div>
                        </td>

                        <td>
                            <?= h($row['TenSan']) ?>
                        </td>

                        <td>
                            <?= date(
                                'd/m/Y',
                                strtotime($row['NgayDat'])
                            ) ?>

                            <br>

                            <?= h(substr($row['ThoiGianBatDau'], 0, 5)) ?>
                            -
                            <?= h(substr($row['ThoiGianKetThuc'], 0, 5)) ?>
                        </td>

                        <td>
                            <?= nl2br(h($row['LyDo'])) ?>
                        </td>

                        <td>

                            <strong>
                                <?= number_format(
                                    $giaDuKien,
                                    0,
                                    ',',
                                    '.'
                                ) ?>đ
                            </strong>

                            <div class="ghi-chu">
                                <?= $soGio ?> giờ tính tiền
                            </div>

                            <?php if (
                                $trangThai === 'Chờ thanh toán cọc' ||
                                $trangThai === 'Đã đặt'
                            ): ?>

                                <div class="ghi-chu">
                                    Tổng tiền:
                                    <?= number_format(
                                        (float)$row['TongTienDuKien'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?>đ
                                </div>

                                <div class="ghi-chu">
                                    Cọc 50%:
                                    <?= number_format(
                                        (float)$row['TongTienDuKien'] * 0.5,
                                        0,
                                        ',',
                                        '.'
                                    ) ?>đ
                                </div>

                                <?php if (!empty($row['MaLichDat'])): ?>
                                    <div class="ghi-chu ma-lich">
                                        Mã lịch đặt:
                                        #<?= (int)$row['MaLichDat'] ?>
                                    </div>
                                <?php endif; ?>

                            <?php endif; ?>

                        </td>

                        <td>
                            <span class="trang-thai <?= h($classTrangThai) ?>">
                                <?= h($trangThai) ?>
                            </span>
                        </td>

                        <td>

                            <?php if ($trangThai === 'Chờ duyệt'): ?>

                                <form
                                    method="POST"
                                    class="form-duyet"
                                    onsubmit="return confirm('Bạn có chắc chắn muốn thực hiện thao tác này?');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= h($_SESSION['csrf_ngoai_gio']) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="MaYeuCau"
                                        value="<?= (int)$row['MaYeuCau'] ?>"
                                    >

                                    <textarea
                                        name="GhiChuAdmin"
                                        placeholder="Ghi chú cho khách hàng (nếu có)"
                                    ></textarea>

                                    <button
                                        type="submit"
                                        name="hanhDong"
                                        value="duyet"
                                        class="btn-duyet"
                                    >
                                        Duyệt yêu cầu
                                    </button>

                                    <button
                                        type="submit"
                                        name="hanhDong"
                                        value="tu_choi"
                                        class="btn-tu-choi"
                                    >
                                        Từ chối
                                    </button>

                                </form>

                            <?php else: ?>

                                <span class="da-xu-ly">
                                    Đã xử lý
                                </span>

                                <?php if (!empty($row['GhiChuAdmin'])): ?>
                                    <div class="ghi-chu">
                                        <?= nl2br(h($row['GhiChuAdmin'])) ?>
                                    </div>
                                <?php endif; ?>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="8" class="khong-co">
                        Chưa có yêu cầu đặt sân ngoài giờ nào.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>


<?php
// admin/quan-ly-lich.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

mysqli_set_charset($conn, 'utf8mb4');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ========================================
// 1. KIỂM TRA ADMIN
// ========================================

if (
    !isset($_SESSION['MaND']) ||
    !isset($_SESSION['VaiTro']) ||
    $_SESSION['VaiTro'] !== 'Admin'
) {
    header('Location: ../login.php');
    exit();
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

function chuyenHuong($url, $message, $type = 'success')
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;

    header('Location: ' . $url);
    exit();
}

function urlDanhSach()
{
    $params = [];

    foreach (['trang_thai', 'tien_coc'] as $key) {
        $value = $_POST[$key] ?? '';

        if (is_string($value) && $value !== '') {
            $params[$key] = $value;
        }
    }

    $chiTiet = (int)($_POST['chi_tiet'] ?? 0);

    if ($chiTiet > 0) {
        $params['chi_tiet'] = $chiTiet;
    }

    return 'quan-ly-lich.php' .
        (count($params) ? '?' . http_build_query($params) : '');
}

function demGiaoDich($conn, $maLich, $loai, $trangThai)
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM thanhtoan
        WHERE MaLichDat = ?
          AND LoaiGiaoDich = ?
          AND TrangThaiThanhToan = ?
    ");

    $stmt->bind_param('iss', $maLich, $loai, $trangThai);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['total'] ?? 0);
}

function daCoc($conn, $maLich)
{
    return demGiaoDich(
        $conn,
        $maLich,
        'Đặt cọc',
        'Thành công'
    ) > 0;
}

function daHoanCoc($conn, $maLich)
{
    return demGiaoDich(
        $conn,
        $maLich,
        'Hoàn tiền',
        'Thành công'
    ) > 0;
}

function dangChoXacNhanCoc($conn, $maLich)
{
    return demGiaoDich(
        $conn,
        $maLich,
        'Đặt cọc',
        'Đang xử lý'
    ) > 0;
}

function trangThaiCoc($conn, $maLich)
{
    if (daHoanCoc($conn, $maLich)) {
        return 'Đã hoàn cọc';
    }

    if (daCoc($conn, $maLich)) {
        return 'Đã cọc';
    }

    if (dangChoXacNhanCoc($conn, $maLich)) {
        return 'Đang chờ xác nhận';
    }

    return 'Chưa cọc';
}

function badgeTrangThai($trangThai)
{
    $classes = [
        'Đã đặt' => 'badge-success',
        'Đang giữ chỗ' => 'badge-warning',
        'Đã hủy' => 'badge-danger',
        'Hoàn thành' => 'badge-info'
    ];

    $class = $classes[$trangThai] ?? 'badge-danger';

    return '<span class="badge ' . $class . '">' .
        e($trangThai ?: 'Không xác định') .
        '</span>';
}

function badgeCoc($trangThai)
{
    $classes = [
        'Đã cọc' => 'badge-success',
        'Đã hoàn cọc' => 'badge-info',
        'Đang chờ xác nhận' => 'badge-warning',
        'Chưa cọc' => 'badge-warning'
    ];

    $class = $classes[$trangThai] ?? 'badge-warning';

    return '<span class="badge ' . $class . '">' .
        e($trangThai) .
        '</span>';
}

function layLichVaKhoa($conn, $maLich)
{
    $stmt = $conn->prepare("
        SELECT MaLichDat, TongTien, TrangThai, LoaiDat
        FROM lichdat
        WHERE MaLichDat = ?
        FOR UPDATE
    ");

    $stmt->bind_param('i', $maLich);
    $stmt->execute();

    $lich = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $lich;
}

function layGiaoDichCocThanhCong($conn, $maLich)
{
    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS SoGiaoDich,
            SUM(SoTien) AS TongCoc,
            MIN(PhuongThuc) AS PhuongThuc,
            COUNT(DISTINCT PhuongThuc) AS SoPhuongThuc
        FROM thanhtoan
        WHERE MaLichDat = ?
          AND LoaiGiaoDich = 'Đặt cọc'
          AND TrangThaiThanhToan = 'Thành công'
    ");

    $stmt->bind_param('i', $maLich);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row;
}

// ========================================
// 3. CSRF TOKEN
// ========================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ========================================
// 4. XỬ LÝ THAO TÁC ADMIN
// ========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action'])
) {
    $csrf = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrf) ||
        !hash_equals($_SESSION['csrf_token'], $csrf)
    ) {
        chuyenHuong(
            'quan-ly-lich.php',
            'Yêu cầu không hợp lệ. Vui lòng thử lại!',
            'error'
        );
    }

    $maLich = (int)($_POST['ma_lich'] ?? 0);
    $action = $_POST['action'];

    if ($maLich <= 0 || !is_string($action)) {
        chuyenHuong(
            'quan-ly-lich.php',
            'Thông tin thao tác không hợp lệ!',
            'error'
        );
    }

    // ------------------------------------
    // A. XÁC NHẬN TIỀN CỌC
    // ------------------------------------

    if ($action === 'xac_nhan_coc') {
        try {
            $conn->begin_transaction();

            $lich = layLichVaKhoa($conn, $maLich);

            if (!$lich) {
                throw new Exception('Không tìm thấy lịch đặt!');
            }

            if (!in_array(
                $lich['TrangThai'],
                ['Đang giữ chỗ', 'Đã đặt'],
                true
            )) {
                throw new Exception(
                    'Lịch không ở trạng thái có thể xác nhận cọc!'
                );
            }

            if (daHoanCoc($conn, $maLich)) {
                throw new Exception('Lịch này đã được hoàn cọc!');
            }

            if (daCoc($conn, $maLich)) {
                throw new Exception(
                    'Lịch này đã được ghi nhận tiền cọc!'
                );
            }

            $tienCoc = round((float)$lich['TongTien'] * 0.5, 2);

            if ($tienCoc <= 0) {
                throw new Exception('Số tiền cọc không hợp lệ!');
            }

            $stmt = $conn->prepare("
                SELECT MaThanhToan, SoTien
                FROM thanhtoan
                WHERE MaLichDat = ?
                  AND LoaiGiaoDich = 'Đặt cọc'
                  AND TrangThaiThanhToan = 'Đang xử lý'
                ORDER BY MaThanhToan DESC
                FOR UPDATE
            ");

            $stmt->bind_param('i', $maLich);
            $stmt->execute();

            $resultPending = $stmt->get_result();
            $pendingRows = [];

            while ($row = $resultPending->fetch_assoc()) {
                $pendingRows[] = $row;
            }

            $stmt->close();

            if (count($pendingRows) > 1) {
                throw new Exception(
                    'Có nhiều giao dịch QR đang chờ. Hãy kiểm tra từng giao dịch trước khi xác nhận.'
                );
            }

            if (count($pendingRows) === 1) {
                $pending = $pendingRows[0];

                if (abs((float)$pending['SoTien'] - $tienCoc) > 0.01) {
                    throw new Exception(
                        'Số tiền giao dịch không khớp với số tiền cọc!'
                    );
                }

                // Chỉ xác nhận sau khi Admin đã kiểm tra tiền thực nhận.
                $stmt = $conn->prepare("
                    UPDATE thanhtoan
                    SET TrangThaiThanhToan = 'Thành công',
                        ThoiGianThanhToan = NOW()
                    WHERE MaThanhToan = ?
                      AND TrangThaiThanhToan = 'Đang xử lý'
                ");

                $stmt->bind_param('i', $pending['MaThanhToan']);
                $stmt->execute();

                if ($stmt->affected_rows !== 1) {
                    $stmt->close();
                    throw new Exception(
                        'Không thể xác nhận giao dịch QR!'
                    );
                }

                $stmt->close();

            } else {
                // Ghi nhận tiền mặt sau khi Admin đã nhận tiền thực tế.
                $stmt = $conn->prepare("
                    INSERT INTO thanhtoan (
                        MaLichDat,
                        SoTien,
                        PhuongThuc,
                        LoaiGiaoDich,
                        TrangThaiThanhToan,
                        ThoiGianThanhToan
                    )
                    VALUES (
                        ?,
                        ?,
                        'Tiền mặt',
                        'Đặt cọc',
                        'Thành công',
                        NOW()
                    )
                ");

                $stmt->bind_param('id', $maLich, $tienCoc);
                $stmt->execute();
                $stmt->close();
            }

            // Chuyển lịch giữ chỗ thành lịch đã đặt.
            if ($lich['TrangThai'] === 'Đang giữ chỗ') {
                $stmt = $conn->prepare("
                    UPDATE lichdat
                    SET TrangThai = 'Đã đặt'
                    WHERE MaLichDat = ?
                      AND TrangThai = 'Đang giữ chỗ'
                ");

                $stmt->bind_param('i', $maLich);
                $stmt->execute();

                if ($stmt->affected_rows !== 1) {
                    $stmt->close();
                    throw new Exception(
                        'Không thể cập nhật trạng thái lịch!'
                    );
                }

                $stmt->close();
            }

            // Đồng bộ yêu cầu ngoài giờ nếu lịch thuộc loại ngoài giờ.
            if (($lich['LoaiDat'] ?? '') === 'Ngoài giờ') {
                $stmt = $conn->prepare("
                    UPDATE yeucau_ngoai_gio
                    SET TrangThaiYeuCau = 'Đã đặt',
                        NgayXuLy = NOW()
                    WHERE MaLichDat = ?
                      AND TrangThaiYeuCau = 'Chờ thanh toán cọc'
                ");

                $stmt->bind_param('i', $maLich);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();

            chuyenHuong(
                urlDanhSach(),
                'Đã xác nhận tiền cọc và cập nhật lịch đặt thành công!'
            );

        } catch (Throwable $e) {
            $conn->rollback();

            error_log('Lỗi xác nhận cọc: ' . $e->getMessage());

            chuyenHuong(
                'quan-ly-lich.php?chi_tiet=' . $maLich,
                'Không thể xác nhận cọc: ' . $e->getMessage(),
                'error'
            );
        }
    }

    // ------------------------------------
    // B. XÁC NHẬN LỊCH ĐẶT
    // ------------------------------------

    elseif ($action === 'xac_nhan') {
        try {
            $conn->begin_transaction();

            $lich = layLichVaKhoa($conn, $maLich);

            if (!$lich || $lich['TrangThai'] !== 'Đang giữ chỗ') {
                throw new Exception(
                    'Lịch không ở trạng thái đang giữ chỗ!'
                );
            }

            if (!daCoc($conn, $maLich) || daHoanCoc($conn, $maLich)) {
                throw new Exception(
                    'Khách hàng chưa có giao dịch cọc thành công!'
                );
            }

            $stmt = $conn->prepare("
                UPDATE lichdat
                SET TrangThai = 'Đã đặt'
                WHERE MaLichDat = ?
                  AND TrangThai = 'Đang giữ chỗ'
            ");

            $stmt->bind_param('i', $maLich);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                $stmt->close();
                throw new Exception('Không thể xác nhận lịch!');
            }

            $stmt->close();

            if (($lich['LoaiDat'] ?? '') === 'Ngoài giờ') {
                $stmt = $conn->prepare("
                    UPDATE yeucau_ngoai_gio
                    SET TrangThaiYeuCau = 'Đã đặt',
                        NgayXuLy = NOW()
                    WHERE MaLichDat = ?
                      AND TrangThaiYeuCau = 'Chờ thanh toán cọc'
                ");

                $stmt->bind_param('i', $maLich);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();

            chuyenHuong(
                urlDanhSach(),
                'Xác nhận lịch đặt thành công!'
            );

        } catch (Throwable $e) {
            $conn->rollback();

            error_log('Lỗi xác nhận lịch: ' . $e->getMessage());

            chuyenHuong(
                'quan-ly-lich.php?chi_tiet=' . $maLich,
                'Lỗi xác nhận lịch: ' . $e->getMessage(),
                'error'
            );
        }
    }

    // ------------------------------------
    // C. HỦY LỊCH
    // ------------------------------------

    elseif ($action === 'huy') {
        try {
            $conn->begin_transaction();

            $lich = layLichVaKhoa($conn, $maLich);

            if (
                !$lich ||
                !in_array(
                    $lich['TrangThai'],
                    ['Đang giữ chỗ', 'Đã đặt'],
                    true
                )
            ) {
                throw new Exception(
                    'Lịch không còn ở trạng thái có thể hủy!'
                );
            }

            if (dangChoXacNhanCoc($conn, $maLich)) {
                throw new Exception(
                    'Lịch đang có giao dịch cọc chờ xác nhận. Hãy kiểm tra giao dịch trước khi hủy.'
                );
            }

            $stmt = $conn->prepare("
                UPDATE lichdat
                SET TrangThai = 'Đã hủy'
                WHERE MaLichDat = ?
                  AND TrangThai IN ('Đang giữ chỗ', 'Đã đặt')
            ");

            $stmt->bind_param('i', $maLich);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                $stmt->close();
                throw new Exception('Không thể hủy lịch!');
            }

            $stmt->close();

            // Đồng bộ trạng thái yêu cầu ngoài giờ khi lịch bị hủy.
            if (($lich['LoaiDat'] ?? '') === 'Ngoài giờ') {
                $stmt = $conn->prepare("
                    UPDATE yeucau_ngoai_gio
                    SET TrangThaiYeuCau = 'Đã hủy',
                        NgayXuLy = NOW()
                    WHERE MaLichDat = ?
                      AND TrangThaiYeuCau = 'Đã đặt'
                ");

                $stmt->bind_param('i', $maLich);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();

            chuyenHuong(
                urlDanhSach(),
                'Đã hủy lịch đặt. Nếu khách đã cọc, hãy xử lý hoàn tiền theo quy định.'
            );

        } catch (Throwable $e) {
            $conn->rollback();

            error_log('Lỗi hủy lịch: ' . $e->getMessage());

            chuyenHuong(
                'quan-ly-lich.php?chi_tiet=' . $maLich,
                'Không thể hủy lịch: ' . $e->getMessage(),
                'error'
            );
        }
    }

    // ------------------------------------
    // D. HOÀN THÀNH LỊCH
    // ------------------------------------

    elseif ($action === 'hoan_thanh') {
        try {
            $conn->begin_transaction();

            $lich = layLichVaKhoa($conn, $maLich);

            if (!$lich || $lich['TrangThai'] !== 'Đã đặt') {
                throw new Exception(
                    'Chỉ được hoàn thành lịch đã đặt!'
                );
            }

            if (!daCoc($conn, $maLich) || daHoanCoc($conn, $maLich)) {
                throw new Exception(
                    'Lịch chưa có tiền cọc hợp lệ!'
                );
            }

            $stmt = $conn->prepare("
                UPDATE lichdat
                SET TrangThai = 'Hoàn thành'
                WHERE MaLichDat = ?
                  AND TrangThai = 'Đã đặt'
            ");

            $stmt->bind_param('i', $maLich);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                $stmt->close();
                throw new Exception(
                    'Không thể cập nhật lịch hoàn thành!'
                );
            }

            $stmt->close();

            $conn->commit();

            chuyenHuong(
                urlDanhSach(),
                'Lịch đặt đã hoàn thành!'
            );

        } catch (Throwable $e) {
            $conn->rollback();

            error_log('Lỗi hoàn thành lịch: ' . $e->getMessage());

            chuyenHuong(
                'quan-ly-lich.php?chi_tiet=' . $maLich,
                'Lỗi hoàn thành lịch: ' . $e->getMessage(),
                'error'
            );
        }
    }

    // ------------------------------------
    // E. GHI NHẬN HOÀN CỌC
    // ------------------------------------

    elseif ($action === 'hoan_coc') {
        try {
            $conn->begin_transaction();

            $lich = layLichVaKhoa($conn, $maLich);

            if (!$lich) {
                throw new Exception('Không tìm thấy lịch đặt!');
            }

            if ($lich['TrangThai'] !== 'Đã hủy') {
                throw new Exception(
                    'Chỉ được hoàn cọc cho lịch đã hủy!'
                );
            }

            if (daHoanCoc($conn, $maLich)) {
                throw new Exception(
                    'Lịch này đã được ghi nhận hoàn cọc!'
                );
            }

            if (dangChoXacNhanCoc($conn, $maLich)) {
                throw new Exception(
                    'Lịch vẫn có giao dịch cọc đang xử lý. Hãy đối chiếu giao dịch trước!'
                );
            }

            $coc = layGiaoDichCocThanhCong($conn, $maLich);

            if ((int)$coc['SoGiaoDich'] !== 1) {
                throw new Exception(
                    'Phải có đúng một giao dịch cọc thành công mới được ghi nhận hoàn cọc.'
                );
            }

            if ((int)$coc['SoPhuongThuc'] !== 1) {
                throw new Exception(
                    'Không xác định được phương thức giao dịch!'
                );
            }

            $tienHoan = (float)$coc['TongCoc'];
            $phuongThuc = $coc['PhuongThuc'];

            if (
                $tienHoan <= 0 ||
                !in_array(
                    $phuongThuc,
                    ['Tiền mặt', 'Chuyển khoản QR', 'MoMo'],
                    true
                )
            ) {
                throw new Exception(
                    'Thông tin hoàn tiền không hợp lệ!'
                );
            }

            // Chỉ ghi nhận sau khi đã hoàn tiền thực tế.
            $stmt = $conn->prepare("
                INSERT INTO thanhtoan (
                    MaLichDat,
                    SoTien,
                    PhuongThuc,
                    LoaiGiaoDich,
                    TrangThaiThanhToan,
                    ThoiGianThanhToan
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    'Hoàn tiền',
                    'Thành công',
                    NOW()
                )
            ");

            $stmt->bind_param(
                'ids',
                $maLich,
                $tienHoan,
                $phuongThuc
            );

            $stmt->execute();
            $stmt->close();

            $conn->commit();

            chuyenHuong(
                'quan-ly-lich.php?chi_tiet=' . $maLich,
                'Đã ghi nhận hoàn cọc ' .
                number_format($tienHoan, 0, ',', '.') . 'đ!'
            );

        } catch (Throwable $e) {
            $conn->rollback();

            error_log('Lỗi hoàn cọc: ' . $e->getMessage());

            chuyenHuong(
                'quan-ly-lich.php?chi_tiet=' . $maLich,
                'Không thể ghi nhận hoàn cọc: ' . $e->getMessage(),
                'error'
            );
        }
    }

        // ------------------------------------
    // F. DUYỆT / TỪ CHỐI YÊU CẦU HỦY
    // ------------------------------------

    elseif ($action === 'xu_ly_yeu_cau_huy') {

        $maYeuCauHuy = (int)($_POST['ma_yeu_cau_huy'] ?? 0);
        $ketQua = $_POST['ket_qua'] ?? '';
        $ghiChuAdmin = trim($_POST['ghi_chu_admin'] ?? '');

        if (
            $maYeuCauHuy <= 0 ||
            !in_array($ketQua, ['duyet', 'tu_choi'], true)
        ) {
            chuyenHuong(
                'quan-ly-lich.php?chi_tiet=' . $maLich,
                'Thông tin xử lý yêu cầu hủy không hợp lệ!',
                'error'
            );
        }

        try {
            $conn->begin_transaction();

            // Khóa yêu cầu hủy.
            $stmt = $conn->prepare("
                SELECT *
                FROM yeucau_huy_lich
                WHERE MaYeuCauHuy = ?
                  AND MaLichDat = ?
                FOR UPDATE
            ");

            $stmt->bind_param('ii', $maYeuCauHuy, $maLich);
            $stmt->execute();

            $yeuCau = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$yeuCau) {
                throw new Exception('Không tìm thấy yêu cầu hủy!');
            }

            if ($yeuCau['TrangThai'] !== 'Chờ duyệt') {
                throw new Exception(
                    'Yêu cầu này đã được xử lý trước đó!'
                );
            }

            // Khóa lịch đặt.
            $lich = layLichVaKhoa($conn, $maLich);

            if (!$lich) {
                throw new Exception('Không tìm thấy lịch đặt!');
            }

            if (!in_array(
                $lich['TrangThai'],
                ['Đang giữ chỗ', 'Đã đặt'],
                true
            )) {
                throw new Exception(
                    'Lịch không còn ở trạng thái có thể xử lý yêu cầu hủy!'
                );
            }

            if ($ketQua === 'duyet') {

                // Không duyệt khi giao dịch cọc vẫn đang chờ đối chiếu.
                if (dangChoXacNhanCoc($conn, $maLich)) {
                    throw new Exception(
                        'Lịch đang có giao dịch cọc chờ xác nhận. Hãy đối chiếu giao dịch trước khi duyệt hủy!'
                    );
                }

                // Chuyển lịch đặt thành Đã hủy.
                $stmt = $conn->prepare("
                    UPDATE lichdat
                    SET TrangThai = 'Đã hủy'
                    WHERE MaLichDat = ?
                      AND TrangThai IN ('Đang giữ chỗ', 'Đã đặt')
                ");

                $stmt->bind_param('i', $maLich);
                $stmt->execute();

                if ($stmt->affected_rows !== 1) {
                    $stmt->close();
                    throw new Exception('Không thể cập nhật lịch thành Đã hủy!');
                }

                $stmt->close();

                // Đồng bộ yêu cầu ngoài giờ nếu có.
                if (($lich['LoaiDat'] ?? '') === 'Ngoài giờ') {
                    $stmt = $conn->prepare("
                        UPDATE yeucau_ngoai_gio
                        SET TrangThaiYeuCau = 'Đã hủy',
                            NgayXuLy = NOW()
                        WHERE MaLichDat = ?
                    ");

                    $stmt->bind_param('i', $maLich);
                    $stmt->execute();
                    $stmt->close();
                }

                $trangThaiMoi = 'Đã duyệt';

            } else {

                // Từ chối: không thay đổi trạng thái lịch đặt.
                $trangThaiMoi = 'Đã từ chối';
            }

            // Cập nhật yêu cầu hủy.
            $stmt = $conn->prepare("
                UPDATE yeucau_huy_lich
                SET TrangThai = ?,
                    GhiChuAdmin = ?,
                    NgayXuLy = NOW()
                WHERE MaYeuCauHuy = ?
                  AND TrangThai = 'Chờ duyệt'
            ");

            $stmt->bind_param(
                'ssi',
                $trangThaiMoi,
                $ghiChuAdmin,
                $maYeuCauHuy
            );

            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                $stmt->close();
                throw new Exception('Không thể cập nhật yêu cầu hủy!');
            }

            $stmt->close();

            $conn->commit();

            if ($ketQua === 'duyet') {
                chuyenHuong(
                    'quan-ly-lich.php?chi_tiet=' . $maLich,
                    'Đã duyệt yêu cầu hủy. Lịch đặt đã chuyển thành Đã hủy. Hãy kiểm tra và xử lý hoàn cọc nếu khách đã thanh toán!'
                );
            } else {
                chuyenHuong(
                    'quan-ly-lich.php?chi_tiet=' . $maLich,
                    'Đã từ chối yêu cầu hủy. Lịch đặt vẫn được giữ nguyên!'
                );
            }

        } catch (Throwable $e) {
            $conn->rollback();

            error_log('Lỗi xử lý yêu cầu hủy: ' . $e->getMessage());

            chuyenHuong(
                'quan-ly-lich.php?chi_tiet=' . $maLich,
                'Không thể xử lý yêu cầu hủy: ' . $e->getMessage(),
                'error'
            );
        }
    }

    else {
        chuyenHuong(
            'quan-ly-lich.php',
            'Thao tác không hợp lệ!',
            'error'
        );
    }
}

// ========================================
// 5. BỘ LỌC
// ========================================

$trang_thai = $_GET['trang_thai'] ?? '';
$tien_coc = $_GET['tien_coc'] ?? '';

if (!is_string($trang_thai)) {
    $trang_thai = '';
}

if (!is_string($tien_coc)) {
    $tien_coc = '';
}

$cac_trang_thai = [
    'Đang giữ chỗ',
    'Đã đặt',
    'Đã hủy',
    'Hoàn thành'
];

$where = [];

if (in_array($trang_thai, $cac_trang_thai, true)) {
    $trangThaiSql = $conn->real_escape_string($trang_thai);
    $where[] = "ld.TrangThai = '$trangThaiSql'";
}

if ($tien_coc === 'chua_coc') {
    $where[] = "
        NOT EXISTS (
            SELECT 1
            FROM thanhtoan tt
            WHERE tt.MaLichDat = ld.MaLichDat
              AND tt.LoaiGiaoDich = 'Đặt cọc'
              AND tt.TrangThaiThanhToan IN (
                  'Thành công',
                  'Đang xử lý'
              )
        )
    ";

} elseif ($tien_coc === 'dang_xu_ly') {
    $where[] = "
        EXISTS (
            SELECT 1
            FROM thanhtoan tt
            WHERE tt.MaLichDat = ld.MaLichDat
              AND tt.LoaiGiaoDich = 'Đặt cọc'
              AND tt.TrangThaiThanhToan = 'Đang xử lý'
        )
    ";

} elseif ($tien_coc === 'da_coc') {
    $where[] = "
        EXISTS (
            SELECT 1
            FROM thanhtoan tt
            WHERE tt.MaLichDat = ld.MaLichDat
              AND tt.LoaiGiaoDich = 'Đặt cọc'
              AND tt.TrangThaiThanhToan = 'Thành công'
        )
        AND NOT EXISTS (
            SELECT 1
            FROM thanhtoan ht
            WHERE ht.MaLichDat = ld.MaLichDat
              AND ht.LoaiGiaoDich = 'Hoàn tiền'
              AND ht.TrangThaiThanhToan = 'Thành công'
        )
    ";

} elseif ($tien_coc === 'da_hoan_coc') {
    $where[] = "
        EXISTS (
            SELECT 1
            FROM thanhtoan tt
            WHERE tt.MaLichDat = ld.MaLichDat
              AND tt.LoaiGiaoDich = 'Đặt cọc'
              AND tt.TrangThaiThanhToan = 'Thành công'
        )
        AND EXISTS (
            SELECT 1
            FROM thanhtoan ht
            WHERE ht.MaLichDat = ld.MaLichDat
              AND ht.LoaiGiaoDich = 'Hoàn tiền'
              AND ht.TrangThaiThanhToan = 'Thành công'
        )
    ";
}

$filter = count($where) > 0
    ? 'WHERE ' . implode(' AND ', $where)
    : '';

// ========================================
// 6. LẤY DANH SÁCH LỊCH ĐẶT
// ========================================

$sql = "
    SELECT
        ld.*,
        nd.HoTen,
        nd.SoDienThoai,
        nd.Email,
        s.TenSan,
        COALESCE(ld.ThoiGianBatDau, kg.ThoiGianBatDau) AS ThoiGianBatDau,
        COALESCE(ld.ThoiGianKetThuc, kg.ThoiGianKetThuc) AS ThoiGianKetThuc
    FROM lichdat ld
    JOIN nguoidung nd ON ld.MaND = nd.MaND
    JOIN san s ON ld.MaSan = s.MaSan
    LEFT JOIN khunggio kg ON ld.MaKhungGio = kg.MaKhungGio
    $filter
    ORDER BY ld.NgayTao DESC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die('Lỗi truy vấn danh sách lịch: ' . e(mysqli_error($conn)));
}

// ========================================
// 7. CHI TIẾT LỊCH ĐẶT
// ========================================

$chi_tiet = null;
$giao_dich = [];
$yeu_cau_huy = null;

if (isset($_GET['chi_tiet'])) {
    $ma_lich_ct = (int)$_GET['chi_tiet'];

    if ($ma_lich_ct > 0) {
        $stmt = $conn->prepare("
            SELECT
                ld.*,
                nd.HoTen,
                nd.SoDienThoai,
                nd.Email,
                s.TenSan,
                COALESCE(ld.ThoiGianBatDau, kg.ThoiGianBatDau) AS ThoiGianBatDau,
                COALESCE(ld.ThoiGianKetThuc, kg.ThoiGianKetThuc) AS ThoiGianKetThuc
            FROM lichdat ld
            JOIN nguoidung nd ON ld.MaND = nd.MaND
            JOIN san s ON ld.MaSan = s.MaSan
            LEFT JOIN khunggio kg ON ld.MaKhungGio = kg.MaKhungGio
            WHERE ld.MaLichDat = ?
            LIMIT 1
        ");

        $stmt->bind_param('i', $ma_lich_ct);
        $stmt->execute();

        $chi_tiet = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($chi_tiet) {
            $stmt = $conn->prepare("
                SELECT *
                FROM thanhtoan
                WHERE MaLichDat = ?
                ORDER BY MaThanhToan DESC
            ");

            $stmt->bind_param('i', $ma_lich_ct);
            $stmt->execute();

            $resultGiaoDich = $stmt->get_result();

            while ($row = $resultGiaoDich->fetch_assoc()) {
                $giao_dich[] = $row;
            }

            $stmt->close();

                        // Lấy yêu cầu hủy mới nhất của lịch.
            $stmt = $conn->prepare("
                SELECT *
                FROM yeucau_huy_lich
                WHERE MaLichDat = ?
                ORDER BY NgayGui DESC, MaYeuCauHuy DESC
                LIMIT 1
            ");

            $stmt->bind_param('i', $ma_lich_ct);
            $stmt->execute();

            $yeu_cau_huy = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }
}

// ========================================
// 8. FLASH MESSAGE
// ========================================

$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';

unset($_SESSION['flash_message'], $_SESSION['flash_type']);

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý lịch đặt - Admin</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f1f5f9;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: #1e293b;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100%;
            top: 0;
            left: 0;
        }

        .sidebar h2 {
            text-align: center;
            padding: 10px 10px 20px;
            margin-bottom: 30px;
            font-size: 20px;
            border-bottom: 1px solid #334155;
        }

        .sidebar a {
            display: block;
            color: #cbd5e1;
            text-decoration: none;
            padding: 14px 25px;
            transition: 0.3s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #334155;
            color: white;
        }

        .main {
            margin-left: 260px;
            flex: 1;
            padding: 30px;
            min-width: 0;
        }

        .header {
            margin-bottom: 25px;
        }

        .header h1 {
            color: #1e293b;
            font-size: 26px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 25px;
        }

        .btn {
            padding: 7px 11px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            color: white;
            margin: 2px;
            white-space: nowrap;
        }

        .btn-danger { background: #dc2626; }
        .btn-success { background: #16a34a; }
        .btn-primary { background: #2563eb; }
        .btn-info { background: #0ea5e9; }
        .btn-warning { background: #f59e0b; }

        .btn:hover { opacity: 0.85; }

        .filter {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filter select {
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: white;
            font-size: 13px;
            outline: none;
            cursor: pointer;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        th, td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
        }

        tbody tr:hover { background: #f8fafc; }

        .badge {
            padding: 4px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            display: inline-block;
        }

        .badge-success { background: #dcfce7; color: #16a34a; }
        .badge-warning { background: #fef9c3; color: #ca8a04; }
        .badge-danger { background: #fee2e2; color: #dc2626; }
        .badge-info { background: #e0f2fe; color: #0284c7; }

        .detail-box {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .detail-box p {
            margin: 10px 0;
            font-size: 14px;
            color: #334155;
        }

        .detail-box strong {
            color: #1e293b;
            min-width: 140px;
            display: inline-block;
        }

        .detail-title {
            margin-bottom: 15px;
            color: #1e293b;
        }

        .alert {
            padding: 13px 16px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
        }

        .actions form,
        .detail-actions form {
            display: inline-block;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #94a3b8;
        }

        .detail-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        @media (max-width: 768px) {
            .sidebar { width: 190px; }
            .main { margin-left: 190px; padding: 15px; }
            .header h1 { font-size: 21px; }
            .card { padding: 15px; }
        }
    </style>
</head>

<body>

<div class="sidebar">
    <h2>Admin Panel</h2>

    <a href="index.php">Dashboard</a>
    <a href="quan-ly-san.php">Quản lý sân</a>
    <a href="quan-ly-lich.php" class="active">Quản lý lịch đặt</a>
    <a href="quan-ly-yeu-cau.php">Yêu cầu ngoài giờ</a>
    <a href="quan-ly-khach.php">Quản lý khách hàng</a>
    <a href="thong-ke.php">Thống kê</a>
    <a href="logout.php">Đăng xuất</a>
</div>

<div class="main">

    <div class="header">
        <h1>Quản lý lịch đặt</h1>
    </div>

    <?php if ($flash_message !== '') { ?>
        <div class="alert <?php echo $flash_type === 'error' ? 'alert-error' : 'alert-success'; ?>">
            <?php echo e($flash_message); ?>
        </div>
    <?php } ?>

    <?php if ($chi_tiet) { ?>

        <div class="card">

            <h2 class="detail-title">
                Chi tiết lịch #<?php echo (int)$chi_tiet['MaLichDat']; ?>
            </h2>

            <div class="detail-box">

                <p><strong>Khách hàng:</strong> <?php echo e($chi_tiet['HoTen']); ?></p>
                <p><strong>Email:</strong> <?php echo e($chi_tiet['Email']); ?></p>
                <p><strong>Số điện thoại:</strong> <?php echo e($chi_tiet['SoDienThoai'] ?? '—'); ?></p>
                <p><strong>Sân:</strong> <?php echo e($chi_tiet['TenSan']); ?></p>

                <p>
                    <strong>Loại đặt:</strong>
                    <?php echo e($chi_tiet['LoaiDat'] ?? 'Đặt thường'); ?>
                </p>

                <p>
                    <strong>Ngày chơi:</strong>
                    <?php echo e(date('d/m/Y', strtotime($chi_tiet['NgayDat']))); ?>
                </p>

                <p>
                    <strong>Giờ chơi:</strong>
                    <?php echo e(!empty($chi_tiet['ThoiGianBatDau']) ? substr($chi_tiet['ThoiGianBatDau'], 0, 5) : '--:--'); ?>
                    -
                    <?php echo e(!empty($chi_tiet['ThoiGianKetThuc']) ? substr($chi_tiet['ThoiGianKetThuc'], 0, 5) : '--:--'); ?>
                </p>

                <p>
                    <strong>Tổng tiền:</strong>
                    <?php echo number_format((float)$chi_tiet['TongTien'], 0, ',', '.'); ?>đ
                </p>

                <p>
                    <strong>Tiền cọc 50%:</strong>
                    <?php echo number_format((float)$chi_tiet['TongTien'] * 0.5, 0, ',', '.'); ?>đ
                </p>

                <p>
                    <strong>Trạng thái lịch:</strong>
                    <?php echo badgeTrangThai($chi_tiet['TrangThai']); ?>
                </p>

                <p>
                    <strong>Số lần đổi:</strong>
                    <?php echo e($chi_tiet['SoLanDoi'] ?? 0); ?>
                </p>

                <p>
                    <strong>Ghi chú:</strong>
                    <?php echo e($chi_tiet['GhiChu'] ?: 'Không có'); ?>
                </p>

                <p>
                    <strong>Trạng thái cọc:</strong>
                    <?php echo badgeCoc(trangThaiCoc($conn, $chi_tiet['MaLichDat'])); ?>
                </p>

            </div>

                        <!-- YÊU CẦU HỦY LỊCH -->

            <?php if ($yeu_cau_huy) { ?>

                <div class="detail-box">

                    <h3 style="margin-bottom:15px;color:#1e293b;">
                        Yêu cầu hủy lịch
                    </h3>

                    <p>
                        <strong>Mã yêu cầu:</strong>
                        #<?php echo (int)$yeu_cau_huy['MaYeuCauHuy']; ?>
                    </p>

                    <p>
                        <strong>Lý do hủy:</strong>
                        <?php echo nl2br(e($yeu_cau_huy['LyDoHuy'])); ?>
                    </p>

                    <p>
                        <strong>Ngày gửi:</strong>
                        <?php
                        echo e(date(
                            'd/m/Y H:i',
                            strtotime($yeu_cau_huy['NgayGui'])
                        ));
                        ?>
                    </p>

                    <p>
                        <strong>Trạng thái yêu cầu:</strong>

                        <?php
                        $classYeuCau = 'badge-warning';

                        if ($yeu_cau_huy['TrangThai'] === 'Đã duyệt') {
                            $classYeuCau = 'badge-success';
                        } elseif ($yeu_cau_huy['TrangThai'] === 'Đã từ chối') {
                            $classYeuCau = 'badge-danger';
                        }
                        ?>

                        <span class="badge <?php echo $classYeuCau; ?>">
                            <?php echo e($yeu_cau_huy['TrangThai']); ?>
                        </span>
                    </p>

                    <?php if (!empty($yeu_cau_huy['GhiChuAdmin'])) { ?>
                        <p>
                            <strong>Ghi chú Admin:</strong>
                            <?php echo nl2br(e($yeu_cau_huy['GhiChuAdmin'])); ?>
                        </p>
                    <?php } ?>

                    <?php if ($yeu_cau_huy['TrangThai'] === 'Chờ duyệt') { ?>

                        <form method="POST"
                              style="margin-top:15px;">

                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?php echo e($_SESSION['csrf_token']); ?>">

                            <input type="hidden"
                                   name="ma_lich"
                                   value="<?php echo (int)$chi_tiet['MaLichDat']; ?>">

                            <input type="hidden"
                                   name="ma_yeu_cau_huy"
                                   value="<?php echo (int)$yeu_cau_huy['MaYeuCauHuy']; ?>">

                            <label style="display:block;margin-bottom:8px;font-weight:bold;">
                                Ghi chú xử lý của Admin:
                            </label>

                            <textarea
                                name="ghi_chu_admin"
                                rows="3"
                                maxlength="1000"
                                style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;margin-bottom:10px;"
                                placeholder="Nhập ghi chú nếu cần..."
                            ></textarea>

                            <button
                                type="submit"
                                name="action"
                                value="xu_ly_yeu_cau_huy"
                                class="btn btn-success"
                                onclick="this.form.ket_qua.value='duyet'; return confirm('Bạn có chắc muốn duyệt yêu cầu hủy lịch này?');"
                            >
                                Duyệt hủy
                            </button>

                            <button
                                type="submit"
                                name="action"
                                value="xu_ly_yeu_cau_huy"
                                class="btn btn-danger"
                                onclick="this.form.ket_qua.value='tu_choi'; return confirm('Bạn có chắc muốn từ chối yêu cầu hủy này?');"
                            >
                                Từ chối
                            </button>

                            <input type="hidden" name="ket_qua" value="">

                        </form>

                    <?php } ?>

                </div>

            <?php } ?>

            <h3 style="margin:20px 0 12px;color:#1e293b;">
                Lịch sử thanh toán / cọc
            </h3>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Mã GD</th>
                            <th>Số tiền</th>
                            <th>Loại giao dịch</th>
                            <th>Phương thức</th>
                            <th>Trạng thái</th>
                            <th>Thời gian</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (count($giao_dich) > 0) { ?>

                        <?php foreach ($giao_dich as $gd) { ?>
                            <tr>
                                <td>#<?php echo (int)$gd['MaThanhToan']; ?></td>

                                <td>
                                    <?php echo number_format((float)$gd['SoTien'], 0, ',', '.'); ?>đ
                                </td>

                                <td><?php echo e($gd['LoaiGiaoDich']); ?></td>
                                <td><?php echo e($gd['PhuongThuc']); ?></td>

                                <td>
                                    <?php if ($gd['TrangThaiThanhToan'] === 'Thành công') { ?>
                                        <span class="badge badge-success">Thành công</span>
                                    <?php } elseif ($gd['TrangThaiThanhToan'] === 'Đang xử lý') { ?>
                                        <span class="badge badge-warning">Đang xử lý</span>
                                    <?php } else { ?>
                                        <span class="badge badge-danger">
                                            <?php echo e($gd['TrangThaiThanhToan']); ?>
                                        </span>
                                    <?php } ?>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($gd['ThoiGianThanhToan'])
                                        ? e(date('d/m/Y H:i', strtotime($gd['ThoiGianThanhToan'])))
                                        : '—';
                                    ?>
                                </td>
                            </tr>
                        <?php } ?>

                    <?php } else { ?>

                        <tr>
                            <td colspan="6" class="empty">Chưa có giao dịch</td>
                        </tr>

                    <?php } ?>

                    </tbody>
                </table>
            </div>

            <div class="detail-actions">

                <a href="<?php
                    echo e('quan-ly-lich.php?' . http_build_query(array_filter([
                        'trang_thai' => $trang_thai,
                        'tien_coc' => $tien_coc
                    ])));
                ?>" class="btn btn-primary">
                    Đóng chi tiết
                </a>

                <?php if (
                    !daCoc($conn, $chi_tiet['MaLichDat']) &&
                    !daHoanCoc($conn, $chi_tiet['MaLichDat']) &&
                    in_array($chi_tiet['TrangThai'], ['Đang giữ chỗ', 'Đã đặt'], true)
                ) { ?>

                    <form method="POST"
                          onsubmit="return confirm('Chỉ xác nhận sau khi đã kiểm tra tiền thực tế nhận được. Tiếp tục?')">

                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="ma_lich" value="<?php echo (int)$chi_tiet['MaLichDat']; ?>">
                        <input type="hidden" name="action" value="xac_nhan_coc">
                        <input type="hidden" name="chi_tiet" value="<?php echo (int)$chi_tiet['MaLichDat']; ?>">
                        <input type="hidden" name="trang_thai" value="<?php echo e($trang_thai); ?>">
                        <input type="hidden" name="tien_coc" value="<?php echo e($tien_coc); ?>">

                        <button type="submit" class="btn btn-warning">
                            <?php
                            echo dangChoXacNhanCoc($conn, $chi_tiet['MaLichDat'])
                                ? 'Xác nhận đã nhận cọc'
                                : 'Nhận cọc tiền mặt';
                            ?>
                        </button>

                    </form>

                <?php } ?>

                <?php if (
                    $chi_tiet['TrangThai'] === 'Đang giữ chỗ' &&
                    daCoc($conn, $chi_tiet['MaLichDat']) &&
                    !daHoanCoc($conn, $chi_tiet['MaLichDat'])
                ) { ?>

                    <form method="POST"
                          onsubmit="return confirm('Xác nhận lịch đặt này?')">

                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="ma_lich" value="<?php echo (int)$chi_tiet['MaLichDat']; ?>">
                        <input type="hidden" name="action" value="xac_nhan">

                        <button type="submit" class="btn btn-success">
                            Xác nhận lịch
                        </button>

                    </form>

                <?php } ?>

                <?php if (
                    daCoc($conn, $chi_tiet['MaLichDat']) &&
                    !daHoanCoc($conn, $chi_tiet['MaLichDat']) &&
                    $chi_tiet['TrangThai'] === 'Đã hủy'
                ) { ?>

                    <form method="POST"
                          onsubmit="return confirm('Chỉ ghi nhận sau khi đã hoàn tiền thực tế cho khách. Tiếp tục?')">

                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="ma_lich" value="<?php echo (int)$chi_tiet['MaLichDat']; ?>">
                        <input type="hidden" name="action" value="hoan_coc">

                        <button type="submit" class="btn btn-info">
                            Ghi nhận hoàn cọc
                        </button>

                    </form>

                <?php } ?>

                <?php if (
                    in_array($chi_tiet['TrangThai'], ['Đang giữ chỗ', 'Đã đặt'], true)
                ) { ?>

                    <form method="POST"
                          onsubmit="return confirm('Bạn có chắc muốn hủy lịch này?')">

                        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="ma_lich" value="<?php echo (int)$chi_tiet['MaLichDat']; ?>">
                        <input type="hidden" name="action" value="huy">

                        <button type="submit" class="btn btn-danger">
                            Hủy lịch
                        </button>

                    </form>

                <?php } ?>

            </div>

        </div>

    <?php } ?>

    <!-- DANH SÁCH LỊCH ĐẶT -->

    <div class="card">

        <div class="filter">

            <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;">

                <select name="trang_thai" onchange="this.form.submit()">
                    <option value="">-- Tất cả trạng thái --</option>

                    <?php foreach ($cac_trang_thai as $tt) { ?>
                        <option value="<?php echo e($tt); ?>"
                            <?php echo $trang_thai === $tt ? 'selected' : ''; ?>>
                            <?php echo e($tt); ?>
                        </option>
                    <?php } ?>
                </select>

                <select name="tien_coc" onchange="this.form.submit()">
                    <option value="">-- Tất cả trạng thái cọc --</option>

                    <option value="chua_coc" <?php echo $tien_coc === 'chua_coc' ? 'selected' : ''; ?>>
                        Chưa cọc
                    </option>

                    <option value="dang_xu_ly" <?php echo $tien_coc === 'dang_xu_ly' ? 'selected' : ''; ?>>
                        Đang chờ xác nhận
                    </option>

                    <option value="da_coc" <?php echo $tien_coc === 'da_coc' ? 'selected' : ''; ?>>
                        Đã cọc
                    </option>

                    <option value="da_hoan_coc" <?php echo $tien_coc === 'da_hoan_coc' ? 'selected' : ''; ?>>
                        Đã hoàn cọc
                    </option>
                </select>

                <a href="quan-ly-lich.php"
                   class="btn btn-primary"
                   style="padding:10px 15px;">
                    Xóa bộ lọc
                </a>

            </form>

        </div>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Mã</th>
                        <th>Khách hàng</th>
                        <th>Sân</th>
                        <th>Ngày</th>
                        <th>Giờ</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th>Cọc</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (mysqli_num_rows($result) > 0) { ?>

                    <?php while ($row = mysqli_fetch_assoc($result)) { ?>

                        <?php
                        $ma = (int)$row['MaLichDat'];
                        $coc = trangThaiCoc($conn, $ma);
                        ?>

                        <tr>

                            <td>#<?php echo $ma; ?></td>
                            <td><?php echo e($row['HoTen']); ?></td>
                            <td><?php echo e($row['TenSan']); ?></td>

                            <td>
                                <?php echo e(date('d/m/Y', strtotime($row['NgayDat']))); ?>
                            </td>

                            <td>
                                <?php echo e(!empty($row['ThoiGianBatDau']) ? substr($row['ThoiGianBatDau'], 0, 5) : '--:--'); ?>
                                -
                                <?php echo e(!empty($row['ThoiGianKetThuc']) ? substr($row['ThoiGianKetThuc'], 0, 5) : '--:--'); ?>
                            </td>

                            <td>
                                <?php echo number_format((float)$row['TongTien'], 0, ',', '.'); ?>đ
                            </td>

                            <td><?php echo badgeTrangThai($row['TrangThai']); ?></td>

                            <td><?php echo badgeCoc($coc); ?></td>

                            <td>
                                <div class="actions">

                                    <a href="quan-ly-lich.php?chi_tiet=<?php echo $ma; ?>&trang_thai=<?php echo urlencode($trang_thai); ?>&tien_coc=<?php echo urlencode($tien_coc); ?>"
                                       class="btn btn-info">
                                        Chi tiết
                                    </a>

                                    <?php if (
                                        $row['TrangThai'] === 'Đang giữ chỗ' &&
                                        daCoc($conn, $ma) &&
                                        !daHoanCoc($conn, $ma)
                                    ) { ?>

                                        <form method="POST"
                                              onsubmit="return confirm('Xác nhận lịch đặt này?')">

                                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="ma_lich" value="<?php echo $ma; ?>">
                                            <input type="hidden" name="action" value="xac_nhan">

                                            <button type="submit" class="btn btn-success">
                                                Xác nhận
                                            </button>

                                        </form>

                                    <?php } ?>

                                    <?php if (
                                        !daCoc($conn, $ma) &&
                                        !daHoanCoc($conn, $ma) &&
                                        in_array($row['TrangThai'], ['Đang giữ chỗ', 'Đã đặt'], true)
                                    ) { ?>

                                        <form method="POST"
                                              onsubmit="return confirm('Chỉ xác nhận sau khi kiểm tra đã nhận tiền thực tế. Tiếp tục?')">

                                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="ma_lich" value="<?php echo $ma; ?>">
                                            <input type="hidden" name="action" value="xac_nhan_coc">
                                            <input type="hidden" name="trang_thai" value="<?php echo e($trang_thai); ?>">
                                            <input type="hidden" name="tien_coc" value="<?php echo e($tien_coc); ?>">

                                            <button type="submit" class="btn btn-warning">
                                                <?php
                                                echo dangChoXacNhanCoc($conn, $ma)
                                                    ? 'Xác nhận cọc'
                                                    : 'Nhận cọc';
                                                ?>
                                            </button>

                                        </form>

                                    <?php } ?>

                                    <?php if ($row['TrangThai'] === 'Đã đặt') { ?>

                                        <form method="POST"
                                              onsubmit="return confirm('Xác nhận lịch đã hoàn thành?')">

                                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="ma_lich" value="<?php echo $ma; ?>">
                                            <input type="hidden" name="action" value="hoan_thanh">

                                            <button type="submit" class="btn btn-primary">
                                                Hoàn thành
                                            </button>

                                        </form>

                                    <?php } ?>

                                    <?php if (
                                        in_array($row['TrangThai'], ['Đã đặt', 'Đang giữ chỗ'], true)
                                    ) { ?>

                                        <form method="POST"
                                              onsubmit="return confirm('Bạn có chắc muốn hủy lịch này?')">

                                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="ma_lich" value="<?php echo $ma; ?>">
                                            <input type="hidden" name="action" value="huy">

                                            <button type="submit" class="btn btn-danger">
                                                Hủy
                                            </button>

                                        </form>

                                    <?php } ?>

                                </div>
                            </td>

                        </tr>

                    <?php } ?>

                <?php } else { ?>

                    <tr>
                        <td colspan="9" class="empty">
                            Không có lịch đặt nào
                        </td>
                    </tr>

                <?php } ?>

                </tbody>
            </table>

        </div>

    </div>

</div>

</body>
</html>

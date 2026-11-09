<?php
// customer/qr_payment.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
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

$maND = (int)$_SESSION['MaND'];

$error = '';
$success = '';

$paymentPending = false;
$paymentSuccess = false;
$bookingExpired = false;

// ========================================
// 2. CSRF
// ========================================

if (empty($_SESSION['csrf_payment'])) {
    $_SESSION['csrf_payment'] = bin2hex(random_bytes(32));
}

// ========================================
// 3. THÔNG TIN TÀI KHOẢN NHẬN TIỀN
// ========================================

$bankCode = 'TCB';
$bankAccount = '19074129932011';
$accountName = 'CAO TRAN TAN DAT';

// ========================================
// 4. XÁC ĐỊNH LOẠI ĐẶT SÂN
// ========================================

// Đặt trong giờ: lấy MaLichDat từ pending_booking trong SESSION.
// Đặt ngoài giờ: lấy MaLichDat từ đường dẫn ?id=...

$isOutsidePayment = false;
$maLichDat = 0;
$expiryTime = 0;

if (isset($_GET['id'])) {

    // Thanh toán yêu cầu ngoài giờ đã được Admin duyệt
    $maLichDat = (int)$_GET['id'];
    $isOutsidePayment = true;

    if ($maLichDat <= 0) {
        die("Mã lịch đặt không hợp lệ.");
    }

} else {

    // Thanh toán đặt sân thông thường
    if (
        !isset($_SESSION['pending_booking']) ||
        !is_array($_SESSION['pending_booking'])
    ) {
        header("Location: booking_demo.php");
        exit();
    }

    $bookingSession = $_SESSION['pending_booking'];

    $maLichDat = (int)($bookingSession['MaLichDat'] ?? 0);
    $expiryTime = (int)($bookingSession['expiry'] ?? 0);

    if ($maLichDat <= 0) {
        unset($_SESSION['pending_booking']);
        header("Location: booking_demo.php");
        exit();
    }
}

// ========================================
// 5. HÀM LẤY THÔNG TIN LỊCH ĐẶT
// ========================================

function getBookingInfo($conn, $maLichDat, $maND)
{
    $stmt = $conn->prepare("
        SELECT
            l.MaLichDat,
            l.MaND,
            l.MaSan,
            l.NgayDat,
            l.ThoiGianBatDau,
            l.ThoiGianKetThuc,
            l.TongTien,
            l.TrangThai,
            l.LoaiDat,
            l.NgayTao,
            s.TenSan
        FROM lichdat l
        LEFT JOIN san s ON l.MaSan = s.MaSan
        WHERE l.MaLichDat = ?
          AND l.MaND = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception("Không thể truy vấn lịch đặt.");
    }

    $stmt->bind_param("ii", $maLichDat, $maND);
    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $result;
}

// ========================================
// 6. LẤY LỊCH ĐẶT TỪ DATABASE
// ========================================

try {

    $bookingDB = getBookingInfo($conn, $maLichDat, $maND);

    if (!$bookingDB) {
        die("Không tìm thấy lịch đặt của bạn.");
    }

    // Nếu truy cập bằng ?id= thì chỉ cho phép thanh toán lịch ngoài giờ
    if (
        $isOutsidePayment &&
        ($bookingDB['LoaiDat'] ?? '') !== 'Ngoài giờ'
    ) {
        die("Đường dẫn thanh toán không hợp lệ.");
    }

    // Nếu đặt ngoài giờ, thời hạn giữ chỗ tính từ lúc tạo lịch.
    // Admin duyệt và tạo lịch giữ chỗ tại thời điểm đó.
    if ($isOutsidePayment) {

        $createdTime = !empty($bookingDB['NgayTao'])
            ? strtotime($bookingDB['NgayTao'])
            : time();

        $expiryTime = $createdTime + 900; // 15 phút
    }

} catch (Throwable $e) {

    error_log("Lỗi lấy lịch đặt: " . $e->getMessage());

    die("Không thể tải thông tin lịch đặt. Vui lòng thử lại.");
}

// ========================================
// 7. TÍNH TIỀN CỌC 50%
// ========================================

$total = (float)$bookingDB['TongTien'];
$deposit = (int)round($total * 0.5);

$transferContent = 'DATSAN ' . $maLichDat;

if ($total <= 0 || $deposit <= 0) {
    die("Số tiền thanh toán không hợp lệ.");
}

// ========================================
// 8. KIỂM TRA GIAO DỊCH
// ========================================

function getPaymentInfo($conn, $maLichDat)
{
    $stmt = $conn->prepare("
        SELECT
            SUM(
                CASE
                    WHEN TrangThaiThanhToan = 'Đang xử lý'
                    THEN 1 ELSE 0
                END
            ) AS DangXuLy,

            SUM(
                CASE
                    WHEN TrangThaiThanhToan = 'Thành công'
                    THEN 1 ELSE 0
                END
            ) AS ThanhCong

        FROM thanhtoan
        WHERE MaLichDat = ?
          AND LoaiGiaoDich = 'Đặt cọc'
    ");

    if (!$stmt) {
        throw new Exception("Không thể kiểm tra giao dịch.");
    }

    $stmt->bind_param("i", $maLichDat);
    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return [
        'pending' => (int)($result['DangXuLy'] ?? 0),
        'success' => (int)($result['ThanhCong'] ?? 0)
    ];
}

// ========================================
// 9. KIỂM TRA HẾT HẠN GIỮ CHỖ
// ========================================

try {

    $paymentInfo = getPaymentInfo($conn, $maLichDat);

    $paymentPending = $paymentInfo['pending'] > 0;
    $paymentSuccess = $paymentInfo['success'] > 0;

    $timeLeft = max(0, $expiryTime - time());

    // Chỉ xử lý hết hạn nếu chưa gửi yêu cầu thanh toán
    // và chưa có giao dịch thành công.
    if (
        $expiryTime > 0 &&
        time() >= $expiryTime &&
        $bookingDB['TrangThai'] === 'Đang giữ chỗ' &&
        !$paymentPending &&
        !$paymentSuccess
    ) {

        $conn->begin_transaction();

        $stmt = $conn->prepare("
            SELECT TrangThai
            FROM lichdat
            WHERE MaLichDat = ?
              AND MaND = ?
            FOR UPDATE
        ");

        $stmt->bind_param("ii", $maLichDat, $maND);
        $stmt->execute();

        $currentBooking = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (
            $currentBooking &&
            $currentBooking['TrangThai'] === 'Đang giữ chỗ'
        ) {

            $stmt = $conn->prepare("
                UPDATE lichdat
                SET TrangThai = 'Đã hủy'
                WHERE MaLichDat = ?
                  AND MaND = ?
                  AND TrangThai = 'Đang giữ chỗ'
            ");

            $stmt->bind_param("ii", $maLichDat, $maND);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();

        $bookingDB = getBookingInfo($conn, $maLichDat, $maND);
    }

} catch (Throwable $e) {

    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        error_log("Rollback lỗi: " . $rollbackError->getMessage());
    }

    error_log("Lỗi kiểm tra hết hạn: " . $e->getMessage());

    $error = "Không thể kiểm tra thời gian giữ chỗ.";
}

// ========================================
// 10. CẬP NHẬT TRẠNG THÁI
// ========================================

try {

    $bookingDB = getBookingInfo($conn, $maLichDat, $maND);

    if (!$bookingDB) {
        die("Không tìm thấy lịch đặt.");
    }

    $paymentInfo = getPaymentInfo($conn, $maLichDat);

    $paymentPending = (
        $paymentInfo['pending'] > 0 &&
        $paymentInfo['success'] <= 0
    );

    $paymentSuccess = $paymentInfo['success'] > 0;

    if ($bookingDB['TrangThai'] === 'Đã hủy') {
        $bookingExpired = true;
    }

    if ($paymentSuccess) {
        $success = "Giao dịch đặt cọc đã được Admin xác nhận.";
    } elseif ($bookingDB['TrangThai'] === 'Đã đặt') {
        $success = "Lịch đặt của bạn đã được Admin xác nhận.";
    }

} catch (Throwable $e) {

    error_log("Lỗi cập nhật trạng thái: " . $e->getMessage());

    $error = "Không thể kiểm tra trạng thái thanh toán.";
}

// ========================================
// 11. KHÁCH XÁC NHẬN ĐÃ CHUYỂN KHOẢN
// ========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['confirm_transfer'])
) {

    $csrf = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrf) ||
        !hash_equals($_SESSION['csrf_payment'], $csrf)
    ) {

        $error = "Phiên làm việc không hợp lệ. Vui lòng tải lại trang.";

    } elseif ($bookingExpired) {

        $error = "Lịch đặt đã hết hạn hoặc đã bị hủy.";

    } elseif ($paymentPending) {

        $error = "Bạn đã gửi yêu cầu thanh toán. Vui lòng chờ Admin kiểm tra.";

    } elseif ($paymentSuccess) {

        $error = "Giao dịch đặt cọc đã được xác nhận.";

    } elseif ($bookingDB['TrangThai'] !== 'Đang giữ chỗ') {

        $error = "Lịch đặt không còn ở trạng thái giữ chỗ.";

    } elseif ($expiryTime <= 0 || time() >= $expiryTime) {

        $error = "Đã hết thời gian giữ chỗ. Vui lòng đặt sân lại.";

    } else {

        try {

            $conn->begin_transaction();

            // Khóa lịch đặt
            $stmt = $conn->prepare("
                SELECT TrangThai, TongTien
                FROM lichdat
                WHERE MaLichDat = ?
                  AND MaND = ?
                FOR UPDATE
            ");

            $stmt->bind_param("ii", $maLichDat, $maND);
            $stmt->execute();

            $currentBooking = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (
                !$currentBooking ||
                $currentBooking['TrangThai'] !== 'Đang giữ chỗ'
            ) {
                throw new Exception(
                    "Lịch đặt không còn ở trạng thái giữ chỗ."
                );
            }

            // Kiểm tra lại thời gian
            if (time() >= $expiryTime) {
                throw new Exception(
                    "Đã hết thời gian giữ chỗ. Vui lòng đặt sân lại."
                );
            }

            // Tính lại tiền cọc từ database
            $deposit = (int)round(
                (float)$currentBooking['TongTien'] * 0.5
            );

            if ($deposit <= 0) {
                throw new Exception("Số tiền cọc không hợp lệ.");
            }

            // Kiểm tra giao dịch đặt cọc đã tồn tại
            $stmt = $conn->prepare("
                SELECT MaThanhToan
                FROM thanhtoan
                WHERE MaLichDat = ?
                  AND LoaiGiaoDich = 'Đặt cọc'
                  AND TrangThaiThanhToan IN (
                      'Đang xử lý',
                      'Thành công'
                  )
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->bind_param("i", $maLichDat);
            $stmt->execute();

            $existingPayment = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if ($existingPayment) {
                throw new Exception(
                    "Đã tồn tại yêu cầu thanh toán. Vui lòng chờ Admin kiểm tra."
                );
            }

            // Tạo giao dịch đặt cọc
            $paymentMethod = 'Chuyển khoản QR';
            $transactionType = 'Đặt cọc';
            $paymentStatus = 'Đang xử lý';

            $stmt = $conn->prepare("
                INSERT INTO thanhtoan (
                    MaLichDat,
                    SoTien,
                    PhuongThuc,
                    LoaiGiaoDich,
                    TrangThaiThanhToan,
                    ThoiGianThanhToan
                )
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $stmt->bind_param(
                "idsss",
                $maLichDat,
                $deposit,
                $paymentMethod,
                $transactionType,
                $paymentStatus
            );

            if (!$stmt->execute()) {
                throw new Exception("Không thể lưu giao dịch.");
            }

            $stmt->close();

            // Ghi chú cho Admin
            $note = 'Khách hàng đã gửi yêu cầu xác nhận chuyển khoản cọc.';

            $stmt = $conn->prepare("
                UPDATE lichdat
                SET GhiChu = ?
                WHERE MaLichDat = ?
                  AND MaND = ?
            ");

            $stmt->bind_param("sii", $note, $maLichDat, $maND);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $paymentPending = true;

            $success = "Đã gửi yêu cầu xác nhận chuyển khoản. Vui lòng chờ Admin kiểm tra giao dịch thực tế.";

        } catch (Throwable $e) {

            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
                error_log("Rollback thanh toán: " . $rollbackError->getMessage());
            }

            $error = $e->getMessage();

            error_log("Lỗi tạo giao dịch: " . $e->getMessage());
        }

        // Tải lại trạng thái sau khi xử lý
        try {

            $bookingDB = getBookingInfo($conn, $maLichDat, $maND);
            $paymentInfo = getPaymentInfo($conn, $maLichDat);

            $paymentSuccess = $paymentInfo['success'] > 0;

            $paymentPending = (
                $paymentInfo['pending'] > 0 &&
                !$paymentSuccess
            );

            $bookingExpired = (
                $bookingDB['TrangThai'] === 'Đã hủy'
            );

        } catch (Throwable $e) {
            error_log("Lỗi tải lại trạng thái: " . $e->getMessage());
        }
    }
}

// ========================================
// 12. TÍNH THỜI GIAN CÒN LẠI
// ========================================

$timeLeft = max(0, $expiryTime - time());

// ========================================
// 13. TẠO MÃ QR VIETQR
// ========================================

$qrUrl = "https://img.vietqr.io/image/"
    . rawurlencode($bankCode) . "-"
    . rawurlencode($bankAccount)
    . "-compact2.png?amount="
    . $deposit
    . "&addInfo="
    . urlencode($transferContent)
    . "&accountName="
    . urlencode($accountName);

$backUrl = $isOutsidePayment
    ? 'lich-su-yeu-cau.php'
    : 'booking_demo.php';

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Thanh Toán Cọc QR - Volleyball Arena</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-[#181412] text-amber-50 min-h-screen font-sans flex flex-col justify-between">

<header class="bg-[#26201c] border-b border-amber-900/40 px-6 py-4 flex justify-between items-center shadow-lg">

    <a href="<?php echo htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8'); ?>"
       class="text-xs text-stone-300 hover:text-amber-400">
        ← Quay lại
    </a>

    <span class="text-xs font-bold text-amber-300">
        Cổng thanh toán VietQR
    </span>

</header>

<main class="max-w-xl mx-auto px-6 py-10 w-full space-y-6">

    <?php if ($bookingExpired) { ?>

        <div class="bg-red-500/10 border border-red-500/40 text-red-400 p-5 rounded-xl text-center">

            <h2 class="font-black text-lg mb-2">
                Lịch đặt đã bị hủy
            </h2>

            <p class="text-sm mb-4">
                Thời gian giữ chỗ đã kết thúc và chưa có giao dịch đặt cọc đang xử lý hoặc thành công.
                Bạn vui lòng đặt sân lại.
            </p>

            <a href="<?php echo htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8'); ?>"
               class="inline-block bg-amber-500 text-stone-950 px-5 py-3 rounded-xl font-bold text-xs">
                QUAY LẠI
            </a>

        </div>

    <?php } else { ?>

        <?php if ($error !== '') { ?>

            <div class="bg-red-500/10 border border-red-500/40 text-red-400 p-4 rounded-xl text-xs font-bold text-center">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>

        <?php } ?>

        <?php if ($success !== '') { ?>

            <div class="bg-emerald-500/10 border border-emerald-500/40 text-emerald-400 p-4 rounded-xl text-xs font-bold text-center">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>

        <?php } ?>

        <?php if (
            !$paymentPending &&
            !$paymentSuccess &&
            $bookingDB['TrangThai'] === 'Đang giữ chỗ'
        ) { ?>

            <div id="timer_box"
                 class="bg-amber-500/10 border border-amber-500/40 text-amber-300 p-3 rounded-xl text-xs font-bold text-center flex justify-center items-center gap-2">

                <span>⏳ Thời gian giữ chỗ:</span>

                <span id="countdown" class="font-mono text-sm text-amber-400 font-black">
                    15:00
                </span>

            </div>

        <?php } ?>

        <div class="bg-[#26201c] border border-amber-900/50 p-8 rounded-3xl shadow-2xl space-y-6 text-center">

            <div class="space-y-2">

                <h1 class="text-xl font-black uppercase text-amber-100">
                    Quét mã QR cọc 50%
                </h1>

                <p class="text-xs text-stone-400">
                    Sử dụng ứng dụng ngân hàng để quét mã.
                </p>

                <span class="inline-block bg-amber-500/10 text-amber-300 border border-amber-500/30 px-3 py-1 rounded-full text-[10px] font-bold">
                    <?php
                    echo ($bookingDB['LoaiDat'] === 'Ngoài giờ')
                        ? 'ĐẶT SÂN NGOÀI GIỜ'
                        : 'ĐẶT SÂN TRONG GIỜ';
                    ?>
                </span>

            </div>

            <?php if (!$paymentSuccess && !$paymentPending) { ?>

                <div class="bg-white p-4 rounded-2xl inline-block shadow-lg border-4 border-amber-500/30">

                    <img
                        src="<?php echo htmlspecialchars($qrUrl, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="Mã QR VietQR"
                        class="w-64 h-64 object-contain mx-auto"
                    >

                </div>

            <?php } ?>

            <div class="bg-[#1c1815] border border-amber-900/30 p-4 rounded-xl text-left space-y-3 text-xs">

                <div class="flex justify-between gap-3">
                    <span class="text-stone-400">Sân:</span>
                    <span class="font-bold text-amber-200">
                        <?php echo htmlspecialchars($bookingDB['TenSan'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>

                <div class="flex justify-between gap-3">
                    <span class="text-stone-400">Ngày đặt:</span>
                    <span class="font-bold text-amber-200">
                        <?php echo date('d/m/Y', strtotime($bookingDB['NgayDat'])); ?>
                    </span>
                </div>

                <div class="flex justify-between gap-3">
                    <span class="text-stone-400">Giờ chơi:</span>
                    <span class="font-bold text-amber-200">
                        <?php
                        echo date('H:i', strtotime($bookingDB['ThoiGianBatDau']))
                            . ' - '
                            . date('H:i', strtotime($bookingDB['ThoiGianKetThuc']));
                        ?>
                    </span>
                </div>

                <div class="flex justify-between gap-3">
                    <span class="text-stone-400">Mã lịch đặt:</span>
                    <span class="font-bold text-amber-200">
                        #<?php echo (int)$maLichDat; ?>
                    </span>
                </div>

                <div class="flex justify-between gap-3">
                    <span class="text-stone-400">Tổng tiền:</span>
                    <span class="font-bold text-amber-200">
                        <?php echo number_format($total, 0, ',', '.'); ?> đ
                    </span>
                </div>

                <div class="flex justify-between gap-3">
                    <span class="text-stone-400">Ngân hàng:</span>
                    <span class="font-bold text-amber-200">
                        Techcombank (TCB)
                    </span>
                </div>

                <div class="flex justify-between gap-3">
                    <span class="text-stone-400">Chủ tài khoản:</span>
                    <span class="font-bold text-amber-200">
                        <?php echo htmlspecialchars($accountName, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>

                <div class="flex justify-between gap-3">
                    <span class="text-stone-400">Số tài khoản:</span>
                    <span class="font-mono font-bold text-amber-300">
                        <?php echo htmlspecialchars($bankAccount, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>

                <div class="flex justify-between gap-3">
                    <span class="text-stone-400">Nội dung chuyển khoản:</span>
                    <span class="font-bold text-amber-300">
                        <?php echo htmlspecialchars($transferContent, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>

                <div class="flex justify-between border-t border-amber-900/30 pt-3">

                    <span class="font-bold text-emerald-400">
                        Số tiền cọc 50%:
                    </span>

                    <span class="font-black text-emerald-400 text-sm">
                        <?php echo number_format($deposit, 0, ',', '.'); ?> đ
                    </span>

                </div>

            </div>

            <?php if ($paymentSuccess) { ?>

                <div class="bg-emerald-500/10 border border-emerald-500/30 p-4 rounded-xl text-emerald-400 text-xs">

                    <p class="font-black mb-2">
                        ✓ GIAO DỊCH ĐẶT CỌC ĐÃ THÀNH CÔNG
                    </p>

                    <p>
                        Hệ thống đã ghi nhận giao dịch đặt cọc thành công.
                    </p>

                </div>

            <?php } elseif ($bookingDB['TrangThai'] === 'Đã đặt') { ?>

                <div class="bg-emerald-500/10 border border-emerald-500/30 p-4 rounded-xl text-emerald-400 text-xs font-bold">
                    Lịch đặt của bạn đã được Admin xác nhận.
                </div>

            <?php } elseif ($paymentPending) { ?>

                <div class="bg-amber-500/10 border border-amber-500/30 p-4 rounded-xl text-amber-300 text-xs">

                    <p class="font-black mb-2">
                        ⏳ ĐANG CHỜ ADMIN XÁC NHẬN
                    </p>

                    <p>
                        Hệ thống đã ghi nhận yêu cầu của bạn.
                        Admin cần kiểm tra giao dịch thực tế trước khi xác nhận lịch đặt.
                    </p>

                </div>

            <?php } elseif ($bookingDB['TrangThai'] === 'Đang giữ chỗ') { ?>

                <?php if ($expiryTime > 0 && time() < $expiryTime) { ?>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['csrf_payment'], ENT_QUOTES, 'UTF-8'); ?>"
                        >

                        <button
                            type="submit"
                            name="confirm_transfer"
                            value="1"
                            class="w-full bg-gradient-to-r from-emerald-600 via-emerald-500 to-emerald-600 hover:from-emerald-500 hover:to-emerald-400 text-stone-950 font-black py-4 rounded-xl shadow-lg transition uppercase tracking-wider text-xs cursor-pointer"
                        >
                            ✓ TÔI ĐÃ CHUYỂN KHOẢN
                        </button>

                    </form>

                    <p class="text-[10px] text-stone-500">
                        Chỉ bấm sau khi bạn đã thực hiện chuyển khoản.
                        Admin sẽ kiểm tra giao dịch thực tế trước khi xác nhận.
                    </p>

                <?php } else { ?>

                    <div class="w-full bg-red-500/10 border border-red-500/30 text-red-400 font-bold py-4 px-3 rounded-xl text-center text-xs">
                        ĐÃ HẾT THỜI GIAN GIỮ CHỖ
                    </div>

                <?php } ?>

            <?php } else { ?>

                <div class="w-full bg-red-500/10 border border-red-500/30 text-red-400 font-bold py-4 px-3 rounded-xl text-center text-xs">
                    LỊCH ĐẶT KHÔNG CÒN HỢP LỆ
                </div>

            <?php } ?>

        </div>

    <?php } ?>

</main>

<footer class="bg-[#120f0d] border-t border-amber-900/40 py-6 text-center text-xs text-stone-500">

    <p>© 2026 Volleyball Arena. Secure Transaction Gateway.</p>

</footer>

<script>
let timeLeft = <?php echo (int)$timeLeft; ?>;

const countdownEl = document.getElementById('countdown');

if (countdownEl) {

    function updateCountdown() {

        if (timeLeft <= 0) {

            clearInterval(timer);

            countdownEl.innerText = '00:00';

            alert('Đã hết thời gian giữ chỗ. Hệ thống sẽ kiểm tra và xử lý lịch.');

            window.location.reload();

            return;
        }

        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;

        countdownEl.innerText =
            String(minutes).padStart(2, '0') + ':' +
            String(seconds).padStart(2, '0');

        timeLeft--;
    }

    updateCountdown();

    const timer = setInterval(updateCountdown, 1000);
}
</script>

</body>
</html>
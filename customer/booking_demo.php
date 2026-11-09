
<?php
session_start();
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
$error = "";

// ========================================
// 2. HÀM HỖ TRỢ
// ========================================

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function layGio($value) {
    return substr((string)$value, 0, 5);
}

// CSRF token
if (empty($_SESSION['csrf_booking'])) {
    $_SESSION['csrf_booking'] = bin2hex(random_bytes(32));
}

// ========================================
// 3. LẤY DANH SÁCH SÂN ĐANG HOẠT ĐỘNG
// ========================================

$sqlSan = "
    SELECT MaSan, TenSan
    FROM san
    WHERE TrangThai = 'Hoạt động'
    ORDER BY MaSan ASC
";

$resultSan = mysqli_query($conn, $sqlSan);

if (!$resultSan) {
    die("Không thể tải danh sách sân: " . e(mysqli_error($conn)));
}

$courts = [];

while ($row = mysqli_fetch_assoc($resultSan)) {
    $courts[] = $row;
}

if (empty($courts)) {
    die("Hiện chưa có sân hoạt động. Vui lòng liên hệ Admin.");
}

// ========================================
// 4. LẤY BẢNG GIÁ
// ========================================

$sqlKhung = "
    SELECT
        MaKhungGio,
        ThoiGianBatDau,
        ThoiGianKetThuc,
        GiaNgayThuong,
        GiaCuoiTuan
    FROM khunggio
    ORDER BY ThoiGianBatDau ASC
";

$resultKhung = mysqli_query($conn, $sqlKhung);

if (!$resultKhung) {
    die("Không thể tải bảng giá: " . e(mysqli_error($conn)));
}

$priceBands = [];

while ($row = mysqli_fetch_assoc($resultKhung)) {
    $priceBands[] = [
        'start' => layGio($row['ThoiGianBatDau']),
        'end' => layGio($row['ThoiGianKetThuc']),
        'weekday' => (float)$row['GiaNgayThuong'],
        'weekend' => (float)$row['GiaCuoiTuan']
    ];
}

if (empty($priceBands)) {
    die("Chưa có bảng giá khung giờ trong database.");
}

// ========================================
// 5. DANH SÁCH 7 NGÀY
// ========================================

$today = date('Y-m-d');
$lastDate = date('Y-m-d', strtotime('+6 days'));

$days = [];

$weekdays = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];

for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));

    $days[] = [
        'date' => $date,
        'display' => date('d/m', strtotime($date)),
        'weekday' => $weekdays[(int)date('w', strtotime($date))]
    ];
}

// ========================================
// 6. LẤY LỊCH ĐÃ ĐẶT
// ========================================

$bookedData = [];

foreach ($courts as $court) {
    $bookedData[(int)$court['MaSan']] = [];
}

$sqlBooked = "
    SELECT
        l.MaSan,
        l.NgayDat,
        COALESCE(l.ThoiGianBatDau, k.ThoiGianBatDau) AS GioBatDau,
        COALESCE(l.ThoiGianKetThuc, k.ThoiGianKetThuc) AS GioKetThuc
    FROM lichdat l
    LEFT JOIN khunggio k
        ON l.MaKhungGio = k.MaKhungGio
    WHERE l.NgayDat BETWEEN ? AND ?
      AND l.TrangThai != 'Đã hủy'
";

$stmtBooked = mysqli_prepare($conn, $sqlBooked);

if (!$stmtBooked) {
    die("Không thể tải lịch đặt.");
}

mysqli_stmt_bind_param($stmtBooked, "ss", $today, $lastDate);
mysqli_stmt_execute($stmtBooked);

$resultBooked = mysqli_stmt_get_result($stmtBooked);

while ($row = mysqli_fetch_assoc($resultBooked)) {

    $maSanBooked = (int)$row['MaSan'];
    $dateBooked = $row['NgayDat'];

    if (!isset($bookedData[$maSanBooked])) {
        continue;
    }

    if (empty($row['GioBatDau']) || empty($row['GioKetThuc'])) {
        continue;
    }

    $startHour = (int)substr($row['GioBatDau'], 0, 2);
    $endHour = (int)substr($row['GioKetThuc'], 0, 2);

    for ($h = $startHour; $h < $endHour; $h++) {
        $hour = sprintf('%02d:00', $h);
        $bookedData[$maSanBooked][$dateBooked][] = $hour;
    }
}

mysqli_stmt_close($stmtBooked);

// ========================================
// 7. GIÁ TRỊ MẶC ĐỊNH
// ========================================

$selectedCourt = (int)$courts[0]['MaSan'];
$selectedDate = $today;
$selectedStart = '';
$selectedEnd = '';

// ========================================
// 8. XỬ LÝ ĐẶT SÂN
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $selectedCourt = (int)($_POST['court'] ?? 0);
    $selectedDate = trim($_POST['date'] ?? '');
    $selectedStart = trim($_POST['start_time'] ?? '');
    $selectedEnd = trim($_POST['end_time'] ?? '');

    // 8.1 KIỂM TRA CSRF

    $csrf = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrf) ||
        !hash_equals($_SESSION['csrf_booking'], $csrf)
    ) {
        $error = "Phiên làm việc không hợp lệ. Vui lòng tải lại trang.";
    }

    // 8.2 KIỂM TRA SÂN

    $courtExists = false;
    $tenSan = '';

    foreach ($courts as $court) {
        if ((int)$court['MaSan'] === $selectedCourt) {
            $courtExists = true;
            $tenSan = $court['TenSan'];
            break;
        }
    }

    if ($error === '' && !$courtExists) {
        $error = "Sân được chọn không hợp lệ hoặc đang bảo trì.";
    }

    // 8.3 KIỂM TRA NGÀY

    if ($error === '') {

        $dateObject = DateTime::createFromFormat('!Y-m-d', $selectedDate);

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $selectedDate ||
            $selectedDate < $today ||
            $selectedDate > $lastDate
        ) {
            $error = "Ngày đặt không hợp lệ. Chỉ được đặt trong 7 ngày tới.";
        }
    }

    // 8.4 KIỂM TRA GIỜ

    if ($error === '') {

        $validTime = '/^(0[6-9]|1[0-9]|2[0-2]):00$/';

        if (
            !preg_match($validTime, $selectedStart) ||
            !preg_match($validTime, $selectedEnd)
        ) {
            $error = "Vui lòng chọn giờ trong khoảng 06:00 đến 22:00.";
        } elseif ($selectedStart >= $selectedEnd) {
            $error = "Giờ kết thúc phải lớn hơn giờ bắt đầu.";
        }
    }

    // 8.5 TÍNH TIỀN

    $total = 0;

    if ($error === '') {

        $isWeekend = in_array(
            (int)date('N', strtotime($selectedDate)),
            [6, 7],
            true
        );

        $startHour = (int)substr($selectedStart, 0, 2);
        $endHour = (int)substr($selectedEnd, 0, 2);

        for ($h = $startHour; $h < $endHour; $h++) {

            $hourStart = sprintf('%02d:00', $h);
            $hourEnd = sprintf('%02d:00', $h + 1);

            $foundPrice = false;

            foreach ($priceBands as $band) {

                if (
                    $hourStart >= $band['start'] &&
                    $hourEnd <= $band['end']
                ) {

                    $price = $isWeekend
                        ? $band['weekend']
                        : $band['weekday'];

                    $total += $price;
                    $foundPrice = true;
                    break;
                }
            }

            if (!$foundPrice) {
                $error = "Không tìm thấy đơn giá cho giờ $hourStart.";
                break;
            }
        }

        if ($error === '' && $total <= 0) {
            $error = "Tổng tiền đặt sân không hợp lệ.";
        }
    }

    // 8.6 KIỂM TRA TRÙNG VÀ LƯU LỊCH

    if ($error === '') {

        $transactionStarted = false;

        try {

            if (!mysqli_begin_transaction($conn)) {
                throw new Exception("Không thể bắt đầu giao dịch đặt sân.");
            }

            $transactionStarted = true;

            // KHÓA DÒNG SÂN TRƯỚC KHI KIỂM TRA LỊCH

            $sqlLockCourt = "
                SELECT MaSan
                FROM san
                WHERE MaSan = ?
                  AND TrangThai = 'Hoạt động'
                FOR UPDATE
            ";

            $stmtLock = mysqli_prepare($conn, $sqlLockCourt);

            if (!$stmtLock) {
                throw new Exception("Không thể kiểm tra trạng thái sân.");
            }

            mysqli_stmt_bind_param($stmtLock, "i", $selectedCourt);
            mysqli_stmt_execute($stmtLock);

            $resultLock = mysqli_stmt_get_result($stmtLock);
            $courtLocked = mysqli_fetch_assoc($resultLock);

            mysqli_stmt_close($stmtLock);

            if (!$courtLocked) {
                throw new Exception("Sân không còn hoạt động. Vui lòng chọn sân khác.");
            }

            // KIỂM TRA LỊCH TRÙNG

            $sqlCheck = "
                SELECT l.MaLichDat
                FROM lichdat l
                LEFT JOIN khunggio k
                    ON l.MaKhungGio = k.MaKhungGio
                WHERE l.MaSan = ?
                  AND l.NgayDat = ?
                  AND l.TrangThai != 'Đã hủy'
                  AND COALESCE(l.ThoiGianBatDau, k.ThoiGianBatDau) < ?
                  AND COALESCE(l.ThoiGianKetThuc, k.ThoiGianKetThuc) > ?
                LIMIT 1
                FOR UPDATE
            ";

            $stmtCheck = mysqli_prepare($conn, $sqlCheck);

            if (!$stmtCheck) {
                throw new Exception("Không thể kiểm tra lịch trống.");
            }

            mysqli_stmt_bind_param(
                $stmtCheck,
                "isss",
                $selectedCourt,
                $selectedDate,
                $selectedEnd,
                $selectedStart
            );

            mysqli_stmt_execute($stmtCheck);

            $resultCheck = mysqli_stmt_get_result($stmtCheck);
            $bookingConflict = mysqli_fetch_assoc($resultCheck);

            mysqli_stmt_close($stmtCheck);

            if ($bookingConflict) {
                throw new Exception(
                    "Khung giờ này đã có người đặt. Vui lòng chọn giờ khác."
                );
            }

            // TÍNH TIỀN CỌC 50%

            $deposit = $total * 0.5;

            // LƯU LỊCH ĐẶT

            $trangThai = 'Đang giữ chỗ';
            $loaiDat = 'Trong giờ';

            $sqlInsert = "
                INSERT INTO lichdat (
                    MaND,
                    MaSan,
                    MaKhungGio,
                    NgayDat,
                    TongTien,
                    TrangThai,
                    ThoiGianBatDau,
                    ThoiGianKetThuc,
                    LoaiDat
                )
                VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?)
            ";

            $stmtInsert = mysqli_prepare($conn, $sqlInsert);

            if (!$stmtInsert) {
                throw new Exception("Không thể chuẩn bị dữ liệu đặt sân.");
            }

            mysqli_stmt_bind_param(
                $stmtInsert,
                "iisdssss",
                $maND,
                $selectedCourt,
                $selectedDate,
                $total,
                $trangThai,
                $selectedStart,
                $selectedEnd,
                $loaiDat
            );

            if (!mysqli_stmt_execute($stmtInsert)) {
                throw new Exception(
                    "Không thể lưu lịch đặt: " . mysqli_stmt_error($stmtInsert)
                );
            }

            $maLichDat = mysqli_insert_id($conn);

            mysqli_stmt_close($stmtInsert);

            // HOÀN TẤT TRANSACTION

            if (!mysqli_commit($conn)) {
                throw new Exception("Không thể hoàn tất giao dịch đặt sân.");
            }

            $transactionStarted = false;

            // LƯU THÔNG TIN CHỜ THANH TOÁN

            $_SESSION['pending_booking'] = [
                'MaLichDat' => $maLichDat,
                'MaSan' => $selectedCourt,
                'court' => $tenSan,
                'date' => $selectedDate,
                'start' => $selectedStart,
                'end' => $selectedEnd,
                'total' => $total,
                'deposit' => $deposit,
                'expiry' => time() + 900
            ];

            header("Location: qr_payment.php");
            exit();

        } catch (Throwable $e) {

            if ($transactionStarted) {
                mysqli_rollback($conn);
            }

            $error = $e->getMessage();
        }
    }
}

// ========================================
// 9. CHUẨN BỊ DỮ LIỆU JAVASCRIPT
// ========================================

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Đặt Sân Bóng Chuyền - Volleyball Arena</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-[#181412] text-amber-50 min-h-screen font-sans flex flex-col justify-between">

<header class="bg-[#26201c] border-b border-amber-900/40 px-6 py-4 flex justify-between items-center shadow-lg sticky top-0 z-50">

    <a href="../index.php" class="flex items-center gap-3">
        <span class="text-2xl">🏐</span>
        <span class="text-lg font-black tracking-wider uppercase text-amber-100">
            VOLLEYBALL ARENA
        </span>
    </a>

    <div class="flex items-center gap-4 text-xs">
        <span class="text-amber-200">
            Chào, <?php echo e($_SESSION['HoTen'] ?? 'thành viên'); ?>
        </span>

        <span class="bg-amber-500/20 border border-amber-500/40 text-amber-300 px-3 py-1 rounded-full font-bold">
            ✨ SÀN GỖ TARAFLEX
        </span>
    </div>

</header>

<main class="max-w-7xl mx-auto px-6 py-8 w-full">

    <div class="text-xs text-stone-400 bg-[#26201c] border border-amber-900/30 p-3 rounded-xl mb-6 flex items-center gap-2">
        <span class="text-amber-400 font-bold">⚡ Hướng dẫn:</span>
        <span>
            Chọn sân, ngày, giờ bắt đầu và giờ kết thúc.
            Hệ thống tự động kiểm tra lịch và tính tiền.
        </span>
    </div>

    <!-- YÊU CẦU ĐẶT SÂN NGOÀI GIỜ -->

    <div class="bg-gradient-to-r from-blue-950/80 to-[#26201c] border border-blue-500/40 p-5 rounded-2xl mb-6 shadow-lg">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">

            <div>
                <h3 class="text-sm font-black text-blue-300 uppercase mb-2">
                    🕒 Bạn muốn đặt sân ngoài giờ?
                </h3>

                <p class="text-xs text-stone-300 leading-relaxed">
                    Giờ hoạt động thông thường từ 06:00 đến 22:00.
                    Nếu muốn đặt sân ngoài khung giờ này, bạn có thể gửi
                    yêu cầu để Admin xem xét và phê duyệt.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">

                <a href="yeu-cau-ngoai-gio.php"
                   class="bg-amber-500 hover:bg-amber-400 text-stone-950 px-5 py-3 rounded-xl font-bold">
                    GỬI YÊU CẦU NGOÀI GIỜ →
                </a>

                <a href="lich-su-yeu-cau.php"
                   class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-3 rounded-xl font-bold">
                    LỊCH SỬ YÊU CẦU
                </a>

                <a href="lich-su-dat-san.php"
                   class="bg-stone-700 hover:bg-stone-600 text-white px-5 py-3 rounded-xl font-bold">
                    LỊCH SỬ ĐẶT SÂN
                </a>

            </div>
        </div>
    </div>

    <?php if ($error !== '') { ?>
        <div class="bg-red-500/10 border border-red-500/40 text-red-400 p-4 rounded-xl text-xs font-bold text-center mb-6">
            <?php echo e($error); ?>
        </div>
    <?php } ?>

    <form action="booking_demo.php" method="POST"
          class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <input type="hidden" name="csrf_token"
               value="<?php echo e($_SESSION['csrf_booking']); ?>">

        <div class="lg:col-span-2 space-y-6">

            <!-- CHỌN SÂN -->

            <div class="bg-[#26201c] border border-amber-900/40 p-6 rounded-2xl shadow-xl space-y-4">

                <h3 class="text-xs font-bold text-stone-400 uppercase tracking-wider">
                    CHỌN SÂN BÓNG CHUYỀN
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <?php foreach ($courts as $court) { ?>

                        <label class="flex items-center gap-3 bg-[#1c1815] border border-amber-900/40 p-4 rounded-xl cursor-pointer hover:border-amber-400 transition">

                            <input
                                type="radio"
                                name="court"
                                value="<?php echo (int)$court['MaSan']; ?>"
                                <?php echo $selectedCourt == $court['MaSan'] ? 'checked' : ''; ?>
                                class="text-amber-500"
                                onchange="updateSlots()"
                            >

                            <div>
                                <p class="text-sm font-bold text-amber-200">
                                    <?php echo e($court['TenSan']); ?>
                                </p>

                                <p class="text-[11px] text-stone-400">
                                    Sân bóng chuyền
                                </p>
                            </div>

                        </label>

                    <?php } ?>

                </div>
            </div>

            <!-- CHỌN NGÀY -->

            <div class="bg-[#26201c] border border-amber-900/40 p-6 rounded-2xl shadow-xl space-y-4">

                <h3 class="text-xs font-bold text-stone-400 uppercase tracking-wider">
                    CHỌN NGÀY
                </h3>

                <div class="grid grid-cols-4 sm:grid-cols-7 gap-2">

                    <?php foreach ($days as $day) { ?>

                        <label class="day-label flex flex-col items-center justify-center p-3 rounded-xl border cursor-pointer transition
                            <?php echo $selectedDate === $day['date']
                                ? 'bg-amber-500 text-stone-950 border-amber-400 font-black'
                                : 'bg-[#1c1815] text-stone-300 border-amber-900/40 hover:border-amber-500'; ?>">

                            <input
                                type="radio"
                                name="date"
                                value="<?php echo e($day['date']); ?>"
                                <?php echo $selectedDate === $day['date'] ? 'checked' : ''; ?>
                                class="hidden"
                                onchange="updateSlots()"
                            >

                            <span class="text-xs uppercase">
                                <?php echo e($day['weekday']); ?>
                            </span>

                            <span class="text-sm font-bold mt-1">
                                <?php echo e($day['display']); ?>
                            </span>

                        </label>

                    <?php } ?>

                </div>
            </div>

            <!-- CHỌN GIỜ -->

            <div class="bg-[#26201c] border border-amber-900/40 p-6 rounded-2xl shadow-xl space-y-4">

                <div class="flex justify-between items-center">

                    <h3 class="text-xs font-bold text-stone-400 uppercase tracking-wider">
                        CHỌN MỐC GIỜ THI ĐẤU
                    </h3>

                    <span id="selected_date_label" class="text-xs text-amber-300 font-mono">
                        📅 <?php echo date('d/m/Y', strtotime($selectedDate)); ?>
                    </span>

                </div>

                <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">

                    <?php for ($h = 6; $h <= 22; $h++) {
                        $hour = sprintf('%02d:00', $h);
                    ?>

                        <div
                            onclick="selectHour('<?php echo $hour; ?>')"
                            id="slot_<?php echo str_replace(':', '', $hour); ?>"
                            data-time="<?php echo $hour; ?>"
                            class="slot-btn bg-[#1c1815] border border-amber-900/50 hover:border-amber-500 p-3 rounded-xl text-center cursor-pointer font-bold text-xs text-amber-200 transition"
                        >
                            <?php echo $hour; ?>
                        </div>

                    <?php } ?>

                </div>

                <div class="grid grid-cols-2 gap-4 pt-4 border-t border-amber-900/30">

                    <div>
                        <label class="block text-[11px] text-stone-400 uppercase mb-1">
                            Giờ bắt đầu:
                        </label>

                        <input
                            type="text"
                            name="start_time"
                            id="start_time"
                            readonly
                            required
                            value="<?php echo e($selectedStart); ?>"
                            placeholder="Click chọn ở trên"
                            class="w-full bg-[#1c1815] border border-amber-900/50 rounded-xl px-3 py-2 text-xs font-bold text-amber-400"
                        >
                    </div>

                    <div>
                        <label class="block text-[11px] text-stone-400 uppercase mb-1">
                            Giờ kết thúc:
                        </label>

                        <input
                            type="text"
                            name="end_time"
                            id="end_time"
                            readonly
                            required
                            value="<?php echo e($selectedEnd); ?>"
                            placeholder="Click chọn ở trên"
                            class="w-full bg-[#1c1815] border border-amber-900/50 rounded-xl px-3 py-2 text-xs font-bold text-amber-400"
                        >
                    </div>

                </div>

                <div class="flex flex-wrap items-center gap-4 pt-3 border-t border-amber-900/30 text-[11px] text-stone-300">

                    <div class="flex items-center gap-1.5">
                        <span class="w-3.5 h-3.5 bg-[#1c1815] border border-amber-900/50 rounded inline-block"></span>
                        <span>Còn trống</span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <span class="w-3.5 h-3.5 bg-stone-600 border border-stone-500 rounded inline-block"></span>
                        <span>Đã có người đặt</span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <span class="w-3.5 h-3.5 bg-amber-600 rounded inline-block"></span>
                        <span>Đang chọn</span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <span class="w-3.5 h-3.5 bg-red-600 rounded inline-block"></span>
                        <span>Bắt đầu</span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <span class="w-3.5 h-3.5 bg-emerald-600 rounded inline-block"></span>
                        <span>Kết thúc</span>
                    </div>

                </div>
            </div>

        </div>

        <!-- TÓM TẮT -->

        <div class="space-y-6">

            <div class="bg-[#26201c] border border-amber-900/50 p-6 rounded-2xl shadow-2xl space-y-6 sticky top-24">

                <h3 class="text-xs font-black text-amber-400 uppercase tracking-wider pb-3 border-b border-amber-900/30 flex items-center gap-2">
                    <span>📄</span> TÓM TẮT ĐẶT SÂN
                </h3>

                <div class="space-y-3 text-xs">

                    <div class="flex justify-between gap-2">
                        <span class="text-stone-400">Sân:</span>
                        <span id="summary_court" class="font-bold text-amber-100"></span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-stone-400">Ngày:</span>
                        <span id="summary_date" class="font-bold text-amber-100"></span>
                    </div>

                    <div class="flex justify-between gap-2">
                        <span class="text-stone-400">Khung giờ:</span>
                        <span id="summary_time" class="font-bold text-amber-400">
                            Chưa chọn giờ
                        </span>
                    </div>

                    <div class="flex justify-between border-t border-amber-900/30 pt-3">
                        <span class="text-stone-400">Đơn giá:</span>
                        <span class="font-bold text-stone-200">
                            Theo bảng giá
                        </span>
                    </div>

                </div>

                <div class="bg-[#1c1815] border border-amber-900/30 p-4 rounded-xl space-y-1">

                    <span class="text-[11px] text-stone-400 uppercase font-bold">
                        TỔNG TIỀN:
                    </span>

                    <div id="summary_total" class="text-2xl font-black text-amber-400">
                        0 đ
                    </div>

                    <div id="summary_deposit" class="text-xs text-emerald-400 font-bold">
                        Cọc trước 50%: 0 đ
                    </div>

                </div>

                <button
                    type="submit"
                    class="w-full bg-gradient-to-r from-amber-600 via-amber-500 to-amber-600 hover:from-amber-500 hover:to-amber-400 text-stone-950 font-black py-4 rounded-xl shadow-lg transition uppercase tracking-wider text-xs cursor-pointer"
                >
                    TIẾP TỤC QUÉT MÃ VIETQR →
                </button>

                <p class="text-[10px] text-stone-500 text-center">
                    🔒 Thông tin đặt sân được bảo mật
                </p>

            </div>
        </div>

    </form>

</main>

<footer class="bg-[#120f0d] border-t border-amber-900/40 py-6 text-center text-xs text-stone-500 mt-12">
    <p>© 2026 Volleyball Arena Management System. All rights reserved.</p>
</footer>

<!-- ========================================
     JAVASCRIPT - CHỌN NGÀY, GIỜ VÀ TÍNH TIỀN
======================================== -->

<script>

// ========================================
// 1. DỮ LIỆU TỪ DATABASE
// ========================================

const bookedData = <?php
echo json_encode(
    $bookedData,
    JSON_UNESCAPED_UNICODE |
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);
?>;

const priceBands = <?php
echo json_encode(
    $priceBands,
    JSON_UNESCAPED_UNICODE |
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);
?>;

const courtNames = <?php
echo json_encode(
    array_column($courts, 'TenSan', 'MaSan'),
    JSON_UNESCAPED_UNICODE |
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);
?>;

let selectedStart = null;
let selectedEnd = null;

// ========================================
// 2. LẤY THÔNG TIN ĐANG CHỌN
// ========================================

function getCourt() {
    const selected = document.querySelector('input[name="court"]:checked');
    return selected ? selected.value : null;
}

function getDate() {
    const selected = document.querySelector('input[name="date"]:checked');
    return selected ? selected.value : null;
}

function formatDate(date) {
    if (!date) return '';

    const parts = date.split('-');
    return parts[2] + '/' + parts[1] + '/' + parts[0];
}

// ========================================
// 3. CẬP NHẬT KHI ĐỔI SÂN HOẶC NGÀY
// ========================================

function updateSlots() {

    selectedStart = null;
    selectedEnd = null;

    document.getElementById('start_time').value = '';
    document.getElementById('end_time').value = '';

    const court = getCourt();
    const date = getDate();

    if (!court || !date) return;

    const currentBooked =
        bookedData[court] && bookedData[court][date]
            ? bookedData[court][date]
            : [];

    document.getElementById('summary_court').innerText =
        courtNames[court] || 'Sân';

    document.getElementById('summary_date').innerText =
        formatDate(date);

    document.getElementById('selected_date_label').innerText =
        '📅 ' + formatDate(date);

    // CẬP NHẬT MÀU NGÀY

    document.querySelectorAll('.day-label').forEach(label => {

        const radio = label.querySelector('input');

        label.classList.remove(
            'bg-amber-500',
            'text-stone-950',
            'border-amber-400',
            'font-black'
        );

        label.classList.add(
            'bg-[#1c1815]',
            'text-stone-300',
            'border-amber-900/40'
        );

        if (radio.checked) {

            label.classList.remove(
                'bg-[#1c1815]',
                'text-stone-300',
                'border-amber-900/40'
            );

            label.classList.add(
                'bg-amber-500',
                'text-stone-950',
                'border-amber-400',
                'font-black'
            );
        }
    });

    // CẬP NHẬT TRẠNG THÁI GIỜ

    document.querySelectorAll('.slot-btn').forEach(slot => {

        const hour = slot.dataset.time;
        const isBooked = currentBooked.includes(hour);

        slot.dataset.booked = isBooked ? 'true' : 'false';

        if (isBooked) {

            slot.className =
                'slot-btn bg-stone-600 border border-stone-500 p-3 rounded-xl text-center font-bold text-xs text-stone-300 opacity-70 cursor-not-allowed';

        } else {

            slot.className =
                'slot-btn bg-[#1c1815] border border-amber-900/50 hover:border-amber-500 p-3 rounded-xl text-center cursor-pointer font-bold text-xs text-amber-200 transition';
        }
    });

    document.getElementById('summary_time').innerText = 'Chưa chọn giờ';
    document.getElementById('summary_total').innerText = '0 đ';

    document.getElementById('summary_deposit').innerText =
        'Cọc trước 50%: 0 đ';
}

// ========================================
// 4. CHỌN GIỜ
// ========================================

function selectHour(hourStr) {

    const slot = document.querySelector(
        `[data-time="${hourStr}"]`
    );

    if (!slot) return;

    const court = getCourt();
    const date = getDate();

    if (!court || !date) return;

    const currentBooked =
        bookedData[court] && bookedData[court][date]
            ? bookedData[court][date]
            : [];

    // CHỌN GIỜ BẮT ĐẦU

    if (!selectedStart || selectedEnd) {

        if (hourStr === '22:00') {
            alert('22:00 là giờ kết thúc, không thể chọn làm giờ bắt đầu.');
            return;
        }

        if (slot.dataset.booked === 'true') {
            alert('Giờ này đã có người đặt!');
            return;
        }

        selectedStart = hourStr;
        selectedEnd = null;

        document.getElementById('start_time').value = hourStr;
        document.getElementById('end_time').value = '';

        highlightSlots();
        return;
    }

    // CHỌN GIỜ KẾT THÚC

    if (hourStr > selectedStart) {

        let hasBookedBetween = false;

        const startHour = parseInt(selectedStart.substring(0, 2));
        const endHour = parseInt(hourStr.substring(0, 2));

        for (let h = startHour; h < endHour; h++) {

            const checkHour = String(h).padStart(2, '0') + ':00';

            if (currentBooked.includes(checkHour)) {
                hasBookedBetween = true;
                break;
            }
        }

        if (hasBookedBetween) {

            alert(
                'Khoảng thời gian bạn chọn có giờ đã được đặt. Vui lòng chọn lại!'
            );

            selectedStart = hourStr === '22:00' ? null : hourStr;
            selectedEnd = null;

            document.getElementById('start_time').value =
                selectedStart || '';

            document.getElementById('end_time').value = '';

            highlightSlots();
            return;
        }

        selectedEnd = hourStr;

        document.getElementById('end_time').value = hourStr;

        highlightSlots();
        calculateFee();

    } else {

        // CHỌN LẠI GIỜ BẮT ĐẦU

        if (hourStr === '22:00') {
            alert('22:00 không thể làm giờ bắt đầu.');
            return;
        }

        if (slot.dataset.booked === 'true') {
            alert('Giờ này đã có người đặt!');
            return;
        }

        selectedStart = hourStr;
        selectedEnd = null;

        document.getElementById('start_time').value = hourStr;
        document.getElementById('end_time').value = '';

        highlightSlots();
    }
}

// ========================================
// 5. TÔ MÀU GIỜ ĐANG CHỌN
// ========================================

function highlightSlots() {

    const court = getCourt();
    const date = getDate();

    if (!court || !date) return;

    const currentBooked =
        bookedData[court] && bookedData[court][date]
            ? bookedData[court][date]
            : [];

    document.querySelectorAll('.slot-btn').forEach(slot => {

        const time = slot.dataset.time;

        if (currentBooked.includes(time)) {

            slot.className =
                'slot-btn bg-stone-600 border border-stone-500 p-3 rounded-xl text-center font-bold text-xs text-stone-300 opacity-70 cursor-not-allowed';

            return;
        }

        slot.className =
            'slot-btn bg-[#1c1815] border border-amber-900/50 hover:border-amber-500 p-3 rounded-xl text-center cursor-pointer font-bold text-xs text-amber-200 transition';

        if (selectedStart && time === selectedStart) {

            slot.className =
                'slot-btn bg-red-600 border border-red-500 p-3 rounded-xl text-center cursor-pointer font-bold text-xs text-white transition';

        } else if (selectedEnd && time === selectedEnd) {

            slot.className =
                'slot-btn bg-emerald-600 border border-emerald-500 p-3 rounded-xl text-center cursor-pointer font-bold text-xs text-white transition';

        } else if (
            selectedStart &&
            selectedEnd &&
            time > selectedStart &&
            time < selectedEnd
        ) {

            slot.className =
                'slot-btn bg-amber-600 border border-amber-500 p-3 rounded-xl text-center cursor-pointer font-bold text-xs text-stone-950 transition';
        }
    });

    if (selectedStart && selectedEnd) {

        document.getElementById('summary_time').innerText =
            selectedStart + ' - ' + selectedEnd;

    } else if (selectedStart) {

        document.getElementById('summary_time').innerText =
            selectedStart + ' - (Chọn giờ kết thúc)';

    } else {

        document.getElementById('summary_time').innerText =
            'Chưa chọn giờ';
    }
}

// ========================================
// 6. TÍNH TIỀN
// ========================================

function calculateFee() {

    if (!selectedStart || !selectedEnd) return;

    const date = getDate();

    if (!date) return;

    const dateObject = new Date(date + 'T12:00:00');

    const day = dateObject.getDay();
    const isWeekend = day === 0 || day === 6;

    const startHour = parseInt(selectedStart.substring(0, 2));
    const endHour = parseInt(selectedEnd.substring(0, 2));

    let total = 0;

    for (let h = startHour; h < endHour; h++) {

        const hourStart = String(h).padStart(2, '0') + ':00';
        const hourEnd = String(h + 1).padStart(2, '0') + ':00';

        const band = priceBands.find(item =>
            hourStart >= item.start &&
            hourEnd <= item.end
        );

        if (!band) {
            alert('Không tìm thấy giá cho khung giờ ' + hourStart);
            return;
        }

        total += isWeekend
            ? Number(band.weekend)
            : Number(band.weekday);
    }

    const deposit = total * 0.5;

    document.getElementById('summary_total').innerText =
        total.toLocaleString('vi-VN') + ' đ';

    document.getElementById('summary_deposit').innerText =
        'Cọc trước 50%: ' +
        deposit.toLocaleString('vi-VN') + ' đ';
}

// ========================================
// 7. KHỞI TẠO KHI TRANG ĐÃ TẢI XONG
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    updateSlots();
});

</script>

</body>
</html>

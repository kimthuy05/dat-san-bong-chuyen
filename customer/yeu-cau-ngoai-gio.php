
<?php
// ==================================================
// 1. KHỞI TẠO VÀ KIỂM TRA ĐĂNG NHẬP
// ==================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

mysqli_set_charset($conn, "utf8mb4");

// Đồng bộ mã người dùng
if (!isset($_SESSION['MaND']) && isset($_SESSION['ma_tai_khoan'])) {
    $_SESSION['MaND'] = $_SESSION['ma_tai_khoan'];
}

// Chưa đăng nhập
if (!isset($_SESSION['MaND'])) {
    header("Location: ../login.php");
    exit();
}

// Không cho Admin gửi yêu cầu với tư cách khách hàng
if (($_SESSION['VaiTro'] ?? '') === 'Admin') {
    header("Location: ../admin/index.php");
    exit();
}

$maND = (int)$_SESSION['MaND'];

$error = '';
$success = '';

// ==================================================
// 2. HÀM HỖ TRỢ
// ==================================================

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// CSRF token
if (empty($_SESSION['csrf_ngoai_gio'])) {
    $_SESSION['csrf_ngoai_gio'] = bin2hex(random_bytes(32));
}

// ==================================================
// 3. LẤY DANH SÁCH SÂN
// ==================================================

$dsSan = [];

$sqlSan = "
    SELECT MaSan, TenSan
    FROM san
    WHERE TrangThai = 'Hoạt động'
    ORDER BY MaSan ASC
";

$resultSan = mysqli_query($conn, $sqlSan);

if (!$resultSan) {
    die("Không thể tải danh sách sân.");
}

while ($row = mysqli_fetch_assoc($resultSan)) {
    $dsSan[] = $row;
}

// ==================================================
// 4. XỬ LÝ GỬI YÊU CẦU
// ==================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $maSan = (int)($_POST['MaSan'] ?? 0);
    $ngayDat = trim($_POST['NgayDat'] ?? '');
    $batDau = trim($_POST['ThoiGianBatDau'] ?? '');
    $ketThuc = trim($_POST['ThoiGianKetThuc'] ?? '');
    $lyDo = trim($_POST['LyDo'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    // ----------------------------------------------
    // 4.1 KIỂM TRA CSRF
    // ----------------------------------------------

    if (
        !is_string($csrf) ||
        !hash_equals($_SESSION['csrf_ngoai_gio'], $csrf)
    ) {
        $error = "Phiên làm việc không hợp lệ. Vui lòng tải lại trang.";
    }

    // ----------------------------------------------
    // 4.2 KIỂM TRA DỮ LIỆU BẮT BUỘC
    // ----------------------------------------------

    if ($error === '') {

        if (
            $maSan <= 0 ||
            $ngayDat === '' ||
            $batDau === '' ||
            $ketThuc === ''
        ) {
            $error = "Vui lòng nhập đầy đủ thông tin!";
        }
    }

    // ----------------------------------------------
    // 4.3 KIỂM TRA SÂN
    // ----------------------------------------------

    $sanHopLe = false;

    if ($error === '') {

        foreach ($dsSan as $san) {
            if ((int)$san['MaSan'] === $maSan) {
                $sanHopLe = true;
                break;
            }
        }

        if (!$sanHopLe) {
            $error = "Sân không tồn tại hoặc hiện không hoạt động!";
        }
    }

    // ----------------------------------------------
    // 4.4 KIỂM TRA NGÀY
    // ----------------------------------------------

    if ($error === '') {

        $dateObject = DateTime::createFromFormat('!Y-m-d', $ngayDat);

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $ngayDat ||
            $ngayDat < date('Y-m-d')
        ) {
            $error = "Ngày đặt không hợp lệ hoặc đã qua!";
        }
    }

    // ----------------------------------------------
    // 4.5 KIỂM TRA GIỜ
    // ----------------------------------------------

    if ($error === '') {

        $validTime = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';

        if (
            !preg_match($validTime, $batDau) ||
            !preg_match($validTime, $ketThuc)
        ) {
            $error = "Giờ bắt đầu hoặc kết thúc không hợp lệ!";
        } elseif ($batDau >= $ketThuc) {
            $error = "Giờ kết thúc phải lớn hơn giờ bắt đầu!";
        }
    }

    // ----------------------------------------------
    // 4.6 KIỂM TRA CÓ THỰC SỰ NGOÀI GIỜ KHÔNG
    // ----------------------------------------------

    if ($error === '') {

        // Nếu toàn bộ khoảng giờ nằm trong 06:00 - 22:00
        // thì khách phải sử dụng chức năng đặt sân thông thường.

        if ($batDau >= '06:00' && $ketThuc <= '22:00') {
            $error = "Khung giờ bạn chọn vẫn nằm trong giờ hoạt động 06:00 - 22:00. Vui lòng sử dụng chức năng đặt sân thông thường!";
        }
    }

    // ----------------------------------------------
    // 4.7 TÍNH TIỀN DỰ KIẾN
    // ----------------------------------------------

    $tongTien = 0;

    if ($error === '') {

        $gioBatDau = strtotime($batDau);
        $gioKetThuc = strtotime($ketThuc);

        $soGio = ($gioKetThuc - $gioBatDau) / 3600;

        if ($soGio <= 0) {
            $error = "Thời lượng đặt sân không hợp lệ!";
        } else {

            // Đơn giá minh họa ngoài giờ
            $donGia = 50000;

            $tongTien = $soGio * $donGia;
        }
    }

    // ----------------------------------------------
    // 4.8 KIỂM TRA TRÙNG LỊCH
    // ----------------------------------------------

    if ($error === '') {

        // Kiểm tra lịch đặt thông thường.
        // COALESCE hỗ trợ cả lịch mới và lịch cũ dùng MaKhungGio.

        $sqlTrung = "
            SELECT l.MaLichDat
            FROM lichdat l
            LEFT JOIN khunggio k
                ON l.MaKhungGio = k.MaKhungGio
            WHERE l.MaSan = ?
              AND l.NgayDat = ?
              AND l.TrangThai IN ('Đang giữ chỗ', 'Đã đặt')
              AND COALESCE(l.ThoiGianBatDau, k.ThoiGianBatDau) < ?
              AND COALESCE(l.ThoiGianKetThuc, k.ThoiGianKetThuc) > ?
            LIMIT 1
        ";

        $stmtTrung = mysqli_prepare($conn, $sqlTrung);

        if (!$stmtTrung) {

            $error = "Không thể kiểm tra lịch sân!";

        } else {

            mysqli_stmt_bind_param(
                $stmtTrung,
                "isss",
                $maSan,
                $ngayDat,
                $ketThuc,
                $batDau
            );

            mysqli_stmt_execute($stmtTrung);

            $resultTrung = mysqli_stmt_get_result($stmtTrung);
            $trung = mysqli_fetch_assoc($resultTrung);

            mysqli_stmt_close($stmtTrung);

            if ($trung) {
                $error = "Khung giờ này đã có người đặt. Vui lòng chọn giờ khác!";
            }
        }
    }

    // ----------------------------------------------
    // 4.9 KIỂM TRA TRÙNG VỚI YÊU CẦU NGOÀI GIỜ
    // ----------------------------------------------

    if ($error === '') {

        $sqlYeuCau = "
            SELECT MaYeuCau
            FROM yeucau_ngoai_gio
            WHERE MaSan = ?
              AND NgayDat = ?
              AND TrangThaiYeuCau IN ('Chờ duyệt', 'Đã duyệt')
              AND ThoiGianBatDau < ?
              AND ThoiGianKetThuc > ?
            LIMIT 1
        ";

        $stmtYeuCau = mysqli_prepare($conn, $sqlYeuCau);

        if (!$stmtYeuCau) {

            $error = "Không thể kiểm tra yêu cầu ngoài giờ!";

        } else {

            mysqli_stmt_bind_param(
                $stmtYeuCau,
                "isss",
                $maSan,
                $ngayDat,
                $ketThuc,
                $batDau
            );

            mysqli_stmt_execute($stmtYeuCau);

            $resultYeuCau = mysqli_stmt_get_result($stmtYeuCau);
            $yeuCauTrung = mysqli_fetch_assoc($resultYeuCau);

            mysqli_stmt_close($stmtYeuCau);

            if ($yeuCauTrung) {
                $error = "Đã có yêu cầu ngoài giờ khác trùng khoảng thời gian này!";
            }
        }
    }

    // ----------------------------------------------
    // 4.10 LƯU YÊU CẦU VÀO DATABASE
    // ----------------------------------------------

    if ($error === '') {

        $trangThai = 'Chờ duyệt';

        $sqlThem = "
            INSERT INTO yeucau_ngoai_gio (
                MaND,
                MaSan,
                NgayDat,
                ThoiGianBatDau,
                ThoiGianKetThuc,
                LyDo,
                TongTienDuKien,
                TrangThaiYeuCau
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmtThem = mysqli_prepare($conn, $sqlThem);

        if (!$stmtThem) {

            $error = "Không thể tạo yêu cầu. Vui lòng thử lại!";

        } else {

            mysqli_stmt_bind_param(
                $stmtThem,
                "iissssds",
                $maND,
                $maSan,
                $ngayDat,
                $batDau,
                $ketThuc,
                $lyDo,
                $tongTien,
                $trangThai
            );

            if (mysqli_stmt_execute($stmtThem)) {

                $success = "Gửi yêu cầu đặt sân ngoài giờ thành công! Vui lòng chờ Admin xét duyệt.";

                // Xóa dữ liệu đã gửi khỏi form
                $_POST = [];

                // Đổi CSRF token sau khi gửi thành công
                $_SESSION['csrf_ngoai_gio'] = bin2hex(random_bytes(32));

            } else {

                $error = "Gửi yêu cầu thất bại. Vui lòng thử lại!";
            }

            mysqli_stmt_close($stmtThem);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Yêu cầu đặt sân ngoài giờ - Volleyball Arena</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-stone-950 text-amber-50 min-h-screen">

<div class="max-w-3xl mx-auto px-5 py-10">

    <!-- QUAY LẠI -->

    <div class="flex justify-between items-center mb-5">

        <a href="booking_demo.php"
           class="text-amber-400 text-sm hover:underline">
            ← Quay lại trang đặt sân
        </a>

        <a href="lich-su-yeu-cau.php"
           class="text-blue-400 text-sm hover:underline">
            Lịch sử yêu cầu →
        </a>

    </div>

    <!-- FORM -->

    <div class="bg-stone-900 border border-amber-900/50 rounded-2xl p-6 md:p-8">

        <h1 class="text-2xl font-black text-amber-400 mb-2">
            YÊU CẦU ĐẶT SÂN NGOÀI GIỜ
        </h1>

        <p class="text-stone-400 text-sm mb-6">
            Giờ hoạt động thông thường: 06:00 - 22:00.
            Yêu cầu ngoài giờ cần được Admin xét duyệt.
        </p>

        <!-- THÔNG BÁO LỖI -->

        <?php if ($error !== ''): ?>

            <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-3 rounded-xl mb-5 text-sm">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <!-- THÔNG BÁO THÀNH CÔNG -->

        <?php if ($success !== ''): ?>

            <div class="bg-green-500/10 border border-green-500/30 text-green-400 p-4 rounded-xl mb-5 text-sm">

                <p class="font-bold">
                    ✓ <?= e($success) ?>
                </p>

                <p class="mt-2">
                    <a href="lich-su-yeu-cau.php"
                       class="underline font-bold">
                        Xem lịch sử yêu cầu
                    </a>
                </p>

            </div>

        <?php endif; ?>

        <form method="POST" action="yeu-cau-ngoai-gio.php" class="space-y-5">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_ngoai_gio']) ?>"
            >

            <!-- CHỌN SÂN -->

            <div>

                <label class="block text-sm font-bold mb-2">
                    Chọn sân
                </label>

                <select
                    name="MaSan"
                    required
                    class="w-full bg-stone-800 border border-stone-700 rounded-xl p-3 text-white"
                >

                    <option value="">
                        -- Chọn sân --
                    </option>

                    <?php foreach ($dsSan as $san): ?>

                        <option
                            value="<?= (int)$san['MaSan'] ?>"
                            <?= (($_POST['MaSan'] ?? '') == $san['MaSan']) ? 'selected' : '' ?>
                        >
                            <?= e($san['TenSan']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- NGÀY -->

            <div>

                <label class="block text-sm font-bold mb-2">
                    Ngày muốn đặt
                </label>

                <input
                    type="date"
                    name="NgayDat"
                    required
                    min="<?= date('Y-m-d') ?>"
                    value="<?= e($_POST['NgayDat'] ?? '') ?>"
                    class="w-full bg-stone-800 border border-stone-700 rounded-xl p-3 text-white"
                >

            </div>

            <!-- GIỜ -->

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>

                    <label class="block text-sm font-bold mb-2">
                        Giờ bắt đầu
                    </label>

                    <input
                        type="time"
                        name="ThoiGianBatDau"
                        required
                        value="<?= e($_POST['ThoiGianBatDau'] ?? '') ?>"
                        class="w-full bg-stone-800 border border-stone-700 rounded-xl p-3 text-white"
                    >

                </div>

                <div>

                    <label class="block text-sm font-bold mb-2">
                        Giờ kết thúc
                    </label>

                    <input
                        type="time"
                        name="ThoiGianKetThuc"
                        required
                        value="<?= e($_POST['ThoiGianKetThuc'] ?? '') ?>"
                        class="w-full bg-stone-800 border border-stone-700 rounded-xl p-3 text-white"
                    >

                </div>

            </div>

            <!-- LÝ DO -->

            <div>

                <label class="block text-sm font-bold mb-2">
                    Lý do đặt ngoài giờ
                </label>

                <textarea
                    name="LyDo"
                    rows="4"
                    maxlength="1000"
                    class="w-full bg-stone-800 border border-stone-700 rounded-xl p-3 text-white"
                    placeholder="Ví dụ: Nhóm có lịch tập muộn sau giờ học..."
                ><?= e($_POST['LyDo'] ?? '') ?></textarea>

            </div>

            <!-- LƯU Ý -->

            <div class="bg-amber-500/10 border border-amber-500/30 rounded-xl p-4 text-sm text-amber-200">

                <p class="font-bold mb-2">
                    📌 LƯU Ý
                </p>

                <p>
                    Đơn giá dự kiến minh họa: 50.000 đồng/giờ.
                    Giá chính thức sẽ do Admin xác nhận.
                </p>

                <p class="mt-2">
                    Gửi yêu cầu chưa có nghĩa là đặt sân thành công.
                    Bạn chỉ tiến hành đặt cọc sau khi được Admin duyệt.
                </p>

            </div>

            <!-- NÚT GỬI -->

            <button
                type="submit"
                class="w-full bg-amber-500 hover:bg-amber-400 text-stone-950 font-black py-4 rounded-xl transition"
            >
                GỬI YÊU CẦU NGOÀI GIỜ
            </button>

        </form>

    </div>

</div>

</body>
</html>

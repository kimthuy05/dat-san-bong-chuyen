
<?php
// ==================================================
// 1. KHỞI TẠO
// ==================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/includes/db.php";

mysqli_set_charset($conn, "utf8mb4");

$error = '';

// Xác định đây là trang đăng nhập Admin hay Khách hàng
$isAdminLogin = (($_GET['role'] ?? '') === 'admin');

// ==================================================
// 2. XỬ LÝ ĐĂNG NHẬP
// ==================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {

        $error = 'Vui lòng nhập đầy đủ thông tin!';

    } else {

        $sql = "
            SELECT
                MaND,
                HoTen,
                Email,
                MatKhau,
                VaiTro,
                TrangThai
            FROM nguoidung
            WHERE Email = ?
            LIMIT 1
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {

            $error = 'Không thể xử lý yêu cầu đăng nhập!';

        } else {

            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);

            // ------------------------------------------
            // 2.1 KIỂM TRA TÀI KHOẢN
            // ------------------------------------------

            if (!$user) {

                $error = 'Email hoặc mật khẩu không chính xác!';

            } elseif ($user['TrangThai'] !== 'Hoạt động') {

                $error = 'Tài khoản của bạn hiện đang bị khóa!';

            } elseif (!password_verify($password, $user['MatKhau'])) {

                $error = 'Email hoặc mật khẩu không chính xác!';

            } elseif (
                $isAdminLogin &&
                $user['VaiTro'] !== 'Admin'
            ) {

                $error = 'Tài khoản này không có quyền quản trị viên!';

            } elseif (
                !$isAdminLogin &&
                $user['VaiTro'] === 'Admin'
            ) {

                $error = 'Vui lòng đăng nhập bằng trang dành cho quản trị viên!';

            } else {

                // --------------------------------------
                // 2.2 ĐĂNG NHẬP THÀNH CÔNG
                // --------------------------------------

                session_regenerate_id(true);

                // Xóa session đăng nhập cũ
                unset(
                    $_SESSION['MaND'],
                    $_SESSION['ma_tai_khoan'],
                    $_SESSION['HoTen'],
                    $_SESSION['Email'],
                    $_SESSION['VaiTro'],
                    $_SESSION['user']
                );

                // Lưu thông tin người dùng
                $_SESSION['MaND'] = (int)$user['MaND'];
                $_SESSION['ma_tai_khoan'] = (int)$user['MaND'];
                $_SESSION['HoTen'] = $user['HoTen'];
                $_SESSION['Email'] = $user['Email'];
                $_SESSION['VaiTro'] = $user['VaiTro'];
                $_SESSION['user'] = $user['HoTen'];

                mysqli_stmt_close($stmt);

                // --------------------------------------
                // 2.3 CHUYỂN TRANG THEO VAI TRÒ
                // --------------------------------------

                if ($user['VaiTro'] === 'Admin') {

                    header("Location: admin/index.php");
                    exit();

                } elseif ($user['VaiTro'] === 'Khách hàng') {

                    header("Location: index.php");
                    exit();

                } else {

                    // Vai trò không hợp lệ
                    session_unset();
                    session_destroy();

                    header("Location: login.php");
                    exit();
                }
            }

            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= $isAdminLogin ? 'Đăng Nhập Admin' : 'Đăng Nhập Khách Hàng' ?>
        - Volleyball Arena
    </title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-[#181412] text-amber-50 min-h-screen flex flex-col justify-between font-sans">

    <!-- HEADER -->
    <header class="bg-gradient-to-r from-[#653512] via-[#8b4513] to-[#a0522d] border-b-2 border-amber-600/40 px-6 py-4 flex justify-between items-center">

        <a href="index.php" class="text-xl font-black uppercase text-amber-100">
            🏐 VOLLEYBALL ARENA
        </a>

        <div class="flex gap-3 text-xs font-bold">

            <a href="index.php" class="text-amber-200 py-2 px-3">
                Trang chủ
            </a>

            <?php if (!$isAdminLogin): ?>

                <a href="register.php"
                   class="bg-amber-500 hover:bg-amber-400 text-stone-950 px-4 py-2 rounded-xl">
                    Đăng ký
                </a>

            <?php endif; ?>

        </div>

    </header>

    <!-- FORM ĐĂNG NHẬP -->
    <div class="max-w-md w-full mx-auto my-12 bg-[#26201c] border border-amber-900/40 p-8 rounded-2xl shadow-2xl">

        <h2 class="text-2xl font-black text-center text-amber-100 uppercase mb-6">

            <?= $isAdminLogin
                ? 'Đăng Nhập Quản Trị Viên'
                : 'Đăng Nhập Khách Hàng'
            ?>

        </h2>

        <!-- THÔNG BÁO LỖI -->
        <?php if ($error !== ''): ?>

            <div class="bg-red-500/10 border border-red-500/30 rounded-xl p-3 mb-4">

                <p class="text-red-400 text-xs text-center font-semibold">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </p>

            </div>

        <?php endif; ?>

        <form
            method="POST"
            action="<?= $isAdminLogin ? 'login.php?role=admin' : 'login.php' ?>"
            class="space-y-4"
        >

            <!-- EMAIL -->
            <div>

                <label class="block text-xs font-bold text-amber-400 mb-1">
                    Email
                </label>

                <input
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    class="w-full bg-[#1c1815] border border-amber-900/60 rounded-xl px-4 py-2.5 text-sm text-amber-100 outline-none focus:border-amber-400"
                    placeholder="Nhập email của bạn"
                >

            </div>

            <!-- MẬT KHẨU -->
            <div>

                <label class="block text-xs font-bold text-amber-400 mb-1">
                    Mật khẩu
                </label>

                <input
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="w-full bg-[#1c1815] border border-amber-900/60 rounded-xl px-4 py-2.5 text-sm text-amber-100 outline-none focus:border-amber-400"
                    placeholder="Nhập mật khẩu"
                >

            </div>

            <!-- NÚT ĐĂNG NHẬP -->
            <button
                type="submit"
                class="w-full bg-amber-500 hover:bg-amber-400 text-stone-950 font-black py-3 rounded-xl uppercase text-sm mt-2 transition"
            >

                <?= $isAdminLogin
                    ? 'Đăng Nhập Admin'
                    : 'Đăng Nhập Khách Hàng'
                ?>

            </button>

        </form>

        <!-- ĐĂNG KÝ KHÁCH HÀNG -->
        <?php if (!$isAdminLogin): ?>

            <p class="text-center text-xs text-stone-400 mt-4">

                Chưa có tài khoản?

                <a href="register.php" class="text-amber-400 font-bold hover:underline">
                    Đăng ký ngay
                </a>

            </p>

        <?php else: ?>

            <p class="text-center text-xs text-blue-400 mt-4">
                Khu vực đăng nhập dành riêng cho quản trị viên.
            </p>

        <?php endif; ?>

    </div>

    <!-- FOOTER -->
    <footer class="py-4 text-center text-xs text-stone-600">
        © 2026 Volleyball Arena
    </footer>

</body>
</html>

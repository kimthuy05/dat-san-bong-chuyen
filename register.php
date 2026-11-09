```php
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/includes/db.php";

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $hoTen = trim($_POST['HoTen'] ?? '');
    $email = trim($_POST['Email'] ?? '');
    $soDienThoai = trim($_POST['SoDienThoai'] ?? '');
    $matKhau = $_POST['MatKhau'] ?? '';

    // 1. Kiểm tra dữ liệu
    if ($hoTen === '' || $email === '' || $soDienThoai === '' || $matKhau === '') {
        $error = 'Vui lòng nhập đầy đủ thông tin!';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ!';

    } elseif (!preg_match('/^[0-9]{10,15}$/', $soDienThoai)) {
        $error = 'Số điện thoại phải có từ 10 đến 15 chữ số!';

    } elseif (strlen($matKhau) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự!';

    } else {

        // 2. Kiểm tra email đã tồn tại
        $stmt = $conn->prepare(
            "SELECT MaND FROM nguoidung WHERE Email = ? LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();

        $emailResult = $stmt->get_result();

        if ($emailResult->num_rows > 0) {
            $error = 'Email này đã được đăng ký!';
        }

        $stmt->close();

        // 3. Kiểm tra số điện thoại đã tồn tại
        if ($error === '') {

            $stmt = $conn->prepare(
                "SELECT MaND FROM nguoidung WHERE SoDienThoai = ? LIMIT 1"
            );
            $stmt->bind_param("s", $soDienThoai);
            $stmt->execute();

            $phoneResult = $stmt->get_result();

            if ($phoneResult->num_rows > 0) {
                $error = 'Số điện thoại này đã được sử dụng!';
            }

            $stmt->close();
        }

        // 4. Thêm tài khoản mới nếu không có lỗi
        if ($error === '') {

            $matKhauHash = password_hash($matKhau, PASSWORD_DEFAULT);

            try {

                $sql = "INSERT INTO nguoidung
                        (HoTen, Email, SoDienThoai, MatKhau, VaiTro, TrangThai)
                        VALUES (?, ?, ?, ?, 'Khách hàng', 'Hoạt động')";

                $insert = $conn->prepare($sql);

                $insert->bind_param(
                    "ssss",
                    $hoTen,
                    $email,
                    $soDienThoai,
                    $matKhauHash
                );

                $insert->execute();
                $insert->close();

                header("Location: login.php?registered=1");
                exit();

            } catch (mysqli_sql_exception $e) {

                // Phòng trường hợp dữ liệu bị trùng trong lúc đăng ký
                if ($e->getCode() == 1062) {
                    $error = 'Email hoặc số điện thoại đã được sử dụng!';
                } else {
                    $error = 'Đăng ký thất bại. Vui lòng thử lại!';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký - Volleyball Arena</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-[#181412] text-amber-50 min-h-screen flex flex-col justify-between font-sans">

<header class="bg-gradient-to-r from-[#653512] via-[#8b4513] to-[#a0522d] border-b-2 border-amber-600/40 px-6 py-4 flex justify-between items-center">
    <a href="index.php" class="text-xl font-black uppercase text-amber-100">
        🏐 VOLLEYBALL ARENA
    </a>

    <div class="flex gap-3 text-xs font-bold">
        <a href="index.php" class="text-amber-200 py-2 px-3">Trang chủ</a>
        <a href="login.php" class="bg-[#362e29] text-amber-200 border border-amber-700/50 px-4 py-2 rounded-xl">
            Đăng nhập
        </a>
    </div>
</header>

<div class="max-w-md w-full mx-auto my-8 bg-[#26201c] border border-amber-900/40 p-8 rounded-2xl shadow-2xl">

    <h2 class="text-2xl font-black text-center text-amber-100 uppercase mb-6">
        Đăng Ký Thành Viên
    </h2>

    <?php if ($error): ?>
        <p class="text-red-400 text-xs text-center mb-4 font-semibold">
            <?= htmlspecialchars($error) ?>
        </p>
    <?php endif; ?>

    <form method="POST" class="space-y-4">

        <div>
            <label class="block text-xs font-bold text-amber-400 mb-1">
                Họ và tên
            </label>

            <input
                type="text"
                name="HoTen"
                required
                maxlength="100"
                value="<?= htmlspecialchars($_POST['HoTen'] ?? '') ?>"
                placeholder="Nhập họ tên của bạn..."
                class="w-full bg-[#1c1815] border border-amber-900/60 rounded-xl px-4 py-2.5 text-sm text-amber-100"
            >
        </div>

        <div>
            <label class="block text-xs font-bold text-amber-400 mb-1">
                Email
            </label>

            <input
                type="email"
                name="Email"
                required
                maxlength="100"
                value="<?= htmlspecialchars($_POST['Email'] ?? '') ?>"
                placeholder="example@gmail.com"
                class="w-full bg-[#1c1815] border border-amber-900/60 rounded-xl px-4 py-2.5 text-sm text-amber-100"
            >
        </div>

        <div>
            <label class="block text-xs font-bold text-amber-400 mb-1">
                Số điện thoại
            </label>

            <input
                type="tel"
                name="SoDienThoai"
                required
                maxlength="15"
                value="<?= htmlspecialchars($_POST['SoDienThoai'] ?? '') ?>"
                placeholder="090xxxxxxx"
                class="w-full bg-[#1c1815] border border-amber-900/60 rounded-xl px-4 py-2.5 text-sm text-amber-100"
            >
        </div>

        <div>
            <label class="block text-xs font-bold text-amber-400 mb-1">
                Mật khẩu
            </label>

            <input
                type="password"
                name="MatKhau"
                required
                minlength="6"
                placeholder="Ít nhất 6 ký tự"
                class="w-full bg-[#1c1815] border border-amber-900/60 rounded-xl px-4 py-2.5 text-sm text-amber-100"
            >
        </div>

        <button
            type="submit"
            class="w-full bg-amber-500 hover:bg-amber-400 text-stone-950 font-black py-3 rounded-xl uppercase text-sm mt-2"
        >
            Đăng Ký Ngay
        </button>

    </form>

    <p class="text-center text-xs text-stone-400 mt-4">
        Đã có tài khoản?
        <a href="login.php" class="text-amber-400 font-bold hover:underline">
            Đăng nhập ngay
        </a>
    </p>

</div>

<footer class="py-4 text-center text-xs text-stone-600">
    © 2026 Volleyball Arena
</footer>

</body>
</html>
```

<?php
session_start();

// Kiểm tra trạng thái đăng nhập
$daDangNhap = isset($_SESSION['MaND']);
$hoTen = $_SESSION['HoTen'] ?? $_SESSION['user'] ?? 'Thành viên';
$vaiTro = $_SESSION['VaiTro'] ?? 'KhachHang';

// Xác định đường dẫn sau khi đăng nhập
if ($daDangNhap && $vaiTro === 'Admin') {
    $linkTaiKhoan = 'admin/index.php';
    $tenTaiKhoan = 'Trang quản trị';
} elseif ($daDangNhap) {
    $linkTaiKhoan = 'customer/index.php';
    $tenTaiKhoan = 'Tài khoản của tôi';
} else {
    $linkTaiKhoan = 'login.php';
    $tenTaiKhoan = 'Đăng nhập';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volleyball Arena - Hệ Thống Đặt Sân Bóng Chuyền</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-[#181412] text-amber-50 min-h-screen font-sans flex flex-col justify-between">

    <!-- HEADER -->
    <header class="bg-[#26201c] border-b border-amber-900/40 px-6 py-4 flex flex-wrap justify-between items-center gap-4 shadow-lg sticky top-0 z-50">

        <a href="index.php" class="flex items-center gap-3">
            <span class="text-2xl">🏐</span>
            <span class="text-lg font-black tracking-wider uppercase text-amber-100">
                VOLLEYBALL ARENA
            </span>
        </a>

        <!-- MENU -->
        <nav class="hidden lg:flex items-center gap-6 text-sm font-bold">
            <a href="index.php" class="text-amber-400 hover:text-amber-300">Trang chủ</a>
            <a href="customer/booking_demo.php" class="text-stone-300 hover:text-amber-300">Đặt sân ngay</a>
            <a href="#features" class="text-stone-300 hover:text-amber-300">Loại sân & Tiện ích</a>
            <a href="#pricing" class="text-stone-300 hover:text-amber-300">Bảng giá</a>
            <a href="#rules" class="text-stone-300 hover:text-amber-300">Luật cơ bản</a>
            <a href="contact.php" class="text-stone-300 hover:text-amber-300">Liên hệ</a>
        </nav>

       <!-- TÀI KHOẢN -->
<div class="flex items-center gap-2">

    <?php if ($daDangNhap): ?>

        <div class="text-right hidden sm:block">
            <p class="text-xs text-amber-200 font-bold">
                Xin chào, <?= htmlspecialchars($hoTen, ENT_QUOTES, 'UTF-8') ?>
            </p>
            <p class="text-[10px] text-stone-400">
                <?= $vaiTro === 'Admin' ? 'Quản trị viên' : 'Khách hàng' ?>
            </p>
        </div>

        <?php if ($vaiTro === 'Admin'): ?>
            <a href="admin/index.php"
               class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-xl text-xs font-bold">
                Trang Admin
            </a>
        <?php else: ?>
            <a href="customer/index.php"
               class="bg-amber-500 hover:bg-amber-400 text-stone-950 px-4 py-2 rounded-xl text-xs font-bold">
                Tài khoản
            </a>
        <?php endif; ?>

        <a href="logout.php"
           class="bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded-xl text-xs font-bold">
            Đăng xuất
        </a>

    <?php else: ?>

        <a href="login.php"
           class="bg-[#362e29] text-amber-200 border border-amber-700/50 px-3 py-2 rounded-xl text-xs font-bold">
            Đăng nhập
        </a>

        <a href="register.php"
           class="bg-amber-500 text-stone-950 px-3 py-2 rounded-xl text-xs font-bold">
            Đăng ký
        </a>

        <a href="login.php?role=admin"
           class="bg-blue-600 hover:bg-blue-500 text-white px-3 py-2 rounded-xl text-xs font-bold">
            Admin
        </a>

    <?php endif; ?>

</div>
    </header>

    <!-- NỘI DUNG CHÍNH -->
    <main class="max-w-7xl mx-auto px-6 py-8 w-full space-y-12">

        <!-- BANNER -->
        <div class="relative rounded-3xl overflow-hidden shadow-2xl border border-amber-900/40">

            <div class="absolute inset-0 bg-cover bg-center brightness-50 scale-105"
                 style="background-image: url('https://images.unsplash.com/photo-1612872087720-bb876e2e67d1?q=80&w=1600&auto=format&fit=crop');">
            </div>

            <div class="absolute inset-0 bg-gradient-to-t from-[#181412] via-[#181412]/80 to-transparent"></div>

            <div class="relative z-10 text-center px-6 py-20 md:py-28 space-y-6 max-w-3xl mx-auto">

                <span class="bg-amber-500/20 border border-amber-500/40 text-amber-300 text-xs font-bold px-4 py-1.5 rounded-full uppercase tracking-widest inline-block">
                    ✨ Mặt sân chất lượng dành cho người chơi
                </span>

                <h1 class="text-3xl md:text-5xl font-black uppercase tracking-wide text-amber-100 leading-tight">
                    ĐẶT SÂN BÓNG CHUYỀN<br>
                    <span class="text-amber-400">NHANH CHÓNG & CHUYÊN NGHIỆP</span>
                </h1>

                <p class="text-sm md:text-base text-stone-200 max-w-2xl mx-auto leading-relaxed">
                    Hệ thống đặt sân bóng chuyền với 2 sân, cho phép lựa chọn ngày,
                    giờ bắt đầu và giờ kết thúc linh hoạt, hỗ trợ đặt cọc trực tuyến.
                </p>

                <div class="pt-2">
                    <a href="customer/booking_demo.php"
                       class="inline-block bg-gradient-to-r from-amber-600 via-amber-500 to-amber-600 hover:from-amber-500 hover:to-amber-400 text-stone-950 font-black px-8 py-4 rounded-xl shadow-xl transition uppercase tracking-wider text-sm">
                        ⚡ ĐẶT SÂN NGAY
                    </a>
                </div>

            </div>
        </div>

        <!-- TÍNH NĂNG -->
        <div id="features" class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4">

            <div class="bg-[#26201c] border border-amber-900/40 p-6 rounded-2xl shadow-xl space-y-2">
                <div class="text-2xl">✨</div>
                <h3 class="text-amber-400 font-bold text-sm uppercase">Sân bóng chuyền chất lượng</h3>
                <p class="text-xs text-stone-400 leading-relaxed">
                    Không gian dành cho hoạt động luyện tập và thi đấu bóng chuyền.
                </p>
            </div>

            <div class="bg-[#26201c] border border-amber-900/40 p-6 rounded-2xl shadow-xl space-y-2">
                <div class="text-2xl">⚡</div>
                <h3 class="text-amber-400 font-bold text-sm uppercase">Đặt lịch linh hoạt</h3>
                <p class="text-xs text-stone-400 leading-relaxed">
                    Chọn sân, ngày, giờ bắt đầu và kết thúc theo nhu cầu.
                </p>
            </div>

            <div class="bg-[#26201c] border border-amber-900/40 p-6 rounded-2xl shadow-xl space-y-2">
                <div class="text-2xl">💳</div>
                <h3 class="text-amber-400 font-bold text-sm uppercase">Đặt cọc trực tuyến</h3>
                <p class="text-xs text-stone-400 leading-relaxed">
                    Hỗ trợ thanh toán đặt cọc 50% qua mã QR.
                </p>
            </div>

        </div>

        <!-- BẢNG GIÁ VÀ HƯỚNG DẪN -->
        <div id="pricing" class="grid grid-cols-1 lg:grid-cols-2 gap-8 pt-4">

            <div class="bg-[#26201c] border border-amber-900/40 p-6 rounded-2xl shadow-xl space-y-4">

                <h2 class="text-sm font-black text-amber-400 uppercase tracking-wider pb-3 border-b border-amber-900/30">
                    💲 Bảng giá thuê sân
                </h2>

                <div class="flex justify-between items-center py-2.5 border-b border-amber-900/20 text-sm">
                    <span class="text-stone-300">Giá thuê sân</span>
                    <span class="font-bold text-amber-300">Theo khung giờ</span>
                </div>

                <div class="flex justify-between items-center py-2.5 border-b border-amber-900/20 text-sm">
                    <span class="text-stone-300">Tiền đặt cọc</span>
                    <span class="font-bold text-amber-400">50% tổng tiền</span>
                </div>

                <div class="pt-4">
                    <a href="customer/booking_demo.php"
                       class="block text-center w-full bg-amber-500 hover:bg-amber-400 text-stone-950 font-black py-3.5 rounded-xl transition uppercase tracking-wider text-xs">
                        TIẾN HÀNH ĐẶT SÂN →
                    </a>
                </div>

            </div>

            <div class="bg-[#26201c] border border-amber-900/40 p-6 rounded-2xl shadow-xl space-y-4">

                <h2 class="text-sm font-black text-amber-400 uppercase tracking-wider pb-3 border-b border-amber-900/30">
                    📖 Hướng dẫn đặt sân
                </h2>

                <ul class="text-xs text-stone-300 space-y-3 leading-relaxed">
                    <li>1. Đăng ký hoặc đăng nhập tài khoản.</li>
                    <li>2. Chọn sân và ngày muốn đặt.</li>
                    <li>3. Chọn giờ bắt đầu và giờ kết thúc.</li>
                    <li>4. Kiểm tra thông tin và số tiền cần thanh toán.</li>
                    <li>5. Thực hiện đặt cọc theo hướng dẫn của hệ thống.</li>
                </ul>

            </div>

        </div>

        <!-- LUẬT BÓNG CHUYỀN -->
        <div id="rules" class="bg-[#26201c] border border-amber-900/40 p-6 md:p-8 rounded-2xl shadow-xl space-y-6">

            <div class="border-b border-amber-900/30 pb-4">
                <h2 class="text-base font-black text-amber-400 uppercase tracking-wider">
                    🏐 Luật bóng chuyền căn bản
                </h2>
                <p class="text-xs text-stone-400 mt-1">
                    Một số quy tắc cơ bản dành cho người mới chơi.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

                <div class="bg-[#1c1815] border border-amber-900/40 p-5 rounded-xl space-y-2">
                    <div class="text-amber-400 font-black text-lg">6v6</div>
                    <h4 class="font-bold text-amber-200 text-sm">Đội hình</h4>
                    <p class="text-xs text-stone-400 leading-relaxed">
                        Mỗi đội thường có 6 người thi đấu trên sân.
                    </p>
                </div>

                <div class="bg-[#1c1815] border border-amber-900/40 p-5 rounded-xl space-y-2">
                    <div class="text-amber-400 font-black text-lg">3</div>
                    <h4 class="font-bold text-amber-200 text-sm">Lần chạm bóng</h4>
                    <p class="text-xs text-stone-400 leading-relaxed">
                        Mỗi đội được chạm bóng tối đa 3 lần trước khi đưa bóng sang sân đối phương.
                    </p>
                </div>

                <div class="bg-[#1c1815] border border-amber-900/40 p-5 rounded-xl space-y-2">
                    <div class="text-amber-400 font-black text-lg">25</div>
                    <h4 class="font-bold text-amber-200 text-sm">Cách tính điểm</h4>
                    <p class="text-xs text-stone-400 leading-relaxed">
                        Một hiệp thông thường kết thúc ở 25 điểm và phải hơn đối phương ít nhất 2 điểm.
                    </p>
                </div>

                <div class="bg-[#1c1815] border border-amber-900/40 p-5 rounded-xl space-y-2">
                    <div class="text-amber-400 font-black text-lg">⚠️</div>
                    <h4 class="font-bold text-amber-200 text-sm">Lỗi thường gặp</h4>
                    <p class="text-xs text-stone-400 leading-relaxed">
                        Các lỗi phổ biến gồm chạm lưới, dẫm vạch khi phát bóng và giữ bóng.
                    </p>
                </div>

            </div>
        </div>

    </main>

    <!-- FOOTER -->
    <footer class="bg-[#120e0c] border-t border-amber-900/40 text-stone-300 py-12 mt-20">

        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-2 gap-10">

            <div class="space-y-4">
                <h3 class="text-amber-100 font-black text-base uppercase tracking-wider">
                    🏐 VOLLEYBALL ARENA SYSTEM
                </h3>

                <p class="text-xs text-stone-400">
                    Hệ thống quản lý và đặt sân bóng chuyền.
                </p>

                <p class="text-xs text-stone-400">
                    Copyright © 2026 Volleyball Arena.
                </p>
            </div>

            <div class="space-y-3 text-xs text-stone-300">
                <p class="font-bold text-amber-100 uppercase">
                    THÔNG TIN LIÊN HỆ
                </p>

                <p>📍 Khu vực thể thao</p>
                <p>📞 Thông tin liên hệ sân</p>
                <p>✉️ Email hỗ trợ khách hàng</p>
            </div>

        </div>

    </footer>

</body>
</html>

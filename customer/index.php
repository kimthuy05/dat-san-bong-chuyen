<?php
// index.php - Trang chủ hệ thống đặt sân bóng chuyền Sàn Gỗ Pro
session_start();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volleyball Arena - Hệ Thống Đặt Sân Chuyên Nghiệp</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#181412] text-amber-50 min-h-screen font-sans">

    <!-- NAVBAR / MENU CHUNG -->
    <header class="bg-gradient-to-r from-[#653512] via-[#8b4513] to-[#a0522d] border-b-2 border-amber-600/40 sticky top-0 z-50 shadow-lg">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <!-- Logo hệ thống -->
            <div class="flex items-center gap-3">
                <span class="text-3xl">🏐</span>
                <a href="index.php" class="text-xl font-black tracking-wider uppercase text-amber-100 hover:text-white transition">
                    VOLLEYBALL ARENA
                </a>
            </div>

            <!-- Menu điều hướng chính -->
            <nav class="hidden md:flex items-center gap-6 text-sm font-semibold">
                <a href="index.php" class="text-white border-b-2 border-amber-400 pb-0.5">Trang chủ</a>
                <a href="customer/booking_demo.php" class="text-amber-200 hover:text-white transition">Đặt sân ngay</a>
                <a href="#features" class="text-amber-200 hover:text-white transition">Tiện ích sân</a>
                <a href="#contact" class="text-amber-200 hover:text-white transition">Liên hệ</a>
            </nav>

            <!-- Khu vực tài khoản: Nút Đăng nhập & Đăng ký kết nối chuẩn từ Menu -->
            <div class="flex items-center gap-3">
                <?php if (isset($_SESSION['user'])): ?>
                    <span class="text-xs text-amber-200 font-bold">Chào, <?php echo $_SESSION['user']; ?></span>
                    <a href="logout.php" class="bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded-xl text-xs font-bold transition">Đăng xuất</a>
                <?php else: ?>
                    <a href="login.php" class="bg-[#362e29] hover:bg-[#4a3f37] text-amber-200 border border-amber-700/50 px-4 py-2 rounded-xl text-xs font-bold transition">Đăng nhập</a>
                    <a href="register.php" class="bg-amber-500 hover:bg-amber-400 text-stone-950 px-4 py-2 rounded-xl text-xs font-black transition shadow-md">Đăng ký</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- HERO SECTION (Banner trang chủ) -->
    <section class="relative py-20 px-6 text-center bg-gradient-to-b from-[#26201c] to-[#181412] border-b border-amber-900/30">
        <div class="max-w-4xl mx-auto">
            <span class="bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs font-bold px-4 py-1.5 rounded-full uppercase tracking-widest inline-block mb-4">
                ✨ Mặt Sân Gỗ Taraflex Tiêu Chuẩn Thi Đấu
            </span>
            <h1 class="text-4xl md:text-6xl font-black text-amber-100 uppercase tracking-wide mb-6">
                Đặt Sân Bóng Chuyền <span class="text-amber-500">Nhanh Chóng & Chuyên Nghiệp</span>
            </h1>
            <p class="text-stone-300 text-base md:text-lg mb-8 max-w-2xl mx-auto">
                Hệ thống đặt sân hiện đại với 2 sân tiêu chuẩn, chọn khung giờ linh hoạt theo khoảng thời gian và thanh toán cọc tự động qua VietQR an toàn tuyệt đối.
            </p>
            <div class="flex justify-center gap-4">
                <a href="customer/booking_demo.php" class="bg-gradient-to-r from-amber-600 via-amber-500 to-amber-600 hover:from-amber-500 hover:to-amber-400 text-stone-950 font-black px-8 py-4 rounded-xl shadow-xl transition-all uppercase tracking-wider text-sm flex items-center gap-2">
                    <span>🏐 Đặt Sân Ngay Lập Tức</span>
                </a>
            </div>
        </div>
    </section>

    <!-- FEATURES / TIỆN ÍCH -->
    <section id="features" class="max-w-7xl mx-auto px-6 py-16">
        <h2 class="text-2xl font-black text-center text-amber-400 uppercase tracking-wider mb-12">Tại Sao Nên Chọn Volleyball Arena?</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-[#26201c] border border-amber-900/40 p-6 rounded-2xl shadow-lg">
                <div class="text-3xl mb-4">🪵</div>
                <h3 class="font-bold text-lg text-amber-200 mb-2">Mặt Sân Gỗ Cao Cấp</h3>
                <p class="text-sm text-stone-400">Sân thảm gỗ chuyên dụng chống trơn trượt, giảm chấn thương tối đa cho vận động viên.</p>
            </div>
            <div class="bg-[#26201c] border border-amber-900/40 p-6 rounded-2xl shadow-lg">
                <div class="text-3xl mb-4">⚡</div>
                <h3 class="font-bold text-lg text-amber-200 mb-2">Đặt Lịch Chọn Khoảng Giờ</h3>
                <p class="text-sm text-stone-400">Giao diện trực quan chọn giờ bắt đầu và kết thúc linh hoạt, tự động tính tiền chính xác.</p>
            </div>
            <div class="bg-[#26201c] border border-amber-900/40 p-6 rounded-2xl shadow-lg">
                <div class="text-3xl mb-4">💳</div>
                <h3 class="font-bold text-lg text-amber-200 mb-2">Thanh Toán VietQR 50%</h3>
                <p class="text-sm text-stone-400">Quét mã cọc trực tuyến nhanh chóng, lịch đặt được giữ chỗ tự động.</p>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer id="contact" class="bg-[#120f0d] border-t border-amber-900/40 py-8 text-center text-xs text-stone-500">
        <p>© 2026 Volleyball Arena Management System. All rights reserved.</p>
    </footer>

</body>
</html>
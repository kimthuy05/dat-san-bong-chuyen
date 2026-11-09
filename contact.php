<?php
// contact.php - Trang liên hệ & hỗ trợ khách hàng Volleyball Arena
session_start();

$message_sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Giả lập xử lý gửi liên hệ thành công
    $message_sent = true;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liên Hệ & Hỗ Trợ - Volleyball Arena</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#181412] text-amber-50 min-h-screen font-sans flex flex-col justify-between">

    <!-- HEADER & THANH MENU -->
    <header class="bg-[#26201c] border-b border-amber-900/40 px-6 py-4 flex justify-between items-center shadow-lg sticky top-0 z-50">
        <a href="index.php" class="flex items-center gap-3 group cursor-pointer">
            <span class="text-2xl">🏐</span>
            <span class="text-lg font-black tracking-wider uppercase text-amber-100 group-hover:text-amber-400 transition">
                VOLLEYBALL ARENA
            </span>
        </a>
        
        <nav class="hidden lg:flex items-center gap-6 text-sm font-bold">
            <a href="index.php" class="text-stone-300 hover:text-amber-300 transition">Trang chủ</a>
            <a href="customer/booking_demo.php" class="text-stone-300 hover:text-amber-300 transition">Đặt sân ngay</a>
            <a href="index.php#pricing" class="text-stone-300 hover:text-amber-300 transition">Bảng giá</a>
            <a href="index.php#rules" class="text-stone-300 hover:text-amber-300 transition">Luật bóng chuyền</a>
            <a href="contact.php" class="text-amber-400 hover:text-amber-300 transition">Liên hệ</a>
        </nav>

        <div class="flex items-center gap-3">
            <?php if (isset($_SESSION['user'])): ?>
                <span class="text-xs text-amber-200 font-bold hidden sm:inline">Chào, <?php echo htmlspecialchars($_SESSION['user']); ?></span>
                <a href="logout.php" class="bg-red-600 hover:bg-red-500 text-white px-3.5 py-1.5 rounded-xl text-xs font-bold transition">Đăng xuất</a>
            <?php else: ?>
                <a href="login.php" class="bg-[#362e29] hover:bg-[#4a3f37] text-amber-200 border border-amber-700/50 px-3.5 py-1.5 rounded-xl text-xs font-bold transition">Đăng nhập</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="max-w-7xl mx-auto px-6 py-10 w-full space-y-10">
        
        <!-- TIÊU ĐỀ TRANG -->
        <div class="text-center space-y-3">
            <span class="bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs font-bold px-4 py-1.5 rounded-full uppercase tracking-widest inline-block">
                📞 24/7 Support Center
            </span>
            <h1 class="text-3xl md:text-4xl font-black uppercase text-amber-100">Liên Hệ Với Chúng Tôi</h1>
            <p class="text-xs md:text-sm text-stone-400 max-w-xl mx-auto leading-relaxed">
                Bạn muốn đặt lịch cố định tháng, tư vấn tổ chức giải đấu hoặc có thắc mắc cần giải đáp? Hãy gửi tin nhắn cho chúng tôi ngay nhé!
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- CỘT TRÁI: FORM GỬI LỜI NHẮN -->
            <div class="bg-[#26201c] border border-amber-900/40 p-8 rounded-2xl shadow-xl space-y-6">
                <h2 class="text-sm font-black text-amber-400 uppercase tracking-wider pb-3 border-b border-amber-900/30 flex items-center gap-2">
                    <span>✉️</span> Gửi Lời Nhắn Trực Tuyến
                </h2>

                <?php if ($message_sent): ?>
                    <div class="bg-emerald-500/10 border border-emerald-500/40 text-emerald-400 p-4 rounded-xl text-xs space-y-1">
                        <p class="font-bold">✓ Cảm ơn bạn đã liên hệ!</p>
                        <p class="text-stone-300">Chúng tôi đã nhận được thông tin và sẽ phản hồi lại trong thời gian sớm nhất.</p>
                    </div>
                <?php endif; ?>

                <form action="contact.php" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-stone-400 uppercase mb-1">Họ và tên của bạn *</label>
                        <input type="text" required placeholder="Nhập họ tên" 
                               class="w-full bg-[#1c1815] border border-amber-900/50 rounded-xl px-4 py-3 text-sm text-amber-100 focus:outline-none focus:border-amber-500 transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-stone-400 uppercase mb-1">Số điện thoại / Zalo *</label>
                        <input type="tel" required placeholder="Nhập số điện thoại" 
                               class="w-full bg-[#1c1815] border border-amber-900/50 rounded-xl px-4 py-3 text-sm text-amber-100 focus:outline-none focus:border-amber-500 transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-stone-400 uppercase mb-1">Nội dung liên hệ *</label>
                        <textarea rows="4" required placeholder="Cần tư vấn đặt cố định tháng, giải đấu hoặc góp ý..." 
                                  class="w-full bg-[#1c1815] border border-amber-900/50 rounded-xl px-4 py-3 text-sm text-amber-100 focus:outline-none focus:border-amber-500 transition"></textarea>
                    </div>

                    <button type="submit" class="w-full bg-gradient-to-r from-amber-600 via-amber-500 to-amber-600 hover:from-amber-500 hover:to-amber-400 text-stone-950 font-black py-3.5 rounded-xl shadow-lg transition uppercase tracking-wider text-xs cursor-pointer">
                        GỬI YÊU CẦU NAY →
                    </button>
                </form>
            </div>

            <!-- CỘT PHẢI: THÔNG TIN CHI TIẾT & BẢN ĐỒ GIẢ LẬP -->
            <div class="space-y-6">
                
                <!-- THÔNG TIN -->
                <div class="bg-[#26201c] border border-amber-900/40 p-8 rounded-2xl shadow-xl space-y-4">
                    <h2 class="text-sm font-black text-amber-400 uppercase tracking-wider pb-3 border-b border-amber-900/30 flex items-center gap-2">
                        <span>📍</span> Thông Tin Trực Tiếp
                    </h2>

                    <div class="space-y-4 text-xs text-stone-300">
                        <div class="flex items-start gap-3">
                            <span class="text-lg bg-amber-500/20 p-2 rounded-xl text-amber-400">🏠</span>
                            <div>
                                <p class="font-bold text-amber-100 mb-0.5">Địa chỉ sân đấu:</p>
                                <p class="text-stone-400">Khu Vực Thể Thao Chuyên Nghiệp, TP. Hồ Chí Minh</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <span class="text-lg bg-amber-500/20 p-2 rounded-xl text-amber-400">📞</span>
                            <div>
                                <p class="font-bold text-amber-100 mb-0.5">Hotline đặt sân & Zalo:</p>
                                <p class="text-amber-400 font-mono font-bold">0901.234.567 / 0988.777.666</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <span class="text-lg bg-amber-500/20 p-2 rounded-xl text-amber-400">⏰</span>
                            <div>
                                <p class="font-bold text-amber-100 mb-0.5">Giờ hoạt động:</p>
                                <p class="text-stone-400">Mở cửa từ 06:00 đến 22:00 tất cả các ngày trong tuần (Kể cả lễ, Tết)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BẢN ĐỒ MINH HỌA -->
                <div class="bg-[#26201c] border border-amber-900/40 p-4 rounded-2xl shadow-xl text-center">
                    <div class="bg-[#1c1815] border border-amber-900/30 rounded-xl p-8 flex flex-col items-center justify-center space-y-2">
                        <span class="text-4xl">🗺️</span>
                        <p class="text-xs font-bold text-amber-200 uppercase">Sơ Đồ Vị Trí Sân Bóng Chuyền</p>
                        <p class="text-[11px] text-stone-500">Thuận tiện di chuyển, có bãi đậu xe máy & ô tô rộng rãi miễn phí.</p>
                    </div>
                </div>

            </div>

        </div>

    </main>

    <!-- FOOTER -->
    <footer class="bg-[#120f0d] border-t border-amber-900/40 py-6 text-center text-xs text-stone-500 mt-16">
        <p>© 2026 Volleyball Arena Management System. All rights reserved.</p>
    </footer>

</body>
</html>
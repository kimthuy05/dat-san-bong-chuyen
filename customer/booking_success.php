<?php
// customer/booking_success.php - Biên lai tách bạch Tiền cọc và Số dư
session_start();
if (isset($_SESSION['pending_booking'])) {
    $_SESSION['bookings'][] = $_SESSION['pending_booking'];
    unset($_SESSION['pending_booking']);
}
$court = $_GET['court'] ?? 'Sân 1';
$date = $_GET['date'] ?? date('Y-m-d');
$start = $_GET['start'] ?? '07:00';
$end = $_GET['end'] ?? '09:00';
$total = intval($_GET['total'] ?? 300000);
$deposit = intval($_GET['deposit'] ?? 150000);
$remaining = $total - $deposit;
$code = "VBA" . rand(100000, 999999);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8"><title>Xác Nhận Đặt Cọc - Volleyball Arena</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#181412] text-amber-50 min-h-screen font-sans flex flex-col justify-between">
    <header class="bg-[#26201c] border-b border-amber-900/40 px-6 py-4 shadow-lg"><a href="../index.php" class="text-lg font-black uppercase text-amber-100">VOLLEYBALL ARENA</a></header>
    <main class="max-w-xl mx-auto px-6 py-10 w-full">
        <div class="bg-[#26201c] border border-amber-900/50 rounded-3xl p-8 shadow-2xl space-y-6 text-center relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1.5 bg-emerald-500"></div>
            <div class="w-16 h-16 bg-emerald-500/20 text-emerald-400 border border-emerald-500/50 rounded-full flex items-center justify-center mx-auto text-3xl font-bold">✓</div>
            <div class="space-y-1">
                <span class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 text-xs font-bold px-3 py-1 rounded-full uppercase">Giai đoạn 1: Đặt cọc giữ chỗ thành công</span>
                <h1 class="text-xl font-black uppercase text-amber-100 mt-2">Biên Lai Xác Nhận Cọc Sân</h1>
            </div>
            <div class="bg-[#1c1815] border border-amber-900/40 rounded-2xl p-5 text-left space-y-3 text-xs">
                <div class="flex justify-between border-b border-amber-900/30 pb-2"><span class="text-stone-400">Mã giao dịch:</span><span class="font-mono font-bold text-amber-300"><?php echo $code; ?></span></div>
                <div class="flex justify-between"><span class="text-stone-400">Sân:</span><span class="font-bold text-amber-100"><?php echo htmlspecialchars($court); ?></span></div>
                <div class="flex justify-between"><span class="text-stone-400">Ngày:</span><span class="font-bold text-amber-100"><?php echo $date; ?></span></div>
                <div class="flex justify-between"><span class="text-stone-400">Khung giờ:</span><span class="font-bold text-amber-400"><?php echo "$start - $end"; ?></span></div>
                <div class="border-t border-amber-900/30 pt-3 space-y-2">
                    <div class="flex justify-between"><span class="text-stone-400">Tổng tiền sân:</span><span class="font-bold text-stone-200"><?php echo number_format($total, 0, ',', '.'); ?> đ</span></div>
                    <div class="flex justify-between bg-emerald-500/10 p-2 rounded-lg border border-emerald-500/30"><span class="font-bold text-emerald-400">1. Đã cọc (50%):</span><span class="font-black text-emerald-400"><?php echo number_format($deposit, 0, ',', '.'); ?> đ (Đã trả)</span></div>
                    <div class="flex justify-between bg-amber-500/10 p-2 rounded-lg border border-amber-500/30"><span class="font-bold text-amber-300">2. Số dư (Thanh toán tại sân):</span><span class="font-black text-amber-300"><?php echo number_format($remaining, 0, ',', '.'); ?> đ (Chưa trả)</span></div>
                </div>
            </div>
            <a href="../index.php" class="inline-block bg-amber-500 text-stone-950 font-black px-6 py-3 rounded-xl uppercase tracking-wider text-xs">VỀ TRANG CHỦ</a>
        </div>
    </main>
    <footer class="bg-[#120f0d] border-t border-amber-900/40 py-6 text-center text-xs text-stone-500">© 2026 Volleyball Arena</footer>
</body>
</html>
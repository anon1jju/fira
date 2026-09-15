<?php
$pageTitle = 'Dashboard';

$dataDir = __DIR__ . '/data';
$itemFile = $dataDir . '/item.json';
$salesFile = $dataDir . '/sales.json';
$transactionsFile = $dataDir . '/transactions.json';
$setoranFile = $dataDir . '/setoran.json';

// Helper function untuk membaca JSON
function readJsonFile($file, $default = [])
{
    if (!file_exists($file)) {
        return $default;
    }
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    return is_array($data) ? $data : $default;
}

// Helper formatting
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatRupiah($angka)
{
    return 'Rp' . number_format((int) $angka, 0, ',', '.');
}

// Ambil data
$itemList = readJsonFile($itemFile, []);
$salesList = readJsonFile($salesFile, []);
$transactionsList = readJsonFile($transactionsFile, []);
$setoranList = readJsonFile($setoranFile, []);

// Petakan status setoran berdasarkan transaction_id
$setoranMap = [];
foreach ($setoranList as $setor) {
    $setoranMap[$setor['transaction_id']] = $setor;
}

// Kalkulasi Statistik
$totalProduk = count($itemList);
$totalAgen = count($salesList);

$totalStok = array_sum(array_column($itemList, 'stok'));

$totalQtyTransaksi = array_sum(array_map(function ($t) {
    return (int) ($t['total_qty'] ?? 0);
}, $transactionsList));

$totalNilaiTransaksi = array_sum(array_map(function ($t) {
    return (int) ($t['total_nilai'] ?? 0);
}, $transactionsList));

// Ambil 5 Transaksi Terbaru (Reverse array)
$recentTransactions = array_slice(array_reverse($transactionsList), 0, 5);

// Generate Aktivitas Terbaru (Diambil dari transaksi terakhir)
$recentActivities = array_slice(array_reverse($transactionsList), 0, 4);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - Fira Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 font-sans">
    <div class="min-h-screen flex flex-col lg:flex-row">
        <?php include 'sidebar.php'; ?>

        <main class="flex-1 p-4 sm:p-6 lg:p-8 min-w-0 max-w-7xl mx-auto w-full">
            
            <!-- HEADER -->
            <header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight">Dashboard</h1>
                    <p class="text-slate-500 mt-1.5 text-sm sm:text-base">
                        Selamat datang di panel admin Fira. Berikut adalah ringkasan data saat ini.
                    </p>
                </div>
                
                <div class="inline-flex items-center gap-2 rounded-full bg-white border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm w-fit">
                    <span class="w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse"></span>
                    Admin Online
                </div>
            </header>

            <!-- STATISTIC CARDS -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                
                <!-- Card 1 -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 flex items-center justify-between gap-4 group hover:shadow-md transition-shadow">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Total Produk</p>
                        <h3 class="text-2xl font-bold text-slate-800"><?= e($totalProduk) ?> <span class="text-sm font-medium text-slate-400">item</span></h3>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 flex items-center justify-between gap-4 group hover:shadow-md transition-shadow">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Total Agen</p>
                        <h3 class="text-2xl font-bold text-slate-800"><?= e($totalAgen) ?> <span class="text-sm font-medium text-slate-400">orang</span></h3>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center group-hover:bg-amber-500 group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 flex items-center justify-between gap-4 group hover:shadow-md transition-shadow">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Barang Keluar</p>
                        <h3 class="text-2xl font-bold text-slate-800"><?= e($totalQtyTransaksi) ?> <span class="text-sm font-medium text-slate-400">pcs</span></h3>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-500 flex items-center justify-center group-hover:bg-purple-500 group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    </div>
                </div>

                <!-- Card 4 -->
                <div class="bg-blue-600 rounded-2xl shadow-md border border-blue-500 p-5 flex items-center justify-between gap-4 relative overflow-hidden">
                    <div class="relative z-10">
                        <p class="text-xs font-semibold text-blue-200 uppercase tracking-wider mb-1">Nilai Transaksi</p>
                        <h3 class="text-2xl font-bold text-white"><?= e(formatRupiah($totalNilaiTransaksi)) ?></h3>
                    </div>
                    <!-- Decorative Background element -->
                    <div class="absolute -right-4 -bottom-4 opacity-20">
                        <svg class="w-24 h-24 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
            </section>

            <!-- MAIN GRID CONTENT -->
            <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                
                <!-- KIRI: TABEL TRANSAKSI TERBARU -->
                <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 flex flex-col h-full overflow-hidden">
                    <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-50/50">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800">Transaksi Terbaru</h2>
                            <p class="text-sm text-slate-500 mt-1">
                                5 Pengambilan barang terakhir oleh agen beserta status setoran.
                            </p>
                        </div>
                        <a href="agen.php" class="w-full sm:w-auto text-center rounded-xl bg-white border border-slate-200 text-slate-700 px-4 py-2 text-sm font-semibold hover:bg-slate-50 transition shadow-sm">
                            Lihat Semua
                        </a>
                    </div>

                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-sm text-left whitespace-nowrap">
                            <thead class="bg-white text-slate-500 border-b border-slate-200 text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="py-4 px-5 font-semibold">Agen</th>
                                    <th class="py-4 px-5 font-semibold">Tanggal</th>
                                    <th class="py-4 px-5 font-semibold text-center">Total Item</th>
                                    <th class="py-4 px-5 font-semibold text-right">Nilai</th>
                                    <th class="py-4 px-5 font-semibold text-center">Status Setoran</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($recentTransactions)): ?>
                                    <tr>
                                        <td colspan="5" class="py-10 text-center text-slate-500">
                                            <svg class="w-10 h-10 mx-auto text-slate-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                            Belum ada transaksi.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentTransactions as $trx): 
                                        $trxId = $trx['id'] ?? 0;
                                        $setorData = $setoranMap[$trxId] ?? null;
                                    ?>
                                        <tr class="hover:bg-slate-50/50 transition">
                                            <td class="py-3.5 px-5">
                                                <div class="font-bold text-slate-800"><?= e($trx['nama_sales'] ?? '-') ?></div>
                                                <div class="text-[11px] text-slate-500"><?= e($trx['hp_sales'] ?? '-') ?></div>
                                            </td>
                                            <td class="py-3.5 px-5 text-slate-600">
                                                <?= e(date('d M Y', strtotime($trx['tanggal'] ?? ''))) ?>
                                            </td>
                                            <td class="py-3.5 px-5 text-center">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-600">
                                                    <?= e($trx['total_qty'] ?? 0) ?> pcs
                                                </span>
                                            </td>
                                            <td class="py-3.5 px-5 text-right font-semibold text-slate-800">
                                                <?= e(formatRupiah($trx['total_nilai'] ?? 0)) ?>
                                            </td>
                                            <td class="py-3.5 px-5 text-center">
                                                <?php if (!$setorData): ?>
                                                    <span class="inline-flex rounded-full bg-amber-100 border border-amber-200 text-amber-700 px-2.5 py-1 text-[10px] uppercase font-bold tracking-wider">
                                                        Belum Disetor
                                                    </span>
                                                <?php elseif (($setorData['kurang'] ?? 0) > 0): ?>
                                                    <span class="inline-flex rounded-full bg-red-100 border border-red-200 text-red-700 px-2.5 py-1 text-[10px] uppercase font-bold tracking-wider">
                                                        Ada Hutang
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex rounded-full bg-green-100 border border-green-200 text-green-700 px-2.5 py-1 text-[10px] uppercase font-bold tracking-wider">
                                                        Lunas
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- KANAN: AKTIVITAS TERBARU -->
                <div class="xl:col-span-1 bg-white rounded-2xl shadow-sm border border-slate-100 p-5 sm:p-6">
                    <h2 class="text-lg font-bold text-slate-800 mb-1">Aktivitas Terakhir</h2>
                    <p class="text-sm text-slate-500 mb-6">
                        Log transaksi pengambilan barang terbaru.
                    </p>

                    <?php if (empty($recentActivities)): ?>
                        <div class="text-center text-slate-400 py-8 text-sm border-2 border-dashed border-slate-100 rounded-xl">
                            Belum ada aktivitas.
                        </div>
                    <?php else: ?>
                        <div class="relative border-l border-slate-200 ml-3 space-y-6 pb-2">
                            <?php 
                            $colors = ['bg-blue-500', 'bg-green-500', 'bg-amber-500', 'bg-purple-500'];
                            foreach ($recentActivities as $index => $activity): 
                                $color = $colors[$index % count($colors)];
                            ?>
                                <div class="relative pl-5">
                                    <span class="absolute -left-1.5 top-1.5 w-3 h-3 rounded-full <?= $color ?> ring-4 ring-white"></span>
                                    
                                    <div>
                                        <p class="text-sm font-bold text-slate-800">
                                            <?= e($activity['nama_sales'] ?? 'Agen') ?> <span class="font-normal text-slate-600">mengambil barang</span>
                                        </p>
                                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                                            Total pengambilan sebanyak <strong><?= e($activity['total_qty'] ?? 0) ?> pcs</strong> senilai <span class="font-semibold"><?= e(formatRupiah($activity['total_nilai'] ?? 0)) ?></span>. Stok telah disesuaikan.
                                        </p>
                                        <p class="text-[10px] text-slate-400 mt-1.5 font-medium uppercase tracking-wider">
                                            <?= e(date('d M Y - H:i', strtotime($activity['created_at'] ?? 'now'))) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
            </section>
        </main>
    </div>
</body>
</html>
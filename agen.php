<?php
$dataDir = __DIR__ . '/data';
$itemFile = $dataDir . '/item.json';
$salesFile = $dataDir . '/sales.json';
$transactionsFile = $dataDir . '/transactions.json';
$setoranFile = $dataDir . '/setoran.json';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

function readJsonFile($file, $default = [])
{
    if (!file_exists($file)) {
        file_put_contents($file, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    $content = file_get_contents($file);
    $data = json_decode($content, true);

    return is_array($data) ? $data : $default;
}

function writeJsonFile($file, $data)
{
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function nextId($items)
{
    if (empty($items)) {
        return 1;
    }

    $ids = array_map('intval', array_column($items, 'id'));

    return empty($ids) ? 1 : max($ids) + 1;
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatRupiah($angka)
{
    return 'Rp' . number_format((int) $angka, 0, ',', '.');
}

$itemList = readJsonFile($itemFile, []);
$salesList = readJsonFile($salesFile, []);
$transactionsList = readJsonFile($transactionsFile, []);
$setoranList = readJsonFile($setoranFile, []);

// Petakan data setoran berdasarkan transaction_id agar mudah dicek statusnya
$setoranMap = [];
foreach ($setoranList as $setor) {
    $setoranMap[$setor['transaction_id']] = $setor;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah_sales') {
        $nama = trim($_POST['nama'] ?? '');
        $hp = trim($_POST['hp'] ?? '');

        if ($nama !== '' && $hp !== '') {
            $salesList[] = [
                'id' => nextId($salesList),
                'nama' => $nama,
                'hp' => $hp,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            writeJsonFile($salesFile, $salesList);

            header('Location: agen.php?success=sales');
            exit;
        }

        header('Location: agen.php?error=sales');
        exit;
    }

    if ($action === 'hapus_sales') {
        $salesId = (int) ($_POST['sales_id'] ?? 0);

        $salesList = array_values(array_filter($salesList, function ($sales) use ($salesId) {
            return (int) ($sales['id'] ?? 0) !== $salesId;
        }));

        writeJsonFile($salesFile, $salesList);

        header('Location: agen.php?success=hapus_sales');
        exit;
    }

    if ($action === 'tambah_transaksi') {
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $salesId = (int) ($_POST['sales_id'] ?? 0);
        $itemIds = $_POST['item_id'] ?? [];
        $jumlahItems = $_POST['jumlah'] ?? [];

        $selectedSales = null;

        foreach ($salesList as $sales) {
            if ((int) ($sales['id'] ?? 0) === $salesId) {
                $selectedSales = $sales;
                break;
            }
        }

        $requestedItems = [];

        foreach ($itemIds as $index => $itemId) {
            $itemId = (int) $itemId;
            $jumlah = (int) ($jumlahItems[$index] ?? 0);

            if ($itemId <= 0 || $jumlah <= 0) {
                continue;
            }

            if (!isset($requestedItems[$itemId])) {
                $requestedItems[$itemId] = 0;
            }

            $requestedItems[$itemId] += $jumlah;
        }

        $items = [];
        $totalQty = 0;
        $totalNilai = 0;
        $canProcess = true;

        foreach ($requestedItems as $itemId => $jumlah) {
            $selectedItemIndex = null;

            foreach ($itemList as $index => $item) {
                if ((int) ($item['id'] ?? 0) === (int) $itemId) {
                    $selectedItemIndex = $index;
                    break;
                }
            }

            if ($selectedItemIndex === null) {
                $canProcess = false;
                break;
            }

            $selectedItem = $itemList[$selectedItemIndex];
            $stokGudang = (int) ($selectedItem['stok'] ?? 0);
            $hargaPerPcs = (int) ($selectedItem['harga_per_pcs'] ?? 0);
            $subtotal = $hargaPerPcs * $jumlah;

            if ($jumlah > $stokGudang) {
                $canProcess = false;
                break;
            }

            $items[] = [
                'item_id' => $itemId,
                'nama_produk' => $selectedItem['nama'] ?? '-',
                'kategori' => $selectedItem['kategori'] ?? '-',
                'jumlah' => $jumlah,
                'harga_per_pcs' => $hargaPerPcs,
                'subtotal' => $subtotal,
                'stok_sebelum' => $stokGudang,
                'stok_sesudah' => $stokGudang - $jumlah,
            ];

            $totalQty += $jumlah;
            $totalNilai += $subtotal;
        }

        if ($selectedSales && !empty($items) && $canProcess) {
            foreach ($items as $transactionItem) {
                foreach ($itemList as &$item) {
                    if ((int) ($item['id'] ?? 0) === (int) $transactionItem['item_id']) {
                        $item['stok'] = max(0, (int) ($item['stok'] ?? 0) - (int) $transactionItem['jumlah']);
                        break;
                    }
                }
                unset($item);
            }

            $transactionsList[] = [
                'id' => nextId($transactionsList),
                'tanggal' => $tanggal,
                'sales_id' => $selectedSales['id'],
                'nama_sales' => $selectedSales['nama'],
                'hp_sales' => $selectedSales['hp'],
                'items' => $items,
                'total_qty' => $totalQty,
                'total_nilai' => $totalNilai,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            writeJsonFile($transactionsFile, $transactionsList);
            writeJsonFile($itemFile, $itemList);

            header('Location: agen.php?success=transaksi');
            exit;
        }

        header('Location: agen.php?error=stok');
        exit;
    }

    if ($action === 'hapus_transaksi') {
        $transactionId = (int) ($_POST['transaction_id'] ?? 0);

        $transactionsList = array_values(array_filter($transactionsList, function ($transaction) use ($transactionId) {
            return (int) ($transaction['id'] ?? 0) !== $transactionId;
        }));

        writeJsonFile($transactionsFile, $transactionsList);

        header('Location: agen.php?success=hapus_transaksi');
        exit;
    }
}

$totalSales = count($salesList);
$totalProduk = count($itemList);
$totalTransactions = count($transactionsList);

$totalBarangDiambil = array_sum(array_map(function ($transaction) {
    return (int) ($transaction['total_qty'] ?? 0);
}, $transactionsList));

$totalNilaiTransaksi = array_sum(array_map(function ($transaction) {
    return (int) ($transaction['total_nilai'] ?? 0);
}, $transactionsList));

$transactionsDesc = array_reverse($transactionsList);

$itemListForJs = json_encode(
    $itemList,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Sales - Fira</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
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
            
            <!-- ALERTS -->
            <?php if (isset($_GET['success']) && $_GET['success'] === 'sales'): ?>
                <div class="mb-6 rounded-xl bg-green-50 border border-green-200 text-green-700 px-5 py-3.5 text-sm font-medium flex items-center gap-2 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Personil agen berhasil ditambahkan.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && $_GET['success'] === 'transaksi'): ?>
                <div class="mb-6 rounded-xl bg-green-50 border border-green-200 text-green-700 px-5 py-3.5 text-sm font-medium flex items-center gap-2 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Transaksi berhasil disimpan, stok gudang telah disesuaikan.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && $_GET['success'] === 'hapus_sales' || (isset($_GET['success']) && $_GET['success'] === 'hapus_transaksi')): ?>
                <div class="mb-6 rounded-xl bg-slate-100 border border-slate-200 text-slate-700 px-5 py-3.5 text-sm font-medium flex items-center gap-2 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Data berhasil dihapus. <?= $_GET['success'] === 'hapus_transaksi' ? '(Stok tidak otomatis kembali)' : '' ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="mb-6 rounded-xl bg-red-50 border border-red-200 text-red-700 px-5 py-3.5 text-sm font-medium flex items-center gap-2 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <?= $_GET['error'] === 'stok' ? 'Transaksi gagal. Pastikan agen dipilih, produk dipilih, dan jumlah tidak melebihi stok.' : 'Data agen tidak valid. Nama dan nomor HP wajib diisi.' ?>
                </div>
            <?php endif; ?>

            <!-- HEADER -->
            <header class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight">Manajemen Agen</h1>
                    <p class="text-slate-500 mt-1.5 text-sm sm:text-base">
                        Kelola data agen, pengeluaran stok, dan riwayat transaksi secara terpusat.
                    </p>
                </div>
            </header>

            <!-- STATISTIC CARDS -->
            <section class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
                    <div class="text-slate-400 text-sm font-medium mb-1">Total Agen</div>
                    <div class="text-2xl font-bold text-slate-800"><?= e($totalSales) ?></div>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
                    <div class="text-slate-400 text-sm font-medium mb-1">Total Produk</div>
                    <div class="text-2xl font-bold text-slate-800"><?= e($totalProduk) ?></div>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
                    <div class="text-slate-400 text-sm font-medium mb-1">Total Transaksi</div>
                    <div class="text-2xl font-bold text-slate-800"><?= e($totalTransactions) ?></div>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
                    <div class="text-slate-400 text-sm font-medium mb-1">Barang Keluar</div>
                    <div class="text-2xl font-bold text-slate-800"><?= e($totalBarangDiambil) ?> <span class="text-sm font-medium text-slate-500">pcs</span></div>
                </div>
                <div class="bg-blue-600 rounded-2xl shadow-md p-5 col-span-2 lg:col-span-1 text-white">
                    <div class="text-blue-200 text-sm font-medium mb-1">Total Nilai Transaksi</div>
                    <div class="text-2xl font-bold"><?= e(formatRupiah($totalNilaiTransaksi)) ?></div>
                </div>
            </section>

            <!-- MIDDLE SECTION: DAFTAR AGEN & FORM TRANSAKSI -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- KIRI: DAFTAR AGEN -->
                <section class="lg:col-span-1 bg-white rounded-2xl shadow-sm border border-slate-100 flex flex-col h-full">
                    <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50 rounded-t-2xl">
                        <h2 class="text-base font-bold text-slate-800">Daftar Agen</h2>
                        <button type="button" onclick="openSalesModal()" class="text-xs font-semibold bg-white border border-slate-200 text-slate-700 px-3 py-1.5 rounded-lg hover:bg-slate-50 transition shadow-sm">
                            + Tambah
                        </button>
                    </div>
                    <div class="p-0 flex-1 overflow-y-auto max-h-[500px]">
                        <?php if (empty($salesList)): ?>
                            <div class="p-5 text-center text-sm text-slate-500">
                                Belum ada data agen.
                            </div>
                        <?php else: ?>
                            <ul class="divide-y divide-slate-100">
                                <?php foreach ($salesList as $index => $sales): ?>
                                    <li class="p-4 flex justify-between items-center hover:bg-slate-50 transition">
                                        <div>
                                            <div class="font-semibold text-slate-800 text-sm"><?= e($sales['nama'] ?? '-') ?></div>
                                            <div class="text-xs text-slate-500 mt-0.5"><?= e($sales['hp'] ?? '-') ?></div>
                                        </div>
                                        <form method="POST" onsubmit="return confirm('Hapus agen ini?')">
                                            <input type="hidden" name="action" value="hapus_sales">
                                            <input type="hidden" name="sales_id" value="<?= e($sales['id'] ?? '') ?>">
                                            <button type="submit" class="text-red-500 hover:bg-red-50 p-2 rounded-lg transition" title="Hapus">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- KANAN: FORM TRANSAKSI BARU -->
                <section class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 p-5 sm:p-6">
                    <div class="mb-5 border-b border-slate-100 pb-4">
                        <h2 class="text-lg font-bold text-slate-800">Buat Transaksi Baru</h2>
                        <p class="text-sm text-slate-500 mt-1">Pilih agen dan tambahkan produk yang diambil.</p>
                    </div>

                    <?php if (empty($salesList)): ?>
                        <div class="rounded-xl bg-yellow-50 border border-yellow-200 text-yellow-800 p-4 text-sm mb-5">
                            Belum ada agen. Silakan tambah agen terlebih dahulu di panel sebelah kiri.
                        </div>
                    <?php endif; ?>

                    <form method="POST" onsubmit="return validateTransactionForm()">
                        <input type="hidden" name="action" value="tambah_transaksi">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Tanggal</label>
                                <input type="date" name="tanggal" value="<?= e(date('Y-m-d')) ?>" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Nama Agen</label>
                                <select name="sales_id" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition appearance-none">
                                    <option value="">-- Pilih Agen --</option>
                                    <?php foreach ($salesList as $sales): ?>
                                        <option value="<?= e($sales['id'] ?? '') ?>"><?= e($sales['nama'] ?? '-') ?> (<?= e($sales['hp'] ?? '-') ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="relative mb-5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Cari & Tambah Produk</label>
                            <div class="relative">
                                <svg class="w-5 h-5 absolute left-3 top-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                <input type="text" id="itemSearch" placeholder="Ketik nama atau kategori produk..." autocomplete="off" class="w-full rounded-xl border border-slate-200 bg-white pl-10 pr-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition">
                            </div>
                            
                            <div id="itemSearchResult" class="hidden absolute z-30 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-lg max-h-64 overflow-y-auto"></div>
                        </div>

                        <div class="mb-5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Produk Terpilih</label>
                            <div id="selectedItemList" class="space-y-3">
                                <div class="rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-400 flex flex-col items-center">
                                    <svg class="w-8 h-8 mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                    Belum ada produk yang dimasukkan ke keranjang.
                                </div>
                            </div>
                        </div>

                        <div id="transactionSummary" class="hidden bg-slate-50 border border-slate-200 rounded-xl p-4 mb-5">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm text-slate-600">Total Qty:</span>
                                <strong id="summaryQty" class="text-slate-800">0 pcs</strong>
                            </div>
                            <div class="flex justify-between items-center pt-2 border-t border-slate-200">
                                <span class="text-sm font-semibold text-slate-700">Total Nilai:</span>
                                <strong id="summaryNilai" class="text-lg text-blue-600">Rp0</strong>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="w-full sm:w-auto rounded-xl bg-blue-600 text-white px-6 py-2.5 text-sm font-semibold hover:bg-blue-700 transition disabled:bg-slate-300 disabled:cursor-not-allowed shadow-sm" <?= empty($salesList) || empty($itemList) ? 'disabled' : '' ?>>
                                Proses Transaksi
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <!-- BOTTOM SECTION: RIWAYAT TRANSAKSI (Dengan Status Pelunasan & Setoran) -->
            <section class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-50/50">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">Riwayat Transaksi</h2>
                        <p class="text-sm text-slate-500 mt-0.5">Daftar historis pengambilan produk oleh agen beserta status pembukuannya.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left whitespace-nowrap">
                        <thead class="bg-white text-slate-500 border-b border-slate-200 text-xs uppercase tracking-wider">
                            <tr>
                                <th class="py-4 px-5 font-semibold">Tanggal & Agen</th>
                                <th class="py-4 px-5 font-semibold">Rincian Produk Terambil</th>
                                <th class="py-4 px-5 font-semibold text-right">Total Pcs</th>
                                <th class="py-4 px-5 font-semibold text-right">Nilai Transaksi</th>
                                <th class="py-4 px-5 font-semibold text-center">Status Setoran</th>
                                <th class="py-4 px-5 font-semibold text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($transactionsDesc)): ?>
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-slate-500">
                                        <svg class="w-12 h-12 mx-auto text-slate-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        Belum ada riwayat transaksi.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactionsDesc as $transaction): 
                                    $trxId = $transaction['id'] ?? 0;
                                    $setorData = $setoranMap[$trxId] ?? null;
                                ?>
                                    <tr class="hover:bg-slate-50/50 transition align-top">
                                        <!-- Kolom Info Agen -->
                                        <td class="py-4 px-5">
                                            <div class="font-medium text-slate-800 mb-1"><?= e(date('d M Y', strtotime($transaction['tanggal'] ?? ''))) ?></div>
                                            <div class="text-blue-600 font-semibold text-sm"><?= e($transaction['nama_sales'] ?? '-') ?></div>
                                            <div class="text-xs text-slate-500"><?= e($transaction['hp_sales'] ?? '-') ?></div>
                                        </td>

                                        <!-- Kolom Rincian Produk -->
                                        <td class="py-4 px-5 whitespace-normal min-w-[350px]">
                                            <div class="border border-slate-200 rounded-lg overflow-hidden">
                                                <table class="w-full text-xs">
                                                    <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
                                                        <tr>
                                                            <th class="px-3 py-2 text-left font-medium">Item</th>
                                                            <th class="px-3 py-2 text-right font-medium">Harga</th>
                                                            <th class="px-3 py-2 text-right font-medium">Qty</th>
                                                            <th class="px-3 py-2 text-right font-medium">Subtotal</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-100">
                                                        <?php foreach (($transaction['items'] ?? []) as $item): ?>
                                                            <tr class="hover:bg-white transition">
                                                                <td class="px-3 py-2">
                                                                    <div class="font-medium text-slate-700"><?= e($item['nama_produk'] ?? '-') ?></div>
                                                                    <div class="text-[10px] text-slate-400"><?= e($item['kategori'] ?? '-') ?></div>
                                                                </td>
                                                                <td class="px-3 py-2 text-right text-slate-600"><?= e(formatRupiah($item['harga_per_pcs'] ?? 0)) ?></td>
                                                                <td class="px-3 py-2 text-right font-medium text-slate-700"><?= e($item['jumlah'] ?? 0) ?></td>
                                                                <td class="px-3 py-2 text-right font-semibold text-slate-800"><?= e(formatRupiah($item['subtotal'] ?? 0)) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>

                                        <td class="py-4 px-5 text-right">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-800">
                                                <?= e($transaction['total_qty'] ?? 0) ?> pcs
                                            </span>
                                        </td>
                                        
                                        <td class="py-4 px-5 text-right font-bold text-slate-800">
                                            <?= e(formatRupiah($transaction['total_nilai'] ?? 0)) ?>
                                        </td>

                                        <!-- Status Setoran -->
                                        <td class="py-4 px-5 text-center">
                                            <?php if (!$setorData): ?>
                                                <span class="inline-flex rounded-full bg-amber-100 border border-amber-200 text-amber-700 px-3 py-1 text-xs font-bold uppercase tracking-wider">
                                                    Belum Disetor
                                                </span>
                                            <?php elseif (($setorData['kurang'] ?? 0) > 0): ?>
                                                <span class="inline-flex rounded-full bg-red-100 border border-red-200 text-red-700 px-3 py-1 text-xs font-bold uppercase tracking-wider" title="Sisa Hutang: <?= e(formatRupiah($setorData['kurang'])) ?>">
                                                    Ada Hutang
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex rounded-full bg-green-100 border border-green-200 text-green-700 px-3 py-1 text-xs font-bold uppercase tracking-wider">
                                                    Lunas
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Aksi -->
                                        <td class="py-4 px-5 text-center">
                                            <form method="POST" onsubmit="return confirm('Hapus transaksi ini?')">
                                                <input type="hidden" name="action" value="hapus_transaksi">
                                                <input type="hidden" name="transaction_id" value="<?= e($transaction['id'] ?? '') ?>">
                                                <button type="submit" class="text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-lg transition inline-flex" title="Hapus Transaksi">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
    </div>

    <!-- MODAL TAMBAH AGEN -->
    <div id="salesModal" class="hidden fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm p-4 items-center justify-center transition-opacity">
        <div class="bg-white w-full max-w-sm rounded-2xl shadow-xl overflow-hidden transform transition-all">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <h2 class="text-lg font-bold text-slate-800">Tambah Agen</h2>
                <button type="button" onclick="closeSalesModal()" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="tambah_sales">

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Nama Agen</label>
                    <input type="text" name="nama" required placeholder="Contoh: Andi" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Nomor HP</label>
                    <input type="text" name="hp" required placeholder="Contoh: 08123456789" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition">
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeSalesModal()" class="flex-1 rounded-xl bg-white border border-slate-200 text-slate-700 px-4 py-2.5 text-sm font-semibold hover:bg-slate-50 transition">Batal</button>
                    <button type="submit" class="flex-1 rounded-xl bg-blue-600 text-white px-4 py-2.5 text-sm font-semibold hover:bg-blue-700 transition shadow-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SCRIPT LOGIC -->
    <script>
        const itemList = <?= $itemListForJs ?: '[]' ?>;
        const selectedItems = [];

        const itemSearch = document.getElementById('itemSearch');
        const itemSearchResult = document.getElementById('itemSearchResult');
        const selectedItemList = document.getElementById('selectedItemList');
        const transactionSummary = document.getElementById('transactionSummary');
        const summaryQty = document.getElementById('summaryQty');
        const summaryNilai = document.getElementById('summaryNilai');

        function openSalesModal() {
            const modal = document.getElementById('salesModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeSalesModal() {
            const modal = document.getElementById('salesModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function formatRupiahJs(number) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(Number(number || 0));
        }

        function renderItemSearchResult(keyword = '') {
            const search = keyword.toLowerCase().trim();

            if (search.length === 0) {
                itemSearchResult.classList.add('hidden');
                itemSearchResult.innerHTML = '';
                return;
            }

            const filteredItems = itemList.filter((item) => {
                const nama = String(item.nama || '').toLowerCase();
                const kategori = String(item.kategori || '').toLowerCase();
                return nama.includes(search) || kategori.includes(search);
            });

            itemSearchResult.classList.remove('hidden');

            if (filteredItems.length === 0) {
                itemSearchResult.innerHTML = `
                    <div class="p-4 text-sm text-slate-500 text-center">Produk tidak ditemukan.</div>
                `;
                return;
            }

            itemSearchResult.innerHTML = filteredItems.map((item) => {
                const stok = Number(item.stok || 0);
                const harga = Number(item.harga_per_pcs || 0);
                const disabledClass = stok <= 0 ? 'opacity-50 cursor-not-allowed bg-slate-50' : 'hover:bg-slate-50 cursor-pointer';
                const disabledAttr = stok <= 0 ? 'disabled' : '';

                return `
                    <button type="button" onclick="addItem(${Number(item.id)})" ${disabledAttr} class="w-full text-left px-4 py-3 transition border-b border-slate-100 flex justify-between items-center last:border-0 ${disabledClass}">
                        <div>
                            <div class="font-semibold text-sm text-slate-800">${escapeHtml(item.nama || '-')}</div>
                            <div class="text-[11px] text-slate-500 mt-0.5">${escapeHtml(item.kategori || '-')}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-bold text-blue-600">${formatRupiahJs(harga)}</div>
                            <div class="text-[11px] font-medium ${stok > 0 ? 'text-green-600' : 'text-red-500'}">Stok: ${stok}</div>
                        </div>
                    </button>
                `;
            }).join('');
        }

        function addItem(itemId) {
            const item = itemList.find((data) => Number(data.id) === Number(itemId));
            if (!item) return;

            const stok = Number(item.stok || 0);
            const harga = Number(item.harga_per_pcs || 0);

            if (stok <= 0) {
                alert('Stok produk ini kosong.');
                return;
            }

            const existingItem = selectedItems.find((data) => Number(data.id) === Number(itemId));

            if (existingItem) {
                if (existingItem.jumlah + 1 > stok) {
                    alert('Jumlah melebihi stok gudang.');
                    return;
                }
                existingItem.jumlah += 1;
            } else {
                selectedItems.push({
                    id: item.id,
                    nama: item.nama || '-',
                    kategori: item.kategori || '-',
                    stok: stok,
                    harga_per_pcs: harga,
                    jumlah: 1
                });
            }

            itemSearch.value = '';
            itemSearchResult.classList.add('hidden');
            itemSearchResult.innerHTML = '';

            renderSelectedItems();
            updateSummary();
        }

        function updateJumlah(itemId, input) {
            const item = selectedItems.find((data) => Number(data.id) === Number(itemId));
            if (!item) return;

            let newJumlah = Number(input.value || 1);
            if (newJumlah < 1) newJumlah = 1;

            if (newJumlah > Number(item.stok || 0)) {
                alert('Jumlah tidak boleh melebihi stok gudang.');
                newJumlah = Number(item.stok || 1);
            }

            item.jumlah = newJumlah;
            input.value = newJumlah;

            const subtotalElement = document.getElementById(`subtotal-${itemId}`);
            if (subtotalElement) {
                subtotalElement.textContent = formatRupiahJs(Number(item.harga_per_pcs || 0) * newJumlah);
            }
            updateSummary();
        }

        function removeItem(itemId) {
            const index = selectedItems.findIndex((data) => Number(data.id) === Number(itemId));
            if (index !== -1) {
                selectedItems.splice(index, 1);
            }
            renderSelectedItems();
            updateSummary();
        }

        function renderSelectedItems() {
            if (selectedItems.length === 0) {
                selectedItemList.innerHTML = `
                    <div class="rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-400 flex flex-col items-center">
                        <svg class="w-8 h-8 mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        Belum ada produk yang dimasukkan ke keranjang.
                    </div>
                `;
                return;
            }

            selectedItemList.innerHTML = selectedItems.map((item) => {
                const subtotal = Number(item.harga_per_pcs || 0) * Number(item.jumlah || 0);

                return `
                    <div class="rounded-xl border border-slate-200 bg-white p-3 flex flex-col gap-3 relative shadow-sm">
                        <input type="hidden" name="item_id[]" value="${Number(item.id)}">
                        
                        <div class="flex justify-between items-start pr-8">
                            <div>
                                <div class="font-bold text-sm text-slate-800">${escapeHtml(item.nama)}</div>
                                <div class="text-[11px] text-slate-500 mt-0.5">Stok: ${Number(item.stok || 0)} | ${formatRupiahJs(item.harga_per_pcs)}/pcs</div>
                            </div>
                            <button type="button" onclick="removeItem(${Number(item.id)})" class="absolute top-3 right-3 text-red-400 hover:text-red-600 bg-red-50 hover:bg-red-100 p-1.5 rounded-md transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>

                        <div class="flex items-center justify-between border-t border-slate-100 pt-3">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-slate-600">Qty:</span>
                                <input type="number" name="jumlah[]" min="1" max="${Number(item.stok || 0)}" value="${Number(item.jumlah || 1)}" oninput="updateJumlah(${Number(item.id)}, this)" class="w-20 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-sm text-center outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] uppercase font-semibold text-slate-400 block leading-tight">Subtotal</span>
                                <span class="font-bold text-slate-800 text-sm" id="subtotal-${Number(item.id)}">${formatRupiahJs(subtotal)}</span>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function updateSummary() {
            if (selectedItems.length === 0) {
                transactionSummary.classList.add('hidden');
                summaryQty.textContent = '0 pcs';
                summaryNilai.textContent = 'Rp0';
                return;
            }

            const totalQty = selectedItems.reduce((total, item) => total + Number(item.jumlah || 0), 0);
            const totalNilai = selectedItems.reduce((total, item) => total + (Number(item.harga_per_pcs || 0) * Number(item.jumlah || 0)), 0);

            transactionSummary.classList.remove('hidden');
            summaryQty.textContent = `${totalQty} pcs`;
            summaryNilai.textContent = formatRupiahJs(totalNilai);
        }

        function validateTransactionForm() {
            if (selectedItems.length === 0) {
                alert('Pilih minimal 1 produk terlebih dahulu.');
                return false;
            }
            for (const item of selectedItems) {
                if (Number(item.jumlah || 0) <= 0) {
                    alert('Jumlah produk harus lebih dari 0.');
                    return false;
                }
                if (Number(item.jumlah || 0) > Number(item.stok || 0)) {
                    alert(`Jumlah ${item.nama} melebihi stok gudang.`);
                    return false;
                }
            }
            return true;
        }

        itemSearch.addEventListener('input', function () { renderItemSearchResult(this.value); });

        document.addEventListener('click', function (event) {
            if (!itemSearch.contains(event.target) && !itemSearchResult.contains(event.target)) {
                itemSearchResult.classList.add('hidden');
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeSalesModal();
                itemSearchResult.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
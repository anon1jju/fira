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
    if (empty($items)) return 1;
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
$transactionsList = readJsonFile($transactionsFile, []);
$setoranList = readJsonFile($setoranFile, []);

// Proses POST Simpan Setoran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah_setoran') {
        $transactionId = (int) ($_POST['transaction_id'] ?? 0);
        $tanggalSetor = $_POST['tanggal_setor'] ?? date('Y-m-d');
        $uangDisetor = (int) preg_replace('/\D/', '', $_POST['uang_disetor'] ?? '0');
        
        // Data kembalian (array form: dikembalikan[item_id] = jumlah)
        $dikembalikanData = $_POST['dikembalikan'] ?? [];

        $selectedTrx = null;
        foreach ($transactionsList as $trx) {
            if ((int) ($trx['id'] ?? 0) === $transactionId) {
                $selectedTrx = $trx;
                break;
            }
        }

        if ($selectedTrx) {
            $rincianSetoran = [];
            $totalExpected = 0;
            $totalTerjual = 0;

            foreach ($selectedTrx['items'] as $item) {
                $itemId = (int) $item['item_id'];
                $diambil = (int) $item['jumlah'];
                $harga = (int) $item['harga_per_pcs'];
                
                // Berapa yang dikembalikan agen?
                $kembali = isset($dikembalikanData[$itemId]) ? (int) $dikembalikanData[$itemId] : 0;
                
                // Cegah agen masukin kembali lebih dari yg diambil
                if ($kembali > $diambil) $kembali = $diambil; 

                $terjual = $diambil - $kembali;
                $subtotal = $terjual * $harga;

                $rincianSetoran[] = [
                    'item_id' => $itemId,
                    'nama_produk' => $item['nama_produk'],
                    'diambil' => $diambil,
                    'dikembalikan' => $kembali,
                    'terjual' => $terjual,
                    'harga_per_pcs' => $harga,
                    'subtotal' => $subtotal
                ];

                $totalExpected += $subtotal;
                $totalTerjual += $terjual;

                // Kembalikan barang yang belum laku ke stok gudang
                if ($kembali > 0) {
                    foreach ($itemList as &$masterItem) {
                        if ((int) $masterItem['id'] === $itemId) {
                            $masterItem['stok'] = (int) ($masterItem['stok'] ?? 0) + $kembali;
                            break;
                        }
                    }
                    unset($masterItem);
                }
            }

            // Hitung Kurang (Hutang)
            $kurang = $totalExpected - $uangDisetor;
            if ($kurang < 0) $kurang = 0; // Jika bayar lebih, anggap pas/lunas

            $setoranList[] = [
                'id' => nextId($setoranList),
                'transaction_id' => $transactionId,
                'sales_id' => $selectedTrx['sales_id'],
                'nama_sales' => $selectedTrx['nama_sales'],
                'tanggal_setor' => $tanggalSetor,
                'items' => $rincianSetoran,
                'total_terjual' => $totalTerjual,
                'total_expected' => $totalExpected,
                'uang_disetor' => $uangDisetor,
                'kurang' => $kurang,
                'status' => $kurang > 0 ? 'hutang' : 'lunas',
                'created_at' => date('Y-m-d H:i:s')
            ];

            writeJsonFile($setoranFile, $setoranList);
            writeJsonFile($itemFile, $itemList); // Update stok gudang

            header('Location: setoran.php?success=1');
            exit;
        }

        header('Location: setoran.php?error=invalid');
        exit;
    }

    if ($action === 'hapus_setoran') {
        header('Location: setoran.php?error=no_delete');
        exit;
    }
    
    // Aksi untuk melunasi hutang agen
    if ($action === 'bayar_hutang') {
        $setoranId = (int) ($_POST['setoran_id'] ?? 0);
        $bayarTambahan = (int) preg_replace('/\D/', '', $_POST['bayar_tambahan'] ?? '0');

        foreach ($setoranList as &$setor) {
            if ((int) ($setor['id'] ?? 0) === $setoranId) {
                // Tambahkan uang yang dibayar ke total uang disetor sebelumnya
                $setor['uang_disetor'] += $bayarTambahan;
                
                // Hitung ulang sisa kurang (hutang)
                $sisaKurang = $setor['total_expected'] - $setor['uang_disetor'];
                if ($sisaKurang < 0) $sisaKurang = 0;

                $setor['kurang'] = $sisaKurang;
                $setor['status'] = $sisaKurang > 0 ? 'hutang' : 'lunas';
                
                // Simpan riwayat pembayaran cicilan jika diperlukan
                if (!isset($setor['riwayat_cicilan'])) {
                    $setor['riwayat_cicilan'] = [];
                }
                $setor['riwayat_cicilan'][] = [
                    'tanggal' => date('Y-m-d H:i:s'),
                    'jumlah' => $bayarTambahan
                ];
                
                break;
            }
        }
        unset($setor);

        writeJsonFile($setoranFile, $setoranList);
        header('Location: setoran.php?success=cicil');
        exit;
    }
}

// Filter Transaksi: Cari transaksi yang BELUM pernah disetor
$settledTransactionIds = array_map(function($s) { return (int) $s['transaction_id']; }, $setoranList);
$activeTransactions = array_filter($transactionsList, function($t) use ($settledTransactionIds) {
    return !in_array((int) $t['id'], $settledTransactionIds);
});

// Statistik Setoran
$totalUangMasuk = array_sum(array_column($setoranList, 'uang_disetor'));
$totalPiutang = array_sum(array_column($setoranList, 'kurang'));
$totalBarangLaku = array_sum(array_column($setoranList, 'total_terjual'));

// Data setoran untuk tabel riwayat
$setoranDesc = array_reverse($setoranList);

// Encode transaksi aktif untuk JavaScript Form
$activeTransactionsJs = json_encode(array_values($activeTransactions), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setoran Agen - Fira Admin</title>
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
            
            <?php if (isset($_GET['success'])): ?>
                <div class="mb-6 rounded-xl bg-green-50 border border-green-200 text-green-700 px-5 py-3.5 text-sm font-medium flex items-center gap-2 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Setoran berhasil dicatat. Barang retur telah ditambahkan kembali ke stok gudang.
                </div>
            <?php endif; ?>

            <header class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-8">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight">Setoran & Piutang Agen</h1>
                    <p class="text-slate-500 mt-1.5 text-sm sm:text-base">
                        Catat barang laku, kembalikan sisa retur ke gudang, dan pantau kekurangan setoran (kasbon).
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full lg:w-auto">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:px-6 flex flex-col justify-center">
                        <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider mb-1">Barang Laku</div>
                        <div class="text-xl font-bold text-slate-800"><?= e($totalBarangLaku) ?> <span class="text-xs font-medium text-slate-500">pcs</span></div>
                    </div>
                    <div class="bg-blue-600 rounded-2xl shadow-md p-4 sm:px-6 flex flex-col justify-center text-white">
                        <div class="text-blue-200 text-xs font-semibold uppercase tracking-wider mb-1">Total Kas Masuk</div>
                        <div class="text-xl font-bold"><?= e(formatRupiah($totalUangMasuk)) ?></div>
                    </div>
                    <div class="bg-red-50 rounded-2xl shadow-sm border border-red-100 p-4 sm:px-6 flex flex-col justify-center">
                        <div class="text-red-400 text-xs font-semibold uppercase tracking-wider mb-1">Total Kekurangan (Piutang)</div>
                        <div class="text-xl font-bold text-red-600"><?= e(formatRupiah($totalPiutang)) ?></div>
                    </div>
                </div>
            </header>

            <!-- TATA LETAK UTAMA: STACK KE BAWAH (Form di Atas, Buku Riwayat Full Width di Bawah) -->
            <div class="flex flex-col gap-8 mb-8">
                
                <!-- 1. FORM SETORAN (Di Atas, Rapi & Terstruktur) -->
                <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 sm:p-6">
                    <div class="mb-5 border-b border-slate-100 pb-4">
                        <h2 class="text-lg font-bold text-slate-800">Form Input Setoran Baru</h2>
                        <p class="text-sm text-slate-500 mt-1">Pilih transaksi gantung agen untuk memproses setoran dan pengembalian barang.</p>
                    </div>

                    <?php if (empty($activeTransactions)): ?>
                        <div class="rounded-xl bg-blue-50 border border-blue-200 text-blue-700 p-4 text-sm text-center">
                            Semua transaksi agen telah lunas/disetor. Tidak ada transaksi gantung.
                        </div>
                    <?php else: ?>
                        <form method="POST" id="formSetoran" onsubmit="return validateForm()">
                            <input type="hidden" name="action" value="tambah_setoran">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Tanggal Setor</label>
                                    <input type="date" name="tanggal_setor" value="<?= e(date('Y-m-d')) ?>" required 
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition">
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Pilih Transaksi Gantung</label>
                                    <select name="transaction_id" id="trxSelect" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition">
                                        <option value="">-- Pilih Transaksi --</option>
                                        <?php foreach ($activeTransactions as $trx): ?>
                                            <option value="<?= e($trx['id']) ?>">
                                                <?= e(date('d/m/Y', strtotime($trx['tanggal']))) ?> - <?= e($trx['nama_sales']) ?> (Total Ambil: <?= e(formatRupiah($trx['total_nilai'])) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Area Rincian Item (Muncul via JS dalam Grid agar Hemat Tempat & Rapi) -->
                            <div id="itemArea" class="hidden mb-5">
                                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Rincian Barang Belum Laku (Retur Gudang)</label>
                                <div id="itemList" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4"></div>
                            </div>

                            <!-- Ringkasan Kalkulasi & Submit -->
                            <div id="summaryArea" class="hidden rounded-xl border-2 border-slate-200 bg-slate-50 p-5 mb-5 max-w-xl">
                                <div class="flex justify-between items-center mb-3">
                                    <span class="text-sm font-semibold text-slate-600">Total Wajib Setor:</span>
                                    <strong id="labelWajibSetor" class="text-slate-800 text-lg">Rp0</strong>
                                </div>
                                <div class="border-t border-slate-200 pt-3 mb-3">
                                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Nominal Uang Disetor oleh Agen</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2.5 text-slate-500 font-semibold text-sm">Rp</span>
                                        <input type="number" name="uang_disetor" id="inputUangDisetor" required min="0" placeholder="0" 
                                            class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-4 py-2.5 text-sm font-bold text-blue-600 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition">
                                    </div>
                                </div>
                                <div class="flex justify-between items-center pt-3 border-t border-slate-200">
                                    <span class="text-sm text-slate-600 font-semibold">Status Pembukuan:</span>
                                    <div class="text-right">
                                        <strong id="labelKurang" class="text-base text-red-500 block">Rp0</strong>
                                        <span id="labelStatus" class="text-[10px] font-bold uppercase tracking-wider text-green-600 bg-green-100 px-2 py-0.5 rounded">LUNAS</span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <button type="submit" id="btnSubmit" disabled class="w-full sm:w-auto rounded-xl bg-blue-600 text-white px-8 py-3 text-sm font-bold hover:bg-blue-700 transition disabled:bg-slate-300 disabled:cursor-not-allowed shadow-sm">
                                    Proses Pembukuan Setoran & Retur Stok
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>

                <!-- 2. BUKU RIWAYAT SETORAN (Full Width di Bawah, Jauh Lebih Luas & Nyaman) -->
                <section class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-5 sm:p-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800">Buku Riwayat Setoran</h2>
                            <p class="text-sm text-slate-500 mt-0.5">Catatan seluruh riwayat setoran, rincian barang laku & retur, serta status piutang agen.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-white text-slate-500 border-b border-slate-200 text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="py-4 px-5 font-semibold w-56">Agen & Tanggal</th>
                                    <th class="py-4 px-5 font-semibold">Rincian Barang (Laku & Retur)</th>
                                    <th class="py-4 px-5 font-semibold text-right w-64">Pembayaran & Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($setoranDesc)): ?>
                                    <tr>
                                        <td colspan="3" class="py-12 text-center text-slate-500">
                                            <svg class="w-12 h-12 mx-auto text-slate-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            Belum ada data riwayat setoran.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($setoranDesc as $setor): ?>
                                        <tr class="hover:bg-slate-50/50 transition align-top">
                                            <!-- Kolom Info Agen -->
                                            <td class="py-4 px-5">
                                                <div class="font-bold text-slate-800 text-base mb-1"><?= e($setor['nama_sales']) ?></div>
                                                <div class="text-xs font-medium text-slate-500 mb-2">
                                                    Setor: <?= e(date('d M Y', strtotime($setor['tanggal_setor']))) ?>
                                                </div>
                                                <?php if ($setor['status'] === 'hutang'): ?>
                                                    <span class="inline-flex rounded bg-red-100 border border-red-200 text-red-700 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider">Ada Hutang</span>
                                                <?php else: ?>
                                                    <span class="inline-flex rounded bg-green-100 border border-green-200 text-green-700 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider">Lunas</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Kolom Rincian Nested Tabel (Sangat Luas & Lega) -->
                                            <td class="py-4 px-5">
                                                <div class="border border-slate-200 rounded-xl overflow-hidden bg-white shadow-sm">
                                                    <table class="w-full text-xs whitespace-nowrap">
                                                        <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
                                                            <tr>
                                                                <th class="px-3 py-2 text-left font-medium">Nama Produk</th>
                                                                <th class="px-3 py-2 text-center font-medium">Bawa</th>
                                                                <th class="px-3 py-2 text-center font-medium text-amber-600">Retur (Sisa)</th>
                                                                <th class="px-3 py-2 text-center font-medium text-green-600">Terjual (Laku)</th>
                                                                <th class="px-3 py-2 text-right font-medium">Harga / Pcs</th>
                                                                <th class="px-3 py-2 text-right font-medium">Subtotal</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-slate-100">
                                                            <?php foreach ($setor['items'] as $item): ?>
                                                                <tr class="hover:bg-slate-50/40">
                                                                    <td class="px-3 py-2 font-medium text-slate-800"><?= e($item['nama_produk']) ?></td>
                                                                    <td class="px-3 py-2 text-center text-slate-600"><?= e($item['diambil']) ?></td>
                                                                    <td class="px-3 py-2 text-center text-amber-600 font-bold"><?= e($item['dikembalikan']) ?></td>
                                                                    <td class="px-3 py-2 text-center text-green-600 font-bold"><?= e($item['terjual']) ?></td>
                                                                    <td class="px-3 py-2 text-right text-slate-600"><?= e(formatRupiah($item['harga_per_pcs'])) ?></td>
                                                                    <td class="px-3 py-2 text-right text-slate-800 font-bold"><?= e(formatRupiah($item['subtotal'])) ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>

                                            <!-- Kolom Pembayaran -->
                                            <td class="py-4 px-5 text-right">
                                                <div class="flex justify-between items-center text-xs text-slate-500 mb-1.5">
                                                    <span>Tagihan Wajib:</span>
                                                    <span class="font-semibold text-slate-700"><?= e(formatRupiah($setor['total_expected'])) ?></span>
                                                </div>
                                                <div class="flex justify-between items-center text-xs text-slate-500 mb-2 border-b border-slate-100 pb-2">
                                                    <span>Uang Disetor:</span>
                                                    <span class="font-bold text-blue-600"><?= e(formatRupiah($setor['uang_disetor'])) ?></span>
                                                </div>
                                                
                                                <?php if (($setor['kurang'] ?? 0) > 0): ?>
                                                    <div class="flex justify-between items-center text-sm mb-3">
                                                        <span class="font-bold text-red-500">Kekurangan:</span>
                                                        <span class="font-extrabold text-red-600 text-base"><?= e(formatRupiah($setor['kurang'])) ?></span>
                                                    </div>
                                                    <button type="button" onclick="openBayarModal(<?= $setor['id'] ?>, '<?= $setor['nama_sales'] ?>', <?= $setor['kurang'] ?>)" 
                                                        class="w-full bg-red-600 hover:bg-red-700 text-white text-xs font-bold py-2 px-3 rounded-xl transition shadow-sm">
                                                        Bayar Hutang / Cicil
                                                    </button>
                                                <?php else: ?>
                                                    <div class="flex justify-between items-center text-sm pt-1">
                                                        <span class="font-semibold text-slate-500">Status:</span>
                                                        <span class="font-bold text-green-600 text-sm">LUNAS</span>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <!-- MODAL BAYAR HUTANG -->
    <div id="bayarModal" class="hidden fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm p-4 items-center justify-center transition-opacity">
        <div class="bg-white w-full max-w-sm rounded-2xl shadow-xl overflow-hidden p-6">
            <div class="flex justify-between items-center mb-4 border-b border-slate-100 pb-3">
                <h3 class="font-bold text-slate-800 text-base">Pelunasan / Cicilan Piutang</h3>
                <button type="button" onclick="closeBayarModal()" class="text-slate-400 hover:text-slate-600 font-bold">✕</button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="bayar_hutang">
                <input type="hidden" name="setoran_id" id="modalSetoranId">

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Nama Agen</label>
                    <input type="text" id="modalNamaSales" disabled class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-600 font-semibold">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Sisa Hutang Saat Ini</label>
                    <input type="text" id="modalSisaHutang" disabled class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-red-600 font-bold">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Nominal Pembayaran</label>
                    <input type="number" name="bayar_tambahan" id="modalNominalBayar" required min="1" placeholder="Masukkan jumlah uang..." 
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold text-blue-600 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="button" onclick="closeBayarModal()" class="flex-1 bg-white border border-slate-200 text-slate-700 py-2 rounded-xl text-sm font-semibold hover:bg-slate-50">Batal</button>
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-xl text-sm font-semibold hover:bg-blue-700 shadow-sm">Simpan Pembayaran</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script Kalkulasi Dinamis Form -->
    <script>
        const activeTransactions = <?= $activeTransactionsJs ?>;
        
        const trxSelect = document.getElementById('trxSelect');
        const itemArea = document.getElementById('itemArea');
        const itemList = document.getElementById('itemList');
        const summaryArea = document.getElementById('summaryArea');
        const inputUangDisetor = document.getElementById('inputUangDisetor');
        const labelWajibSetor = document.getElementById('labelWajibSetor');
        const labelKurang = document.getElementById('labelKurang');
        const labelStatus = document.getElementById('labelStatus');
        const btnSubmit = document.getElementById('btnSubmit');

        let currentTrx = null;
        let expectedTotal = 0;

        function formatRupiahJs(number) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
        }
        
        function openBayarModal(setoranId, namaSales, sisaKurang) {
            document.getElementById('modalSetoranId').value = setoranId;
            document.getElementById('modalNamaSales').value = namaSales;
            document.getElementById('modalSisaHutang').value = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(sisaKurang);
            document.getElementById('modalNominalBayar').value = sisaKurang;
            
            const modal = document.getElementById('bayarModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    
        function closeBayarModal() {
            const modal = document.getElementById('bayarModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function renderItems() {
            if (!currentTrx) return;
            
            itemList.innerHTML = currentTrx.items.map(item => {
                return `
                    <div class="rounded-xl border border-slate-200 bg-white p-4 flex flex-col gap-3 shadow-sm">
                        <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                            <div class="font-bold text-sm text-slate-800">${item.nama_produk}</div>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-600">Bawa: ${item.jumlah} pcs</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 items-center">
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-amber-600 mb-1">Retur (Belum Laku)</label>
                                <input type="number" name="dikembalikan[${item.item_id}]" min="0" max="${item.jumlah}" value="0" 
                                    oninput="calculateTotal()" data-id="${item.item_id}" data-harga="${item.harga_per_pcs}" data-diambil="${item.jumlah}"
                                    class="input-retur w-full rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition">
                            </div>
                            <div class="text-right">
                                <span class="block text-[10px] uppercase font-bold text-green-600 mb-1">Terjual (Laku)</span>
                                <span id="laku-${item.item_id}" class="text-base font-bold text-slate-800">${item.jumlah} pcs</span>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function calculateTotal() {
            if (!currentTrx) return;
            
            expectedTotal = 0;
            const returInputs = document.querySelectorAll('.input-retur');
            
            returInputs.forEach(input => {
                let retur = parseInt(input.value) || 0;
                let diambil = parseInt(input.getAttribute('data-diambil'));
                let harga = parseInt(input.getAttribute('data-harga'));
                let itemId = input.getAttribute('data-id');

                if (retur > diambil) {
                    retur = diambil;
                    input.value = retur;
                }
                if (retur < 0) {
                    retur = 0;
                    input.value = retur;
                }

                let terjual = diambil - retur;
                document.getElementById(`laku-${itemId}`).textContent = `${terjual} pcs`;
                
                expectedTotal += (terjual * harga);
            });

            labelWajibSetor.textContent = formatRupiahJs(expectedTotal);
            calculateKurang();
        }

        function calculateKurang() {
            let uangDisetor = parseInt(inputUangDisetor.value) || 0;
            let kurang = expectedTotal - uangDisetor;
            
            if (kurang <= 0) {
                labelKurang.textContent = "Rp0 (Lunas)";
                labelKurang.className = "text-base text-green-600 font-bold block";
                labelStatus.textContent = "LUNAS";
                labelStatus.className = "text-[10px] font-bold uppercase tracking-wider text-green-600 bg-green-100 px-2 py-0.5 rounded";
            } else {
                labelKurang.textContent = formatRupiahJs(kurang);
                labelKurang.className = "text-base text-red-600 font-bold block";
                labelStatus.textContent = "HUTANG / KASBON";
                labelStatus.className = "text-[10px] font-bold uppercase tracking-wider text-red-600 bg-red-100 px-2 py-0.5 rounded";
            }
        }

        trxSelect.addEventListener('change', function() {
            const trxId = parseInt(this.value);
            currentTrx = activeTransactions.find(t => t.id === trxId);
            
            if (currentTrx) {
                itemArea.classList.remove('hidden');
                summaryArea.classList.remove('hidden');
                btnSubmit.removeAttribute('disabled');
                renderItems();
                calculateTotal();
            } else {
                itemArea.classList.add('hidden');
                summaryArea.classList.add('hidden');
                btnSubmit.setAttribute('disabled', 'disabled');
            }
        });

        if (inputUangDisetor) {
            inputUangDisetor.addEventListener('input', calculateKurang);
        }
    </script>
</body>
</html>
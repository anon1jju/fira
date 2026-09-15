<?php
$dataDir = __DIR__ . '/data';
$itemFile = $dataDir . '/item.json';

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

$defaultItems = [
    [
        'id' => 1,
        'nama' => 'Beras Premium',
        'kategori' => 'Sembako',
        'stok' => 120,
        'harga_per_pcs' => 75000,
    ],
    [
        'id' => 2,
        'nama' => 'Minyak Goreng',
        'kategori' => 'Sembako',
        'stok' => 80,
        'harga_per_pcs' => 20000,
    ],
    [
        'id' => 3,
        'nama' => 'Gula Pasir',
        'kategori' => 'Sembako',
        'stok' => 95,
        'harga_per_pcs' => 18000,
    ],
];

$itemList = readJsonFile($itemFile, $defaultItems);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'simpan_produk') {
        $id = $_POST['id'] ?? '';
        $nama = trim($_POST['nama'] ?? '');
        $kategori = trim($_POST['kategori'] ?? '');
        $stok = (int) ($_POST['stok'] ?? 0);
        $hargaPerPcs = (int) ($_POST['harga_per_pcs'] ?? 0);

        if ($nama !== '' && $kategori !== '') {
            if ($id !== '') {
                foreach ($itemList as &$item) {
                    if ((int) ($item['id'] ?? 0) === (int) $id) {
                        $item['nama'] = $nama;
                        $item['kategori'] = $kategori;
                        $item['stok'] = max(0, $stok);
                        $item['harga_per_pcs'] = max(0, $hargaPerPcs);
                        break;
                    }
                }
                unset($item);
            } else {
                $itemList[] = [
                    'id' => nextId($itemList),
                    'nama' => $nama,
                    'kategori' => $kategori,
                    'stok' => max(0, $stok),
                    'harga_per_pcs' => max(0, $hargaPerPcs),
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }

            writeJsonFile($itemFile, $itemList);

            header('Location: produk.php?success=simpan');
            exit;
        }

        header('Location: produk.php?error=invalid');
        exit;
    }

    if ($action === 'hapus_produk') {
        $id = (int) ($_POST['id'] ?? 0);

        $itemList = array_values(array_filter($itemList, function ($item) use ($id) {
            return (int) ($item['id'] ?? 0) !== $id;
        }));

        writeJsonFile($itemFile, $itemList);

        header('Location: produk.php?success=hapus');
        exit;
    }
}

$editData = null;

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];

    foreach ($itemList as $item) {
        if ((int) ($item['id'] ?? 0) === $editId) {
            $editData = $item;
            break;
        }
    }
}

$totalProduk = count($itemList);
$totalStok = array_sum(array_map(function ($item) {
    return (int) ($item['stok'] ?? 0);
}, $itemList));

$totalNilaiStok = array_sum(array_map(function ($item) {
    return (int) ($item['stok'] ?? 0) * (int) ($item['harga_per_pcs'] ?? 0);
}, $itemList));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Produk - Fira</title>
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
            
            <!-- ALERTS -->
            <?php if (isset($_GET['success']) && $_GET['success'] === 'simpan'): ?>
                <div class="mb-6 rounded-xl bg-green-50 border border-green-200 text-green-700 px-5 py-3.5 text-sm font-medium flex items-center gap-2 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Produk berhasil disimpan ke sistem.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && $_GET['success'] === 'hapus'): ?>
                <div class="mb-6 rounded-xl bg-slate-100 border border-slate-200 text-slate-700 px-5 py-3.5 text-sm font-medium flex items-center gap-2 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Produk berhasil dihapus dari sistem.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'invalid'): ?>
                <div class="mb-6 rounded-xl bg-red-50 border border-red-200 text-red-700 px-5 py-3.5 text-sm font-medium flex items-center gap-2 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    Data produk tidak valid. Pastikan nama dan kategori produk sudah diisi dengan benar.
                </div>
            <?php endif; ?>

            <!-- HEADER & STATS -->
            <header class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-8">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight">Manajemen Produk</h1>
                    <p class="text-slate-500 mt-1.5 text-sm sm:text-base">
                        Kelola master data barang, pantau stok gudang, dan atur harga per item.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full lg:w-auto">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:px-6 flex flex-col justify-center">
                        <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider mb-1">Total Jenis</div>
                        <div class="text-2xl font-bold text-slate-800"><?= e($totalProduk) ?></div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:px-6 flex flex-col justify-center">
                        <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider mb-1">Total Stok Gudang</div>
                        <div class="text-2xl font-bold text-slate-800"><?= e($totalStok) ?> <span class="text-sm font-medium text-slate-500">pcs</span></div>
                    </div>
                    <div class="bg-blue-600 rounded-2xl shadow-md p-4 sm:px-6 flex flex-col justify-center text-white">
                        <div class="text-blue-200 text-xs font-semibold uppercase tracking-wider mb-1">Estimasi Nilai Aset</div>
                        <div class="text-2xl font-bold"><?= e(formatRupiah($totalNilaiStok)) ?></div>
                    </div>
                </div>
            </header>

            <!-- MAIN GRID CONTENT -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- KIRI: FORM TAMBAH/EDIT (Sticky) -->
                <div class="lg:col-span-1">
                    <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 sm:p-6 sticky top-6">
                        <div class="mb-5 border-b border-slate-100 pb-4">
                            <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                                <?php if ($editData): ?>
                                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    Edit Data Produk
                                <?php else: ?>
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Tambah Produk Baru
                                <?php endif; ?>
                            </h2>
                            <p class="text-sm text-slate-500 mt-1">Lengkapi form di bawah untuk memperbarui katalog.</p>
                        </div>

                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="simpan_produk">
                            <input type="hidden" name="id" value="<?= e($editData['id'] ?? '') ?>">

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Nama Produk</label>
                                <input type="text" name="nama" required value="<?= e($editData['nama'] ?? '') ?>" placeholder="Contoh: Beras Premium 5Kg" 
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:bg-white transition">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Kategori</label>
                                <input type="text" name="kategori" value="<?= e($editData['kategori'] ?? '') ?>" placeholder="Contoh: Sembako" 
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:bg-white transition">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Stok (Pcs)</label>
                                    <input type="number" name="stok" min="0" required value="<?= e($editData['stok'] ?? '') ?>" placeholder="0" 
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:bg-white transition">
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Harga / Pcs</label>
                                    <input type="number" name="harga_per_pcs" min="0" required value="<?= e($editData['harga_per_pcs'] ?? '') ?>" placeholder="0" 
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:bg-white transition">
                                </div>
                            </div>

                            <div class="flex gap-3 pt-4 border-t border-slate-100">
                                <?php if ($editData): ?>
                                    <a href="produk.php" class="flex-1 text-center rounded-xl bg-white border border-slate-200 text-slate-700 px-4 py-2.5 text-sm font-semibold hover:bg-slate-50 transition shadow-sm">
                                        Batal
                                    </a>
                                <?php endif; ?>
                                <button type="submit" class="flex-1 rounded-xl <?= $editData ? 'bg-amber-500 hover:bg-amber-600' : 'bg-blue-600 hover:bg-blue-700' ?> text-white px-4 py-2.5 text-sm font-semibold transition shadow-sm">
                                    <?= $editData ? 'Simpan Perubahan' : 'Simpan Produk' ?>
                                </button>
                            </div>
                        </form>
                    </section>
                </div>

                <!-- KANAN: TABEL DAFTAR PRODUK -->
                <div class="lg:col-span-2">
                    <section class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col h-full">
                        <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50/50">
                            <div>
                                <h2 class="text-lg font-bold text-slate-800">Daftar Produk</h2>
                                <p class="text-sm text-slate-500 mt-0.5">Seluruh master barang yang terdaftar di gudang.</p>
                            </div>
                        </div>

                        <div class="overflow-x-auto flex-1">
                            <table class="w-full text-sm text-left whitespace-nowrap">
                                <thead class="bg-white text-slate-500 border-b border-slate-200 text-xs uppercase tracking-wider">
                                    <tr>
                                        <th class="py-4 px-5 font-semibold w-16">No</th>
                                        <th class="py-4 px-5 font-semibold">Produk & Kategori</th>
                                        <th class="py-4 px-5 font-semibold text-center">Stok Gudang</th>
                                        <th class="py-4 px-5 font-semibold text-right">Harga Satuan</th>
                                        <th class="py-4 px-5 font-semibold text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php if (empty($itemList)): ?>
                                        <tr>
                                            <td colspan="5" class="py-12 text-center text-slate-500">
                                                <svg class="w-12 h-12 mx-auto text-slate-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                                Belum ada data produk yang didaftarkan.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($itemList as $index => $item): ?>
                                            <?php
                                                $stok = (int) ($item['stok'] ?? 0);
                                                $harga = (int) ($item['harga_per_pcs'] ?? 0);
                                                $nilaiStok = $stok * $harga;
                                                
                                                // Logika warna badge stok
                                                $stockBadgeClass = 'bg-blue-50 text-blue-700 border-blue-100'; // Default (ada stok)
                                                if ($stok === 0) {
                                                    $stockBadgeClass = 'bg-red-50 text-red-600 border-red-100'; // Habis
                                                } elseif ($stok <= 10) {
                                                    $stockBadgeClass = 'bg-amber-50 text-amber-700 border-amber-100'; // Menipis
                                                }
                                            ?>
                                            <tr class="hover:bg-slate-50/70 transition-colors">
                                                <td class="py-4 px-5 text-slate-500 font-medium"><?= e($index + 1) ?></td>

                                                <td class="py-4 px-5">
                                                    <div class="font-bold text-slate-800"><?= e($item['nama'] ?? '-') ?></div>
                                                    <div class="text-xs text-slate-500 mt-0.5"><?= e($item['kategori'] ?? '-') ?></div>
                                                </td>

                                                <td class="py-4 px-5 text-center">
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold border <?= $stockBadgeClass ?>">
                                                        <?= e($stok) ?> pcs
                                                    </span>
                                                </td>

                                                <td class="py-4 px-5 text-right">
                                                    <div class="font-semibold text-slate-800"><?= e(formatRupiah($harga)) ?></div>
                                                    <div class="text-[10px] text-slate-400 mt-0.5 uppercase">Aset: <?= e(formatRupiah($nilaiStok)) ?></div>
                                                </td>

                                                <td class="py-4 px-5">
                                                    <div class="flex items-center justify-center gap-2">
                                                        <!-- Tombol Edit Icon -->
                                                        <a href="produk.php?edit=<?= e($item['id'] ?? '') ?>" title="Edit Produk"
                                                           class="p-2 text-amber-500 bg-amber-50 hover:bg-amber-100 rounded-lg transition-colors">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                        </a>

                                                        <!-- Tombol Hapus Icon -->
                                                        <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus produk ini?')" class="inline-block">
                                                            <input type="hidden" name="action" value="hapus_produk">
                                                            <input type="hidden" name="id" value="<?= e($item['id'] ?? '') ?>">
                                                            <button type="submit" title="Hapus Produk"
                                                                    class="p-2 text-red-500 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
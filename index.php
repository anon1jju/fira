<?php

declare(strict_types=1);

require __DIR__ . '/app/storage.php';

session_start();

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money(mixed $value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function num(mixed $value): string
{
    return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
}

function now_id(): string
{
    return date('Y-m-d H:i:s');
}

function next_id(array $rows): int
{
    $max = 0;
    foreach ($rows as $row) {
        $max = max($max, (int) ($row['id'] ?? 0));
    }
    return $max + 1;
}

function redirect_to(string $page, array $params = []): never
{
    $params = array_merge(['page' => $page], $params);
    header('Location: ?' . http_build_query($params));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function post_string(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function post_float(string $key): float
{
    $value = str_replace(',', '.', post_string($key));
    return is_numeric($value) ? (float) $value : -1;
}

function find_index_by_id(array $rows, int $id): int
{
    foreach ($rows as $index => $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            return $index;
        }
    }
    return -1;
}

function item_name(array $items, int $id): string
{
    $index = find_index_by_id($items, $id);
    return $index >= 0 ? (string) $items[$index]['name'] : 'Barang dihapus';
}

function sales_name(array $sales, int $id): string
{
    $index = find_index_by_id($sales, $id);
    return $index >= 0 ? (string) $sales[$index]['name'] : 'Sales dihapus';
}

function transaction_totals(array $transaction): array
{
    $taken = 0.0;
    $sold = 0.0;
    $expected = 0.0;
    foreach ($transaction['items'] ?? [] as $item) {
        $taken += (float) ($item['qty_taken'] ?? 0);
        $soldQty = isset($item['qty_sold']) ? (float) $item['qty_sold'] : 0.0;
        $sold += $soldQty;
        $expected += $soldQty * (float) ($item['price'] ?? 0);
    }

    return [
        'taken' => $taken,
        'sold' => $sold,
        'expected' => $expected,
        'paid' => (float) ($transaction['paid_amount'] ?? 0),
        'difference' => (float) ($transaction['difference'] ?? 0),
    ];
}

function has_transaction_item(array $transactions, int $itemId): bool
{
    foreach ($transactions as $transaction) {
        foreach ($transaction['items'] ?? [] as $item) {
            if ((int) ($item['item_id'] ?? 0) === $itemId) {
                return true;
            }
        }
    }
    return false;
}

function has_transaction_sales(array $transactions, int $salesId): bool
{
    foreach ($transactions as $transaction) {
        if ((int) ($transaction['sales_id'] ?? 0) === $salesId) {
            return true;
        }
    }
    return false;
}

function agent_daily_summaries(array $transactions, array $sales): array
{
    $summaries = [];
    foreach ($transactions as $transaction) {
        $salesId = (int) ($transaction['sales_id'] ?? 0);
        $date = substr((string) ($transaction['date'] ?? ''), 0, 10) ?: '-';
        $key = $date . ':' . $salesId;
        if (!isset($summaries[$key])) {
            $summaries[$key] = [
                'date' => $date,
                'sales_id' => $salesId,
                'sales_name' => sales_name($sales, $salesId),
                'qty_taken' => 0.0,
                'qty_returned' => 0.0,
                'paid_amount' => 0.0,
                'transactions' => 0,
            ];
        }

        $summaries[$key]['transactions']++;
        $summaries[$key]['paid_amount'] += (float) ($transaction['paid_amount'] ?? 0);
        foreach ($transaction['items'] ?? [] as $item) {
            $summaries[$key]['qty_taken'] += (float) ($item['qty_taken'] ?? 0);
            $summaries[$key]['qty_returned'] += (float) ($item['qty_returned'] ?? 0);
        }
    }

    usort($summaries, fn ($a, $b) => strcmp($b['date'], $a['date']) ?: strcmp($a['sales_name'], $b['sales_name']));
    return $summaries;
}

$store = load_store();
$page = $_GET['page'] ?? 'dashboard';
$pageAliases = ['items' => 'products', 'sales' => 'agents'];
$page = $pageAliases[$page] ?? $page;
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'save_item') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = post_string('name');
            $sku = post_string('sku');
            $unit = post_string('unit') ?: 'pcs';
            $type = post_string('type') ?: '-';
            $price = post_float('price');
            $initialStock = post_float('stock');

            if ($name === '' || $price < 0 || ($id === 0 && $initialStock < 0)) {
                flash('error', 'Nama, harga jual, dan stok awal wajib valid.');
                redirect_to('products');
            }

            if ($id > 0) {
                $index = find_index_by_id($store['items'], $id);
                if ($index < 0) {
                    flash('error', 'Barang tidak ditemukan.');
                    redirect_to('products');
                }
                $store['items'][$index]['name'] = $name;
                $store['items'][$index]['sku'] = $sku;
                $store['items'][$index]['unit'] = $unit;
                $store['items'][$index]['type'] = $type;
                $store['items'][$index]['price'] = $price;
                $store['items'][$index]['updated_at'] = now_id();
                flash('success', 'Barang berhasil diperbarui.');
            } else {
                $store['items'][] = [
                    'id' => next_id($store['items']),
                    'name' => $name,
                    'sku' => $sku,
                    'unit' => $unit,
                    'type' => $type,
                    'price' => $price,
                    'stock' => $initialStock,
                    'created_at' => now_id(),
                    'updated_at' => now_id(),
                ];
                flash('success', 'Barang berhasil ditambahkan.');
            }
            save_store($store);
            redirect_to('products');
        }

        if ($action === 'delete_item') {
            $id = (int) ($_POST['id'] ?? 0);
            $index = find_index_by_id($store['items'], $id);
            if ($index < 0 || has_transaction_item($store['transactions'], $id)) {
                flash('error', 'Barang tidak bisa dihapus karena tidak ditemukan atau sudah dipakai transaksi.');
            } else {
                array_splice($store['items'], $index, 1);
                save_store($store);
                flash('success', 'Barang berhasil dihapus.');
            }
            redirect_to('products');
        }

        if ($action === 'save_sales') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = post_string('name');
            $phone = post_string('phone');
            if ($name === '') {
                flash('error', 'Nama sales wajib diisi.');
                redirect_to('agents');
            }

            if ($id > 0) {
                $index = find_index_by_id($store['sales'], $id);
                if ($index < 0) {
                    flash('error', 'Sales tidak ditemukan.');
                    redirect_to('agents');
                }
                $store['sales'][$index]['name'] = $name;
                $store['sales'][$index]['phone'] = $phone;
                $store['sales'][$index]['updated_at'] = now_id();
                flash('success', 'Sales berhasil diperbarui.');
            } else {
                $store['sales'][] = [
                    'id' => next_id($store['sales']),
                    'name' => $name,
                    'phone' => $phone,
                    'created_at' => now_id(),
                    'updated_at' => now_id(),
                ];
                flash('success', 'Sales berhasil ditambahkan.');
            }
            save_store($store);
            redirect_to('agents');
        }

        if ($action === 'delete_sales') {
            $id = (int) ($_POST['id'] ?? 0);
            $index = find_index_by_id($store['sales'], $id);
            if ($index < 0 || has_transaction_sales($store['transactions'], $id)) {
                flash('error', 'Sales tidak bisa dihapus karena tidak ditemukan atau sudah dipakai transaksi.');
            } else {
                array_splice($store['sales'], $index, 1);
                save_store($store);
                flash('success', 'Sales berhasil dihapus.');
            }
            redirect_to('agents');
        }

        if ($action === 'create_pickup') {
            $salesId = (int) ($_POST['sales_id'] ?? 0);
            if (find_index_by_id($store['sales'], $salesId) < 0) {
                flash('error', 'Pilih sales yang valid.');
                redirect_to('pickup');
            }

            $requested = [];
            foreach (($_POST['item_id'] ?? []) as $row => $itemIdRaw) {
                $itemId = (int) $itemIdRaw;
                $qty = isset($_POST['qty'][$row]) ? (float) str_replace(',', '.', (string) $_POST['qty'][$row]) : 0.0;
                if ($itemId > 0 && $qty > 0) {
                    $requested[$itemId] = ($requested[$itemId] ?? 0) + $qty;
                }
            }

            if ($requested === []) {
                flash('error', 'Tambahkan minimal satu barang dan qty pengambilan.');
                redirect_to('pickup');
            }

            $transactionItems = [];
            foreach ($requested as $itemId => $qty) {
                $index = find_index_by_id($store['items'], (int) $itemId);
                if ($index < 0) {
                    flash('error', 'Barang yang dipilih tidak ditemukan.');
                    redirect_to('pickup');
                }
                if ((float) $store['items'][$index]['stock'] < $qty) {
                    flash('error', 'Stok ' . $store['items'][$index]['name'] . ' tidak cukup.');
                    redirect_to('pickup');
                }
                $store['items'][$index]['stock'] = (float) $store['items'][$index]['stock'] - $qty;
                $store['items'][$index]['updated_at'] = now_id();
                $transactionItems[] = [
                    'item_id' => (int) $itemId,
                    'name' => $store['items'][$index]['name'],
                    'sku' => $store['items'][$index]['sku'],
                    'unit' => $store['items'][$index]['unit'],
                    'price' => (float) $store['items'][$index]['price'],
                    'qty_taken' => $qty,
                    'qty_returned' => null,
                    'qty_sold' => null,
                    'subtotal' => 0,
                ];
            }

            $store['transactions'][] = [
                'id' => next_id($store['transactions']),
                'date' => now_id(),
                'sales_id' => $salesId,
                'status' => 'open',
                'items' => $transactionItems,
                'paid_amount' => 0,
                'expected_amount' => 0,
                'difference' => 0,
                'closed_at' => null,
            ];
            save_store($store);
            flash('success', 'Transaksi pengambilan tersimpan dan stok gudang berkurang.');
            redirect_to('transactions');
        }

        if ($action === 'close_transaction') {
            $id = (int) ($_POST['id'] ?? 0);
            $index = find_index_by_id($store['transactions'], $id);
            if ($index < 0 || ($store['transactions'][$index]['status'] ?? '') !== 'open') {
                flash('error', 'Transaksi open tidak ditemukan.');
                redirect_to('returns');
            }

            $expected = 0.0;
            foreach ($store['transactions'][$index]['items'] as $itemIndex => $transactionItem) {
                $returned = isset($_POST['returned'][$itemIndex]) ? (float) str_replace(',', '.', (string) $_POST['returned'][$itemIndex]) : -1;
                $taken = (float) $transactionItem['qty_taken'];
                if ($returned < 0 || $returned > $taken) {
                    flash('error', 'Qty retur wajib antara 0 dan qty ambil.');
                    redirect_to('return_detail', ['id' => $id]);
                }
                $sold = $taken - $returned;
                $subtotal = $sold * (float) $transactionItem['price'];
                $expected += $subtotal;

                $store['transactions'][$index]['items'][$itemIndex]['qty_returned'] = $returned;
                $store['transactions'][$index]['items'][$itemIndex]['qty_sold'] = $sold;
                $store['transactions'][$index]['items'][$itemIndex]['subtotal'] = $subtotal;

                $itemStoreIndex = find_index_by_id($store['items'], (int) $transactionItem['item_id']);
                if ($itemStoreIndex >= 0) {
                    $store['items'][$itemStoreIndex]['stock'] = (float) $store['items'][$itemStoreIndex]['stock'] + $returned;
                    $store['items'][$itemStoreIndex]['updated_at'] = now_id();
                }
            }

            $paid = post_float('paid_amount');
            if ($paid < 0) {
                flash('error', 'Uang setoran wajib valid.');
                redirect_to('return_detail', ['id' => $id]);
            }

            $store['transactions'][$index]['status'] = 'closed';
            $store['transactions'][$index]['paid_amount'] = $paid;
            $store['transactions'][$index]['expected_amount'] = $expected;
            $store['transactions'][$index]['difference'] = $paid - $expected;
            $store['transactions'][$index]['closed_at'] = now_id();
            save_store($store);
            flash('success', 'Transaksi selesai, stok retur sudah kembali ke gudang.');
            redirect_to('transaction_detail', ['id' => $id]);
        }
    }
} catch (Throwable $exception) {
    flash('error', 'Terjadi kesalahan: ' . $exception->getMessage());
    redirect_to('dashboard');
}

$editItem = null;
if ($page === 'products' && isset($_GET['edit'])) {
    $idx = find_index_by_id($store['items'], (int) $_GET['edit']);
    $editItem = $idx >= 0 ? $store['items'][$idx] : null;
}

$editSales = null;
if ($page === 'agents' && isset($_GET['edit'])) {
    $idx = find_index_by_id($store['sales'], (int) $_GET['edit']);
    $editSales = $idx >= 0 ? $store['sales'][$idx] : null;
}

function render_header(string $page): void
{
    $menus = [
        'dashboard' => ['label' => 'Dashboard', 'icon' => '📊'],
        'products' => ['label' => 'Produk', 'icon' => '📦'],
        'agents' => ['label' => 'Agent', 'icon' => '👥'],
    ];
    ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stok Gudang JSON</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        warehouse: {
                            50: '#eff6ff',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            900: '#172033'
                        }
                    }
                }
            }
        };
    </script>
    <style type="text/tailwindcss">
        @layer base {
            body { @apply bg-slate-100 text-slate-900 antialiased; }
            h2 { @apply text-xl font-bold text-slate-900; }
            h3 { @apply mt-6 text-base font-bold text-slate-800; }
            label { @apply mt-4 mb-1.5 block text-sm font-semibold text-slate-700; }
            input, select { @apply w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100; }
            table { @apply mt-4 min-w-full divide-y divide-slate-200 text-sm; }
            thead { @apply bg-slate-50; }
            th { @apply px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500; }
            td { @apply border-b border-slate-100 px-4 py-3 align-top text-slate-700; }
        }
        @layer components {
            .sidebar-link { @apply flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white; }
            .sidebar-link.active { @apply bg-white text-blue-700 shadow-lg shadow-blue-950/10; }
            .grid { @apply grid grid-cols-1 gap-4 lg:grid-cols-12; }
            .card { @apply rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/70; }
            .span-3 { @apply lg:col-span-3; }
            .span-4 { @apply lg:col-span-4; }
            .span-6 { @apply lg:col-span-6; }
            .span-8 { @apply lg:col-span-8; }
            .span-12 { @apply lg:col-span-12; }
            .muted { @apply text-sm text-slate-500; }
            .stat { @apply mt-2 text-3xl font-extrabold tracking-tight text-slate-900; }
            .button, button { @apply inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100; }
            .button.secondary { @apply bg-slate-600 hover:bg-slate-700; }
            .button.danger, button.danger { @apply bg-red-600 hover:bg-red-700 focus:ring-red-100; }
            .button.light { @apply bg-slate-100 text-slate-700 shadow-none hover:bg-slate-200 focus:ring-slate-100; }
            .actions { @apply flex flex-wrap items-center gap-2; }
            .flash { @apply mb-4 rounded-2xl border px-4 py-3 text-sm font-semibold; }
            .flash.success { @apply border-emerald-200 bg-emerald-50 text-emerald-700; }
            .flash.error { @apply border-red-200 bg-red-50 text-red-700; }
            .badge { @apply inline-flex rounded-full px-2.5 py-1 text-xs font-extrabold uppercase tracking-wide; }
            .badge.open { @apply bg-amber-100 text-amber-700; }
            .badge.closed { @apply bg-emerald-100 text-emerald-700; }
            .row-form { @apply mb-3 grid grid-cols-1 gap-3 md:grid-cols-[2fr_1fr]; }
            .right { @apply text-right; }
            .danger-text { @apply text-red-600; }
            .ok-text { @apply text-emerald-700; }
        }
    </style>
</head>
<body>
<div class="min-h-screen lg:flex">
    <aside class="bg-slate-950 text-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-72 lg:flex-col">
        <div class="bg-gradient-to-br from-blue-600 to-teal-600 p-6 lg:bg-none">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 text-2xl shadow-inner">🏬</div>
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight">Stok Gudang</h1>
                    <p class="text-sm text-blue-100 lg:text-slate-400">PHP + JSON Storage</p>
                </div>
            </div>
        </div>
        <nav class="flex gap-2 overflow-x-auto p-4 lg:flex-1 lg:flex-col lg:overflow-visible">
            <?php foreach ($menus as $key => $menu): ?>
                <a class="sidebar-link <?= $page === $key ? 'active' : '' ?>" href="?page=<?= e($key) ?>">
                    <span><?= e($menu['icon']) ?></span><span class="whitespace-nowrap"><?= e($menu['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="hidden border-t border-white/10 p-5 text-sm text-slate-400 lg:block">
            Sidebar utama: Dashboard, Produk, dan Agent.
        </div>
    </aside>
    <main class="flex-1 p-4 sm:p-6 lg:p-8">
        <div class="mb-6 rounded-3xl bg-gradient-to-r from-blue-600 to-teal-600 p-6 text-white shadow-lg shadow-blue-200">
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-100">Dashboard Admin Gudang</p>
            <h2 class="mt-1 text-2xl font-extrabold text-white">Pantau stok dan transaksi sales harian</h2>
        </div>
        <?php foreach (flashes() as $message): ?>
            <div class="flash <?= e($message['type']) ?>"><?= e($message['message']) ?></div>
        <?php endforeach; ?>
    <?php
}

function render_footer(): void
{
    echo '</main></div></body></html>';
}

render_header($page);

if ($page === 'dashboard'):
    $openTransactions = array_values(array_filter($store['transactions'], fn ($t) => ($t['status'] ?? '') === 'open'));
    $recent = array_slice(array_reverse($store['transactions']), 0, 5);
    ?>
    <div class="grid">
        <section class="card span-3"><div class="muted">Total jenis barang</div><div class="stat"><?= count($store['items']) ?></div></section>
        <section class="card span-3"><div class="muted">Total stok unit</div><div class="stat"><?= e(num(array_sum(array_map(fn ($i) => (float) ($i['stock'] ?? 0), $store['items'])))) ?></div></section>
        <section class="card span-3"><div class="muted">Sales terdaftar</div><div class="stat"><?= count($store['sales']) ?></div></section>
        <section class="card span-3"><div class="muted">Transaksi open</div><div class="stat"><?= count($openTransactions) ?></div></section>
        <section class="card span-12">
            <h2>Aksi cepat</h2>
            <p class="muted">Menu utama di sidebar hanya Dashboard, Produk, dan Agent. Alur operasional tetap tersedia dari tombol berikut.</p>
            <p class="actions mt-4"><a class="button" href="?page=pickup">Catat pengambilan</a><a class="button secondary" href="?page=returns">Input setoran/retur</a><a class="button light" href="?page=transactions">Lihat riwayat</a></p>
        </section>
        <section class="card span-6">
            <h2>Ringkasan stok saat ini</h2>
            <table><thead><tr><th>Produk</th><th>Jenis</th><th>Stok</th><th>Harga</th></tr></thead><tbody>
            <?php foreach ($store['items'] as $item): ?>
                <tr><td><?= e($item['name']) ?></td><td><?= e($item['type'] ?? '-') ?></td><td><?= e(num($item['stock'])) ?> <?= e($item['unit']) ?></td><td><?= e(money($item['price'])) ?></td></tr>
            <?php endforeach; if ($store['items'] === []): ?><tr><td colspan="4" class="muted">Belum ada produk.</td></tr><?php endif; ?>
            </tbody></table>
        </section>
        <section class="card span-6">
            <h2>Pengambilan belum selesai</h2>
            <table><thead><tr><th>Tanggal</th><th>Sales</th><th>Total ambil</th><th></th></tr></thead><tbody>
            <?php foreach ($openTransactions as $transaction): $totals = transaction_totals($transaction); ?>
                <tr><td><?= e($transaction['date']) ?></td><td><?= e(sales_name($store['sales'], (int) $transaction['sales_id'])) ?></td><td><?= e(num($totals['taken'])) ?></td><td><a class="button light" href="?page=return_detail&id=<?= e($transaction['id']) ?>">Setor</a></td></tr>
            <?php endforeach; if ($openTransactions === []): ?><tr><td colspan="4" class="muted">Tidak ada transaksi open.</td></tr><?php endif; ?>
            </tbody></table>
        </section>
        <section class="card span-12">
            <h2>Riwayat transaksi terbaru</h2>
            <?php render_transactions_table($recent, $store); ?>
        </section>
    </div>
<?php elseif ($page === 'products'): ?>
    <div class="grid">
        <section class="card span-4">
            <h2><?= $editItem ? 'Edit produk' : 'Tambah produk' ?></h2>
            <form method="post">
                <input type="hidden" name="action" value="save_item">
                <input type="hidden" name="id" value="<?= e($editItem['id'] ?? 0) ?>">
                <label>Nama produk</label><input name="name" required value="<?= e($editItem['name'] ?? '') ?>">
                <label>SKU/kode (opsional)</label><input name="sku" value="<?= e($editItem['sku'] ?? '') ?>">
                <label>Satuan</label><input name="unit" required value="<?= e($editItem['unit'] ?? 'pcs') ?>">
                <label>Jenis</label><input name="type" required value="<?= e($editItem['type'] ?? '-') ?>">
                <label>Harga jual per unit</label><input name="price" type="number" min="0" step="0.01" required value="<?= e($editItem['price'] ?? 0) ?>">
                <?php if (!$editItem): ?><label>Stok awal</label><input name="stock" type="number" min="0" step="0.01" required value="0"><?php endif; ?>
                <p class="actions"><button>Simpan</button><?php if ($editItem): ?><a class="button light" href="?page=products">Batal</a><?php endif; ?></p>
            </form>
        </section>
        <section class="card span-8">
            <h2>Produk</h2>
            <table><thead><tr><th>Nama produk</th><th>Stok</th><th>Jenis</th><th>Satuan</th><th>Harga</th><th>Aksi</th></tr></thead><tbody>
            <?php foreach ($store['items'] as $item): ?>
                <tr>
                    <td><?= e($item['name']) ?></td><td><?= e(num($item['stock'])) ?></td><td><?= e($item['type'] ?? '-') ?></td><td><?= e($item['unit']) ?></td><td><?= e(money($item['price'])) ?></td>
                    <td class="actions"><a class="button light" href="?page=products&edit=<?= e($item['id']) ?>">Edit</a><form method="post" onsubmit="return confirm('Hapus barang ini?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="id" value="<?= e($item['id']) ?>"><button class="danger">Hapus</button></form></td>
                </tr>
            <?php endforeach; if ($store['items'] === []): ?><tr><td colspan="6" class="muted">Belum ada produk.</td></tr><?php endif; ?>
            </tbody></table>
        </section>
    </div>
<?php elseif ($page === 'agents'): ?>
    <?php $agentSummaries = agent_daily_summaries($store['transactions'], $store['sales']); ?>
    <div class="grid">
        <section class="card span-4">
            <h2><?= $editSales ? 'Edit agent' : 'Tambah agent' ?></h2>
            <form method="post">
                <input type="hidden" name="action" value="save_sales">
                <input type="hidden" name="id" value="<?= e($editSales['id'] ?? 0) ?>">
                <label>Nama sales/agent</label><input name="name" required value="<?= e($editSales['name'] ?? '') ?>">
                <label>No. HP/catatan (opsional)</label><input name="phone" value="<?= e($editSales['phone'] ?? '') ?>">
                <p class="actions mt-4"><button>Simpan</button><?php if ($editSales): ?><a class="button light" href="?page=agents">Batal</a><?php endif; ?></p>
            </form>
        </section>
        <section class="card span-8">
            <h2>Agent berdasarkan tanggal</h2>
            <table><thead><tr><th>Tanggal</th><th>Nama sales</th><th>Jumlah barang diambil</th><th>Jumlah barang dikembalikan</th><th>Jumlah uang setor</th><th>Transaksi</th></tr></thead><tbody>
            <?php foreach ($agentSummaries as $summary): ?>
                <tr>
                    <td><?= e($summary['date']) ?></td>
                    <td><?= e($summary['sales_name']) ?></td>
                    <td><?= e(num($summary['qty_taken'])) ?></td>
                    <td><?= e(num($summary['qty_returned'])) ?></td>
                    <td><?= e(money($summary['paid_amount'])) ?></td>
                    <td><?= e($summary['transactions']) ?></td>
                </tr>
            <?php endforeach; if ($agentSummaries === []): ?><tr><td colspan="6" class="muted">Belum ada data pengambilan/setoran agent.</td></tr><?php endif; ?>
            </tbody></table>
        </section>
        <section class="card span-12">
            <h2>CRUD agent</h2>
            <table><thead><tr><th>Nama sales</th><th>No. HP/catatan</th><th>CRUD</th></tr></thead><tbody>
            <?php foreach ($store['sales'] as $sales): ?>
                <tr><td><?= e($sales['name']) ?></td><td><?= e($sales['phone']) ?></td><td class="actions"><a class="button light" href="?page=agents&edit=<?= e($sales['id']) ?>">Edit</a><form method="post" onsubmit="return confirm('Hapus agent ini?')"><input type="hidden" name="action" value="delete_sales"><input type="hidden" name="id" value="<?= e($sales['id']) ?>"><button class="danger">Hapus</button></form></td></tr>
            <?php endforeach; if ($store['sales'] === []): ?><tr><td colspan="3" class="muted">Belum ada agent.</td></tr><?php endif; ?>
            </tbody></table>
        </section>
    </div>
<?php elseif ($page === 'pickup'): ?>
    <section class="card">
        <h2>Pencatatan pengambilan barang</h2>
        <?php if ($store['sales'] === [] || $store['items'] === []): ?>
            <p class="muted">Tambahkan sales dan barang terlebih dahulu.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="create_pickup">
                <label>Sales</label>
                <select name="sales_id" required><option value="">Pilih sales</option><?php foreach ($store['sales'] as $sales): ?><option value="<?= e($sales['id']) ?>"><?= e($sales['name']) ?></option><?php endforeach; ?></select>
                <h3>Item yang diambil</h3>
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <div class="row-form">
                        <select name="item_id[]"><option value="">Pilih barang</option><?php foreach ($store['items'] as $item): ?><option value="<?= e($item['id']) ?>"><?= e($item['name']) ?> (stok <?= e(num($item['stock'])) ?> <?= e($item['unit']) ?>)</option><?php endforeach; ?></select>
                        <input name="qty[]" type="number" min="0" step="0.01" placeholder="Qty ambil">
                    </div>
                <?php endfor; ?>
                <button>Simpan pengambilan</button>
            </form>
        <?php endif; ?>
    </section>
<?php elseif ($page === 'returns'): ?>
    <section class="card">
        <h2>Pencatatan setoran/retur</h2>
        <table><thead><tr><th>Tanggal ambil</th><th>Sales</th><th>Total ambil</th><th>Aksi</th></tr></thead><tbody>
        <?php $open = false; foreach ($store['transactions'] as $transaction): if (($transaction['status'] ?? '') !== 'open') { continue; } $open = true; $totals = transaction_totals($transaction); ?>
            <tr><td><?= e($transaction['date']) ?></td><td><?= e(sales_name($store['sales'], (int) $transaction['sales_id'])) ?></td><td><?= e(num($totals['taken'])) ?></td><td><a class="button" href="?page=return_detail&id=<?= e($transaction['id']) ?>">Input setoran</a></td></tr>
        <?php endforeach; if (!$open): ?><tr><td colspan="4" class="muted">Tidak ada transaksi open.</td></tr><?php endif; ?>
        </tbody></table>
    </section>
<?php elseif ($page === 'return_detail'):
    $idx = find_index_by_id($store['transactions'], (int) ($_GET['id'] ?? 0));
    $transaction = $idx >= 0 ? $store['transactions'][$idx] : null;
    ?>
    <section class="card">
        <?php if (!$transaction || ($transaction['status'] ?? '') !== 'open'): ?>
            <p class="muted">Transaksi open tidak ditemukan.</p>
        <?php else: ?>
            <h2>Setoran/retur transaksi #<?= e($transaction['id']) ?></h2>
            <p><strong>Sales:</strong> <?= e(sales_name($store['sales'], (int) $transaction['sales_id'])) ?> · <strong>Tanggal:</strong> <?= e($transaction['date']) ?></p>
            <form method="post">
                <input type="hidden" name="action" value="close_transaction"><input type="hidden" name="id" value="<?= e($transaction['id']) ?>">
                <table><thead><tr><th>Barang</th><th>Harga</th><th>Qty ambil</th><th>Qty retur/sisa</th></tr></thead><tbody>
                <?php foreach ($transaction['items'] as $i => $item): ?>
                    <tr><td><?= e($item['name']) ?></td><td><?= e(money($item['price'])) ?></td><td><?= e(num($item['qty_taken'])) ?> <?= e($item['unit']) ?></td><td><input name="returned[<?= e($i) ?>]" type="number" min="0" max="<?= e($item['qty_taken']) ?>" step="0.01" required value="0"></td></tr>
                <?php endforeach; ?>
                </tbody></table>
                <label>Uang yang benar-benar disetor</label><input name="paid_amount" type="number" min="0" step="0.01" required value="0">
                <p class="muted">Sistem akan menghitung qty terjual, total seharusnya disetor, dan selisih lebih/kurang setelah disimpan.</p>
                <button>Selesaikan transaksi</button>
            </form>
        <?php endif; ?>
    </section>
<?php elseif ($page === 'transactions'): ?>
    <section class="card"><h2>Riwayat transaksi</h2><?php render_transactions_table(array_reverse($store['transactions']), $store); ?></section>
<?php elseif ($page === 'transaction_detail'):
    $idx = find_index_by_id($store['transactions'], (int) ($_GET['id'] ?? 0));
    $transaction = $idx >= 0 ? $store['transactions'][$idx] : null;
    ?>
    <section class="card">
        <?php if (!$transaction): ?>
            <p class="muted">Transaksi tidak ditemukan.</p>
        <?php else: $totals = transaction_totals($transaction); ?>
            <h2>Detail transaksi #<?= e($transaction['id']) ?></h2>
            <p><strong>Tanggal ambil:</strong> <?= e($transaction['date']) ?> · <strong>Sales:</strong> <?= e(sales_name($store['sales'], (int) $transaction['sales_id'])) ?> · <span class="badge <?= e($transaction['status']) ?>"><?= e($transaction['status']) ?></span></p>
            <?php if (($transaction['status'] ?? '') === 'closed'): ?><p><strong>Selesai:</strong> <?= e($transaction['closed_at']) ?></p><?php endif; ?>
            <table><thead><tr><th>Barang</th><th>Harga</th><th>Ambil</th><th>Retur</th><th>Terjual</th><th>Subtotal</th></tr></thead><tbody>
            <?php foreach ($transaction['items'] as $item): ?>
                <tr><td><?= e($item['name']) ?></td><td><?= e(money($item['price'])) ?></td><td><?= e(num($item['qty_taken'])) ?></td><td><?= e($item['qty_returned'] === null ? '-' : num($item['qty_returned'])) ?></td><td><?= e($item['qty_sold'] === null ? '-' : num($item['qty_sold'])) ?></td><td><?= e(money($item['subtotal'] ?? 0)) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
            <h3>Ringkasan</h3>
            <p>Total ambil: <strong><?= e(num($totals['taken'])) ?></strong> · Total terjual: <strong><?= e(num($totals['sold'])) ?></strong> · Seharusnya setor: <strong><?= e(money($totals['expected'])) ?></strong> · Setoran: <strong><?= e(money($totals['paid'])) ?></strong> · Selisih: <strong class="<?= $totals['difference'] < 0 ? 'danger-text' : 'ok-text' ?>"><?= e(money($totals['difference'])) ?></strong></p>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="card"><p>Halaman tidak ditemukan.</p></section>
<?php endif; ?>

<?php
render_footer();

function render_transactions_table(array $transactions, array $store): void
{
    ?>
    <table><thead><tr><th>Tanggal</th><th>Sales</th><th>Status</th><th>Total ambil</th><th>Total terjual</th><th>Total setoran</th><th>Selisih</th><th></th></tr></thead><tbody>
    <?php foreach ($transactions as $transaction): $totals = transaction_totals($transaction); ?>
        <tr>
            <td><?= e($transaction['date']) ?></td>
            <td><?= e(sales_name($store['sales'], (int) $transaction['sales_id'])) ?></td>
            <td><span class="badge <?= e($transaction['status']) ?>"><?= e($transaction['status']) ?></span></td>
            <td><?= e(num($totals['taken'])) ?></td>
            <td><?= e(num($totals['sold'])) ?></td>
            <td><?= e(money($totals['paid'])) ?></td>
            <td class="<?= $totals['difference'] < 0 ? 'danger-text' : 'ok-text' ?>"><?= e(money($totals['difference'])) ?></td>
            <td><a class="button light" href="?page=transaction_detail&id=<?= e($transaction['id']) ?>">Detail</a></td>
        </tr>
    <?php endforeach; if ($transactions === []): ?><tr><td colspan="8" class="muted">Belum ada transaksi.</td></tr><?php endif; ?>
    </tbody></table>
    <?php
}

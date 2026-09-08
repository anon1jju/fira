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

$store = load_store();
$page = $_GET['page'] ?? 'dashboard';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'save_item') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = post_string('name');
            $sku = post_string('sku');
            $unit = post_string('unit') ?: 'pcs';
            $price = post_float('price');
            $initialStock = post_float('stock');

            if ($name === '' || $price < 0 || ($id === 0 && $initialStock < 0)) {
                flash('error', 'Nama, harga jual, dan stok awal wajib valid.');
                redirect_to('items');
            }

            if ($id > 0) {
                $index = find_index_by_id($store['items'], $id);
                if ($index < 0) {
                    flash('error', 'Barang tidak ditemukan.');
                    redirect_to('items');
                }
                $store['items'][$index]['name'] = $name;
                $store['items'][$index]['sku'] = $sku;
                $store['items'][$index]['unit'] = $unit;
                $store['items'][$index]['price'] = $price;
                $store['items'][$index]['updated_at'] = now_id();
                flash('success', 'Barang berhasil diperbarui.');
            } else {
                $store['items'][] = [
                    'id' => next_id($store['items']),
                    'name' => $name,
                    'sku' => $sku,
                    'unit' => $unit,
                    'price' => $price,
                    'stock' => $initialStock,
                    'created_at' => now_id(),
                    'updated_at' => now_id(),
                ];
                flash('success', 'Barang berhasil ditambahkan.');
            }
            save_store($store);
            redirect_to('items');
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
            redirect_to('items');
        }

        if ($action === 'save_sales') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = post_string('name');
            $phone = post_string('phone');
            if ($name === '') {
                flash('error', 'Nama sales wajib diisi.');
                redirect_to('sales');
            }

            if ($id > 0) {
                $index = find_index_by_id($store['sales'], $id);
                if ($index < 0) {
                    flash('error', 'Sales tidak ditemukan.');
                    redirect_to('sales');
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
            redirect_to('sales');
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
            redirect_to('sales');
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
if ($page === 'items' && isset($_GET['edit'])) {
    $idx = find_index_by_id($store['items'], (int) $_GET['edit']);
    $editItem = $idx >= 0 ? $store['items'][$idx] : null;
}

$editSales = null;
if ($page === 'sales' && isset($_GET['edit'])) {
    $idx = find_index_by_id($store['sales'], (int) $_GET['edit']);
    $editSales = $idx >= 0 ? $store['sales'][$idx] : null;
}

function render_header(string $page): void
{
    $menus = [
        'dashboard' => 'Dashboard',
        'items' => 'Barang',
        'sales' => 'Sales',
        'pickup' => 'Pengambilan',
        'returns' => 'Setoran/Retur',
        'transactions' => 'Riwayat',
    ];
    ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stok Gudang JSON</title>
    <style>
        :root { --primary:#2563eb; --bg:#f4f7fb; --card:#fff; --text:#172033; --muted:#667085; --border:#d9e2ef; --danger:#dc2626; --ok:#15803d; --warn:#b45309; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, Segoe UI, sans-serif; background:var(--bg); color:var(--text); }
        header { background:linear-gradient(120deg, #1d4ed8, #0f766e); color:#fff; padding:22px; }
        header h1 { margin:0 0 12px; font-size:24px; }
        nav { display:flex; gap:8px; flex-wrap:wrap; }
        nav a { color:#eaf2ff; text-decoration:none; padding:8px 12px; border-radius:999px; background:rgba(255,255,255,.14); }
        nav a.active, nav a:hover { background:#fff; color:#1d4ed8; }
        main { max-width:1180px; margin:0 auto; padding:22px; }
        .grid { display:grid; grid-template-columns: repeat(12, 1fr); gap:16px; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:16px; padding:18px; box-shadow:0 8px 24px rgba(22,34,51,.05); }
        .span-3 { grid-column: span 3; } .span-4 { grid-column: span 4; } .span-6 { grid-column: span 6; } .span-8 { grid-column: span 8; } .span-12 { grid-column: span 12; }
        h2, h3 { margin-top:0; } .muted { color:var(--muted); } .stat { font-size:30px; font-weight:800; }
        table { width:100%; border-collapse:collapse; margin-top:12px; }
        th, td { padding:10px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; }
        th { font-size:13px; color:var(--muted); background:#f8fafc; }
        input, select { width:100%; padding:10px; border:1px solid var(--border); border-radius:10px; background:#fff; }
        label { display:block; font-weight:700; margin:10px 0 6px; }
        button, .button { display:inline-block; border:0; border-radius:10px; padding:10px 14px; background:var(--primary); color:white; text-decoration:none; cursor:pointer; font-weight:700; }
        .button.secondary { background:#475569; } .button.danger, button.danger { background:var(--danger); } .button.light { background:#e2e8f0; color:#0f172a; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .flash { padding:12px 14px; border-radius:12px; margin-bottom:14px; border:1px solid; }
        .flash.success { background:#ecfdf3; color:#166534; border-color:#bbf7d0; }
        .flash.error { background:#fef2f2; color:#991b1b; border-color:#fecaca; }
        .badge { display:inline-block; border-radius:999px; padding:4px 9px; font-size:12px; font-weight:800; }
        .badge.open { background:#fff7ed; color:var(--warn); } .badge.closed { background:#ecfdf3; color:var(--ok); }
        .row-form { display:grid; grid-template-columns: 2fr 1fr; gap:10px; margin-bottom:10px; }
        .right { text-align:right; } .danger-text { color:var(--danger); } .ok-text { color:var(--ok); }
        @media (max-width: 800px) { .span-3, .span-4, .span-6, .span-8 { grid-column: span 12; } table { display:block; overflow-x:auto; } main { padding:14px; } }
    </style>
</head>
<body>
<header>
    <h1>Stok Gudang JSON</h1>
    <nav>
        <?php foreach ($menus as $key => $label): ?>
            <a class="<?= $page === $key ? 'active' : '' ?>" href="?page=<?= e($key) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
</header>
<main>
    <?php foreach (flashes() as $message): ?>
        <div class="flash <?= e($message['type']) ?>"><?= e($message['message']) ?></div>
    <?php endforeach; ?>
    <?php
}

function render_footer(): void
{
    echo '</main></body></html>';
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
        <section class="card span-6">
            <h2>Ringkasan stok saat ini</h2>
            <table><thead><tr><th>Barang</th><th>SKU</th><th>Stok</th><th>Harga</th></tr></thead><tbody>
            <?php foreach ($store['items'] as $item): ?>
                <tr><td><?= e($item['name']) ?></td><td><?= e($item['sku']) ?></td><td><?= e(num($item['stock'])) ?> <?= e($item['unit']) ?></td><td><?= e(money($item['price'])) ?></td></tr>
            <?php endforeach; if ($store['items'] === []): ?><tr><td colspan="4" class="muted">Belum ada barang.</td></tr><?php endif; ?>
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
<?php elseif ($page === 'items'): ?>
    <div class="grid">
        <section class="card span-4">
            <h2><?= $editItem ? 'Edit barang' : 'Tambah barang' ?></h2>
            <form method="post">
                <input type="hidden" name="action" value="save_item">
                <input type="hidden" name="id" value="<?= e($editItem['id'] ?? 0) ?>">
                <label>Nama barang</label><input name="name" required value="<?= e($editItem['name'] ?? '') ?>">
                <label>SKU/kode (opsional)</label><input name="sku" value="<?= e($editItem['sku'] ?? '') ?>">
                <label>Satuan</label><input name="unit" required value="<?= e($editItem['unit'] ?? 'pcs') ?>">
                <label>Harga jual per unit</label><input name="price" type="number" min="0" step="0.01" required value="<?= e($editItem['price'] ?? 0) ?>">
                <?php if (!$editItem): ?><label>Stok awal</label><input name="stock" type="number" min="0" step="0.01" required value="0"><?php endif; ?>
                <p class="actions"><button>Simpan</button><?php if ($editItem): ?><a class="button light" href="?page=items">Batal</a><?php endif; ?></p>
            </form>
        </section>
        <section class="card span-8">
            <h2>Master barang</h2>
            <table><thead><tr><th>Nama</th><th>SKU</th><th>Satuan</th><th>Harga</th><th>Stok</th><th>Aksi</th></tr></thead><tbody>
            <?php foreach ($store['items'] as $item): ?>
                <tr>
                    <td><?= e($item['name']) ?></td><td><?= e($item['sku']) ?></td><td><?= e($item['unit']) ?></td><td><?= e(money($item['price'])) ?></td><td><?= e(num($item['stock'])) ?></td>
                    <td class="actions"><a class="button light" href="?page=items&edit=<?= e($item['id']) ?>">Edit</a><form method="post" onsubmit="return confirm('Hapus barang ini?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="id" value="<?= e($item['id']) ?>"><button class="danger">Hapus</button></form></td>
                </tr>
            <?php endforeach; if ($store['items'] === []): ?><tr><td colspan="6" class="muted">Belum ada barang.</td></tr><?php endif; ?>
            </tbody></table>
        </section>
    </div>
<?php elseif ($page === 'sales'): ?>
    <div class="grid">
        <section class="card span-4">
            <h2><?= $editSales ? 'Edit sales' : 'Tambah sales' ?></h2>
            <form method="post">
                <input type="hidden" name="action" value="save_sales">
                <input type="hidden" name="id" value="<?= e($editSales['id'] ?? 0) ?>">
                <label>Nama sales</label><input name="name" required value="<?= e($editSales['name'] ?? '') ?>">
                <label>No. HP/catatan (opsional)</label><input name="phone" value="<?= e($editSales['phone'] ?? '') ?>">
                <p class="actions"><button>Simpan</button><?php if ($editSales): ?><a class="button light" href="?page=sales">Batal</a><?php endif; ?></p>
            </form>
        </section>
        <section class="card span-8">
            <h2>Master sales</h2>
            <table><thead><tr><th>Nama</th><th>No. HP/catatan</th><th>Aksi</th></tr></thead><tbody>
            <?php foreach ($store['sales'] as $sales): ?>
                <tr><td><?= e($sales['name']) ?></td><td><?= e($sales['phone']) ?></td><td class="actions"><a class="button light" href="?page=sales&edit=<?= e($sales['id']) ?>">Edit</a><form method="post" onsubmit="return confirm('Hapus sales ini?')"><input type="hidden" name="action" value="delete_sales"><input type="hidden" name="id" value="<?= e($sales['id']) ?>"><button class="danger">Hapus</button></form></td></tr>
            <?php endforeach; if ($store['sales'] === []): ?><tr><td colspan="3" class="muted">Belum ada sales.</td></tr><?php endif; ?>
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

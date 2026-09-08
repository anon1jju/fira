# Aplikasi Stok Gudang PHP JSON

Aplikasi web sederhana untuk admin gudang yang mencatat barang, sales, pengambilan barang oleh sales, retur/sisa barang, dan uang setoran penjualan. Aplikasi ini memakai PHP native tanpa framework besar, Tailwind CSS untuk frontend, dan menyimpan data dalam file JSON.

## Fitur

- Sidebar utama berisi Dashboard, Produk, dan Agent.
- Dashboard ringkas:
  - Total jenis produk, total stok unit, agent/sales terdaftar, dan transaksi yang masih open.
  - Ringkasan stok saat ini.
  - Daftar pengambilan sales yang belum diselesaikan.
  - Riwayat transaksi terbaru.
- Produk:
  - Tabel nama produk, stok, jenis, satuan, harga, dan aksi CRUD.
  - Tambah produk dengan nama, SKU/kode opsional, satuan, jenis, harga jual, dan stok awal.
  - Edit data produk.
  - Hapus produk jika belum pernah dipakai transaksi.
- Agent:
  - CRUD sales/agent.
  - Ringkasan berdasarkan tanggal berisi nama sales, jumlah barang diambil, jumlah barang dikembalikan, jumlah uang yang disetor, dan jumlah transaksi.
- Pengambilan barang:
  - Pilih sales dan beberapa barang sekaligus.
  - Validasi stok cukup di server.
  - Stok gudang otomatis berkurang saat transaksi disimpan.
  - Transaksi dibuat dengan status `open`.
- Setoran/retur:
  - Pilih transaksi `open`.
  - Input qty retur/sisa untuk setiap barang.
  - Sistem menghitung qty terjual, total uang yang seharusnya disetor, setoran aktual, dan selisih.
  - Stok retur otomatis kembali ke gudang.
  - Transaksi berubah menjadi `closed`.
- Riwayat transaksi dan detail transaksi.

## Cara menjalankan

Pastikan PHP sudah terpasang, lalu jalankan dari root repository:

```bash
php -S localhost:8000
```

Buka browser ke:

```text
http://localhost:8000
```

## Struktur file

```text
index.php          Entry point, routing sederhana, sidebar Dashboard/Produk/Agent, form, dan UI Tailwind
app/storage.php    Helper baca/tulis JSON dengan file locking
data/              Folder data runtime JSON, dibuat/diisi otomatis jika belum ada
```

File data runtime yang dibuat otomatis:

```text
data/items.json          Data master produk, jenis, dan stok saat ini
data/sales.json          Data master sales/agent
data/transactions.json   Data pengambilan, setoran/retur, dan status transaksi
```

File JSON runtime di dalam `data/` diabaikan oleh Git agar data lokal tidak ikut ter-commit.

## Catatan teknis

- Tidak menggunakan MySQL/PostgreSQL/SQLite.
- Frontend menggunakan Tailwind CSS CDN sehingga tidak perlu proses build asset.
- Output HTML disanitasi dengan `htmlspecialchars`.
- Input utama divalidasi di server-side.
- Penulisan JSON memakai lock file (`flock`) dan temporary file sebelum `rename` untuk mengurangi risiko file korup.

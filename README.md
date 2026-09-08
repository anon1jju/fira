# Aplikasi Stok Gudang PHP JSON

Aplikasi web sederhana untuk admin gudang yang mencatat barang, sales, pengambilan barang oleh sales, retur/sisa barang, dan uang setoran penjualan. Aplikasi ini memakai PHP native tanpa framework besar, Tailwind CSS untuk frontend, dan menyimpan data dalam file JSON.

## Fitur

- Dashboard ringkas dengan sidebar navigasi responsif:
  - Total jenis barang, total stok unit, sales terdaftar, dan transaksi yang masih open.
  - Ringkasan stok saat ini.
  - Daftar pengambilan sales yang belum diselesaikan.
  - Riwayat transaksi terbaru.
- Master barang:
  - Tambah barang dengan nama, SKU/kode opsional, satuan, harga jual, dan stok awal.
  - Edit data barang.
  - Hapus barang jika belum pernah dipakai transaksi.
  - Lihat stok saat ini.
- Master sales:
  - Tambah, edit, dan hapus sales.
  - Hapus sales hanya jika belum pernah dipakai transaksi.
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
index.php          Entry point, routing sederhana, form, sidebar, dashboard, dan UI Tailwind
app/storage.php    Helper baca/tulis JSON dengan file locking
data/              Folder data runtime JSON, dibuat/diisi otomatis jika belum ada
```

File data runtime yang dibuat otomatis:

```text
data/items.json          Data master barang dan stok saat ini
data/sales.json          Data master sales
data/transactions.json   Data pengambilan, setoran/retur, dan status transaksi
```

File JSON runtime di dalam `data/` diabaikan oleh Git agar data lokal tidak ikut ter-commit.

## Catatan teknis

- Tidak menggunakan MySQL/PostgreSQL/SQLite.
- Frontend menggunakan Tailwind CSS CDN sehingga tidak perlu proses build asset.
- Output HTML disanitasi dengan `htmlspecialchars`.
- Input utama divalidasi di server-side.
- Penulisan JSON memakai lock file (`flock`) dan temporary file sebelum `rename` untuk mengurangi risiko file korup.

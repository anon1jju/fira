<?php

declare(strict_types=1);

const DATA_DIR = __DIR__ . '/../data';

function ensure_data_file(string $name, array $default): string
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0775, true);
    }

    $path = DATA_DIR . '/' . $name . '.json';
    if (!file_exists($path)) {
        safe_write_json($name, $default);
    }

    return $path;
}

function read_json(string $name, array $default = []): array
{
    $path = ensure_data_file($name, $default);
    $handle = fopen($path, 'rb');
    if (!$handle) {
        return $default;
    }

    flock($handle, LOCK_SH);
    $contents = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    if ($contents === false || trim($contents) === '') {
        return $default;
    }

    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : $default;
}

function safe_write_json(string $name, array $data): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0775, true);
    }

    $path = DATA_DIR . '/' . $name . '.json';
    $lockPath = DATA_DIR . '/' . $name . '.lock';
    $lock = fopen($lockPath, 'c');
    if (!$lock) {
        throw new RuntimeException('Tidak bisa membuka file lock data.');
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Tidak bisa mengunci file data.');
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Tidak bisa mengubah data menjadi JSON.');
        }

        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Tidak bisa menulis file data sementara.');
        }

        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Tidak bisa menyimpan file data.');
        }

        flock($lock, LOCK_UN);
    } finally {
        fclose($lock);
    }
}

function load_store(): array
{
    return [
        'items' => read_json('items', []),
        'sales' => read_json('sales', []),
        'transactions' => read_json('transactions', []),
    ];
}

function save_store(array $store): void
{
    safe_write_json('items', array_values($store['items'] ?? []));
    safe_write_json('sales', array_values($store['sales'] ?? []));
    safe_write_json('transactions', array_values($store['transactions'] ?? []));
}

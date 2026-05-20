<?php
/**
 * File test koneksi Midtrans
 * Letakkan di root project Laravel (sejajar dengan artisan)
 * Jalankan: php test-midtrans.php
 * Hapus file ini setelah selesai test
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$serverKey    = config('midtrans.server_key');
$isProduction = config('midtrans.is_production');
echo "Panjang key : " . strlen($serverKey) . " karakter\n";
echo "Karakter pertama: " . substr($serverKey, 0, 15) . "\n";
echo "Karakter terakhir: " . substr($serverKey, -5) . "\n";

echo "=== CEK CONFIG MIDTRANS ===\n";
// Di dalam test-midtrans.php
echo "Server Key dari config: " . config('midtrans.server_key') . "\n";
echo "Is Production dari config: " . (config('midtrans.is_production') ? 'true' : 'false') . "\n";
echo "Server Key  : " . $serverKey . "\n";
echo "Is Production: ";
var_dump($isProduction);
echo "Tipe is_production: " . gettype($isProduction) . "\n";
echo "\n";

// Set config Midtrans
\Midtrans\Config::$serverKey    = $serverKey;
\Midtrans\Config::$isProduction = (bool) $isProduction;
\Midtrans\Config::$isSanitized  = true;
\Midtrans\Config::$is3ds        = true;

echo "=== TEST KONEKSI KE MIDTRANS ===\n";

try {
    // Buat transaksi dummy untuk test
    $params = [
        'transaction_details' => [
            'order_id'     => 'TEST-' . time(),
            'gross_amount' => 10000,
        ],
        'customer_details' => [
            'first_name' => 'Test',
            'phone'      => '08123456789',
        ],
        'item_details' => [[
            'id'       => 1,
            'price'    => 10000,
            'quantity' => 1,
            'name'     => 'Test Paket',
        ]],
    ];

    $snapToken = \Midtrans\Snap::getSnapToken($params);
    echo "✅ BERHASIL! Snap Token: " . $snapToken . "\n";

} catch (\Exception $e) {
    echo "❌ GAGAL: " . $e->getMessage() . "\n";
}
<?php
// proses_pesanan.php — Handler AJAX Keranjang (Muslimah Bakery)
// Format session cart: $_SESSION['cart'][$id_produk] = (int) qty
// Dipanggil dari index.php via fetch() POST
include 'koneksi.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
// ── 1. Cek Login ──
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'pesan' => 'Silakan login terlebih dahulu.']);
    exit;
}
// ── 2. Validasi Request ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['aksi'])) {
    echo json_encode(['status' => 'error', 'pesan' => 'Request tidak valid.']);
    exit;
}
// FIX: validasi CSRF token — token dikirim dari index.php/produk.php lewat
// variabel JS CSRF_TOKEN yang nilainya sama dengan $_SESSION['csrf_token'].
$token_valid = isset($_POST['csrf_token'])
    && isset($_SESSION['csrf_token'])
    && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);

if (!$token_valid) {
    echo json_encode(['status' => 'error', 'pesan' => 'Sesi tidak valid, silakan muat ulang halaman.']);
    exit;
}
// ── 3. Bersihkan format cart lama (jika ada) ──
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
foreach ($_SESSION['cart'] as $k => $v) {
    if (!is_numeric($v)) unset($_SESSION['cart'][$k]);
}
// ── 4. Aksi: tambah_keranjang ──
if ($_POST['aksi'] === 'tambah_keranjang') {
    $id_produk = (int)($_POST['id_produk'] ?? 0);
    $jumlah    = max(1, (int)($_POST['jumlah'] ?? 1));
    if ($id_produk <= 0) {
        echo json_encode(['status' => 'error', 'pesan' => 'ID produk tidak valid.']);
        exit;
    }
    // Cek stok dari DB
    $stmt = mysqli_prepare($conn, "SELECT nama_produk, stok FROM produk WHERE id_produk = ? LIMIT 1");
    // FIX: cek hasil prepare() sebelum dipakai
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'pesan' => 'Terjadi kesalahan server. Silakan coba lagi.']);
        exit;
    }
    mysqli_stmt_bind_param($stmt, "i", $id_produk);
    mysqli_stmt_execute($stmt);
    $res  = mysqli_stmt_get_result($stmt);
    $prod = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if (!$prod) {
        echo json_encode(['status' => 'error', 'pesan' => 'Produk tidak ditemukan.']);
        exit;
    }
    $stok_db  = (int)$prod['stok'];
    $qty_cart = (int)($_SESSION['cart'][$id_produk] ?? 0);
    if ($stok_db <= 0) {
        echo json_encode(['status' => 'error', 'pesan' => 'Stok produk habis.']);
        exit;
    }
    if (($qty_cart + $jumlah) > $stok_db) {
        echo json_encode(['status' => 'error', 'pesan' => 'Stok tidak mencukupi. Tersisa ' . $stok_db . ' item.']);
        exit;
    }
    // Simpan ke session dengan key = id_produk, value = qty (integer)
    $_SESSION['cart'][$id_produk] = $qty_cart + $jumlah;
    $total_cart = array_sum(array_map('intval', $_SESSION['cart']));
    echo json_encode([
        'status'     => 'ok',
        'pesan'      => htmlspecialchars($prod['nama_produk']) . ' berhasil ditambahkan ke keranjang.',
        'total_cart' => $total_cart,
    ]);
    exit;
}
// ── Aksi tidak dikenal ──
echo json_encode(['status' => 'error', 'pesan' => 'Aksi tidak dikenal.']);
exit;
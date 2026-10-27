<?php
// cart.php — Keranjang Belanja (Muslimah Bakery)
// Format session: $_SESSION['cart'][$id_produk] = (int) qty

if (session_status() === PHP_SESSION_NONE) session_start();
include 'koneksi.php';

// Redirect admin ke dashboard, tidak boleh akses halaman user
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: admin.php");
    exit;
}

// Redirect jika belum login
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// FIX: CSRF token — dibuat sekali per sesi, dipakai untuk validasi aksi add/reduce/remove
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ── Bersihkan entry lama yang formatnya salah (array, bukan integer) ──
foreach ($_SESSION['cart'] as $k => $v) {
    if (!is_numeric($v)) unset($_SESSION['cart'][$k]);
}

// ── Ambil semua produk dari database ──
$products = [];
$q = mysqli_query($conn, "SELECT * FROM produk");
if ($q) {
    while ($p = mysqli_fetch_assoc($q)) {
        $products[(int)$p['id_produk']] = [
            'nama'  => $p['nama_produk'],
            'harga' => (int)$p['harga'],
            'img'   => $p['gambar'],
            'stok'  => (int)$p['stok'],
        ];
    }
}

// FIX: bersihkan entry cart yang produknya sudah tidak ada di database (misal dihapus admin)
// supaya statistik "Jenis Produk" / "Total Item" tidak salah hitung.
foreach ($_SESSION['cart'] as $id_cart => $qty_cart_stale) {
    if (!isset($products[(int)$id_cart])) {
        unset($_SESSION['cart'][$id_cart]);
    }
}

// ── Ambil & bersihkan pesan flash (misal notifikasi stok habis) ──
$flash_msg = $_SESSION['cart_flash'] ?? null;
unset($_SESSION['cart_flash']);

// ── Aksi keranjang ──
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];

    // FIX: validasi CSRF token sebelum memproses aksi yang mengubah data
    $token_valid = isset($_GET['token']) && hash_equals($csrf_token, $_GET['token']);

    if ($token_valid && $_GET['action'] === 'add') {
        // Cek stok langsung dari DB
        $stmt = mysqli_prepare($conn, "SELECT stok FROM produk WHERE id_produk = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $res      = mysqli_stmt_get_result($stmt);
        $row      = mysqli_fetch_assoc($res);
        $stok_db  = $row ? (int)$row['stok'] : 0;
        mysqli_stmt_close($stmt);

        $qty_cart = (int)($_SESSION['cart'][$id] ?? 0);
        if ($stok_db > 0 && $qty_cart < $stok_db) {
            $_SESSION['cart'][$id] = $qty_cart + 1;
        } else {
            // FIX: kasih tahu user kenapa qty tidak bertambah
            $_SESSION['cart_flash'] = 'Stok produk ini tidak mencukupi untuk ditambah lagi.';
        }

    } elseif ($token_valid && $_GET['action'] === 'reduce') {
        if (isset($_SESSION['cart'][$id])) {
            if ((int)$_SESSION['cart'][$id] > 1) {
                $_SESSION['cart'][$id]--;
            } else {
                unset($_SESSION['cart'][$id]);
            }
        }

    } elseif ($token_valid && $_GET['action'] === 'remove') {
        unset($_SESSION['cart'][$id]);
    }

    header("Location: cart.php");
    exit;
}

// ── Hitung stats ──
$total_jenis     = count($_SESSION['cart']);
$total_item_cart = array_sum(array_map('intval', $_SESSION['cart']));
$nama_user       = $_SESSION['user'] ?? 'Pelanggan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja — Musimah Bakery</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playball&family=Plus+Jakarta+Sans:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary  : #1e3a8a;
            --primary-l: #3b82f6;
            --dark     : #0f172a;
            --bg       : #f8fafc;
            --white    : #ffffff;
            --border   : #e2e8f0;
            --muted    : #64748b;
            --text     : #1e293b;
            --accent   : #ef4444;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); line-height:1.6; }

        /* HEADER */
        header {
            background:var(--dark); padding:1rem 5%;
            display:flex; justify-content:space-between; align-items:center;
            position:sticky; top:0; z-index:100;
            box-shadow:0 4px 6px -1px rgba(0,0,0,0.15);
        }
        .logo { font-family:'Playball',cursive; color:#fff; font-size:1.5rem; text-decoration:none; }
        .header-right { display:flex; align-items:center; gap:12px; }

        /* USER CAPSULE */
        .user-capsule {
            display:flex; align-items:center; gap:8px;
            background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.15);
            border-radius:50px; padding:6px 14px 6px 10px;
        }
        .user-capsule span { font-size:13px; font-weight:600; color:#fff; }
        .btn-logout {
            color:#94a3b8; font-size:14px; text-decoration:none;
            border-left:1px solid rgba(255,255,255,.15); padding-left:10px;
            transition:.2s;
        }
        .btn-logout:hover { color:#ef4444; }

        .btn-back-nav {
            display:flex; align-items:center; gap:8px; color:#cbd5e1;
            text-decoration:none; font-size:13px; font-weight:500;
            padding:8px 16px; border:1px solid rgba(255,255,255,.2);
            border-radius:8px; transition:.3s;
        }
        .btn-back-nav:hover { color:#fff; background:rgba(255,255,255,.08); }

        /* WELCOME BANNER */
        .welcome-banner {
            background: linear-gradient(135deg, var(--dark) 0%, #1e3a8a 100%);
            padding: 40px 5%;
            display: flex; align-items: center; justify-content: space-between;
            gap: 20px; flex-wrap: wrap;
        }
        .welcome-text h2 {
            font-family:'Plus Jakarta Sans',sans-serif;
            font-size: 1.6rem; font-weight:700; color:#fff;
            margin-bottom: 6px;
        }
        .welcome-text p { font-size:14px; color:#94a3b8; }
        .welcome-text .username-highlight { color: var(--primary-l); font-weight:700; }
        .welcome-icon {
            width:60px; height:60px; background:rgba(59,130,246,.15);
            border:1px solid rgba(59,130,246,.3);
            border-radius:16px; display:flex; align-items:center; justify-content:center;
            font-size:24px; color:var(--primary-l); flex-shrink:0;
        }
        .welcome-stats { display:flex; gap:24px; flex-wrap:wrap; }
        .welcome-stat {
            text-align:center;
            background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
            border-radius:12px; padding:12px 20px;
        }
        .welcome-stat .stat-num { font-size:1.4rem; font-weight:700; color:#fff; }
        .welcome-stat .stat-label { font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; margin-top:2px; }

        /* CONTAINER */
        .container { max-width:1000px; margin:32px auto; padding:0 1.5rem 60px; }

        /* FLASH MESSAGE */
        .flash-msg {
            max-width:1000px; margin:20px auto 0; padding:13px 20px;
            background:#fff7ed; border:1.5px solid #fed7aa; color:#c2410c;
            border-radius:10px; font-size:14px; font-weight:600;
            display:flex; align-items:center; gap:10px;
        }

        /* CART CARD */
        .cart-card {
            background:var(--white); border:1px solid var(--border);
            border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.05); overflow:hidden;
        }
        .cart-header {
            padding:20px 28px; border-bottom:1px solid var(--border);
            display:flex; align-items:center; justify-content:space-between;
            background:var(--dark);
        }
        .cart-header-left { display:flex; align-items:center; gap:12px; }
        .cart-header h2 { font-size:16px; font-weight:700; color:#fff; }
        .cart-count {
            background:var(--primary); color:#fff;
            font-size:11px; font-weight:700;
            padding:3px 10px; border-radius:20px;
        }

        /* TABLE */
        .cart-table-wrapper { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:600px; }
        th {
            background:#f8fafc; padding:12px 20px;
            text-align:left; font-size:11px;
            text-transform:uppercase; letter-spacing:.8px;
            color:var(--muted); border-bottom:1px solid var(--border); font-weight:600;
        }
        td { padding:16px 20px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }
        tbody tr:hover { background:#f8fafc; }

        .product-cell { display:flex; align-items:center; gap:14px; }
        .product-cell img {
            width:56px; height:56px; object-fit:cover;
            border-radius:10px; border:1px solid var(--border);
        }
        .product-name { font-weight:600; font-size:14px; color:var(--dark); }

        /* QTY */
        .qty-box {
            display:flex; align-items:center;
            border:1px solid var(--border); border-radius:8px;
            overflow:hidden; width:fit-content;
        }
        .qty-btn {
            padding:6px 12px; background:#f8fafc; color:var(--dark);
            text-decoration:none; font-weight:700; font-size:12px; transition:.2s;
            display:flex; align-items:center;
        }
        .qty-btn:hover { background:var(--primary); color:#fff; }
        .qty-btn.disabled { opacity:.3; cursor:not-allowed; pointer-events:none; }
        .qty-val {
            padding:0 14px; font-weight:700; font-size:14px;
            min-width:36px; text-align:center; color:var(--dark);
        }

        /* FOOTER */
        .cart-footer {
            padding:24px 28px; background:#f8fafc; border-top:1px solid var(--border);
            display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;
        }
        .total-info p { font-size:12px; color:var(--muted); margin-bottom:4px; }
        .total-info h2 { font-size:1.6rem; font-weight:800; color:var(--dark); }

        .btn-checkout {
            background:var(--dark); color:#fff; padding:13px 28px;
            border-radius:10px; text-decoration:none; font-weight:600; font-size:14px;
            display:inline-flex; align-items:center; gap:10px;
            transition:.3s; border:none; cursor:pointer;
        }
        .btn-checkout:hover { background:var(--primary); transform:translateY(-2px); box-shadow:0 8px 20px rgba(30,58,138,.3); }

        /* EMPTY */
        .empty-cart { text-align:center; padding:60px 20px; }
        .empty-cart i { font-size:4rem; color:#e2e8f0; margin-bottom:16px; display:block; }
        .empty-cart h3 { font-size:18px; margin-bottom:8px; color:var(--dark); }
        .empty-cart p { color:var(--muted); margin-bottom:24px; }

        /* HAPUS */
        .btn-remove { color:#cbd5e1; font-size:15px; transition:.2s; }
        .btn-remove:hover { color:var(--accent); }

        @media (max-width:640px) {
            .welcome-banner { padding:28px 5%; }
            .welcome-text h2 { font-size:1.3rem; }
            .container { margin:20px auto; padding:0 1rem 40px; }
            .cart-footer { flex-direction:column; text-align:center; }
            .btn-checkout { width:100%; justify-content:center; }
        }
    </style>
</head>
<body>

<header>
    <a href="index.php" class="logo">Musimah Bakery</a>
    <div class="header-right">
        <?php if (isset($_SESSION['user'])): ?>
        <div class="user-capsule">
            <i class="fa-solid fa-circle-user" style="color:#93c5fd;font-size:16px;"></i>
            <span><?= htmlspecialchars($_SESSION['user']) ?></span>
            <a href="logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
        <?php endif; ?>
        <a href="index.php#menu" class="btn-back-nav">
            <i class="fa-solid fa-arrow-left"></i> Lanjut Belanja
        </a>
    </div>
</header>

<!-- WELCOME BANNER -->
<div class="welcome-banner">
    <div style="display:flex; align-items:center; gap:20px;">
        <div class="welcome-icon">
            <i class="fa-solid fa-basket-shopping"></i>
        </div>
        <div class="welcome-text">
            <h2>Selamat Datang, <span class="username-highlight"><?= htmlspecialchars($nama_user) ?></span>!</h2>
            <p>Periksa pesanan Anda sebelum melanjutkan ke pembayaran</p>
        </div>
    </div>
    <div class="welcome-stats">
        <div class="welcome-stat">
            <div class="stat-num"><?= $total_jenis ?></div>
            <div class="stat-label">Jenis Produk</div>
        </div>
        <div class="welcome-stat">
            <div class="stat-num"><?= $total_item_cart ?></div>
            <div class="stat-label">Total Item</div>
        </div>
    </div>
</div>

<?php if ($flash_msg): ?>
<div class="flash-msg">
    <i class="fa-solid fa-circle-exclamation"></i>
    <span><?= htmlspecialchars($flash_msg) ?></span>
</div>
<?php endif; ?>

<div class="container">
    <div class="cart-card">
        <div class="cart-header">
            <div class="cart-header-left">
                <i class="fa-solid fa-cart-shopping" style="color:#93c5fd;font-size:18px;"></i>
                <h2>Keranjang Belanja</h2>
            </div>
            <?php if ($total_item_cart > 0): ?>
            <span class="cart-count"><?= $total_item_cart ?> item</span>
            <?php endif; ?>
        </div>

        <?php if (empty($_SESSION['cart'])): ?>
        <div class="empty-cart">
            <i class="fa-solid fa-basket-shopping"></i>
            <h3>Keranjang Masih Kosong</h3>
            <p>Yuk, pilih kue favoritmu dulu!</p>
            <a href="index.php#menu" class="btn-checkout">
                <i class="fa-solid fa-cake-candles"></i> Lihat Menu
            </a>
        </div>
        <?php else: ?>
        <div class="cart-table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Harga</th>
                        <th>Jumlah</th>
                        <th>Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_bayar = 0;
                    foreach ($_SESSION['cart'] as $id => $qty):
                        $id  = (int)$id;
                        $qty = (int)$qty;
                        if (!isset($products[$id])) continue;
                        $item = $products[$id];
                        $sub  = $item['harga'] * $qty;
                        $total_bayar += $sub;
                        $stok_tersedia = (int)$item['stok'];
                    ?>
                    <tr>
                        <td>
                            <div class="product-cell">
                                <img src="<?= htmlspecialchars($item['img']) ?>"
                                     alt="<?= htmlspecialchars($item['nama']) ?>"
                                     onerror="this.src='https://via.placeholder.com/56x56?text=Kue'">
                                <div>
                                    <div class="product-name"><?= htmlspecialchars($item['nama']) ?></div>
                                    <?php if ($stok_tersedia <= 10): ?>
                                    <small style="color:#f59e0b;font-size:11px;">Sisa <?= $stok_tersedia ?> stok</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td style="color:var(--muted);font-size:14px;">Rp <?= number_format($item['harga'],0,',','.') ?></td>
                        <td>
                            <div class="qty-box">
                                <a href="?action=reduce&id=<?= $id ?>&token=<?= urlencode($csrf_token) ?>" class="qty-btn">
                                    <i class="fa-solid fa-minus"></i>
                                </a>
                                <span class="qty-val"><?= $qty ?></span>
                                <?php if ($qty >= $stok_tersedia): ?>
                                <span class="qty-btn disabled"><i class="fa-solid fa-plus"></i></span>
                                <?php else: ?>
                                <a href="?action=add&id=<?= $id ?>&token=<?= urlencode($csrf_token) ?>" class="qty-btn">
                                    <i class="fa-solid fa-plus"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="font-weight:700;color:var(--primary);">
                            Rp <?= number_format($sub,0,',','.') ?>
                        </td>
                        <td>
                            <a href="?action=remove&id=<?= $id ?>&token=<?= urlencode($csrf_token) ?>" class="btn-remove" title="Hapus">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="cart-footer">
            <div class="total-info">
                <p>Total Pembayaran</p>
                <h2>Rp <?= number_format($total_bayar,0,',','.') ?></h2>
            </div>
            <a href="checkout.php" class="btn-checkout">
                <i class="fa-solid fa-paper-plane"></i> Lanjut Checkout
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
<?php
/**
 * produk.php — Muslimah Bakery
 * + Badge Best Seller
 * + Pop-up deskripsi & informasi gizi per produk
 */
include 'koneksi.php';
session_start();

// FIX: CSRF token untuk endpoint tambah-keranjang (proses_pesanan.php)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$kategori_filter = isset($_GET['kategori']) ? $_GET['kategori'] : 'semua';

$cols_result = mysqli_query($conn, "SHOW COLUMNS FROM produk");
$existing_cols = [];
if ($cols_result) {
    while ($col = mysqli_fetch_assoc($cols_result)) {
        $existing_cols[] = $col['Field'];
    }
}
$has_bestseller   = in_array('is_bestseller', $existing_cols);
$has_deskripsi    = in_array('deskripsi',     $existing_cols);
$has_kalori       = in_array('kalori',        $existing_cols);
$has_protein      = in_array('protein',       $existing_cols);
$has_lemak        = in_array('lemak',         $existing_cols);
$has_karbohidrat  = in_array('karbohidrat',   $existing_cols);

$order = $has_bestseller ? "ORDER BY is_bestseller DESC, id_produk DESC" : "ORDER BY id_produk DESC";

if ($kategori_filter === 'semua' || empty($kategori_filter)) {
    $query = mysqli_query($conn, "SELECT * FROM produk $order");
} else {
    $kat = mysqli_real_escape_string($conn, $kategori_filter);
    $query = mysqli_query($conn, "SELECT * FROM produk WHERE kategori = '$kat' $order");
}

$pesan  = $_SESSION['pesan_error']  ?? '';
$sukses = $_SESSION['pesan_sukses'] ?? '';
unset($_SESSION['pesan_error'], $_SESSION['pesan_sukses']);

// FIX: hitung total item keranjang dengan cara yang sama & lebih aman seperti di index.php
$total_items = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $v) {
        if (is_array($v) && isset($v['jumlah'])) {
            $total_items += (int)$v['jumlah'];
        } elseif (is_numeric($v)) {
            $total_items += (int)$v;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Produk Kami — Musimah Bakery</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --navy-deep:  #060d1f;
    --navy-dark:  #0b1735;
    --navy-mid:   #112255;
    --navy-light: #1a3370;
    --gold:       #c9a96e;
    --gold-light: #e8c98a;
    --gold-pale:  #f5e6cb;
    --cream:      #fdf8f0;
    --white:      #ffffff;
    --text-muted: #8a9bc5;
    --border:     rgba(201,169,110,0.2);
}
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; }
body { font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--navy-deep); overflow-x: hidden; min-height: 100vh; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
@keyframes modalIn { from { opacity: 0; transform: scale(0.93) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
header {
    position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
    width: 92%; max-width: 1140px; height: 68px; background: rgba(6,13,31,0.9);
    backdrop-filter: blur(20px); border: 1px solid rgba(201,169,110,0.15); border-radius: 60px;
    display: flex; justify-content: space-between; align-items: center; padding: 0 28px; z-index: 1000;
    box-shadow: 0 8px 40px rgba(0,0,0,0.35);
}
.header-left { display: flex; align-items: center; gap: 16px; }
.logo { text-decoration: none; font-family: 'Cormorant Garamond', serif; font-size: 1.9rem; font-weight: 400; font-style: italic; color: var(--white); letter-spacing: 1px; line-height: 1; padding-top: 4px; }
.desktop-nav ul { display: flex; list-style: none; gap: 4px; align-items: center; }
.desktop-nav ul li a { text-decoration: none; color: rgba(255,255,255,0.7); font-weight: 400; font-size: 0.875rem; padding: 6px 14px; border-radius: 30px; transition: all 0.25s; }
.desktop-nav ul li a:hover, .desktop-nav ul li a.active { color: var(--white); background: rgba(255,255,255,0.09); }
.nav-has-dropdown { position: relative; }
.dropdown {
    position: absolute; top: calc(100% + 10px); left: 0; background: var(--navy-dark);
    border: 1px solid rgba(201,169,110,0.18); border-radius: 16px; min-width: 180px;
    list-style: none; padding: 8px; box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    opacity: 0; visibility: hidden; transform: translateY(6px);
    transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s;
    pointer-events: none; z-index: 999;
}
.nav-has-dropdown:hover > .dropdown, .nav-has-dropdown:focus-within > .dropdown { opacity: 1; visibility: visible; transform: translateY(0); pointer-events: auto; }
.dropdown li a { display: block; padding: 9px 16px; border-radius: 10px; color: rgba(255,255,255,0.7) !important; font-size: 0.875rem; background: none !important; }
.dropdown li a:hover { background: rgba(201,169,110,0.12) !important; color: var(--gold) !important; }
.header-right { display: flex; align-items: center; gap: 10px; }
.cart-link { position: relative; color: var(--white); text-decoration: none; display: flex; align-items: center; width: 40px; height: 40px; justify-content: center; border-radius: 50%; border: 1px solid rgba(255,255,255,0.15); transition: all 0.25s; }
.cart-link:hover { border-color: var(--gold); background: rgba(201,169,110,0.1); }
.cart-badge { position: absolute; top: -4px; right: -4px; background: var(--gold); color: var(--navy-deep); font-size: 0.6rem; font-weight: 700; min-width: 16px; height: 16px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.user-capsule { display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12); border-radius: 40px; padding: 6px 14px 6px 10px; }
.user-info { display: flex; align-items: center; gap: 7px; color: rgba(255,255,255,0.85); font-size: 0.85rem; white-space: nowrap; }
.btn-logout-small { color: rgba(255,255,255,0.4); font-size: 0.85rem; text-decoration: none; display: flex; align-items: center; border-left: 1px solid rgba(255,255,255,0.12); padding-left: 8px; transition: 0.2s; }
.btn-logout-small:hover { color: #e11d48; }
.btn-masuk { background: var(--gold); color: var(--navy-deep); text-decoration: none; padding: 9px 22px; border-radius: 40px; font-weight: 600; font-size: 0.85rem; transition: all 0.25s; }
.btn-masuk:hover { background: var(--gold-light); transform: translateY(-1px); }
.hamburger { display: none; flex-direction: column; gap: 5px; cursor: pointer; padding: 4px; }
.hamburger span { width: 20px; height: 1.5px; background: var(--white); border-radius: 2px; }
.sidebar-overlay { position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1999;display:none;backdrop-filter:blur(4px); }
.mobile-sidebar { position:fixed;top:0;left:-100%;width:290px;height:100%; background:var(--navy-dark);z-index:2000;transition:0.4s cubic-bezier(.4,0,.2,1); padding:36px 28px;overflow-y:auto;border-right:1px solid rgba(201,169,110,0.12); }
.sidebar-logo { font-family:'Cormorant Garamond',serif;font-size:2rem;font-weight:400;font-style:italic;color:var(--white); }
.sidebar-nav { list-style:none;margin-top:30px; }
.sidebar-nav li { border-bottom:1px solid rgba(255,255,255,0.06); }
.sidebar-nav li a { display:flex;align-items:center;gap:13px;padding:14px 0; text-decoration:none;color:rgba(255,255,255,0.8);font-size:0.95rem;transition:0.25s; }
.sidebar-nav li a:hover { color:var(--gold); }
.sidebar-nav li a i { color:var(--gold);width:18px;text-align:center;font-size:0.85rem; }
.accordion-btn { display:flex;align-items:center;gap:13px;padding:14px 0;color:rgba(255,255,255,0.8);font-size:0.95rem;cursor:pointer; }
.accordion-btn i:first-child { color:var(--gold);width:18px;text-align:center;font-size:0.85rem; }
.mobile-sub-menu { max-height:0;overflow:hidden;list-style:none;background:rgba(0,0,0,0.2);border-radius:10px;transition:0.4s ease; }
.mobile-sub-menu li a { padding-left:34px;font-size:0.875rem;color:rgba(255,255,255,0.5); }
.mobile-sub-menu li a:hover { color:var(--gold); }
.page-banner { background: var(--navy-deep); padding: 155px 0 80px; position: relative; overflow: hidden; text-align: center; }
.page-banner::before { content: 'Menu'; position: absolute; left: 50%; top: 50%; transform: translate(-50%,-50%); font-family: 'Cormorant Garamond', serif; font-size: 280px; font-weight: 300; font-style: italic; color: rgba(255,255,255,0.022); white-space: nowrap; pointer-events: none; user-select: none; line-height: 1; }
.page-banner::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(201,169,110,0.3), transparent); }
.banner-eyebrow { font-size: 0.63rem; letter-spacing: 6px; color: var(--gold); text-transform: uppercase; display: block; margin-bottom: 16px; opacity: 0; animation: fadeUp 0.7s ease 0.1s forwards; }
.banner-title { font-family: 'Cormorant Garamond', serif; font-size: clamp(3.5rem, 7vw, 6rem); font-weight: 300; font-style: italic; color: var(--white); line-height: 1; letter-spacing: -1px; opacity: 0; animation: fadeUp 0.9s ease 0.25s forwards; }
.banner-sub { font-size: 0.875rem; color: rgba(255,255,255,0.38); margin-top: 12px; opacity: 0; animation: fadeUp 0.8s ease 0.4s forwards; }
.banner-ornament { display: flex; align-items: center; justify-content: center; gap: 14px; margin: 26px auto 0; opacity: 0; animation: fadeUp 0.7s ease 0.5s forwards; }
.orn-line { width: 50px; height: 1px; }
.orn-line.l { background: linear-gradient(90deg, transparent, var(--gold)); }
.orn-line.r { background: linear-gradient(90deg, var(--gold), transparent); }
.orn-diamond { width: 5px; height: 5px; background: var(--gold); transform: rotate(45deg); opacity: 0.7; }
.alert-wrap { max-width: 1140px; margin: 30px auto 0; padding: 0 5%; }
.alert { padding: 14px 20px; border-radius: 14px; font-size: 0.875rem; font-weight: 500; display: flex; align-items: center; gap: 10px; }
.alert-error   { background: #fee2e2; color: #b91c1c; border-left: 4px solid #ef4444; }
.alert-success { background: #dcfce7; color: #15803d; border-left: 4px solid #22c55e; }
.filter-section { padding: 50px 0 20px; display: flex; justify-content: center; opacity: 0; animation: fadeUp 0.7s ease 0.55s forwards; }
.filter-tabs { display: flex; gap: 8px; align-items: center; background: var(--white); border: 1px solid rgba(11,23,53,0.1); border-radius: 60px; padding: 6px; box-shadow: 0 4px 24px rgba(11,23,53,0.07); flex-wrap: wrap; justify-content: center; }
.filter-tab { text-decoration: none; padding: 9px 22px; border-radius: 40px; font-size: 0.82rem; font-weight: 500; color: var(--text-muted); transition: all 0.25s; white-space: nowrap; }
.filter-tab:hover { color: var(--navy-deep); background: rgba(11,23,53,0.05); }
.filter-tab.active { background: var(--navy-deep); color: var(--white); box-shadow: 0 4px 16px rgba(6,13,31,0.2); }
.produk-wrap { max-width: 1140px; margin: 0 auto; padding: 40px 5% 100px; }
.produk-count { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 30px; letter-spacing: 0.5px; }
.produk-count span { color: var(--navy-mid); font-weight: 600; }
.produk-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 28px; }
.card { background: var(--white); border-radius: 24px; overflow: hidden; border: 1px solid rgba(11,23,53,0.08); box-shadow: 0 4px 24px rgba(11,23,53,0.05); display: flex; flex-direction: column; transition: all 0.4s cubic-bezier(.22,1,.36,1); position: relative; opacity: 0; transform: translateY(28px); }
.card.visible { opacity: 1; transform: translateY(0); }
.card:hover { transform: translateY(-12px); box-shadow: 0 28px 70px rgba(11,23,53,0.14); }
.badge-bestseller { position: absolute; top: 14px; left: 14px; z-index: 4; background: linear-gradient(135deg, #c9a96e, #e8c98a); color: var(--navy-deep); font-size: 0.6rem; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; padding: 5px 11px; border-radius: 30px; display: flex; align-items: center; gap: 5px; box-shadow: 0 4px 16px rgba(201,169,110,0.45); }
.badge-bestseller i { font-size: 0.6rem; }
.badge-habis { position: absolute; top: 14px; left: 14px; z-index: 3; background: rgba(6,13,31,0.85); backdrop-filter: blur(8px); color: #ef4444; font-size: 0.65rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; padding: 5px 12px; border-radius: 30px; border: 1px solid rgba(239,68,68,0.3); }
.card.has-bestseller .badge-habis { top: 46px; }
.badge-kategori { position: absolute; top: 14px; right: 14px; z-index: 3; background: rgba(6,13,31,0.75); backdrop-filter: blur(8px); color: var(--gold); font-size: 0.62rem; letter-spacing: 2px; text-transform: uppercase; padding: 5px 12px; border-radius: 30px; border: 1px solid rgba(201,169,110,0.2); }
.btn-info { position: absolute; bottom: 14px; right: 14px; z-index: 4; width: 32px; height: 32px; border-radius: 50%; background: rgba(6,13,31,0.75); backdrop-filter: blur(8px); border: 1px solid rgba(201,169,110,0.25); color: var(--gold); font-size: 0.75rem; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.25s; opacity: 0; transform: translateY(4px); }
.card:hover .btn-info { opacity: 1; transform: translateY(0); }
.btn-info:hover { background: var(--gold); color: var(--navy-deep); border-color: var(--gold); }
.card-img-wrap { position: relative; height: 230px; overflow: hidden; }
.card-img-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.6s cubic-bezier(.22,1,.36,1); }
.card:hover .card-img-wrap img { transform: scale(1.08); }
.card-img-wrap.out-of-stock::after { content: ''; position: absolute; inset: 0; background: rgba(6,13,31,0.35); }
.card-body { padding: 22px 22px 0; flex-grow: 1; display: flex; flex-direction: column; gap: 6px; }
.card-title { font-family: 'Cormorant Garamond', serif; font-size: 1.3rem; font-weight: 400; color: var(--navy-deep); line-height: 1.2; }
.card-price { font-size: 1.05rem; font-weight: 600; color: var(--navy-mid); }
.stok-info { font-size: 0.75rem; display: flex; align-items: center; gap: 6px; margin-top: 4px; }
.stok-info.ok    { color: #16a34a; }
.stok-info.warn  { color: #b45309; font-weight: 600; }
.stok-info.habis { color: #dc2626; font-weight: 700; }
.card-divider { height: 1px; background: rgba(11,23,53,0.07); margin: 16px 22px; }
.card-footer { padding: 0 22px 22px; }
.btn-beli { width: 100%; padding: 12px; border-radius: 50px; border: none; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.25s; font-family: 'DM Sans', sans-serif; letter-spacing: 0.3px; }
.btn-beli.aktif { background: var(--navy-deep); color: var(--white); }
.btn-beli.aktif:hover { background: var(--navy-mid); transform: translateY(-1px); box-shadow: 0 8px 24px rgba(6,13,31,0.2); }
.btn-beli.nonaktif { background: rgba(11,23,53,0.06); color: #94a3b8; cursor: not-allowed; pointer-events: none; }
.empty-state { text-align: center; padding: 80px 20px; grid-column: 1 / -1; }
.empty-state i { font-size: 3.5rem; color: rgba(11,23,53,0.12); display: block; margin-bottom: 20px; }
.empty-state h3 { font-family: 'Cormorant Garamond', serif; font-size: 1.8rem; font-weight: 300; color: var(--navy-mid); margin-bottom: 10px; }
.empty-state p { color: var(--text-muted); font-size: 0.9rem; }
#toast { position: fixed; bottom: 28px; right: 28px; background: var(--navy-deep); color: var(--white); padding: 14px 22px; border-radius: 14px; font-size: 0.875rem; font-weight: 500; border-left: 3px solid var(--gold); opacity: 0; transform: translateY(10px); transition: all 0.3s; z-index: 9999; max-width: 300px; box-shadow: 0 16px 50px rgba(0,0,0,0.3); pointer-events: none; }
#toast.show { opacity: 1; transform: translateY(0); pointer-events: auto; }
#toast.error { border-left-color: #ef4444; }
.modal-overlay { position: fixed; inset: 0; z-index: 8000; background: rgba(6,13,31,0.75); backdrop-filter: blur(10px); display: flex; align-items: center; justify-content: center; padding: 20px; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
.modal-overlay.open { opacity: 1; pointer-events: auto; }
.modal { background: var(--white); border-radius: 28px; max-width: 760px; width: 100%; max-height: 90vh; overflow-y: auto; position: relative; box-shadow: 0 40px 100px rgba(0,0,0,0.4); transform: scale(0.93) translateY(20px); transition: transform 0.35s cubic-bezier(.22,1,.36,1); }
.modal-overlay.open .modal { transform: scale(1) translateY(0); }
.modal-close { position: sticky; top: 16px; float: right; margin: 16px 16px 0 0; z-index: 10; width: 36px; height: 36px; border-radius: 50%; background: rgba(11,23,53,0.08); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--navy-mid); font-size: 0.9rem; transition: all 0.2s; }
.modal-close:hover { background: var(--navy-deep); color: var(--white); }
.modal-img { width: 100%; height: 280px; object-fit: cover; border-radius: 24px 24px 0 0; display: block; }
.modal-body { padding: 28px 32px 32px; }
.modal-eyebrow { font-size: 0.63rem; letter-spacing: 5px; color: var(--gold); text-transform: uppercase; margin-bottom: 8px; display: block; }
.modal-title { font-family: 'Cormorant Garamond', serif; font-size: 2.1rem; font-weight: 300; color: var(--navy-deep); line-height: 1.1; margin-bottom: 6px; }
.modal-price { font-size: 1.2rem; font-weight: 700; color: var(--navy-mid); margin-bottom: 16px; }
.modal-badges { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
.modal-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; }
.modal-badge.bs { background: linear-gradient(135deg,#c9a96e,#e8c98a); color: var(--navy-deep); }
.modal-badge.kat { background: rgba(11,23,53,0.08); color: var(--navy-mid); }
.modal-badge.stok-ok { background: #dcfce7; color: #15803d; }
.modal-badge.stok-warn { background: #fef3c7; color: #92400e; }
.modal-badge.stok-habis { background: #fee2e2; color: #b91c1c; }
.modal-divider { height: 1px; background: rgba(11,23,53,0.08); margin: 20px 0; }
.modal-desc-title { font-size: 0.68rem; letter-spacing: 3px; text-transform: uppercase; color: var(--gold); margin-bottom: 10px; }
.modal-desc { font-size: 0.9rem; color: #475569; line-height: 1.75; }
.modal-desc-empty { font-size: 0.875rem; color: rgba(11,23,53,0.3); font-style: italic; }
.gizi-section { margin-top: 24px; }
.gizi-title { font-size: 0.68rem; letter-spacing: 3px; text-transform: uppercase; color: var(--gold); margin-bottom: 14px; }
.gizi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
.gizi-item { background: linear-gradient(135deg, var(--navy-deep), var(--navy-mid)); border-radius: 16px; padding: 16px 12px; text-align: center; position: relative; overflow: hidden; }
.gizi-item::before { content: ''; position: absolute; top: -20px; right: -20px; width: 60px; height: 60px; border-radius: 50%; background: rgba(201,169,110,0.08); }
.gizi-value { font-family: 'Cormorant Garamond', serif; font-size: 1.8rem; font-weight: 400; color: var(--gold); display: block; line-height: 1; }
.gizi-unit { font-size: 0.65rem; color: rgba(255,255,255,0.4); display: block; margin-bottom: 4px; }
.gizi-label { font-size: 0.7rem; color: rgba(255,255,255,0.6); letter-spacing: 1px; text-transform: uppercase; }
.gizi-note { font-size: 0.72rem; color: rgba(11,23,53,0.35); margin-top: 10px; font-style: italic; }
.gizi-empty { font-size: 0.875rem; color: rgba(11,23,53,0.3); font-style: italic; }
.modal-footer { display: flex; gap: 12px; margin-top: 24px; flex-wrap: wrap; }
.modal-btn-beli { flex: 1; min-width: 160px; padding: 14px 24px; border-radius: 50px; border: none; font-size: 0.875rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.25s; font-family: 'DM Sans', sans-serif; background: var(--navy-deep); color: var(--white); }
.modal-btn-beli:hover { background: var(--navy-mid); transform: translateY(-1px); box-shadow: 0 8px 24px rgba(6,13,31,0.2); }
.modal-btn-beli.nonaktif { background: rgba(11,23,53,0.06); color: #94a3b8; cursor: not-allowed; pointer-events: none; }
.modal-btn-wa { padding: 14px 24px; border-radius: 50px; border: 1.5px solid rgba(11,23,53,0.15); font-size: 0.875rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.25s; font-family: 'DM Sans', sans-serif; background: transparent; color: var(--navy-mid); text-decoration: none; }
.modal-btn-wa:hover { background: #dcfce7; border-color: #16a34a; color: #16a34a; }
footer { background: var(--navy-deep); border-radius: 48px 48px 0 0; overflow: hidden; }
.footer-inner { width: 90%; max-width: 1140px; margin: auto; padding: 70px 0 44px; }
.footer-grid { display: flex; justify-content: center; margin-bottom: 50px; }
.footer-brand { text-align: center; max-width: 420px; }
.footer-logo { font-family: 'Cormorant Garamond', serif; font-size: 2.4rem; font-weight: 300; font-style: italic; color: var(--white); display: block; margin-bottom: 14px; }
.footer-desc { color: rgba(255,255,255,0.4); font-size: 0.875rem; line-height: 1.8; margin: 0 auto; }
.footer-bottom { border-top: 1px solid rgba(255,255,255,0.06); padding: 22px 0; text-align: center; color: rgba(255,255,255,0.22); font-size: 0.8rem; }
@media (max-width: 960px) {
    header { width: 95%; top: 12px; padding: 0 18px; height: 62px; }
    .desktop-nav { display: none; }
    .hamburger { display: flex; }
    .gizi-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
    .produk-grid { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 18px; }
    .filter-tabs { gap: 4px; }
    .filter-tab { padding: 8px 16px; font-size: 0.78rem; }
    #toast { left: 16px; right: 16px; bottom: 16px; }
    .modal-body { padding: 20px 20px 24px; }
    .modal-title { font-size: 1.7rem; }
    .gizi-grid { grid-template-columns: repeat(2, 1fr); }
    .modal-footer { flex-direction: column; }
}

.sound-player { position:fixed;bottom:30px;right:30px;z-index:900;display:flex;flex-direction:column;align-items:flex-end;gap:12px; }
.sound-fab { width:54px;height:54px;background:var(--gold);border-radius:50%;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 28px rgba(201,169,110,0.5);transition:all 0.3s cubic-bezier(.22,1,.36,1);color:var(--navy-deep);font-size:1.2rem;position:relative;outline:none; }
.sound-fab:hover { transform:scale(1.1);box-shadow:0 12px 40px rgba(201,169,110,0.65); }
.sound-fab.panel-open { background:var(--navy-mid);color:var(--gold);border:1.5px solid rgba(201,169,110,0.4); }
.sound-fab.playing::before,.sound-fab.playing::after { content:'';position:absolute;inset:-6px;border-radius:50%;border:1.5px solid rgba(201,169,110,0.4);animation:fabRing 2s ease infinite; }
.sound-fab.playing::after { animation-delay:1s; }
@keyframes fabRing { 0%{transform:scale(1);opacity:0.8}100%{transform:scale(1.75);opacity:0} }
.sound-panel { background:rgba(6,13,31,0.95);backdrop-filter:blur(24px);border:1px solid rgba(201,169,110,0.22);border-radius:22px;padding:18px 18px 16px;width:260px;box-shadow:0 24px 70px rgba(0,0,0,0.5);display:none;flex-direction:column;gap:14px; }
.sound-panel.show { display:flex;animation:panelIn 0.3s cubic-bezier(.22,1,.36,1); }
@keyframes panelIn { from{opacity:0;transform:translateY(14px) scale(0.95)}to{opacity:1;transform:translateY(0) scale(1)} }
.sp-header { display:flex;align-items:center;gap:10px; }
.sp-icon { color:var(--gold);font-size:0.9rem; }
.sp-title { font-family:'Cormorant Garamond',serif;font-size:1.1rem;font-style:italic;color:#fff;flex:1; }
.sp-status { font-size:0.58rem;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.25);white-space:nowrap; }
.sp-status.live { color:var(--gold); }
.sp-visualizer { display:flex;align-items:flex-end;gap:3px;height:32px;padding:0 2px; }
.sp-bar { flex:1;border-radius:2px;background:rgba(201,169,110,0.2);height:4px; }
.sp-visualizer.active .sp-bar { background:var(--gold); }
.sp-visualizer.active .sp-bar:nth-child(1)  { animation:vb1 0.75s ease infinite; }
.sp-visualizer.active .sp-bar:nth-child(2)  { animation:vb2 0.55s ease infinite 0.10s; }
.sp-visualizer.active .sp-bar:nth-child(3)  { animation:vb3 0.85s ease infinite 0.05s; }
.sp-visualizer.active .sp-bar:nth-child(4)  { animation:vb1 0.65s ease infinite 0.20s; }
.sp-visualizer.active .sp-bar:nth-child(5)  { animation:vb4 1.05s ease infinite; }
.sp-visualizer.active .sp-bar:nth-child(6)  { animation:vb2 0.70s ease infinite 0.15s; }
.sp-visualizer.active .sp-bar:nth-child(7)  { animation:vb3 0.90s ease infinite 0.08s; }
.sp-visualizer.active .sp-bar:nth-child(8)  { animation:vb1 0.60s ease infinite 0.25s; }
.sp-visualizer.active .sp-bar:nth-child(9)  { animation:vb4 0.80s ease infinite 0.12s; }
.sp-visualizer.active .sp-bar:nth-child(10) { animation:vb2 0.70s ease infinite 0.30s; }
@keyframes vb1 { 0%,100%{height:4px}  50%{height:26px} }
@keyframes vb2 { 0%,100%{height:11px} 50%{height:6px}  }
@keyframes vb3 { 0%,100%{height:7px}  35%{height:30px} 70%{height:14px} }
@keyframes vb4 { 0%,100%{height:18px} 50%{height:4px}  }
.sp-track { display:flex;align-items:center;gap:12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.07);border-radius:14px;padding:12px 14px; }
.sp-track-icon { width:36px;height:36px;border-radius:10px;background:rgba(201,169,110,0.12);display:flex;align-items:center;justify-content:center;color:var(--gold);font-size:1rem;flex-shrink:0; }
.sp-track-info { flex:1;min-width:0; }
.sp-track-name { font-size:0.82rem;font-weight:500;color:#fff;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.sp-track-sub { font-size:0.63rem;color:rgba(255,255,255,0.3);display:block;margin-top:2px; }
.sp-progress-wrap { display:flex;flex-direction:column;gap:5px; }
.sp-progress-bar { width:100%;height:3px;background:rgba(255,255,255,0.1);border-radius:2px;overflow:hidden;cursor:pointer;position:relative; }
.sp-progress-fill { height:100%;background:var(--gold);border-radius:2px;width:0%;transition:width 0.5s linear; }
.sp-time-row { display:flex;justify-content:space-between;font-size:0.6rem;color:rgba(255,255,255,0.25); }
.sp-controls { display:flex;align-items:center;justify-content:center;gap:16px; }
.sp-btn-play { width:44px;height:44px;border-radius:50%;background:var(--gold);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--navy-deep);font-size:1.1rem;transition:all 0.25s;outline:none;flex-shrink:0; }
.sp-btn-play:hover { background:var(--gold-light);transform:scale(1.08); }
.sp-btn-secondary { width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);cursor:pointer;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,0.5);font-size:0.8rem;transition:all 0.22s;outline:none; }
.sp-btn-secondary:hover { background:rgba(201,169,110,0.1);border-color:rgba(201,169,110,0.3);color:var(--gold); }
.sp-btn-secondary.active { color:var(--gold);border-color:rgba(201,169,110,0.4); }
.sp-volume { display:flex;align-items:center;gap:9px; }
.sp-vol-icon { color:rgba(201,169,110,0.4);font-size:0.78rem;width:14px;text-align:center;flex-shrink:0; }
.sp-vol-slider { flex:1;height:3px;appearance:none;-webkit-appearance:none;background:rgba(255,255,255,0.1);border-radius:2px;outline:none;cursor:pointer; }
.sp-vol-slider::-webkit-slider-thumb { -webkit-appearance:none;width:13px;height:13px;border-radius:50%;background:var(--gold);cursor:pointer;transition:transform 0.2s; }
.sp-vol-slider::-webkit-slider-thumb:hover { transform:scale(1.2); }
.sp-vol-val { font-size:0.65rem;color:rgba(255,255,255,0.25);min-width:28px;text-align:right; }
.sp-divider { height:1px;background:rgba(255,255,255,0.06); }
@media(max-width:480px){ .sound-player{bottom:18px;right:14px} .sound-panel{width:236px} }
</style>
</head>
<body>

<div class="sidebar-overlay" id="overlay"></div>
<div class="mobile-sidebar" id="sidebar">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <span class="sidebar-logo">Musimah</span>
        <i class="fa-solid fa-xmark" id="closeBtn" style="font-size:1.3rem;cursor:pointer;color:rgba(255,255,255,0.5);"></i>
    </div>
    <ul class="sidebar-nav">
        <li><a href="index.php" onclick="closeSidebar()"><i class="fa-solid fa-house"></i> Beranda</a></li>
        <li>
            <div class="accordion-btn" id="mobileAccordionBtn">
                <i class="fa-solid fa-utensils"></i>
                <span style="flex:1;color:rgba(255,255,255,0.8);">Menu</span>
                <i class="fa-solid fa-chevron-down" id="mobileArrow" style="color:rgba(255,255,255,0.4);font-size:0.75rem;transition:0.3s;"></i>
            </div>
            <ul id="mobileSub" class="mobile-sub-menu">
                <li><a href="produk.php?kategori=semua" onclick="closeSidebar()">Semua Kue</a></li>
                <li><a href="produk.php?kategori=dessert" onclick="closeSidebar()">Dessert</a></li>
                <li><a href="produk.php?kategori=roti" onclick="closeSidebar()">Roti</a></li>
                <li><a href="produk.php?kategori=kuekering" onclick="closeSidebar()">Kue Kering</a></li>
            </ul>
        </li>
        <li><a href="tentang.php" onclick="closeSidebar()"><i class="fa-solid fa-circle-info"></i> Tentang Kami</a></li>
        <li><a href="kontak.php" onclick="closeSidebar()"><i class="fa-solid fa-phone"></i> Kontak</a></li>
        <li style="margin-top:20px;border-top:1px solid rgba(201,169,110,0.2);padding-top:10px;">
            <a href="cart.php" style="color:var(--gold)!important;font-weight:600;">
                <i class="fa-solid fa-basket-shopping" style="color:var(--gold)!important;"></i> Keranjang
            </a>
        </li>
    </ul>
</div>

<header>
    <div class="header-left">
        <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
        <a href="index.php" class="logo">Musimah</a>
    </div>
    <nav class="desktop-nav">
        <ul>
            <li><a href="index.php">Beranda</a></li>
            <li class="nav-has-dropdown">
                <a href="javascript:void(0)" class="active">Menu &nbsp;<i class="fa-solid fa-chevron-down" style="font-size:0.65rem;opacity:0.6;"></i></a>
                <ul class="dropdown">
                    <li><a href="produk.php?kategori=semua">Semua Kue</a></li>
                    <li><a href="produk.php?kategori=dessert">Dessert</a></li>
                    <li><a href="produk.php?kategori=roti">Roti</a></li>
                    <li><a href="produk.php?kategori=kuekering">Kue Kering</a></li>
                </ul>
            </li>
            <li><a href="tentang.php">Tentang</a></li>
            <li><a href="kontak.php">Kontak</a></li>
        </ul>
    </nav>
    <div class="header-right">
        <a href="cart.php" class="cart-link">
            <i class="fa-solid fa-basket-shopping"></i>
            <?php if($total_items > 0): ?>
                <span class="cart-badge"><?= $total_items ?></span>
            <?php endif; ?>
        </a>
        <?php if(isset($_SESSION['user'])): ?>
            <div class="user-capsule">
                <div class="user-info">
                    <i class="fa-solid fa-circle-user"></i>
                    <span><?= htmlspecialchars($_SESSION['user']) ?></span>
                </div>
                <a href="logout.php" class="btn-logout-small"><i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        <?php else: ?>
            <a href="login.php" class="btn-masuk">Masuk</a>
        <?php endif; ?>
    </div>
</header>

<div class="page-banner">
    <span class="banner-eyebrow">✦ &nbsp; Pilihan Terbaik &nbsp; ✦</span>
    <h1 class="banner-title">Produk Kami</h1>
    <p class="banner-sub">Dibuat dengan bahan organik pilihan, penuh cinta di setiap gigitan</p>
    <div class="banner-ornament">
        <div class="orn-line l"></div>
        <div class="orn-diamond"></div>
        <div class="orn-line r"></div>
    </div>
</div>

<?php if ($pesan): ?>
<div class="alert-wrap">
    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($pesan) ?></div>
</div>
<?php endif; ?>
<?php if ($sukses): ?>
<div class="alert-wrap">
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($sukses) ?></div>
</div>
<?php endif; ?>

<div class="produk-wrap">
    <?php $total_produk = $query ? mysqli_num_rows($query) : 0; ?>
    <p class="produk-count">Menampilkan <span><?= $total_produk ?></span> produk</p>

    <div class="produk-grid">
    <?php if ($total_produk === 0): ?>
        <div class="empty-state">
            <i class="fa-solid fa-cake-candles"></i>
            <h3>Belum Ada Produk</h3>
            <p>Produk untuk kategori ini belum tersedia saat ini.</p>
        </div>
    <?php else: ?>
        <?php while ($row = mysqli_fetch_assoc($query)):
            $stok        = (int) ($row['stok'] ?? 0);
            $habis       = $stok <= 0;
            $hampirHabis = !$habis && $stok <= 10;
            $kat         = htmlspecialchars($row['kategori'] ?? $row['category'] ?? $row['jenis'] ?? 'Bakery');
            $isBestseller = $has_bestseller && !empty($row['is_bestseller']) && (int)$row['is_bestseller'] === 1;

            $kalori      = ($has_kalori      && $row['kalori']      !== null) ? (int)$row['kalori']        : null;
            $protein     = ($has_protein     && $row['protein']     !== null) ? (float)$row['protein']     : null;
            $lemak       = ($has_lemak       && $row['lemak']       !== null) ? (float)$row['lemak']       : null;
            $karbohidrat = ($has_karbohidrat && $row['karbohidrat'] !== null) ? (float)$row['karbohidrat'] : null;
            $hasGizi     = ($kalori !== null || $protein !== null || $lemak !== null || $karbohidrat !== null);

            $deskripsi   = ($has_deskripsi && isset($row['deskripsi'])) ? $row['deskripsi'] : '';

            $modalData = json_encode([
                // FIX: ID produk ditambahkan supaya modal bisa menambah keranjang
                // dengan cara yang sama & seakurat tombol kartu produk / index.php
                'id'          => (int)$row['id_produk'],
                'nama'        => $row['nama_produk'],
                'harga'       => (int)$row['harga'],
                'gambar'      => $row['gambar'],
                'kategori'    => $kat,
                'stok'        => $stok,
                'habis'       => $habis,
                'hampirHabis' => $hampirHabis,
                'bestseller'  => $isBestseller,
                'deskripsi'   => $deskripsi,
                'kalori'      => $kalori,
                'protein'     => $protein,
                'lemak'       => $lemak,
                'karbohidrat' => $karbohidrat,
                'hasGizi'     => $hasGizi,
            ], JSON_HEX_QUOT | JSON_HEX_APOS);
        ?>

        <div class="card <?= $isBestseller ? 'has-bestseller' : '' ?>">

            <?php if ($isBestseller): ?>
            <div class="badge-bestseller"><i class="fa-solid fa-fire"></i> Best Seller</div>
            <?php endif; ?>

            <?php if ($habis): ?>
            <div class="badge-habis"><i class="fa-solid fa-ban"></i> Stok Habis</div>
            <?php endif; ?>

            <div class="badge-kategori"><?= $kat ?></div>

            <div class="card-img-wrap <?= $habis ? 'out-of-stock' : '' ?>">
                <img
                    src="<?= htmlspecialchars($row['gambar']) ?>"
                    alt="<?= htmlspecialchars($row['nama_produk']) ?>"
                    onerror="this.src='https://via.placeholder.com/300x230?text=Kue'"
                >
                <button class="btn-info" onclick='bukaModal(<?= $modalData ?>)' title="Lihat detail & info gizi">
                    <i class="fa-solid fa-circle-info"></i>
                </button>
            </div>

            <div class="card-body">
                <div class="card-title"><?= htmlspecialchars($row['nama_produk']) ?></div>
                <div class="card-price">Rp <?= number_format($row['harga'], 0, ',', '.') ?></div>

                <?php if ($habis): ?>
                    <div class="stok-info habis"><i class="fa-solid fa-circle-xmark"></i> Stok tidak tersedia</div>
                <?php elseif ($hampirHabis): ?>
                    <div class="stok-info warn"><i class="fa-solid fa-triangle-exclamation"></i> Sisa <?= $stok ?> item</div>
                <?php else: ?>
                    <div class="stok-info ok"><i class="fa-solid fa-circle-check"></i> Stok tersedia (<?= $stok ?>)</div>
                <?php endif; ?>
            </div>

            <div class="card-divider"></div>

            <div class="card-footer">
                <?php if ($habis): ?>
                <button class="btn-beli nonaktif" disabled>
                    <i class="fa-solid fa-ban"></i> Stok Habis
                </button>
                <?php else: ?>
                <button
                    class="btn-beli aktif"
                    onclick="tambahKeranjang(this)"
                    data-id="<?= (int)$row['id_produk'] ?>"        
                    data-nama="<?= htmlspecialchars($row['nama_produk'], ENT_QUOTES) ?>"
                    data-harga="<?= (int)$row['harga'] ?>"
                    data-stok="<?= $stok ?>"
                >
                    <i class="fa-solid fa-basket-shopping" style="font-size:0.8rem;"></i>
                    Tambah ke Keranjang
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="modalOverlay" onclick="tutupModalOverlay(event)">
    <div class="modal" id="modal">
        <button class="modal-close" onclick="tutupModal()"><i class="fa-solid fa-xmark"></i></button>
        <img id="modal-img" class="modal-img" src="" alt="">
        <div class="modal-body">
            <span class="modal-eyebrow" id="modal-kat"></span>
            <div class="modal-title" id="modal-nama"></div>
            <div class="modal-price" id="modal-harga"></div>
            <div class="modal-badges" id="modal-badges"></div>

            <div class="modal-divider"></div>

            <div class="modal-desc-title">Deskripsi Produk</div>
            <div id="modal-desc"></div>

            <div class="gizi-section" id="gizi-section">
                <div class="gizi-title">Informasi Gizi <span style="font-size:0.62rem;color:var(--text-muted);font-style:italic;text-transform:none;letter-spacing:0;">(per sajian)</span></div>
                <div id="gizi-content"></div>
            </div>

            <div class="modal-footer" id="modal-footer"></div>
        </div>
    </div>
</div>

<!-- ═══════════ SOUND PLAYER ═══════════ -->
<audio id="bakeryAudio" src="assets/audio/sound.mp3" loop preload="auto"></audio>
<div class="sound-player" id="soundPlayer">
    <div class="sound-panel" id="soundPanel">
        <div class="sp-header">
            <i class="fa-solid fa-music sp-icon"></i>
            <span class="sp-title">Bakery Ambience</span>
            <span class="sp-status" id="spStatus">OFF</span>
        </div>
        <div class="sp-visualizer" id="spViz">
            <div class="sp-bar"></div><div class="sp-bar"></div><div class="sp-bar"></div>
            <div class="sp-bar"></div><div class="sp-bar"></div><div class="sp-bar"></div>
            <div class="sp-bar"></div><div class="sp-bar"></div><div class="sp-bar"></div>
            <div class="sp-bar"></div>
        </div>
        <div class="sp-track">
            <div class="sp-track-icon"><i class="fa-solid fa-bread-slice"></i></div>
            <div class="sp-track-info">
                <span class="sp-track-name">Bakery Ambience</span>
                <span class="sp-track-sub">Musimah Homemade Bakery</span>
            </div>
        </div>
        <div class="sp-progress-wrap">
            <div class="sp-progress-bar" id="spProgressBar" onclick="seekAudio(event)">
                <div class="sp-progress-fill" id="spProgressFill"></div>
            </div>
            <div class="sp-time-row">
                <span id="spTimeCurrent">0:00</span>
                <span id="spTimeDuration">0:00</span>
            </div>
        </div>
        <div class="sp-controls">
            <button class="sp-btn-secondary" onclick="restartAudio()"><i class="fa-solid fa-backward-step"></i></button>
            <button class="sp-btn-play" id="btnPlayPause" onclick="togglePlayPause()"><i class="fa-solid fa-play" id="playIcon"></i></button>
            <button class="sp-btn-secondary active" id="btnLoop" onclick="toggleLoop()"><i class="fa-solid fa-repeat"></i></button>
        </div>
        <div class="sp-divider"></div>
        <div class="sp-volume">
            <i class="fa-solid fa-volume-low sp-vol-icon" id="volIcon"></i>
            <input type="range" class="sp-vol-slider" id="volSlider" min="0" max="100" value="40" oninput="setVolume(this.value)">
            <span class="sp-vol-val" id="volVal">40%</span>
        </div>
    </div>
    <button class="sound-fab" id="soundFab" onclick="toggleSoundPanel()" aria-label="Toggle sound player">
        <i class="fa-solid fa-music" id="fabIcon"></i>
    </button>
</div>

<footer>
    <div class="footer-inner">
        <div class="footer-grid">
            <div class="footer-brand">
                <span class="footer-logo">Musimah</span>
                <p class="footer-desc">Menghadirkan kelezatan premium dari dapur rumahan sejak 2026. Setiap kue dibuat dengan bahan organik pilihan dan penuh cinta.</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 Musimah Bakery & Cake &nbsp;·&nbsp; Dibuat dengan cinta untuk Anda</p>
        </div>
    </div>
</footer>

<div id="toast"></div>

<script>
/* FIX: token CSRF, dikirim di setiap request tambah-keranjang */
const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?>';

const hamburger = document.getElementById('hamburger');
const sidebar   = document.getElementById('sidebar');
const overlay   = document.getElementById('overlay');
const closeBtn  = document.getElementById('closeBtn');

hamburger.onclick = () => { sidebar.style.left = '0'; overlay.style.display = 'block'; };
function closeSidebar() { sidebar.style.left = '-100%'; overlay.style.display = 'none'; }
closeBtn.onclick = closeSidebar;
overlay.onclick  = closeSidebar;

const mobileAccordionBtn = document.getElementById('mobileAccordionBtn');
const mobileSub   = document.getElementById('mobileSub');
const mobileArrow = document.getElementById('mobileArrow');
mobileAccordionBtn.onclick = () => {
    const open = mobileSub.style.maxHeight && mobileSub.style.maxHeight !== '0px';
    mobileSub.style.maxHeight = open ? '0px' : '400px';
    mobileArrow.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
};

const cards = document.querySelectorAll('.card');
const io = new IntersectionObserver((entries) => {
    entries.forEach((e, i) => {
        if (e.isIntersecting) {
            setTimeout(() => e.target.classList.add('visible'), i * 80);
            io.unobserve(e.target);
        }
    });
}, { threshold: 0.08 });
cards.forEach(c => io.observe(c));

function tampilToast(pesan, isError = false) {
    const t = document.getElementById('toast');
    t.textContent = pesan;
    t.className   = 'show' + (isError ? ' error' : '');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.className = '', 3200);
}

async function tambahKeranjang(btn) {
    const id    = btn.dataset.id;
    const harga = btn.dataset.harga;
    const stok  = parseInt(btn.dataset.stok);

    if (stok <= 0) { tampilToast('Stok produk habis!', true); return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';

    try {
        const form = new FormData();
        form.append('aksi',        'tambah_keranjang');
        form.append('id_produk', id);
        form.append('harga',       harga);
        form.append('jumlah',      1);
        form.append('csrf_token',  CSRF_TOKEN);

        const res  = await fetch('proses_pesanan.php', { method: 'POST', body: form });
        const data = await res.json();

        if (data.status === 'ok') {
            tampilToast('✓ ' + data.pesan);
            const badge = document.querySelector('.cart-badge');
            if (badge) {
                badge.textContent = parseInt(badge.textContent || '0') + 1;
            } else {
                const cartLink = document.querySelector('.cart-link');
                const newBadge = document.createElement('span');
                newBadge.className = 'cart-badge';
                newBadge.textContent = '1';
                cartLink.appendChild(newBadge);
            }
        } else {
            tampilToast(data.pesan, true);
            if (data.pesan.toLowerCase().includes('habis')) {
                btn.className    = 'btn-beli nonaktif';
                btn.disabled     = true;
                btn.innerHTML    = '<i class="fa-solid fa-ban"></i> Stok Habis';
                btn.dataset.stok = '0';
                return;
            }
        }
    } catch (e) {
        tampilToast('Terjadi kesalahan. Coba lagi.', true);
    } finally {
        if (!btn.classList.contains('nonaktif')) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-basket-shopping" style="font-size:0.8rem;"></i> Tambah ke Keranjang';
        }
    }
}

let modalActiveProduct = null;

function bukaModal(data) {
    modalActiveProduct = data;
    const overlay = document.getElementById('modalOverlay');

    const img = document.getElementById('modal-img');
    img.src = data.gambar;
    img.onerror = () => { img.src = 'https://via.placeholder.com/760x280?text=' + encodeURIComponent(data.nama); };
    img.alt = data.nama;

    document.getElementById('modal-kat').textContent   = data.kategori;
    document.getElementById('modal-nama').textContent  = data.nama;
    document.getElementById('modal-harga').textContent = 'Rp ' + data.harga.toLocaleString('id-ID');

    const badgesEl = document.getElementById('modal-badges');
    badgesEl.innerHTML = '';
    if (data.bestseller) {
        badgesEl.innerHTML += '<span class="modal-badge bs"><i class="fa-solid fa-fire"></i> Best Seller</span>';
    }
    badgesEl.innerHTML += `<span class="modal-badge kat">${data.kategori}</span>`;
    if (data.habis) {
        badgesEl.innerHTML += '<span class="modal-badge stok-habis"><i class="fa-solid fa-ban"></i> Stok Habis</span>';
    } else if (data.hampirHabis) {
        badgesEl.innerHTML += `<span class="modal-badge stok-warn"><i class="fa-solid fa-triangle-exclamation"></i> Sisa ${data.stok} item</span>`;
    } else {
        badgesEl.innerHTML += `<span class="modal-badge stok-ok"><i class="fa-solid fa-circle-check"></i> Stok Tersedia (${data.stok})</span>`;
    }

    const descEl = document.getElementById('modal-desc');
    if (data.deskripsi && data.deskripsi.trim() !== '') {
        descEl.innerHTML = `<p class="modal-desc">${escHtml(data.deskripsi)}</p>`;
    } else {
        descEl.innerHTML = '<p class="modal-desc-empty">Deskripsi belum tersedia untuk produk ini.</p>';
    }

    const giziContent = document.getElementById('gizi-content');
    if (data.hasGizi) {
        const giziItems = [
            { label: 'Kalori', value: data.kalori, unit: 'kcal' },
            { label: 'Protein', value: data.protein, unit: 'g' },
            { label: 'Lemak', value: data.lemak, unit: 'g' },
            { label: 'Karbohidrat', value: data.karbohidrat, unit: 'g' },
        ];
        giziContent.innerHTML = `
            <div class="gizi-grid">
                ${giziItems.map(g => g.value !== null ? `
                <div class="gizi-item">
                    <span class="gizi-value">${g.value}</span>
                    <span class="gizi-unit">${g.unit}</span>
                    <span class="gizi-label">${g.label}</span>
                </div>` : '').join('')}
            </div>
            <p class="gizi-note">* Nilai gizi perkiraan per porsi. Dapat bervariasi tergantung bahan.</p>
        `;
    } else {
        giziContent.innerHTML = '<p class="gizi-empty">Informasi gizi belum tersedia untuk produk ini.</p>';
    }

    // FIX: tombol "Pesan via WA" / "Tanya Stok" di pop-up dihapus sesuai permintaan.
    const footerEl = document.getElementById('modal-footer');
    if (data.habis) {
        footerEl.innerHTML = `
            <button class="modal-btn-beli nonaktif" disabled>
                <i class="fa-solid fa-ban"></i> Stok Habis
            </button>`;
    } else {
        footerEl.innerHTML = `
            <button class="modal-btn-beli" onclick="modalTambahKeranjang()">
                <i class="fa-solid fa-basket-shopping"></i> Tambah ke Keranjang
            </button>`;
    }

    document.getElementById('modal').scrollTop = 0;
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function tutupModal() {
    document.getElementById('modalOverlay').classList.remove('open');
    document.body.style.overflow = '';
    modalActiveProduct = null;
}

function tutupModalOverlay(e) {
    if (e.target === document.getElementById('modalOverlay')) tutupModal();
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') tutupModal(); });

async function modalTambahKeranjang() {
    const data = modalActiveProduct;
    if (!data || data.habis) return;

    const btn = document.querySelector('#modal-footer .modal-btn-beli');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';

    try {
        const form = new FormData();
        form.append('aksi',      'tambah_keranjang');
        // FIX: kirim id_produk, bukan nama_produk — konsisten dengan tambahKeranjang()
        // di atas dan dengan versi index.php, supaya identifikasi produk akurat.
        form.append('id_produk', data.id);
        form.append('jumlah',    1);
        form.append('csrf_token', CSRF_TOKEN);

        const res  = await fetch('proses_pesanan.php', { method: 'POST', body: form });
        const resp = await res.json();

        if (resp.status === 'ok') {
            tampilToast('✓ ' + resp.pesan);
            const badge = document.querySelector('.cart-badge');
            if (badge) { badge.textContent = parseInt(badge.textContent || '0') + 1; }
            else {
                const cartLink = document.querySelector('.cart-link');
                const nb = document.createElement('span');
                nb.className = 'cart-badge'; nb.textContent = '1';
                cartLink.appendChild(nb);
            }
            tutupModal();
        } else {
            tampilToast(resp.pesan, true);
        }
    } catch (e) {
        tampilToast('Terjadi kesalahan. Coba lagi.', true);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-basket-shopping"></i> Tambah ke Keranjang';
        }
    }
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/* ══ SOUND PLAYER ══ */
const audio    = document.getElementById('bakeryAudio');
const fab      = document.getElementById('soundFab');
const panel    = document.getElementById('soundPanel');
const viz      = document.getElementById('spViz');
const spStatus = document.getElementById('spStatus');
const fabIcon  = document.getElementById('fabIcon');
const playIcon = document.getElementById('playIcon');
const btnLoop  = document.getElementById('btnLoop');
const fillEl   = document.getElementById('spProgressFill');
const timeCur  = document.getElementById('spTimeCurrent');
const timeDur  = document.getElementById('spTimeDuration');
const volIcon  = document.getElementById('volIcon');
let panelOpen = false, isLooping = true;
audio.volume = 0.4; audio.loop = true;

function fmtTime(s) {
    if (!s || isNaN(s)) return '0:00';
    return Math.floor(s/60) + ':' + (Math.floor(s%60)<10?'0':'') + Math.floor(s%60);
}
audio.addEventListener('timeupdate', () => {
    if (!audio.duration) return;
    fillEl.style.width = (audio.currentTime/audio.duration*100) + '%';
    timeCur.textContent = fmtTime(audio.currentTime);
});
audio.addEventListener('loadedmetadata', () => { timeDur.textContent = fmtTime(audio.duration); });
function seekAudio(e) {
    if (!audio.duration) return;
    const bar = document.getElementById('spProgressBar'), rect = bar.getBoundingClientRect();
    audio.currentTime = Math.max(0, Math.min(1,(e.clientX-rect.left)/rect.width)) * audio.duration;
}
function togglePlayPause() { if (audio.paused) audio.play().catch(()=>{}); else audio.pause(); updateSPUI(); }
function restartAudio() { audio.currentTime=0; if(audio.paused) audio.play().catch(()=>{}); updateSPUI(); }
function toggleLoop() { isLooping=!isLooping; audio.loop=isLooping; btnLoop.classList.toggle('active',isLooping); }
function setVolume(v) {
    audio.volume=v/100;
    document.getElementById('volVal').textContent=v+'%';
    volIcon.className = v==0?'fa-solid fa-volume-xmark sp-vol-icon':v<50?'fa-solid fa-volume-low sp-vol-icon':'fa-solid fa-volume-high sp-vol-icon';
}
function toggleSoundPanel() {
    panelOpen=!panelOpen;
    panel.classList.toggle('show',panelOpen);
    fab.classList.toggle('panel-open',panelOpen);
    fabIcon.className = panelOpen?'fa-solid fa-xmark':'fa-solid fa-music';
    if(panelOpen && audio.paused) { audio.play().catch(()=>{}); updateSPUI(); }
}
function updateSPUI() {
    const playing=!audio.paused;
    playIcon.className = playing?'fa-solid fa-pause':'fa-solid fa-play';
    fab.classList.toggle('playing',playing);
    viz.classList.toggle('active',playing);
    spStatus.textContent = playing?'● LIVE':'PAUSED';
    spStatus.classList.toggle('live',playing);
    if(!panelOpen) fabIcon.className='fa-solid fa-music';
}
audio.addEventListener('play',  updateSPUI);
audio.addEventListener('pause', updateSPUI);
</script>
</body>
</html>
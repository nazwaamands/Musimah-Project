<?php
session_start();
include 'koneksi.php';

// FIX: CSRF token untuk endpoint tambah-keranjang (proses_pesanan.php)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// chatbot dihandle oleh chatbot.php

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: admin.php");
    exit;
}

// Cek kolom opsional
$cols_res = mysqli_query($conn, "SHOW COLUMNS FROM produk");
$existing_cols = [];
if ($cols_res) {
    while ($col = mysqli_fetch_assoc($cols_res)) $existing_cols[] = $col['Field'];
}
$has_bestseller  = in_array('is_bestseller',  $existing_cols);
$has_deskripsi   = in_array('deskripsi',      $existing_cols);
$has_kalori      = in_array('kalori',         $existing_cols);
$has_protein     = in_array('protein',        $existing_cols);
$has_lemak       = in_array('lemak',          $existing_cols);
$has_karbohidrat = in_array('karbohidrat',    $existing_cols);

$query_bestseller = null;
if ($has_bestseller) {
    $query_bestseller = mysqli_query($conn, "SELECT * FROM produk WHERE is_bestseller = 1 AND stok > 0 ORDER BY id_produk DESC");
}

$query_produk = mysqli_query($conn, "SELECT * FROM produk ORDER BY id_produk DESC");

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
    <title>Musimah — Premium Homemade Bakery</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy-deep:   #060d1f;
            --navy-dark:   #0b1735;
            --navy-mid:    #112255;
            --navy-light:  #1a3370;
            --navy-soft:   #1e3d80;
            --gold:        #c9a96e;
            --gold-light:  #e8c98a;
            --gold-pale:   #f5e6cb;
            --cream:       #fdf8f0;
            --white:       #ffffff;
            --text-muted:  #8a9bc5;
            --border:      rgba(201,169,110,0.2);
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--cream);
            color: var(--navy-deep);
            overflow-x: hidden;
        }

        /* ─────────── WELCOME SCREEN ─────────── */
        #welcome-screen {
            position: fixed; inset: 0;
            background: var(--navy-deep);
            z-index: 9999;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 0;
            animation: wsOut 0.8s ease forwards 3.4s;
        }
        .ws-ornament { width:80px;height:1px;background:linear-gradient(90deg,transparent,var(--gold),transparent);opacity:0;animation:fadeIn 0.6s ease 0.3s forwards; }
        .ws-logo { font-family:'Cormorant Garamond',serif;font-size:5.5rem;font-weight:300;font-style:italic;color:var(--white);line-height:1;letter-spacing:2px;margin:18px 0 6px;opacity:0;animation:slideUp 0.9s cubic-bezier(.22,1,.36,1) 0.5s forwards; }
        .ws-tagline { font-size:0.68rem;letter-spacing:7px;color:var(--gold);text-transform:uppercase;font-weight:400;opacity:0;animation:fadeIn 0.8s ease 1s forwards;margin-bottom:22px; }
        .ws-ornament2 { width:0;height:1px;background:linear-gradient(90deg,transparent,var(--gold),transparent);animation:growBar 0.7s ease 1.2s forwards;margin-bottom:24px; }
        .ws-welcome { font-family:'Cormorant Garamond',serif;font-size:1.15rem;font-style:italic;color:rgba(255,255,255,0.6);letter-spacing:2px;opacity:0;animation:fadeIn 0.8s ease 1.5s forwards; }
        .ws-dots { display:flex;gap:8px;margin-top:44px;opacity:0;animation:fadeIn 0.6s ease 1.9s forwards; }
        .ws-dots span { width:5px;height:5px;border-radius:50%;background:rgba(201,169,110,0.3); }
        .ws-dots span:nth-child(1) { animation:dotPulse 1.1s ease 2.2s infinite; }
        .ws-dots span:nth-child(2) { animation:dotPulse 1.1s ease 2.4s infinite; }
        .ws-dots span:nth-child(3) { animation:dotPulse 1.1s ease 2.6s infinite; }

        @keyframes slideUp   { from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)} }
        @keyframes fadeIn    { from{opacity:0}to{opacity:1} }
        @keyframes growBar   { from{width:0}to{width:120px} }
        @keyframes dotPulse  { 0%,100%{background:rgba(201,169,110,0.2);transform:scale(1)}50%{background:var(--gold);transform:scale(1.4)} }
        @keyframes wsOut     { to{opacity:0;visibility:hidden} }

        /* ─────────── HEADER ─────────── */
        header { position:fixed;top:20px;left:50%;transform:translateX(-50%);width:92%;max-width:1140px;height:68px;background:rgba(6,13,31,0.85);backdrop-filter:blur(20px);border:1px solid rgba(201,169,110,0.15);border-radius:60px;display:flex;justify-content:space-between;align-items:center;padding:0 28px;z-index:1000;box-shadow:0 8px 40px rgba(0,0,0,0.35); }
        .header-left { display:flex;align-items:center;gap:16px; }
        .logo { text-decoration:none;font-family:'Cormorant Garamond',serif;font-size:1.9rem;font-weight:400;font-style:italic;color:var(--white);letter-spacing:1px;line-height:1;padding-top:4px; }
        .desktop-nav ul { display:flex;list-style:none;gap:6px;align-items:center; }
        .desktop-nav ul li a { text-decoration:none;color:rgba(255,255,255,0.75);font-weight:400;font-size:0.875rem;letter-spacing:0.3px;padding:6px 14px;border-radius:30px;transition:all 0.25s; }
        .desktop-nav ul li a:hover { color:var(--white);background:rgba(255,255,255,0.08); }
        .nav-has-dropdown { position:relative; }
        .dropdown { position:absolute;top:calc(100% + 10px);left:0;background:var(--navy-dark);border:1px solid rgba(201,169,110,0.18);border-radius:16px;min-width:180px;list-style:none;padding:8px;box-shadow:0 20px 60px rgba(0,0,0,0.4);opacity:0;visibility:hidden;transform:translateY(6px);transition:opacity 0.2s ease,transform 0.2s ease,visibility 0.2s;pointer-events:none;z-index:999; }
        .nav-has-dropdown:hover>.dropdown,.nav-has-dropdown:focus-within>.dropdown { opacity:1;visibility:visible;transform:translateY(0);pointer-events:auto; }
        .dropdown li a { display:block;padding:9px 16px;border-radius:10px;font-size:0.875rem; }
        .dropdown li a:hover { background:rgba(201,169,110,0.1)!important;color:var(--gold)!important; }
        .header-right { display:flex;align-items:center;gap:10px; }
        .cart-link { position:relative;color:var(--white);text-decoration:none;display:flex;align-items:center;width:40px;height:40px;justify-content:center;border-radius:50%;border:1px solid rgba(255,255,255,0.15);transition:all 0.25s; }
        .cart-link:hover { border-color:var(--gold);background:rgba(201,169,110,0.1); }
        .cart-badge { position:absolute;top:-4px;right:-4px;background:var(--gold);color:var(--navy-deep);font-size:0.6rem;font-weight:700;min-width:16px;height:16px;border-radius:50%;display:flex;align-items:center;justify-content:center; }
        .user-capsule { display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.12);border-radius:40px;padding:6px 14px 6px 10px; }
        .user-info { display:flex;align-items:center;gap:7px;color:rgba(255,255,255,0.85);font-size:0.85rem;white-space:nowrap; }
        .btn-logout-small { color:rgba(255,255,255,0.4);font-size:0.85rem;text-decoration:none;display:flex;align-items:center;border-left:1px solid rgba(255,255,255,0.12);padding-left:8px;transition:0.2s; }
        .btn-logout-small:hover { color:#e11d48; }
        .btn-masuk { background:var(--gold);color:var(--navy-deep);text-decoration:none;padding:9px 22px;border-radius:40px;font-weight:600;font-size:0.85rem;transition:all 0.25s;letter-spacing:0.3px; }
        .btn-masuk:hover { background:var(--gold-light);transform:translateY(-1px); }

        /* ─────────── HAMBURGER ─────────── */
        .hamburger { display:none;flex-direction:column;gap:5px;cursor:pointer;padding:4px; }
        .hamburger span { width:20px;height:1.5px;background:var(--white);transition:0.3s;border-radius:2px; }

        /* ─────────── MOBILE SIDEBAR ─────────── */
        .sidebar-overlay { position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1999;display:none;backdrop-filter:blur(4px); }
        .mobile-sidebar { position:fixed;top:0;left:-100%;width:290px;height:100%;background:var(--navy-dark);z-index:2000;transition:0.4s cubic-bezier(.4,0,.2,1);padding:36px 28px;overflow-y:auto;border-right:1px solid rgba(201,169,110,0.12); }
        .sidebar-logo { font-family:'Cormorant Garamond',serif;font-size:2rem;font-weight:400;font-style:italic;color:var(--white); }
        .sidebar-nav { list-style:none;margin-top:30px; }
        .sidebar-nav li { border-bottom:1px solid rgba(255,255,255,0.06); }
        .sidebar-nav li a { display:flex;align-items:center;gap:13px;padding:14px 0;text-decoration:none;color:rgba(255,255,255,0.8);font-size:0.95rem;transition:0.25s; }
        .sidebar-nav li a:hover { color:var(--gold); }
        .sidebar-nav li a i { color:var(--gold);width:18px;text-align:center;font-size:0.85rem; }
        .accordion-btn { display:flex;align-items:center;gap:13px;padding:14px 0;color:rgba(255,255,255,0.8);font-size:0.95rem;cursor:pointer; }
        .accordion-btn i:first-child { color:var(--gold);width:18px;text-align:center;font-size:0.85rem; }
        .mobile-sub-menu { max-height:0;overflow:hidden;list-style:none;background:rgba(0,0,0,0.2);border-radius:10px;transition:0.4s ease; }
        .mobile-sub-menu li a { padding-left:34px;font-size:0.875rem;color:rgba(255,255,255,0.55); }
        .mobile-sub-menu li a:hover { color:var(--gold); }

        /* ─────────── HERO ─────────── */
        .hero { min-height:100vh;background:var(--navy-deep);position:relative;overflow:hidden;display:grid;grid-template-columns:1fr 1fr; }
        .hero-left { position:relative;z-index:3;display:flex;flex-direction:column;justify-content:center;padding:160px 60px 120px 7%;background:var(--navy-deep); }
        .hero-left::after { content:'';position:absolute;top:0;right:-60px;width:120px;height:100%;background:var(--navy-deep);clip-path:polygon(0 0,30% 0,100% 100%,0 100%);z-index:2; }
        .hero-corner { position:absolute;top:44px;left:5%;width:60px;height:60px;border-top:1px solid rgba(201,169,110,0.4);border-left:1px solid rgba(201,169,110,0.4);animation:fadeIn 1s ease 3.5s both; }
        .hero-year { font-size:0.62rem;letter-spacing:6px;color:var(--gold);text-transform:uppercase;margin-bottom:36px;animation:fadeIn 0.8s ease 3.6s both;display:flex;align-items:center;gap:14px; }
        .hero-year::before { content:'';display:inline-block;width:28px;height:1px;background:var(--gold);opacity:0.6; }
        .hero-title { font-family:'Cormorant Garamond',serif;font-size:clamp(5.5rem,9vw,9.5rem);font-weight:300;font-style:italic;color:var(--white);line-height:0.88;letter-spacing:-2px;animation:luxTitleIn 1.3s cubic-bezier(.16,1,.3,1) 3.7s both; }
        .hero-title .t-gold { display:block;background:linear-gradient(130deg,#fffbe8 0%,var(--gold-light) 35%,var(--gold) 70%,#a07840 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text; }
        .hero-rule { display:flex;align-items:center;gap:0;margin:30px 0 28px;animation:fadeIn 0.8s ease 3.95s both; }
        .hero-rule-line { height:1px;width:50px;background:linear-gradient(90deg,var(--gold),transparent); }
        .hero-rule-diamond { width:5px;height:5px;background:var(--gold);transform:rotate(45deg);margin:0 10px;flex-shrink:0;opacity:0.7; }
        .hero-rule-long { height:1px;flex:1;background:linear-gradient(90deg,rgba(201,169,110,0.3),transparent); }
        .hero-desc { font-size:0.875rem;line-height:1.85;color:rgba(255,255,255,0.45);max-width:320px;margin-bottom:44px;animation:fadeIn 0.9s ease 4.0s both; }
        .hero-cta { display:flex;gap:14px;align-items:center;animation:fadeIn 0.8s ease 4.15s both;flex-wrap:wrap; }
        .hero-stats-row { display:flex;gap:36px;margin-top:52px;padding-top:36px;border-top:1px solid rgba(255,255,255,0.06);animation:fadeIn 0.9s ease 4.3s both; }
        .hsr-num { font-family:'Cormorant Garamond',serif;font-size:2rem;font-weight:300;color:var(--gold);display:block;line-height:1; }
        .hsr-lbl { font-size:0.62rem;letter-spacing:2px;color:rgba(255,255,255,0.3);text-transform:uppercase;display:block;margin-top:5px; }
        .hero-right { position:relative;overflow:hidden; }
        .hero-photo { position:absolute;inset:0;background:url('assets/images/navbar.jpeg') center center/cover no-repeat;transform:scale(1.08);transition:transform 10s ease; }
        .hero-photo.loaded { transform:scale(1); }
        .hero-photo-mask { position:absolute;inset:0;background:linear-gradient(100deg,var(--navy-deep) 0%,rgba(6,13,31,0.3) 28%,rgba(6,13,31,0.05) 60%,rgba(6,13,31,0.4) 100%);z-index:1; }
        .hero-photo-vignette { position:absolute;bottom:0;left:0;right:0;height:200px;background:linear-gradient(to top,var(--navy-deep) 0%,transparent 100%);z-index:1; }
        .hero-photo-badge { position:absolute;bottom:52px;right:40px;z-index:3;background:rgba(6,13,31,0.75);backdrop-filter:blur(16px);border:1px solid rgba(201,169,110,0.25);border-radius:20px;padding:18px 26px;text-align:right;animation:fadeIn 1s ease 4.4s both; }
        .hero-photo-badge .hpb-label { font-size:0.6rem;letter-spacing:3px;color:var(--gold);text-transform:uppercase;display:block;margin-bottom:6px; }
        .hero-photo-badge .hpb-text { font-family:'Cormorant Garamond',serif;font-size:1.05rem;font-style:italic;color:var(--white);display:block;line-height:1.3; }
        .hero-scroll-text { position:absolute;right:18px;top:50%;z-index:3;transform:translateY(-50%) rotate(90deg);font-size:0.58rem;letter-spacing:4px;color:rgba(255,255,255,0.2);text-transform:uppercase;white-space:nowrap;animation:fadeIn 1s ease 4.5s both; }
        .hero-scroll { position:absolute;bottom:0;left:50%;z-index:10;transform:translateX(-50%);display:flex;flex-direction:column;align-items:center;animation:fadeIn 1s ease 4.5s both; }
        .hero-scroll-line { width:1px;height:60px;background:linear-gradient(to bottom,rgba(201,169,110,0.6),transparent);animation:scrollLine 2.2s ease infinite; }
        @keyframes scrollLine { 0%{transform:scaleY(0);transform-origin:top;opacity:1}60%{transform:scaleY(1);transform-origin:top;opacity:1}100%{transform:scaleY(1);transform-origin:top;opacity:0} }
        @keyframes luxTitleIn { from{opacity:0;transform:translateX(-30px);filter:blur(6px)}to{opacity:1;transform:translateX(0);filter:blur(0)} }

        .btn-primary-gold { background:var(--gold);color:var(--navy-deep);text-decoration:none;padding:15px 38px;border-radius:4px;font-weight:600;font-size:0.875rem;letter-spacing:1px;text-transform:uppercase;transition:all 0.3s;display:inline-flex;align-items:center;gap:10px;box-shadow:0 8px 30px rgba(201,169,110,0.2); }
        .btn-primary-gold:hover { background:var(--gold-light);transform:translateY(-2px);box-shadow:0 16px 40px rgba(201,169,110,0.35); }
        .btn-outline-white { border:1px solid rgba(255,255,255,0.2);color:rgba(255,255,255,0.75);text-decoration:none;padding:14px 30px;border-radius:4px;font-size:0.875rem;letter-spacing:1px;text-transform:uppercase;transition:all 0.3s; }
        .btn-outline-white:hover { border-color:var(--gold);color:var(--gold); }

        @media(max-width:900px){.hero{grid-template-columns:1fr;grid-template-rows:1fr auto;min-height:100svh}.hero-left{padding:140px 6% 60px}.hero-left::after{display:none}.hero-right{height:340px;order:-1}.hero-stats-row{gap:24px;flex-wrap:wrap}.hero-photo-badge{right:20px;bottom:20px}.hero-scroll-text{display:none}}

        /* ─────────── SECTION COMMON ─────────── */
        .section-wrap { padding:100px 0; }
        .container { width:90%;max-width:1140px;margin:auto; }
        .section-header { text-align:center;margin-bottom:60px; }
        .section-eyebrow { display:inline-block;font-size:0.68rem;letter-spacing:5px;color:var(--gold);text-transform:uppercase;margin-bottom:14px; }
        .section-title { font-family:'Cormorant Garamond',serif;font-size:clamp(2.4rem,5vw,3.5rem);font-weight:300;font-style:italic;color:var(--navy-deep);line-height:1.1; }

        /* ─────────── PRODUCT CARDS ─────────── */
        .grid-products { display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:28px; }
        .card { background:var(--white);border-radius:24px;overflow:hidden;border:1px solid rgba(11,23,53,0.08);transition:all 0.4s cubic-bezier(.22,1,.36,1);position:relative; }
        .card:hover { transform:translateY(-12px);box-shadow:0 30px 80px rgba(11,23,53,0.15); }
        .card-img-wrap { position:relative;overflow:hidden;height:240px; }
        .card-img-wrap img { width:100%;height:100%;object-fit:cover;transition:transform 0.6s cubic-bezier(.22,1,.36,1); }
        .card:hover .card-img-wrap img { transform:scale(1.08); }
        .card-category { position:absolute;top:14px;left:14px;background:rgba(6,13,31,0.75);backdrop-filter:blur(8px);color:var(--gold);font-size:0.65rem;letter-spacing:2px;text-transform:uppercase;padding:5px 12px;border-radius:30px;border:1px solid rgba(201,169,110,0.2); }
        .card-info-btn { position:absolute;bottom:14px;right:14px;z-index:4;width:32px;height:32px;border-radius:50%;background:rgba(6,13,31,0.75);backdrop-filter:blur(8px);border:1px solid rgba(201,169,110,0.25);color:var(--gold);font-size:0.75rem;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.25s;opacity:0;transform:translateY(4px); }
        .card:hover .card-info-btn { opacity:1;transform:translateY(0); }
        .card-info-btn:hover { background:var(--gold);color:var(--navy-deep);border-color:var(--gold); }
        .card-body { padding:22px 22px 24px; }
        .card h3 { font-family:'Cormorant Garamond',serif;font-size:1.35rem;font-weight:400;color:var(--navy-deep);margin-bottom:6px; }
        .card-price { font-size:1.1rem;font-weight:600;color:var(--navy-mid);margin-bottom:18px; }
        .btn-buy { background:var(--navy-deep);color:var(--white);border:none;padding:11px 24px;border-radius:50px;display:inline-flex;align-items:center;gap:8px;font-weight:500;font-size:0.875rem;font-family:'DM Sans',sans-serif;transition:all 0.3s;width:100%;justify-content:center;cursor:pointer; }
        .btn-buy:hover { background:var(--navy-mid);transform:translateY(-1px); }
        .btn-buy-disabled { background:#e2e8f0;color:#94a3b8;cursor:not-allowed;padding:11px 24px;border-radius:50px;display:block;font-size:0.875rem;text-align:center;width:100%; }

        /* ─────────── ABOUT ─────────── */
        .about-section { background:var(--navy-deep);padding:120px 0;position:relative;overflow:hidden; }
        .about-section::before { content:'M';position:absolute;right:-60px;top:-80px;font-family:'Cormorant Garamond',serif;font-size:500px;font-weight:300;font-style:italic;color:rgba(255,255,255,0.025);line-height:1;pointer-events:none;user-select:none; }
        .about-grid { display:grid;grid-template-columns:1fr 1fr;gap:80px;align-items:center; }
        .about-img-stack { position:relative;height:520px; }
        .about-img-main { position:absolute;left:0;top:0;width:78%;height:440px;border-radius:24px;overflow:hidden;box-shadow:0 40px 80px rgba(0,0,0,0.5);border:1px solid rgba(201,169,110,0.15); }
        .about-img-main img { width:100%;height:100%;object-fit:cover; }
        .about-img-accent { position:absolute;right:0;bottom:0;width:55%;height:300px;border-radius:20px;overflow:hidden;box-shadow:0 30px 60px rgba(0,0,0,0.5);border:3px solid var(--navy-deep); }
        .about-img-accent img { width:100%;height:100%;object-fit:cover; }
        .about-badge-float { position:absolute;left:50%;bottom:60px;transform:translateX(-50%);background:var(--gold);color:var(--navy-deep);border-radius:16px;padding:16px 24px;text-align:center;box-shadow:0 15px 40px rgba(201,169,110,0.3);white-space:nowrap; }
        .about-badge-float .num { font-size:1.8rem;font-weight:700;display:block; }
        .about-badge-float .lbl { font-size:0.7rem;letter-spacing:2px;text-transform:uppercase;opacity:0.7; }
        .about-content { color:rgba(255,255,255,0.85); }
        .about-eyebrow { font-size:0.68rem;letter-spacing:5px;color:var(--gold);text-transform:uppercase;margin-bottom:18px;display:block; }
        .about-title { font-family:'Cormorant Garamond',serif;font-size:clamp(2.5rem,4vw,4rem);font-weight:300;font-style:italic;color:var(--white);line-height:1.1;margin-bottom:24px; }
        .about-divider { width:50px;height:1px;background:linear-gradient(90deg,var(--gold),transparent);margin-bottom:24px; }
        .about-text { font-size:0.95rem;line-height:1.9;color:rgba(255,255,255,0.6);margin-bottom:20px; }
        .about-stats { display:flex;gap:40px;margin-top:40px;padding-top:32px;border-top:1px solid rgba(255,255,255,0.08); }
        .about-stat .num { font-family:'Cormorant Garamond',serif;font-size:2.5rem;font-weight:300;color:var(--gold);display:block;line-height:1; }
        .about-stat .lbl { font-size:0.75rem;color:rgba(255,255,255,0.4);letter-spacing:1px;margin-top:4px; }

        /* ─────────── CONTACT ─────────── */
        .contact-section { padding:100px 0;background:var(--cream); }
        .contact-card { background:var(--white);border-radius:28px;border:1px solid rgba(11,23,53,0.08);padding:40px;display:flex;flex-direction:column;gap:24px;box-shadow:0 8px 40px rgba(11,23,53,0.06); }
        .contact-item { display:flex;align-items:flex-start;gap:18px; }
        .contact-icon { width:46px;height:46px;border-radius:14px;background:var(--navy-deep);color:var(--gold);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0; }
        .contact-item strong { display:block;font-size:0.8rem;letter-spacing:1px;text-transform:uppercase;color:var(--navy-mid);margin-bottom:4px; }
        .contact-item p { color:#64748b;font-size:0.9rem; }
        .map-wrap { border-radius:24px;overflow:hidden;border:1px solid rgba(11,23,53,0.08);box-shadow:0 8px 40px rgba(11,23,53,0.06); }
        .map-wrap iframe { width:100%;height:360px;border:none;display:block; }

        /* ─────────── FOOTER ─────────── */
        footer { background:var(--navy-deep);border-radius:48px 48px 0 0;margin-top:40px;overflow:hidden; }
        .footer-inner { width:90%;max-width:1140px;margin:auto;padding:80px 0 50px; }
        .footer-grid { display:flex;justify-content:center;margin-bottom:0; }
        .footer-brand { text-align:center;max-width:420px; }
        .footer-logo { font-family:'Cormorant Garamond',serif;font-size:2.4rem;font-weight:300;font-style:italic;color:var(--white);display:block;margin-bottom:16px; }
        .footer-desc { color:rgba(255,255,255,0.45);font-size:0.875rem;line-height:1.8;margin:0 auto; }
        .footer-bottom { border-top:1px solid rgba(255,255,255,0.06);padding:24px 0;text-align:center;color:rgba(255,255,255,0.25);font-size:0.8rem; }

        /* ─────────── BESTSELLER ─────────── */
        .bestseller-section { padding:100px 0;background:var(--navy-deep);position:relative;overflow:hidden; }
        .bestseller-section::before { content:'★';position:absolute;right:-40px;top:-60px;font-size:420px;font-weight:900;color:rgba(201,169,110,0.04);pointer-events:none;user-select:none;line-height:1; }
        .bestseller-section .section-eyebrow { color:var(--gold); }
        .bestseller-section .section-title   { color:var(--white); }
        .bs-track-wrap { position:relative; }
        .bs-track { display:flex;gap:24px;overflow-x:auto;padding-bottom:12px;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;scrollbar-width:none; }
        .bs-track::-webkit-scrollbar { display:none; }
        .bs-arrow { position:absolute;top:50%;transform:translateY(-50%);width:42px;height:42px;border-radius:50%;background:rgba(201,169,110,0.15);border:1px solid rgba(201,169,110,0.3);color:var(--gold);font-size:0.9rem;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:5;transition:all 0.25s;backdrop-filter:blur(8px); }
        .bs-arrow:hover { background:var(--gold);color:var(--navy-deep); }
        .bs-arrow-left  { left:-20px; }
        .bs-arrow-right { right:-20px; }
        @media(max-width:700px){ .bs-arrow { display:none; } }
        .bs-card { flex:0 0 260px;scroll-snap-align:start;background:rgba(255,255,255,0.04);border:1px solid rgba(201,169,110,0.15);border-radius:24px;overflow:hidden;transition:all 0.4s cubic-bezier(.22,1,.36,1);position:relative; }
        .bs-card:hover { transform:translateY(-10px);border-color:rgba(201,169,110,0.4);box-shadow:0 24px 60px rgba(0,0,0,0.4); }
        .bs-ribbon { position:absolute;top:14px;right:14px;z-index:2;background:linear-gradient(135deg,#fde68a,#f59e0b);color:#78350f;font-size:0.6rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:5px 11px;border-radius:30px;display:flex;align-items:center;gap:5px;box-shadow:0 4px 12px rgba(245,158,11,0.4); }
        .bs-card-img-wrap { overflow:hidden;position:relative; }
        .bs-card-img-wrap::after { content:'';position:absolute;inset:0;background:linear-gradient(to top,rgba(201,169,110,0.15) 0%,transparent 50%);pointer-events:none; }
        .bs-card-img { width:100%;height:210px;object-fit:cover;display:block;transition:transform 0.5s cubic-bezier(.22,1,.36,1); }
        .bs-card:hover .bs-card-img { transform:scale(1.06); }
        .bs-info-btn { position:absolute;bottom:14px;left:14px;z-index:4;width:30px;height:30px;border-radius:50%;background:rgba(6,13,31,0.75);backdrop-filter:blur(8px);border:1px solid rgba(201,169,110,0.25);color:var(--gold);font-size:0.7rem;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.25s;opacity:0;transform:translateY(4px); }
        .bs-card:hover .bs-info-btn { opacity:1;transform:translateY(0); }
        .bs-info-btn:hover { background:var(--gold);color:var(--navy-deep);border-color:var(--gold); }
        .bs-card-body { padding:18px 18px 20px; }
        .bs-card-cat { font-size:0.6rem;letter-spacing:3px;text-transform:uppercase;color:var(--gold);margin-bottom:6px;display:block; }
        .bs-card h3 { font-family:'Cormorant Garamond',serif;font-size:1.25rem;font-weight:400;color:var(--white);margin-bottom:8px;line-height:1.2; }
        .bs-card-price { font-size:1.05rem;font-weight:700;color:var(--gold-light);margin-bottom:16px;display:block; }
        .btn-buy-bs { background:rgba(201,169,110,0.12);border:1px solid rgba(201,169,110,0.3);color:var(--gold);padding:10px 20px;border-radius:50px;display:flex;align-items:center;justify-content:center;gap:8px;font-size:0.85rem;font-weight:500;font-family:'DM Sans',sans-serif;transition:all 0.3s;width:100%;cursor:pointer; }
        .btn-buy-bs:hover { background:var(--gold);color:var(--navy-deep);border-color:var(--gold); }
        .card-bestseller-badge { position:absolute;top:14px;right:14px;z-index:2;background:linear-gradient(135deg,#fde68a,#f59e0b);color:#78350f;font-size:0.58rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:4px 10px;border-radius:30px;display:flex;align-items:center;gap:4px;box-shadow:0 4px 10px rgba(245,158,11,0.35); }
        .bs-dots { display:flex;justify-content:center;gap:8px;margin-top:22px; }
        .bs-dot { width:6px;height:6px;border-radius:50%;background:rgba(201,169,110,0.25);cursor:pointer;transition:all 0.3s; }
        .bs-dot.active { background:var(--gold);width:20px;border-radius:3px; }

        /* ─────────── MODAL ─────────── */
        .modal-overlay { position:fixed;inset:0;z-index:8000;background:rgba(6,13,31,0.75);backdrop-filter:blur(10px);display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;pointer-events:none;transition:opacity 0.3s ease; }
        .modal-overlay.open { opacity:1;pointer-events:auto; }
        .modal { background:var(--white);border-radius:28px;max-width:760px;width:100%;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 40px 100px rgba(0,0,0,0.4);transform:scale(0.93) translateY(20px);transition:transform 0.35s cubic-bezier(.22,1,.36,1); }
        .modal-overlay.open .modal { transform:scale(1) translateY(0); }
        .modal-close { position:sticky;top:16px;float:right;margin:16px 16px 0 0;z-index:10;width:36px;height:36px;border-radius:50%;background:rgba(11,23,53,0.08);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--navy-mid);font-size:0.9rem;transition:all 0.2s; }
        .modal-close:hover { background:var(--navy-deep);color:var(--white); }
        .modal-img { width:100%;height:280px;object-fit:cover;border-radius:24px 24px 0 0;display:block; }
        .modal-body { padding:28px 32px 32px; }
        .modal-eyebrow { font-size:0.63rem;letter-spacing:5px;color:var(--gold);text-transform:uppercase;margin-bottom:8px;display:block; }
        .modal-title { font-family:'Cormorant Garamond',serif;font-size:2.1rem;font-weight:300;color:var(--navy-deep);line-height:1.1;margin-bottom:6px; }
        .modal-price { font-size:1.2rem;font-weight:700;color:var(--navy-mid);margin-bottom:16px; }
        .modal-badges { display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px; }
        .modal-badge { padding:4px 12px;border-radius:20px;font-size:0.7rem;font-weight:600;letter-spacing:1px;text-transform:uppercase; }
        .modal-badge.bs  { background:linear-gradient(135deg,#c9a96e,#e8c98a);color:var(--navy-deep); }
        .modal-badge.kat { background:rgba(11,23,53,0.08);color:var(--navy-mid); }
        .modal-badge.stok-ok   { background:#dcfce7;color:#15803d; }
        .modal-badge.stok-warn { background:#fef3c7;color:#92400e; }
        .modal-badge.stok-habis { background:#fee2e2;color:#b91c1c; }
        .modal-divider { height:1px;background:rgba(11,23,53,0.08);margin:20px 0; }
        .modal-desc-title { font-size:0.68rem;letter-spacing:3px;text-transform:uppercase;color:var(--gold);margin-bottom:10px; }
        .modal-desc { font-size:0.9rem;color:#475569;line-height:1.75; }
        .modal-desc-empty { font-size:0.875rem;color:rgba(11,23,53,0.3);font-style:italic; }
        .gizi-section { margin-top:24px; }
        .gizi-title { font-size:0.68rem;letter-spacing:3px;text-transform:uppercase;color:var(--gold);margin-bottom:14px; }
        .gizi-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:12px; }
        .gizi-item { background:linear-gradient(135deg,var(--navy-deep),var(--navy-mid));border-radius:16px;padding:16px 12px;text-align:center;position:relative;overflow:hidden; }
        .gizi-item::before { content:'';position:absolute;top:-20px;right:-20px;width:60px;height:60px;border-radius:50%;background:rgba(201,169,110,0.08); }
        .gizi-value { font-family:'Cormorant Garamond',serif;font-size:1.8rem;font-weight:400;color:var(--gold);display:block;line-height:1; }
        .gizi-unit  { font-size:0.65rem;color:rgba(255,255,255,0.4);display:block;margin-bottom:4px; }
        .gizi-label { font-size:0.7rem;color:rgba(255,255,255,0.6);letter-spacing:1px;text-transform:uppercase; }
        .gizi-note  { font-size:0.72rem;color:rgba(11,23,53,0.35);margin-top:10px;font-style:italic; }
        .gizi-empty { font-size:0.875rem;color:rgba(11,23,53,0.3);font-style:italic; }
        .modal-footer { display:flex;gap:12px;margin-top:24px;flex-wrap:wrap; }
        .modal-btn-beli { flex:1;min-width:160px;padding:14px 24px;border-radius:50px;border:none;font-size:0.875rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.25s;font-family:'DM Sans',sans-serif;background:var(--navy-deep);color:var(--white); }
        .modal-btn-beli:hover { background:var(--navy-mid);transform:translateY(-1px);box-shadow:0 8px 24px rgba(6,13,31,0.2); }
        .modal-btn-beli.nonaktif { background:rgba(11,23,53,0.06);color:#94a3b8;cursor:not-allowed;pointer-events:none; }
        @media(max-width:600px){ .gizi-grid{grid-template-columns:repeat(2,1fr)} .modal-body{padding:20px 20px 24px} .modal-title{font-size:1.7rem} .modal-footer{flex-direction:column} }

        /* ─────────── SOUND PLAYER ─────────── */
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
        .sp-title { font-family:'Cormorant Garamond',serif;font-size:1.1rem;font-style:italic;color:var(--white);flex:1; }
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
        .sp-track-name { font-size:0.82rem;font-weight:500;color:var(--white);display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
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
        @media(max-width:680px){ .sound-player{bottom:18px;right:70px} .sound-panel{width:230px} }

        /* ════════════════════════════════
           CHATBOT — Navy & Cream Style
        ════════════════════════════════ */

        /* FAB button */
        .chat-fab {
            position: fixed;
            bottom: 108px;
            right: 30px;
            z-index: 950;
            width: 50px;
            height: 50px;
            background: var(--navy-mid);
            border-radius: 50%;
            border: 1.5px solid rgba(201,169,110,0.35);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 28px rgba(6,13,31,0.45);
            transition: all 0.3s cubic-bezier(.22,1,.36,1);
            color: var(--gold);
            font-size: 1.1rem;
            outline: none;
        }
        .chat-fab:hover { transform: scale(1.1); background: var(--navy-light); }
        .chat-fab.open  { background: var(--navy-deep); border-color: var(--gold); }
        .chat-fab.active-chat { background: var(--navy-deep); border-color: var(--gold); color: var(--gold-light); }

        /* Badge notifikasi */
        .chat-notif {
            position: absolute;
            top: -3px; right: -3px;
            width: 16px; height: 16px;
            background: #e63946;
            border-radius: 50%;
            border: 2px solid var(--cream);
            font-size: 0.58rem; font-weight: 700;
            color: #fff;
            display: flex; align-items: center; justify-content: center;
        }
        .chat-notif.hide { display: none; }

        /* Panel — fixed, muncul di atas FAB */
        .chat-panel {
            position: fixed;
            bottom: 190px;
            right: 30px;
            z-index: 949;
            width: 310px;
            max-height: calc(100vh - 300px);
            background: var(--navy-deep);
            border: 1px solid rgba(201,169,110,0.22);
            border-radius: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,0.55);
            transform: translateY(12px) scale(0.95);
            transform-origin: bottom right;
            opacity: 0;
            pointer-events: none;
            transition: transform 0.3s cubic-bezier(.34,1.56,.64,1), opacity 0.2s ease;
            will-change: transform, opacity;
        }
        .chat-panel.open {
            transform: translateY(0) scale(1);
            opacity: 1;
            pointer-events: all;
        }

        /* Mobile: bottom sheet compact */
        @media(max-width:680px){
            .chat-fab {
                bottom: 20px; right: 16px;
                width: 46px; height: 46px; font-size: 1rem;
            }
            .chat-fab.open { display: none; }
            .chat-panel {
                right: 0; left: 0; bottom: 0; top: auto;
                width: 100%;
                border-radius: 18px 18px 0 0;
                transform-origin: bottom center;
                transform: translateY(100%);
                opacity: 1;
                /* Tinggi fixed: header+messages+quick+input = compact */
                height: auto;
                max-height: 68vh;
            }
            .chat-panel.open {
                transform: translateY(0);
                pointer-events: all;
            }
            .chat-messages {
                min-height: 0;
                height: 180px;
                max-height: 180px;
                flex: none;
            }
        }

        /* Header panel */
        .cp-head {
            background: var(--navy-mid);
            border-bottom: 1px solid rgba(201,169,110,0.18);
            padding: 12px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        .cp-avatar {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: rgba(201,169,110,0.12);
            border: 1px solid rgba(201,169,110,0.3);
            display: flex; align-items: center; justify-content: center;
            color: var(--gold); font-size: 0.95rem;
            flex-shrink: 0;
        }
        .cp-head-info h4 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 0.92rem; font-style: italic;
            color: var(--white); font-weight: 400;
            line-height: 1; margin-bottom: 3px;
        }
        .cp-head-info span {
            font-size: 0.6rem; color: rgba(255,255,255,0.35);
            display: flex; align-items: center; gap: 5px;
        }
        .cp-live-dot {
            width: 6px; height: 6px;
            background: #5de0a5; border-radius: 50%;
            animation: blink 1.6s ease-in-out infinite;
        }
        @keyframes blink { 0%,100%{opacity:1}50%{opacity:0.3} }
        .cp-close {
            margin-left: auto;
            background: none; border: none;
            color: rgba(255,255,255,0.4); font-size: 1rem;
            cursor: pointer; width: 28px; height: 28px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .cp-close:hover { background: rgba(255,255,255,0.08); color: var(--white); }

        /* Messages */
        .chat-messages {
            flex: 1;
            padding: 12px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-height: 130px;
            max-height: 420px;
            background: rgba(6,13,31,0.6);
        }
        .chat-messages::-webkit-scrollbar { width: 3px; }
        .chat-messages::-webkit-scrollbar-thumb { background: rgba(201,169,110,0.3); border-radius: 10px; }

        .cm {
            max-width: 86%;
            padding: 8px 12px;
            border-radius: 14px;
            font-size: 0.8rem;
            line-height: 1.6;
            word-break: break-word;
        }
        .cm-bot {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(201,169,110,0.15);
            color: rgba(255,255,255,0.85);
            align-self: flex-start;
            border-bottom-left-radius: 3px;
        }
        .cm-user {
            background: var(--navy-mid);
            border: 1px solid rgba(201,169,110,0.25);
            color: var(--gold-pale);
            align-self: flex-end;
            border-bottom-right-radius: 3px;
        }

        /* Typing dots */
        .typing-dots {
            display: flex; align-items: center; gap: 4px;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(201,169,110,0.15);
            border-radius: 14px; border-bottom-left-radius: 3px;
            align-self: flex-start;
        }
        .typing-dots span {
            width: 5px; height: 5px;
            background: var(--gold); border-radius: 50%;
            animation: tdot 1.2s ease-in-out infinite; opacity: 0.5;
        }
        .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes tdot { 0%,80%,100%{transform:translateY(0);opacity:0.4}40%{transform:translateY(-6px);opacity:1} }

        /* Quick replies */
        .chat-quick-wrap {
            position: relative;
            padding: 7px 12px;
            background: rgba(6,13,31,0.5);
            border-top: 1px solid rgba(201,169,110,0.08);
            flex-shrink: 0;
        }
        .chat-quick-toggle {
            width: 100%;
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(201,169,110,0.08);
            border: 1px solid rgba(201,169,110,0.28);
            color: rgba(201,169,110,0.85);
            font-size: 0.72rem;
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .chat-quick-toggle:hover { background: rgba(201,169,110,0.15); border-color: var(--gold); color: var(--gold); }
        .chat-quick-toggle i.cqt-chevron { font-size: 0.65rem; transition: transform 0.25s; }
        .chat-quick-toggle.open i.cqt-chevron { transform: rotate(180deg); }

        .chat-quick-dropdown {
            position: absolute;
            left: 12px; right: 12px; bottom: calc(100% + 6px);
            background: var(--navy-dark);
            border: 1px solid rgba(201,169,110,0.22);
            border-radius: 14px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.45);
            padding: 6px;
            display: flex; flex-direction: column; gap: 3px;
            max-height: 220px; overflow-y: auto;
            opacity: 0; transform: translateY(6px) scale(0.97);
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease;
            z-index: 10;
        }
        .chat-quick-dropdown.show {
            opacity: 1; transform: translateY(0) scale(1);
            pointer-events: auto;
        }
        .cq-item {
            display: flex; align-items: center; gap: 8px;
            background: none; border: none; text-align: left;
            color: rgba(255,255,255,0.75);
            font-size: 0.78rem;
            font-family: 'DM Sans', sans-serif;
            padding: 9px 10px;
            border-radius: 9px;
            cursor: pointer;
            transition: all 0.15s;
            width: 100%;
        }
        .cq-item:hover { background: rgba(201,169,110,0.12); color: var(--gold); }

        /* Input row */
        .chat-input-row {
            display: flex;
            align-items: center;
            border-top: 1px solid rgba(201,169,110,0.12);
            background: var(--navy-dark);
            padding: 2px 6px 2px 14px;
            gap: 6px;
            flex-shrink: 0;
        }
        .chat-input-row input {
            flex: 1; min-width: 0;
            background: none; border: none; outline: none;
            color: var(--cream);
            font-size: 0.82rem;
            font-family: 'DM Sans', sans-serif;
            padding: 10px 0;
        }
        .chat-input-row input::placeholder { color: rgba(255,255,255,0.22); }
        .chat-send {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: var(--gold); border: none;
            color: var(--navy-deep);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 0.8rem;
            transition: all 0.2s; flex-shrink: 0;
        }
        .chat-send:hover { background: var(--gold-light); transform: scale(1.08); }

        /* ─────────── RESPONSIVE ─────────── */
        @media(max-width:960px){ header{width:95%;top:12px;padding:0 18px;height:62px} .desktop-nav{display:none} .hamburger{display:flex} .about-grid{grid-template-columns:1fr;gap:50px} .about-img-stack{height:400px} .about-section::before{display:none} }
        @media(max-width:640px){ .hero-title{font-size:5rem;letter-spacing:-1px} .hero-cta{flex-direction:column;align-items:flex-start} .about-img-stack{height:300px} .about-img-main{width:80%;height:260px} .about-img-accent{width:55%;height:200px} .about-badge-float{display:none} .about-stats{gap:24px;flex-wrap:wrap} }
    </style>
</head>
<body>

<!-- ═══════════ WELCOME SCREEN ═══════════ -->
<div id="welcome-screen">
    <div class="ws-ornament"></div>
    <div class="ws-logo">Musimah</div>
    <div class="ws-tagline">Premium Homemade Bakery</div>
    <div class="ws-ornament2"></div>
    <div class="ws-welcome">Selamat Datang</div>
    <div class="ws-dots"><span></span><span></span><span></span></div>
</div>

<!-- ═══════════ MOBILE SIDEBAR ═══════════ -->
<div class="sidebar-overlay" id="overlay"></div>
<div class="mobile-sidebar" id="sidebar">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <span class="sidebar-logo">Musimah</span>
        <i class="fa-solid fa-xmark" id="closeBtn" style="font-size:1.3rem;cursor:pointer;color:rgba(255,255,255,0.6);"></i>
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

<!-- ═══════════ HEADER ═══════════ -->
<header>
    <div class="header-left">
        <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
        <a href="index.php" class="logo">Musimah</a>
    </div>
    <nav class="desktop-nav">
        <ul>
            <li><a href="#beranda">Beranda</a></li>
            <li class="nav-has-dropdown">
                <a href="javascript:void(0)">Menu &nbsp;<i class="fa-solid fa-chevron-down" style="font-size:0.65rem;opacity:0.6;"></i></a>
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
            <?php if ($total_items > 0): ?>
                <span class="cart-badge"><?= $total_items ?></span>
            <?php endif; ?>
        </a>
        <?php if (isset($_SESSION['user'])): ?>
            <div class="user-capsule">
                <div class="user-info">
                    <i class="fa-solid fa-circle-user"></i>
                    <span><?= htmlspecialchars($_SESSION['user']) ?></span>
                </div>
                <a href="logout.php" class="btn-logout-small" style="border-left:none;padding-left:0;"><i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        <?php else: ?>
            <a href="login.php" class="btn-masuk">Masuk</a>
        <?php endif; ?>
    </div>
</header>

<!-- ═══════════ HERO ═══════════ -->
<section class="hero" id="beranda">
    <div class="hero-left">
        <div class="hero-corner"></div>
        <div class="hero-year">Est. 2026 &nbsp;·&nbsp; Bekasi</div>
        <h1 class="hero-title"><span class="t-gold">Mus</span>imah</h1>
        <div class="hero-rule">
            <div class="hero-rule-line"></div>
            <div class="hero-rule-diamond"></div>
            <div class="hero-rule-long"></div>
        </div>
        <p class="hero-desc">Kue premium buatan tangan dari dapur rumahan kami. Dibuat dengan bahan organik pilihan dan penuh cinta untuk setiap momen istimewa Anda.</p>
        <div class="hero-cta">
            <a href="menu.php" class="btn-primary-gold">Lihat Menu &nbsp;<i class="fa-solid fa-arrow-right" style="font-size:0.75rem;"></i></a>
            <a href="tentang.php" class="btn-outline-white">Kisah Kami</a>
        </div>
    </div>
    <div class="hero-right">
        <div class="hero-photo" id="heroPhoto"></div>
        <div class="hero-photo-mask"></div>
        <div class="hero-photo-vignette"></div>
        <div class="hero-scroll-text">Scroll to explore</div>
    </div>
    <div class="hero-scroll"><div class="hero-scroll-line"></div></div>
</section>

<!-- ═══════════ MENU FAVORIT ═══════════ -->
<section class="section-wrap" id="menu">
    <div class="container">
        <div class="section-header">
            <span class="section-eyebrow">✦ &nbsp; Pilihan Terbaik &nbsp; ✦</span>
            <h2 class="section-title">Menu Favorit Kami</h2>
        </div>
        <div class="grid-products">
            <?php while ($query_produk && ($p = mysqli_fetch_assoc($query_produk))):
                $p_stok        = (int)($p['stok'] ?? 0);
                $p_habis       = $p_stok <= 0;
                $p_hampirHabis = !$p_habis && $p_stok <= 10;
                $p_kat         = $p['kategori'] ?? ($p['category'] ?? ($p['jenis'] ?? 'Bakery'));
                $p_isBs        = $has_bestseller && !empty($p['is_bestseller']);
                $p_deskripsi   = ($has_deskripsi && isset($p['deskripsi'])) ? $p['deskripsi'] : '';
                $p_kalori      = ($has_kalori      && $p['kalori']      !== null) ? (int)$p['kalori']    : null;
                $p_protein     = ($has_protein     && $p['protein']     !== null) ? (float)$p['protein'] : null;
                $p_lemak       = ($has_lemak       && $p['lemak']       !== null) ? (float)$p['lemak']   : null;
                $p_karbohidrat = ($has_karbohidrat && $p['karbohidrat'] !== null) ? (float)$p['karbohidrat'] : null;
                $p_hasGizi     = ($p_kalori !== null || $p_protein !== null || $p_lemak !== null || $p_karbohidrat !== null);
                $p_modalData   = json_encode([
                    'id'          => (int)$p['id_produk'],
                    'nama'        => $p['nama_produk'],
                    'harga'       => (int)$p['harga'],
                    'gambar'      => $p['gambar'],
                    'kategori'    => htmlspecialchars($p_kat),
                    'stok'        => $p_stok,
                    'habis'       => $p_habis,
                    'hampirHabis' => $p_hampirHabis,
                    'bestseller'  => $p_isBs,
                    'deskripsi'   => $p_deskripsi,
                    'kalori'      => $p_kalori,
                    'protein'     => $p_protein,
                    'lemak'       => $p_lemak,
                    'karbohidrat' => $p_karbohidrat,
                    'hasGizi'     => $p_hasGizi,
                ], JSON_HEX_QUOT | JSON_HEX_APOS);
            ?>
            <div class="card">
                <div class="card-img-wrap">
                    <img src="<?= htmlspecialchars($p['gambar']) ?>"
                         alt="<?= htmlspecialchars($p['nama_produk']) ?>"
                         onerror="this.src='https://via.placeholder.com/300x240?text=Kue'">
                    <span class="card-category"><?= htmlspecialchars($p_kat) ?></span>
                    <?php if ($p_isBs): ?>
                    <span class="card-bestseller-badge"><i class="fa-solid fa-fire"></i> Best Seller</span>
                    <?php endif; ?>
                    <button class="card-info-btn" onclick='bukaModal(<?= $p_modalData ?>)' title="Lihat detail & info gizi">
                        <i class="fa-solid fa-circle-info"></i>
                    </button>
                </div>
                <div class="card-body">
                    <h3><?= htmlspecialchars($p['nama_produk']) ?></h3>
                    <p class="card-price">Rp <?= number_format($p['harga'], 0, ',', '.') ?></p>
                    <?php if ($p_habis): ?>
                        <div class="btn-buy-disabled">Stok Habis</div>
                    <?php else: ?>
                        <button class="btn-buy"
                            onclick="tambahKeranjang(this)"
                            data-id="<?= (int)$p['id_produk'] ?>"
                            data-nama="<?= htmlspecialchars($p['nama_produk'], ENT_QUOTES) ?>"
                            data-harga="<?= (int)$p['harga'] ?>"
                            data-stok="<?= $p_stok ?>">
                            <i class="fa-solid fa-basket-shopping" style="font-size:0.8rem;"></i>
                            Tambah ke Keranjang
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<?php if ($has_bestseller && $query_bestseller && mysqli_num_rows($query_bestseller) > 0): ?>
<!-- ═══════════ BEST SELLER ═══════════ -->
<section class="bestseller-section" id="bestseller">
    <div class="container">
        <div class="section-header">
            <span class="section-eyebrow">✦ &nbsp; Favorit Pelanggan &nbsp; ✦</span>
            <h2 class="section-title">Best Seller Kami</h2>
        </div>
        <div class="bs-track-wrap">
            <div class="bs-arrow bs-arrow-left" onclick="scrollBsTrack(-1)"><i class="fa-solid fa-chevron-left"></i></div>
            <div class="bs-arrow bs-arrow-right" onclick="scrollBsTrack(1)"><i class="fa-solid fa-chevron-right"></i></div>
            <div class="bs-track" id="bsTrack">
                <?php while ($bp = mysqli_fetch_assoc($query_bestseller)):
                    $bp_stok        = (int)($bp['stok'] ?? 0);
                    $bp_habis       = $bp_stok <= 0;
                    $bp_hampirHabis = !$bp_habis && $bp_stok <= 10;
                    $bp_kat         = $bp['kategori'] ?? ($bp['category'] ?? ($bp['jenis'] ?? 'Bakery'));
                    $bp_deskripsi   = ($has_deskripsi && isset($bp['deskripsi'])) ? $bp['deskripsi'] : '';
                    $bp_kalori      = ($has_kalori      && $bp['kalori']      !== null) ? (int)$bp['kalori']    : null;
                    $bp_protein     = ($has_protein     && $bp['protein']     !== null) ? (float)$bp['protein'] : null;
                    $bp_lemak       = ($has_lemak       && $bp['lemak']       !== null) ? (float)$bp['lemak']   : null;
                    $bp_karbohidrat = ($has_karbohidrat && $bp['karbohidrat'] !== null) ? (float)$bp['karbohidrat'] : null;
                    $bp_hasGizi     = ($bp_kalori !== null || $bp_protein !== null || $bp_lemak !== null || $bp_karbohidrat !== null);
                    $bp_modalData   = json_encode([
                        'id'          => (int)$bp['id_produk'],
                        'nama'        => $bp['nama_produk'],
                        'harga'       => (int)$bp['harga'],
                        'gambar'      => $bp['gambar'],
                        'kategori'    => htmlspecialchars($bp_kat),
                        'stok'        => $bp_stok,
                        'habis'       => $bp_habis,
                        'hampirHabis' => $bp_hampirHabis,
                        'bestseller'  => true,
                        'deskripsi'   => $bp_deskripsi,
                        'kalori'      => $bp_kalori,
                        'protein'     => $bp_protein,
                        'lemak'       => $bp_lemak,
                        'karbohidrat' => $bp_karbohidrat,
                        'hasGizi'     => $bp_hasGizi,
                    ], JSON_HEX_QUOT | JSON_HEX_APOS);
                ?>
                <div class="bs-card">
                    <span class="bs-ribbon"><i class="fa-solid fa-fire"></i> Best Seller</span>
                    <div class="bs-card-img-wrap">
                        <img src="<?= htmlspecialchars($bp['gambar']) ?>"
                             alt="<?= htmlspecialchars($bp['nama_produk']) ?>"
                             class="bs-card-img"
                             onerror="this.src='https://via.placeholder.com/260x210?text=Kue'">
                        <button class="bs-info-btn" onclick='bukaModal(<?= $bp_modalData ?>)' title="Lihat detail & info gizi">
                            <i class="fa-solid fa-circle-info"></i>
                        </button>
                    </div>
                    <div class="bs-card-body">
                        <span class="bs-card-cat"><?= htmlspecialchars($bp_kat) ?></span>
                        <h3><?= htmlspecialchars($bp['nama_produk']) ?></h3>
                        <span class="bs-card-price">Rp <?= number_format($bp['harga'], 0, ',', '.') ?></span>
                        <?php if ($bp_habis): ?>
                            <div class="btn-buy-disabled">Stok Habis</div>
                        <?php else: ?>
                            <button class="btn-buy-bs"
                                onclick="tambahKeranjang(this)"
                                data-id="<?= (int)$bp['id_produk'] ?>"
                                data-nama="<?= htmlspecialchars($bp['nama_produk'], ENT_QUOTES) ?>"
                                data-harga="<?= (int)$bp['harga'] ?>"
                                data-stok="<?= $bp_stok ?>">
                                <i class="fa-solid fa-basket-shopping" style="font-size:0.8rem;"></i>
                                Tambah ke Keranjang
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <div class="bs-dots" id="bsDots"></div>
    </div>
</section>
<?php endif; ?>

<!-- ═══════════ ABOUT ═══════════ -->
<section class="about-section" id="tentang">
    <div class="container">
        <div class="about-grid">
            <div class="about-img-stack">
                <div class="about-img-main"><img src="assets/images/navbar.jpeg" alt="Dapur Muslimah"></div>
                <div class="about-img-accent"><img src="assets/images/tentang.jfif" alt="Detail Kue"></div>
            </div>
            <div class="about-content">
                <span class="about-eyebrow">✦ &nbsp; Tentang Kami &nbsp; ✦</span>
                <h2 class="about-title">Kisah Manis<br>dari Dapur Kami</h2>
                <div class="about-divider"></div>
                <p class="about-text">Musimah Bakery lahir dari kecintaan mendalam terhadap seni memanggang. Sejak 2026, kami menghadirkan kelezatan premium buatan tangan dengan bahan-bahan organik pilihan terbaik.</p>
                <p class="about-text">Setiap kue yang kami buat adalah sebuah karya — dibentuk dengan penuh perhatian dan cinta, untuk menghadirkan momen istimewa di setiap gigitan.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ KONTAK ═══════════ -->
<section class="contact-section" id="kontak">
    <div class="container">
        <div class="section-header">
            <span class="section-eyebrow">✦ &nbsp; Temukan Kami &nbsp; ✦</span>
            <h2 class="section-title">Hubungi Kami</h2>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:28px;align-items:stretch;">
            <div class="contact-card">
                <div class="contact-item">
                    <div class="contact-icon"><i class="fa-solid fa-location-dot"></i></div>
                    <div><strong>Alamat</strong><p>Jl. Kampung Rawasapi, Mustikasari,<br>Bekasi, Jawa Barat 17157</p></div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon"><i class="fa-brands fa-whatsapp"></i></div>
                    <div><strong>WhatsApp</strong><p>+62 822-3599-8521</p></div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon"><i class="fa-solid fa-clock"></i></div>
                    <div><strong>Jam Operasional</strong><p>Senin – Sabtu: 08.00 – 20.00<br>Minggu: 09.00 – 17.00</p></div>
                </div>
            </div>
            <div class="map-wrap">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3965.879104072328!2d107.01489717370085!3d-6.279620861466405!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e698e0fd68fca43%3A0x29d98db62414c7ac!2sJl.%20Kp.%20Rw.%20Sapi%2C%20Mustikasari%2C%20Kec.%20Mustika%20Jaya%2C%20Kota%20Bks%2C%20Jawa%20Barat%2017157!5e0!3m2!1sid!2sid" loading="lazy"></iframe>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ FOOTER ═══════════ -->
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

<!-- ═══════════ MODAL ═══════════ -->
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
            <div class="gizi-section">
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

<!-- ═══════════ CHATBOT WIDGET ═══════════ -->
<!-- Overlay backdrop (mobile) -->
<div id="chatBackdrop" onclick="toggleChat()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:948;backdrop-filter:blur(2px);"></div>

<!-- Panel chat -->
<div class="chat-panel" id="chatPanel">
    <div class="cp-head">
        <div class="cp-avatar"><i class="fa-solid fa-comments"></i></div>
        <div class="cp-head-info">
            <h4>Musimah Assistant</h4>
            <span><div class="cp-live-dot"></div> Online sekarang</span>
        </div>
        <button class="cp-close" onclick="toggleChat()" aria-label="Tutup"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <div class="chat-messages" id="chatMessages">
        <div class="cm cm-bot">Halo! Selamat datang di <b>Musimah Bakery</b> 🍞<br>Ada yang bisa saya bantu?</div>
    </div>

    <div class="chat-quick-wrap">
        <div class="chat-quick-dropdown" id="chatQuickDropdown">
            <button class="cq-item" onclick="chatQuick('menu')">🍩&nbsp; Menu</button>
            <button class="cq-item" onclick="chatQuick('kategori')">📂&nbsp; Kategori</button>
            <button class="cq-item" onclick="chatQuick('status pesanan')">📦&nbsp; Status Pesanan</button>
            <button class="cq-item" onclick="chatQuick('pembayaran')">💳&nbsp; Pembayaran</button>
            <button class="cq-item" onclick="chatQuick('lokasi')">📍&nbsp; Lokasi</button>
            <button class="cq-item" onclick="chatQuick('jam buka')">⏰&nbsp; Jam Buka</button>
            <button class="cq-item" onclick="chatQuick('pengiriman')">🚗&nbsp; Delivery</button>
        </div>
        <button class="chat-quick-toggle" id="chatQuickToggle" onclick="toggleChatQuick()">
            <span>💬 Pilihan Cepat</span>
            <i class="fa-solid fa-chevron-up cqt-chevron"></i>
        </button>
    </div>

    <div class="chat-input-row">
        <input type="text" id="chatInput" placeholder="Ketik pesan..." onkeypress="chatKey(event)">
        <button class="chat-send" onclick="chatSend()" aria-label="Kirim">
            <i class="fa-solid fa-paper-plane"></i>
        </button>
    </div>
</div>

<!-- FAB button -->
<button class="chat-fab" id="chatFab" onclick="toggleChat()" aria-label="Buka chat">
    <i class="fa-solid fa-comments" id="chatFabIcon"></i>
    <div class="chat-notif" id="chatNotif">1</div>
</button>

<script>
/* FIX: token CSRF, dikirim di setiap request tambah-keranjang */
const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?>';

/* ══ WELCOME SCREEN ══ */
const ws = document.getElementById('welcome-screen');
ws.addEventListener('animationend', e => { if (e.animationName === 'wsOut') ws.remove(); });

/* ══ HERO PHOTO ══ */
const heroPhoto = document.getElementById('heroPhoto');
if (heroPhoto) {
    const bgImg = new Image();
    bgImg.onload = () => { setTimeout(() => heroPhoto.classList.add('loaded'), 50); };
    bgImg.src = 'assets/images/navbar.jpeg';
}

/* ══ HAMBURGER & SIDEBAR ══ */
const hamburger = document.getElementById('hamburger');
const sidebar   = document.getElementById('sidebar');
const overlay   = document.getElementById('overlay');
const closeBtn  = document.getElementById('closeBtn');
hamburger.onclick = () => { sidebar.style.left = '0'; overlay.style.display = 'block'; };
function closeSidebar() { sidebar.style.left = '-100%'; overlay.style.display = 'none'; }
closeBtn.onclick = closeSidebar;
overlay.onclick  = closeSidebar;

/* ══ MOBILE ACCORDION ══ */
const mobileAccordionBtn = document.getElementById('mobileAccordionBtn');
const mobileSub   = document.getElementById('mobileSub');
const mobileArrow = document.getElementById('mobileArrow');
mobileAccordionBtn.onclick = () => {
    const open = mobileSub.style.maxHeight && mobileSub.style.maxHeight !== '0px';
    mobileSub.style.maxHeight = open ? '0px' : '400px';
    mobileArrow.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
};

/* ══ SCROLL REVEAL ══ */
const revealEls = document.querySelectorAll('.card, .about-img-stack, .about-content, .contact-card, .map-wrap');
const io = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            e.target.style.opacity = '1';
            e.target.style.transform = 'translateY(0)';
            io.unobserve(e.target);
        }
    });
}, { threshold: 0.1 });
revealEls.forEach(el => {
    el.style.opacity = '0';
    el.style.transition = 'opacity 0.7s ease, transform 0.7s ease';
    el.style.transform = 'translateY(24px)';
    io.observe(el);
});

/* ══ BESTSELLER SLIDER ══ */
const bsTrack = document.getElementById('bsTrack');
if (bsTrack) {
    const bsDots  = document.getElementById('bsDots');
    const bsCards = bsTrack.querySelectorAll('.bs-card');
    const cardW   = 260 + 24;
    bsCards.forEach((_, i) => {
        const d = document.createElement('div');
        d.className = 'bs-dot' + (i === 0 ? ' active' : '');
        d.onclick = () => { bsTrack.scrollTo({ left: i * cardW, behavior: 'smooth' }); };
        bsDots.appendChild(d);
    });
    bsTrack.addEventListener('scroll', () => {
        const idx = Math.round(bsTrack.scrollLeft / cardW);
        bsDots.querySelectorAll('.bs-dot').forEach((d, i) => d.classList.toggle('active', i === idx));
    });
}
function scrollBsTrack(dir) {
    if (!bsTrack) return;
    bsTrack.scrollBy({ left: dir * (260 + 24) * 2, behavior: 'smooth' });
}

/* ══════════════════════════════════════
   MODAL DETAIL PRODUK
══════════════════════════════════════ */
let modalActiveProduct = null;

function bukaModal(data) {
    modalActiveProduct = data;
    const overlayEl = document.getElementById('modalOverlay');
    const img = document.getElementById('modal-img');
    img.src = data.gambar;
    img.onerror = () => { img.src = 'https://via.placeholder.com/760x280?text=' + encodeURIComponent(data.nama); };
    img.alt = data.nama;
    document.getElementById('modal-kat').textContent   = data.kategori;
    document.getElementById('modal-nama').textContent  = data.nama;
    document.getElementById('modal-harga').textContent = 'Rp ' + data.harga.toLocaleString('id-ID');
    const badgesEl = document.getElementById('modal-badges');
    badgesEl.innerHTML = '';
    if (data.bestseller) badgesEl.innerHTML += '<span class="modal-badge bs"><i class="fa-solid fa-fire"></i> Best Seller</span>';
    badgesEl.innerHTML += `<span class="modal-badge kat">${escHtml(data.kategori)}</span>`;
    if (data.habis) {
        badgesEl.innerHTML += '<span class="modal-badge stok-habis"><i class="fa-solid fa-ban"></i> Stok Habis</span>';
    } else if (data.hampirHabis) {
        badgesEl.innerHTML += `<span class="modal-badge stok-warn"><i class="fa-solid fa-triangle-exclamation"></i> Sisa ${data.stok} item</span>`;
    } else {
        badgesEl.innerHTML += `<span class="modal-badge stok-ok"><i class="fa-solid fa-circle-check"></i> Stok Tersedia (${data.stok})</span>`;
    }
    const descEl = document.getElementById('modal-desc');
    descEl.innerHTML = (data.deskripsi && data.deskripsi.trim() !== '')
        ? `<p class="modal-desc">${escHtml(data.deskripsi)}</p>`
        : '<p class="modal-desc-empty">Deskripsi belum tersedia untuk produk ini.</p>';
    const giziContent = document.getElementById('gizi-content');
    if (data.hasGizi) {
        const items = [
            { label:'Kalori', value:data.kalori, unit:'kcal' },
            { label:'Protein', value:data.protein, unit:'g' },
            { label:'Lemak', value:data.lemak, unit:'g' },
            { label:'Karbohidrat', value:data.karbohidrat, unit:'g' },
        ];
        giziContent.innerHTML = `
            <div class="gizi-grid">
                ${items.map(g => g.value !== null ? `
                <div class="gizi-item">
                    <span class="gizi-value">${g.value}</span>
                    <span class="gizi-unit">${g.unit}</span>
                    <span class="gizi-label">${g.label}</span>
                </div>` : '').join('')}
            </div>
            <p class="gizi-note">* Nilai gizi perkiraan per porsi.</p>`;
    } else {
        giziContent.innerHTML = '<p class="gizi-empty">Informasi gizi belum tersedia.</p>';
    }
    const footerEl = document.getElementById('modal-footer');
    if (data.habis) {
        footerEl.innerHTML = `
            <button class="modal-btn-beli nonaktif" disabled><i class="fa-solid fa-ban"></i> Stok Habis</button>`;
    } else {
        footerEl.innerHTML = `
            <button class="modal-btn-beli" onclick="modalTambahKeranjang()"><i class="fa-solid fa-basket-shopping"></i> Tambah ke Keranjang</button>`;
    }
    document.getElementById('modal').scrollTop = 0;
    overlayEl.classList.add('open');
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

function tampilToast(pesan, isError = false) {
    let t = document.getElementById('toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'toast';
        t.style.cssText = 'position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(10px);background:var(--navy-deep);color:var(--white);padding:14px 22px;border-radius:14px;font-size:0.875rem;font-weight:500;border-left:3px solid var(--gold);opacity:0;transition:all 0.3s;z-index:9999;box-shadow:0 16px 50px rgba(0,0,0,0.3);pointer-events:none;white-space:nowrap;';
        document.body.appendChild(t);
    }
    t.textContent = pesan;
    t.style.borderLeftColor = isError ? '#ef4444' : 'var(--gold)';
    t.style.opacity = '1';
    t.style.transform = 'translateX(-50%) translateY(0)';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.style.opacity='0'; t.style.transform='translateX(-50%) translateY(10px)'; }, 3000);
}

function updateCartBadge(totalCart) {
    let badge = document.querySelector('.cart-badge');
    if (totalCart > 0) {
        if (badge) { badge.textContent = totalCart; }
        else {
            const cartLink = document.querySelector('.cart-link');
            if (cartLink) { const nb = document.createElement('span'); nb.className = 'cart-badge'; nb.textContent = totalCart; cartLink.appendChild(nb); }
        }
    } else if (badge) { badge.remove(); }
}

async function tambahKeranjang(btn) {
    const id = btn.dataset.id, stok = parseInt(btn.dataset.stok);
    if (!id || id === '0') { tampilToast('ID produk tidak valid.', true); return; }
    if (stok <= 0) { tampilToast('Stok produk habis!', true); return; }
    btn.disabled = true;
    const origHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    try {
        const form = new FormData();
        form.append('aksi', 'tambah_keranjang');
        form.append('id_produk', id);
        form.append('jumlah', 1);
        form.append('csrf_token', CSRF_TOKEN);
        const res = await fetch('proses_pesanan.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.status === 'ok') { tampilToast('✓ ' + data.pesan); updateCartBadge(data.total_cart); }
        else { tampilToast(data.pesan, true); }
    } catch (e) { tampilToast('Terjadi kesalahan. Coba lagi.', true); }
    finally { btn.disabled = false; btn.innerHTML = origHTML; }
}

async function modalTambahKeranjang() {
    const data = modalActiveProduct;
    if (!data || data.habis) return;
    const btn = document.querySelector('#modal-footer .modal-btn-beli');
    if (!btn) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';
    try {
        const form = new FormData();
        form.append('aksi', 'tambah_keranjang');
        form.append('id_produk', data.id);
        form.append('jumlah', 1);
        form.append('csrf_token', CSRF_TOKEN);
        const res = await fetch('proses_pesanan.php', { method: 'POST', body: form });
        const resp = await res.json();
        if (resp.status === 'ok') { tampilToast('✓ ' + resp.pesan); updateCartBadge(resp.total_cart); tutupModal(); }
        else { tampilToast(resp.pesan, true); btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-basket-shopping"></i> Tambah ke Keranjang'; }
    } catch (e) { tampilToast('Terjadi kesalahan. Coba lagi.', true); btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-basket-shopping"></i> Tambah ke Keranjang'; }
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ══ SOUND PLAYER ══ */
const audio = document.getElementById('bakeryAudio');
const fab = document.getElementById('soundFab'), panel = document.getElementById('soundPanel');
const viz = document.getElementById('spViz'), spStatus = document.getElementById('spStatus');
const fabIcon = document.getElementById('fabIcon'), playIcon = document.getElementById('playIcon');
const btnLoop = document.getElementById('btnLoop'), fillEl = document.getElementById('spProgressFill');
const timeCur = document.getElementById('spTimeCurrent'), timeDur = document.getElementById('spTimeDuration');
const volIcon = document.getElementById('volIcon');
let panelOpen = false, isLooping = true;
audio.volume = 0.4; audio.loop = true;
function fmtTime(s) { if (!s || isNaN(s)) return '0:00'; return Math.floor(s/60) + ':' + (Math.floor(s%60)<10?'0':'') + Math.floor(s%60); }
audio.addEventListener('timeupdate', () => { if (!audio.duration) return; fillEl.style.width = (audio.currentTime/audio.duration*100) + '%'; timeCur.textContent = fmtTime(audio.currentTime); });
audio.addEventListener('loadedmetadata', () => { timeDur.textContent = fmtTime(audio.duration); });
function seekAudio(e) { if (!audio.duration) return; const bar = document.getElementById('spProgressBar'), rect = bar.getBoundingClientRect(); audio.currentTime = Math.max(0, Math.min(1,(e.clientX-rect.left)/rect.width)) * audio.duration; }
function togglePlayPause() { if (audio.paused) audio.play().catch(()=>{}); else audio.pause(); updateUI(); }
function restartAudio() { audio.currentTime=0; if(audio.paused) audio.play().catch(()=>{}); updateUI(); }
function toggleLoop() { isLooping=!isLooping; audio.loop=isLooping; btnLoop.classList.toggle('active',isLooping); }
function setVolume(v) { audio.volume=v/100; document.getElementById('volVal').textContent=v+'%'; volIcon.className = v==0?'fa-solid fa-volume-xmark sp-vol-icon':v<50?'fa-solid fa-volume-low sp-vol-icon':'fa-solid fa-volume-high sp-vol-icon'; }
function toggleSoundPanel() { panelOpen=!panelOpen; panel.classList.toggle('show',panelOpen); fab.classList.toggle('panel-open',panelOpen); fabIcon.className = panelOpen?'fa-solid fa-xmark':'fa-solid fa-music'; if(panelOpen && audio.paused) { audio.play().catch(()=>{}); updateUI(); } }
function updateUI() { const playing=!audio.paused; playIcon.className = playing?'fa-solid fa-pause':'fa-solid fa-play'; fab.classList.toggle('playing',playing); viz.classList.toggle('active',playing); spStatus.textContent = playing?'● LIVE':'PAUSED'; spStatus.classList.toggle('live',playing); if(!panelOpen) fabIcon.className='fa-solid fa-music'; }
audio.addEventListener('play', updateUI);
audio.addEventListener('pause', updateUI);

/* ══════════════════════════════════════
   CHATBOT WIDGET
══════════════════════════════════════ */
let chatOpen = false;
const isMobile = () => window.innerWidth <= 680;

function toggleChat() {
    chatOpen = !chatOpen;
    const panel    = document.getElementById('chatPanel');
    const fab      = document.getElementById('chatFab');
    const notif    = document.getElementById('chatNotif');
    const backdrop = document.getElementById('chatBackdrop');
    const mobile   = isMobile();

    panel.classList.toggle('open', chatOpen);
    notif.classList.add('hide');

    if (mobile) {
        // Mobile: bottom sheet — FAB tersembunyi via CSS .open, backdrop aktif
        fab.classList.toggle('open', chatOpen);
        backdrop.style.display = chatOpen ? 'block' : 'none';
        document.body.style.overflow = chatOpen ? 'hidden' : '';
    } else {
        // Desktop: FAB tetap tampil, ganti icon
        fab.classList.remove('open');
        document.getElementById('chatFabIcon').className = chatOpen ? 'fa-solid fa-xmark' : 'fa-solid fa-comments';
        fab.classList.toggle('active-chat', chatOpen);
        backdrop.style.display = 'none';
        document.body.style.overflow = '';
    }

    if (chatOpen) setTimeout(() => document.getElementById('chatInput').focus(), 100);
}

function chatKey(e) { if (e.key === 'Enter') chatSend(); }
function chatQuick(txt) {
    document.getElementById('chatInput').value = txt;
    closeChatQuick();
    chatSend();
}

/* ══ QUICK REPLY DROPDOWN ══ */
function toggleChatQuick() {
    const dd     = document.getElementById('chatQuickDropdown');
    const toggle = document.getElementById('chatQuickToggle');
    const isOpen = dd.classList.contains('show');
    if (isOpen) closeChatQuick();
    else {
        dd.classList.add('show');
        toggle.classList.add('open');
    }
}
function closeChatQuick() {
    document.getElementById('chatQuickDropdown').classList.remove('show');
    document.getElementById('chatQuickToggle').classList.remove('open');
}
document.addEventListener('click', (e) => {
    const wrap = document.querySelector('.chat-quick-wrap');
    if (wrap && !wrap.contains(e.target)) closeChatQuick();
});

function chatSend() {
    const input = document.getElementById('chatInput');
    const msg = input.value.trim();
    if (!msg) return;
    chatAppend(escHtml(msg), 'cm-user');
    input.value = '';
    const dots = chatTyping();
    const form = new FormData();
    form.append('chat_message', msg);
    fetch('chatbot.php', { method: 'POST', body: form })
        .then(r => r.text())
        .then(reply => { chatRemove(dots); chatAppend(reply, 'cm-bot'); })
        .catch(() => { chatRemove(dots); chatAppend('Maaf, terjadi kesalahan.', 'cm-bot'); });
}

function chatAppend(html, cls) {
    const box = document.getElementById('chatMessages');
    const d = document.createElement('div');
    d.className = 'cm ' + cls;
    d.innerHTML = html;
    box.appendChild(d);
    box.scrollTop = box.scrollHeight;
}
function chatTyping() {
    const box = document.getElementById('chatMessages');
    const d = document.createElement('div');
    d.className = 'typing-dots';
    d.innerHTML = '<span></span><span></span><span></span>';
    box.appendChild(d);
    box.scrollTop = box.scrollHeight;
    return d;
}
function chatRemove(el) { if (el && el.parentNode) el.parentNode.removeChild(el); }
</script>
</body>
</html>
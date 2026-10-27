<?php
session_start();
include 'koneksi.php';

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
<title>Hubungi Kami — Musimah Bakery</title>
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
    --text-muted: #64748b;
    --border:     rgba(201,169,110,0.2);
}

*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    font-family: 'DM Sans', sans-serif;
    background: var(--cream);
    color: var(--navy-deep);
    overflow-x: hidden;
}

@keyframes fadeUp  { from { opacity:0; transform:translateY(22px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeIn  { from { opacity:0; } to { opacity:1; } }
@keyframes growBar { from { width:0; } to { width:120px; } }

/* ═══ HEADER ═══ */
header {
    position: fixed; top: 20px; left: 50%;
    transform: translateX(-50%);
    width: 92%; max-width: 1140px; height: 68px;
    background: rgba(6,13,31,0.9);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(201,169,110,0.15);
    border-radius: 60px;
    display: flex; justify-content: space-between; align-items: center;
    padding: 0 28px; z-index: 1000;
    box-shadow: 0 8px 40px rgba(0,0,0,0.35);
}
.header-left { display: flex; align-items: center; gap: 16px; }
.logo {
    text-decoration: none;
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.9rem; font-weight: 400; font-style: italic;
    color: var(--white); letter-spacing: 1px; line-height: 1; padding-top: 4px;
}
.desktop-nav ul { display: flex; list-style: none; gap: 4px; align-items: center; }
.desktop-nav ul li a {
    text-decoration: none; color: rgba(255,255,255,0.7);
    font-weight: 400; font-size: 0.875rem;
    padding: 6px 14px; border-radius: 30px; transition: all 0.25s;
}
.desktop-nav ul li a:hover,
.desktop-nav ul li a.active { color: var(--white); background: rgba(255,255,255,0.09); }
.nav-has-dropdown { position: relative; }
.dropdown {
    position: absolute; top: calc(100% + 10px); left: 0;
    background: var(--navy-dark);
    border: 1px solid rgba(201,169,110,0.18);
    border-radius: 16px; min-width: 180px;
    list-style: none; padding: 8px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    opacity: 0; visibility: hidden; transform: translateY(6px);
    transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s;
    pointer-events: none; z-index: 999;
}
.nav-has-dropdown:hover > .dropdown,
.nav-has-dropdown:focus-within > .dropdown {
    opacity: 1; visibility: visible; transform: translateY(0); pointer-events: auto;
}
.dropdown li a {
    display: block; padding: 9px 16px; border-radius: 10px;
    color: rgba(255,255,255,0.7) !important; font-size: 0.875rem; background: none !important;
}
.dropdown li a:hover { background: rgba(201,169,110,0.12) !important; color: var(--gold) !important; }
.header-right { display: flex; align-items: center; gap: 10px; }
.cart-link {
    position: relative; color: var(--white); text-decoration: none;
    display: flex; align-items: center; width: 40px; height: 40px; justify-content: center;
    border-radius: 50%; border: 1px solid rgba(255,255,255,0.15); transition: all 0.25s;
}
.cart-link:hover { border-color: var(--gold); background: rgba(201,169,110,0.1); }
.cart-badge {
    position: absolute; top: -4px; right: -4px;
    background: var(--gold); color: var(--navy-deep);
    font-size: 0.6rem; font-weight: 700;
    min-width: 16px; height: 16px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
}
.user-capsule {
    display: flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 40px; padding: 6px 14px 6px 10px;
}
.user-info { display: flex; align-items: center; gap: 7px; color: rgba(255,255,255,0.85); font-size: 0.85rem; white-space: nowrap; }
.btn-logout-small {
    color: rgba(255,255,255,0.4); font-size: 0.85rem; text-decoration: none;
    display: flex; align-items: center;
    border-left: 1px solid rgba(255,255,255,0.12); padding-left: 8px; transition: 0.2s;
}
.btn-logout-small:hover { color: #e11d48; }
.btn-masuk {
    background: var(--gold); color: var(--navy-deep);
    text-decoration: none; padding: 9px 22px;
    border-radius: 40px; font-weight: 600; font-size: 0.85rem; transition: all 0.25s;
}
.btn-masuk:hover { background: var(--gold-light); transform: translateY(-1px); }

/* ═══ HAMBURGER ═══ */
.hamburger { display: none; flex-direction: column; gap: 5px; cursor: pointer; padding: 4px; }
.hamburger span { width: 20px; height: 1.5px; background: var(--white); border-radius: 2px; }

/* ═══ MOBILE SIDEBAR ═══ */
.sidebar-overlay { position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1999;display:none;backdrop-filter:blur(4px); }
.mobile-sidebar {
    position:fixed;top:0;left:-100%;width:290px;height:100%;
    background:var(--navy-dark);z-index:2000;transition:0.4s cubic-bezier(.4,0,.2,1);
    padding:36px 28px;overflow-y:auto;border-right:1px solid rgba(201,169,110,0.12);
}
.sidebar-logo { font-family:'Cormorant Garamond',serif;font-size:2rem;font-weight:400;font-style:italic;color:var(--white); }
.sidebar-nav { list-style:none;margin-top:30px; }
.sidebar-nav li { border-bottom:1px solid rgba(255,255,255,0.06); }
.sidebar-nav li a {
    display:flex;align-items:center;gap:13px;padding:14px 0;
    text-decoration:none;color:rgba(255,255,255,0.8);font-size:0.95rem;transition:0.25s;
}
.sidebar-nav li a:hover { color:var(--gold); }
.sidebar-nav li a i { color:var(--gold);width:18px;text-align:center;font-size:0.85rem; }
.accordion-btn { display:flex;align-items:center;gap:13px;padding:14px 0;color:rgba(255,255,255,0.8);font-size:0.95rem;cursor:pointer; }
.accordion-btn i:first-child { color:var(--gold);width:18px;text-align:center;font-size:0.85rem; }
.mobile-sub-menu { max-height:0;overflow:hidden;list-style:none;background:rgba(0,0,0,0.2);border-radius:10px;transition:0.4s ease; }
.mobile-sub-menu li a { padding-left:34px;font-size:0.875rem;color:rgba(255,255,255,0.5); }
.mobile-sub-menu li a:hover { color:var(--gold); }

/* ═══ PAGE BANNER ═══ */
.page-banner {
    background: var(--navy-deep);
    padding: 155px 0 85px;
    position: relative; overflow: hidden; text-align: center;
}
.page-banner::before {
    content: 'Kontak';
    position: absolute; left: 50%; top: 50%; transform: translate(-50%,-50%);
    font-family: 'Cormorant Garamond', serif;
    font-size: 260px; font-weight: 300; font-style: italic;
    color: rgba(255,255,255,0.022); white-space: nowrap;
    pointer-events: none; user-select: none; line-height: 1;
}
.page-banner::after {
    content: '';
    position: absolute; bottom: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(201,169,110,0.3), transparent);
}
.banner-eyebrow {
    font-size: 0.63rem; letter-spacing: 6px; color: var(--gold);
    text-transform: uppercase; display: block; margin-bottom: 16px;
    opacity: 0; animation: fadeUp 0.7s ease 0.1s forwards;
}
.banner-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: clamp(3.2rem, 6.5vw, 5.8rem);
    font-weight: 300; font-style: italic; color: var(--white);
    line-height: 1; letter-spacing: -1px;
    opacity: 0; animation: fadeUp 0.9s ease 0.25s forwards;
}
.banner-sub {
    font-size: 0.875rem; color: rgba(255,255,255,0.38); margin-top: 12px;
    opacity: 0; animation: fadeUp 0.8s ease 0.4s forwards; letter-spacing: 1px;
}
.banner-ornament {
    display: flex; align-items: center; justify-content: center; gap: 14px;
    margin: 26px auto 0;
    opacity: 0; animation: fadeUp 0.7s ease 0.5s forwards;
}
.orn-line { width: 50px; height: 1px; }
.orn-line.l { background: linear-gradient(90deg, transparent, var(--gold)); }
.orn-line.r { background: linear-gradient(90deg, var(--gold), transparent); }
.orn-diamond { width: 5px; height: 5px; background: var(--gold); transform: rotate(45deg); opacity: 0.7; }

/* ═══ CONTAINER ═══ */
.container { width: 90%; max-width: 1140px; margin: auto; }

/* ═══ SOCIAL SECTION ═══ */
.social-section { padding: 100px 0 60px; }

.section-header { text-align: center; margin-bottom: 60px; }
.section-eyebrow {
    display: inline-block; font-size: 0.63rem; letter-spacing: 6px;
    color: var(--gold); text-transform: uppercase; margin-bottom: 14px;
}
.section-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: clamp(2.4rem, 5vw, 3.5rem); font-weight: 300; font-style: italic;
    color: var(--navy-deep); line-height: 1.1;
}
.section-sub { color: var(--text-muted); font-size: 0.9rem; margin-top: 10px; }

/* Social cards */
.social-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px;
}
.social-card {
    text-decoration: none; color: inherit;
    background: var(--white);
    border: 1px solid rgba(11,23,53,0.08);
    border-radius: 24px; padding: 36px 30px;
    display: flex; flex-direction: column; align-items: flex-start; gap: 18px;
    transition: all 0.4s cubic-bezier(.22,1,.36,1);
    position: relative; overflow: hidden;
    opacity: 0; transform: translateY(28px);
}
.social-card.visible { opacity: 1; transform: translateY(0); }
.social-card::after {
    content: '';
    position: absolute; bottom: 0; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
    opacity: 0; transition: opacity 0.3s;
}
.social-card:hover { transform: translateY(-10px); box-shadow: 0 24px 60px rgba(11,23,53,0.12); }
.social-card:hover::after { opacity: 1; }

.social-icon {
    width: 56px; height: 56px; border-radius: 18px;
    background: var(--navy-deep);
    display: flex; align-items: center; justify-content: center;
    transition: all 0.3s;
}
.social-card:hover .social-icon { background: var(--navy-mid); transform: scale(1.05); }
.social-icon i { font-size: 1.4rem; color: var(--gold); }

.social-card h3 {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.35rem; font-weight: 400; color: var(--navy-deep); margin-bottom: 4px;
}
.social-card p { font-size: 0.875rem; color: var(--text-muted); line-height: 1.6; }
.social-card .sc-arrow {
    margin-top: auto; align-self: flex-end;
    width: 36px; height: 36px; border-radius: 50%;
    border: 1px solid rgba(11,23,53,0.12);
    display: flex; align-items: center; justify-content: center;
    color: var(--navy-mid); font-size: 0.75rem; transition: all 0.25s;
}
.social-card:hover .sc-arrow { background: var(--navy-deep); color: var(--gold); border-color: var(--navy-deep); }

/* ═══ INFO STRIP ═══ */
.info-strip {
    background: var(--navy-deep);
    border-radius: 28px; padding: 50px 60px;
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 40px; margin: 0 0 100px;
    position: relative; overflow: hidden;
}
.info-strip::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(201,169,110,0.4), transparent);
}
.info-item { display: flex; align-items: flex-start; gap: 18px; }
.info-item-icon {
    width: 46px; height: 46px; border-radius: 14px; flex-shrink: 0;
    background: rgba(201,169,110,0.12);
    border: 1px solid rgba(201,169,110,0.2);
    display: flex; align-items: center; justify-content: center;
}
.info-item-icon i { color: var(--gold); font-size: 1rem; }
.info-item-text strong {
    display: block; font-size: 0.7rem; letter-spacing: 2px;
    text-transform: uppercase; color: var(--gold); margin-bottom: 6px;
}
.info-item-text p { color: rgba(255,255,255,0.6); font-size: 0.9rem; line-height: 1.6; }

/* ═══ MAP SECTION ═══ */
.map-section { padding: 0 0 100px; }
.map-header { text-align: center; margin-bottom: 40px; }
.map-wrap {
    border-radius: 28px; overflow: hidden;
    border: 1px solid rgba(11,23,53,0.08);
    box-shadow: 0 8px 50px rgba(11,23,53,0.08);
}
.map-wrap iframe { width: 100%; height: 420px; border: none; display: block; }

/* ═══ FOOTER ═══ */
footer { background: var(--navy-deep); border-radius: 48px 48px 0 0; overflow: hidden; }
.footer-inner { width: 90%; max-width: 1140px; margin: auto; padding: 70px 0 44px; }

/*
   PERUBAHAN: kolom "Navigasi" dan "Bantuan" dihapus dari HTML (lihat di bawah),
   jadi footer-grid sekarang cuma berisi satu kolom (footer-brand). Diganti dari
   grid 3 kolom jadi flex + justify-content:center supaya kolom Muslimah yang
   tersisa otomatis ke tengah, bukan mepet kiri seperti bekas grid sebelumnya.
*/
.footer-grid {
    display: flex;
    justify-content: center;
    margin-bottom: 50px;
}
.footer-brand { text-align: center; max-width: 420px; }
.footer-logo { font-family: 'Cormorant Garamond', serif; font-size: 2.4rem; font-weight: 300; font-style: italic; color: var(--white); display: block; margin-bottom: 14px; }
.footer-desc { color: rgba(255,255,255,0.4); font-size: 0.875rem; line-height: 1.8; margin: 0 auto; }
.footer-bottom { border-top: 1px solid rgba(255,255,255,0.06); padding: 22px 0; text-align: center; color: rgba(255,255,255,0.22); font-size: 0.8rem; }

/* ═══ RESPONSIVE ═══ */
@media (max-width: 960px) {
    header { width: 95%; top: 12px; padding: 0 18px; height: 62px; }
    .desktop-nav { display: none; }
    .hamburger { display: flex; }
    .social-grid { grid-template-columns: 1fr; }
    .info-strip { grid-template-columns: 1fr; padding: 36px 30px; gap: 28px; }
}
@media (max-width: 640px) {
    .social-grid { grid-template-columns: 1fr; }
    .info-strip { border-radius: 20px; }
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

<!-- ═══ MOBILE SIDEBAR ═══ -->
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
        <li><a href="kontak.php" onclick="closeSidebar()" style="color:var(--gold)!important;"><i class="fa-solid fa-phone" style="color:var(--gold)!important;"></i> Kontak</a></li>
        <li style="margin-top:20px;border-top:1px solid rgba(201,169,110,0.2);padding-top:10px;">
            <a href="cart.php" style="color:var(--gold)!important;font-weight:600;">
                <i class="fa-solid fa-basket-shopping" style="color:var(--gold)!important;"></i> Keranjang
            </a>
        </li>
    </ul>
</div>

<!-- ═══ HEADER ═══ -->
<header>
    <div class="header-left">
        <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
        <a href="index.php" class="logo">Musimah</a>
    </div>
    <nav class="desktop-nav">
        <ul>
            <li><a href="index.php">Beranda</a></li>
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
            <li><a href="kontak.php" class="active">Kontak</a></li>
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

<!-- ═══ PAGE BANNER ═══ -->
<div class="page-banner">
    <span class="banner-eyebrow">✦ &nbsp; Sapa Kami &nbsp; ✦</span>
    <h1 class="banner-title">Hubungi Kami</h1>
    <p class="banner-sub">Kami senang mendengar dari Anda — satu pesan, satu langkah lebih dekat</p>
    <div class="banner-ornament">
        <div class="orn-line l"></div>
        <div class="orn-diamond"></div>
        <div class="orn-line r"></div>
    </div>
</div>

<!-- ═══ SOCIAL / CHANNEL SECTION ═══ -->
<section class="social-section">
    <div class="container">
        <div class="section-header">
            <span class="section-eyebrow">✦ &nbsp; Pilih Cara Anda &nbsp; ✦</span>
            <h2 class="section-title">Bicara Lewat Satu Klik</h2>
            <p class="section-sub">Pilih cara ternyaman untuk menyapa dan memesan bersama kami</p>
        </div>
        <div class="social-grid">
            <a href="https://wa.me/6282235998521" class="social-card" target="_blank" rel="noopener">
                <div class="social-icon"><i class="fa-brands fa-whatsapp"></i></div>
                <div>
                    <h3>Pesan via WhatsApp</h3>
                    <p>Respon cepat untuk pemesanan, konsultasi menu, dan pertanyaan seputar produk kami.</p>
                </div>
                <div class="sc-arrow"><i class="fa-solid fa-arrow-right"></i></div>
            </a>
            <a href="https://www.instagram.com/muslimahbakery26?utm_source=qr" class="social-card" target="_blank" rel="noopener">
                <div class="social-icon"><i class="fa-brands fa-instagram"></i></div>
                <div>
                    <h3>Inspirasi Instagram</h3>
                    <p>Lihat katalog kue terbaru, behind-the-scenes dapur, dan promo spesial setiap harinya.</p>
                </div>
                <div class="sc-arrow"><i class="fa-solid fa-arrow-right"></i></div>
            </a>
            <a href="https://maps.google.com/?q=-6.279620,107.014897" class="social-card" target="_blank" rel="noopener">
                <div class="social-icon"><i class="fa-solid fa-location-dot"></i></div>
                <div>
                    <h3>Mampir ke Dapur</h3>
                    <p>Kunjungi langsung toko kami di Bekasi dan pilih kue favorit Anda secara langsung.</p>
                </div>
                <div class="sc-arrow"><i class="fa-solid fa-arrow-right"></i></div>
            </a>
        </div>
    </div>
</section>

<!-- ═══ INFO STRIP ═══ -->
<div class="container">
    <div class="info-strip">
        <div class="info-item">
            <div class="info-item-icon"><i class="fa-solid fa-location-dot"></i></div>
            <div class="info-item-text">
                <strong>Alamat</strong>
                <p>Jl. Kampung Rawasapi, Mustikasari,<br>Kec. Mustika Jaya, Bekasi 17157</p>
            </div>
        </div>
        <div class="info-item">
            <div class="info-item-icon"><i class="fa-brands fa-whatsapp"></i></div>
            <div class="info-item-text">
                <strong>WhatsApp</strong>
                <p>+62 822-3599-8521<br>Senin – Sabtu, 08.00 – 20.00</p>
            </div>
        </div>
        <div class="info-item">
            <div class="info-item-icon"><i class="fa-solid fa-clock"></i></div>
            <div class="info-item-text">
                <strong>Jam Operasional</strong>
                <p>Senin – Sabtu: 08.00 – 20.00<br>Minggu: 09.00 – 17.00</p>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MAP SECTION ═══ -->
<section class="map-section">
    <div class="container">
        <div class="map-header">
            <span class="section-eyebrow">✦ &nbsp; Temukan Kami &nbsp; ✦</span>
            <h2 class="section-title" style="margin-top:10px;">Lokasi Toko</h2>
        </div>
        <div class="map-wrap">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3965.879104072328!2d107.01489717370085!3d-6.279620861466405!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e698e0fd68fca43%3A0x29d98db62414c7ac!2sJl.%20Kp.%20Rw.%20Sapi%2C%20Mustikasari%2C%20Kec.%20Mustika%20Jaya%2C%20Kota%20Bks%2C%20Jawa%20Barat%2017157!5e0!3m2!1sid!2sid"
                loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </div>
</section>

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

<!-- ═══ FOOTER ═══ -->
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

<script>
    // Sidebar
    const hamburger = document.getElementById('hamburger');
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('overlay');
    const closeBtn  = document.getElementById('closeBtn');

    hamburger.onclick = () => { sidebar.style.left = '0'; overlay.style.display = 'block'; };
    function closeSidebar() { sidebar.style.left = '-100%'; overlay.style.display = 'none'; }
    closeBtn.onclick = closeSidebar;
    overlay.onclick  = closeSidebar;

    // Mobile accordion
    const mobileAccordionBtn = document.getElementById('mobileAccordionBtn');
    const mobileSub   = document.getElementById('mobileSub');
    const mobileArrow = document.getElementById('mobileArrow');
    mobileAccordionBtn.onclick = () => {
        const open = mobileSub.style.maxHeight && mobileSub.style.maxHeight !== '0px';
        mobileSub.style.maxHeight = open ? '0px' : '400px';
        mobileArrow.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
    };

    // Scroll reveal
    const revealEls = document.querySelectorAll('.social-card');
    const io = new IntersectionObserver((entries) => {
        entries.forEach((e, i) => {
            if (e.isIntersecting) {
                setTimeout(() => e.target.classList.add('visible'), i * 100);
                io.unobserve(e.target);
            }
        });
    }, { threshold: 0.1 });
    revealEls.forEach(el => io.observe(el));

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
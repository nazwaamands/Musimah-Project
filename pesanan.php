<?php
include 'koneksi.php';
session_start();

// Proteksi Admin
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

// ── MIGRASI OTOMATIS: pastikan enum status_pesanan sudah punya 'Dibatalkan' ──
$cek_kolom = mysqli_query($conn, "SHOW COLUMNS FROM pesanan LIKE 'status_pesanan'");
if ($cek_kolom) {
    $info_kolom = mysqli_fetch_assoc($cek_kolom);
    if ($info_kolom && strpos($info_kolom['Type'], "'Dibatalkan'") === false) {
        mysqli_query($conn, "ALTER TABLE pesanan MODIFY status_pesanan ENUM('Pending','Diproses','Dikirim','Selesai','Dibatalkan') DEFAULT 'Pending'");
    }
}

// Auto-batalkan pesanan Pending yang sudah lewat 24 jam tanpa pembayaran
include_once __DIR__ . '/auto_expire.php';
auto_expire_pesanan($conn);

const STATUS_PESANAN_DIIZINKAN = ['Pending', 'Diproses', 'Dikirim', 'Selesai', 'Dibatalkan'];

// UPDATE STATUS PESANAN
$pesan_error_status = '';
if (isset($_POST['update_status'])) {
    $id_p        = mysqli_real_escape_string($conn, $_POST['id_pesanan']);
    $status_baru = $_POST['status_pesanan'] ?? '';

    if (!in_array($status_baru, STATUS_PESANAN_DIIZINKAN, true)) {
        $pesan_error_status = 'Status pesanan tidak valid.';
    } else {
        // FIX: cek status pesanan SAAT INI dulu sebelum mengizinkan perubahan.
        // Kalau pesanan sudah berstatus "Selesai", kunci — tidak boleh diubah
        // ke status apa pun lagi (Pending/Diproses/Dikirim/Dibatalkan), bahkan
        // kalau permintaan update dikirim manual lewat luar form (bukan cuma
        // mengandalkan dropdown yang di-disable di tampilan).
        $cek_status = mysqli_query($conn, "SELECT status_pesanan FROM pesanan WHERE id_pesanan='$id_p'");
        $data_status = $cek_status ? mysqli_fetch_assoc($cek_status) : null;

        if (!$data_status) {
            $pesan_error_status = 'Pesanan tidak ditemukan.';
        } elseif (strtolower($data_status['status_pesanan']) === 'selesai') {
            $pesan_error_status = 'Pesanan yang sudah berstatus "Selesai" tidak dapat diubah statusnya lagi.';
        } else {
            $status_baru_esc = mysqli_real_escape_string($conn, $status_baru);
            $update = mysqli_query($conn, "UPDATE pesanan SET status_pesanan='$status_baru_esc' WHERE id_pesanan='$id_p'");

            if ($update) {
                header("Location: pesanan.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
                exit;
            } else {
                $pesan_error_status = 'Gagal memperbarui status pesanan.';
            }
        }
    }
}

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : "";

// STATS
$q_total = mysqli_query($conn, "SELECT COUNT(*) as total FROM pesanan");
$total_pesanan = $q_total ? (mysqli_fetch_assoc($q_total)['total'] ?? 0) : 0;

$q_pending = mysqli_query($conn, "SELECT COUNT(*) as total FROM pesanan WHERE status_pesanan = 'Pending'");
$total_pending = $q_pending ? (mysqli_fetch_assoc($q_pending)['total'] ?? 0) : 0;

$q_selesai = mysqli_query($conn, "SELECT COUNT(*) as total FROM pesanan WHERE status_pesanan = 'Selesai'");
$total_selesai = $q_selesai ? (mysqli_fetch_assoc($q_selesai)['total'] ?? 0) : 0;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pesanan — Musimah Bakery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary       : #1e3a8a;
            --primary-light : #3b82f6;
            --dark          : #0f172a;
            --bg            : #f8fafc;
            --white         : #ffffff;
            --text-main     : #1e293b;
            --text-muted    : #64748b;
            --sidebar-w     : 260px;
            --ease          : cubic-bezier(0.4, 0, 0.2, 1);
        }

        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; outline:none; }
        body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text-main); min-height:100vh; overflow-x:hidden; }

        .overlay {
            position:fixed; inset:0;
            background:rgba(0,0,0,.5); backdrop-filter:blur(3px);
            z-index:800; opacity:0; visibility:hidden;
            transition:.3s var(--ease);
        }
        .overlay.open { opacity:1; visibility:visible; }

        .sidebar {
            width:var(--sidebar-w); background:var(--dark);
            position:fixed; top:0; left:0; height:100vh;
            padding:30px 20px; z-index:900;
            display:flex; flex-direction:column;
            transition:transform .3s var(--ease); overflow-y:auto;
        }
        .sidebar::-webkit-scrollbar { width:4px; }
        .sidebar::-webkit-scrollbar-thumb { background:rgba(255,255,255,.1); border-radius:4px; }

        .sidebar-close {
            display:none; position:absolute; top:16px; right:16px;
            background:rgba(255,255,255,.12); border:none; color:#fff;
            width:32px; height:32px; border-radius:50%; cursor:pointer;
            align-items:center; justify-content:center; font-size:15px; transition:.2s;
        }
        .sidebar-close:hover { background:var(--primary-light); }

        .sidebar-brand { display:flex; align-items:center; gap:12px; padding-left:10px; margin-bottom:40px; }
        .brand-dot {
            width:10px; height:10px; background:var(--primary-light);
            border-radius:50%; box-shadow:0 0 10px var(--primary-light); flex-shrink:0;
        }
        .brand-text { font-family:'Plus Jakarta Sans',sans-serif; font-size:20px; color:#fff; letter-spacing:.4px; }

        .sidebar-menu { list-style:none; flex-grow:1; }
        .sidebar-menu li { margin-bottom:4px; }
        .sidebar-menu a {
            color:#94a3b8; text-decoration:none;
            display:flex; align-items:center; gap:12px;
            padding:12px 16px; border-radius:12px;
            font-size:14px; font-weight:500; transition:.2s;
        }
        .sidebar-menu a i { width:20px; font-size:16px; }
        .sidebar-menu a:hover { color:#fff; background:rgba(255,255,255,.06); }
        .sidebar-menu a.active {
            background:var(--primary); color:#fff;
            box-shadow:0 4px 12px rgba(0,0,0,.25);
        }

        .logout-section { margin-top:auto; padding-top:20px; border-top:1px solid rgba(255,255,255,.08); }
        .logout-btn {
            color:#fca5a5 !important; text-decoration:none;
            display:flex; align-items:center; gap:12px;
            padding:12px 16px; font-size:14px;
            border-radius:12px; transition:.2s;
        }
        .logout-btn:hover { background:rgba(252,165,165,.08); }

        .main-content { margin-left:var(--sidebar-w); padding:40px; min-height:100vh; transition:margin .3s var(--ease); }

        header { display:flex; justify-content:space-between; align-items:center; margin-bottom:40px; }
        .header-left { display:flex; align-items:center; gap:16px; }

        .menu-toggle {
            display:none; background:var(--white); border:none;
            width:42px; height:42px; border-radius:12px; cursor:pointer;
            align-items:center; justify-content:center;
            font-size:18px; color:var(--dark);
            box-shadow:0 2px 10px rgba(0,0,0,.06); transition:.2s;
        }
        .menu-toggle:hover { background:#f1f5f9; }

        .header-title h1 { font-size:28px; font-weight:700; color:var(--dark); }
        .admin-profile {
            display:flex; align-items:center; gap:12px;
            background:var(--white); padding:6px 16px 6px 6px;
            border-radius:50px; box-shadow:0 2px 10px rgba(0,0,0,.05);
        }
        .profile-icon {
            width:34px; height:34px; border-radius:50%;
            background:var(--primary); color:#fff;
            display:flex; align-items:center; justify-content:center;
            font-size:12px; font-weight:700;
        }

        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:24px; margin-bottom:32px; }
        .stat-card {
            background:var(--white); padding:24px; border-radius:20px;
            display:flex; justify-content:space-between; align-items:center;
            box-shadow:0 4px 20px rgba(0,0,0,.03); border:1px solid #f1f5f9;
        }
        .stat-info p { font-size:13px; color:var(--text-muted); font-weight:500; margin-bottom:4px; }
        .stat-info h3 { font-size:26px; font-weight:700; color:var(--dark); }
        .stat-icon {
            width:50px; height:50px; background:#eff6ff; color:var(--primary);
            border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:20px;
            flex-shrink:0;
        }

        .table-section {
            background:var(--white); border-radius:24px; padding:30px;
            box-shadow:0 4px 20px rgba(0,0,0,.03); border:1px solid #f1f5f9;
            margin-bottom:40px;
        }
        .section-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
        .section-header h2 { font-size:18px; font-weight:700; }
        .count-pill { background:#eff6ff; color:var(--primary); font-size:12px; font-weight:700; padding:6px 14px; border-radius:50px; }

        .search-form { display:flex; gap:10px; margin-bottom:20px; }
        .search-wrap { position:relative; flex:1; max-width:360px; }
        .search-wrap input {
            width:100%; padding:10px 15px 10px 40px;
            border:1px solid #e2e8f0; border-radius:12px;
            font-size:14px; font-family:'Inter',sans-serif;
            background:var(--bg); transition:.3s;
        }
        .search-wrap input:focus { border-color:var(--primary-light); box-shadow:0 0 0 4px rgba(59,130,246,.1); background:#fff; }
        .search-wrap i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--text-muted); }
        .btn-search {
            background:var(--primary); color:#fff;
            border:none; padding:10px 20px; border-radius:12px;
            font-size:14px; font-weight:600; cursor:pointer; transition:.2s;
        }
        .btn-search:hover { background:#1d4ed8; }

        .table-responsive { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:960px; }
        table th {
            text-align:left; padding:14px 16px;
            color:var(--text-muted); font-size:12px;
            text-transform:uppercase; letter-spacing:.5px;
            border-bottom:1px solid #f1f5f9; font-weight:600;
        }
        table td { padding:14px 16px; border-bottom:1px solid #f1f5f9; font-size:14px; vertical-align:middle; }
        table tbody tr.order-row:hover { background:#f8fafc; }

        .chevron-cell { width:34px; }
        .chevron-btn {
            width:28px; height:28px; border-radius:8px; border:none; background:#f1f5f9;
            color:var(--text-muted); cursor:pointer; display:flex; align-items:center; justify-content:center;
            transition:transform .25s var(--ease), background .2s, color .2s; font-size:12px;
        }
        .chevron-btn:hover { background:#eff6ff; color:var(--primary); }
        .chevron-btn.rotated { transform:rotate(180deg); background:#eff6ff; color:var(--primary); }

        .status-pill { padding:4px 10px; border-radius:6px; font-size:11px; font-weight:700; text-transform:uppercase; white-space:nowrap; }
        .status-pending    { background:#fff7ed; color:#c2410c; }
        .status-diproses   { background:#eff6ff; color:#2563eb; }
        .status-dikirim    { background:#f5f3ff; color:#7c3aed; }
        .status-selesai    { background:#dcfce7; color:#15803d; }
        .status-dibatalkan { background:#fee2e2; color:#ef4444; }

        .method-badge {
            background:#f1f5f9; color:#475569;
            padding:3px 8px; border-radius:5px;
            font-size:11px; font-weight:600; text-transform:uppercase;
        }

        .img-bukti {
            width:42px; height:42px; object-fit:cover;
            border-radius:8px; cursor:pointer;
            border:2px solid #e2e8f0; transition:.2s;
        }
        .img-bukti:hover { border-color:var(--primary-light); transform:scale(1.05); }

        .aksi-cell { display:flex; flex-direction:column; gap:6px; align-items:flex-start; }
        .status-select-row { display:flex; gap:6px; align-items:center; }
        .status-select {
            font-size:12px; padding:7px 10px; border:1.5px solid #e2e8f0; border-radius:9px;
            font-family:'Inter',sans-serif; background:var(--bg); color:var(--text-main);
            cursor:pointer; transition:.2s; min-width:118px;
        }
        .status-select:focus { border-color:var(--primary-light); outline:none; box-shadow:0 0 0 3px rgba(59,130,246,.1); }
        .btn-save-status {
            width:32px; height:32px; border-radius:9px; border:none; background:var(--primary);
            color:#fff; cursor:pointer; transition:.2s; display:flex; align-items:center; justify-content:center;
            font-size:13px; flex-shrink:0;
        }
        .btn-save-status:hover { background:#1d4ed8; transform:scale(1.05); }

        /* FIX: tampilan terkunci untuk pesanan yang sudah Selesai */
        .locked-status {
            display:flex; align-items:center; gap:7px;
            font-size:11.5px; font-weight:600; color:#15803d;
            background:#f0fdf4; border:1px solid #bbf7d0;
            padding:8px 12px; border-radius:9px; min-width:118px;
        }
        .locked-status i { font-size:11px; }

        .empty-state { text-align:center; padding:60px 20px; color:var(--text-muted); }
        .empty-state i { font-size:48px; margin-bottom:16px; display:block; opacity:.3; }

        tr.detail-row { display:none; }
        tr.detail-row.open { display:table-row; }
        tr.detail-row td { padding:0; border-bottom:1px solid #f1f5f9; }
        .detail-panel { background:#f8fafc; padding:22px 26px; animation:panelIn .25s var(--ease); }
        @keyframes panelIn { from{opacity:0; transform:translateY(-4px);} to{opacity:1; transform:translateY(0);} }

        .detail-section-label {
            font-size:10.5px; text-transform:uppercase; letter-spacing:.8px; font-weight:800;
            color:var(--text-muted); margin-bottom:12px; display:flex; align-items:center; gap:7px;
        }
        .detail-section-label i { color:var(--primary); }

        .kirim-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:18px; margin-bottom:22px; }
        .kirim-item .lbl { font-size:10px; text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted); font-weight:700; margin-bottom:5px; }
        .kirim-item .val { font-size:13.5px; font-weight:700; color:var(--dark); }

        .produk-list { background:var(--white); border-radius:14px; border:1px solid #e2e8f0; overflow:hidden; }
        .produk-item { display:flex; align-items:center; gap:12px; padding:12px 16px; border-bottom:1px solid #f1f5f9; }
        .produk-item:last-child { border-bottom:none; }
        .produk-icon { width:34px; height:34px; border-radius:9px; background:#eff6ff; color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
        .produk-name { font-weight:700; flex:1; font-size:13.5px; }
        .qty-badge { background:#eff6ff; color:var(--primary); font-size:11px; font-weight:800; padding:3px 9px; border-radius:20px; margin-right:16px; white-space:nowrap; }
        .produk-subtotal { font-weight:800; color:var(--dark); min-width:90px; text-align:right; }

        #imgModal {
            display:none; position:fixed; z-index:1000;
            inset:0; background:rgba(0,0,0,.85);
            align-items:center; justify-content:center;
            backdrop-filter:blur(4px);
        }
        #imgModal img { max-width:80%; max-height:80vh; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,.5); }
        #imgModal .close-modal {
            position:absolute; top:20px; right:20px;
            background:rgba(255,255,255,.15); color:#fff;
            width:38px; height:38px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; font-size:18px; transition:.2s;
        }
        #imgModal .close-modal:hover { background:rgba(255,255,255,.3); }

        @media (max-width:1024px) {
            .sidebar { transform:translateX(-100%); }
            .sidebar.open { transform:translateX(0); }
            .sidebar.open .sidebar-close { display:flex; }
            .main-content { margin-left:0; padding:24px 20px; }
            .menu-toggle { display:inline-flex; }
            .header-title h1 { font-size:22px; }
        }
        @media (max-width:480px) {
            .main-content { padding:16px 14px; }
            header { margin-bottom:20px; }
            .stats-grid { gap:12px; }
            .stat-card { padding:18px; }
            .stat-info h3 { font-size:22px; }
            .admin-profile span { display:none; }
            .header-title h1 { font-size:19px; }
            .table-section { padding:16px 12px; border-radius:18px; }
            .kirim-grid { grid-template-columns:1fr; gap:12px; }
        }
    </style>
</head>
<body>

<div id="imgModal" onclick="closeModal(event)">
    <div class="close-modal" onclick="document.getElementById('imgModal').style.display='none'">
        <i class="fa-solid fa-xmark"></i>
    </div>
    <img id="modalImg" src="" alt="Bukti Pembayaran">
</div>

<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<nav class="sidebar" id="sidebar" aria-label="Navigasi admin">
    <button class="sidebar-close" onclick="closeSidebar()" aria-label="Tutup sidebar">
        <i class="fa-solid fa-xmark"></i>
    </button>

    <div class="sidebar-brand">
        <div class="brand-dot"></div>
        <div class="brand-text">MusimahAdmin</div>
    </div>

    <ul class="sidebar-menu">
        <li><a href="admin.php"><i class="fa-solid fa-grid-2"></i><span>Dashboard</span></a></li>
        <li><a href="kelola_produk.php"><i class="fa-solid fa-cake-candles"></i><span>Produk Kue</span></a></li>
        <li><a href="pesanan.php" class="active"><i class="fa-solid fa-cart-shopping"></i><span>Pesanan</span></a></li>
        <li><a href="pelanggan.php"><i class="fa-solid fa-users"></i><span>Pelanggan</span></a></li>
        <li><a href="laporan.php"><i class="fa-solid fa-chart-line"></i><span>Laporan</span></a></li>
    </ul>

    <div class="logout-section">
        <a href="logout.php" class="logout-btn">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            <span>Keluar</span>
        </a>
    </div>
</nav>

<main class="main-content">
    <header>
        <div class="header-left">
            <button class="menu-toggle" onclick="openSidebar()" aria-label="Buka sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="header-title"><h1>Manajemen Pesanan</h1></div>
        </div>
        <div class="admin-profile">
            <div class="profile-icon"><?= strtoupper(substr($_SESSION['user'],0,2)) ?></div>
            <span style="font-weight:600;font-size:14px;"><?= htmlspecialchars($_SESSION['user']) ?></span>
        </div>
    </header>

    <?php if ($pesan_error_status): ?>
    <div style="background:#fee2e2;color:#b91c1c;border-left:4px solid #ef4444;padding:14px 20px;border-radius:12px;margin-bottom:24px;font-size:14px;font-weight:500;display:flex;align-items:center;gap:10px;">
        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($pesan_error_status) ?>
    </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info"><p>Total Pesanan</p><h3><?= $total_pesanan ?></h3></div>
            <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><p>Menunggu Konfirmasi</p><h3><?= $total_pending ?></h3></div>
            <div class="stat-icon" style="background:#fff7ed;color:#ea580c;"><i class="fa-solid fa-clock"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><p>Pesanan Selesai</p><h3><?= $total_selesai ?></h3></div>
            <div class="stat-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fa-solid fa-circle-check"></i></div>
        </div>
    </div>

    <div class="table-section">
        <div class="section-header">
            <h2>Daftar Pesanan</h2>
            <span class="count-pill"><?= $total_pesanan ?> Pesanan</span>
        </div>

        <form class="search-form" method="GET">
            <div class="search-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="search" placeholder="Cari ID atau nama pelanggan..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn-search">Cari</button>
        </form>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th class="chevron-cell"></th>
                        <th>ID</th>
                        <th>Waktu</th>
                        <th>Pelanggan</th>
                        <th>Total</th>
                        <th>Metode</th>
                        <th>Bukti</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $where = "";
                    if ($search) {
                        $where = "WHERE pesanan.id_pesanan LIKE '%$search%' OR users.username LIKE '%$search%'";
                    }

                    $sql = "SELECT pesanan.*, users.username FROM pesanan 
                            LEFT JOIN users ON pesanan.id_user = users.id 
                            $where ORDER BY id_pesanan DESC";
                    
                    $query = mysqli_query($conn, $sql);
                    $row_count = 0;

                    while ($query && ($row = mysqli_fetch_assoc($query))):
                        $row_count++;
                        $id = (int)$row['id_pesanan'];

                        $nama = !empty($row['username']) ? htmlspecialchars($row['username']) : "Guest";

                        $status_lower = strtolower($row['status_pesanan']);
                        if ($status_lower === 'selesai') {
                            $pill = 'status-selesai';
                        } elseif ($status_lower === 'dikirim') {
                            $pill = 'status-dikirim';
                        } elseif ($status_lower === 'diproses') {
                            $pill = 'status-diproses';
                        } elseif ($status_lower === 'dibatalkan') {
                            $pill = 'status-dibatalkan';
                        } else {
                            $pill = 'status-pending';
                        }

                        // FIX: flag apakah pesanan ini sudah final/terkunci
                        $terkunci = $status_lower === 'selesai';

                        $metode = !empty($row['metode_pembayaran']) ? strtoupper($row['metode_pembayaran']) : "-";
                        $bukti  = $row['bukti_pembayaran'];
                        $has_bukti = !empty($bukti) && file_exists(__DIR__ . "/uploads/bukti/" . $bukti);

                        $nama_penerima = htmlspecialchars($row['nama_penerima'] ?? '');
                        $no_hp         = htmlspecialchars($row['no_hp'] ?? '');
                        $alamat_full   = trim(($row['alamat'] ?? '') . ', ' . ($row['kota'] ?? '') . ' ' . ($row['kodepos'] ?? ''), ', ');
                    ?>
                    <tr class="order-row">
                        <td class="chevron-cell">
                            <button type="button" class="chevron-btn" onclick="toggleDetail(<?= $id ?>)" aria-label="Lihat detail pesanan">
                                <i class="fa-solid fa-chevron-down"></i>
                            </button>
                        </td>
                        <td><strong style="color:var(--primary);">#<?= $id ?></strong></td>
                        <td>
                            <div style="font-weight:500;"><?= date('d M Y', strtotime($row['tanggal_pesan'])) ?></div>
                            <small style="color:var(--text-muted);"><?= date('H:i', strtotime($row['tanggal_pesan'])) ?> WIB</small>
                        </td>
                        <td><?= $nama ?></td>
                        <td style="color:var(--primary); font-weight:700;">
                            Rp <?= number_format($row['total_bayar'], 0, ',', '.') ?>
                        </td>
                        <td>
                            <span class="method-badge"><?= $metode ?></span>
                        </td>
                        <td>
                            <?php if ($has_bukti): ?>
                                <img src="uploads/bukti/<?= htmlspecialchars($bukti) ?>" 
                                     class="img-bukti" 
                                     onclick="previewImg('uploads/bukti/<?= htmlspecialchars($bukti) ?>')" 
                                     title="Klik untuk perbesar">
                            <?php else: ?>
                                <span style="color:#cbd5e1; font-size:12px; font-style:italic;">Belum upload</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-pill <?= $pill ?>"><?= htmlspecialchars($row['status_pesanan']) ?></span>
                        </td>
                        <td>
                            <?php if ($terkunci): ?>
                                <!-- FIX: kalau sudah Selesai, tampilkan status terkunci (bukan dropdown), tidak bisa diedit lagi -->
                                <div class="locked-status" title="Pesanan yang sudah Selesai tidak dapat diubah lagi">
                                    <i class="fa-solid fa-lock"></i> Terkunci
                                </div>
                            <?php else: ?>
                            <form method="POST" class="aksi-cell">
                                <div class="status-select-row">
                                    <input type="hidden" name="id_pesanan" value="<?= $id ?>">
                                    <select name="status_pesanan" class="status-select">
                                        <option value="Pending"    <?= $status_lower === 'pending'    ? 'selected' : '' ?>>Pending</option>
                                        <option value="Diproses"   <?= $status_lower === 'diproses'   ? 'selected' : '' ?>>Diproses</option>
                                        <option value="Dikirim"    <?= $status_lower === 'dikirim'    ? 'selected' : '' ?>>Dikirim</option>
                                        <option value="Selesai"    <?= $status_lower === 'selesai'    ? 'selected' : '' ?>>Selesai</option>
                                        <option value="Dibatalkan" <?= $status_lower === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                                    </select>
                                    <button type="submit" name="update_status" value="1" class="btn-save-status" title="Simpan status">
                                        <i class="fa-solid fa-floppy-disk"></i>
                                    </button>
                                </div>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr class="detail-row" id="detail-<?= $id ?>">
                        <td colspan="9">
                            <div class="detail-panel">
                                <div class="detail-section-label"><i class="fa-solid fa-truck"></i> Data Pengiriman</div>
                                <div class="kirim-grid">
                                    <div class="kirim-item">
                                        <div class="lbl">Nama Penerima</div>
                                        <div class="val"><?= $nama_penerima !== '' ? $nama_penerima : '<span style="color:#cbd5e1;font-weight:500;font-style:italic;">Tidak diisi</span>' ?></div>
                                    </div>
                                    <div class="kirim-item">
                                        <div class="lbl">Nomor HP</div>
                                        <div class="val"><?= $no_hp !== '' ? $no_hp : '<span style="color:#cbd5e1;font-weight:500;font-style:italic;">Tidak diisi</span>' ?></div>
                                    </div>
                                    <div class="kirim-item">
                                        <div class="lbl">Alamat Lengkap</div>
                                        <div class="val"><?= ($alamat_full !== '' && $alamat_full !== ',') ? htmlspecialchars($alamat_full) : '<span style="color:#cbd5e1;font-weight:500;font-style:italic;">Tidak diisi</span>' ?></div>
                                    </div>
                                </div>

                                <div class="detail-section-label"><i class="fa-solid fa-cake-candles"></i> Detail Produk</div>
                                <div class="produk-list">
                                <?php
                                    $dq = mysqli_query($conn, "SELECT * FROM detail_pesanan WHERE id_pesanan='" . $id . "'");
                                    if ($dq && mysqli_num_rows($dq) > 0):
                                        while ($item = mysqli_fetch_assoc($dq)):
                                ?>
                                    <div class="produk-item">
                                        <div class="produk-icon"><i class="fa-solid fa-cake-candles"></i></div>
                                        <div class="produk-name"><?= htmlspecialchars($item['nama_produk']) ?></div>
                                        <div class="qty-badge">x<?= (int)$item['jumlah'] ?></div>
                                        <div class="produk-subtotal">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></div>
                                    </div>
                                <?php
                                        endwhile;
                                    else:
                                ?>
                                    <div class="produk-item" style="color:#cbd5e1;font-style:italic;">Tidak ada produk tercatat.</div>
                                <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>

                    <?php if ($row_count === 0): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <i class="fa-solid fa-box-open"></i>
                                <p>Belum ada data pesanan<?= $search ? ' yang cocok dengan pencarian' : '' ?></p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }
    window.addEventListener('resize', () => {
        if (window.innerWidth > 1024) closeSidebar();
    });

    function previewImg(src) {
        document.getElementById('modalImg').src = src;
        document.getElementById('imgModal').style.display = 'flex';
    }
    function closeModal(e) {
        if (e.target === document.getElementById('imgModal')) {
            document.getElementById('imgModal').style.display = 'none';
        }
    }

    function toggleDetail(id) {
        const panel = document.getElementById('detail-' + id);
        const btn = event.currentTarget;
        const isOpen = panel.classList.contains('open');

        document.querySelectorAll('tr.detail-row.open').forEach(p => p.classList.remove('open'));
        document.querySelectorAll('.chevron-btn.rotated').forEach(b => b.classList.remove('rotated'));

        if (!isOpen) {
            panel.classList.add('open');
            btn.classList.add('rotated');
        }
    }
</script>
</body>
</html>
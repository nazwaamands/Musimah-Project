<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

$q_produk = mysqli_query($conn, "SELECT COUNT(*) as total FROM produk");
$total_produk = $q_produk ? (mysqli_fetch_assoc($q_produk)['total'] ?? 0) : 0;

$q_pesanan = mysqli_query($conn, "SELECT COUNT(*) as total FROM pesanan");
$total_pesanan = $q_pesanan ? (mysqli_fetch_assoc($q_pesanan)['total'] ?? 0) : 0;

$q_user = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
$total_user = $q_user ? (mysqli_fetch_assoc($q_user)['total'] ?? 0) : 0;

$q_income = mysqli_query($conn, "SELECT SUM(total_bayar) as total FROM pesanan WHERE status_pesanan = 'Selesai'");
$total_pendapatan = $q_income ? (mysqli_fetch_assoc($q_income)['total'] ?? 0) : 0;

$q_notif = mysqli_query($conn, "SELECT COUNT(*) as total FROM pesanan WHERE status_pesanan = 'Pending'");
$notif_count = $q_notif ? (mysqli_fetch_assoc($q_notif)['total'] ?? 0) : 0;

// Ambil 10 pesanan terbaru beserta nama user
// FIX: sebelumnya ORDER BY ... ASC malah mengambil 10 pesanan TERLAMA, bukan terbaru.
$q_pesanan_list = mysqli_query($conn,
    "SELECT p.id_pesanan, p.tanggal_pesan, p.total_bayar, p.status_pesanan,
            u.username
     FROM pesanan p
     LEFT JOIN users u ON p.id_user = u.id
     ORDER BY tanggal_pesan DESC, id_pesanan DESC
     LIMIT 10"
);

$chart_labels = [];
$chart_data   = [];

for ($i = 5; $i >= 0; $i--) {
    $bulan   = date('Y-m', strtotime("-$i months"));
    $label   = date('M Y', strtotime("-$i months"));
    $q_bulan = mysqli_query($conn,
        "SELECT COALESCE(SUM(total_bayar), 0) as total
         FROM pesanan
         WHERE status_pesanan = 'Selesai'
           AND DATE_FORMAT(tanggal_pesan, '%Y-%m') = '$bulan'"
    );
    $row_bulan      = $q_bulan ? mysqli_fetch_assoc($q_bulan) : ['total' => 0];
    $chart_labels[] = $label;
    $chart_data[]   = (float) ($row_bulan['total'] ?? 0);
}

$chart_labels_json = json_encode($chart_labels);
$chart_data_json   = json_encode($chart_data);

$pendapatan_bulan_ini  = $chart_data[5] ?? 0;
$pendapatan_bulan_lalu = $chart_data[4] ?? 0;
if ($pendapatan_bulan_lalu > 0) {
    $growth_pct = (($pendapatan_bulan_ini - $pendapatan_bulan_lalu) / $pendapatan_bulan_lalu) * 100;
} else {
    $growth_pct = $pendapatan_bulan_ini > 0 ? 100 : 0;
}
$growth_positive = $growth_pct >= 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Musimah Bakery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
            --bottomnav-h   : 64px;
            --ease          : cubic-bezier(0.4, 0, 0.2, 1);
        }

        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; outline:none; }
        body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text-main); min-height:100vh; overflow-x:hidden; }

        /* ===== OVERLAY ===== */
        .overlay {
            position:fixed; inset:0;
            background:rgba(0,0,0,.5);
            backdrop-filter:blur(3px);
            z-index:800;
            opacity:0; visibility:hidden;
            transition:.3s var(--ease);
        }
        .overlay.open { opacity:1; visibility:visible; }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width:var(--sidebar-w);
            background:var(--dark);
            position:fixed; top:0; left:0; height:100vh;
            padding:30px 20px;
            z-index:900;
            display:flex; flex-direction:column;
            transition:transform .3s var(--ease);
            overflow-y:auto;
        }
        .sidebar::-webkit-scrollbar { width:4px; }
        .sidebar::-webkit-scrollbar-thumb { background:rgba(255,255,255,.1); border-radius:4px; }

        .sidebar-close {
            display:none;
            position:absolute; top:16px; right:16px;
            background:rgba(255,255,255,.12); border:none; color:#fff;
            width:32px; height:32px; border-radius:50%;
            cursor:pointer;
            align-items:center; justify-content:center;
            font-size:15px; transition:.2s;
        }
        .sidebar-close:hover { background:var(--primary-light); }

        .sidebar-brand { display:flex; align-items:center; gap:12px; padding-left:10px; margin-bottom:40px; }
        .brand-dot {
            width:10px; height:10px;
            background:var(--primary-light);
            border-radius:50%;
            box-shadow:0 0 10px var(--primary-light);
            flex-shrink:0;
        }
        .brand-text { font-family:'Plus Jakarta Sans',sans-serif; font-size:20px; color:#fff; letter-spacing:.4px; }

        .sidebar-menu { list-style:none; flex-grow:1; }
        .sidebar-menu li { margin-bottom:4px; }
        .sidebar-menu a {
            color:#94a3b8; text-decoration:none;
            display:flex; align-items:center; gap:12px;
            padding:12px 16px; border-radius:12px;
            font-size:14px; font-weight:500;
            transition:.2s;
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

        /* ===== MAIN ===== */
        .main-content { margin-left:var(--sidebar-w); padding:40px; min-height:100vh; transition:margin .3s var(--ease); }

        /* ===== HEADER ===== */
        header { display:flex; justify-content:space-between; align-items:center; margin-bottom:40px; }
        .header-left { display:flex; align-items:center; gap:16px; }

        .menu-toggle {
            display:none;
            background:var(--white); border:none;
            width:42px; height:42px; border-radius:12px;
            cursor:pointer;
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

        /* ===== STATS ===== */
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:24px; margin-bottom:32px; }
        .stat-card {
            background:var(--white); padding:24px; border-radius:20px;
            display:flex; justify-content:space-between; align-items:center;
            box-shadow:0 4px 20px rgba(0,0,0,.03); border:1px solid #f1f5f9;
        }
        .stat-info p { font-size:13px; color:var(--text-muted); font-weight:500; margin-bottom:4px; }
        .stat-info h3 { font-size:22px; font-weight:700; color:var(--dark); }
        .stat-icon {
            width:50px; height:50px; background:#eff6ff; color:var(--primary);
            border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:20px;
        }

        /* ===== CHART ===== */
        .chart-section {
            background:var(--white); border-radius:24px; padding:30px;
            box-shadow:0 4px 20px rgba(0,0,0,.03); border:1px solid #f1f5f9;
            margin-bottom:32px;
        }
        .chart-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; }
        .chart-header h2 { font-size:18px; font-weight:700; }
        .growth-badge { padding:6px 12px; border-radius:50px; font-size:12px; font-weight:600; }
        .growth-badge.up   { background:#dcfce7; color:#15803d; }
        .growth-badge.down { background:#fee2e2; color:#b91c1c; }
        .chart-summary-row { display:flex; gap:40px; margin-bottom:25px; }
        .chart-summary-item .label { font-size:12px; color:var(--text-muted); margin-bottom:5px; display:block; }
        .chart-summary-item .value { font-size:20px; font-weight:700; color:var(--primary); }
        .chart-wrapper { height:320px; }

        /* ===== RECENT ORDERS TABLE ===== */
        .orders-table-section {
            background:var(--white); border-radius:24px; padding:30px;
            box-shadow:0 4px 20px rgba(0,0,0,.03); border:1px solid #f1f5f9;
            margin-bottom:40px;
        }
        .section-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; }
        .section-header h2 { font-size:18px; font-weight:700; }

        .btn-primary {
            background:var(--primary); color:#fff; padding:10px 20px;
            border-radius:12px; text-decoration:none; font-size:14px; font-weight:600;
            display:inline-flex; align-items:center; gap:8px; transition:.2s;
        }
        .btn-primary:hover { background:var(--dark); }

        .table-responsive { overflow-x:auto; }
        table.orders-table { width:100%; border-collapse:collapse; min-width:560px; }
        table.orders-table th {
            text-align:left; padding:14px 16px;
            color:var(--text-muted); font-size:12px;
            text-transform:uppercase; letter-spacing:.5px;
            border-bottom:1px solid #f1f5f9; font-weight:600;
        }
        table.orders-table td { padding:14px 16px; border-bottom:1px solid #f1f5f9; font-size:14px; vertical-align:middle; }
        table.orders-table tbody tr:last-child td { border-bottom:none; }
        table.orders-table tbody tr:hover { background:#f8fafc; }

        /* Status badges */
        .status-badge { padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; display:inline-block; }
        .status-pending    { background:#fef9c3; color:#854d0e; }
        .status-diproses   { background:#dbeafe; color:#1d4ed8; }
        .status-dikirim    { background:#e0f2fe; color:#0369a1; }
        .status-selesai    { background:#dcfce7; color:#15803d; }
        .status-dibatalkan { background:#fee2e2; color:#b91c1c; }

        .order-id { font-weight:700; color:var(--dark); }
        .order-id small { display:block; color:var(--text-muted); font-weight:400; font-size:12px; }

        .btn-detail {
            padding:6px 14px; border-radius:8px; font-size:12px; font-weight:600;
            background:#eff6ff; color:var(--primary); text-decoration:none; transition:.2s;
            display:inline-flex; align-items:center; gap:6px;
        }
        .btn-detail:hover { background:var(--primary); color:#fff; }

        /* ===== RESPONSIVE ===== */
        @media (max-width:1024px) {
            .sidebar { transform:translateX(-100%); }
            .sidebar.open { transform:translateX(0); }
            .sidebar.open .sidebar-close { display:flex; }
            .main-content { margin-left:0; padding:24px 20px 90px; }
            .menu-toggle { display:inline-flex; }
            .header-title h1 { font-size:22px; }
        }
        @media (max-width:480px) {
            .main-content { padding:16px 14px 80px; }
            header { margin-bottom:20px; }
            .stats-grid { gap:12px; }
            .stat-card { padding:18px; }
            .stat-info h3 { font-size:20px; }
            .admin-profile span { display:none; }
            .header-title h1 { font-size:19px; }
            .chart-summary-row { gap:20px; }
            .section-header { flex-direction:column; align-items:flex-start; gap:12px; }
        }
    </style>
</head>
<body>

<!-- OVERLAY -->
<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<nav class="sidebar" id="sidebar" aria-label="Navigasi admin">
    <button class="sidebar-close" onclick="closeSidebar()" aria-label="Tutup sidebar">
        <i class="fa-solid fa-xmark"></i>
    </button>

    <div class="sidebar-brand">
        <div class="brand-dot"></div>
        <div class="brand-text">MusimahAdmin</div>
    </div>

    <ul class="sidebar-menu">
        <li><a href="admin.php" class="active"><i class="fa-solid fa-grid-2"></i><span>Dashboard</span></a></li>
        <li><a href="kelola_produk.php"><i class="fa-solid fa-cake-candles"></i><span>Produk Kue</span></a></li>
        <li><a href="pesanan.php"><i class="fa-solid fa-cart-shopping"></i><span>Pesanan</span></a></li>
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

<!-- MAIN -->
<main class="main-content">
    <header>
        <div class="header-left">
            <button class="menu-toggle" onclick="openSidebar()" aria-label="Buka sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="header-title"><h1>Dashboard</h1></div>
        </div>
        <div class="admin-profile">
            <div class="profile-icon">AD</div>
            <span style="font-weight:600;font-size:14px;"><?= htmlspecialchars($_SESSION['user']) ?></span>
        </div>
    </header>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info"><p>Pendapatan</p><h3>Rp <?= number_format($total_pendapatan,0,',','.') ?></h3></div>
            <div class="stat-icon" style="background:#ecfdf5;color:#10b981;"><i class="fa-solid fa-wallet"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><p>Total Pesanan</p><h3><?= $total_pesanan ?></h3></div>
            <div class="stat-icon" style="background:#fff7ed;color:#f59e0b;"><i class="fa-solid fa-bag-shopping"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><p>Jumlah Produk</p><h3><?= $total_produk ?></h3></div>
            <div class="stat-icon"><i class="fa-solid fa-box"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><p>Pelanggan</p><h3><?= $total_user ?></h3></div>
            <div class="stat-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="fa-solid fa-users"></i></div>
        </div>
    </div>

    <!-- CHART -->
    <div class="chart-section">
        <div class="chart-header">
            <h2>Analisis Pendapatan</h2>
            <span class="growth-badge <?= $growth_positive ? 'up' : 'down' ?>">
                <?= ($growth_positive ? '↑' : '↓') . ' ' . number_format(abs($growth_pct),1) ?>% vs bulan lalu
            </span>
        </div>
        <div class="chart-summary-row">
            <div class="chart-summary-item">
                <span class="label">Bulan Ini</span>
                <div class="value">Rp <?= number_format($pendapatan_bulan_ini,0,',','.') ?></div>
            </div>
            <div class="chart-summary-item">
                <span class="label">Bulan Lalu</span>
                <div class="value" style="color:var(--text-muted)">Rp <?= number_format($pendapatan_bulan_lalu,0,',','.') ?></div>
            </div>
        </div>
        <div class="chart-wrapper">
            <canvas id="revenueBarChart"></canvas>
        </div>
    </div>

    <!-- RECENT ORDERS TABLE -->
    <div class="orders-table-section">
        <div class="section-header">
            <h2>Pesanan Terbaru</h2>
            <a href="pesanan.php" class="btn-primary">
                <i class="fa-solid fa-list"></i> Lihat Semua
            </a>
        </div>

        <div class="table-responsive">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>ID Pesanan</th>
                        <th>Pelanggan</th>
                        <th>Tanggal</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($q_pesanan_list && mysqli_num_rows($q_pesanan_list) > 0):
                    while ($row = mysqli_fetch_assoc($q_pesanan_list)):
                        // Tentukan kelas status
                        $status = $row['status_pesanan'];
                        $status_lower = strtolower($status);
                        if ($status_lower === 'pending')         $sc = 'status-pending';
                        elseif ($status_lower === 'diproses')    $sc = 'status-diproses';
                        elseif ($status_lower === 'dikirim')     $sc = 'status-dikirim';
                        elseif ($status_lower === 'selesai')     $sc = 'status-selesai';
                        elseif ($status_lower === 'dibatalkan')  $sc = 'status-dibatalkan';
                        else                                     $sc = 'status-pending';
                ?>
                <tr>
                    <td>
                        <span class="order-id">
                            #<?= str_pad($row['id_pesanan'], 4, '0', STR_PAD_LEFT) ?>
                        </span>
                    </td>
                    <td style="font-weight:500;"><?= htmlspecialchars($row['username'] ?? 'Tamu') ?></td>
                    <td style="color:var(--text-muted);">
                        <?= date('d M Y', strtotime($row['tanggal_pesan'])) ?><br>
                        <small><?= date('H:i', strtotime($row['tanggal_pesan'])) ?></small>
                    </td>
                    <td style="font-weight:700;color:var(--primary);">
                        Rp <?= number_format($row['total_bayar'],0,',','.') ?>
                    </td>
                    <td><span class="status-badge <?= $sc ?>"><?= htmlspecialchars($status) ?></span></td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted);">
                        <i class="fa-solid fa-inbox" style="font-size:32px;margin-bottom:8px;display:block;opacity:.3;"></i>
                        Belum ada pesanan
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
/* ── Sidebar toggle ── */
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

/* ── Chart ── */
const labels = <?= $chart_labels_json ?>;
const data   = <?= $chart_data_json ?>;

new Chart(document.getElementById('revenueBarChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Pendapatan',
            data,
            backgroundColor: data.map((_, i) => i === 5 ? '#1e3a8a' : '#e2e8f0'),
            borderRadius: 8,
            barPercentage: 0.5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: '#f1f5f9' },
                ticks: { callback: v => 'Rp ' + (v/1000) + 'k', color: '#64748b' }
            },
            x: { grid: { display: false }, ticks: { color: '#64748b' } }
        }
    }
});
</script>
</body>
</html>
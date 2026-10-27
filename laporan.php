<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

$tgl_mulai   = isset($_GET['tgl_mulai'])   && strtotime($_GET['tgl_mulai'])   ? $_GET['tgl_mulai']   : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) && strtotime($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// FIX: kalau rentang tanggal terbalik (tgl_mulai lebih baru dari tgl_selesai),
// tukar posisi keduanya supaya query BETWEEN tetap masuk akal, bukan diam-diam kosong.
if (strtotime($tgl_mulai) > strtotime($tgl_selesai)) {
    [$tgl_mulai, $tgl_selesai] = [$tgl_selesai, $tgl_mulai];
}

$dt_mulai   = $tgl_mulai   . ' 00:00:00';
$dt_selesai = $tgl_selesai . ' 23:59:59';

$pendapatan    = 0;
$jml_transaksi = 0;

// FIX: cek hasil prepare() sebelum dipakai
$stmt_total = $conn->prepare("
    SELECT 
        COALESCE(SUM(total_bayar), 0) AS total, 
        COUNT(*) AS jml 
    FROM pesanan 
    WHERE status_pesanan = 'Selesai' 
      AND tanggal_pesan BETWEEN ? AND ?
");
if ($stmt_total) {
    $stmt_total->bind_param("ss", $dt_mulai, $dt_selesai);
    $stmt_total->execute();
    $result_total  = $stmt_total->get_result()->fetch_assoc();
    $pendapatan    = $result_total['total'] ?? 0;
    $jml_transaksi = $result_total['jml']   ?? 0;
    $stmt_total->close();
}

$rata_rata = $jml_transaksi > 0 ? $pendapatan / $jml_transaksi : 0;

$chart_labels = [];
$chart_data   = [];
for ($i = 5; $i >= 0; $i--) {
    $bulan   = date('Y-m', strtotime("-$i months"));
    $label   = date('M Y', strtotime("-$i months"));
    $total_bulan = 0;

    $q_bulan = $conn->prepare("
        SELECT COALESCE(SUM(total_bayar), 0) as total
        FROM pesanan
        WHERE status_pesanan = 'Selesai'
          AND DATE_FORMAT(tanggal_pesan, '%Y-%m') COLLATE utf8mb4_general_ci = ?
    ");
    if ($q_bulan) {
        $q_bulan->bind_param("s", $bulan);
        $q_bulan->execute();
        $row_bulan   = $q_bulan->get_result()->fetch_assoc();
        $total_bulan = (float) ($row_bulan['total'] ?? 0);
        $q_bulan->close();
    }

    $chart_labels[] = $label;
    $chart_data[]   = $total_bulan;
}

$pendapatan_bulan_ini  = $chart_data[5] ?? 0;
$pendapatan_bulan_lalu = $chart_data[4] ?? 0;
if ($pendapatan_bulan_lalu > 0) {
    $growth_pct = (($pendapatan_bulan_ini - $pendapatan_bulan_lalu) / $pendapatan_bulan_lalu) * 100;
} else {
    $growth_pct = $pendapatan_bulan_ini > 0 ? 100 : 0;
}
$growth_positive = $growth_pct >= 0;

$chart_labels_json = json_encode($chart_labels);
$chart_data_json   = json_encode($chart_data);

// FIX: cek hasil prepare() sebelum dipakai; siapkan result_table kosong sebagai fallback
$result_table = null;
$stmt_table = $conn->prepare("
    SELECT pesanan.*, users.username 
    FROM pesanan 
    LEFT JOIN users ON pesanan.id_user = users.id
    WHERE pesanan.status_pesanan = 'Selesai' 
      AND pesanan.tanggal_pesan BETWEEN ? AND ?
    ORDER BY pesanan.tanggal_pesan ASC
");
if ($stmt_table) {
    $stmt_table->bind_param("ss", $dt_mulai, $dt_selesai);
    $stmt_table->execute();
    $result_table = $stmt_table->get_result();
}
$no_urut = 1;

function e(?string $val): string {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan — Musimah Bakery</title>
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
            background:var(--primary-light); border-radius:50%;
            box-shadow:0 0 10px var(--primary-light); flex-shrink:0;
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
        .sidebar-menu a.active { background:var(--primary); color:#fff; box-shadow:0 4px 12px rgba(0,0,0,.25); }

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

        /* ===== FILTER CARD ===== */
        .filter-card {
            background:var(--white); padding:24px; border-radius:20px;
            margin-bottom:28px;
            box-shadow:0 4px 20px rgba(0,0,0,.03); border:1px solid #f1f5f9;
        }
        .filter-form { display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap; }
        .form-group { display:flex; flex-direction:column; gap:6px; flex:1; min-width:180px; }
        .form-group label {
            font-size:11px; font-weight:600; color:var(--text-muted);
            text-transform:uppercase; letter-spacing:1px;
        }
        .form-group input {
            padding:10px 14px; border-radius:12px;
            border:1px solid #e2e8f0; font-family:inherit;
            font-size:14px; background:var(--bg); transition:.3s;
        }
        .form-group input:focus { border-color:var(--primary-light); box-shadow:0 0 0 4px rgba(59,130,246,.1); background:#fff; }
        .btn-filter {
            background:var(--primary); color:#fff; border:none;
            padding:10px 22px; border-radius:12px; cursor:pointer;
            font-size:14px; font-weight:600; transition:.2s;
            display:inline-flex; align-items:center; gap:8px;
        }
        .btn-filter:hover { background:var(--dark); }
        .btn-reset {
            font-size:13px; color:var(--text-muted); text-decoration:none;
            padding-bottom:10px; align-self:flex-end; transition:.2s;
        }
        .btn-reset:hover { color:var(--primary); }

        /* ===== STATS ===== */
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:24px; margin-bottom:28px; }
        .stat-card {
            background:var(--white); padding:24px; border-radius:20px;
            display:flex; justify-content:space-between; align-items:center;
            box-shadow:0 4px 20px rgba(0,0,0,.03); border:1px solid #f1f5f9;
        }
        .stat-info p { font-size:13px; color:var(--text-muted); font-weight:500; margin-bottom:4px; }
        .stat-info h3 { font-size:22px; font-weight:700; color:var(--dark); }
        .stat-icon {
            width:50px; height:50px; background:#eff6ff; color:var(--primary);
            border-radius:14px; display:flex; align-items:center; justify-content:center;
            font-size:20px; flex-shrink:0;
        }

        /* ===== CHART ===== */
        .chart-section {
            background:var(--white); border-radius:24px; padding:30px;
            box-shadow:0 4px 20px rgba(0,0,0,.03); border:1px solid #f1f5f9; margin-bottom:28px;
        }
        .chart-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .chart-header h2 { font-size:18px; font-weight:700; }
        .growth-badge { padding:6px 12px; border-radius:50px; font-size:12px; font-weight:600; }
        .growth-badge.up   { background:#dcfce7; color:#15803d; }
        .growth-badge.down { background:#fee2e2; color:#b91c1c; }
        .chart-summary-row { display:flex; gap:40px; margin-bottom:25px; }
        .chart-summary-item .label { font-size:12px; color:var(--text-muted); margin-bottom:5px; display:block; }
        .chart-summary-item .value { font-size:20px; font-weight:700; color:var(--primary); }
        .chart-wrapper { height:300px; }

        /* ===== TABLE ===== */
        .table-section {
            background:var(--white); border-radius:24px; padding:30px;
            box-shadow:0 4px 20px rgba(0,0,0,.03); border:1px solid #f1f5f9;
            margin-bottom:40px;
        }
        .section-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .section-header h2 { font-size:18px; font-weight:700; }

        .search-box { position:relative; margin-bottom:20px; max-width:400px; }
        .search-box input {
            width:100%; padding:10px 15px 10px 40px;
            border:1px solid #e2e8f0; border-radius:12px;
            font-size:14px; font-family:'Inter',sans-serif;
            background:var(--bg); transition:.3s;
        }
        .search-box input:focus { border-color:var(--primary-light); box-shadow:0 0 0 4px rgba(59,130,246,.1); background:#fff; }
        .search-box i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--text-muted); }

        .table-responsive { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:600px; }
        table th {
            text-align:left; padding:14px 16px;
            color:var(--text-muted); font-size:12px;
            text-transform:uppercase; letter-spacing:.5px;
            border-bottom:1px solid #f1f5f9; font-weight:600;
        }
        table td { padding:14px 16px; border-bottom:1px solid #f1f5f9; font-size:14px; vertical-align:middle; }
        table tbody tr:last-child td { border-bottom:none; }
        table tbody tr:hover { background:#f8fafc; }

        .status-pill {
            background:#dcfce7; color:#15803d;
            padding:4px 10px; border-radius:6px;
            font-size:12px; font-weight:600;
        }

        .empty-state { text-align:center; padding:60px 20px; color:var(--text-muted); }
        .empty-state i { font-size:48px; margin-bottom:16px; display:block; opacity:.3; }

        /* ===== RESPONSIVE ===== */
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
            .admin-profile span { display:none; }
            .header-title h1 { font-size:19px; }
            .stats-grid { gap:12px; }
            .stat-card { padding:18px; }
            .filter-form { flex-direction:column; }
            .btn-filter { width:100%; justify-content:center; }
            .chart-summary-row { flex-direction:column; gap:16px; }
            .table-section { padding:16px 12px; border-radius:18px; }
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
        <li><a href="admin.php"><i class="fa-solid fa-grid-2"></i><span>Dashboard</span></a></li>
        <li><a href="kelola_produk.php"><i class="fa-solid fa-cake-candles"></i><span>Produk Kue</span></a></li>
        <li><a href="pesanan.php"><i class="fa-solid fa-cart-shopping"></i><span>Pesanan</span></a></li>
        <li><a href="pelanggan.php"><i class="fa-solid fa-users"></i><span>Pelanggan</span></a></li>
        <li><a href="laporan.php" class="active"><i class="fa-solid fa-chart-line"></i><span>Laporan</span></a></li>
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
            <div class="header-title"><h1>Laporan Penjualan</h1></div>
        </div>
        <div class="admin-profile">
            <div class="profile-icon">AD</div>
            <span style="font-weight:600;font-size:14px;"><?= e($_SESSION['user']) ?></span>
        </div>
    </header>

    <!-- FILTER -->
    <section class="filter-card">
        <form method="GET" action="laporan.php" class="filter-form">
            <div class="form-group">
                <label for="tgl_mulai">Dari Tanggal</label>
                <input type="date" id="tgl_mulai" name="tgl_mulai" value="<?= e($tgl_mulai) ?>">
            </div>
            <div class="form-group">
                <label for="tgl_selesai">Sampai Tanggal</label>
                <input type="date" id="tgl_selesai" name="tgl_selesai" value="<?= e($tgl_selesai) ?>">
            </div>
            <button type="submit" class="btn-filter">
                <i class="fa-solid fa-magnifying-glass"></i> Filter Laporan
            </button>
            <a href="laporan.php" class="btn-reset">Reset</a>
        </form>
    </section>

    <!-- STATS -->
    <section class="stats-grid">
        <div class="stat-card">
            <div class="stat-info"><p>Total Pendapatan</p><h3>Rp <?= number_format($pendapatan,0,',','.') ?></h3></div>
            <div class="stat-icon" style="background:#ecfdf5;color:#10b981;"><i class="fa-solid fa-money-bill-trend-up"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><p>Pesanan Berhasil</p><h3><?= (int)$jml_transaksi ?></h3></div>
            <div class="stat-icon" style="background:#fff7ed;color:#f59e0b;"><i class="fa-solid fa-check-double"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><p>Rata-rata per Transaksi</p><h3>Rp <?= number_format($rata_rata,0,',','.') ?></h3></div>
            <div class="stat-icon"><i class="fa-solid fa-calculator"></i></div>
        </div>
    </section>

    <!-- CHART -->
    <section class="chart-section">
        <div class="chart-header">
            <h2>Tren Pendapatan 6 Bulan</h2>
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
            <canvas id="revenueChart"></canvas>
        </div>
    </section>

    <!-- TABLE -->
    <section class="table-section">
        <div class="section-header">
            <h2>Detail Transaksi Selesai</h2>
            <span style="font-size:13px;color:var(--text-muted);"><?= (int)$jml_transaksi ?> transaksi ditemukan</span>
        </div>

        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="searchInput" placeholder="Cari nama pelanggan..." oninput="filterTable()">
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>No. Pesanan</th>
                        <th>Pelanggan</th>
                        <th>Tanggal</th>
                        <th>Total Bayar</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="transaksiBody">
                    <?php if (!$result_table || $result_table->num_rows === 0): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="fa-solid fa-folder-open"></i>
                                <p>Tidak ada data transaksi pada periode ini.</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php while ($row = $result_table->fetch_assoc()): ?>
                        <tr>
                            <td style="font-weight:700;color:var(--primary);">#<?= $no_urut++ ?></td>
                            <td style="font-weight:600;"><?= e($row['username'] ?? 'Guest') ?></td>
                            <td style="color:var(--text-muted);"><?= e(date('d M Y', strtotime($row['tanggal_pesan']))) ?></td>
                            <td style="font-weight:700;color:var(--dark);">Rp <?= number_format($row['total_bayar'],0,',','.') ?></td>
                            <td><span class="status-pill">Selesai</span></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php if ($stmt_table) $stmt_table->close(); ?>

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

const labels = <?= $chart_labels_json ?>;
const data   = <?= $chart_data_json ?>;

new Chart(document.getElementById('revenueChart'), {
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

function filterTable() {
    const val  = document.getElementById('searchInput').value.toUpperCase();
    const rows = document.getElementById('transaksiBody').getElementsByTagName('tr');
    for (let row of rows) {
        const td = row.getElementsByTagName('td')[1];
        if (td) row.style.display = td.textContent.toUpperCase().includes(val) ? '' : 'none';
    }
}
</script>
</body>
</html>
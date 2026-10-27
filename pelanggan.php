<?php
include 'koneksi.php'; 
session_start();

if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

$nama_kolom_id = 'id'; 

if (isset($_POST['hapus_id'])) {
    $id_hapus = mysqli_real_escape_string($conn, $_POST['hapus_id']);
    $user_sekarang = $_SESSION['user'];

    $cek_user = mysqli_query($conn, "SELECT username FROM users WHERE $nama_kolom_id ='$id_hapus'");
    $data_user = $cek_user ? mysqli_fetch_assoc($cek_user) : null;

    if ($data_user) {
        if ($data_user['username'] === $user_sekarang) {
            $_SESSION['pesan_error_pelanggan'] = 'Anda tidak bisa menghapus akun Anda sendiri!';
        } else {
            // FIX: cek hasil DELETE sebelum menampilkan pesan sukses
            $hapus_ok = mysqli_query($conn, "DELETE FROM users WHERE $nama_kolom_id ='$id_hapus'");
            if ($hapus_ok) {
                $_SESSION['pesan_sukses_pelanggan'] = 'Data pelanggan berhasil dihapus';
            } else {
                $_SESSION['pesan_error_pelanggan'] = 'Gagal menghapus data pelanggan. Silakan coba lagi.';
            }
        }
    }
    header("Location: pelanggan.php");
    exit;
}

$pesan_sukses_pelanggan = $_SESSION['pesan_sukses_pelanggan'] ?? '';
$pesan_error_pelanggan  = $_SESSION['pesan_error_pelanggan'] ?? '';
unset($_SESSION['pesan_sukses_pelanggan'], $_SESSION['pesan_error_pelanggan']);

$q_total = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
$total_pelanggan = $q_total ? (mysqli_fetch_assoc($q_total)['total'] ?? 0) : 0;

$q_baru = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role != 'admin' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
$pelanggan_baru = $q_baru ? (mysqli_fetch_assoc($q_baru)['total'] ?? 0) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pelanggan — Musimah Bakery</title>
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
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:24px; margin-bottom:32px; }
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
        }

        /* ===== TABLE SECTION ===== */
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
        table { width:100%; border-collapse:collapse; min-width:500px; }
        table th {
            text-align:left; padding:14px 16px;
            color:var(--text-muted); font-size:12px;
            text-transform:uppercase; letter-spacing:.5px;
            border-bottom:1px solid #f1f5f9; font-weight:600;
        }
        table td { padding:14px 16px; border-bottom:1px solid #f1f5f9; font-size:14px; vertical-align:middle; }
        table tbody tr:last-child td { border-bottom:none; }
        table tbody tr:hover { background:#f8fafc; }

        .avatar {
            width:38px; height:38px; border-radius:10px;
            background:#eff6ff; color:var(--primary);
            display:flex; align-items:center; justify-content:center;
            font-weight:700; font-size:15px; flex-shrink:0;
        }

        .role-badge { display:inline-block; padding:4px 10px; border-radius:6px; font-size:11px; font-weight:600; }
        .role-admin { background:#ede9fe; color:#6d28d9; }
        .role-user  { background:#f0fdf4; color:#15803d; }

        .btn-action {
            width:34px; height:34px; border-radius:10px;
            display:inline-flex; align-items:center; justify-content:center;
            text-decoration:none; transition:.2s;
        }
        .btn-delete { background:#fee2e2; color:#ef4444; }
        .btn-action:hover { opacity:.8; transform:scale(1.08); }

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
            .stats-grid { gap:12px; }
            .stat-card { padding:18px; }
            .stat-info h3 { font-size:22px; }
            .admin-profile span { display:none; }
            .header-title h1 { font-size:19px; }
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
        <li><a href="pelanggan.php" class="active"><i class="fa-solid fa-users"></i><span>Pelanggan</span></a></li>
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
            <div class="header-title"><h1>Data Pelanggan</h1></div>
        </div>
        <div class="admin-profile">
            <div class="profile-icon">AD</div>
            <span style="font-weight:600;font-size:14px;"><?= htmlspecialchars($_SESSION['user']) ?></span>
        </div>
    </header>

    <!-- NOTIFIKASI -->
    <?php if ($pesan_sukses_pelanggan): ?>
    <div style="background:#dcfce7;color:#15803d;border-left:4px solid #22c55e;padding:14px 20px;border-radius:12px;margin-bottom:24px;font-size:14px;font-weight:500;display:flex;align-items:center;gap:10px;">
        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($pesan_sukses_pelanggan) ?>
    </div>
    <?php endif; ?>
    <?php if ($pesan_error_pelanggan): ?>
    <div style="background:#fee2e2;color:#b91c1c;border-left:4px solid #ef4444;padding:14px 20px;border-radius:12px;margin-bottom:24px;font-size:14px;font-weight:500;display:flex;align-items:center;gap:10px;">
        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($pesan_error_pelanggan) ?>
    </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info"><p>Total Pelanggan</p><h3><?= $total_pelanggan ?></h3></div>
            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><p>Pelanggan Baru Bulan Ini</p><h3><?= $pelanggan_baru ?></h3></div>
            <div class="stat-icon" style="background:#f0fdf4;color:#10b981;"><i class="fa-solid fa-user-plus"></i></div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-section">
        <div class="section-header">
            <h2>Daftar Pelanggan</h2>
        </div>

        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="searchInput" placeholder="Cari nama atau email..." oninput="filterTable()">
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Pelanggan</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th style="text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="pelangganBody">
                <?php
                $query = mysqli_query($conn, "SELECT * FROM users ORDER BY $nama_kolom_id DESC");
                $row_count = 0;
                while ($query && ($row = mysqli_fetch_assoc($query))):
                    $row_count++;
                    $nama_tampil = (isset($row['nama_lengkap']) && !empty($row['nama_lengkap'])) ? $row['nama_lengkap'] : $row['username'];
                    $inisial = strtoupper(substr($nama_tampil, 0, 1));
                    $is_admin = isset($row['role']) && $row['role'] === 'admin';
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div class="avatar"><?= htmlspecialchars($inisial) ?></div>
                            <div>
                                <div style="font-weight:600;color:var(--dark);"><?= htmlspecialchars($nama_tampil) ?></div>
                                <small style="color:var(--text-muted);"><?= htmlspecialchars($row['username']) ?></small>
                            </div>
                        </div>
                    </td>
                    <td style="color:var(--text-muted);"><?= htmlspecialchars($row['email']) ?></td>
                    <td>
                        <span class="role-badge <?= $is_admin ? 'role-admin' : 'role-user' ?>">
                            <?= $is_admin ? 'Admin' : 'Pelanggan' ?>
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <?php if (!$is_admin): ?>
                        <form method="POST" action="pelanggan.php" onsubmit="return confirm('Yakin ingin menghapus pelanggan ini?')" style="display:inline;">
                            <input type="hidden" name="hapus_id" value="<?= (int)$row[$nama_kolom_id] ?>">
                            <button type="submit" class="btn-action btn-delete" title="Hapus" style="border:none;cursor:pointer;">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                        <?php else: ?>
                        <span style="color:#e2e8f0;font-size:13px;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($row_count === 0): ?>
                <tr>
                    <td colspan="4">
                        <div class="empty-state">
                            <i class="fa-solid fa-users-slash"></i>
                            <p>Belum ada data pelanggan</p>
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

function filterTable() {
    const val  = document.getElementById('searchInput').value.toUpperCase();
    const rows = document.getElementById('pelangganBody').getElementsByTagName('tr');
    for (let row of rows) {
        let found = false;
        for (let cell of row.getElementsByTagName('td')) {
            if (cell.textContent.toUpperCase().includes(val)) { found = true; break; }
        }
        row.style.display = found ? '' : 'none';
    }
}
</script>
</body>
</html>
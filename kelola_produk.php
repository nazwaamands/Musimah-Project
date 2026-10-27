<?php
include 'koneksi.php';
session_start();

if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

if (isset($_POST['hapus_id'])) {
    $id    = mysqli_real_escape_string($conn, $_POST['hapus_id']);
    $ambil = mysqli_query($conn, "SELECT gambar FROM produk WHERE id_produk='$id'");
    $data  = $ambil ? mysqli_fetch_assoc($ambil) : null;
    if ($data) {
        // FIX: coba DELETE dari database DULU dan pastikan berhasil, BARU hapus file
        // gambar setelahnya — supaya kalau DELETE gagal, gambar produk tidak
        // ikut hilang sia-sia dan pesan yang ditampilkan sesuai kenyataan.
        $hapus_ok = mysqli_query($conn, "DELETE FROM produk WHERE id_produk='$id'");
        if ($hapus_ok) {
            if (!empty($data['gambar']) && file_exists($data['gambar'])) {
                @unlink($data['gambar']);
            }
            $_SESSION['pesan_sukses_produk'] = 'Produk berhasil dihapus';
        } else {
            $_SESSION['pesan_sukses_produk'] = 'Gagal menghapus produk. Silakan coba lagi.';
        }
        header("Location: kelola_produk.php");
        exit;
    }
}

$pesan_sukses = $_SESSION['pesan_sukses_produk'] ?? '';
unset($_SESSION['pesan_sukses_produk']);

$q_total = mysqli_query($conn, "SELECT COUNT(*) as total FROM produk");
$total_produk = $q_total ? (mysqli_fetch_assoc($q_total)['total'] ?? 0) : 0;

$q_stok = mysqli_query($conn, "SELECT COUNT(*) as total FROM produk WHERE stok > 0 AND stok <= 10");
$stok_tipis = $q_stok ? (mysqli_fetch_assoc($q_stok)['total'] ?? 0) : 0;

// ===== TAMBAHAN: hitung produk stok habis =====
$q_habis  = mysqli_query($conn, "SELECT COUNT(*) as total FROM produk WHERE stok = 0");
$stok_habis_count = $q_habis ? (mysqli_fetch_assoc($q_habis)['total'] ?? 0) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Produk — Musimah Bakery</title>
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

/* ===== SIDEBAR ===== */
.sidebar {
    width:var(--sidebar-w); background:var(--dark);
    position:fixed; top:0; left:0; height:100vh;
    padding:30px 20px; z-index:900;
    display:flex; flex-direction:column;
    transition:transform .3s var(--ease);
    overflow-y:auto;
}
.sidebar::-webkit-scrollbar{width:4px;}
.sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:4px;}

.sidebar-brand{display:flex;align-items:center;gap:12px;padding-left:10px;margin-bottom:40px;}
.brand-dot{width:10px;height:10px;background:var(--primary-light);border-radius:50%;box-shadow:0 0 10px var(--primary-light);flex-shrink:0;}
.brand-text{font-family:'Plus Jakarta Sans',sans-serif;font-size:20px;color:#fff;letter-spacing:.4px;}

.sidebar-menu{list-style:none;flex-grow:1;}
.sidebar-menu li{margin-bottom:4px;}
.sidebar-menu a{color:#94a3b8;text-decoration:none;display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:12px;font-size:14px;font-weight:500;transition:.2s;}
.sidebar-menu a i{width:20px;font-size:16px;}
.sidebar-menu a:hover{color:#fff;background:rgba(255,255,255,.06);}
.sidebar-menu a.active{background:var(--primary);color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.25);}

.logout-section{margin-top:auto;padding-top:20px;border-top:1px solid rgba(255,255,255,.08);}
.logout-btn{color:#fca5a5!important;text-decoration:none;display:flex;align-items:center;gap:12px;padding:12px 16px;font-size:14px;}

.sidebar-close{
    display:none;
    position:absolute;top:16px;right:16px;
    background:rgba(255,255,255,.12);border:none;color:#fff;
    width:32px;height:32px;border-radius:50%;cursor:pointer;
    align-items:center;justify-content:center;font-size:15px;transition:.2s;
}
.sidebar-close:hover{background:var(--primary-light);}

/* ===== OVERLAY ===== */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(3px);z-index:800;opacity:0;visibility:hidden;transition:.3s;}
.overlay.open{opacity:1;visibility:visible;}

/* ===== MAIN ===== */
.main-content{margin-left:var(--sidebar-w);padding:40px;min-height:100vh;transition:margin .3s var(--ease);}

/* ===== HEADER ===== */
header{display:flex;justify-content:space-between;align-items:center;margin-bottom:40px;}
.header-left{display:flex;align-items:center;gap:16px;}
.menu-toggle{
    display:none;background:var(--white);border:none;
    width:42px;height:42px;border-radius:12px;cursor:pointer;
    align-items:center;justify-content:center;font-size:18px;
    color:var(--dark);box-shadow:0 2px 10px rgba(0,0,0,.06);transition:.2s;
}
.menu-toggle:hover{background:#f1f5f9;}
.header-title h1{font-size:28px;font-weight:700;color:var(--dark);}
.admin-profile{display:flex;align-items:center;gap:12px;background:var(--white);padding:6px 16px 6px 6px;border-radius:50px;box-shadow:0 2px 10px rgba(0,0,0,.05);}
.profile-icon{width:34px;height:34px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;}

/* ===== STATS ===== */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:24px;margin-bottom:32px;}
.stat-card{background:var(--white);padding:24px;border-radius:20px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 4px 20px rgba(0,0,0,.03);border:1px solid #f1f5f9;}
.stat-info p{font-size:13px;color:var(--text-muted);font-weight:500;margin-bottom:4px;}
.stat-info h3{font-size:26px;font-weight:700;color:var(--dark);}
.stat-icon{width:50px;height:50px;background:#eff6ff;color:var(--primary);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;}

/* ===== TABLE SECTION ===== */
.table-section{background:var(--white);border-radius:24px;padding:30px;box-shadow:0 4px 20px rgba(0,0,0,.03);border:1px solid #f1f5f9;margin-bottom:40px;}
.section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.section-header h2{font-size:18px;font-weight:700;}

.btn-add{
    background:var(--primary);color:#fff;padding:10px 20px;
    border-radius:12px;text-decoration:none;font-size:14px;font-weight:600;
    display:inline-flex;align-items:center;gap:8px;transition:.2s;
}
.btn-add:hover{background:var(--dark);}

.search-box{position:relative;margin-bottom:20px;max-width:400px;}
.search-box input{width:100%;padding:10px 15px 10px 40px;border:1px solid #e2e8f0;border-radius:12px;font-size:14px;font-family:'Inter',sans-serif;transition:.3s;background:var(--bg);}
.search-box input:focus{border-color:var(--primary-light);box-shadow:0 0 0 4px rgba(59,130,246,.1);background:#fff;}
.search-box i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);}

.table-responsive{overflow-x:auto;}
table{width:100%;border-collapse:collapse;min-width:560px;}
table th{text-align:left;padding:14px 16px;color:var(--text-muted);font-size:12px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #f1f5f9;font-weight:600;}
table td{padding:14px 16px;border-bottom:1px solid #f1f5f9;font-size:14px;vertical-align:middle;}
table tbody tr:last-child td{border-bottom:none;}
table tbody tr:hover{background:#f8fafc;}

/* Baris stok habis — highlight merah muda */
table tbody tr.row-habis{background:#fff5f5;}
table tbody tr.row-habis:hover{background:#fee2e2;}

.img-produk{width:46px;height:46px;object-fit:cover;border-radius:10px;border:1px solid #e2e8f0;}

/* ===== STOK BADGE (diperluas) ===== */
.stok-badge{padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:5px;}
.stok-aman    {background:#dcfce7;color:#15803d;}
.stok-terbatas{background:#fef9c3;color:#854d0e;}
.stok-habis   {background:#fee2e2;color:#b91c1c;}

.action-group{display:flex;gap:8px;}
.btn-action{width:34px;height:34px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;transition:.2s;}
.btn-edit  {background:#eff6ff;color:var(--primary-light);}
.btn-delete{background:#fee2e2;color:#ef4444;}
.btn-action:hover{opacity:.8;transform:scale(1.08);}

.empty-state{text-align:center;padding:60px 20px;color:var(--text-muted);}
.empty-state i{font-size:48px;margin-bottom:16px;display:block;opacity:.3;}

/* ===== RESPONSIVE ===== */
@media (max-width:1024px){
    .sidebar{transform:translateX(-100%);}
    .sidebar.open{transform:translateX(0);}
    .sidebar.open .sidebar-close{display:flex;}
    .main-content{margin-left:0;padding:24px 20px;}
    .menu-toggle{display:inline-flex;}
    .header-title h1{font-size:22px;}
}
@media (max-width:480px){
    .main-content{padding:16px 14px;}
    header{margin-bottom:20px;}
    .stats-grid{gap:12px;}
    .stat-card{padding:18px;}
    .stat-info h3{font-size:22px;}
    .table-section{padding:16px 12px;border-radius:18px;}
    .admin-profile span{display:none;}
    .header-title h1{font-size:19px;}
    .section-header{flex-direction:column;align-items:flex-start;gap:12px;}
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
        <li><a href="kelola_produk.php" class="active"><i class="fa-solid fa-cake-candles"></i><span>Produk Kue</span></a></li>
        <li><a href="pesanan.php"><i class="fa-solid fa-cart-shopping"></i><span>Pesanan</span></a></li>
        <li><a href="pelanggan.php"><i class="fa-solid fa-users"></i><span>Pelanggan</span></a></li>
        <li><a href="laporan.php"><i class="fa-solid fa-chart-line"></i><span>Laporan</span></a></li>
    </ul>

    <div class="logout-section">
        <a href="logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar</a>
    </div>
</nav>

<!-- MAIN -->
<main class="main-content">
    <header>
        <div class="header-left">
            <button class="menu-toggle" onclick="openSidebar()" aria-label="Buka sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="header-title"><h1>Kelola Produk</h1></div>
        </div>
        <div class="admin-profile">
            <div class="profile-icon">AD</div>
            <span style="font-weight:600;font-size:14px;"><?= htmlspecialchars($_SESSION['user']) ?></span>
        </div>
    </header>

    <!-- NOTIFIKASI SUKSES -->
    <?php if ($pesan_sukses): ?>
    <div style="background:#dcfce7;color:#15803d;border-left:4px solid #22c55e;padding:14px 20px;border-radius:12px;margin-bottom:24px;font-size:14px;font-weight:500;display:flex;align-items:center;gap:10px;">
        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($pesan_sukses) ?>
    </div>
    <?php endif; ?>

    <!-- STATS (3 kartu) -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info"><p>Total Produk</p><h3><?= $total_produk ?></h3></div>
            <div class="stat-icon"><i class="fa-solid fa-box"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><p>Stok Hampir Habis</p><h3><?= $stok_tipis ?></h3></div>
            <div class="stat-icon" style="background:#fef9c3;color:#854d0e;"><i class="fa-solid fa-triangle-exclamation"></i></div>
        </div>
        <!-- KARTU BARU: Stok Habis -->
        <div class="stat-card">
            <div class="stat-info"><p>Stok Habis</p><h3><?= $stok_habis_count ?></h3></div>
            <div class="stat-icon" style="background:#fee2e2;color:#b91c1c;"><i class="fa-solid fa-ban"></i></div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-section">
        <div class="section-header">
            <h2>Daftar Produk Kue</h2>
            <a href="tambah_produk.php" class="btn-add"><i class="fa-solid fa-plus"></i> Tambah Produk</a>
        </div>

        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="searchInput" placeholder="Cari nama produk..." oninput="filterTable()">
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Gambar</th>
                        <th>Nama Produk</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th style="text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="produkBody">
                <?php
                $query = mysqli_query($conn, "SELECT * FROM produk ORDER BY stok ASC, id_produk DESC");
                $count = 0;
                while ($query && ($row = mysqli_fetch_assoc($query))):
                    $count++;
                    $stok = (int)$row['stok'];

                    // Tentukan badge dan class baris
                    if ($stok <= 0) {
                        $sc       = 'stok-habis';
                        $sl       = 'Habis';
                        $icon     = 'fa-ban';
                        $rowClass = 'row-habis';
                    } elseif ($stok <= 10) {
                        $sc       = 'stok-terbatas';
                        $sl       = 'Terbatas';
                        $icon     = 'fa-triangle-exclamation';
                        $rowClass = '';
                    } else {
                        $sc       = 'stok-aman';
                        $sl       = 'Tersedia';
                        $icon     = 'fa-circle-check';
                        $rowClass = '';
                    }
                ?>
                <tr class="<?= $rowClass ?>">
                    <td>
                        <img
                            src="<?= htmlspecialchars($row['gambar']) ?>"
                            class="img-produk"
                            onerror="this.src='https://via.placeholder.com/60?text=Kue'"
                            <?= $stok <= 0 ? 'style="opacity:.45;filter:grayscale(1);"' : '' ?>
                        >
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--dark);"><?= htmlspecialchars($row['nama_produk']) ?></div>
                        <small style="color:var(--text-muted);">ID: #<?= (int)$row['id_produk'] ?></small>
                    </td>
                    <td style="font-weight:700;color:var(--primary);">Rp <?= number_format($row['harga'],0,',','.') ?></td>
                    <td>
                        <span class="stok-badge <?= $sc ?>">
                            <i class="fa-solid <?= $icon ?>"></i>
                            <?= $sl ?> (<?= $stok ?>)
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <div class="action-group" style="justify-content:center;">
                            <a href="edit_produk.php?id=<?= (int)$row['id_produk'] ?>" class="btn-action btn-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                            <form method="POST" action="kelola_produk.php" onsubmit="return confirm('Hapus produk ini?')" style="display:inline;">
                                <input type="hidden" name="hapus_id" value="<?= (int)$row['id_produk'] ?>">
                                <button type="submit" class="btn-action btn-delete" title="Hapus" style="border:none;cursor:pointer;">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($count === 0): ?>
                <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-box-open"></i><p>Belum ada produk terdaftar</p></div></td></tr>
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
    const rows = document.getElementById('produkBody').getElementsByTagName('tr');
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
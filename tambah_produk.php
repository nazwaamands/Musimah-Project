<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

// ── Cek kolom opsional di DB (sama seperti edit_produk.php) ──
$cols_res      = mysqli_query($conn, "SHOW COLUMNS FROM produk");
$existing_cols = [];
if ($cols_res) {
    while ($col = mysqli_fetch_assoc($cols_res)) $existing_cols[] = $col['Field'];
}
$has_bestseller  = in_array('is_bestseller', $existing_cols);
$has_kalori      = in_array('kalori',        $existing_cols);
$has_protein     = in_array('protein',       $existing_cols);
$has_lemak       = in_array('lemak',         $existing_cols);
$has_karbohidrat = in_array('karbohidrat',   $existing_cols);

if (isset($_POST['submit'])) {
    $nama      = mysqli_real_escape_string($conn, $_POST['nama_produk']);
    // FIX: harga & stok di-cast ke tipe angka, bukan cuma di-escape sebagai string
    $harga     = (int)$_POST['harga'];
    $stok      = (int)$_POST['stok'];
    $kategori  = mysqli_real_escape_string($conn, $_POST['kategori']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);

    // Kolom opsional
    $extra_cols = "";
    $extra_vals = "";

    if ($has_bestseller) {
        $bs = isset($_POST['is_bestseller']) ? 1 : 0;
        $extra_cols .= ", is_bestseller";
        $extra_vals .= ", '$bs'";
    }
    if ($has_kalori) {
        $v = ($_POST['kalori'] !== '') ? (int)$_POST['kalori'] : 'NULL';
        $extra_cols .= ", kalori";
        $extra_vals .= ", $v";
    }
    if ($has_protein) {
        $v = ($_POST['protein'] !== '') ? (float)$_POST['protein'] : 'NULL';
        $extra_cols .= ", protein";
        $extra_vals .= ", $v";
    }
    if ($has_lemak) {
        $v = ($_POST['lemak'] !== '') ? (float)$_POST['lemak'] : 'NULL';
        $extra_cols .= ", lemak";
        $extra_vals .= ", $v";
    }
    if ($has_karbohidrat) {
        $v = ($_POST['karbohidrat'] !== '') ? (float)$_POST['karbohidrat'] : 'NULL';
        $extra_cols .= ", karbohidrat";
        $extra_vals .= ", $v";
    }

    $nama_file   = $_FILES['gambar']['name'];
    $ukuran_file = $_FILES['gambar']['size'];
    $tmp_file    = $_FILES['gambar']['tmp_name'];
    $error_file  = $_FILES['gambar']['error'];

    // FIX: tangani kode error upload lain (bukan cuma "tidak ada file"),
    // supaya pesannya jelas dan tidak menyesatkan (misal dikira "format tidak didukung"
    // padahal sebenarnya ukurannya kelebihan batas server).
    $pesan_error_upload = match ($error_file) {
        UPLOAD_ERR_OK       => null,
        UPLOAD_ERR_NO_FILE  => 'Pilih gambar terlebih dahulu!',
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file gambar melebihi batas maksimal server. Silakan kompres/perkecil gambar.',
        UPLOAD_ERR_PARTIAL  => 'Upload gambar terputus di tengah jalan. Silakan coba lagi.',
        default             => 'Gagal mengupload gambar (kode error: ' . $error_file . '). Silakan coba lagi.',
    };

    if ($pesan_error_upload !== null) {
        echo "<script>alert(" . json_encode($pesan_error_upload) . ");</script>";
    } else {
        $folder_tujuan = "uploads/";
        // FIX: permission folder diperketat ke 0755 (bukan 0777)
        if (!is_dir($folder_tujuan)) mkdir($folder_tujuan, 0755, true);

        $ekstensi_boleh = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
        $x        = explode('.', $nama_file);
        $ekstensi = strtolower(end($x));

        // Cek isi file asli, bukan cuma nama/ekstensinya (getimagesize gagal
        // kalau file bukan gambar sungguhan, jadi tidak bisa dikelabui dengan
        // rename file berbahaya jadi .jpg/.png)
        $info_gambar = @getimagesize($tmp_file);

        if (in_array($ekstensi, $ekstensi_boleh) && $info_gambar !== false) {
            $nama_baru = time() . "_" . $nama_file;
            $path      = $folder_tujuan . $nama_baru;

            if (move_uploaded_file($tmp_file, $path)) {
                $query = "INSERT INTO produk (nama_produk, harga, stok, kategori, deskripsi, gambar$extra_cols)
                          VALUES ('$nama', $harga, $stok, '$kategori', '$deskripsi', '$path'$extra_vals)";
                if (mysqli_query($conn, $query)) {
                    echo "<script>alert('Produk Berhasil Ditambahkan!'); window.location='kelola_produk.php';</script>";
                } else {
                    echo "<script>alert('Gagal menyimpan ke database: " . mysqli_error($conn) . "');</script>";
                }
            } else {
                echo "<script>alert('Gagal mengupload gambar ke folder tujuan');</script>";
            }
        } else {
            echo "<script>alert('Format gambar tidak didukung! Gunakan JPG, PNG, WEBP, atau GIF');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Produk — Musimah Bakery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary       : #1e3a8a;
            --primary-light : #3b82f6;
            --primary-hover : #1d4ed8;
            --dark          : #0f172a;
            --bg            : #f8fafc;
            --white         : #ffffff;
            --text-main     : #1e293b;
            --text-muted    : #64748b;
            --border        : #e2e8f0;
            --sidebar-w     : 260px;
            --ease          : cubic-bezier(0.4, 0, 0.2, 1);
        }

        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; outline:none; }
        body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text-main); min-height:100vh; overflow-x:hidden; }

        /* ===== OVERLAY ===== */
        .overlay {
            position:fixed; inset:0;
            background:rgba(0,0,0,.5); backdrop-filter:blur(3px);
            z-index:800; opacity:0; visibility:hidden;
            transition:.3s var(--ease);
        }
        .overlay.open { opacity:1; visibility:visible; }

        /* ===== SIDEBAR ===== */
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
        .sidebar-menu a.active { background:var(--primary); color:#fff; box-shadow:0 4px 12px rgba(0,0,0,.25); }

        .logout-section { margin-top:auto; padding-top:20px; border-top:1px solid rgba(255,255,255,.08); }
        .logout-btn {
            color:#fca5a5 !important; text-decoration:none;
            display:flex; align-items:center; gap:12px;
            padding:12px 16px; font-size:14px; border-radius:12px; transition:.2s;
        }
        .logout-btn:hover { background:rgba(252,165,165,.08); }

        /* ===== MAIN ===== */
        .main-content {
            margin-left:var(--sidebar-w); padding:40px; min-height:100vh;
            transition:margin .3s var(--ease);
            display:flex; flex-direction:column; align-items:center;
        }

        /* ===== TOP BAR ===== */
        .topbar {
            display:flex; justify-content:space-between; align-items:center;
            margin-bottom:32px; width:100%; max-width:700px;
        }
        .topbar-left { display:flex; align-items:center; gap:16px; }

        .menu-toggle {
            display:none; background:var(--white); border:none;
            width:42px; height:42px; border-radius:12px; cursor:pointer;
            align-items:center; justify-content:center;
            font-size:18px; color:var(--dark);
            box-shadow:0 2px 10px rgba(0,0,0,.06); transition:.2s;
        }
        .menu-toggle:hover { background:#f1f5f9; }

        .topbar-title h1 { font-size:26px; font-weight:700; color:var(--dark); }
        .topbar-title p  { font-size:13px; color:var(--text-muted); margin-top:2px; }

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

        /* ===== FORM CARD ===== */
        .form-card {
            background:var(--white); border-radius:24px;
            box-shadow:0 4px 20px rgba(0,0,0,.04);
            border:1px solid var(--border); overflow:hidden;
            width:100%; max-width:700px;
        }
        .card-header {
            background:var(--primary); padding:24px 32px;
            display:flex; align-items:center; gap:16px;
        }
        .card-header-icon {
            width:44px; height:44px; border-radius:12px;
            background:rgba(255,255,255,.15);
            display:flex; align-items:center; justify-content:center;
            color:#fff; font-size:18px; flex-shrink:0;
        }
        .card-header-text h2 { font-family:'Plus Jakarta Sans',sans-serif; font-size:18px; color:#fff; font-weight:700; }
        .card-header-text p  { font-size:13px; color:rgba(255,255,255,.7); margin-top:2px; }

        .card-body { padding:32px; }

        /* FORM GROUPS */
        .form-group { margin-bottom:22px; }
        .form-group label {
            display:block; font-size:12px; font-weight:700;
            color:var(--text-muted); text-transform:uppercase;
            letter-spacing:.6px; margin-bottom:8px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width:100%; padding:12px 16px;
            border:1px solid var(--border); border-radius:12px;
            font-size:14px; font-family:'Inter',sans-serif;
            color:var(--text-main); background:var(--bg);
            transition:.25s var(--ease);
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color:var(--primary-light); background:var(--white);
            box-shadow:0 0 0 4px rgba(59,130,246,.1);
        }
        .form-group textarea { resize:vertical; min-height:90px; }

        .rupiah-preview {
            display:flex; align-items:center; gap:8px; margin-top:10px;
            background:#eff6ff; color:var(--primary);
            font-size:15px; font-weight:700;
            padding:10px 16px; border-radius:10px;
            border-left:4px solid var(--primary);
        }

        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }

        .divider { border:none; border-top:1px solid var(--border); margin:4px 0 22px; }
        .section-label {
            font-size:12px; font-weight:700; color:var(--text-muted);
            text-transform:uppercase; letter-spacing:.6px; margin-bottom:14px;
            display:flex; align-items:center; gap:6px;
        }

        /* ── Best Seller Toggle (identik edit_produk) ── */
        .bs-toggle-wrap {
            display:flex; align-items:center; gap:14px;
            background:#fffbeb; border:1.5px solid #fde68a;
            border-radius:14px; padding:14px 18px;
        }
        .bs-label {
            display:flex; align-items:center; gap:10px;
            cursor:pointer; font-size:14px; font-weight:600; color:#92400e; margin:0;
        }
        .toggle-switch { position:relative; width:44px; height:24px; flex-shrink:0; }
        .toggle-switch input { opacity:0; width:0; height:0; }
        .toggle-slider {
            position:absolute; inset:0; background:#d1d5db;
            border-radius:24px; transition:.3s; cursor:pointer;
        }
        .toggle-slider::before {
            content:''; position:absolute;
            width:18px; height:18px; left:3px; top:3px;
            background:#fff; border-radius:50%; transition:.3s;
        }
        .toggle-switch input:checked + .toggle-slider { background:#f59e0b; }
        .toggle-switch input:checked + .toggle-slider::before { transform:translateX(20px); }
        .bs-hint { font-size:12px; color:#b45309; margin-top:6px; }

        /* ── Gizi Box (identik edit_produk) ── */
        .gizi-box {
            background:#f0fdf4; border:1.5px solid #86efac;
            border-radius:14px; padding:18px 18px 10px;
        }
        .gizi-box .grid-4 input { background:#fff; }
        .gizi-box .grid-4 .form-group { margin-bottom:0; }
        .gizi-hint { font-size:11px; color:#6b7280; margin-top:10px; font-style:italic; }

        /* FILE UPLOAD */
        .file-upload-wrapper {
            position:relative; border:2px dashed var(--border);
            border-radius:12px; padding:16px 20px;
            text-align:center; cursor:pointer;
            transition:.25s; background:var(--bg);
        }
        .file-upload-wrapper:hover { border-color:var(--primary-light); background:#eff6ff; }
        .file-upload-wrapper input[type="file"] {
            position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%;
        }
        .file-upload-text { font-size:13px; color:var(--text-muted); pointer-events:none; }
        .file-upload-text i { color:var(--primary-light); margin-right:6px; }
        .file-note { font-size:12px; color:#94a3b8; margin-top:8px; display:block; }

        #img-preview {
            width:100%; max-height:220px; object-fit:contain;
            border-radius:12px; margin-top:12px; display:none;
            background:var(--bg); border:2px dashed var(--border); padding:10px;
        }

        /* BUTTONS */
        .btn-group { display:flex; flex-direction:column; gap:12px; margin-top:10px; }
        .btn-save {
            width:100%; background:var(--primary); color:#fff;
            border:none; padding:15px; border-radius:12px;
            font-size:15px; font-weight:700; font-family:'Inter',sans-serif;
            cursor:pointer;
            display:flex; align-items:center; justify-content:center; gap:10px;
            transition:.25s var(--ease);
        }
        .btn-save:hover {
            background:var(--primary-hover); transform:translateY(-2px);
            box-shadow:0 8px 24px rgba(30,58,138,.25);
        }
        .btn-cancel {
            display:block; text-align:center; text-decoration:none;
            color:var(--text-muted); font-size:14px; font-weight:500;
            padding:12px; border-radius:12px; border:1px solid var(--border); transition:.2s;
        }
        .btn-cancel:hover { background:#f1f5f9; color:var(--text-main); }

        /* ===== RESPONSIVE ===== */
        @media (max-width:1024px) {
            .sidebar { transform:translateX(-100%); }
            .sidebar.open { transform:translateX(0); }
            .sidebar.open .sidebar-close { display:flex; }
            .main-content { margin-left:0; padding:24px 20px; }
            .menu-toggle { display:inline-flex; }
        }
        @media (max-width:600px) {
            .main-content { padding:16px 14px; }
            .topbar { margin-bottom:20px; }
            .card-body { padding:20px; }
            .grid-2 { grid-template-columns:1fr; gap:0; }
            .grid-4 { grid-template-columns:1fr 1fr; gap:12px; }
            .admin-profile span { display:none; }
            .topbar-title h1 { font-size:20px; }
        }
        @media (max-width:400px) { .grid-4 { grid-template-columns:1fr 1fr; } }
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
        <div class="brand-text">MuslimahAdmin</div>
    </div>
    <ul class="sidebar-menu">
        <li><a href="admin.php"><i class="fa-solid fa-grid-2"></i><span>Dashboard</span></a></li>
        <li><a href="kelola_produk.php" class="active"><i class="fa-solid fa-cake-candles"></i><span>Produk Kue</span></a></li>
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

    <!-- TOP BAR -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-toggle" onclick="openSidebar()" aria-label="Buka sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="topbar-title">
                <h1>Tambah Produk</h1>
                <p>Tambah produk kue baru ke katalog</p>
            </div>
        </div>
        <div class="admin-profile">
            <div class="profile-icon">AD</div>
            <span style="font-weight:600;font-size:14px;"><?= htmlspecialchars($_SESSION['user']) ?></span>
        </div>
    </div>

    <!-- FORM CARD -->
    <div class="form-card">
        <div class="card-header">
            <div class="card-header-icon">
                <i class="fa-solid fa-plus"></i>
            </div>
            <div class="card-header-text">
                <h2>Produk Baru</h2>
                <p>Lengkapi detail kue untuk dipajang di katalog</p>
            </div>
        </div>

        <div class="card-body">
            <form action="" method="POST" enctype="multipart/form-data">

                <!-- ── Informasi Produk ── -->
                <p class="section-label">
                    <i class="fa-solid fa-tag" style="opacity:.6;"></i> Informasi Produk
                </p>

                <div class="form-group">
                    <label>Nama Produk</label>
                    <input type="text" name="nama_produk" placeholder="Misal: Brownies Panggang Almond" required>
                </div>

                <div class="form-group">
                    <label>Harga Jual</label>
                    <input type="number" name="harga" id="inputHarga" placeholder="Contoh: 50000" min="0" required>
                    <div class="rupiah-preview" id="showRupiah">
                        <i class="fa-solid fa-money-bill-wave"></i>
                        <span>Rp 0</span>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Stok Tersedia</label>
                        <input type="number" name="stok" placeholder="0" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="kategori" required>
                            <option value="" disabled selected>Pilih...</option>
                            <option value="dessert">Dessert</option>
                            <option value="roti">Roti</option>
                            <option value="kuekering">Kue Kering</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Deskripsi Kue</label>
                    <textarea name="deskripsi" placeholder="Jelaskan detail rasa, ukuran, atau topping..."></textarea>
                </div>

                <!-- ── Best Seller Toggle (muncul jika kolom ada di DB) ── -->
                <?php if ($has_bestseller): ?>
                <div class="form-group">
                    <div class="bs-toggle-wrap">
                        <label class="bs-label">
                            <span class="toggle-switch">
                                <input type="checkbox" name="is_bestseller" id="toggleBS">
                                <span class="toggle-slider"></span>
                            </span>
                            <span><i class="fa-solid fa-fire" style="color:#f59e0b;"></i> Tandai sebagai Best Seller</span>
                        </label>
                    </div>
                    <p class="bs-hint">Produk akan tampil dengan badge 🔥 Best Seller di halaman katalog.</p>
                </div>
                <?php endif; ?>

                <!-- ── Informasi Gizi (muncul jika minimal 1 kolom ada di DB) ── -->
                <?php if ($has_kalori || $has_protein || $has_lemak || $has_karbohidrat): ?>
                <hr class="divider">
                <p class="section-label">
                    <i class="fa-solid fa-chart-pie"></i> Informasi Gizi
                    <span style="font-weight:400;text-transform:none;letter-spacing:0;margin-left:4px;">(opsional)</span>
                </p>
                <div class="gizi-box">
                    <div class="grid-4">
                        <?php if ($has_kalori): ?>
                        <div class="form-group">
                            <label>Kalori (kcal)</label>
                            <input type="number" name="kalori" placeholder="250" min="0">
                        </div>
                        <?php endif; ?>
                        <?php if ($has_protein): ?>
                        <div class="form-group">
                            <label>Protein (g)</label>
                            <input type="number" name="protein" step="0.1" placeholder="5.2" min="0">
                        </div>
                        <?php endif; ?>
                        <?php if ($has_lemak): ?>
                        <div class="form-group">
                            <label>Lemak (g)</label>
                            <input type="number" name="lemak" step="0.1" placeholder="12.5" min="0">
                        </div>
                        <?php endif; ?>
                        <?php if ($has_karbohidrat): ?>
                        <div class="form-group">
                            <label>Karbo (g)</label>
                            <input type="number" name="karbohidrat" step="0.1" placeholder="30.0" min="0">
                        </div>
                        <?php endif; ?>
                    </div>
                    <p class="gizi-hint">* Kosongkan jika tidak diketahui. Data ditampilkan di pop-up detail produk.</p>
                </div>
                <?php endif; ?>

                <!-- ── Foto Produk ── -->
                <hr class="divider">
                <p class="section-label">
                    <i class="fa-solid fa-image" style="opacity:.6;"></i> Foto Produk
                </p>

                <div class="form-group">
                    <div class="file-upload-wrapper" id="dropZone">
                        <input type="file" name="gambar" id="inputFoto" accept="image/*" required>
                        <p class="file-upload-text">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            Klik atau seret foto ke sini
                        </p>
                        <span class="file-note">PNG, JPG, WEBP, GIF</span>
                    </div>
                    <img id="img-preview" src="" alt="Preview gambar">
                </div>

                <hr class="divider">

                <div class="btn-group">
                    <button type="submit" name="submit" class="btn-save">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan ke Katalog
                    </button>
                    <a href="kelola_produk.php" class="btn-cancel">
                        <i class="fa-solid fa-xmark" style="margin-right:6px;"></i>Batal
                    </a>
                </div>

            </form>
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

    // LIVE RUPIAH PREVIEW
    document.getElementById('inputHarga').addEventListener('input', function () {
        let val = parseInt(this.value) || 0;
        document.querySelector('#showRupiah span').textContent = 'Rp ' + val.toLocaleString('id-ID');
    });

    // PREVIEW GAMBAR
    const inputFoto  = document.getElementById('inputFoto');
    const imgPreview = document.getElementById('img-preview');
    const dropZone   = document.getElementById('dropZone');

    inputFoto.addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
            imgPreview.src = URL.createObjectURL(file);
            imgPreview.style.display = 'block';
            dropZone.style.borderColor = 'var(--primary-light)';
            dropZone.querySelector('.file-upload-text').innerHTML =
                '<i class="fa-solid fa-check" style="color:#16a34a;"></i> ' + file.name;
        }
    });
</script>
</body>
</html>
<?php
date_default_timezone_set('Asia/Jakarta');

ob_start();
session_start();
include 'koneksi.php';

function isJabodetabek(string $lokasi): bool
{
    $teks = strtolower(trim($lokasi));
    if ($teks === '') return false;

    $teks = preg_replace('/\b(kota|kabupaten|kab\.?)\s+/i', '', $teks);

    if (str_contains($teks, 'kepulauan seribu') || str_contains($teks, 'pulau seribu')) {
        return false;
    }

    $wilayah_didukung = [
        'jakarta pusat', 'jakpus',
        'jakarta barat', 'jakbar',
        'jakarta timur', 'jaktim',
        'jakarta selatan', 'jaksel',
        'jakarta utara', 'jakut',

        'bogor',

        'depok',

        'tangerang selatan', 'tangerang sel', 'tangsel',
        'tangerang',

        'bekasi',
    ];

    foreach ($wilayah_didukung as $wilayah) {
        if (str_contains($teks, $wilayah)) return true;
    }
    return false;
}

if (isset($_POST['cek_kota_ajax'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    $kota  = trim($_POST['kota'] ?? '');
    $valid = $kota !== '' && isJabodetabek($kota);
    echo json_encode([
        'valid'   => $valid,
        'message' => $valid ? '' : 'Maaf, layanan pengiriman saat ini hanya tersedia untuk wilayah Jabodetabek.'
    ]);
    exit;
}

if (!isset($_SESSION['user'])) {
    ob_end_clean();
    header('Location: login.php');
    exit;
}

$uname     = $_SESSION['user'];
$uname_esc = mysqli_real_escape_string($conn, $uname);
$cek     = mysqli_query($conn, "SELECT id FROM users WHERE username = '$uname_esc'");
$data    = $cek ? mysqli_fetch_assoc($cek) : null;

// FIX: kalau user tidak ditemukan di database (misal akun sudah dihapus tapi session masih aktif),
// jangan lanjut proses — arahkan ke login lagi.
if (!$data) {
    ob_end_clean();
    header('Location: login.php');
    exit;
}
$id_user = $data['id'];

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    ob_end_clean();
    header('Location: index.php');
    exit;
}

$total_bayar = 0;
foreach ($_SESSION['cart'] as $id_produk => $jumlah) {
    // FIX: cast ke int untuk konsistensi/defense-in-depth sebelum dipakai di query
    $id_produk = (int)$id_produk;
    $ambil = mysqli_query($conn, "SELECT harga FROM produk WHERE id_produk = '$id_produk'");
    $pecah = $ambil ? mysqli_fetch_assoc($ambil) : null;
    if ($pecah) $total_bayar += $pecah['harga'] * $jumlah;
}

$old = [
    'nama'              => '',
    'hp'                => '',
    'alamat_lengkap'    => '',
    'kota'              => '',
    'kodepos'           => '',
    'metode_pembayaran' => '',
];
$error = '';

// FIX: daftar ekstensi & MIME type yang diizinkan untuk bukti pembayaran.
// Dicek dari ISI file (bukan cuma nama file) supaya tidak bisa dikelabui
// dengan mengganti nama file berbahaya jadi berakhiran .jpg/.png/.pdf.
const EKSTENSI_BUKTI_DIIZINKAN = ['jpg', 'jpeg', 'png', 'pdf'];
const MIME_BUKTI_DIIZINKAN = [
    'image/jpeg',
    'image/png',
    'application/pdf',
];

// FIX: metode pembayaran hanya boleh salah satu dari daftar ini
const METODE_PEMBAYARAN_DIIZINKAN = ['BANK', 'EWALLET'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    $error = 'Ukuran data yang dikirim (foto bukti pembayaran + form) terlalu besar untuk server saat ini. '
           . 'Silakan kompres/perkecil foto bukti pembayaran (disarankan di bawah 1 MB) lalu coba kirim ulang. '
           . 'Kalau masih gagal, batas ukuran upload di server (post_max_size / upload_max_filesize) perlu dinaikkan oleh admin.';
}

if (isset($_POST['checkout'])) {
    $old['nama']              = trim($_POST['nama'] ?? '');
    $old['hp']                = trim($_POST['hp'] ?? '');
    $old['alamat_lengkap']    = trim($_POST['alamat_lengkap'] ?? '');
    $old['kota']              = trim($_POST['kota'] ?? '');
    $old['kodepos']           = trim($_POST['kodepos'] ?? '');
    $old['metode_pembayaran'] = $_POST['metode_pembayaran'] ?? '';

    $MAKS_UKURAN_BUKTI = 2 * 1024 * 1024;

    if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] === UPLOAD_ERR_INI_SIZE) {
        $error = 'Ukuran file bukti pembayaran melebihi batas server. Silakan kompres foto terlebih dahulu.';
    } elseif (isset($_FILES['bukti']) && $_FILES['bukti']['error'] === 0 && $_FILES['bukti']['size'] > $MAKS_UKURAN_BUKTI) {
        $error = 'Ukuran file bukti pembayaran maksimal 2 MB. File Anda terlalu besar, silakan kompres dahulu.';
    }
    // FIX: validasi metode pembayaran harus salah satu dari whitelist yang diizinkan
    elseif (!in_array($old['metode_pembayaran'], METODE_PEMBAYARAN_DIIZINKAN, true)) {
        $error = 'Metode pembayaran tidak valid. Silakan pilih ulang metode pembayaran.';
    }
    // FIX: validasi ekstensi DAN tipe MIME file bukti pembayaran (bukan cuma dari nama file)
    elseif (isset($_FILES['bukti']) && $_FILES['bukti']['error'] === 0) {
        $ext_upload = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
        $mime_upload = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_upload = finfo_file($finfo, $_FILES['bukti']['tmp_name']);
            finfo_close($finfo);
        }

        if (!in_array($ext_upload, EKSTENSI_BUKTI_DIIZINKAN, true) || !in_array($mime_upload, MIME_BUKTI_DIIZINKAN, true)) {
            $error = 'Format file bukti pembayaran tidak valid. Hanya JPG, PNG, atau PDF yang diizinkan.';
        }
    }
    elseif (!isJabodetabek($old['kota'])) {
        $error = 'Maaf, layanan pengiriman saat ini hanya tersedia untuk wilayah Jabodetabek.';
    }

    if ($error === '') {
        $tanggal_pesan  = date("Y-m-d H:i:s");
        $metode         = $old['metode_pembayaran']; // sudah divalidasi lewat whitelist di atas
        $bukti_filename = NULL;

        if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] === 0) {
            // FIX: ekstensi file baru dibuat dari whitelist yang sudah tervalidasi, bukan dari input mentah user
            $ext_upload     = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
            $bukti_filename = 'bukti_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext_upload;
            $folder_bukti   = 'uploads/bukti/';
            // FIX: permission folder diperketat ke 0755 (bukan 0777)
            if (!is_dir($folder_bukti)) @mkdir($folder_bukti, 0755, true);
            if (!@move_uploaded_file($_FILES['bukti']['tmp_name'], $folder_bukti . $bukti_filename)) {
                $bukti_filename = NULL;
            }
        }

        $nama_penerima = mysqli_real_escape_string($conn, $old['nama']);
        $no_hp         = mysqli_real_escape_string($conn, $old['hp']);
        $alamat        = mysqli_real_escape_string($conn, $old['alamat_lengkap']);
        $kota          = mysqli_real_escape_string($conn, $old['kota']);
        $kodepos       = mysqli_real_escape_string($conn, $old['kodepos']);
        $metode_esc    = mysqli_real_escape_string($conn, $metode);

        $insert_pesanan = mysqli_query($conn,
            "INSERT INTO pesanan (id_user, nama_penerima, no_hp, alamat, kota, kodepos, tanggal_pesan, total_bayar, metode_pembayaran, status_pesanan, bukti_pembayaran)
            VALUES ('$id_user','$nama_penerima','$no_hp','$alamat','$kota','$kodepos','$tanggal_pesan','$total_bayar','$metode_esc','Pending','$bukti_filename')"
        );

        if ($insert_pesanan) {
            $id_pesanan_baru = mysqli_insert_id($conn);
            foreach ($_SESSION['cart'] as $id_produk => $jumlah) {
                $id_produk = (int)$id_produk;
                $jumlah    = (int)$jumlah;
                $ambil_p   = mysqli_query($conn, "SELECT * FROM produk WHERE id_produk = '$id_produk'");
                $p         = $ambil_p ? mysqli_fetch_assoc($ambil_p) : null;
                if (!$p) continue;
                $sub = $p['harga'] * $jumlah;
                mysqli_query($conn,
                    "INSERT INTO detail_pesanan (id_pesanan, id_produk, nama_produk, harga, jumlah, subtotal)
                     VALUES ('$id_pesanan_baru','$id_produk','$p[nama_produk]','$p[harga]','$jumlah','$sub')"
                );
                mysqli_query($conn,
                    "UPDATE produk SET stok = GREATEST(0, stok - $jumlah) WHERE id_produk = '$id_produk'"
                );
            }
            unset($_SESSION['cart']);
            ob_end_clean();
            header('Location: nota.php?id=' . $id_pesanan_baru);
            exit;
        } else {
            $error = 'Terjadi kesalahan saat menyimpan pesanan. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — Musimah Bakery</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playball&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy:       #0f172a;
            --navy-light: #1e3a8a;
            --text-main:  #1e293b;
            --text-muted: #64748b;
            --border:     #e2e8f0;
            --bg:         #f1f5f9;
            --white:      #ffffff;
            --accent:     #f43f5e;
            --success:    #16a34a;
            --danger:     #dc2626;
            --danger-bg:  #fef2f2;
            --radius:     14px;
            --shadow:     0 2px 16px rgba(15,23,42,0.07);
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        html { scroll-behavior:smooth; }
        body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text-main); line-height:1.6; min-height:100vh; }

        header {
            background:var(--navy); padding:0 5%; height:64px;
            display:flex; justify-content:space-between; align-items:center;
            position:sticky; top:0; z-index:100;
            box-shadow:0 4px 20px rgba(0,0,0,0.15);
        }
        .logo { font-family:'Playball',cursive; color:#fff; font-size:1.4rem; text-decoration:none; display:flex; align-items:center; gap:8px; }
        .btn-back-nav {
            display:flex; align-items:center; gap:7px; color:#94a3b8; text-decoration:none;
            font-size:0.8rem; font-weight:500; padding:8px 14px;
            border:1px solid rgba(255,255,255,0.15); border-radius:8px; transition:0.25s;
        }
        .btn-back-nav:hover { color:#fff; border-color:rgba(255,255,255,0.4); background:rgba(255,255,255,0.08); }

        .progress-bar { background:var(--white); border-bottom:1px solid var(--border); padding:14px 5%; }
        .progress-steps { max-width:500px; margin:0 auto; display:flex; align-items:center; justify-content:center; }
        .step { display:flex; flex-direction:column; align-items:center; gap:5px; flex:1; }
        .step-circle {
            width:32px; height:32px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-size:0.75rem; font-weight:700;
            background:#e2e8f0; color:var(--text-muted); transition:0.3s;
        }
        .step.done  .step-circle { background:var(--success); color:#fff; }
        .step.active .step-circle { background:var(--navy); color:#fff; box-shadow:0 0 0 4px rgba(15,23,42,0.12); }
        .step-label { font-size:0.68rem; color:var(--text-muted); font-weight:500; }
        .step.active .step-label { color:var(--navy); font-weight:700; }
        .step.done  .step-label  { color:var(--success); }
        .step-line { flex:1; height:2px; background:#e2e8f0; margin-bottom:18px; }
        .step-line.done { background:var(--success); }

        .page-wrap {
            max-width:1060px; margin:0 auto; padding:24px 20px 60px;
            display:grid; grid-template-columns:1fr 360px; gap:22px; align-items:start;
        }

        .card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; margin-bottom:18px; }
        .card:last-child { margin-bottom:0; }
        .card-head {
            padding:16px 20px; border-bottom:1px solid var(--border);
            display:flex; align-items:center; gap:10px;
            background:linear-gradient(135deg, var(--navy) 0%, #1e3a8a 100%);
        }
        .card-head h2 { font-size:0.9rem; font-weight:700; color:#fff; }
        .card-head i  { color:#93c5fd; font-size:0.95rem; }
        .card-head .badge { margin-left:auto; background:rgba(255,255,255,0.15); color:#fff; font-size:0.62rem; font-weight:700; padding:3px 9px; border-radius:20px; }
        .card-body { padding:18px 20px; }

        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:320px; }
        th { background:#f8fafc; padding:10px 14px; text-align:left; font-size:0.68rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted); border-bottom:1px solid var(--border); font-weight:600; }
        td { padding:12px 14px; border-bottom:1px solid #f1f5f9; vertical-align:middle; font-size:0.86rem; }
        tbody tr:last-child td { border-bottom:none; }
        tfoot td { background:#f8fafc; border-top:2px solid var(--border); border-bottom:none; font-weight:700; }

        .product-cell { display:flex; align-items:center; gap:11px; }
        .product-cell img { width:44px; height:44px; object-fit:cover; border-radius:8px; border:1px solid var(--border); flex-shrink:0; }
        .product-name { font-weight:600; color:var(--navy); font-size:0.86rem; }

        .qty-badge { display:inline-flex; align-items:center; justify-content:center; background:#eff6ff; color:var(--navy-light); font-weight:700; font-size:0.8rem; border-radius:6px; padding:3px 10px; border:1px solid #bfdbfe; }

        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px; }
        .form-group { display:flex; flex-direction:column; gap:5px; margin-bottom:12px; }
        .form-group:last-child { margin-bottom:0; }
        label.f-label { font-size:0.68rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; }
        .req { color:var(--accent); }
        .f-control {
            width:100%; padding:10px 12px;
            border:1.5px solid var(--border); border-radius:9px;
            font-family:'Inter',sans-serif; font-size:0.86rem; color:var(--text-main);
            background:var(--white); outline:none; transition:0.2s; appearance:none;
        }
        .f-control:focus { border-color:var(--navy); box-shadow:0 0 0 3px rgba(15,23,42,0.08); }
        .f-control::placeholder { color:#b0bec5; }
        textarea.f-control { resize:vertical; min-height:80px; }
        select.f-control {
            cursor:pointer;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat:no-repeat; background-position:right 12px center; padding-right:36px;
        }
        .prefix-wrap { display:flex; border:1.5px solid var(--border); border-radius:9px; overflow:hidden; transition:0.2s; }
        .prefix-wrap:focus-within { border-color:var(--navy); box-shadow:0 0 0 3px rgba(15,23,42,0.08); }
        .prefix-tag { padding:10px 11px; background:#f8fafc; color:var(--text-muted); font-size:0.8rem; font-weight:600; border-right:1.5px solid var(--border); display:flex; align-items:center; white-space:nowrap; }
        .prefix-wrap input { border:none; border-radius:0; box-shadow:none; flex:1; }
        .prefix-wrap input:focus { box-shadow:none; }

        .pay-box { background:linear-gradient(135deg,#f0f9ff,#e0f2fe); border:1.5px solid #bae6fd; border-radius:10px; padding:13px 15px; display:flex; align-items:center; gap:13px; margin-top:10px; }
        .pay-box.ewallet { background:linear-gradient(135deg,#f5f3ff,#ede9fe); border-color:#c4b5fd; }
        .pay-icon { width:40px; height:40px; background:var(--navy); border-radius:9px; display:flex; align-items:center; justify-content:center; color:white; font-size:0.95rem; flex-shrink:0; }
        .pay-icon.purple { background:#7c3aed; }
        .pay-info .bank   { font-weight:700; font-size:0.8rem; color:var(--navy); }
        .pay-info .acc    { font-size:0.95rem; font-weight:800; letter-spacing:1.5px; color:var(--text-main); margin:2px 0; }
        .pay-info .holder { font-size:0.65rem; color:var(--text-muted); text-transform:uppercase; }

        .file-drop { border:2px dashed var(--border); border-radius:10px; padding:18px; text-align:center; cursor:pointer; transition:0.2s; position:relative; background:#fafbfc; }
        .file-drop:hover { border-color:var(--navy); background:#f0f4ff; }
        .file-drop input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
        .up-icon { font-size:1.5rem; color:#94a3b8; margin-bottom:5px; display:block; }
        .file-drop p { font-size:0.78rem; color:var(--text-muted); margin:0; }
        .file-drop strong { color:var(--navy); }
        #file-name { font-size:0.73rem; color:var(--success); margin-top:5px; font-weight:600; display:none; }

        .divider { height:1px; background:var(--border); margin:14px 0; }

        .sum-row { display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid #f1f5f9; font-size:0.84rem; }
        .sum-row .lbl { color:var(--text-muted); }
        .sum-row .val { font-weight:600; }
        .sum-total { display:flex; justify-content:space-between; align-items:center; padding:12px 0 0; border-top:2px solid var(--border); margin-top:4px; }
        .sum-total .lbl { font-weight:700; font-size:0.88rem; color:var(--navy); }
        .sum-total .val { font-weight:800; font-size:1.1rem; color:var(--navy); }

        .btn-checkout {
            width:100%; padding:13px; background:var(--navy); color:#fff;
            border:none; border-radius:10px; font-family:'Inter',sans-serif;
            font-size:0.88rem; font-weight:700; cursor:pointer; transition:0.3s;
            display:flex; align-items:center; justify-content:center; gap:9px; margin-top:14px;
        }
        .btn-checkout:hover:not(:disabled) { background:#000; transform:translateY(-2px); box-shadow:0 8px 24px rgba(15,23,42,0.2); }
        .btn-checkout:disabled { background:#94a3b8; cursor:not-allowed; transform:none; box-shadow:none; }
        .btn-back-link {
            display:flex; align-items:center; justify-content:center; gap:7px;
            width:100%; padding:10px; margin-top:9px;
            border:1.5px solid var(--border); border-radius:10px;
            text-decoration:none; color:var(--text-muted); font-size:0.8rem; font-weight:500; transition:0.2s;
        }
        .btn-back-link:hover { border-color:var(--navy); color:var(--navy); background:#f8fafc; }

        .sticky-total {
            display:none; position:fixed; bottom:0; left:0; right:0; z-index:90;
            background:var(--white); border-top:1px solid var(--border);
            padding:12px 18px; box-shadow:0 -4px 20px rgba(0,0,0,0.1);
            align-items:center; justify-content:space-between; gap:12px;
        }
        .st-info p { font-size:0.68rem; color:var(--text-muted); margin-bottom:1px; }
        .st-info strong { font-size:1rem; font-weight:800; color:var(--navy); }
        .st-btn {
            background:var(--navy); color:#fff; border:none; border-radius:9px;
            padding:11px 20px; font-size:0.84rem; font-weight:700; cursor:pointer;
            display:flex; align-items:center; gap:7px; white-space:nowrap; font-family:'Inter',sans-serif;
        }
        .st-btn:active { background:#000; }
        .st-btn:disabled { background:#94a3b8; cursor:not-allowed; }

        .alert-error {
            max-width:1060px; margin:16px auto 0; padding:13px 18px;
            background:var(--danger-bg); border:1.5px solid #fecaca; color:var(--danger);
            border-radius:10px; font-size:0.85rem; font-weight:600;
            display:flex; align-items:center; gap:10px;
        }

        .f-control.kota-invalid { border-color:var(--danger); background:var(--danger-bg); }
        .f-control.kota-valid { border-color:var(--success); background:#f0fdf4; }
        #kota-msg { font-size:0.72rem; margin-top:5px; display:none; align-items:center; gap:5px; font-weight:600; }
        #kota-msg.show { display:flex; }
        #kota-msg.error { color:var(--danger); }
        #kota-msg.ok { color:var(--success); }
        .kota-hint { font-size:0.68rem; color:var(--text-muted); margin-top:4px; }

        .alamat-gate-msg { font-size:0.78rem; font-weight:600; margin-top:12px; display:none; align-items:center; gap:8px; padding:10px 12px; border-radius:9px; }
        .alamat-gate-msg.show { display:flex; }
        .alamat-gate-msg.error { background:var(--danger-bg); color:var(--danger); }
        .alamat-gate-msg.ok { background:#f0fdf4; color:var(--success); }

        .payment-locked { text-align:center; padding:30px 10px; color:var(--text-muted); }
        .payment-locked .lock-icon {
            width:52px; height:52px; margin:0 auto 14px; border-radius:50%;
            background:#f1f5f9; color:#94a3b8; display:flex; align-items:center; justify-content:center; font-size:1.2rem;
        }
        .payment-locked p { font-size:0.82rem; line-height:1.6; }

        @media (max-width:900px) {
            .page-wrap { grid-template-columns:1fr; padding:14px 14px 100px; gap:14px; }
            .sticky-total { display:flex; }
            .btn-checkout-wrap { display:none; }
            .alert-error { margin:14px 14px 0; }
        }
        @media (max-width:520px) {
            header { padding:0 4%; }
            .logo { font-size:1.15rem; }
            .progress-bar { padding:10px 4%; }
            .step-label { font-size:0.6rem; }
            .form-row { grid-template-columns:1fr; gap:0; }
            .card-body { padding:14px; }
            .card-head { padding:13px 14px; }
        }
    </style>
</head>
<body>

<header>
    <a href="index.php" class="logo">
        <i class="fa-solid fa-cake-candles" style="font-size:0.95rem;color:#fbbf24;"></i>
        Musimah Bakery
    </a>
    <a href="cart.php" class="btn-back-nav">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Keranjang</span>
    </a>
</header>

<div class="progress-bar">
    <div class="progress-steps">
        <div class="step done">
            <div class="step-circle"><i class="fa-solid fa-check" style="font-size:0.65rem;"></i></div>
            <span class="step-label">Keranjang</span>
        </div>
        <div class="step-line done"></div>
        <div class="step active">
            <div class="step-circle">2</div>
            <span class="step-label">Checkout</span>
        </div>
        <div class="step-line"></div>
        <div class="step">
            <div class="step-circle">3</div>
            <span class="step-label">Selesai</span>
        </div>
    </div>
</div>

<?php if ($error): ?>
<div class="alert-error">
    <i class="fa-solid fa-circle-exclamation"></i>
    <span><?= htmlspecialchars($error) ?></span>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="checkoutForm">
<div class="page-wrap">

    <div>
        <div class="card">
            <div class="card-head">
                <i class="fa-solid fa-list-ul"></i>
                <h2>Rincian Pesanan</h2>
                <span class="badge"><?= count($_SESSION['cart']) ?> produk</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th style="text-align:center;">Jml</th>
                            <th style="text-align:right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_SESSION['cart'] as $id_produk => $jumlah):
                            $id_produk = (int)$id_produk;
                            $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM produk WHERE id_produk='$id_produk'"));
                            if (!$row) continue;
                        ?>
                        <tr>
                            <td>
                                <div class="product-cell">
                                    <img src="<?= htmlspecialchars($row['gambar']) ?>" alt="<?= htmlspecialchars($row['nama_produk']) ?>" onerror="this.src='https://via.placeholder.com/44?text=Kue'">
                                    <span class="product-name"><?= htmlspecialchars($row['nama_produk']) ?></span>
                                </div>
                            </td>
                            <td style="text-align:center;"><span class="qty-badge"><?= (int)$jumlah ?></span></td>
                            <td style="text-align:right; font-weight:700; color:var(--navy);">
                                Rp <?= number_format($row['harga'] * $jumlah, 0, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" style="text-align:right; color:var(--text-muted); font-weight:500; font-size:0.8rem;">Total</td>
                            <td style="text-align:right; color:var(--navy); font-size:0.95rem;">Rp <?= number_format($total_bayar, 0, ',', '.') ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <i class="fa-solid fa-location-dot"></i>
                <h2>Alamat Pengiriman</h2>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="f-label">Nama Penerima <span class="req">*</span></label>
                        <input type="text" name="nama" class="f-control" placeholder="Nama lengkap" value="<?= htmlspecialchars($old['nama']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="f-label">No. WhatsApp <span class="req">*</span></label>
                        <div class="prefix-wrap">
                            <span class="prefix-tag">+62</span>
                            <input type="text" name="hp" class="f-control" placeholder="8xxxxxxxxxx" value="<?= htmlspecialchars($old['hp']) ?>" required>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="f-label">Alamat Lengkap <span class="req">*</span></label>
                    <textarea name="alamat_lengkap" class="f-control" placeholder="Nama jalan, No. rumah, RT/RW, Kelurahan, Kecamatan..." required><?= htmlspecialchars($old['alamat_lengkap']) ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="f-label">Kota / Kabupaten <span class="req">*</span></label>
                        <input type="text" name="kota" id="kotaInput" class="f-control" placeholder="Contoh: Bekasi" value="<?= htmlspecialchars($old['kota']) ?>" required autocomplete="off">
                        <div id="kota-msg"></div>
                        <div class="kota-hint">Area yang dilayani: Jakarta Pusat/Barat/Timur/Selatan/Utara, Kota/Kabupaten Bogor, Kota Depok, Kota/Kabupaten Tangerang, Kota Tangerang Selatan, Kota/Kabupaten Bekasi. (Kepulauan Seribu belum dilayani)</div>
                    </div>
                    <div class="form-group">
                        <label class="f-label">Kode Pos <span class="req">*</span></label>
                        <input type="text" name="kodepos" id="kodeposInput" class="f-control" placeholder="17157" value="<?= htmlspecialchars($old['kodepos']) ?>" required>
                    </div>
                </div>

                <div id="alamat-gate-msg" class="alamat-gate-msg"></div>

                <button type="button" id="btnLanjutBayar" class="btn-checkout" style="margin-top:14px;">
                    <span id="btnLanjutBayarLabel"><i class="fa-solid fa-arrow-right"></i> Lanjutkan ke Pembayaran</span>
                </button>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-head">
                <i class="fa-solid fa-wallet"></i>
                <h2>Pembayaran</h2>
            </div>
            <div class="card-body">

                <div id="paymentLocked" class="payment-locked">
                    <div class="lock-icon"><i class="fa-solid fa-lock"></i></div>
                    <p>Lengkapi dan validasi <strong>alamat pengiriman</strong> terlebih dahulu.<br>Metode pembayaran akan muncul di sini setelah alamat dinyatakan berada di wilayah Jabodetabek.</p>
                </div>

                <div id="paymentUnlocked" style="display:none;">

                <div class="form-group">
                    <label class="f-label">Metode Pembayaran <span class="req">*</span></label>
                    <select name="metode_pembayaran" class="f-control" required id="metodeSelect">
                        <option value="">— Pilih Metode —</option>
                        <option value="BANK" <?= $old['metode_pembayaran'] === 'BANK' ? 'selected' : '' ?>>Transfer Bank</option>
                        <option value="EWALLET" <?= $old['metode_pembayaran'] === 'EWALLET' ? 'selected' : '' ?>>E-Wallet (Gopay / Dana)</option>
                    </select>
                </div>

                <div class="pay-box" id="box-bank" style="display:none;">
                    <div class="pay-icon"><i class="fa-solid fa-building-columns"></i></div>
                    <div class="pay-info">
                        <div class="bank">Bank Mandiri</div>
                        <div class="acc">1660 0037 3832 5</div>
                        <div class="holder">Nazwa Amanda Syifa</div>
                    </div>
                </div>

                <div class="pay-box ewallet" id="box-ewallet" style="display:none;">
                    <div class="pay-icon purple"><i class="fa-solid fa-wallet"></i></div>
                    <div class="pay-info">
                        <div class="bank">Gopay / Dana</div>
                        <div class="acc">0822-3599-8521</div>
                        <div class="holder">Nazwa Amanda Syifa</div>
                    </div>
                </div>

                <div id="section-pembayaran" style="display:none;">

                    <div class="divider"></div>

                    <div class="form-group">
                        <label class="f-label">Bukti Pembayaran <span class="req">*</span></label>
                        <div class="file-drop" id="dropArea">
                            <input type="file" name="bukti" accept=".jpg,.jpeg,.png,.pdf" required id="fileInput">
                            <i class="fa-solid fa-cloud-arrow-up up-icon"></i>
                            <p><strong>Klik untuk upload</strong> atau seret ke sini</p>
                            <p style="margin-top:2px;">JPG, PNG, PDF — maks 2 MB</p>
                            <div id="file-name"></div>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="sum-row">
                        <span class="lbl">Subtotal produk</span>
                        <span class="val">Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
                    </div>
                    <div class="sum-row" style="border-bottom:none;">
                        <span class="lbl">Ongkos kirim</span>
                        <span class="val" style="color:var(--success);">Gratis</span>
                    </div>
                    <div class="sum-total">
                        <span class="lbl">Total Bayar</span>
                        <span class="val">Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
                    </div>

                    <div class="btn-checkout-wrap">
                        <button type="submit" name="checkout" value="1" class="btn-checkout" id="btnCheckoutDesktop">
                            <i class="fa-solid fa-paper-plane"></i> Kirim Pesanan
                        </button>
                        <a href="cart.php" class="btn-back-link">
                            <i class="fa-solid fa-arrow-left"></i> Kembali ke Keranjang
                        </a>
                    </div>

                </div>
                </div>
            </div>
        </div>
    </div>

</div>

<div class="sticky-total" id="stickyTotal">
    <div class="st-info">
        <p>Total Bayar</p>
        <strong>Rp <?= number_format($total_bayar, 0, ',', '.') ?></strong>
    </div>
    <button class="st-btn" id="btnCheckoutMobile" type="submit" name="checkout" value="1">
        <i class="fa-solid fa-paper-plane"></i> Kirim Pesanan
    </button>
</div>
</form>

<script>
const MAKS_UKURAN_BUKTI = 2 * 1024 * 1024;

const fileInput = document.getElementById('fileInput');
const fileName  = document.getElementById('file-name');
fileInput.addEventListener('change', function () {
    if (this.files.length > 0) {
        const file = this.files[0];

        if (file.size > MAKS_UKURAN_BUKTI) {
            alert('Ukuran file "' + file.name + '" adalah ' + (file.size / 1024 / 1024).toFixed(1) +
                  ' MB, melebihi batas maksimal 2 MB.\n\nSilakan pilih foto lain atau kompres foto ini terlebih dahulu.');
            this.value = '';
            fileName.style.display = 'none';
            document.getElementById('dropArea').style.borderColor = '';
            return;
        }

        fileName.textContent = '✓ ' + file.name;
        fileName.style.display = 'block';
        document.getElementById('dropArea').style.borderColor = '#16a34a';
    }
});

const metodeSelect      = document.getElementById('metodeSelect');
const boxBank           = document.getElementById('box-bank');
const boxEwallet        = document.getElementById('box-ewallet');
const sectionPembayaran = document.getElementById('section-pembayaran');
const stickyTotal       = document.getElementById('stickyTotal');
const paymentLocked     = document.getElementById('paymentLocked');
const paymentUnlocked   = document.getElementById('paymentUnlocked');

function isMobile() { return window.innerWidth <= 900; }

function updatePayBox() {
    const val = metodeSelect.value;
    const aktif = val === 'BANK' || val === 'EWALLET';

    boxBank.style.display    = val === 'BANK'    ? 'flex' : 'none';
    boxEwallet.style.display = val === 'EWALLET' ? 'flex' : 'none';
    sectionPembayaran.style.display = aktif ? 'block' : 'none';
    stickyTotal.style.display = (aktif && isMobile() && alamatTervalidasi) ? 'flex' : 'none';
    updateSubmitState();
}

metodeSelect.addEventListener('change', updatePayBox);
window.addEventListener('resize', updatePayBox);

const kotaInput  = document.getElementById('kotaInput');
const kotaMsg    = document.getElementById('kota-msg');
const btnDesktop = document.getElementById('btnCheckoutDesktop');
const btnMobile  = document.getElementById('btnCheckoutMobile');
let kotaValid = null;
let debounceTimer = null;

const PESAN_WILAYAH_TIDAK_DIDUKUNG = 'Maaf, layanan pengiriman saat ini hanya tersedia untuk wilayah Jabodetabek.';

function isJabodetabekJS(lokasiRaw) {
    let teks = (lokasiRaw || '').toLowerCase().trim();
    if (!teks) return false;

    teks = teks.replace(/\b(kota|kabupaten|kab\.?)\s+/gi, '');

    if (teks.includes('kepulauan seribu') || teks.includes('pulau seribu')) {
        return false;
    }

    const wilayahDidukung = [
        'jakarta pusat', 'jakpus',
        'jakarta barat', 'jakbar',
        'jakarta timur', 'jaktim',
        'jakarta selatan', 'jaksel',
        'jakarta utara', 'jakut',
        'bogor',
        'depok',
        'tangerang selatan', 'tangerang sel', 'tangsel',
        'tangerang',
        'bekasi',
    ];

    return wilayahDidukung.some(w => teks.includes(w));
}

function cekWilayah(kota) {
    if (!kota) {
        kotaMsg.classList.remove('show');
        kotaInput.classList.remove('kota-valid', 'kota-invalid');
        kotaValid = null;
        updateSubmitState();
        return kotaValid;
    }

    kotaValid = isJabodetabekJS(kota);

    kotaMsg.classList.add('show');
    if (kotaValid) {
        kotaMsg.className = 'show ok';
        kotaMsg.innerHTML = '<i class="fa-solid fa-circle-check"></i> Wilayah didukung untuk pengiriman.';
        kotaInput.classList.remove('kota-invalid');
        kotaInput.classList.add('kota-valid');
    } else {
        kotaMsg.className = 'show error';
        kotaMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + PESAN_WILAYAH_TIDAK_DIDUKUNG;
        kotaInput.classList.remove('kota-valid');
        kotaInput.classList.add('kota-invalid');
    }

    updateSubmitState();
    return kotaValid;
}

function updateSubmitState() {
    const blokir = (kotaValid === false) || !alamatTervalidasi;
    if (btnDesktop) btnDesktop.disabled = blokir;
    if (btnMobile)  btnMobile.disabled  = blokir;
}

kotaInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => cekWilayah(kotaInput.value.trim()), 450);
});

const namaInput      = document.querySelector('input[name="nama"]');
const hpInput        = document.querySelector('input[name="hp"]');
const alamatTextarea = document.querySelector('textarea[name="alamat_lengkap"]');
const kodeposInput   = document.getElementById('kodeposInput');
const btnLanjutBayar = document.getElementById('btnLanjutBayar');
const btnLanjutLabel = document.getElementById('btnLanjutBayarLabel');
const gateMsg        = document.getElementById('alamat-gate-msg');

let alamatTervalidasi = false;

function tampilkanGateMsg(pesan, tipe) {
    gateMsg.className = 'alamat-gate-msg show ' + tipe;
    gateMsg.innerHTML = (tipe === 'ok'
        ? '<i class="fa-solid fa-circle-check"></i> '
        : '<i class="fa-solid fa-circle-exclamation"></i> ') + pesan;
}

function kuncKembaliPembayaran() {
    if (!alamatTervalidasi) return;
    alamatTervalidasi = false;
    paymentUnlocked.style.display = 'none';
    paymentLocked.style.display   = 'block';
    stickyTotal.style.display     = 'none';
    metodeSelect.value = '';
    updatePayBox();
}

async function validasiAlamatUntukLanjut() {
    const nama    = namaInput.value.trim();
    const hp      = hpInput.value.trim();
    const alamat  = alamatTextarea.value.trim();
    const kota    = kotaInput.value.trim();
    const kodepos = kodeposInput.value.trim();

    if (!nama || !hp || !alamat || !kota || !kodepos) {
        tampilkanGateMsg('Lengkapi semua data alamat pengiriman terlebih dahulu.', 'error');
        return;
    }

    btnLanjutBayar.disabled = true;
    btnLanjutLabel.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memvalidasi alamat...';

    const valid = await cekWilayah(kota);

    btnLanjutBayar.disabled = false;
    btnLanjutLabel.innerHTML = '<i class="fa-solid fa-arrow-right"></i> Lanjutkan ke Pembayaran';

    if (valid === true) {
        alamatTervalidasi = true;
        gateMsg.classList.remove('show');
        paymentLocked.style.display   = 'none';
        paymentUnlocked.style.display = 'block';
        updatePayBox();
        paymentUnlocked.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        alamatTervalidasi = false;
        tampilkanGateMsg('Maaf, layanan pengiriman saat ini hanya tersedia untuk wilayah Jabodetabek.', 'error');
    }
}

btnLanjutBayar.addEventListener('click', validasiAlamatUntukLanjut);

[namaInput, hpInput, alamatTextarea, kotaInput, kodeposInput].forEach(el => {
    el.addEventListener('input', kuncKembaliPembayaran);
});

if (kotaInput.value.trim()) {
    cekWilayah(kotaInput.value.trim());
}

let sedangMengirim = false;

document.getElementById('checkoutForm').addEventListener('submit', function (e) {
    if (!alamatTervalidasi || kotaValid === false) {
        e.preventDefault();
        tampilkanGateMsg('Alamat pengiriman belum tervalidasi. Silakan validasi ulang.', 'error');
        return;
    }

    if (sedangMengirim) {
        e.preventDefault();
        return;
    }
    sedangMengirim = true;

    if (btnDesktop) {
        btnDesktop.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengirim...';
        btnDesktop.style.pointerEvents = 'none';
        btnDesktop.style.opacity = '0.7';
    }
    if (btnMobile) {
        btnMobile.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengirim...';
        btnMobile.style.pointerEvents = 'none';
        btnMobile.style.opacity = '0.7';
    }
});
</script>
</body>
</html>
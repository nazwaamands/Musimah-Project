<?php
ob_start();
session_start();
include 'koneksi.php';

if (!isset($_SESSION['user'])) { ob_end_clean(); header("Location: login.php"); exit; }

$id_pesanan = (int)($_GET['id'] ?? 0);
if ($id_pesanan === 0) { ob_end_clean(); header("Location: index.php"); exit; }

$stmt = mysqli_prepare($conn,
    "SELECT p.*, u.username FROM pesanan p JOIN users u ON u.id = p.id_user WHERE p.id_pesanan = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "i", $id_pesanan);
mysqli_stmt_execute($stmt);
$pesanan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// FIX: ambil id_user dari database berdasarkan username di session,
// karena $_SESSION['id_user'] / $_SESSION['id'] tidak pernah di-set di login.php
$uname_esc = mysqli_real_escape_string($conn, $_SESSION['user']);
$cek_u  = mysqli_query($conn, "SELECT id FROM users WHERE username = '$uname_esc'");
$data_u = mysqli_fetch_assoc($cek_u);
$id_user_session = (int)($data_u['id'] ?? 0);

if (!$pesanan || (int)$pesanan['id_user'] !== $id_user_session) { ob_end_clean(); header("Location: index.php"); exit; }

$stmt_d = mysqli_prepare($conn, "SELECT * FROM detail_pesanan WHERE id_pesanan = ? ORDER BY id_detail ASC");
mysqli_stmt_bind_param($stmt_d, "i", $id_pesanan);
mysqli_stmt_execute($stmt_d);
$result_d = mysqli_stmt_get_result($stmt_d);
$details = [];
while ($row = mysqli_fetch_assoc($result_d)) $details[] = $row;
mysqli_stmt_close($stmt_d);

$nama_user = $_SESSION['user'] ?? 'Pelanggan';
$no_invoice = 'MB-' . date('Y') . '-' . str_pad($id_pesanan, 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= $no_invoice ?> — Musimah Bakery</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playball&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy:    #0f172a;
            --navy2:   #1e3a8a;
            --gold:    #f59e0b;
            --text:    #1e293b;
            --muted:   #64748b;
            --border:  #e2e8f0;
            --bg:      #f8fafc;
            --white:   #ffffff;
            --success: #16a34a;
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); }

        /* ── SCREEN ONLY ── */
        .screen-only { display:block; }

        /* ─── HEADER SCREEN ─── */
        .top-bar {
            background:var(--navy); padding:0 5%; height:60px;
            display:flex; justify-content:space-between; align-items:center;
            position:sticky; top:0; z-index:10;
        }
        .top-bar .logo { font-family:'Playball',cursive; color:#fff; font-size:1.3rem; text-decoration:none; }
        .top-bar .user { display:flex; align-items:center; gap:8px; color:#94a3b8; font-size:13px; }
        .top-bar .user a { color:#94a3b8; text-decoration:none; margin-left:8px; padding-left:8px; border-left:1px solid rgba(255,255,255,0.15); }
        .top-bar .user a:hover { color:#ef4444; }

        /* ─── SUCCESS STRIP ─── */
        .success-strip {
            background:linear-gradient(90deg, #052e16, #0f172a);
            border-bottom:1px solid rgba(34,197,94,0.2);
            padding:20px 5%;
            display:flex; align-items:center; gap:14px;
        }
        .success-strip i { font-size:22px; color:#4ade80; }
        .success-strip h2 { font-size:15px; font-weight:700; color:#fff; }
        .success-strip p  { font-size:12px; color:rgba(255,255,255,0.5); margin-top:2px; }
        .action-buttons { margin-left:auto; display:flex; gap:10px; }
        .btn-print {
            display:inline-flex; align-items:center; gap:7px;
            padding:9px 18px; background:var(--navy2); color:#fff;
            border:none; border-radius:8px; font-size:13px; font-weight:600;
            cursor:pointer; font-family:'Inter',sans-serif; text-decoration:none; transition:0.2s;
        }
        .btn-print:hover { background:#1d4ed8; }
        .btn-back {
            display:inline-flex; align-items:center; gap:7px;
            padding:9px 18px; background:transparent; color:#94a3b8;
            border:1px solid rgba(255,255,255,0.15); border-radius:8px;
            font-size:13px; font-weight:500; text-decoration:none; transition:0.2s;
        }
        .btn-back:hover { color:#fff; border-color:rgba(255,255,255,0.35); }

        /* ─── WRAPPER ─── */
        .wrapper { max-width:720px; margin:30px auto; padding:0 20px 60px; }

        /* ═══════════════════════════════════
           INVOICE CARD — tampilan layar & PDF
        ═══════════════════════════════════ */
        .invoice {
            background:var(--white);
            border:1px solid var(--border);
            border-radius:16px;
            overflow:hidden;
            box-shadow:0 4px 30px rgba(0,0,0,0.08);
        }

        /* ── TOP BANNER ── */
        .inv-banner {
            background:linear-gradient(135deg, var(--navy) 0%, var(--navy2) 100%);
            padding:32px 36px;
            display:flex; justify-content:space-between; align-items:flex-start;
        }
        .inv-brand .name {
            font-family:'Playball',cursive; font-size:1.8rem; color:#fff;
            line-height:1; margin-bottom:5px;
        }
        .inv-brand .tagline { font-size:11px; color:rgba(255,255,255,0.45); letter-spacing:2px; text-transform:uppercase; }
        .inv-brand .addr    { font-size:11px; color:rgba(255,255,255,0.4); margin-top:8px; line-height:1.7; }

        .inv-meta { text-align:right; }
        .inv-meta .word { font-size:11px; color:rgba(255,255,255,0.4); letter-spacing:3px; text-transform:uppercase; margin-bottom:8px; }
        .inv-meta .num  { font-size:1.3rem; font-weight:800; color:var(--gold); letter-spacing:1px; }
        .inv-meta .date { font-size:11px; color:rgba(255,255,255,0.45); margin-top:6px; }
        .inv-meta .status-pill {
            display:inline-flex; align-items:center; gap:5px;
            margin-top:8px; padding:4px 12px; border-radius:20px;
            font-size:11px; font-weight:700;
            background:rgba(245,158,11,0.2); color:var(--gold); border:1px solid rgba(245,158,11,0.3);
        }

        /* ── GOLD STRIP ── */
        .gold-strip { height:3px; background:linear-gradient(90deg, var(--gold), #fbbf24, var(--gold)); }

        /* ── BODY ── */
        .inv-body { padding:30px 36px; }

        /* Billed to + metode */
        .inv-top-row { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:28px; gap:20px; }
        .billed-to .sect-label { font-size:9px; font-weight:700; color:var(--muted); letter-spacing:2px; text-transform:uppercase; margin-bottom:8px; }
        .billed-to .cust-name  { font-size:15px; font-weight:700; color:var(--navy); }
        .billed-to .cust-info  { font-size:12px; color:var(--muted); margin-top:3px; }

        .pay-to { text-align:right; }
        .pay-to .sect-label { font-size:9px; font-weight:700; color:var(--muted); letter-spacing:2px; text-transform:uppercase; margin-bottom:8px; }
        .pay-box {
            display:inline-flex; align-items:center; gap:10px;
            background:#f8fafc; border:1px solid var(--border);
            border-radius:10px; padding:10px 14px;
        }
        .pay-box .pay-icon { width:34px; height:34px; background:var(--navy); border-radius:8px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:0.85rem; flex-shrink:0; }
        .pay-box .pay-icon.purple { background:#6d28d9; }
        .pay-box .bank { font-weight:700; font-size:12px; color:var(--navy); }
        .pay-box .acc  { font-size:13px; font-weight:800; letter-spacing:1.5px; color:var(--text); margin:1px 0; }
        .pay-box .holder { font-size:10px; color:var(--muted); }

        /* Tabel */
        .inv-table { width:100%; border-collapse:collapse; margin-bottom:20px; }
        .inv-table thead tr { background:var(--navy); }
        .inv-table thead th {
            padding:10px 14px; text-align:left; font-size:10px;
            text-transform:uppercase; letter-spacing:1px; color:rgba(255,255,255,0.7); font-weight:700;
        }
        .inv-table thead th:last-child { text-align:right; }
        .inv-table thead th:nth-child(2),
        .inv-table thead th:nth-child(3) { text-align:center; }

        .inv-table tbody tr { border-bottom:1px solid #f1f5f9; }
        .inv-table tbody tr:last-child { border-bottom:none; }
        .inv-table tbody tr:nth-child(even) { background:#f8fafc; }
        .inv-table tbody td { padding:11px 14px; font-size:13px; vertical-align:middle; }
        .inv-table tbody td:nth-child(2) { text-align:center; }
        .inv-table tbody td:nth-child(3) { text-align:center; color:var(--muted); }
        .inv-table tbody td:last-child  { text-align:right; font-weight:700; color:var(--navy2); }

        /* Summary */
        .inv-summary { display:flex; justify-content:flex-end; }
        .summary-box { width:260px; }
        .sum-row { display:flex; justify-content:space-between; padding:6px 0; font-size:12.5px; border-bottom:1px solid #f1f5f9; }
        .sum-row .lbl { color:var(--muted); }
        .sum-row .val { font-weight:600; }
        .sum-total {
            display:flex; justify-content:space-between; align-items:center;
            margin-top:10px; padding:12px 16px;
            background:var(--navy); border-radius:10px;
        }
        .sum-total .lbl { font-size:12px; color:rgba(255,255,255,0.65); font-weight:600; }
        .sum-total .val { font-size:1.1rem; font-weight:800; color:var(--gold); }

        /* Footer invoice */
        .inv-footer {
            margin-top:28px; padding-top:20px;
            border-top:1px dashed var(--border);
            display:flex; justify-content:space-between; align-items:flex-end;
        }
        .inv-footer .note { font-size:11px; color:var(--muted); line-height:1.8; max-width:320px; }
        .inv-footer .note strong { color:var(--text); }
        .inv-footer .thanks { text-align:right; }
        .inv-footer .thanks .ty { font-family:'Playball',cursive; font-size:1.1rem; color:var(--navy); }
        .inv-footer .thanks .sub { font-size:10px; color:var(--muted); margin-top:2px; }

        /* ── BUKTI (screen only) ── */
        .bukti-section {
            margin-top:16px; background:var(--white);
            border:1px solid var(--border); border-radius:12px;
            overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.05);
        }
        .bukti-head { background:var(--navy); padding:12px 20px; display:flex; align-items:center; gap:8px; }
        .bukti-head span { font-size:13px; font-weight:600; color:#fff; }
        .bukti-body { padding:16px 20px; }
        .bukti-img { max-width:100%; border-radius:8px; border:1px solid var(--border); }

        /* ── ACTION ROW (screen only) ── */
        .action-row { display:flex; gap:10px; flex-wrap:wrap; margin-top:16px; }
        .btn-primary {
            display:inline-flex; align-items:center; gap:8px;
            padding:11px 22px; background:var(--navy); color:#fff;
            border:none; border-radius:9px; font-size:13px; font-weight:600;
            cursor:pointer; font-family:'Inter',sans-serif; text-decoration:none; transition:0.2s;
        }
        .btn-primary:hover { background:#000; }
        .btn-secondary {
            display:inline-flex; align-items:center; gap:8px;
            padding:11px 22px; background:var(--white); color:var(--muted);
            border:1.5px solid var(--border); border-radius:9px;
            font-size:13px; font-weight:500; text-decoration:none; cursor:pointer;
            font-family:'Inter',sans-serif; transition:0.2s;
        }
        .btn-secondary:hover { border-color:var(--navy); color:var(--navy); }

        /* ══════════════
           PRINT / PDF
        ══════════════ */
        @media print {
            * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
            body { background:#fff; margin:0; padding:0; }
            .top-bar, .success-strip, .bukti-section, .action-row { display:none !important; }
            .wrapper { max-width:100%; margin:0; padding:0; }
            .invoice { border:none; border-radius:0; box-shadow:none; }
            .inv-banner { padding:28px 32px; }
            .inv-body   { padding:24px 32px; }

            /* Paksa warna background tercetak */
            .inv-banner { background:linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%) !important; }
            .inv-table thead tr { background:#0f172a !important; }
            .sum-total { background:#0f172a !important; }
            .gold-strip { background:linear-gradient(90deg, #f59e0b, #fbbf24, #f59e0b) !important; }
            .inv-table tbody tr:nth-child(even) { background:#f8fafc !important; }
            .pay-box { background:#f8fafc !important; }
        }
    </style>
</head>
<body>

<!-- TOP BAR (screen only) -->
<div class="top-bar screen-only">
    <a href="index.php" class="logo">Musimah Bakery</a>
    <div class="user">
        <i class="fa-solid fa-circle-user" style="color:#93c5fd;"></i>
        <span><?= htmlspecialchars($nama_user) ?></span>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
</div>

<!-- SUCCESS STRIP (screen only) -->
<div class="success-strip screen-only">
    <i class="fa-solid fa-circle-check"></i>
    <div>
        <h2>Pesanan <?= $no_invoice ?> berhasil dibuat!</h2>
        <p>Selesaikan pembayaran sesuai instruksi di bawah.</p>
    </div>
    <div class="action-buttons">
        <button onclick="window.print()" class="btn-print"><i class="fa-solid fa-file-pdf"></i> Simpan PDF</button>
        <a href="index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Belanja Lagi</a>
    </div>
</div>

<div class="wrapper">

    <!-- ═══ INVOICE ═══ -->
    <div class="invoice">

        <!-- Banner atas -->
        <div class="inv-banner">
            <div class="inv-brand">
                <div class="name">Musimah Bakery</div>
                <div class="tagline">Premium Homemade Bakery</div>
                <div class="addr">
                    Jl. Kp. Rawasapi, Mustikasari<br>
                    Bekasi, Jawa Barat 17157<br>
                    +62 822-3599-8521
                </div>
            </div>
            <div class="inv-meta">
                <div class="word">Invoice</div>
                <div class="num"><?= $no_invoice ?></div>
                <div class="date"><?= date('d F Y', strtotime($pesanan['tanggal_pesan'])) ?></div>
                <div>
                    <span class="status-pill">
                        <i class="fa-solid fa-clock" style="font-size:9px;"></i>
                        <?= htmlspecialchars($pesanan['status_pesanan']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Gold line -->
        <div class="gold-strip"></div>

        <!-- Body -->
        <div class="inv-body">

            <!-- Billed to + rekening -->
            <div class="inv-top-row">
                <div class="billed-to">
                    <div class="sect-label">Billed To</div>
                    <div class="cust-name"><?= htmlspecialchars($pesanan['username']) ?></div>
                    <div class="cust-info">Tanggal: <?= date('d M Y, H:i', strtotime($pesanan['tanggal_pesan'])) ?> WIB</div>
                </div>
                <div class="pay-to">
                    <div class="sect-label">Bayar ke</div>
                    <?php if ($pesanan['metode_pembayaran'] === 'EWALLET'): ?>
                    <div class="pay-box">
                        <div class="pay-icon purple"><i class="fa-solid fa-wallet"></i></div>
                        <div>
                            <div class="bank">Gopay / Dana</div>
                            <div class="acc">0822-3599-8521</div>
                            <div class="holder">a.n. Nazwa Amanda Syifa</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="pay-box">
                        <div class="pay-icon"><i class="fa-solid fa-building-columns"></i></div>
                        <div>
                            <div class="bank">Bank Mandiri</div>
                            <div class="acc">1660 0037 3832 5</div>
                            <div class="holder">a.n. Nazwa Amanda Syifa</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabel produk -->
            <table class="inv-table">
                <thead>
                    <tr>
                        <th style="width:45%;">Produk</th>
                        <th style="width:10%;">Qty</th>
                        <th style="width:20%;">Harga Satuan</th>
                        <th style="width:25%;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($details as $i => $d): ?>
                    <tr>
                        <td style="font-weight:600;"><?= htmlspecialchars($d['nama_produk']) ?></td>
                        <td><?= (int)$d['jumlah'] ?></td>
                        <td>Rp <?= number_format((int)$d['harga'],0,',','.') ?></td>
                        <td>Rp <?= number_format((int)$d['subtotal'],0,',','.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Ringkasan total -->
            <div class="inv-summary">
                <div class="summary-box">
                    <div class="sum-row">
                        <span class="lbl">Subtotal</span>
                        <span class="val">Rp <?= number_format((int)$pesanan['total_bayar'],0,',','.') ?></span>
                    </div>
                    <div class="sum-row">
                        <span class="lbl">Ongkos Kirim</span>
                        <span class="val" style="color:var(--success);">Gratis</span>
                    </div>
                    <div class="sum-total">
                        <span class="lbl">Total Bayar</span>
                        <span class="val">Rp <?= number_format((int)$pesanan['total_bayar'],0,',','.') ?></span>
                    </div>
                </div>
            </div>

            <!-- Footer invoice -->
            <div class="inv-footer">
                <div class="note">
                    <strong>Catatan:</strong><br>
                    Pembayaran harap dilakukan dalam <strong>1×24 jam</strong>.<br>
                    Pesanan otomatis dibatalkan jika melewati batas waktu.
                </div>
                <div class="thanks">
                    <div class="ty">Musimah Bakery</div>
                    <div class="sub">Terima kasih atas kepercayaan Anda ♥</div>
                </div>
            </div>

        </div><!-- inv-body -->
    </div><!-- invoice -->

    <!-- BUKTI (screen only) -->
    <?php if (!empty($pesanan['bukti_pembayaran'])): ?>
    <div class="bukti-section screen-only">
        <div class="bukti-head">
            <i class="fa-solid fa-image" style="color:#93c5fd;font-size:13px;"></i>
            <span>Bukti Pembayaran</span>
        </div>
        <div class="bukti-body">
            <?php
                $ext_b = strtolower(pathinfo($pesanan['bukti_pembayaran'], PATHINFO_EXTENSION));
                $url_b = 'uploads/bukti/' . htmlspecialchars($pesanan['bukti_pembayaran']);
            ?>
            <?php if ($ext_b === 'pdf'): ?>
                <a href="<?= $url_b ?>" target="_blank" class="btn-secondary" style="display:inline-flex;">
                    <i class="fa-solid fa-file-pdf"></i> Lihat PDF
                </a>
            <?php else: ?>
                <img src="<?= $url_b ?>" alt="Bukti Pembayaran" class="bukti-img">
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ACTION ROW (screen only) -->
    <div class="action-row screen-only">
        <a href="index.php" class="btn-primary"><i class="fa-solid fa-store"></i> Lanjut Belanja</a>
        <button onclick="window.print()" class="btn-secondary"><i class="fa-solid fa-print"></i> Cetak / PDF</button>
        <a href="produk.php" class="btn-secondary"><i class="fa-solid fa-cake-candles"></i> Lihat Menu</a>
    </div>

</div><!-- wrapper -->
</body>
</html>
<?php
/**
 * chatbot.php — Muslimah Bakery
 * Handler AJAX untuk chatbot widget.
 * Dipanggil via fetch() POST dari index.php
 * dengan field: chat_message
 */

include 'koneksi.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: text/html; charset=UTF-8');

// Hanya terima POST dengan field chat_message
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['chat_message'])) {
    http_response_code(400);
    echo 'Request tidak valid.';
    exit;
}

// FIX: rate-limiting sederhana berbasis session — maksimal 1 request per 1 detik per user,
// supaya endpoint ini tidak bisa dibanjiri request beruntun yang membebani database.
$now = microtime(true);
if (isset($_SESSION['chat_last_request']) && ($now - $_SESSION['chat_last_request']) < 1.0) {
    http_response_code(429);
    echo 'Terlalu banyak permintaan. Mohon tunggu sebentar.';
    exit;
}
$_SESSION['chat_last_request'] = $now;

$userMessage = strtolower(trim($_POST['chat_message']));
$response    = '';

// ════════════════════════════════════════
// HELPER FUNCTIONS
// ════════════════════════════════════════

/** Ambil semua kategori yang stoknya masih ada */
function getChatKategori($conn) {
    $res = mysqli_query($conn, "SELECT DISTINCT kategori FROM produk WHERE stok > 0 AND kategori IS NOT NULL AND kategori != '' ORDER BY kategori");
    $cats = [];
    // FIX: cek hasil query sebelum di-loop, supaya tidak fatal error kalau query gagal
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) $cats[] = htmlspecialchars($r['kategori']);
    }
    return $cats;
}

/** Ambil produk berdasarkan kategori tertentu */
function getChatProdukByKategori($conn, $kat) {
    $kat_esc = mysqli_real_escape_string($conn, $kat);
    $res     = mysqli_query($conn, "SELECT nama_produk, harga FROM produk WHERE stok > 0 AND LOWER(kategori) = '" . strtolower($kat_esc) . "' ORDER BY nama_produk LIMIT 8");
    $rows    = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    }
    return $rows;
}

/** Ambil semua produk tersedia (max 8) */
function getChatAllProduk($conn) {
    $res  = mysqli_query($conn, "SELECT nama_produk, harga, kategori FROM produk WHERE stok > 0 ORDER BY nama_produk LIMIT 8");
    $rows = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    }
    return $rows;
}

/**
 * Ambil id_user dari tabel users berdasarkan username yang sedang login.
 * Mengembalikan null kalau tidak ditemukan / belum login.
 */
function getChatIdUser($conn) {
    if (!isset($_SESSION['user'])) return null;
    $uname_esc = mysqli_real_escape_string($conn, $_SESSION['user']);
    $res  = mysqli_query($conn, "SELECT id FROM users WHERE username = '$uname_esc' LIMIT 1");
    if (!$res) return null;
    $data = mysqli_fetch_assoc($res);
    return $data ? (int)$data['id'] : null;
}

/**
 * Ambil SEMUA pesanan milik user tertentu (tanpa batas jumlah), lengkap
 * dengan status_pesanan APA ADANYA dari database (Pending/Diproses/
 * Dikirim/Selesai/Dibatalkan/dsb — sesuai yang diset admin), sama seperti
 * yang ditampilkan di nota.php.
 */
function getChatRiwayatPesanan($conn, $id_user) {
    $id_user = (int)$id_user;
    $res     = mysqli_query(
        $conn,
        "SELECT id_pesanan, tanggal_pesan, total_bayar, status_pesanan
         FROM pesanan
         WHERE id_user = $id_user
         ORDER BY id_pesanan DESC"
    );
    $rows = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    }
    return $rows;
}

/** Format status jadi badge warna, mengikuti gaya status di nota.php/admin */
function formatChatStatusBadge($status) {
    $status = trim((string)$status);
    if ($status === '') $status = 'Pending';

    $warna = [
        'pending'    => '#f59e0b', // kuning/oranye — menunggu konfirmasi admin
        'diproses'   => '#3b82f6', // biru — sedang diproses admin
        'dikirim'    => '#8b5cf6', // ungu — dalam pengiriman
        'selesai'    => '#16a34a', // hijau — selesai
        'dibatalkan' => '#dc2626', // merah — dibatalkan
        'ditolak'    => '#dc2626',
    ];
    $key   = strtolower($status);
    $warna_hex = $warna[$key] ?? '#94a3b8';

    return "<span style='color:$warna_hex;font-weight:700;'>" . htmlspecialchars($status) . "</span>";
}

// ════════════════════════════════════════
// DETEKSI KATA KUNCI
// ════════════════════════════════════════

$kata_kategori = strpos($userMessage, 'kategori')   !== false
              || strpos($userMessage, 'jenis')       !== false
              || strpos($userMessage, 'tipe')        !== false;

$kata_menu     = strpos($userMessage, 'menu')        !== false
              || strpos($userMessage, 'kue')         !== false
              || strpos($userMessage, 'roti')        !== false
              || strpos($userMessage, 'produk')      !== false
              || strpos($userMessage, 'daftar')      !== false;

$kata_bayar    = strpos($userMessage, 'bayar')       !== false
              || strpos($userMessage, 'pembayaran')  !== false
              || strpos($userMessage, 'transfer')    !== false
              || strpos($userMessage, 'payment')     !== false
              || strpos($userMessage, 'rekening')    !== false
              || strpos($userMessage, 'ewallet')     !== false
              || strpos($userMessage, 'e-wallet')    !== false
              || strpos($userMessage, 'dompet')      !== false
              || strpos($userMessage, 'gopay')       !== false
              || strpos($userMessage, 'dana')        !== false
              || strpos($userMessage, 'bca')         !== false
              || strpos($userMessage, 'mandiri')     !== false;

$kata_stok     = strpos($userMessage, 'stok')        !== false
              || strpos($userMessage, 'tersedia')    !== false
              || strpos($userMessage, 'masih ada')   !== false;

// ── Kata kunci nilai gizi (kalori, protein, lemak, karbohidrat) ──
// Bisa dikombinasikan dengan arah "rendah" atau "tinggi".
$kata_kalori      = strpos($userMessage, 'kalori')      !== false
                 || strpos($userMessage, 'diet')        !== false
                 || strpos($userMessage, 'sehat')       !== false
                 || strpos($userMessage, 'light')       !== false;

$kata_protein     = strpos($userMessage, 'protein')     !== false;

$kata_lemak       = strpos($userMessage, 'lemak')       !== false
                 || strpos($userMessage, 'fat')         !== false;

$kata_karbohidrat = strpos($userMessage, 'karbohidrat') !== false
                 || strpos($userMessage, 'karbo')       !== false;

$kata_gizi        = $kata_kalori || $kata_protein || $kata_lemak || $kata_karbohidrat;

// Arah pengurutan: default "rendah" kecuali kata "tinggi/banyak/kaya" disebut.
// Untuk kalori, kata seperti 'diet'/'sehat'/'light' juga otomatis diartikan "rendah".
$kata_tinggi = strpos($userMessage, 'tinggi') !== false
            || strpos($userMessage, 'banyak') !== false
            || strpos($userMessage, 'kaya')   !== false;
$kata_rendah = strpos($userMessage, 'rendah') !== false
            || strpos($userMessage, 'sedikit') !== false
            || strpos($userMessage, 'minim')   !== false;

// Kata kunci status/riwayat pesanan — dicek SEBELUM kata 'pesan' generik,
// karena kata 'pesanan' mengandung substring 'pesan' dan akan ketangkap
// duluan oleh blok "Pesan / order" kalau urutannya salah.
$kata_status   = strpos($userMessage, 'status pesanan')   !== false
              || strpos($userMessage, 'status')            !== false
              || strpos($userMessage, 'riwayat pesanan')   !== false
              || strpos($userMessage, 'riwayat')            !== false
              || strpos($userMessage, 'cek pesanan')        !== false
              || strpos($userMessage, 'pesanan saya')       !== false
              || strpos($userMessage, 'lacak pesanan')      !== false
              || strpos($userMessage, 'progress pesanan')   !== false
              || strpos($userMessage, 'progres pesanan')    !== false
              || strpos($userMessage, 'pesanan saya sampai mana') !== false;

// ════════════════════════════════════════
// LOGIC RESPON
// ════════════════════════════════════════

// ── Salam ──
if (strpos($userMessage, 'halo')  !== false
 || strpos($userMessage, 'hai')   !== false
 || strpos($userMessage, 'hi')    !== false
 || strpos($userMessage, 'hei')   !== false) {

    $response = "Halo! Selamat datang di <b>Musimah Bakery</b><br><br>"
              . "Saya bisa bantu info:<br>"
              . "- <b>menu</b> — daftar produk<br>"
              . "- <b>kategori</b> — jenis kue<br>"
              . "- <b>stok</b> — ketersediaan produk<br>"
              . "- <b>kalori/protein/lemak/karbohidrat rendah/tinggi</b> — cari kue sesuai gizi<br>"
              . "- <b>pembayaran</b> — cara bayar<br>"
              . "- <b>status pesanan</b> — cek status pesanan Anda<br>"
              . "- <b>pengiriman</b> — info ongkir<br>"
              . "- <b>lokasi</b> — alamat toko<br>"
              . "- <b>jam buka</b> — jam operasional";

// ── Kategori saja ──
} elseif ($kata_kategori && !$kata_menu) {
    $cats = getChatKategori($conn);
    if (empty($cats)) {
        $response = "Saat ini kami belum memiliki kategori terdaftar.<br>Coba ketik <b>menu</b> untuk melihat semua produk.";
    } else {
        $response = "<b>Kategori produk kami:</b><br><br>";
        foreach ($cats as $c) {
            $response .= "- <b>$c</b><br>";
        }
        $response .= "<br>Ketik nama kategori (contoh: <i>dessert</i>) untuk melihat produknya.";
    }

// ── Kategori + menu sekaligus ──
} elseif ($kata_kategori && $kata_menu) {
    $cats = getChatKategori($conn);
    if (empty($cats)) {
        $response = "Belum ada produk terdaftar saat ini.";
    } else {
        $response = "<b>Semua kategori dan produk:</b><br><br>";
        foreach ($cats as $c) {
            $prods = getChatProdukByKategori($conn, $c);
            $response .= "<b>$c</b><br>";
            foreach ($prods as $p) {
                $response .= "&nbsp;&nbsp;- " . htmlspecialchars($p['nama_produk'])
                           . " — Rp " . number_format($p['harga'], 0, ',', '.') . "<br>";
            }
            $response .= "<br>";
        }
    }

// ── Menu / produk ──
} elseif ($kata_menu) {
    $cats        = getChatKategori($conn);
    $matched_cat = null;
    foreach ($cats as $c) {
        if (strpos($userMessage, strtolower($c)) !== false) { $matched_cat = $c; break; }
    }

    if ($matched_cat) {
        $prods = getChatProdukByKategori($conn, $matched_cat);
        if (empty($prods)) {
            $response = "Tidak ada produk tersedia di kategori <b>" . htmlspecialchars($matched_cat) . "</b> saat ini.";
        } else {
            $response = "<b>Produk kategori " . htmlspecialchars($matched_cat) . ":</b><br><br>";
            foreach ($prods as $p) {
                $response .= "- <b>" . htmlspecialchars($p['nama_produk']) . "</b>"
                           . " — Rp " . number_format($p['harga'], 0, ',', '.') . "<br>";
            }
            $response .= "<br>Ketik <b>pesan [nama kue]</b> untuk memesan.";
        }
    } else {
        $prods = getChatAllProduk($conn);
        if (empty($prods)) {
            $response = "Maaf, belum ada produk tersedia saat ini.";
        } else {
            $response = "<b>Menu tersedia:</b><br><br>";
            foreach ($prods as $p) {
                $kat = $p['kategori']
                    ? " <span style='font-size:0.78em;color:#c9a96e;'>(" . htmlspecialchars($p['kategori']) . ")</span>"
                    : "";
                $response .= "- <b>" . htmlspecialchars($p['nama_produk']) . "</b>$kat"
                           . " — Rp " . number_format($p['harga'], 0, ',', '.') . "<br>";
            }
            $response .= "<br>Ketik <b>kategori</b> untuk melihat jenis kue, atau <b>pesan [nama kue]</b> untuk memesan.";
        }
    }

// ── Pembayaran ──
} elseif ($kata_bayar) {
    $response = "<b>Metode Pembayaran</b><br><br>"
              . "<b>Transfer Bank</b><br>"
              . "- BCA &nbsp;&nbsp;&nbsp;&nbsp;: <b>1234567890</b><br>"
              . "- Mandiri : <b>0987654321</b><br>"
              . "A/N: <i>Musimah Bakery</i><br><br>"
              . "<b>E-Wallet</b><br>"
              . "- GoPay : <b>0822-3599-8521</b><br>"
              . "- DANA &nbsp;: <b>0822-3599-8521</b><br><br>"
              . "Setelah transfer, unggah bukti pembayaran langsung di halaman <b>checkout</b> pada saat pemesanan.";

// ── Lokasi ──
} elseif (strpos($userMessage, 'lokasi')  !== false
       || strpos($userMessage, 'alamat')  !== false
       || strpos($userMessage, 'dimana')  !== false) {
    $response = "Kami berlokasi di:<br>"
              . "<b>Jl. Kampung Rawasapi, Mustikasari,<br>Bekasi, Jawa Barat 17157</b><br><br>"
              . "Kami tunggu kedatangan Anda!";

// ── Jam buka ──
} elseif (strpos($userMessage, 'jam buka')    !== false
       || strpos($userMessage, 'buka')        !== false
       || strpos($userMessage, 'operasional') !== false) {
    $response = "<b>Jam Operasional:</b><br>"
              . "Senin - Sabtu: <b>08.00 - 20.00</b><br>"
              . "Minggu: <b>09.00 - 17.00</b> WIB";

// ── Status / riwayat pesanan (BARU) ──
// Mengambil status pesanan LANGSUNG dari database (tabel pesanan), persis
// sama dengan status_pesanan yang ditampilkan di nota.php dan yang diubah
// oleh admin lewat halaman kelola pesanan (Pending / Diproses / Dikirim /
// Selesai / Dibatalkan, dsb).
} elseif ($kata_status) {
    if (!isset($_SESSION['user'])) {
        $response = "Untuk melihat status pesanan, silakan <b>login</b> terlebih dahulu ke akun Anda di website kami.";
    } else {
        $id_user = getChatIdUser($conn);

        if ($id_user === null) {
            $response = "Maaf, akun Anda tidak ditemukan. Silakan login ulang.";
        } else {
            $rows = getChatRiwayatPesanan($conn, $id_user);

            if (empty($rows)) {
                $response = "Anda belum memiliki riwayat pesanan.<br><br>"
                          . "Yuk mulai belanja! Ketik <b>menu</b> untuk lihat produk kami.";
            } else {
                $response = "<b>Status Pesanan Anda</b>:<br><br>";
                foreach ($rows as $r) {
                    $id_pesanan = (int)$r['id_pesanan'];
                    $no_invoice = 'MB-' . date('Y', strtotime($r['tanggal_pesan']))
                                . '-' . str_pad((string)$id_pesanan, 4, '0', STR_PAD_LEFT);
                    $tanggal   = date('d M Y', strtotime($r['tanggal_pesan']));
                    $badge     = formatChatStatusBadge($r['status_pesanan'] ?? 'Pending');

                    $response .= "<b>$no_invoice</b> &nbsp;<span style='opacity:0.6;font-size:0.85em;'>($tanggal)</span><br>"
                               . "Total: Rp " . number_format($r['total_bayar'], 0, ',', '.') . "<br>"
                               . "Status: $badge<br>"
                               . "<a href='nota.php?id=$id_pesanan' target='_blank' style='color:#c9a96e;font-size:0.82em;'>Lihat Invoice &rarr;</a><br><br>";
                }
                $response .= "Ketik <b>status pesanan</b> lagi kapan saja untuk cek pembaruan status.";
            }
        }
    }

// ── Stok produk ──
} elseif ($kata_stok && !$kata_menu && !$kata_kategori) {
    $res = mysqli_query($conn, "SELECT nama_produk, stok FROM produk ORDER BY nama_produk");
    // FIX: cek hasil query sebelum loop
    if (!$res) {
        $response = "Maaf, terjadi kendala saat mengambil data stok. Silakan coba lagi.";
    } else {
        $response = "<b>Ketersediaan Stok Produk:</b><br><br>";
        while ($r = mysqli_fetch_assoc($res)) {
            $status = $r['stok'] > 0
                ? "Tersedia (" . $r['stok'] . ")"
                : "Habis";
            $response .= "- <b>" . htmlspecialchars($r['nama_produk']) . "</b>: " . $status . "<br>";
        }
    }

// ── Info gizi: kalori / protein / lemak / karbohidrat (rendah atau tinggi) ──
} elseif ($kata_gizi) {
    // Tentukan kolom yang dicek — prioritas: protein > lemak > karbohidrat > kalori,
    // supaya kalau user sebut lebih dari satu (jarang terjadi), yang paling spesifik menang.
    if ($kata_protein) {
        $kolom = 'protein'; $label = 'Protein'; $unit = 'g';
    } elseif ($kata_lemak) {
        $kolom = 'lemak'; $label = 'Lemak'; $unit = 'g';
    } elseif ($kata_karbohidrat) {
        $kolom = 'karbohidrat'; $label = 'Karbohidrat'; $unit = 'g';
    } else {
        $kolom = 'kalori'; $label = 'Kalori'; $unit = 'kcal';
    }

    // Arah: kalau user sebut "tinggi/banyak/kaya" -> urutkan dari yang paling tinggi.
    // Kalau tidak, default rendah (juga berlaku untuk kata 'diet'/'sehat'/'light' di kalori).
    $urutan     = $kata_tinggi ? 'DESC' : 'ASC';
    $arah_label = $kata_tinggi ? 'Tertinggi' : 'Terendah';

    $res  = mysqli_query($conn, "SELECT nama_produk, harga, $kolom FROM produk WHERE stok > 0 AND $kolom IS NOT NULL ORDER BY $kolom $urutan LIMIT 5");
    $rows = [];
    // FIX: cek hasil query sebelum loop
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    }

    if (empty($rows)) {
        $response = "Maaf, info " . strtolower($label) . " produk belum tersedia saat ini.";
    } else {
        $response = "<b>Produk dengan $label $arah_label:</b><br><br>";
        foreach ($rows as $r) {
            $response .= "- <b>" . htmlspecialchars($r['nama_produk']) . "</b>"
                       . " — " . $r[$kolom] . " $unit"
                       . " — Rp " . number_format($r['harga'], 0, ',', '.') . "<br>";
        }
        if ($kolom === 'kalori' && !$kata_tinggi) {
            $response .= "<br>Cocok untuk Anda yang sedang menjaga pola makan.";
        } elseif ($kolom === 'protein' && $kata_tinggi) {
            $response .= "<br>Cocok untuk Anda yang butuh asupan protein lebih.";
        }
    }

// ── Pesan / order ──
} elseif (strpos($userMessage, 'pesan')       !== false
       || strpos($userMessage, 'memesan')     !== false
       || strpos($userMessage, 'beli')        !== false
       || strpos($userMessage, 'ingin')       !== false
       || strpos($userMessage, 'mau')         !== false
       || strpos($userMessage, 'order')       !== false
       || strpos($userMessage, 'bisa pesan')  !== false
       || strpos($userMessage, 'cara pesan')  !== false
       || strpos($userMessage, 'gimana cara') !== false
       || strpos($userMessage, 'bagaimana')   !== false
       || strpos($userMessage, 'checkout')    !== false
       || strpos($userMessage, 'bisa beli')   !== false
       || strpos($userMessage, 'mau beli')    !== false
       || strpos($userMessage, 'mau pesan')   !== false
       || strpos($userMessage, 'pengen')      !== false
       || strpos($userMessage, 'pingin')      !== false
       || strpos($userMessage, 'butuh')       !== false
       || strpos($userMessage, 'perlu')       !== false) {

    $item = trim(str_ireplace([
        'ingin memesan', 'mau memesan', 'pengen pesan', 'pingin pesan',
        'bisa pesan', 'cara pesan', 'gimana cara', 'bagaimana cara',
        'bisa beli', 'mau beli', 'mau pesan', 'pesan', 'memesan',
        'ingin', 'mau', 'beli', 'order', 'checkout', 'pengen',
        'pingin', 'butuh', 'perlu'
    ], '', $userMessage));

    if (empty($item)) {
        $response = "Tentu! Berikut cara memesan di <b>Musimah Bakery</b>:<br><br>"
                  . "1. Pilih produk di halaman <b>Menu</b><br>"
                  . "2. Klik <b>Tambah ke Keranjang</b><br>"
                  . "3. Buka <b>Keranjang</b> dan lanjutkan checkout<br>"
                  . "4. Isi alamat, pilih metode pembayaran, lalu unggah bukti bayar langsung di halaman checkout<br>"
                  . "5. Tunggu konfirmasi admin — ketik <b>status pesanan</b> di chat ini kapan saja untuk cek progresnya<br><br>"
                  . "Atau ketik <b>pesan [nama kue]</b> untuk cek stok langsung.<br>"
                  . "Contoh: <i>pesan Croissant</i>";
    } else {
        $item_esc = mysqli_real_escape_string($conn, strtolower($item));
        $cek      = mysqli_query($conn, "SELECT nama_produk, harga, stok FROM produk WHERE LOWER(nama_produk) LIKE '%$item_esc%' LIMIT 1");
        // FIX: cek hasil query sebelum fetch
        $prod = $cek ? mysqli_fetch_assoc($cek) : null;

        if ($prod && $prod['stok'] > 0) {
            $response = "<b>" . htmlspecialchars($prod['nama_produk']) . "</b> tersedia.<br>"
                      . "Harga: <b>Rp " . number_format($prod['harga'], 0, ',', '.') . "</b><br><br>"
                      . "Silakan tambahkan ke keranjang dan selesaikan pembayaran langsung di website kami.<br><br>"
                      . "Ketik <b>pembayaran</b> untuk info cara bayar.";
        } elseif ($prod && $prod['stok'] <= 0) {
            $response = "<b>" . htmlspecialchars($prod['nama_produk']) . "</b> sedang habis stok.<br>"
                      . "Ketik <b>menu</b> untuk melihat produk lain yang tersedia.";
        } else {
            $response = "Kami tidak menemukan produk <b>" . htmlspecialchars(ucwords($item)) . "</b>.<br>"
                      . "Ketik <b>menu</b> untuk melihat semua produk kami.";
        }
    }

// ── WhatsApp / kontak ──
} elseif (strpos($userMessage, 'wa')       !== false
       || strpos($userMessage, 'whatsapp') !== false
       || strpos($userMessage, 'kontak')   !== false
       || strpos($userMessage, 'hubungi')  !== false
       || strpos($userMessage, 'cs')       !== false) {
    $response = "Hubungi kami:<br>"
              . "<b>WhatsApp: +62 822-3599-8521</b><br><br>"
              . "Jam respons: Senin - Sabtu 08.00 - 20.00 WIB";

// ── Terima kasih ──
} elseif (strpos($userMessage, 'terima kasih') !== false
       || strpos($userMessage, 'makasih')      !== false
       || strpos($userMessage, 'thanks')        !== false) {
    $response = "Sama-sama! Senang bisa membantu.<br>Jangan ragu bertanya ya!";

// ── Pengiriman / ongkir ──
} elseif (strpos($userMessage, 'ongkir')     !== false
       || strpos($userMessage, 'pengiriman') !== false
       || strpos($userMessage, 'delivery')   !== false
       || strpos($userMessage, 'antar')      !== false) {
    $response = "<b>Info Pengiriman:</b><br>"
              . "Kami melayani pengiriman gratis ongkir.<br>"
              . "Area pengiriman: Bekasi dan sekitarnya.<br><br>"
              . "Pesanan akan langsung diantar ke alamat Anda.";

// ── Fallback: cek nama kategori langsung ──
} else {
    $cats        = getChatKategori($conn);
    $matched_cat = null;
    foreach ($cats as $c) {
        if (strpos($userMessage, strtolower($c)) !== false) { $matched_cat = $c; break; }
    }

    if ($matched_cat) {
        $prods    = getChatProdukByKategori($conn, $matched_cat);
        $response = "<b>Produk kategori " . htmlspecialchars($matched_cat) . ":</b><br><br>";
        foreach ($prods as $p) {
            $response .= "- <b>" . htmlspecialchars($p['nama_produk']) . "</b>"
                       . " — Rp " . number_format($p['harga'], 0, ',', '.') . "<br>";
        }
        $response .= "<br>Ketik <b>pesan [nama kue]</b> untuk memesan.";
    } else {
        $response = "Maaf, saya belum mengerti.<br><br>Coba ketik:<br>"
                  . "- <b>menu</b> — produk tersedia<br>"
                  . "- <b>kategori</b> — jenis kue<br>"
                  . "- <b>stok</b> — ketersediaan produk<br>"
                  . "- <b>kalori/protein/lemak/karbohidrat rendah/tinggi</b> — cari kue sesuai gizi<br>"
                  . "- <b>pembayaran</b> — cara bayar<br>"
                  . "- <b>status pesanan</b> — cek status pesanan Anda<br>"
                  . "- <b>lokasi</b> — alamat toko<br>"
                  . "- <b>jam buka</b> — jam operasional";
    }
}

echo $response;
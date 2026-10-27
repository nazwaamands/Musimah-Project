<?php
/**
 * auto_expired.php
 *
 * Auto-batalkan pesanan yang masih 'Pending' lebih dari 24 jam (pembayaran
 * tidak dilakukan tepat waktu). Dipanggil di awal halaman (admin maupun user)
 * supaya status selalu up-to-date saat halaman dibuka/refresh.
 *
 * Status yang dipakai adalah 'Dibatalkan', sesuai enum kolom status_pesanan:
 * 'Pending','Diproses','Dikirim','Selesai','Dibatalkan'.
 */
if (!function_exists('auto_expire_pesanan')) {
function auto_expire_pesanan($conn) {
    // ── Throttle: cek maks 1x per 60 detik, supaya tidak query berat tiap request ──
    $flag_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'muslimah_bakery_expire.txt';
    $interval  = 60; // detik
    if (file_exists($flag_file) && (time() - filemtime($flag_file)) < $interval) {
        return; // belum waktunya, skip
    }
    // Perbarui timestamp flag
    file_put_contents($flag_file, date('Y-m-d H:i:s'));

    // ── Ambil pesanan Pending yang sudah > 24 jam ──
    $batas = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $res = mysqli_query($conn,
        "SELECT id_pesanan FROM pesanan
         WHERE status_pesanan = 'Pending'
           AND tanggal_pesan < '$batas'"
    );
    if (!$res || mysqli_num_rows($res) === 0) return;

    // FIX: cek struktur tabel SEKALI SAJA di luar loop, bukan diulang per pesanan
    $kolom    = mysqli_query($conn, "SHOW COLUMNS FROM detail_pesanan LIKE 'id_produk'");
    $pakai_id = $kolom && mysqli_num_rows($kolom) > 0;

    while ($row = mysqli_fetch_assoc($res)) {
        $id_pesanan = (int)$row['id_pesanan'];

        // FIX: bungkus rollback stok + update status dalam satu transaction
        // supaya kalau ada query yang gagal di tengah, semuanya dibatalkan (rollback),
        // bukan malah tanggung (stok sudah nambah tapi status belum berubah, dsb).
        mysqli_begin_transaction($conn);
        $sukses = true;

        if ($pakai_id) {
            // ✅ Cara akurat: rollback stok via id_produk
            $detail = mysqli_query($conn,
                "SELECT id_produk, jumlah FROM detail_pesanan
                 WHERE id_pesanan = '$id_pesanan' AND id_produk IS NOT NULL"
            );
            if ($detail) {
                while ($d = mysqli_fetch_assoc($detail)) {
                    $id_produk = (int)$d['id_produk'];
                    $jumlah    = (int)$d['jumlah'];
                    if ($id_produk > 0 && $jumlah > 0) {
                        $ok = mysqli_query($conn,
                            "UPDATE produk
                             SET stok = stok + $jumlah
                             WHERE id_produk = '$id_produk'"
                        );
                        if (!$ok) $sukses = false;
                    }
                }
            } else {
                $sukses = false;
            }
        } else {
            // Fallback: rollback stok via nama_produk
            $detail = mysqli_query($conn,
                "SELECT nama_produk, jumlah FROM detail_pesanan
                 WHERE id_pesanan = '$id_pesanan'"
            );
            if ($detail) {
                while ($d = mysqli_fetch_assoc($detail)) {
                    $nama   = mysqli_real_escape_string($conn, $d['nama_produk']);
                    $jumlah = (int)$d['jumlah'];
                    if ($jumlah > 0) {
                        $ok = mysqli_query($conn,
                            "UPDATE produk
                             SET stok = stok + $jumlah
                             WHERE nama_produk = '$nama'"
                        );
                        if (!$ok) $sukses = false;
                    }
                }
            } else {
                $sukses = false;
            }
        }

        // ── Update status pesanan → Dibatalkan (otomatis, tanpa alasan) ──
        if ($sukses) {
            $ok_status = mysqli_query($conn,
                "UPDATE pesanan
                 SET status_pesanan = 'Dibatalkan'
                 WHERE id_pesanan = '$id_pesanan'"
            );
            if (!$ok_status) $sukses = false;
        }

        if ($sukses) {
            mysqli_commit($conn);
        } else {
            mysqli_rollback($conn);
            // FIX: catat kegagalan supaya ketahuan, tidak diam-diam hilang
            error_log("auto_expire_pesanan: gagal memproses pesanan #$id_pesanan, transaksi di-rollback.");
        }
    }
}
}
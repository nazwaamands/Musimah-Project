<?php
session_start();
include 'koneksi.php';

if (isset($_SESSION['user']) && ($_SESSION['role'] ?? '') === 'admin') {
    header("Location: admin.php");
    exit;
}

$message = "";

// FIX: pembatasan percobaan login gagal khusus admin — lebih ketat dari login user biasa
// karena akun admin punya akses penuh ke sistem.
$MAKS_PERCOBAAN = 3;
$WAKTU_BLOKIR   = 120; // detik

if (!isset($_SESSION['login_admin_attempts'])) $_SESSION['login_admin_attempts'] = 0;
if (!isset($_SESSION['login_admin_blocked_until'])) $_SESSION['login_admin_blocked_until'] = 0;

$sedang_diblokir = time() < $_SESSION['login_admin_blocked_until'];

if (isset($_POST['login_admin']) && !$sedang_diblokir) {
    $email    = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    $login_sukses = false;

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $login_sukses = true;

            // FIX: regenerasi session ID setelah login admin berhasil untuk mencegah session fixation
            session_regenerate_id(true);

            $_SESSION['user']    = $row['username'];
            $_SESSION['role']    = $row['role'];
            $_SESSION['id_user'] = (int)$row['id'];
            $_SESSION['user_id'] = (int)$row['id'];

            // Reset counter percobaan gagal setelah berhasil login
            unset($_SESSION['login_admin_attempts'], $_SESSION['login_admin_blocked_until']);

            header("Location: admin.php");
            exit;
        }
    }

    if (!$login_sukses) {
        // FIX: pesan generik — tidak membedakan "email bukan admin" vs "password salah",
        // supaya tidak bisa dipakai untuk mencari tahu email mana yang punya akses admin
        $message = "Email atau password administrator salah.";

        $_SESSION['login_admin_attempts']++;
        if ($_SESSION['login_admin_attempts'] >= $MAKS_PERCOBAAN) {
            $_SESSION['login_admin_blocked_until'] = time() + $WAKTU_BLOKIR;
            $_SESSION['login_admin_attempts'] = 0;
        }
    }
    $stmt->close();
} elseif (isset($_POST['login_admin']) && $sedang_diblokir) {
    $sisa_detik = $_SESSION['login_admin_blocked_until'] - time();
    $message = "Terlalu banyak percobaan gagal. Silakan coba lagi dalam {$sisa_detik} detik.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Musimah</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            /* Perubahan Palet Warna ke Formal Blue */
            --primary-color: #1e3a8a; /* Navy Blue */
            --primary-hover: #172554;
            --accent-color: #3b82f6;  /* Royal Blue */
            --bg-color: #f8fafc;      /* Light Grayish Blue */
            --white: #ffffff;
            --text-muted: #64748b;
            --error-bg: #fef2f2;
            --error-text: #991b1b;
            --input-border: #e2e8f0;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-color);
            /* Background pattern halus warna biru */
            background-image: radial-gradient(var(--accent-color) 0.5px, transparent 0.5px);
            background-size: 30px 30px;
            background-opacity: 0.1;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-card {
            background: var(--white);
            width: 100%;
            max-width: 400px;
            padding: 50px 35px;
            border-radius: 24px;
            box-shadow: 0 20px 25px -5px rgba(30, 58, 138, 0.1), 0 10px 10px -5px rgba(30, 58, 138, 0.04);
            text-align: center;
            transition: var(--transition);
        }

        .icon-box {
            width: 65px;
            height: 65px;
            background: var(--primary-color);
            color: var(--white);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin: 0 auto 25px;
            box-shadow: 0 10px 15px -3px rgba(30, 58, 138, 0.3);
        }

        .header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .header p {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 35px;
            line-height: 1.5;
        }

        .alert {
            background: var(--error-bg);
            color: var(--error-text);
            border: 1px solid rgba(153, 27, 27, 0.2);
            padding: 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 25px;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            text-align: left;
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--primary-color);
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: 1px;
            padding-left: 5px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 16px;
            transition: var(--transition);
        }

        .form-group input {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border-radius: 12px;
            border: 2px solid var(--input-border);
            background: #fcfdfe;
            font-size: 15px;
            font-family: inherit;
            color: var(--primary-color);
            transition: var(--transition);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent-color);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .form-group input:focus + i {
            color: var(--accent-color);
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: var(--primary-color);
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 58, 138, 0.2);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .back-link {
            margin-top: 35px;
            padding-top: 25px;
            border-top: 1.5px solid #f1f5f9;
        }

        .back-link a {
            color: var(--accent-color);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .back-link a:hover {
            color: var(--primary-color);
            gap: 12px;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 40px 25px;
                border-radius: 20px;
            }
            .header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="icon-box">
            <i class="fa-solid fa-shield-halved"></i>
        </div>

        <div class="header">
            <h1>Admin Panel</h1>
            <p>Sistem Manajemen Internal<br>Toko Musimah</p>
        </div>

        <?php if ($message !== ""): ?>
            <div class="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label for="email">ID Email Administrator</label>
                <div class="input-wrapper">
                    <input type="email" id="email" name="email" placeholder="admin@gmail.com" required>
                    <i class="fa-solid fa-envelope"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Kata Sandi</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                    <i class="fa-solid fa-lock"></i>
                </div>
            </div>

            <button type="submit" name="login_admin" class="btn-login">
                Autentikasi Masuk <i class="fa-solid fa-right-to-bracket"></i>
            </button>
        </form>

        <div class="back-link">
            <a href="login.php">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Halaman Utama
            </a>
        </div>
    </div>

</body>
</html>
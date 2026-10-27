<?php
session_start();
include 'koneksi.php';

if (isset($_SESSION['user'])) {
    if (($_SESSION['role'] ?? '') == 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$message = "";

// FIX: pembatasan sederhana percobaan login gagal berbasis session,
// supaya tidak bisa brute-force password tanpa henti.
$MAKS_PERCOBAAN = 5;
$WAKTU_BLOKIR   = 60; // detik

if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['login_blocked_until'])) $_SESSION['login_blocked_until'] = 0;

$sedang_diblokir = time() < $_SESSION['login_blocked_until'];

if (isset($_POST['login']) && !$sedang_diblokir) {
    $email    = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    $login_sukses = false;

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $login_sukses = true;

            // FIX: regenerasi session ID setelah login berhasil untuk mencegah session fixation
            session_regenerate_id(true);

            $_SESSION['user']    = $row['username'];
            $_SESSION['role']    = $row['role'];
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['id_user'] = $row['id'];

            // Reset counter percobaan gagal setelah berhasil login
            unset($_SESSION['login_attempts'], $_SESSION['login_blocked_until']);

            if ($row['role'] == 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: index.php");
            }
            exit;
        }
    }

    if (!$login_sukses) {
        // FIX: pesan generik, tidak membedakan "email tidak terdaftar" vs "password salah"
        // supaya tidak bisa dipakai untuk menebak email mana yang terdaftar (user enumeration)
        $message = "Email atau password salah.";

        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= $MAKS_PERCOBAAN) {
            $_SESSION['login_blocked_until'] = time() + $WAKTU_BLOKIR;
            $_SESSION['login_attempts'] = 0;
        }
    }
    $stmt->close();
} elseif (isset($_POST['login']) && $sedang_diblokir) {
    $sisa_detik = $_SESSION['login_blocked_until'] - time();
    $message = "Terlalu banyak percobaan gagal. Silakan coba lagi dalam {$sisa_detik} detik.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login — Musimah Bakery</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            /* Palette Biru Formal */
            --primary: #1e3a8a;       /* Navy Blue */
            --primary-hover: #1e40af; /* Blue 800 */
            --accent: #3b82f6;        /* Royal Blue */
            --dark: #0f172a;          /* Slate Dark */
            --bg: #f8fafc;            /* Light Slate */
            --border: #e2e8f0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg);
            /* Pola titik halus warna biru */
            background-image: radial-gradient(var(--primary) 0.5px, transparent 0.5px);
            background-size: 24px 24px;
            background-opacity: 0.05;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            background: #ffffff;
            width: 100%;
            max-width: 400px;
            padding: 45px 35px;
            border-radius: 24px;
            box-shadow: 0 20px 25px -5px rgba(30, 58, 138, 0.1), 0 10px 10px -5px rgba(30, 58, 138, 0.04);
            position: relative;
            text-align: center;
        }

        .logo-area h1 {
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .logo-area p {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 30px;
        }

        .alert {
            font-size: 13px;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fee2e2;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #dcfce7;
        }

        .form-group {
            text-align: left;
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
            margin-left: 2px;
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .form-group input {
            width: 100%;
            padding: 14px 18px;
            border-radius: 12px;
            border: 1.5px solid var(--border);
            background: #ffffff;
            font-family: inherit;
            font-size: 15px;
            transition: all 0.3s ease;
            color: var(--dark);
        }

        .form-group input[name="password"] {
            padding-right: 50px;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .toggle-password {
            position: absolute;
            right: 18px;
            cursor: pointer;
            color: #94a3b8;
            font-size: 18px;
            transition: 0.2s;
        }

        .toggle-password:hover {
            color: var(--accent);
        }

        .forgot-password {
            text-align: right;
            margin-top: 8px;
        }

        .forgot-password a {
            color: var(--accent);
            font-size: 12px;
            text-decoration: none;
            font-weight: 600;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .btn-main {
            width: 100%;
            padding: 16px;
            background: var(--primary);
            color: #FFF;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: 0 4px 6px -1px rgba(30, 58, 138, 0.2);
        }

        .btn-main:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(30, 58, 138, 0.3);
        }

        .footer-links {
            margin-top: 25px;
            font-size: 14px;
            color: #64748b;
        }

        .footer-links a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }

        .admin-separator {
            margin: 35px 0 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .admin-separator span {
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .admin-separator p {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #94a3b8;
            letter-spacing: 1px;
        }

        .btn-admin {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #FFF;
            border: 1.5px solid var(--border);
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-admin:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: var(--primary);
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="logo-area">
            <h1>Musimah</h1>
            <p>Silakan masuk ke akun Anda</p>
        </div>

        <?php if (isset($_SESSION['info_success'])): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <?= htmlspecialchars($_SESSION['info_success']); ?>
            </div>
            <?php unset($_SESSION['info_success']); ?>
        <?php endif; ?>

        <?php if ($message !== ""): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label>Alamat Email</label>
                <input type="email" name="email" placeholder="contoh@email.com" required>
            </div>
            <div class="form-group">
                <label>Kata Sandi</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="passwordInput" placeholder="••••••••" required>
                    <i class="fa-solid fa-eye toggle-password" id="eyeIcon"></i>
                </div>
            </div>
            <button type="submit" name="login" class="btn-main">Masuk Sekarang</button>
        </form>

        <div class="footer-links">
            Belum memiliki akun? <a href="register.php">Daftar Akun</a>
        </div>

        <div class="admin-separator">
            <span></span>
            <p>Portal Internal</p>
            <span></span>
        </div>

        <a href="login_admin.php" class="btn-admin">
            <i class="fa-solid fa-user-tie"></i> Area Administrator
        </a>
    </div>

    <script>
        const eyeIcon = document.getElementById('eyeIcon');
        const passwordInput = document.getElementById('passwordInput');

        eyeIcon.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>

</body>
</html>
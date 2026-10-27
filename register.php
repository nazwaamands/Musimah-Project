<?php
session_start();
include 'koneksi.php';

$message = "";

if (isset($_POST['register'])) {
    // FIX: trim input supaya tidak ada duplikat "semu" karena spasi
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password_raw = $_POST['password'] ?? '';

    // FIX: validasi panjang password di server, bukan cuma placeholder
    if (strlen($password_raw) < 8) {
        $message = "Password minimal 8 karakter.";
    } elseif ($username === '' || $email === '') {
        $message = "Username dan email tidak boleh kosong.";
    } else {
        $password = password_hash($password_raw, PASSWORD_DEFAULT);
        $role = 'user';

        // FIX: cek keunikan EMAIL sekaligus USERNAME. Ini penting karena
        // banyak halaman lain (checkout.php, nota.php, chatbot.php) mencari
        // data user lewat "WHERE username = ...", bukan email/ID — kalau
        // username bisa duplikat, pesanan/riwayat bisa salah nyasar ke akun lain.
        $checkUser = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        if (!$checkUser) {
            $message = "Terjadi kesalahan server. Silakan coba lagi.";
        } else {
            $checkUser->bind_param("ss", $email, $username);
            $checkUser->execute();
            $result = $checkUser->get_result();

            if ($result->num_rows > 0) {
                $message = "Email atau username sudah terdaftar!";
            } else {
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                if (!$stmt) {
                    $message = "Terjadi kesalahan server. Silakan coba lagi.";
                } else {
                    $stmt->bind_param("ssss", $username, $email, $password, $role);

                    if ($stmt->execute()) {
                        $_SESSION['info_success'] = "Berhasil Daftar! Silakan Login.";
                        header("Location: login.php");
                        exit();
                    } else {
                        $message = "Registrasi Gagal.";
                    }
                    $stmt->close();
                }
            }
            $checkUser->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Daftar — Akun</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Warna Formal (Navy & Slate) */
            --primary: #1E293B;      /* Navy Dark */
            --accent: #2563EB;       /* Blue Link */
            --dark: #0F172A;         /* Deep Black-Blue */
            --bg: #F8FAFC;           /* Light Grey Background */
            --border: #E2E8F0;       /* Border Color */
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg);
            /* Pola titik halus untuk kesan premium */
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 24px 24px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .reg-card {
            background: #ffffff;
            width: 100%;
            max-width: 400px;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.05);
            border: 1px solid var(--border);
        }

        .header { text-align: center; margin-bottom: 25px; }
        .header h1 {
            font-weight: 700;
            font-size: 24px;
            color: var(--dark);
            letter-spacing: -0.5px;
        }
        .header p { font-size: 14px; color: #64748B; margin-top: 5px; }

        .alert {
            font-size: 13px;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            background: #FEF2F2; 
            color: #B91C1C; 
            border: 1px solid #FEE2E2;
        }

        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--primary);
            text-transform: uppercase;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border-radius: 10px;
            border: 1.5px solid var(--border);
            background: #FFFFFF;
            font-size: 14px;
            font-family: inherit;
            transition: 0.3s;
            color: var(--dark);
        }
        .form-group input:focus { 
            outline: none; 
            border-color: var(--accent); 
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn-reg {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: #FFF;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }
        .btn-reg:hover {
            background: #334155; /* Slate yang sedikit lebih terang */
        }

        .footer-link { 
            text-align: center; 
            margin-top: 25px; 
            font-size: 14px; 
            color: #64748B; 
        }
        .footer-link a { 
            color: var(--accent); 
            text-decoration: none; 
            font-weight: 600; 
        }
        .footer-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 350px) {
            .reg-card { padding: 25px 20px; }
        }
    </style>
</head>
<body>

    <div class="reg-card">
        <div class="header">
            <h1>Daftar Akun</h1>
            <p>Silakan lengkapi data diri Anda</p>
        </div>

        <?php if ($message !== ""): ?>
            <div class="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Nama Lengkap" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="email@contoh.com" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Min. 8 Karakter" minlength="8" required>
            </div>
            <button type="submit" name="register" class="btn-reg">Daftar Sekarang</button>
        </form>

        <div class="footer-link">
            Sudah punya akun? <a href="login.php">Masuk di sini</a>
        </div>
    </div>

</body>
</html>
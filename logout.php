<?php
session_start();
session_unset();

// FIX: hapus cookie session secara eksplisit di browser (best practice tambahan,
// walaupun session_destroy() sudah menghapus data session di server)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout — Musimah Bakery</title>
    <meta http-equiv="refresh" content="3;url=login.php">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy:        #1B2A4A;
            --navy-deep:   #111D33;
            --navy-mid:    #243558;
            --gold:        #C9A96E;
            --gold-light:  #E8C99A;
            --cream:       #FAF7F2;
            --white:       #ffffff;
            --text-muted:  #8896AE;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            background: var(--navy-deep);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'DM Sans', sans-serif;
            overflow: hidden;
        }

        /* Ambient background blobs */
        body::before,
        body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.12;
            pointer-events: none;
        }
        body::before {
            width: 500px; height: 500px;
            background: var(--gold);
            top: -100px; right: -100px;
        }
        body::after {
            width: 400px; height: 400px;
            background: #3A5A9B;
            bottom: -100px; left: -80px;
        }

        .card {
            position: relative;
            width: 100%;
            max-width: 400px;
            background: var(--navy-mid);
            border-radius: 24px;
            border: 1px solid rgba(201, 169, 110, 0.15);
            box-shadow:
                0 30px 60px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255,255,255,0.05);
            padding: 50px 40px 44px;
            text-align: center;
            animation: fadeUp 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Gold divider top accent */
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 50%;
            transform: translateX(-50%);
            width: 60px; height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            border-radius: 0 0 2px 2px;
        }

        .icon-wrap {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(201, 169, 110, 0.08);
            border: 1.5px solid rgba(201, 169, 110, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 28px;
            color: var(--gold);
            font-size: 26px;
            animation: popIn 0.5s 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        @keyframes popIn {
            from { opacity: 0; transform: scale(0.6); }
            to   { opacity: 1; transform: scale(1); }
        }

        .brand {
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 10px;
            opacity: 0.8;
            animation: fadeIn 0.4s 0.4s ease both;
        }

        .title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 32px;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 14px;
            line-height: 1.1;
            animation: fadeIn 0.4s 0.45s ease both;
        }

        .subtitle {
            font-size: 14px;
            font-weight: 300;
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: 6px;
            animation: fadeIn 0.4s 0.5s ease both;
        }

        .thanks {
            font-size: 13px;
            color: rgba(136, 150, 174, 0.7);
            line-height: 1.7;
            margin-bottom: 36px;
            animation: fadeIn 0.4s 0.55s ease both;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .btn-back {
            display: block;
            padding: 14px 30px;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--navy-deep);
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            letter-spacing: 0.3px;
            transition: all 0.25s ease;
            margin-bottom: 20px;
            animation: fadeIn 0.4s 0.6s ease both;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(201, 169, 110, 0.35);
            filter: brightness(1.05);
        }

        .btn-back:active {
            transform: translateY(0);
        }

        /* Countdown bar */
        .progress-wrap {
            animation: fadeIn 0.4s 0.65s ease both;
        }

        .progress-bar {
            height: 2px;
            background: rgba(255,255,255,0.06);
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-fill {
            height: 100%;
            width: 100%;
            background: linear-gradient(90deg, var(--gold), var(--gold-light));
            border-radius: 2px;
            animation: shrink 3s linear forwards;
            transform-origin: left;
        }

        @keyframes shrink {
            from { transform: scaleX(1); }
            to   { transform: scaleX(0); }
        }

        .redirect-note {
            font-size: 11px;
            color: rgba(136, 150, 174, 0.5);
            letter-spacing: 0.4px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrap">
            <i class="fa-solid fa-door-open"></i>
        </div>
        <p class="brand">Musimah Bakery</p>
        <h2 class="title">Sampai jumpa!</h2>
        <p class="subtitle">Anda telah berhasil keluar dari akun.</p>
        <p class="thanks">Terima kasih telah berkunjung.<br>Kami tunggu kedatangan Anda kembali</p>
        <a href="login.php" class="btn-back">Login Kembali</a>
        <div class="progress-wrap">
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
            <p class="redirect-note">Mengalihkan ke login dalam 3 detik...</p>
        </div>
    </div>
</body>
</html>
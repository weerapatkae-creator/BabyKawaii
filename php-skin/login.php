<?php
require_once __DIR__ . '/config/database.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['admin_id']   = $user['id'];
            $_SESSION['admin_name'] = $user['full_name'] ?: $user['username'];
            $_SESSION['admin_role'] = $user['role'];

            // Update last login
            $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            header('Location: ' . SITE_URL . '/dashboard.php');
            exit;
        } else {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    } else {
        $error = 'กรุณากรอกข้อมูลให้ครบ';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - BabyKawaii Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
    <!-- Fredoka One for logo font match -->
    <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #FBE9EE 0%, #F0EAF6 50%, #E2F1ED 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            max-width: 440px;
            width: 100%;
            margin: 0 auto;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(138,109,176,0.22);
            overflow: hidden;
        }
        /* ── Logo header ── */
        .login-header {
            background: #fff;
            padding: 36px 40px 24px;
            text-align: center;
            border-bottom: 2px solid #FFE8F0;
            position: relative;
        }
        .login-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(160deg, #FFF5F8 0%, #F8F0FF 60%, #F0FFF8 100%);
            z-index: 0;
        }
        .login-header > * { position: relative; z-index: 1; }
        .login-logo {
            max-width: 250px;
            width: 88%;
            display: block;
            margin: 0 auto 6px;
            /* subtle lift */
            filter: drop-shadow(0 4px 10px rgba(200,130,130,0.18));
            animation: floatUp 3s ease-in-out infinite;
        }
        .login-tagline {
            font-size: 0.78rem;
            color: #C8A0B8;
            letter-spacing: 0.5px;
            margin: 0;
        }
        /* ── Form body ── */
        .login-body { background: #fff; padding: 32px; }
        /* ── Float animation ── */
        @keyframes floatUp {
            0%,100% { transform: translateY(0);   }
            50%      { transform: translateY(-5px); }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="login-card">
        <div class="login-header">
            <img src="<?= SITE_URL ?>/assets/images/logo.svg"
                 alt="BabyKawaii Shop"
                 class="login-logo">
            <p class="login-tagline">ระบบจัดการร้านค้าเสื้อผ้าเด็ก</p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                <span class="me-2">⚠️</span> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">👤 ชื่อผู้ใช้</label>
                    <input type="text" name="username" class="form-control form-control-lg"
                           placeholder="admin" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label">🔒 รหัสผ่าน</label>
                    <div class="input-group">
                        <input type="password" name="password" id="passwordInput" class="form-control form-control-lg" placeholder="••••••••" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePw()">👁️</button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 btn-lg">
                    🌸 เข้าสู่ระบบ
                </button>
            </form>

        </div>
    </div>
</div>
<script>
function togglePw() {
    const input = document.getElementById('passwordInput');
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>

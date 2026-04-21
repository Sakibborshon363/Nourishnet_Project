<?php

require_once 'includes/db.php';
require_once 'includes/auth.php';


if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Email ও Password দিন।';
    } else {
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            loginUser($user); // Session set করো
            header('Location: index.php');
            exit;
        } else {
            $error = 'Email বা Password ভুল।';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NourishNet — Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'DM Sans',sans-serif;background:linear-gradient(135deg,#e8f5ee 0%,#f2faf5 100%);min-height:100vh;display:flex;align-items:center;justify-content:center}
  .login-box{background:#fff;border-radius:16px;padding:40px;width:400px;max-width:95vw;box-shadow:0 8px 32px rgba(26,107,58,.12)}
  .logo{font-family:'DM Serif Display',serif;font-size:26px;color:#1a6b3a;text-align:center;margin-bottom:6px}
  .tagline{text-align:center;font-size:13px;color:#888;margin-bottom:28px}
  .form-group{margin-bottom:16px}
  label{display:block;font-size:12px;font-weight:600;color:#666;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px}
  input{width:100%;padding:10px 14px;border-radius:8px;border:1px solid #e0e0e0;font-size:14px;font-family:'DM Sans',sans-serif;outline:none;transition:.15s}
  input:focus{border-color:#2e8b57;box-shadow:0 0 0 3px rgba(46,139,87,.1)}
  .btn{width:100%;padding:12px;background:#2e8b57;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:.15s;margin-top:4px}
  .btn:hover{background:#1a6b3a}
  .error{background:#fdecea;color:#c0392b;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;border:.5px solid #e8b3ad}
  .demo-box{margin-top:20px;padding:14px;background:#f2faf5;border-radius:8px;font-size:12px;color:#4a4a4a;border:.5px solid #b8dfc9}
  .demo-box strong{color:#1a6b3a;display:block;margin-bottom:6px}
  .register-link{text-align:center;margin-top:16px;font-size:13px;color:#888}
  .register-link a{color:#2e8b57;text-decoration:none;font-weight:600}
</style>
</head>
<body>
<div class="login-box">
  <div class="logo">🌿 NourishNet</div>
  <div class="tagline">Connecting Surplus with Need</div>

  <?php if ($error): ?>
    <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- login form — POST method, CSRF সুরক্ষার জন্য পরে token যোগ করুন -->
  <form method="POST" action="login.php">
    <div class="form-group">
      <label for="email">Email Address</label>
      <input type="email" id="email" name="email" placeholder="you@example.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn">Login →</button>
  </form>

  <div class="demo-box">
    <strong>🧪 Demo Credentials (Test করুন)</strong>
    <div>Donor: <code>donor@test.com</code> / <code>password123</code></div>
    <div>Volunteer: <code>volunteer@test.com</code> / <code>password123</code></div>
    <div>Shelter: <code>shelter@test.com</code> / <code>password123</code></div>
  </div>

  <div class="register-link">
    নতুন account নেই? <a href="register.php">Register করুন</a>
  </div>
</div>
</body>
</html>

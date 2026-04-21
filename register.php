<?php

require_once 'includes/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) { header('Location: index.php'); exit; }

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email']     ?? '');
    $password = trim($_POST['password']  ?? '');
    $role     = $_POST['role']           ?? '';
    $phone    = trim($_POST['phone']     ?? '');
    $address  = trim($_POST['address']   ?? '');
    $org_name = trim($_POST['org_name']  ?? '');

    $allowed_roles = ['donor', 'volunteer', 'shelter'];

    if (empty($name) || empty($email) || empty($password) || !in_array($role, $allowed_roles)) {
        $error = 'সব required field পূরণ করুন।';
    } elseif (strlen($password) < 6) {
        $error = 'Password কমপক্ষে ৬ অক্ষরের হতে হবে।';
    } else {
        // Email duplicate check
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'এই email-এ ইতিমধ্যে account আছে।';
        } else {
            // Password securely hash করো
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (full_name, email, password_hash, role, phone, address, org_name)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$name, $email, $hash, $role, $phone, $address, $org_name]);
            $success = 'Account তৈরি হয়েছে! এখন login করুন।';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>NourishNet — Register</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'DM Sans',sans-serif;background:linear-gradient(135deg,#e8f5ee,#f2faf5);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .box{background:#fff;border-radius:16px;padding:36px;width:480px;max-width:100%;box-shadow:0 8px 32px rgba(26,107,58,.12)}
  .logo{font-family:'DM Serif Display',serif;font-size:24px;color:#1a6b3a;text-align:center;margin-bottom:20px}
  .form-group{margin-bottom:14px}
  label{display:block;font-size:12px;font-weight:600;color:#666;text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px}
  input,select,textarea{width:100%;padding:9px 12px;border-radius:8px;border:1px solid #e0e0e0;font-size:14px;font-family:'DM Sans',sans-serif;outline:none;transition:.15s}
  input:focus,select:focus{border-color:#2e8b57;box-shadow:0 0 0 3px rgba(46,139,87,.1)}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .btn{width:100%;padding:11px;background:#2e8b57;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;margin-top:6px}
  .btn:hover{background:#1a6b3a}
  .error{background:#fdecea;color:#c0392b;padding:10px;border-radius:8px;font-size:13px;margin-bottom:14px}
  .success{background:#e8f5ee;color:#1a6b3a;padding:10px;border-radius:8px;font-size:13px;margin-bottom:14px}
  .back{text-align:center;margin-top:14px;font-size:13px;color:#888}
  .back a{color:#2e8b57;text-decoration:none;font-weight:600}
</style>
</head>
<body>
<div class="box">
  <div class="logo">🌿 NourishNet — Register</div>

  <?php if ($error): ?><div class="error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success">✅ <?= htmlspecialchars($success) ?> <a href="login.php">Login করুন</a></div><?php endif; ?>

  <form method="POST">
    <div class="row">
      <div class="form-group">
        <label>পূর্ণ নাম *</label>
        <input type="text" name="full_name" placeholder="আপনার নাম" required>
      </div>
      <div class="form-group">
        <label>Role *</label>
        <select name="role" required>
          <option value="">— বেছে নিন —</option>
          <option value="donor">Donor (দাতা)</option>
          <option value="volunteer">Volunteer (স্বেচ্ছাসেবী)</option>
          <option value="shelter">Shelter (সংস্থা)</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label>Email *</label>
      <input type="email" name="email" placeholder="email@example.com" required>
    </div>
    <div class="form-group">
      <label>Password *</label>
      <input type="password" name="password" placeholder="কমপক্ষে ৬ অক্ষর" required>
    </div>
    <div class="row">
      <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" placeholder="01XXXXXXXXX">
      </div>
      <div class="form-group">
        <label>Organization Name</label>
        <input type="text" name="org_name" placeholder="ব্যবসা / সংস্থার নাম">
      </div>
    </div>
    <div class="form-group">
      <label>Address</label>
      <input type="text" name="address" placeholder="এলাকা, শহর">
    </div>
    <button type="submit" class="btn">Account তৈরি করুন →</button>
  </form>
  <div class="back">ইতিমধ্যে account আছে? <a href="login.php">Login করুন</a></div>
</div>
</body>
</html>

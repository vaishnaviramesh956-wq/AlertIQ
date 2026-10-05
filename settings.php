<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

function db(): ?PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=alertiq;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $e) { $pdo = false; }
    }
    return $pdo ?: null;
}
$pdo = db();
$userId = (int) $_SESSION['user_id'];
$notice = '';
$error = '';

/* Make sure a preferences table exists (safe to run every time) */
if ($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_settings (
            user_id INT PRIMARY KEY,
            notify_email TINYINT(1) NOT NULL DEFAULT 1,
            notify_whatsapp TINYINT(1) NOT NULL DEFAULT 1,
            notify_telegram TINYINT(1) NOT NULL DEFAULT 1
        )");
    } catch (PDOException $e) {}
}

/* --- Handle form submissions --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $error = 'Name cannot be empty.';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?');
                $stmt->execute([$name, $userId]);
                $notice = 'Profile updated.';
            } catch (PDOException $e) {
                $error = 'Could not update profile — does the `users` table have a `name` column?';
            }
        }
    }

    if ($action === 'preferences') {
        $notifyEmail = isset($_POST['notify_email']) ? 1 : 0;
        $notifyWhatsapp = isset($_POST['notify_whatsapp']) ? 1 : 0;
        $notifyTelegram = isset($_POST['notify_telegram']) ? 1 : 0;
        try {
            $stmt = $pdo->prepare('REPLACE INTO user_settings (user_id, notify_email, notify_whatsapp, notify_telegram) VALUES (?, ?, ?, ?)');
            $stmt->execute([$userId, $notifyEmail, $notifyWhatsapp, $notifyTelegram]);
            $notice = 'Preferences saved.';
        } catch (PDOException $e) {
            $error = 'Could not save preferences.';
        }
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        if ($new === '' || $new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && password_verify($current, $row['password'])) {
                    $hash = password_hash($new, PASSWORD_BCRYPT);
                    $upd = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $upd->execute([$hash, $userId]);
                    $notice = 'Password changed.';
                } else {
                    $error = 'Current password is incorrect.';
                }
            } catch (PDOException $e) {
                $error = 'Could not change password.';
            }
        }
    }
}

/* --- Load current values --- */
$userName = 'User';
$userEmail = '';
if ($pdo) {
    try {
        $stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u) { $userName = $u['name'] ?: explode('@', $u['email'])[0]; $userEmail = $u['email']; }
    } catch (PDOException $e) {}
}
$initials = strtoupper(substr($userName, 0, 1));

$prefs = ['notify_email' => 1, 'notify_whatsapp' => 1, 'notify_telegram' => 1];
if ($pdo) {
    try {
        $stmt = $pdo->prepare('SELECT notify_email, notify_whatsapp, notify_telegram FROM user_settings WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $prefs = $row;
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings — AlertIQ</title>
<style>
  :root{ --navy-900:#0f1b3d; --blue-500:#3b6fe0; --cyan-400:#5fd4ff; --ink-900:#101323; --ink-500:#5b6178; --ink-300:#8b91a7; --paper:#fff; --bg-app:#f3f6fc; --hairline:#e7eaf2; --font:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; }
  *{ box-sizing:border-box; } body{ margin:0; font-family:var(--font); background:var(--bg-app); color:var(--ink-900); } a{ color:inherit; text-decoration:none; }
  .shell{ display:flex; min-height:100vh; }
  .sidebar{ width:230px; flex:none; background:var(--paper); border-right:1px solid var(--hairline); display:flex; flex-direction:column; padding:22px 16px; }
  .side-brand{ display:flex; align-items:center; gap:10px; padding:4px 8px 22px; }
  .side-badge{ width:38px; height:38px; border-radius:11px; background:linear-gradient(135deg, var(--blue-500), var(--cyan-400)); display:flex; align-items:center; justify-content:center; flex:none; }
  .side-brand span{ font-weight:700; font-size:18px; }
  .side-nav{ display:flex; flex-direction:column; gap:2px; }
  .side-nav a{ display:flex; align-items:center; gap:11px; padding:10px 12px; border-radius:10px; font-size:14.5px; font-weight:600; color:var(--ink-500); border-left:3px solid transparent; }
  .side-nav a svg{ flex:none; } .side-nav a:hover{ background:var(--bg-app); }
  .side-nav a.active{ background:#eaf1ff; color:var(--blue-500); border-left-color:var(--blue-500); }
  .side-quote{ margin-top:auto; padding:18px 10px 6px; font-size:13.5px; font-style:italic; color:var(--ink-500); line-height:1.5; border-top:1px dashed var(--hairline); margin-top:24px; }
  .side-quote .rule{ width:30px; height:3px; border-radius:2px; background:var(--cyan-400); margin-top:8px; }
  .main{ flex:1; min-width:0; padding:26px 32px 60px; max-width:640px; }
  .topbar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; }
  .topbar .tag{ color:var(--ink-500); font-size:14px; }
  .topbar-right{ display:flex; align-items:center; gap:18px; }
  .avatar{ width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg, var(--blue-500), var(--cyan-400)); color:#fff; font-weight:700; font-size:14px; display:flex; align-items:center; justify-content:center; }
  .user-chip{ display:flex; align-items:center; gap:10px; }
  .user-meta .name{ font-size:13.5px; font-weight:700; }
  .logout{ font-size:12.5px; color:var(--ink-300); }
  h1.title{ font-size:27px; margin:0 0 4px; font-weight:700; }
  .subtitle{ color:var(--ink-500); font-size:14.5px; margin:0 0 22px; }

  .notice, .error-box{ font-size:13.5px; padding:10px 14px; border-radius:10px; margin-bottom:18px; }
  .notice{ background:#e8f8ee; color:#1f9d55; }
  .error-box{ background:#fdecec; color:#e5484d; }

  .card{ background:var(--paper); border:1px solid var(--hairline); border-radius:14px; padding:20px 22px; margin-bottom:18px; }
  .card h2{ font-size:16.5px; margin:0 0 4px; }
  .card .hint{ font-size:13px; color:var(--ink-500); margin:0 0 16px; }
  .field{ margin-bottom:14px; }
  .field label{ display:block; font-size:13px; font-weight:600; margin-bottom:6px; }
  .field input{ width:100%; font-family:var(--font); font-size:14px; padding:10px 12px; border-radius:9px; border:1.5px solid var(--hairline); }
  .field input:disabled{ background:var(--bg-app); color:var(--ink-300); }
  .toggle-row{ display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--hairline); font-size:14px; }
  .toggle-row:last-of-type{ border-bottom:0; }
  .switch{ width:38px; height:22px; }
  .btn{ background:var(--navy-900); color:#fff; border:0; font-family:var(--font); font-weight:600; font-size:14px; padding:10px 18px; border-radius:9px; cursor:pointer; margin-top:6px; }
  .btn:hover{ filter:brightness(1.15); }

  @media (max-width:760px){
    .shell{ flex-direction:column; } .sidebar{ width:100%; flex-direction:row; align-items:center; padding:12px 16px; overflow-x:auto; }
    .side-brand{ padding:0 12px 0 0; } .side-nav{ flex-direction:row; } .side-quote{ display:none; }
    .main{ padding:20px 16px 44px; }
  }
</style>
</head>
<body>
<div class="shell">
  <div class="sidebar">
    <div class="side-brand">
      <div class="side-badge"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 2C9.24 2 7 4.24 7 7v3.2c0 .53-.21 1.04-.59 1.41L5 13v2h14v-2l-1.41-1.39A2 2 0 0 1 17 10.2V7c0-2.76-2.24-5-5-5Z" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/><path d="M9.5 18a2.5 2.5 0 0 0 5 0" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/></svg></div>
      <span>AlertIQ</span>
    </div>
    <nav class="side-nav">
      <a href="dashboard.php"><svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M4 11 12 4l8 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 10v9h12v-9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>Dashboard</a>
      <a href="notifications.php"><svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M12 2C9.24 2 7 4.24 7 7v3.2c0 .53-.21 1.04-.59 1.41L5 13v2h14v-2l-1.41-1.39A2 2 0 0 1 17 10.2V7c0-2.76-2.24-5-5-5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9.5 18a2.5 2.5 0 0 0 5 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>Notifications</a>
      <a class="active" href="settings.php"><svg width="17" height="17" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.3 1a7 7 0 0 0-2-1.2L14 2h-4l-.6 2.6a7 7 0 0 0-2 1.2l-2.3-1-2 3.4 2 1.6A7 7 0 0 0 5 12c0 .4 0 .8.1 1.2l-2 1.6 2 3.4 2.3-1a7 7 0 0 0 2 1.2L10 22h4l.6-2.6a7 7 0 0 0 2-1.2l2.3 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>Settings</a>
      <a href="help.php"><svg width="17" height="17" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.8.4-1 .8-1 1.7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>Help</a>
    </nav>
    <div class="side-quote">"Less noise, more focus."<div class="rule"></div></div>
  </div>

  <div class="main">
    <div class="topbar">
      <div class="tag">Smarter Notifications. Safer You.</div>
      <div class="topbar-right">
        <div class="user-chip">
          <div class="avatar"><?= htmlspecialchars($initials, ENT_QUOTES) ?></div>
          <div class="user-meta"><div class="name"><?= htmlspecialchars($userName, ENT_QUOTES) ?></div></div>
        </div>
        <a class="logout" href="dashboard.php?logout=1">Log out</a>
      </div>
    </div>

    <h1 class="title">Settings</h1>
    <p class="subtitle">Manage your profile, notification channels, and password.</p>

    <?php if ($notice): ?><div class="notice"><?= htmlspecialchars($notice, ENT_QUOTES) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error-box"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

    <div class="card">
      <h2>Profile</h2>
      <p class="hint">Your display name, shown across the dashboard.</p>
      <form method="POST" action="settings.php">
        <input type="hidden" name="action" value="profile">
        <div class="field">
          <label>Name</label>
          <input type="text" name="name" value="<?= htmlspecialchars($userName, ENT_QUOTES) ?>" required>
        </div>
        <div class="field">
          <label>Email</label>
          <input type="text" value="<?= htmlspecialchars($userEmail, ENT_QUOTES) ?>" disabled>
        </div>
        <button class="btn" type="submit">Save profile</button>
      </form>
    </div>

    <div class="card">
      <h2>Notification channels</h2>
      <p class="hint">Choose which sources should feed into your dashboard.</p>
      <form method="POST" action="settings.php">
        <input type="hidden" name="action" value="preferences">
        <div class="toggle-row"><span>Email</span><input class="switch" type="checkbox" name="notify_email" <?= $prefs['notify_email'] ? 'checked' : '' ?>></div>
        <div class="toggle-row"><span>WhatsApp</span><input class="switch" type="checkbox" name="notify_whatsapp" <?= $prefs['notify_whatsapp'] ? 'checked' : '' ?>></div>
        <div class="toggle-row"><span>Telegram</span><input class="switch" type="checkbox" name="notify_telegram" <?= $prefs['notify_telegram'] ? 'checked' : '' ?>></div>
        <button class="btn" type="submit">Save preferences</button>
      </form>
    </div>

    <div class="card">
      <h2>Change password</h2>
      <p class="hint">You'll need your current password to set a new one.</p>
      <form method="POST" action="settings.php">
        <input type="hidden" name="action" value="password">
        <div class="field"><label>Current password</label><input type="password" name="current_password" required></div>
        <div class="field"><label>New password</label><input type="password" name="new_password" required></div>
        <div class="field"><label>Confirm new password</label><input type="password" name="confirm_password" required></div>
        <button class="btn" type="submit">Update password</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
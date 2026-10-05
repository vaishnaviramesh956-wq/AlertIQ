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

/* -----------------------------------------------------------------
 * DB connection — same settings as login.php. Update dbname/user/pass
 * here if your real database differs from this scaffold.
 * ----------------------------------------------------------------- */
function db(): ?PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=localhost;dbname=alertiq;charset=utf8mb4',
                'root', '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            $pdo = false;
        }
    }
    return $pdo ?: null;
}

/* -----------------------------------------------------------------
 * Handle a priority correction (the "Not correct?" control below).
 * Expects a `notifications` table with columns: id, priority.
 * Logs the correction to `priority_corrections` (created if missing)
 * so it's available later as labeled data for train_model.py.
 * ----------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['correct_id'], $_POST['correct_priority'])) {
    $pdo = db();
    if ($pdo) {
        $id = (int) $_POST['correct_id'];
        $newPriority = $_POST['correct_priority'];
        $allowed = ['Critical', 'Important', 'Normal', 'Low'];
        if (in_array($newPriority, $allowed, true)) {
            try {
                $stmt = $pdo->prepare('UPDATE notifications SET priority = ? WHERE id = ?');
                $stmt->execute([$newPriority, $id]);

                $pdo->exec('CREATE TABLE IF NOT EXISTS priority_corrections (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    notification_id INT NOT NULL,
                    corrected_priority VARCHAR(20) NOT NULL,
                    corrected_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )');
                $log = $pdo->prepare('INSERT INTO priority_corrections (notification_id, corrected_priority) VALUES (?, ?)');
                $log->execute([$id, $newPriority]);
            } catch (PDOException $e) {
                // Table/columns may not match yet — safe to ignore for now.
            }
        }
    }
    header('Location: dashboard.php');
    exit;
}

/* -----------------------------------------------------------------
 * User display name — tries `users.name`, falls back gracefully.
 * ----------------------------------------------------------------- */
$userName = 'User';
$pdo = db();
if ($pdo) {
    try {
        $stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $userName = $u['name'] ?: explode('@', $u['email'])[0];
        }
    } catch (PDOException $e) {
        // `name` column may not exist yet — keep the fallback.
    }
}
$initials = strtoupper(substr($userName, 0, 1));

/* -----------------------------------------------------------------
 * Notifications — pulled from the `notifications` table:
 * id, source, sender, message, priority, created_at.
 * Falls back to sample rows if the table isn't set up yet, so this
 * page still renders while you wire up add_notification.php / DB.
 * ----------------------------------------------------------------- */
$notifications = [];
$usingSample = false;

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, source, sender, message, priority, created_at FROM notifications
                              ORDER BY FIELD(priority,'Critical','Important','Normal','Low'), created_at DESC
                              LIMIT 50");
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $usingSample = true;
    }
} else {
    $usingSample = true;
}

if ($usingSample || !$notifications) {
    $usingSample = true;
    $notifications = [
        ['id' => 1, 'source' => 'whatsapp', 'sender' => 'Manager', 'message' => 'server crash urgent help needed', 'priority' => 'Critical', 'created_at' => date('Y-m-d H:i:s', strtotime('-40 min'))],
        ['id' => 2, 'source' => 'gmail',    'sender' => 'HR Team', 'message' => 'Interview call reminder for tomorrow', 'priority' => 'Important', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))],
        ['id' => 3, 'source' => 'telegram', 'sender' => 'Dev Group', 'message' => 'Deployment finished successfully', 'priority' => 'Normal', 'created_at' => date('Y-m-d H:i:s', strtotime('-3 hour'))],
        ['id' => 4, 'source' => 'email',    'sender' => 'Newsletter', 'message' => 'This week in tech — digest', 'priority' => 'Low', 'created_at' => date('Y-m-d H:i:s', strtotime('-5 hour'))],
    ];
}

$counts = ['Critical' => 0, 'Important' => 0, 'Normal' => 0, 'Low' => 0];
foreach ($notifications as $n) {
    if (isset($counts[$n['priority']])) $counts[$n['priority']]++;
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    return floor($diff / 86400) . ' d ago';
}

function sourceIcon(string $source): array {
    // [svg-path, background color, label]
    return match ($source) {
        'whatsapp' => ['<path d="M21 11.5a8.4 8.4 0 0 1-8.9 8.4A8.8 8.8 0 0 1 8 19l-4 1 1-3.6A8.4 8.4 0 1 1 21 11.5Z" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/>', '#25b064', 'WhatsApp'],
        'telegram' => ['<path d="m3 4 18 8-18 8 4-8-4-8Z" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/>', '#33a3e3', 'Telegram'],
        'gmail'    => ['<path d="M4 6h16v12H4V6Z" stroke="#fff" stroke-width="1.6"/><path d="m4 7 8 6 8-6" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>', '#e35a45', 'Gmail'],
        default    => ['<path d="M4 6h16v12H4V6Z" stroke="#fff" stroke-width="1.6"/><path d="m4 7 8 6 8-6" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>', '#5b6178', 'Email'],
    };
}

$priorityStyle = [
    'Critical'  => ['bg' => '#fdecec', 'fg' => '#e5484d', 'border' => '#e5484d', 'emoji' => '🚨', 'icon' => '<path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/>'],
    'Important' => ['bg' => '#fef3e0', 'fg' => '#c07a06', 'border' => '#f5a524', 'emoji' => '🔥', 'icon' => '<path d="M12 3 2 20h20L12 3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12 10v4M12 17h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>'],
    'Normal'    => ['bg' => '#e9f0ff', 'fg' => '#3b6fe0', 'border' => '#3b6fe0', 'emoji' => '⛓️‍💥', 'icon' => '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M12 8h.01M11 12h1v4h1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'],
    'Low'       => ['bg' => '#e8f8ee', 'fg' => '#1f9d55', 'border' => '#1f9d55', 'emoji' => '📌', 'icon' => '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="m8 12 3 3 5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — AlertIQ</title>
<style>
  :root{
    --navy-900:#0f1b3d; --blue-500:#3b6fe0; --cyan-400:#5fd4ff;
    --ink-900:#101323; --ink-500:#5b6178; --ink-300:#8b91a7;
    --paper:#ffffff; --bg-app:#f3f6fc; --hairline:#e7eaf2;
    --font:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  }
  *{ box-sizing:border-box; }
  body{ margin:0; font-family:var(--font); background:var(--bg-app); color:var(--ink-900); }
  a{ color:inherit; text-decoration:none; }

  .shell{ display:flex; min-height:100vh; }

  /* ---------------- Sidebar ---------------- */
  .sidebar{
    width:230px; flex:none; background:var(--paper); border-right:1px solid var(--hairline);
    display:flex; flex-direction:column; padding:22px 16px;
  }
  .side-brand{ display:flex; align-items:center; gap:10px; padding:4px 8px 22px; }
  .side-badge{
    width:38px; height:38px; border-radius:11px;
    background:linear-gradient(135deg, var(--blue-500), var(--cyan-400));
    display:flex; align-items:center; justify-content:center; flex:none;
  }
  .side-brand span{ font-weight:700; font-size:18px; }

  .side-nav{ display:flex; flex-direction:column; gap:2px; }
  .side-nav a{
    display:flex; align-items:center; gap:11px; padding:10px 12px;
    border-radius:10px; font-size:14.5px; font-weight:600; color:var(--ink-500);
    border-left:3px solid transparent;
  }
  .side-nav a svg{ flex:none; }
  .side-nav a:hover{ background:var(--bg-app); }
  .side-nav a.active{
    background:#eaf1ff; color:var(--blue-500); border-left-color:var(--blue-500);
  }

  .side-quote{
    margin-top:auto; padding:18px 10px 6px; font-size:13.5px; font-style:italic;
    color:var(--ink-500); line-height:1.5; border-top:1px dashed var(--hairline); margin-top:24px;
  }
  .side-quote .rule{ width:30px; height:3px; border-radius:2px; background:var(--cyan-400); margin-top:8px; }

  /* ---------------- Main ---------------- */
  .main{ flex:1; min-width:0; padding:26px 32px 60px; }

  .topbar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; }
  .topbar .tag{ color:var(--ink-500); font-size:14px; }
  .topbar-right{ display:flex; align-items:center; gap:18px; }
  .bell-btn{ position:relative; width:38px; height:38px; border-radius:10px; background:var(--paper); border:1px solid var(--hairline); display:flex; align-items:center; justify-content:center; color:var(--ink-500); }
  .bell-btn .dot{ position:absolute; top:7px; right:8px; width:7px; height:7px; border-radius:50%; background:#e5484d; }
  .user-chip{ display:flex; align-items:center; gap:10px; }
  .avatar{ width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg, var(--blue-500), var(--cyan-400)); color:#fff; font-weight:700; font-size:14px; display:flex; align-items:center; justify-content:center; }
  .user-meta .name{ font-size:13.5px; font-weight:700; }
  .user-meta .status{ font-size:11.5px; color:#1f9d55; display:flex; align-items:center; gap:4px; }
  .user-meta .status i{ width:6px; height:6px; border-radius:50%; background:#1f9d55; display:inline-block; }
  .logout{ font-size:12.5px; color:var(--ink-300); }

  .content-grid{ display:grid; grid-template-columns:1fr 260px; gap:28px; align-items:start; }

  h1.title{ font-size:27px; margin:0 0 4px; font-weight:700; }
  .subtitle{ color:var(--ink-500); font-size:14.5px; margin:0 0 22px; }

  .stats{ display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
  .stat{ background:var(--paper); border:1px solid var(--hairline); border-radius:14px; padding:16px; }
  .stat .ic{ width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center; margin-bottom:10px; }
  .stat .n{ font-size:24px; font-weight:700; }
  .stat .l{ font-size:11.5px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; margin-top:2px; }

  .btn-add{
    display:inline-flex; align-items:center; gap:8px;
    background:var(--navy-900); color:#fff; font-weight:600; font-size:14px;
    padding:11px 18px; border-radius:10px; margin-bottom:22px;
  }
  .btn-add:hover{ filter:brightness(1.15); }

  .item{ background:var(--paper); border:1px solid var(--hairline); border-left:4px solid var(--hairline); border-radius:13px; padding:16px 16px 14px; margin-bottom:12px; }
  .item-row{ display:flex; align-items:center; gap:10px; margin-bottom:10px; }
  .badge{ font-size:11px; font-weight:700; letter-spacing:.03em; padding:3px 10px; border-radius:999px; }
  .src{ display:flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-500); font-weight:600; }
  .src .ic{ width:20px; height:20px; border-radius:6px; display:flex; align-items:center; justify-content:center; }
  .time{ margin-left:auto; font-size:12px; color:var(--ink-300); }
  .kebab{ background:none; border:0; color:var(--ink-300); cursor:pointer; padding:4px; }

  .msg{ font-size:14.5px; }
  .msg b{ font-weight:700; }

  .correct{ display:none; align-items:center; gap:10px; margin-top:12px; padding-top:12px; border-top:1px dashed var(--hairline); font-size:13px; color:var(--ink-500); }
  .correct.open{ display:flex; flex-wrap:wrap; }
  .correct select{ font-family:var(--font); font-size:13px; padding:6px 10px; border-radius:8px; border:1px solid var(--hairline); }
  .correct button{ font-family:var(--font); font-size:13px; font-weight:600; background:var(--navy-900); color:#fff; border:0; padding:7px 14px; border-radius:8px; cursor:pointer; }

  /* ---------------- Right promo panel ---------------- */
  .promo{ position:sticky; top:26px; background:linear-gradient(160deg,#eef4ff,#f8fbff); border:1px solid var(--hairline); border-radius:18px; padding:26px 20px; text-align:center; }
  .promo .cluster{ position:relative; width:160px; height:160px; margin:0 auto 16px; }
  .promo .ring{ position:absolute; inset:0; border:1.5px dashed rgba(59,111,224,0.25); border-radius:50%; }
  .promo .core{ position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:70px; height:70px; border-radius:18px; background:linear-gradient(145deg, var(--blue-500), var(--navy-900)); display:flex; align-items:center; justify-content:center; box-shadow:0 14px 30px -10px rgba(59,111,224,.55); }
  .promo .chip{ position:absolute; width:30px; height:30px; border-radius:9px; display:flex; align-items:center; justify-content:center; background:#fff; border:1px solid var(--hairline); }
  .promo .c1{ top:0; left:6%; } .promo .c2{ top:4%; right:0; } .promo .c3{ bottom:2%; right:8%; } .promo .c4{ bottom:8%; left:0; }
  .promo h3{ font-size:15.5px; margin:0 0 4px; }
  .promo .rule{ width:34px; height:3px; border-radius:2px; background:var(--cyan-400); margin:8px auto 0; }
  @media (prefers-reduced-motion:no-preference){ .promo .ring{ animation:spin 20s linear infinite; } }
  @keyframes spin{ to{ transform:rotate(360deg); } }

  @keyframes critical-vibrate{
    0%,10%,60%,100%{ transform:translateX(0); }
    2%,6%{ transform:translateX(-3px); }
    4%,8%{ transform:translateX(3px); }
    62%,66%{ transform:translateX(-3px); }
    64%,68%{ transform:translateX(3px); }
  }
  @keyframes critical-glow{
    0%,100%{ box-shadow:0 0 0 0 rgba(229,72,77,.35); }
    50%{ box-shadow:0 0 0 6px rgba(229,72,77,.12); }
  }
  .item.critical-alert{
    animation: critical-vibrate 3s ease-in-out infinite, critical-glow 1.6s ease-in-out infinite;
  }
  @media (prefers-reduced-motion: reduce){
    .item.critical-alert{ animation: critical-glow 1.6s ease-in-out infinite; }
  }

  /* ---- themed priority icon animations ---- */
  .p-icon{ display:inline-block; }
  @keyframes alarm-blink{ 0%,49%{ opacity:1; } 50%,100%{ opacity:.35; } }
  .p-icon-critical{ animation: alarm-blink 0.7s steps(1) infinite; }

  @keyframes flame-flicker{
    0%   { transform:scale(1) rotate(-4deg);   filter:drop-shadow(0 0 2px rgba(245,165,36,.5)); }
    50%  { transform:scale(1.18) rotate(4deg); filter:drop-shadow(0 0 7px rgba(245,101,36,.9)); }
    100% { transform:scale(1.05) rotate(-2deg);filter:drop-shadow(0 0 4px rgba(245,165,36,.7)); }
  }
  .p-icon-important{ animation: flame-flicker 0.5s ease-in-out infinite alternate; transform-origin:bottom center; }

  @keyframes chain-sway{
    0%,100%{ transform:rotate(-12deg); }
    50%{ transform:rotate(12deg); }
  }
  .p-icon-normal{ transform-origin:top center; animation: chain-sway 1.7s ease-in-out infinite; }

  @keyframes bubble-rise{
    0%{ transform:translateY(0) scale(.5); opacity:0; }
    25%{ opacity:.85; }
    100%{ transform:translateY(-14px) scale(1); opacity:0; }
  }
  .p-icon-low{ position:relative; }
  .p-icon-low::before, .p-icon-low::after{
    content:''; position:absolute; bottom:2px; border-radius:50%;
    background:radial-gradient(circle at 30% 30%, rgba(255,255,255,.95), rgba(59,111,224,.4));
    animation: bubble-rise 2.1s ease-in infinite;
  }
  .p-icon-low::before{ left:-3px; width:5px; height:5px; animation-delay:0s; }
  .p-icon-low::after{ left:11px; width:4px; height:4px; animation-delay:1.05s; }

  @media (prefers-reduced-motion: reduce){
    .p-icon-critical, .p-icon-important, .p-icon-normal{ animation:none; }
    .p-icon-low::before, .p-icon-low::after{ animation:none; opacity:.6; }
  }

  @media (max-width:1080px){ .content-grid{ grid-template-columns:1fr; } .promo{ display:none; } }
  @media (max-width:760px){
    .shell{ flex-direction:column; }
    .sidebar{ width:100%; flex-direction:row; align-items:center; padding:12px 16px; overflow-x:auto; }
    .side-brand{ padding:0 12px 0 0; }
    .side-nav{ flex-direction:row; }
    .side-quote{ display:none; }
    .main{ padding:20px 16px 44px; }
    .stats{ grid-template-columns:repeat(2,1fr); }
  }
</style>
</head>
<body>

<div class="shell">

  <div class="sidebar">
    <div class="side-brand">
      <div class="side-badge">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 2C9.24 2 7 4.24 7 7v3.2c0 .53-.21 1.04-.59 1.41L5 13v2h14v-2l-1.41-1.39A2 2 0 0 1 17 10.2V7c0-2.76-2.24-5-5-5Z" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/><path d="M9.5 18a2.5 2.5 0 0 0 5 0" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/></svg>
      </div>
      <span>AlertIQ</span>
    </div>
    <nav class="side-nav">
      <a class="active" href="dashboard.php">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M4 11 12 4l8 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 10v9h12v-9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Dashboard
      </a>
      <a href="notifications.php">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M12 2C9.24 2 7 4.24 7 7v3.2c0 .53-.21 1.04-.59 1.41L5 13v2h14v-2l-1.41-1.39A2 2 0 0 1 17 10.2V7c0-2.76-2.24-5-5-5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9.5 18a2.5 2.5 0 0 0 5 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        Notifications
      </a>
      <a href="settings.php">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.3 1a7 7 0 0 0-2-1.2L14 2h-4l-.6 2.6a7 7 0 0 0-2 1.2l-2.3-1-2 3.4 2 1.6A7 7 0 0 0 5 12c0 .4 0 .8.1 1.2l-2 1.6 2 3.4 2.3-1a7 7 0 0 0 2 1.2L10 22h4l.6-2.6a7 7 0 0 0 2-1.2l2.3 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>
        Settings
      </a>
      <a href="help.php">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.8.4-1 .8-1 1.7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Help
      </a>
    </nav>
    <div class="side-quote">
      "Less noise, more focus."
      <div class="rule"></div>
    </div>
  </div>

  <div class="main">
    <div class="topbar">
      <div class="tag">Smarter Notifications. Safer You.</div>
      <div class="topbar-right">
        <a class="bell-btn" href="notifications.php" title="Notifications">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M12 2C9.24 2 7 4.24 7 7v3.2c0 .53-.21 1.04-.59 1.41L5 13v2h14v-2l-1.41-1.39A2 2 0 0 1 17 10.2V7c0-2.76-2.24-5-5-5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9.5 18a2.5 2.5 0 0 0 5 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
          <span class="dot"></span>
        </a>
        <div class="user-chip">
          <div class="avatar"><?= htmlspecialchars($initials, ENT_QUOTES) ?></div>
          <div class="user-meta">
            <div class="name"><?= htmlspecialchars($userName, ENT_QUOTES) ?></div>
            <div class="status"><i></i> Online</div>
          </div>
        </div>
        <a class="logout" href="?logout=1">Log out</a>
      </div>
    </div>

    <div class="content-grid">
      <div>
        <h1 class="title">Dashboard</h1>
        <p class="subtitle">Notifications sorted by priority, powered by AI.</p>

        <div class="stats">
          <?php foreach ($counts as $label => $count):
            $s = $priorityStyle[$label]; ?>
            <div class="stat">
              <div class="ic" style="background:<?= $s['bg'] ?>;color:<?= $s['fg'] ?>">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><?= $s['icon'] ?></svg>
              </div>
              <div class="n" style="color:<?= $s['fg'] ?>"><?= $count ?></div>
              <div class="l" style="color:<?= $s['fg'] ?>"><?= strtoupper($label) ?></div>
            </div>
          <?php endforeach; ?>
        </div>

        <a class="btn-add" href="add_notification.php">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
          Add new notification
        </a>

        <?php foreach ($notifications as $n):
          $s = $priorityStyle[$n['priority']] ?? $priorityStyle['Normal'];
          [$iconPath, $iconBg, $srcLabel] = sourceIcon($n['source']); ?>
          <div class="item<?= $n['priority'] === 'Critical' ? ' critical-alert' : '' ?>" style="border-left-color:<?= $s['border'] ?>">
            <div class="item-row">
              <span class="badge" style="background:<?= $s['bg'] ?>;color:<?= $s['fg'] ?>"><span class="p-icon p-icon-<?= strtolower($n['priority']) ?>"><?= $s['emoji'] ?></span> <?= strtoupper($n['priority']) ?></span>
              <span class="src">
                <span class="ic" style="background:<?= $iconBg ?>"><svg width="12" height="12" viewBox="0 0 24 24" fill="none"><?= $iconPath ?></svg></span>
                <?= htmlspecialchars($srcLabel, ENT_QUOTES) ?>
              </span>
              <span class="time"><?= timeAgo($n['created_at']) ?></span>
              <button class="kebab" onclick="document.getElementById('c<?= $n['id'] ?>').classList.toggle('open')" aria-label="More options">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="5" r="1.5" fill="currentColor"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/><circle cx="12" cy="19" r="1.5" fill="currentColor"/></svg>
              </button>
            </div>
            <div class="msg"><b><?= htmlspecialchars($n['sender'], ENT_QUOTES) ?>:</b> <?= htmlspecialchars($n['message'], ENT_QUOTES) ?></div>

            <form class="correct" id="c<?= $n['id'] ?>" method="POST" action="dashboard.php">
              <span>Not correct? Actual priority:</span>
              <input type="hidden" name="correct_id" value="<?= (int) $n['id'] ?>">
              <select name="correct_priority">
                <?php foreach (['Critical','Important','Normal','Low'] as $opt): ?>
                  <option value="<?= $opt ?>" <?= $opt === $n['priority'] ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit">Submit</button>
            </form>
          </div>
        <?php endforeach; ?>

        <?php if ($usingSample): ?>
          <p style="font-size:12.5px;color:var(--ink-300);margin-top:6px;">
            Showing sample data — connect the <code>notifications</code> table to replace this.
          </p>
        <?php endif; ?>
      </div>

      <div class="promo">
        <div class="cluster">
          <div class="ring"></div>
          <div class="core"><svg width="30" height="30" viewBox="0 0 24 24" fill="none"><path d="M12 2C9.24 2 7 4.24 7 7v3.2c0 .53-.21 1.04-.59 1.41L5 13v2h14v-2l-1.41-1.39A2 2 0 0 1 17 10.2V7c0-2.76-2.24-5-5-5Z" stroke="#fff" stroke-width="1.5" stroke-linejoin="round"/><path d="M9.5 18a2.5 2.5 0 0 0 5 0" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/></svg></div>
          <div class="chip c1" style="color:#25b064"><svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M21 11.5a8.4 8.4 0 0 1-8.9 8.4A8.8 8.8 0 0 1 8 19l-4 1 1-3.6A8.4 8.4 0 1 1 21 11.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg></div>
          <div class="chip c2" style="color:#e35a45"><svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M4 6h16v12H4V6Z" stroke="currentColor" stroke-width="1.6"/><path d="m4 7 8 6 8-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
          <div class="chip c3" style="color:#33a3e3"><svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="m3 4 18 8-18 8 4-8-4-8Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg></div>
          <div class="chip c4" style="color:var(--ink-500)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M4 6h16v12H4V6Z" stroke="currentColor" stroke-width="1.6"/><path d="m4 7 8 6 8-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        </div>
        <h3>Smart Notifications for a Safer Digital World</h3>
        <div class="rule"></div>
      </div>
    </div>
  </div>

</div>

</body>
</html>
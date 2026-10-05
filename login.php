<?php
session_start();

$error = '';
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldEmail = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($oldEmail === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        /* -----------------------------------------------------------------
         * Plug your existing AlertIQ authentication logic in here.
         * This is a working scaffold: it assumes a `users` table with an
         * `email` column and a bcrypt-hashed `password` column. Update the
         * connection details (host / dbname / user / pass) to match your
         * local MySQL setup, or replace this whole block with your current
         * login.php logic — the form below posts `email` and `password`
         * fields, same as before, so nothing else needs to change.
         * ----------------------------------------------------------------- */
        try {
            $pdo = new PDO(
                'mysql:host=localhost;dbname=alertiq;charset=utf8mb4',
                'root',
                '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $stmt = $pdo->prepare('SELECT id, password FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$oldEmail]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                header('Location: dashboard.php');
                exit;
            }
            $error = 'Invalid email or password.';
        } catch (PDOException $e) {
            $error = 'Could not reach the database. Check the connection settings in login.php.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in — AlertIQ</title>
<style>
  :root{
    --navy-950:#0a1128;
    --navy-900:#0f1b3d;
    --navy-800:#16225a;
    --indigo-600:#2a3f9e;
    --blue-500:#3b6fe0;
    --cyan-400:#5fd4ff;
    --ink-900:#101323;
    --ink-500:#5b6178;
    --ink-300:#8b91a7;
    --paper:#ffffff;
    --hairline:#e3e6ef;
    --radius-lg:20px;
    --radius-md:12px;
    --font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  }

  *{ box-sizing:border-box; }
  html,body{ height:100%; }
  body{
    margin:0;
    font-family:var(--font);
    color:var(--ink-900);
    background:
      radial-gradient(1100px 700px at 82% 18%, rgba(95,212,255,0.16), transparent 60%),
      radial-gradient(900px 600px at 8% 85%, rgba(59,111,224,0.20), transparent 55%),
      linear-gradient(160deg, var(--navy-950) 0%, var(--navy-900) 45%, var(--navy-800) 100%);
    min-height:100vh;
    overflow-x:hidden;
  }

  .scene{
    min-height:100vh;
    display:grid;
    grid-template-columns:1.1fr 0.95fr 0.9fr;
    align-items:center;
    gap:24px;
    padding:56px 64px;
  }

  /* ---------- Brand + headline (left) ---------- */
  .brand{
    position:absolute;
    top:40px;
    left:64px;
    display:flex;
    align-items:center;
    gap:12px;
  }
  .brand-badge{
    width:44px; height:44px;
    border-radius:12px;
    background:linear-gradient(135deg, var(--blue-500), var(--cyan-400));
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 8px 24px rgba(59,111,224,0.45);
  }
  .brand-name{ color:#fff; font-size:20px; font-weight:700; line-height:1.15; }
  .brand-tag{ color:var(--ink-300); font-size:12.5px; margin-top:1px; }

  .hero{
    padding-top:64px;
  }
  .hero h1{
    font-size:clamp(34px, 4vw, 50px);
    line-height:1.08;
    font-weight:700;
    color:#fff;
    margin:0 0 18px;
    letter-spacing:-0.01em;
  }
  .hero-rule{
    width:56px; height:4px; border-radius:2px;
    background:linear-gradient(90deg, var(--cyan-400), var(--blue-500));
    margin-bottom:20px;
  }
  .hero p{
    color:var(--ink-300);
    font-size:16px;
    line-height:1.6;
    max-width:34ch;
    margin:0;
  }

  /* ---------- Sign-in card (center) ---------- */
  .card{
    position:relative;
    background:var(--paper);
    border-radius:var(--radius-lg);
    padding:36px 34px 30px;
    width:100%;
    max-width:400px;
    justify-self:center;
    box-shadow:0 30px 60px -20px rgba(5,10,30,0.55);
  }
  .card-head{ display:flex; align-items:center; gap:10px; margin-bottom:22px; }
  .card-badge{
    width:32px; height:32px; border-radius:9px;
    background:linear-gradient(135deg, var(--blue-500), var(--cyan-400));
    display:flex; align-items:center; justify-content:center;
  }
  .card-head span{ font-weight:700; font-size:17px; color:var(--ink-900); }

  .card h2{ font-size:23px; margin:0 0 6px; font-weight:700; color:var(--ink-900); }
  .card .sub{ font-size:14px; color:var(--ink-500); line-height:1.5; margin:0 0 24px; }

  .field{ margin-bottom:16px; }
  .field label{
    display:block; font-size:13px; font-weight:600;
    color:var(--ink-900); margin-bottom:7px;
  }
  .input-wrap{
    display:flex; align-items:center; gap:9px;
    border:1.5px solid var(--hairline);
    border-radius:var(--radius-md);
    padding:11px 13px;
    transition:border-color .15s ease, box-shadow .15s ease;
  }
  .input-wrap:focus-within{
    border-color:var(--blue-500);
    box-shadow:0 0 0 4px rgba(59,111,224,0.12);
  }
  .input-wrap svg{ flex:none; color:var(--ink-300); }
  .input-wrap input{
    border:0; outline:0; width:100%;
    font-size:14.5px; font-family:var(--font); color:var(--ink-900);
    background:transparent;
  }
  .toggle-pw{
    flex:none; background:none; border:0; padding:2px; cursor:pointer;
    color:var(--ink-300); display:flex;
  }
  .toggle-pw:hover{ color:var(--ink-500); }

  .error-box{
    background:#fdecec; border:1px solid #f6c6c6; color:#9a3131;
    font-size:13px; padding:10px 12px; border-radius:10px; margin-bottom:16px;
  }

  .submit{
    width:100%;
    margin-top:6px;
    background:linear-gradient(135deg, var(--navy-900), var(--indigo-600));
    color:#fff; border:0; border-radius:var(--radius-md);
    padding:13px 16px; font-size:15px; font-weight:600;
    display:flex; align-items:center; justify-content:center; gap:8px;
    cursor:pointer;
    transition:filter .15s ease, transform .05s ease;
  }
  .submit:hover{ filter:brightness(1.12); }
  .submit:active{ transform:translateY(1px); }

  .card-foot{
    text-align:center; font-size:12.5px; color:var(--ink-300);
    margin-top:18px;
  }

  /* ---------- Icon cluster (right) ---------- */
  .cluster{
    position:relative;
    width:280px; height:280px;
    justify-self:center;
  }
  .cluster .ring{
    position:absolute; inset:0;
    border:1.5px dashed rgba(255,255,255,0.14);
    border-radius:50%;
  }
  .cluster .core{
    position:absolute; top:50%; left:50%;
    transform:translate(-50%,-50%);
    width:118px; height:118px;
    border-radius:26px;
    background:linear-gradient(145deg, var(--blue-500), var(--navy-900));
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 20px 45px -12px rgba(59,111,224,0.55);
  }
  .chip{
    position:absolute;
    width:52px; height:52px;
    border-radius:14px;
    display:flex; align-items:center; justify-content:center;
    background:rgba(255,255,255,0.06);
    border:1px solid rgba(255,255,255,0.14);
    backdrop-filter:blur(6px);
    color:#eaf1ff;
  }
  .chip.c1{ top:2%; left:8%; }
  .chip.c2{ top:6%; right:0%; }
  .chip.c3{ bottom:4%; right:10%; }
  .chip.c4{ bottom:10%; left:0%; }

  @media (prefers-reduced-motion: no-preference){
    .cluster .ring{ animation:spin 22s linear infinite; }
  }
  @keyframes spin{ to{ transform:rotate(360deg); } }

  /* ---------- Responsive ---------- */
  @media (max-width: 1100px){
    .scene{ grid-template-columns:1fr; justify-items:center; text-align:center; padding:100px 24px 48px; gap:36px; }
    .brand{ position:static; margin-bottom:0; }
    .hero{ padding-top:0; }
    .hero-rule{ margin-left:auto; margin-right:auto; }
    .hero p{ margin-left:auto; margin-right:auto; }
    .cluster{ display:none; }
  }
  @media (max-width: 480px){
    .card{ padding:28px 22px 24px; }
  }
</style>
</head>
<body>

<div class="brand">
  <div class="brand-badge">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 2C9.24 2 7 4.24 7 7v3.2c0 .53-.21 1.04-.59 1.41L5 13v2h14v-2l-1.41-1.39A2 2 0 0 1 17 10.2V7c0-2.76-2.24-5-5-5Z" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/><path d="M9.5 18a2.5 2.5 0 0 0 5 0" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/></svg>
  </div>
  <div>
    <div class="brand-name">AlertIQ</div>
    <div class="brand-tag">Smarter alerts. Safer you.</div>
  </div>
</div>

<div class="scene">

  <div class="hero">
    <h1>Stay alert.<br>Stay secure.</h1>
    <div class="hero-rule"></div>
    <p>Smarter notifications for a safer digital world — sorted by what actually matters.</p>
  </div>

  <div class="card">
    <div class="card-head">
      <div class="card-badge">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 2C9.24 2 7 4.24 7 7v3.2c0 .53-.21 1.04-.59 1.41L5 13v2h14v-2l-1.41-1.39A2 2 0 0 1 17 10.2V7c0-2.76-2.24-5-5-5Z" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/><path d="M9.5 18a2.5 2.5 0 0 0 5 0" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/></svg>
      </div>
      <span>AlertIQ</span>
    </div>

    <h2>Sign in to your dashboard</h2>
    <p class="sub">Your notifications, sorted by what actually matters.</p>

    <?php if ($error !== ''): ?>
      <div class="error-box"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>
      <div class="field">
        <label for="email">Email</label>
        <div class="input-wrap">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M4 6h16v12H4V6Z" stroke="currentColor" stroke-width="1.6"/><path d="m4 7 8 6 8-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <input type="email" id="email" name="email" placeholder="test@example.com" value="<?= htmlspecialchars($oldEmail, ENT_QUOTES) ?>" required autofocus>
        </div>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="input-wrap">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.6"/></svg>
          <input type="password" id="password" name="password" placeholder="••••••••" required>
          <button type="button" class="toggle-pw" id="togglePw" aria-label="Show password">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/></svg>
          </button>
        </div>
      </div>

      <button type="submit" class="submit">
        Sign in
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 6l6 6-6 6" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
    </form>

    <div class="card-foot">AI-powered notification prioritization</div>
  </div>

  <div class="cluster" aria-hidden="true">
    <div class="ring"></div>
    <div class="core">
      <svg width="46" height="46" viewBox="0 0 24 24" fill="none"><path d="M12 2C9.24 2 7 4.24 7 7v3.2c0 .53-.21 1.04-.59 1.41L5 13v2h14v-2l-1.41-1.39A2 2 0 0 1 17 10.2V7c0-2.76-2.24-5-5-5Z" stroke="#fff" stroke-width="1.5" stroke-linejoin="round"/><path d="M9.5 18a2.5 2.5 0 0 0 5 0" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/></svg>
    </div>
    <div class="chip c1">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M4 6h16v12H4V6Z" stroke="currentColor" stroke-width="1.5"/><path d="m4 7 8 6 8-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <div class="chip c2">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M21 11.5a8.4 8.4 0 0 1-8.9 8.4A8.8 8.8 0 0 1 8 19l-4 1 1-3.6A8.4 8.4 0 1 1 21 11.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
    </div>
    <div class="chip c3">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="m3 4 18 8-18 8 4-8-4-8Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
    </div>
    <div class="chip c4">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
    </div>
  </div>

</div>

<script>
  document.getElementById('togglePw').addEventListener('click', function () {
    var input = document.getElementById('password');
    var isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
  });
</script>
</body>
</html>
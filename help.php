<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userName = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Help — AlertIQ</title>
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
  .main{ flex:1; min-width:0; padding:26px 32px 60px; max-width:720px; }
  .topbar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; }
  .topbar .tag{ color:var(--ink-500); font-size:14px; }
  .logout{ font-size:12.5px; color:var(--ink-300); }
  h1.title{ font-size:27px; margin:0 0 4px; font-weight:700; }
  .subtitle{ color:var(--ink-500); font-size:14.5px; margin:0 0 22px; }

  .faq{ background:var(--paper); border:1px solid var(--hairline); border-radius:12px; margin-bottom:10px; overflow:hidden; }
  .faq summary{ cursor:pointer; list-style:none; padding:15px 18px; font-size:14.5px; font-weight:600; display:flex; align-items:center; justify-content:space-between; }
  .faq summary::-webkit-details-marker{ display:none; }
  .faq summary .chev{ transition:transform .15s ease; color:var(--ink-300); }
  .faq[open] summary .chev{ transform:rotate(180deg); }
  .faq p{ margin:0; padding:0 18px 16px; font-size:13.5px; color:var(--ink-500); line-height:1.6; }

  .contact{ margin-top:26px; background:linear-gradient(160deg,#eef4ff,#f8fbff); border:1px solid var(--hairline); border-radius:14px; padding:20px 22px; font-size:13.5px; color:var(--ink-500); }
  .contact b{ color:var(--ink-900); }

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
      <a href="settings.php"><svg width="17" height="17" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.3 1a7 7 0 0 0-2-1.2L14 2h-4l-.6 2.6a7 7 0 0 0-2 1.2l-2.3-1-2 3.4 2 1.6A7 7 0 0 0 5 12c0 .4 0 .8.1 1.2l-2 1.6 2 3.4 2.3-1a7 7 0 0 0 2 1.2L10 22h4l.6-2.6a7 7 0 0 0 2-1.2l2.3 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>Settings</a>
      <a class="active" href="help.php"><svg width="17" height="17" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.8.4-1 .8-1 1.7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>Help</a>
    </nav>
    <div class="side-quote">"Less noise, more focus."<div class="rule"></div></div>
  </div>

  <div class="main">
    <div class="topbar">
      <div class="tag">Smarter Notifications. Safer You.</div>
      <a class="logout" href="dashboard.php?logout=1">Log out</a>
    </div>

    <h1 class="title">Help & FAQ</h1>
    <p class="subtitle">Quick answers about how AlertIQ works.</p>

    <details class="faq" open>
      <summary>What is AlertIQ? <span class="chev">⌄</span></summary>
      <p>AlertIQ collects notifications from different sources (email, WhatsApp, Telegram, and more) and uses a trained model to sort them by priority — Critical, Important, Normal, or Low — so you see what matters first.</p>
    </details>

    <details class="faq">
      <summary>How is a notification's priority decided? <span class="chev">⌄</span></summary>
      <p>Each message is passed through the model in <code>ml_model/predict.py</code>, which was trained with <code>train_model.py</code> on labeled examples. It looks at the wording of the message to estimate how urgent it is.</p>
    </details>

    <details class="faq">
      <summary>The priority looks wrong — can I fix it? <span class="chev">⌄</span></summary>
      <p>Yes. On the Notifications page, open the ⋮ menu on any item, pick the correct priority from the dropdown, and save. Your correction is stored separately so it can be used to improve the model later.</p>
    </details>

    <details class="faq">
      <summary>How do I add a notification manually? <span class="chev">⌄</span></summary>
      <p>Use the "Add new notification" button on the Dashboard. It opens the notification form where you can enter the source, sender, and message.</p>
    </details>

    <details class="faq">
      <summary>How do I change my password or notification channels? <span class="chev">⌄</span></summary>
      <p>Go to Settings. You can update your display name, turn notification channels on or off, and change your password there.</p>
    </details>

    <details class="faq">
      <summary>I'm having trouble signing in. <span class="chev">⌄</span></summary>
      <p>Double-check your email and password, and make sure your local server (Apache/MySQL) is running. If the problem continues, an admin may need to check the database connection settings.</p>
    </details>

    <div class="contact">
      Still stuck? Reach out to <b>support@alertiq.local</b> and describe what you were doing when the issue happened.
    </div>
  </div>
</div>
</body>
</html>
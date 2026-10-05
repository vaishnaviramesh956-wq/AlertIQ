<?php
$conn = new mysqli("localhost", "root", "", "alertiq");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$predicted_priority = "";
$message_text = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $source = $conn->real_escape_string($_POST['source']);
    $sender = $conn->real_escape_string($_POST['sender']);
    $message_text = $conn->real_escape_string($_POST['message']);

    $python_path = "C:\\Users\\acer\\AppData\\Local\\Programs\\Python\\Python314\\python.exe";
    $script_path = __DIR__ . "\\ml_model\\predict.py";
    $safe_message = escapeshellarg($message_text);

    $command = "$python_path $script_path $safe_message 2>&1";
    $predicted_priority = trim(shell_exec($command));

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, source, sender, message, priority, confidence_score) VALUES (1, ?, ?, ?, ?, ?)");
    $confidence = 0.80;
    $stmt->bind_param("ssssd", $source, $sender, $message_text, $predicted_priority, $confidence);
    $stmt->execute();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Notification — AlertIQ</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#0A0D16;
    --gold:#C9A227;
    --gold-light:#E8C468;
    --card:#FFFFFF;
    --border:#E2E4EB;
    --text:#14151F;
    --muted:#767889;
    --critical:#E74C3C;
    --important:#E67E22;
    --normal:#3B82F6;
    --low:#9AA0AC;
  }
  *{box-sizing:border-box;}
  html, body{ height:100%; }
  body{
    margin:0;
    min-height:100vh;
    background-color:#EDEFF5;
    background-image:
      radial-gradient(circle at 6% -5%, rgba(59,130,246,0.13) 0%, transparent 36%),
      radial-gradient(circle at 100% 8%, rgba(201,162,39,0.14) 0%, transparent 40%),
      radial-gradient(circle at 45% 105%, rgba(27,42,74,0.08) 0%, transparent 42%);
    background-attachment:fixed;
    background-repeat:no-repeat;
    font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
    color:var(--text);
  }
  header{
    background: linear-gradient(120deg, #0A1128 0%, #16213E 40%, #1F2E52 65%, #4A3A12 100%);
    border-bottom:2px solid var(--gold);
    padding:20px 28px;
    display:flex;
    align-items:center;
    gap:9px;
    box-shadow:0 8px 24px rgba(11,14,26,0.3);
  }
  .brand-dot{
    width:10px; height:10px; border-radius:50%;
    background:var(--gold-light);
    box-shadow:0 0 0 4px rgba(232,196,104,0.25);
  }
  .brand-name{
    font-family:'Manrope', sans-serif;
    font-weight:800;
    font-size:19px;
    color:#fff;
    letter-spacing:-0.3px;
  }

  main{ max-width:480px; margin:0 auto; padding:40px 24px 60px; }
  .form-card{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:14px;
    padding:30px 28px;
    box-shadow:0 2px 10px rgba(20,21,31,0.06);
  }
  .page-title{
    font-family:'Manrope', sans-serif;
    font-weight:800;
    font-size:22px;
    letter-spacing:-0.3px;
    margin:0 0 4px;
  }
  .page-sub{ font-size:13.5px; color:var(--muted); margin:0 0 24px; }

  label{
    display:block;
    font-size:12.5px;
    font-weight:600;
    color:var(--text);
    margin-bottom:6px;
    margin-top:16px;
  }
  label:first-of-type{ margin-top:0; }
  select, input[type="text"]{
    width:100%;
    height:42px;
    padding:0 13px;
    font-size:14.5px;
    font-family:inherit;
    color:var(--text);
    border:1.5px solid var(--border);
    border-radius:8px;
    outline:none;
    background:#fff;
    transition:border-color .15s ease, box-shadow .15s ease;
  }
  select:focus, input[type="text"]:focus{
    border-color:var(--normal);
    box-shadow:0 0 0 3px rgba(59,130,246,0.15);
  }

  button{
    width:100%;
    height:44px;
    margin-top:22px;
    background:var(--ink);
    color:var(--gold-light);
    font-family:inherit;
    font-size:14.5px;
    font-weight:600;
    border:1px solid rgba(232,196,104,0.35);
    border-radius:8px;
    cursor:pointer;
    transition:background .15s ease;
  }
  button:hover{ background:#252A45; }

  .result{
    margin-top:22px;
    padding:16px 18px;
    border-radius:10px;
    background:#F4F7FE;
    border-left:5px solid var(--normal);
  }
  .result.Critical{ border-left-color:var(--critical); background:#FDECEA; }
  .result.Important{ border-left-color:var(--important); background:#FDF3E9; }
  .result.Normal{ border-left-color:var(--normal); background:#EEF4FE; }
  .result.Low{ border-left-color:var(--low); background:#F2F3F5; }
  .result p{ margin:0 0 6px; font-size:13.5px; }
  .result .badge{
    display:inline-block;
    font-size:11.5px;
    font-weight:700;
    padding:4px 11px;
    border-radius:20px;
    color:#fff;
    letter-spacing:0.2px;
  }
  .badge.Critical{ background:var(--critical); }
  .badge.Important{ background:var(--important); }
  .badge.Normal{ background:var(--normal); }
  .badge.Low{ background:var(--low); }

  .back-link{
    display:inline-block;
    margin-top:20px;
    font-size:13.5px;
    color:var(--muted);
    text-decoration:none;
  }
  .back-link:hover{ color:var(--text); }
</style>
</head>
<body>
<header>
  <span class="brand-dot"></span>
  <span class="brand-name">AlertIQ</span>
</header>

<main>
  <div class="form-card">
    <h1 class="page-title">Add new notification</h1>
    <p class="page-sub">The AI model will predict its priority automatically.</p>

    <form method="POST">
      <label>Source</label>
      <select name="source">
        <option>Gmail</option>
        <option>WhatsApp</option>
        <option>LinkedIn</option>
        <option>Instagram</option>
        <option>Naukri</option>
        <option>Slack</option>
      </select>

      <label>Sender</label>
      <input type="text" name="sender" placeholder="e.g. Manager" required>

      <label>Message</label>
      <input type="text" name="message" placeholder="e.g. Server is down, urgent fix needed" required>

      <button type="submit">Predict &amp; save</button>
    </form>

    <?php if ($predicted_priority): ?>
      <div class="result <?php echo $predicted_priority; ?>">
        <p><b>Message:</b> <?php echo htmlspecialchars($message_text); ?></p>
        <span class="badge <?php echo $predicted_priority; ?>">AI predicted: <?php echo htmlspecialchars($predicted_priority); ?></span>
      </div>
    <?php endif; ?>

    <a class="back-link" href="dashboard.php">← Back to dashboard</a>
  </div>
</main>
</body>
</html>
<?php $conn->close(); ?>
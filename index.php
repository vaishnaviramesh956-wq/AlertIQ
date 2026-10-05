<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$conn = new mysqli("localhost", "root", "", "alertiq");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['notification_id'])) {
    $notif_id = intval($_POST['notification_id']);
    $corrected = $conn->real_escape_string($_POST['corrected_priority']);
    $stmt = $conn->prepare("INSERT INTO feedback (notification_id, corrected_priority) VALUES (?, ?)");
    $stmt->bind_param("is", $notif_id, $corrected);
    $stmt->execute();
    header("Location: index.php");
    exit;
}

$counts = ["Critical" => 0, "Important" => 0, "Normal" => 0, "Low" => 0];
$countResult = $conn->query("SELECT priority, COUNT(*) as total FROM notifications GROUP BY priority");
while ($row = $countResult->fetch_assoc()) {
    $counts[$row['priority']] = $row['total'];
}
$totalCount = array_sum($counts);

$result = $conn->query("SELECT * FROM notifications ORDER BY FIELD(priority, 'Critical','Important','Normal','Low'), created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — AlertIQ</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#0A0D16;
    --gold:#C9A227;
    --gold-light:#E8C468;
    --card:#FFFFFF;
    --border:#E2E4EB;
    --text:#1C1C22;
    --muted:#767889;
    --critical:#B03A2E;
    --important:#B9701E;
    --normal:#2C5FAE;
    --low:#8C8F9B;
  }
  *{box-sizing:border-box;}
  html, body{ height:100%; }
  body{
    margin:0;
    min-height:100vh;
    background-color:#F2F0EA;
    background-image:
      radial-gradient(circle at 50% -10%, rgba(201,162,39,0.16) 0%, transparent 40%),
      radial-gradient(circle at 100% 100%, rgba(10,17,40,0.06) 0%, transparent 45%),
      radial-gradient(circle at 0% 100%, rgba(10,17,40,0.05) 0%, transparent 40%);
    background-attachment:fixed;
    background-repeat:no-repeat;
    font-family: 'Manrope', system-ui, sans-serif;
    color:var(--text);
  }
  header{
    background: linear-gradient(120deg, #0A1128 0%, #16213E 40%, #1F2E52 65%, #4A3A12 100%);
    border-bottom:2px solid var(--gold);
    padding:22px 30px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    flex-wrap:wrap;
    gap:12px;
    box-shadow:0 8px 24px rgba(11,14,26,0.3);
  }
  .brand{ display:flex; align-items:center; gap:11px; }
  .brand-dot{
    width:9px; height:9px; border-radius:50%;
    background:var(--gold-light);
    box-shadow:0 0 0 4px rgba(232,196,104,0.25);
  }
  .brand-name{
    font-family:'Playfair Display', serif;
    font-weight:700;
    font-size:23px;
    color:#fff;
    letter-spacing:0.3px;
  }
  .user-area{ display:flex; align-items:center; gap:18px; }
  .welcome{ font-size:13px; color:#D9C99A; letter-spacing:0.2px; }
  .logout-btn{
    font-size:12.5px;
    font-weight:600;
    color:#fff;
    background:rgba(255,255,255,0.08);
    border:1px solid rgba(232,196,104,0.4);
    padding:7px 15px;
    border-radius:7px;
    text-decoration:none;
    letter-spacing:0.3px;
    transition:background .15s ease;
  }
  .logout-btn:hover{ background:rgba(232,196,104,0.18); }

  main{ max-width:800px; margin:0 auto; padding:40px 24px 60px; }
  .page-title{
    font-family:'Playfair Display', serif;
    font-weight:700;
    font-size:34px;
    letter-spacing:0.2px;
    margin:0 0 6px;
    color:var(--ink);
  }
  .page-sub{ font-size:14.5px; color:var(--muted); margin:0 0 28px; }

  .stats{
    display:grid;
    grid-template-columns:repeat(4, 1fr);
    gap:16px;
    margin-bottom:32px;
  }
  .stat-card{
    position:relative;
    background:var(--card);
    border:1px solid var(--border);
    border-radius:12px;
    padding:24px 20px 20px;
    overflow:hidden;
    box-shadow:0 3px 12px rgba(20,21,31,0.06);
    transition:transform .15s ease, box-shadow .15s ease;
  }
  .stat-card:hover{
    transform:translateY(-2px);
    box-shadow:0 8px 20px rgba(20,21,31,0.10);
  }
  .stat-card::before{
    content:"";
    position:absolute;
    top:0; left:0; right:0;
    height:3px;
  }
  .stat-card.Critical::before{ background:var(--critical); }
  .stat-card.Important::before{ background:var(--important); }
  .stat-card.Normal::before{ background:var(--normal); }
  .stat-card.Low::before{ background:var(--low); }
  .stat-card.Critical{ background:linear-gradient(180deg, rgba(176,58,46,0.05) 0%, #fff 55%); }
  .stat-card.Important{ background:linear-gradient(180deg, rgba(185,112,30,0.05) 0%, #fff 55%); }
  .stat-card.Normal{ background:linear-gradient(180deg, rgba(44,95,174,0.05) 0%, #fff 55%); }
  .stat-card.Low{ background:linear-gradient(180deg, rgba(140,143,155,0.06) 0%, #fff 55%); }

  .stat-count{
    font-family:'Playfair Display', serif;
    font-weight:700;
    font-size:42px;
    line-height:1;
    margin-bottom:9px;
  }
  .stat-card.Critical .stat-count{ color:var(--critical); }
  .stat-card.Important .stat-count{ color:var(--important); }
  .stat-card.Normal .stat-count{ color:var(--normal); }
  .stat-card.Low .stat-count{ color:var(--low); }
  .stat-label{
    font-size:11.5px;
    color:var(--muted);
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:1.2px;
  }

  .add-btn{
    display:inline-flex;
    align-items:center;
    gap:8px;
    background:var(--ink);
    color:var(--gold-light);
    text-decoration:none;
    font-size:13.5px;
    font-weight:600;
    letter-spacing:0.3px;
    padding:12px 20px;
    border-radius:8px;
    margin-bottom:28px;
    border:1px solid rgba(232,196,104,0.35);
    transition:background .15s ease;
  }
  .add-btn:hover{ background:#252A45; }

  .card{
    background:var(--card);
    border:1px solid var(--border);
    border-left:5px solid var(--low);
    border-radius:10px;
    padding:20px 22px;
    margin-bottom:14px;
    box-shadow:0 2px 8px rgba(20,21,31,0.05);
  }
  .card.Critical{ border-left-color:var(--critical); }
  .card.Important{ border-left-color:var(--important); }
  .card.Normal{ border-left-color:var(--normal); }
  .card.Low{ border-left-color:var(--low); }

  .card-top{ display:flex; align-items:center; gap:9px; margin-bottom:11px; }
  .badge{
    font-size:11px;
    font-weight:700;
    padding:4px 12px;
    border-radius:20px;
    color:#fff;
    letter-spacing:0.5px;
    text-transform:uppercase;
  }
  .badge.Critical{ background:var(--critical); }
  .badge.Important{ background:var(--important); }
  .badge.Normal{ background:var(--normal); }
  .badge.Low{ background:var(--low); }
  .source{ font-size:12.5px; color:var(--muted); font-weight:600; }

  .msg{
    font-family:'Manrope', system-ui, sans-serif;
    font-size:15.5px;
    line-height:1.55;
    margin:0 0 15px;
    color:var(--text);
  }
  .msg b{ font-weight:700; }

  .fb{
    display:flex;
    align-items:center;
    gap:9px;
    flex-wrap:wrap;
    padding-top:13px;
    border-top:1px solid var(--border);
  }
  .fb-label{ font-size:12.5px; color:var(--muted); }
  .fb select{
    font-size:13px;
    padding:6px 9px;
    border:1px solid var(--border);
    border-radius:6px;
    color:var(--text);
    background:#fff;
    font-family:inherit;
  }
  .fb button{
    font-size:12.5px;
    font-weight:600;
    color:#fff;
    background:var(--ink);
    border:none;
    padding:7px 14px;
    border-radius:6px;
    cursor:pointer;
    font-family:inherit;
  }
  .fb button:hover{ background:#252A45; }

  @media (max-width:560px){
    .stats{ grid-template-columns:repeat(2, 1fr); }
    .page-title{ font-size:28px; }
  }
</style>
</head>
<body>
<header>
  <div class="brand">
    <span class="brand-dot"></span>
    <span class="brand-name">AlertIQ</span>
  </div>
  <div class="user-area">
    <span class="welcome">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
    <a class="logout-btn" href="logout.php">Logout</a>
  </div>
</header>

<main>
  <h1 class="page-title">Dashboard</h1>
  <p class="page-sub">Notifications sorted by priority, powered by AI</p>

  <div class="stats">
    <div class="stat-card Critical">
      <div class="stat-count"><?php echo $counts['Critical']; ?></div>
      <div class="stat-label">Critical</div>
    </div>
    <div class="stat-card Important">
      <div class="stat-count"><?php echo $counts['Important']; ?></div>
      <div class="stat-label">Important</div>
    </div>
    <div class="stat-card Normal">
      <div class="stat-count"><?php echo $counts['Normal']; ?></div>
      <div class="stat-label">Normal</div>
    </div>
    <div class="stat-card Low">
      <div class="stat-count"><?php echo $counts['Low']; ?></div>
      <div class="stat-label">Low</div>
    </div>
  </div>

  <a class="add-btn" href="add_notification.php">+ Add new notification</a>

  <?php while ($row = $result->fetch_assoc()): ?>
    <div class="card <?php echo $row['priority']; ?>">
      <div class="card-top">
        <span class="badge <?php echo $row['priority']; ?>"><?php echo $row['priority']; ?></span>
        <span class="source"><?php echo htmlspecialchars($row['source']); ?></span>
      </div>
      <p class="msg"><b><?php echo htmlspecialchars($row['sender']); ?>:</b> <?php echo htmlspecialchars($row['message']); ?></p>
      <form method="POST" class="fb">
        <input type="hidden" name="notification_id" value="<?php echo $row['id']; ?>">
        <span class="fb-label">Not correct? Actual priority:</span>
        <select name="corrected_priority">
          <option>Critical</option>
          <option>Important</option>
          <option>Normal</option>
          <option>Low</option>
        </select>
        <button type="submit">Submit</button>
      </form>
    </div>
  <?php endwhile; ?>
</main>
</body>
</html>
<?php $conn->close(); ?>
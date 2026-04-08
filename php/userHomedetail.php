<?php
include "Server.php";

if(!isset($_GET['shop_id'])){
    die("Shop not found");
}

$shop_id = $_GET['shop_id'];

$stmt = $conn->prepare("SELECT * FROM shop WHERE SHOP_ID=?");
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0){
    die("Invalid Shop");
}

$shop = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($shop['NAME']); ?></title>

<style>
:root {
    --black:#0a0806;
    --dark:#111009;
    --dark2:#1a1712;
    --gold:#c9a84c;
    --gold2:#e8c96a;
    --text:#d4cfc6;
    --muted:#7a756c;
    --border:rgba(201,168,76,0.15);
}

/* reset */
*{margin:0;padding:0;box-sizing:border-box;}
body{
    font-family: system-ui;
    background: radial-gradient(circle at top, #1a1712, #0a0806);
    color:var(--text);
}

/* back button */
.back-btn{
    position:fixed;
    top:20px; left:20px;
    width:42px;height:42px;
    border-radius:50%;
    background:rgba(0,0,0,0.4);
    display:flex;align-items:center;justify-content:center;
    border:1px solid var(--border);
    color:white;
    transition:.2s;
}
.back-btn:hover{
    background:var(--gold);
    color:black;
}
.back-btn svg{width:18px}

/* container */
.container{
    max-width:1200px;
    margin:auto;
    padding:40px 20px;
}

/* card */
.card{
    background:rgba(17,16,9,0.8);
    border:1px solid var(--border);
    border-radius:20px;
    overflow:hidden;
    backdrop-filter:blur(10px);
    transition:.3s;
}
.card:hover{
    transform:scale(1.01);
}

/* banner */
.banner{
    height:350px;
    background-size:cover;
    background-position:center;
    position:relative;
}
.banner::after{
    content:'';
    position:absolute; inset:0;
    background:linear-gradient(to bottom,transparent,rgba(0,0,0,0.8));
}

/* content */
.content{
    padding:30px;
}

/* title */
h1{
    font-size:2.5rem;
    margin-bottom:10px;
    color:var(--gold2);
}

/* description */
.desc{
    margin:20px 0;
    color:var(--muted);
}

/* divider */
.divider{
    height:1px;
    background:linear-gradient(to right,transparent,var(--gold),transparent);
    margin:20px 0;
}

/* grid */
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:20px;
}

/* info card */
.info{
    padding:15px;
    border-radius:12px;
    background:rgba(255,255,255,0.02);
    border:1px solid var(--border);
    transition:.2s;
}
.info:hover{
    transform:translateY(-3px);
}

/* icon row */
.info-header{
    display:flex;
    align-items:center;
    gap:10px;
    margin-bottom:5px;
}

.icon{
    width:30px;height:30px;
    display:flex;align-items:center;justify-content:center;
    background:rgba(201,168,76,0.1);
    border-radius:6px;
}

.icon svg{
    width:16px;
    stroke:var(--gold);
    fill:none;
}

/* button */
.btn{
    margin-top:25px;
    padding:14px 30px;
    border:none;
    border-radius:30px;
    background:linear-gradient(120deg,var(--gold),var(--gold2));
    color:black;
    font-weight:bold;
    cursor:pointer;
    position:relative;
    overflow:hidden;
}
.btn::after{
    content:'';
    position:absolute;
    inset:0;
    background:linear-gradient(120deg,transparent,rgba(255,255,255,0.4),transparent);
    transform:translateX(-100%);
}
.btn:hover::after{
    transform:translateX(100%);
    transition:.5s;
}

/* seat badge */
.badge{
    background:var(--dark2);
    padding:6px 12px;
    border-radius:20px;
    border:1px solid var(--border);
    display:inline-block;
    margin-top:10px;
}
</style>
</head>

<body>

<a href="javascript:history.back()" class="back-btn">
<svg viewBox="0 0 20 20">
<path d="M13 15l-4-4 4-4" stroke="currentColor" stroke-width="1.5"/>
</svg>
</a>

<div class="container">
<div class="card">

<div class="banner" style="background-image:url('<?php echo htmlspecialchars($shop['IMAGES']); ?>')"></div>

<div class="content">

<h1><?php echo htmlspecialchars($shop['NAME']); ?></h1>

<div class="badge">
🪑 <?php echo $shop['SEAT_NO']; ?> seats
</div>

<p class="desc">
<?php echo nl2br(htmlspecialchars($shop['DESCRIPTION'])); ?>
</p>

<div class="divider"></div>

<div class="grid">

<div class="info">
<div class="info-header">
<div class="icon">
<svg viewBox="0 0 20 20">
<path d="M10 2a6 6 0 016 6c0 4-6 10-6 10S4 12 4 8a6 6 0 016-6z"/>
</svg>
</div>
<strong>Location</strong>
</div>
<?php echo htmlspecialchars($shop['LOCATION']); ?>
</div>

<div class="info">
<div class="info-header">
<div class="icon">
<svg viewBox="0 0 20 20">
<path d="M2 5a2 2 0 012-2h2l2 5-2 1a11 11 0 005 5l1-2 5 2v2a2 2 0 01-2 2h-1C7 18 2 13 2 6V5z"/>
</svg>
</div>
<strong>Contact</strong>
</div>
<?php echo htmlspecialchars($shop['CONTACT']); ?>
</div>

<div class="info">
<div class="info-header">
<div class="icon">
<svg viewBox="0 0 20 20">
<path d="M3 10h14M10 3v14"/>
</svg>
</div>
<strong>Availability</strong>
</div>
<?php echo $shop['SEAT_NO']; ?> seats available
</div>

</div>

<button class="btn"
onclick="location.href='booking.php?shop_id=<?php echo $shop_id; ?>'">
Reserve Now →
</button>

</div>
</div>
</div>

</body>
</html>
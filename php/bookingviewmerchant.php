<?php
session_start();
include "Server.php";

if(!isset($_SESSION['user_id']) || !isset($_SESSION['SHOP_ID'])){
    die("You must be logged in and have a shop to view bookings.");
}

$shop_id  = $_SESSION['SHOP_ID'];
$message  = "";
$msg_type = "";

/* ================= DELETE BOOKING ================= */
if(isset($_POST['delete_booking'])){
    $booking_id = $_POST['booking_id'];
    $stmt = $conn->prepare("DELETE FROM booking WHERE BOOKING_ID=? AND SHOP_ID=?");
    $stmt->bind_param("ii", $booking_id, $shop_id);
    $stmt->execute();
    if($stmt->affected_rows > 0){
        $message  = "Booking removed successfully.";
        $msg_type = "success";
    } else {
        $message  = "Could not remove booking.";
        $msg_type = "danger";
    }
}

/* ================= FETCH BOOKINGS ================= */
$stmt = $conn->prepare("
    SELECT b.BOOKING_ID, u.USERNAME, b.DATE, b.TIME, b.TOTAL
    FROM   booking b
    JOIN   users u ON b.USERS_ID = u.USERS_ID
    WHERE  b.SHOP_ID = ?
    ORDER  BY b.DATE DESC, b.TIME ASC
");
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$result   = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
$today    = date("Y-m-d");

/* ── quick stats ── */
$total_revenue = array_sum(array_column($bookings, 'TOTAL'));
$upcoming      = count(array_filter($bookings, fn($b) => $b['DATE'] >= $today));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shop Bookings</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:      #080b12;
    --surface: #0e1420;
    --surface2:#141928;
    --border:  rgba(255,255,255,0.07);
    --border2: rgba(255,255,255,0.12);
    --accent:  #4f8fff;
    --accent2: #7c5cfc;
    --green:   #22c97a;
    --red:     #ff4f6a;
    --amber:   #f59e0b;
    --text:    #e8eaf0;
    --muted:   #6b7280;
}

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    overflow-x: hidden;
}

body::-webkit-scrollbar {
    display: none;
}

body::before {
    content: '';
    position: fixed; inset: 0;
    background-image:
        linear-gradient(rgba(79,143,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(79,143,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none; z-index: 0;
}
body::after {
    content: '';
    position: fixed;
    top: -180px; right: -180px;
    width: 520px; height: 520px;
    background: radial-gradient(circle, rgba(124,92,252,0.07) 0%, transparent 70%);
    pointer-events: none; z-index: 0;
}

.page-wrapper {
    position: relative; z-index: 1;
    max-width: 980px;
    margin: 0 auto;
    padding: 48px 24px 80px;
}

/* ── Header ── */
.page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 36px;
    padding-bottom: 28px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 16px;
}
.page-header h1 {
    font-family: 'Syne', sans-serif;
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: -0.5px;
    background: linear-gradient(135deg, #fff 30%, #7caaff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1.15;
}
.page-header p { font-size: 0.85rem; color: var(--muted); margin-top: 6px; font-weight: 300; }
.booking-count {
    font-family: 'Syne', sans-serif;
    font-size: 0.72rem; font-weight: 700;
    letter-spacing: 1px; text-transform: uppercase;
    padding: 6px 14px; border-radius: 99px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    color: #fff; white-space: nowrap;
}

/* ── Flash ── */
.flash {
    display: flex; align-items: center; gap: 10px;
    padding: 13px 18px; border-radius: 10px;
    font-size: 0.875rem; font-weight: 500;
    margin-bottom: 28px;
    animation: slideDown 0.35s ease;
}
.flash.success { background: rgba(34,201,122,0.1); border: 1px solid rgba(34,201,122,0.25); color: #5ee8a6; }
.flash.danger  { background: rgba(255,79,106,0.1);  border: 1px solid rgba(255,79,106,0.25);  color: #ff8fa1; }
.flash-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.flash.success .flash-dot { background: var(--green); }
.flash.danger  .flash-dot { background: var(--red); }

/* ── Stat cards ── */
.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}
.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px 22px;
    position: relative;
    overflow: hidden;
    animation: fadeIn 0.4s ease both;
}
.stat-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
}
.stat-card:nth-child(2) { animation-delay: 0.06s; }
.stat-card:nth-child(3) { animation-delay: 0.12s; }
.stat-label {
    font-size: 0.68rem; font-weight: 600;
    letter-spacing: 1.2px; text-transform: uppercase;
    color: var(--muted); margin-bottom: 10px;
}
.stat-value {
    font-family: 'Syne', sans-serif;
    font-size: 1.6rem; font-weight: 800;
    line-height: 1;
}
.stat-value.green { color: var(--green); }
.stat-value.blue  { color: #7caaff; }
.stat-value.amber { color: #fbbf24; }
.stat-sub { font-size: 0.75rem; color: var(--muted); margin-top: 5px; font-weight: 300; }

/* ── Table card ── */
.card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    position: relative;
    animation: fadeIn 0.4s ease 0.15s both;
}
.card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
}
.card-header {
    padding: 18px 22px 14px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
}
.card-title {
    font-family: 'Syne', sans-serif;
    font-size: 0.78rem; font-weight: 700;
    letter-spacing: 1.4px; text-transform: uppercase;
    color: var(--muted);
}

/* ── Table ── */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
thead tr { border-bottom: 1px solid var(--border2); }
thead th {
    padding: 12px 18px;
    text-align: left;
    font-size: 0.67rem; font-weight: 600;
    letter-spacing: 1.3px; text-transform: uppercase;
    color: var(--muted);
}
thead th.right { text-align: right; }
tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,0.025); }
tbody td { padding: 14px 18px; font-size: 0.88rem; vertical-align: middle; }
tbody td.right { text-align: right; }

/* ── ID badge ── */
.id-badge {
    font-family: 'Syne', sans-serif;
    font-size: 0.7rem; font-weight: 700;
    color: var(--muted);
    background: var(--surface2);
    border: 1px solid var(--border2);
    padding: 2px 8px; border-radius: 6px;
}

/* ── User cell ── */
.user-cell { display: flex; align-items: center; gap: 10px; }
.user-avatar {
    width: 32px; height: 32px; border-radius: 8px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    display: flex; align-items: center; justify-content: center;
    font-family: 'Syne', sans-serif; font-size: 0.7rem; font-weight: 700;
    color: #fff; flex-shrink: 0; text-transform: uppercase;
}
.user-name { font-weight: 500; }

/* ── Date & time ── */
.date-text { font-weight: 500; }
.date-past     { color: var(--muted); }
.date-today    { color: var(--amber); }
.date-upcoming { color: var(--text); }

.time-pill {
    display: inline-block;
    background: var(--surface2);
    border: 1px solid var(--border2);
    padding: 3px 10px; border-radius: 99px;
    font-size: 0.78rem; font-weight: 500; color: var(--muted);
}

.status-badge {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 0.68rem; font-weight: 600; letter-spacing: 0.4px;
    padding: 3px 9px; border-radius: 99px;
}
.status-past     { background: rgba(107,114,128,0.15); border: 1px solid rgba(107,114,128,0.2); color: var(--muted); }
.status-today    { background: rgba(245,158,11,0.12);  border: 1px solid rgba(245,158,11,0.25);  color: #fbbf24; }
.status-upcoming { background: rgba(79,143,255,0.1);   border: 1px solid rgba(79,143,255,0.2);   color: #7caaff; }

/* ── Total ── */
.total-val {
    font-family: 'Syne', sans-serif;
    font-size: 0.9rem; font-weight: 700; color: var(--green);
}

/* ── Delete btn ── */
.btn-delete {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 13px;
    background: rgba(255,79,106,0.1);
    border: 1px solid rgba(255,79,106,0.2);
    border-radius: 8px;
    color: #ff8fa1;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.8rem; font-weight: 500;
    cursor: pointer;
    transition: background 0.15s, transform 0.12s;
    white-space: nowrap;
}
.btn-delete:hover { background: rgba(255,79,106,0.18); transform: translateY(-1px); }
.btn-delete:active { transform: translateY(0); }

/* ── Empty state ── */
.empty-state { padding: 64px 24px; text-align: center; }
.empty-icon {
    width: 56px; height: 56px;
    background: var(--surface2);
    border: 1px solid var(--border2);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px; font-size: 1.5rem;
}
.empty-state h3 { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 700; margin-bottom: 6px; }
.empty-state p  { font-size: 0.85rem; color: var(--muted); font-weight: 300; }

/* ── Animations ── */
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

@media (max-width: 600px) {
    .stats-row { grid-template-columns: 1fr 1fr; }
    .stats-row .stat-card:last-child { grid-column: span 2; }
}
</style>
</head>
<body>
<div class="page-wrapper">

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1>Shop Bookings</h1>
            <p>All appointments</p>
        </div>
        <span class="booking-count">
            <?php echo count($bookings); ?> booking<?php echo count($bookings) !== 1 ? 's' : ''; ?>
        </span>
    </div>

    <!-- Flash -->
    <?php if(!empty($message)): ?>
    <div class="flash <?php echo $msg_type; ?>">
        <span class="flash-dot"></span>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- Stats row -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Total bookings</div>
            <div class="stat-value blue"><?php echo count($bookings); ?></div>
            <div class="stat-sub">all time</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Revenue</div>
            <div class="stat-value green">Rs. <?php echo number_format($total_revenue, 0); ?></div>
            <div class="stat-sub">all time</div>
        </div>
    </div>

    <!-- Table card -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Booking records</span>
        </div>

        <?php if(empty($bookings)): ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <h3>No bookings yet</h3>
            <p>When customers book your shop, they'll appear here.</p>
        </div>
        <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th class="right">Total</th>
                    <th class="right">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($bookings as $row):
                $d = $row['DATE'];
                if($d < $today)       { $dateCls = 'date-past';     $badgeCls = 'status-past';     $badgeTxt = 'Past'; }
                elseif($d === $today) { $dateCls = 'date-today';    $badgeCls = 'status-today';    $badgeTxt = 'Today'; }
                else                  { $dateCls = 'date-upcoming'; $badgeCls = 'status-upcoming'; $badgeTxt = 'Upcoming'; }
                $initials = strtoupper(substr($row['USERNAME'], 0, 2));
            ?>
            <tr>
                <td><span class="id-badge">#<?php echo $row['BOOKING_ID']; ?></span></td>
                <td>
                    <div class="user-cell">
                        <div class="user-avatar"><?php echo $initials; ?></div>
                        <span class="user-name"><?php echo htmlspecialchars($row['USERNAME']); ?></span>
                    </div>
                </td>
                <td><span class="date-text <?php echo $dateCls; ?>"><?php echo date("M j, Y", strtotime($d)); ?></span></td>
                <td><span class="time-pill"><?php echo date("h:i A", strtotime($row['TIME'])); ?></span></td>
                <td><span class="status-badge <?php echo $badgeCls; ?>"><?php echo $badgeTxt; ?></span></td>
                <td class="right"><span class="total-val">Rs. <?php echo number_format($row['TOTAL'], 2); ?></span></td>
                <td class="right">
                    <form method="POST" onsubmit="return confirm('Remove this booking?');">
                        <input type="hidden" name="booking_id" value="<?php echo $row['BOOKING_ID']; ?>">
                        <button type="submit" name="delete_booking" class="btn-delete">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path d="M1.5 3h9M4.5 3V2h3v1M4 3l.4 6.5h3.2L8 3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Remove
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
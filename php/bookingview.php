<?php
include "Server.php";
session_start();

$user_id  = $_SESSION['user_id'];
$message  = "";
$msg_type = "";

/* ── WAL helpers ─────────────────────────────────────────────── */
function wal_write(mysqli $conn, string $txn_id, string $operation, array $payload): int {
    $json = json_encode($payload);
    $stmt = $conn->prepare("
        INSERT INTO wal_log (TXN_ID, OPERATION, PAYLOAD, STATUS)
        VALUES (?, ?, ?, 'PENDING')
    ");
    $stmt->bind_param("sss", $txn_id, $operation, $json);
    $stmt->execute();
    return (int) $conn->insert_id;
}

function wal_resolve(mysqli $conn, int $wal_id, string $status): void {
    $stmt = $conn->prepare("
        UPDATE wal_log SET STATUS = ?, RESOLVED_AT = NOW()
        WHERE WAL_ID = ?
    ");
    $stmt->bind_param("si", $status, $wal_id);
    $stmt->execute();
}

/* ================= DELETE BOOKING (WAL-backed) ================= */
if(isset($_POST['delete_booking'])){
    $booking_id = (int)$_POST['booking_id'];
    $wal_id     = null;

    // 1. Fetch full booking details BEFORE deleting
    //    (need username, shop info, merchant — all go into WAL payload)
    $fetch = $conn->prepare("
        SELECT b.BOOKING_ID, b.SHOP_ID, b.DATE, b.TIME, b.TOTAL,
               u.USERNAME, u.USERS_ID,
               s.USERS_ID AS MERCHANT_ID, s.NAME AS SHOP_NAME
        FROM   booking b
        JOIN   users u ON b.USERS_ID = u.USERS_ID
        JOIN   shop  s ON b.SHOP_ID  = s.SHOP_ID
        WHERE  b.BOOKING_ID = ? AND b.USERS_ID = ?
    ");
    $fetch->bind_param("ii", $booking_id, $user_id);
    $fetch->execute();
    $booking_info = $fetch->get_result()->fetch_assoc();

    if(!$booking_info){
        $message  = "Booking not found.";
        $msg_type = "danger";
    } else {
        try {
            $conn->begin_transaction();

            // 2. Write WAL entry as PENDING before touching booking table
            $txn_id = uniqid('txn_del_', true);
            $wal_id = wal_write($conn, $txn_id, 'DELETE_BOOKING', [
                'booking_id'  => $booking_info['BOOKING_ID'],
                'user_id'     => $booking_info['USERS_ID'],
                'username'    => $booking_info['USERNAME'],
                'shop_id'     => $booking_info['SHOP_ID'],
                'shop_name'   => $booking_info['SHOP_NAME'],
                'merchant_id' => $booking_info['MERCHANT_ID'],
                'date'        => $booking_info['DATE'],
                'time'        => $booking_info['TIME'],
                'total'       => $booking_info['TOTAL'],
            ]);

            // 3. Delete the booking row
            $delete = $conn->prepare("DELETE FROM booking WHERE BOOKING_ID = ? AND USERS_ID = ?");
            $delete->bind_param("ii", $booking_id, $user_id);
            $delete->execute();

            if($delete->affected_rows > 0){
                // 4. Resolve WAL as COMMITTED
                //    notification.php reads this directly — no notifications table needed
                wal_resolve($conn, $wal_id, 'COMMITTED');
                $conn->commit();
                $message  = "Booking cancelled successfully.";
                $msg_type = "success";
            } else {
                wal_resolve($conn, $wal_id, 'ROLLED_BACK');
                $conn->rollback();
                $message  = "Could not cancel booking.";
                $msg_type = "danger";
            }

        } catch(Exception $e){
            if($wal_id !== null) wal_resolve($conn, $wal_id, 'ROLLED_BACK');
            $conn->rollback();
            $message  = "Cancellation failed. Please try again.";
            $msg_type = "danger";
        }
    }
}

/* ================= FETCH BOOKINGS ================= */
$sql = $conn->prepare("
    SELECT b.*, s.NAME AS SHOP_NAME
    FROM booking b
    JOIN shop s ON b.SHOP_ID = s.SHOP_ID
    WHERE b.USERS_ID = ?
    ORDER BY b.DATE, b.TIME
");
$sql->bind_param("i", $user_id);
$sql->execute();
$bookings = $sql->get_result()->fetch_all(MYSQLI_ASSOC);
$today    = date("Y-m-d");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Bookings</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:       #080b12;
    --surface:  #0e1420;
    --surface2: #141928;
    --border:   rgba(255,255,255,0.07);
    --border2:  rgba(255,255,255,0.12);
    --accent:   #4f8fff;
    --accent2:  #7c5cfc;
    --green:    #22c97a;
    --red:      #ff4f6a;
    --amber:    #f59e0b;
    --text:     #e8eaf0;
    --muted:    #6b7280;
}

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    overflow-x: hidden;
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
    bottom: -200px; right: -150px;
    width: 550px; height: 550px;
    background: radial-gradient(circle, rgba(79,143,255,0.07) 0%, transparent 70%);
    pointer-events: none; z-index: 0;
}

.page-wrapper {
    position: relative; z-index: 1;
    max-width: 900px;
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
.page-header p {
    font-size: 0.85rem;
    color: var(--muted);
    margin-top: 6px;
    font-weight: 300;
}
.booking-count {
    font-family: 'Syne', sans-serif;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: 6px 14px;
    border-radius: 99px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    color: #fff;
    white-space: nowrap;
}

/* ── Flash ── */
.flash {
    display: flex; align-items: center; gap: 10px;
    padding: 13px 18px;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 28px;
    animation: slideDown 0.35s ease;
}
.flash.success { background: rgba(34,201,122,0.1); border: 1px solid rgba(34,201,122,0.25); color: #5ee8a6; }
.flash.danger  { background: rgba(255,79,106,0.1);  border: 1px solid rgba(255,79,106,0.25);  color: #ff8fa1; }
.flash-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.flash.success .flash-dot { background: var(--green); }
.flash.danger  .flash-dot { background: var(--red); }

/* ── Card ── */
.card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    position: relative;
    animation: fadeIn 0.4s ease both;
}
.card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
}

/* ── Table ── */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }

thead tr { border-bottom: 1px solid var(--border2); }
thead th {
    padding: 14px 18px;
    text-align: left;
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 1.3px;
    text-transform: uppercase;
    color: var(--muted);
}
thead th:last-child { text-align: right; }

tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background 0.15s;
}
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,0.025); }

tbody td {
    padding: 16px 18px;
    font-size: 0.9rem;
    vertical-align: middle;
}
tbody td:last-child { text-align: right; }

/* ── Shop name cell ── */
.shop-cell { display: flex; align-items: center; gap: 10px; }
.shop-avatar {
    width: 34px; height: 34px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    display: flex; align-items: center; justify-content: center;
    font-family: 'Syne', sans-serif;
    font-size: 0.75rem;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
}
.shop-name { font-weight: 500; color: var(--text); }

/* ── Date & Time ── */
.date-text { font-weight: 500; }
.date-past     { color: var(--muted); }
.date-today    { color: var(--amber); }
.date-upcoming { color: var(--text); }

.time-pill {
    display: inline-block;
    background: var(--surface2);
    border: 1px solid var(--border2);
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 0.78rem;
    font-weight: 500;
    color: var(--muted);
}

/* ── Total ── */
.total-amount {
    font-family: 'Syne', sans-serif;
    font-weight: 700;
    font-size: 0.92rem;
    color: var(--green);
}
.total-label { font-size: 0.7rem; color: var(--muted); }

/* ── Status badge ── */
.status-badge {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 0.7rem; font-weight: 600;
    letter-spacing: 0.5px;
    padding: 3px 9px;
    border-radius: 99px;
}
.status-past     { background: rgba(107,114,128,0.15); border: 1px solid rgba(107,114,128,0.2); color: var(--muted); }
.status-today    { background: rgba(245,158,11,0.12);  border: 1px solid rgba(245,158,11,0.25);  color: #fbbf24; }
.status-upcoming { background: rgba(79,143,255,0.1);   border: 1px solid rgba(79,143,255,0.2);   color: #7caaff; }

/* ── Delete button ── */
.btn-delete {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px;
    background: rgba(255,79,106,0.1);
    border: 1px solid rgba(255,79,106,0.2);
    border-radius: 8px;
    color: #ff8fa1;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s, transform 0.12s;
}
.btn-delete:hover  { background: rgba(255,79,106,0.18); transform: translateY(-1px); }
.btn-delete:active { transform: translateY(0); }

/* ── Empty state ── */
.empty-state {
    padding: 64px 24px;
    text-align: center;
}
.empty-icon {
    width: 56px; height: 56px;
    background: var(--surface2);
    border: 1px solid var(--border2);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
}
.empty-state h3 {
    font-family: 'Syne', sans-serif;
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 6px;
}
.empty-state p { font-size: 0.85rem; color: var(--muted); font-weight: 300; }

/* ── Animations ── */
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
</head>
<body>

<button onclick="window.location.href='userHome.php'"
        style="position:fixed; top:20px; left:20px; background:none; border:none; color:var(--text); cursor:pointer;">
    <svg width="14" height="14" viewBox="0 0 10 10" fill="none">
        <path d="M6.5 1.5L3 5l3.5 3.5"
              stroke="currentColor" stroke-width="1.5"
              stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</button>

<div class="page-wrapper">

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1>My Bookings</h1>
            <p>Track and manage your appointments</p>
        </div>
        <span class="booking-count">
            <?php echo count($bookings); ?> appointment<?php echo count($bookings) !== 1 ? 's' : ''; ?>
        </span>
    </div>

    <!-- Flash -->
    <?php if(!empty($message)): ?>
    <div class="flash <?php echo $msg_type; ?>">
        <span class="flash-dot"></span>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- Table Card -->
    <div class="card">
        <?php if(empty($bookings)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <svg width="24" height="24" viewBox="0 0 80 80" fill="none" style="color:var(--muted)">
                    <rect x="10" y="18" width="60" height="52" rx="8" stroke="currentColor" stroke-width="4"/>
                    <path d="M25 18V10M55 18V10" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                    <path d="M10 34h60" stroke="currentColor" stroke-width="4"/>
                    <path d="M26 50h28M26 62h18" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                </svg>
            </div>
            <h3>No appointments yet</h3>
            <p>Your upcoming bookings will appear here.</p>
        </div>
        <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Shop</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($bookings as $row):
                $d = $row['DATE'];
                if($d < $today)       { $dateCls = 'date-past';     $badgeCls = 'status-past';     $badgeTxt = 'Past'; }
                elseif($d === $today) { $dateCls = 'date-today';    $badgeCls = 'status-today';    $badgeTxt = 'Today'; }
                else                  { $dateCls = 'date-upcoming'; $badgeCls = 'status-upcoming'; $badgeTxt = 'Upcoming'; }

                $initials      = strtoupper(substr($row['SHOP_NAME'], 0, 2));
                $formattedDate = date("M j, Y", strtotime($d));
            ?>
            <tr>
                <td>
                    <div class="shop-cell">
                        <div class="shop-avatar"><?php echo $initials; ?></div>
                        <span class="shop-name"><?php echo htmlspecialchars($row['SHOP_NAME']); ?></span>
                    </div>
                </td>
                <td>
                    <span class="date-text <?php echo $dateCls; ?>"><?php echo $formattedDate; ?></span>
                </td>
                <td>
                    <span class="time-pill"><?php echo date("h:i A", strtotime($row['TIME'])); ?></span>
                </td>
                <td>
                    <div class="total-label">Total</div>
                    <div class="total-amount">Rs. <?php echo number_format($row['TOTAL'], 2); ?></div>
                </td>
                <td>
                    <span class="status-badge <?php echo $badgeCls; ?>">
                        <?php echo $badgeTxt; ?>
                    </span>
                </td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="booking_id" value="<?php echo $row['BOOKING_ID']; ?>">
                        <button type="submit" name="delete_booking" class="btn-delete"
                                onclick="return confirm('Cancel this booking?');">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path d="M1.5 3h9M4.5 3V2h3v1M4 3l.4 6.5h3.2L8 3"
                                      stroke="currentColor" stroke-width="1.3"
                                      stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Cancel
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
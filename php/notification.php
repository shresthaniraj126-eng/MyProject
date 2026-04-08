<?php
include "Server.php";
session_start();

function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return "Just now";
    if ($diff < 3600)   return floor($diff / 60)   . "m ago";
    if ($diff < 86400)  return floor($diff / 3600)  . "h ago";
    if ($diff < 604800) return floor($diff / 86400) . "d ago";
    return date("M j, Y", strtotime($datetime));
}

$merchant_id = (int)$_SESSION['user_id'];
$today       = date("Y-m-d");

/* ── DEBUG collector ── */
$debug = [];
$debug['merchant_id'] = $merchant_id;
$debug['today']       = $today;

/* ══════════════════════════════════════════════════════
   Step 1 — Get all shop IDs owned by this merchant
   ══════════════════════════════════════════════════════ */
$shop_stmt = $conn->prepare("SELECT SHOP_ID FROM shop WHERE USERS_ID = ?");
$shop_stmt->bind_param("i", $merchant_id);
$shop_stmt->execute();
$shop_rows   = $shop_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$shop_stmt->close();

$my_shop_ids = array_column($shop_rows, 'SHOP_ID');
$debug['my_shop_ids'] = $my_shop_ids;

/* ══════════════════════════════════════════════════════
   Step 2 — Fetch WAL entries
   FIX: changed  DATE(CREATED_AT) < ?
           to    DATE(CREATED_AT) <= ?
   so cancellations made TODAY are also included.
   Your payload date "2026-03-29" = today was being
   excluded by the strict less-than filter.
   ══════════════════════════════════════════════════════ */
$wal_stmt = $conn->prepare("
    SELECT WAL_ID, TXN_ID, PAYLOAD, CREATED_AT
    FROM   wal_log
    WHERE  OPERATION = 'DELETE_BOOKING'
      AND  STATUS    = 'COMMITTED'
      AND  DATE(CREATED_AT) <= ?
    ORDER  BY CREATED_AT DESC
");
$wal_stmt->bind_param("s", $today);
$wal_stmt->execute();
$wal_rows = $wal_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$wal_stmt->close();

$debug['wal_rows_found'] = count($wal_rows);
$debug['wal_rows']       = $wal_rows;

/* ══════════════════════════════════════════════════════
   Step 3 — Prepare reusable statements outside loop
   ══════════════════════════════════════════════════════ */
$chk_stmt = $conn->prepare("
    SELECT COUNT(*) AS cnt
    FROM   booking
    WHERE  BOOKING_ID = ?
      AND  USERS_ID   = ?
");

$usr_stmt = $conn->prepare("
    SELECT USERNAME FROM users WHERE USERS_ID = ?
");

/* ══════════════════════════════════════════════════════
   Step 4 — Filter and cross-check
   ══════════════════════════════════════════════════════ */
$history      = [];
$debug['rows_trace'] = [];

foreach ($wal_rows as $row) {

    $trace = ['wal_id' => $row['WAL_ID'], 'payload_raw' => $row['PAYLOAD']];

    $p = json_decode($row['PAYLOAD'], true);
    if (!$p) {
        $trace['skip_reason'] = 'JSON decode failed';
        $debug['rows_trace'][] = $trace;
        continue;
    }

    $shop_id     = (int)($p['shop_id']    ?? 0);
    $booking_id  = (int)($p['booking_id'] ?? 0);
    $wal_user_id = (int)($p['user_id']    ?? 0);

    $trace['shop_id']     = $shop_id;
    $trace['booking_id']  = $booking_id;
    $trace['wal_user_id'] = $wal_user_id;

    if (!in_array($shop_id, $my_shop_ids)) {
        $trace['skip_reason'] = "shop_id $shop_id not in merchant shops: [" . implode(',', $my_shop_ids) . "]";
        $debug['rows_trace'][] = $trace;
        continue;
    }

    if ($booking_id === 0 || $wal_user_id === 0) {
        $trace['skip_reason'] = 'booking_id or user_id is 0';
        $debug['rows_trace'][] = $trace;
        continue;
    }

    /* Cross-check: booking still exists? */
    $chk_stmt->bind_param("ii", $booking_id, $wal_user_id);
    $chk_stmt->execute();
    $cnt = (int)$chk_stmt->get_result()->fetch_assoc()['cnt'];
    $trace['booking_still_exists_cnt'] = $cnt;

    if ($cnt === 0) {
        $usr_stmt->bind_param("i", $wal_user_id);
        $usr_stmt->execute();
        $urow = $usr_stmt->get_result()->fetch_assoc();

        $p['_username_live'] = $urow['USERNAME'] ?? ($p['username'] ?? 'Unknown User');
        $row['_p'] = $p;
        $history[] = $row;
        $trace['result'] = 'ADDED to history';
    } else {
        $trace['skip_reason'] = "booking still exists in booking table (cnt=$cnt) — not truly deleted";
    }

    $debug['rows_trace'][] = $trace;
}

$chk_stmt->close();
$usr_stmt->close();

$debug['history_count'] = count($history);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cancellation History</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg: #080b12; --surface: #0e1420; --surface2: #141928;
    --border: rgba(255,255,255,0.07); --border2: rgba(255,255,255,0.12);
    --accent: #4f8fff; --accent2: #7c5cfc;
    --green: #22c97a; --red: #ff4f6a; --amber: #f59e0b;
    --text: #e8eaf0; --muted: #6b7280;
}
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; overflow-x: hidden; }
body::before {
    content: ''; position: fixed; inset: 0;
    background-image: linear-gradient(rgba(79,143,255,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(79,143,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px; pointer-events: none; z-index: 0;
}
.page-wrapper { position: relative; z-index: 1; max-width: 780px; margin: 0 auto; padding: 48px 24px 80px; }
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 36px; padding-bottom: 28px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 14px; }
.page-header h1 { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; letter-spacing: -0.5px; background: linear-gradient(135deg, #fff 30%, #7caaff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1.15; }
.page-header p { font-size: 0.85rem; color: var(--muted); margin-top: 6px; font-weight: 300; }
.count-pill { font-family: 'Syne', sans-serif; font-size: 0.72rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; padding: 6px 14px; border-radius: 99px; background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff; white-space: nowrap; }
.timeline { display: flex; flex-direction: column; gap: 0; }
.tl-item { display: flex; gap: 18px; animation: fadeIn 0.35s ease both; }
.tl-item:nth-child(1){animation-delay:.04s} .tl-item:nth-child(2){animation-delay:.08s}
.tl-item:nth-child(3){animation-delay:.12s} .tl-item:nth-child(4){animation-delay:.16s}
.tl-spine { display: flex; flex-direction: column; align-items: center; flex-shrink: 0; width: 36px; }
.tl-dot { width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0; background: rgba(255,79,106,0.1); border: 1px solid rgba(255,79,106,0.25); display: flex; align-items: center; justify-content: center; z-index: 1; }
.tl-line { width: 1px; flex: 1; min-height: 20px; background: linear-gradient(to bottom, rgba(255,79,106,0.2), transparent); margin: 6px 0; }
.tl-item:last-child .tl-line { display: none; }
.tl-card { flex: 1; background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 16px 18px; margin-bottom: 16px; position: relative; transition: background 0.15s; }
.tl-card:hover { background: var(--surface2); }
.tl-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.06), transparent); border-radius: 14px 14px 0 0; }
.tl-msg { font-size: 0.9rem; line-height: 1.55; color: var(--text); margin-bottom: 10px; }
.tl-msg strong { color: #fff; font-weight: 600; }
.tl-msg .shop-name { color: #7caaff; font-weight: 500; }
.tl-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.badge { display: inline-flex; align-items: center; gap: 4px; font-size: 0.65rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; padding: 2px 9px; border-radius: 99px; }
.badge-red   { background: rgba(255,79,106,0.1);  border: 1px solid rgba(255,79,106,0.2);  color: #ff8fa1; }
.badge-wal   { background: rgba(124,92,252,0.12); border: 1px solid rgba(124,92,252,0.25); color: #a78bfa; }
.badge-green { background: rgba(34,201,122,0.1);  border: 1px solid rgba(34,201,122,0.2);  color: #5ee8a6; }
.tl-time { font-size: 0.72rem; color: var(--muted); display: flex; align-items: center; gap: 4px; margin-left: auto; }
.tl-amount { position: absolute; top: 16px; right: 18px; font-family: 'Syne', sans-serif; font-size: 0.85rem; font-weight: 700; color: var(--red); opacity: 0.85; }
.empty-state { padding: 80px 24px; text-align: center; }
.empty-icon { width: 60px; height: 60px; background: var(--surface2); border: 1px solid var(--border2); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; }
.empty-state h3 { font-family: 'Syne', sans-serif; font-size: 1.05rem; font-weight: 700; margin-bottom: 6px; }
.empty-state p { font-size: 0.85rem; color: var(--muted); font-weight: 300; }

/* ── Debug panel ── */
.debug-panel { margin-top: 40px; border: 1px solid #f59e0b33; border-radius: 12px; overflow: hidden; }
.debug-panel summary { padding: 12px 16px; background: rgba(245,158,11,0.08); color: var(--amber); font-size: 0.8rem; font-weight: 600; cursor: pointer; letter-spacing: 0.5px; text-transform: uppercase; }
.debug-inner { padding: 16px; }
.debug-section { margin-bottom: 16px; }
.debug-section h4 { font-size: 0.72rem; color: var(--amber); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
.debug-kv { display: grid; grid-template-columns: auto 1fr; gap: 4px 12px; font-size: 0.78rem; }
.debug-kv .k { color: var(--muted); white-space: nowrap; }
.debug-kv .v { color: var(--text); font-family: monospace; word-break: break-all; }
.debug-trace { display: flex; flex-direction: column; gap: 8px; }
.trace-item { background: var(--surface2); border-radius: 8px; padding: 10px 12px; font-size: 0.75rem; font-family: monospace; }
.trace-item .skip { color: var(--red); margin-top: 4px; }
.trace-item .pass { color: var(--green); margin-top: 4px; }
.trace-item .label { color: var(--muted); }

@keyframes fadeIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
</style>
</head>
<body>
<div class="page-wrapper">

    <div class="page-header">
        <div>
            <h1>Cancellation History</h1>
            <p>WAL-verified bookings cancelled by customers</p>
        </div>
        <span class="count-pill">
            <?php echo count($history); ?> record<?php echo count($history) !== 1 ? 's' : ''; ?>
        </span>
    </div>

    <?php if (empty($history)): ?>
    <div class="empty-state">
        <div class="empty-icon">
            <svg width="24" height="24" viewBox="0 0 80 80" fill="none" style="color:var(--muted)">
                <path d="M40 10 C40 10 30 15 28 30 C26 50 20 58 12 65 L68 65 C60 58 54 50 52 30 C50 15 40 10 40 10Z" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/>
                <line x1="40" y1="10" x2="40" y2="4" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                <path d="M32 65 Q32 75 40 75 Q48 75 48 65" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
            </svg>
        </div>
        <h3>No cancellations yet</h3>
        <p>Cancellations will appear here once verified against the WAL log.</p>
    </div>

    <?php else: ?>
    <div class="timeline">
        <?php foreach ($history as $entry):
            $p         = $entry['_p'];
            $username  = htmlspecialchars($p['_username_live'] ?? $p['username'] ?? 'Unknown');
            $shop_name = htmlspecialchars($p['shop_name'] ?? 'Unknown Shop');
            $wal_uid   = (int)($p['user_id'] ?? 0);
            $appt_date = date("M j, Y", strtotime($p['date']));
            $appt_time = date("g:i A",  strtotime($p['time']));
            $total     = number_format((float)($p['total'] ?? 0), 2);
            $log_date  = date("M j, Y", strtotime($entry['CREATED_AT']));
            $log_time  = date("g:i A",  strtotime($entry['CREATED_AT']));
            $time_ago  = time_ago($entry['CREATED_AT']);
        ?>
        <div class="tl-item">
            <div class="tl-spine">
                <div class="tl-dot">
                    <svg width="16" height="16" viewBox="0 0 80 80" fill="none" style="color:var(--red)">
                        <path d="M40 10 C40 10 30 15 28 30 C26 50 20 58 12 65 L68 65 C60 58 54 50 52 30 C50 15 40 10 40 10Z" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/>
                        <line x1="40" y1="10" x2="40" y2="4" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                        <path d="M32 65 Q32 75 40 75 Q48 75 48 65" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="tl-line"></div>
            </div>
            <div class="tl-card">
                
                <div class="tl-msg">
                    <strong><?php echo $username; ?></strong>
                    cancelled their booking at
                    <span class="shop-name"><?php echo $shop_name; ?></span>
                    — appointment was on <strong><?php echo $appt_date; ?></strong>
                    at <strong><?php echo $appt_time; ?></strong>.
                </div>
                <div class="tl-meta">
                    <span class="badge badge-red">Cancelled</span>
                    
                    <span class="badge badge-green">Booking gone</span>
                    <span class="tl-time">
                        <svg width="11" height="11" viewBox="0 0 12 12" fill="none">
                            <circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.2"/>
                            <path d="M6 3.5V6l2 1.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                        </svg>
                        <?php echo $log_date; ?> · <?php echo $log_time; ?> 
                    </span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>



</div>
</body>
</html>
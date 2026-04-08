<?php
include "Server.php";
session_start();

$user_id = $_SESSION['user_id'];
$shop_id = $_GET['shop_id'];

$today   = date("Y-m-d");
$message = "";

/* ================= FETCH SHOP SEATS ================= */
$shop       = $conn->query("SELECT SEAT_NO FROM shop WHERE SHOP_ID=$shop_id")->fetch_assoc();
$seat_limit = $shop['SEAT_NO'];

/* ================= FETCH CATEGORIES ================= */
$categories = $conn->query("SELECT * FROM category WHERE SHOP_ID=$shop_id");

$times = [];
for ($h = 8; $h <= 18; $h++) {
    $times[] = sprintf("%02d:00:00", $h);
}
?>
<?php
function wal_write(mysqli $conn, string $txn_id, string $operation, array $payload): int
{
    $json = json_encode($payload);
    $stmt = $conn->prepare("
        INSERT INTO wal_log (TXN_ID, OPERATION, PAYLOAD, STATUS)
        VALUES (?, ?, ?, 'PENDING')
    ");
    $stmt->bind_param("sss", $txn_id, $operation, $json);
    $stmt->execute();
    return (int) $conn->insert_id;
}

function wal_resolve(mysqli $conn, int $wal_id, string $status): void
{
    $stmt = $conn->prepare("
        UPDATE wal_log
        SET STATUS = ?, RESOLVED_AT = NOW()
        WHERE WAL_ID = ?
    ");
    $stmt->bind_param("si", $status, $wal_id);
    $stmt->execute();
}

function wal_recover(mysqli $conn): void
{
    $stale = $conn->query("
        SELECT WAL_ID, PAYLOAD
        FROM   wal_log
        WHERE  STATUS = 'PENDING'
          AND  CREATED_AT < NOW() - INTERVAL 5 MINUTE
    ");
    while ($row = $stale->fetch_assoc()) {
        $wal_id = $row['WAL_ID'];
        $stmt   = $conn->prepare("
            UPDATE wal_log
            SET STATUS = 'ROLLED_BACK', RESOLVED_AT = NOW()
            WHERE WAL_ID = ?
        ");
        $stmt->bind_param("i", $wal_id);
        $stmt->execute();
    }
}

wal_recover($conn);

/* ===============================================================
   BOOKING LOGIC — ACID + WAL
   ============================================================== */
if (isset($_POST['book'])) {

    $date     = $_POST['date'];
    $time     = $_POST['time'];
    $selected = $_POST['categories'] ?? [];

    if ($date < $today) {
        $message = "Cannot book a past date!";
    } elseif (empty($selected)) {
        $message = "Select at least one service!";
    } else {

        $total = 0;
        foreach ($selected as $id) {
            $res    = $conn->query("SELECT PRICE FROM category WHERE CATEGORIES_ID=$id");
            $row    = $res->fetch_assoc();
            $total += $row['PRICE'];
        }

        $txn_id = uniqid('txn_', true);
        $wal_id = null;

        try {
            $conn->begin_transaction();

            $wal_id = wal_write($conn, $txn_id, 'INSERT_BOOKING', [
                'user_id'    => $user_id,
                'shop_id'    => $shop_id,
                'date'       => $date,
                'time'       => $time,
                'total'      => $total,
                'categories' => $selected,
            ]);

            $dupCheck = $conn->prepare("
                SELECT COUNT(*) AS already_booked
                FROM   booking
                WHERE  USERS_ID = ? AND SHOP_ID = ? AND DATE = ? AND TIME = ?
                FOR UPDATE
            ");
            $dupCheck->bind_param("iiss", $user_id, $shop_id, $date, $time);
            $dupCheck->execute();
            $dup = $dupCheck->get_result()->fetch_assoc();

            if ($dup['already_booked'] > 0) {
                wal_resolve($conn, $wal_id, 'ROLLED_BACK');
                $conn->rollback();
                $message = "You already have a booking at this date and time!";
            } else {
                $check = $conn->prepare("
                    SELECT COUNT(*) AS total_booked
                    FROM   booking
                    WHERE  SHOP_ID = ? AND DATE = ? AND TIME = ?
                    FOR UPDATE
                ");
                $check->bind_param("iss", $shop_id, $date, $time);
                $check->execute();
                $result = $check->get_result()->fetch_assoc();

                if ($result['total_booked'] >= $seat_limit) {
                    wal_resolve($conn, $wal_id, 'ROLLED_BACK');
                    $conn->rollback();
                    $message = "Time slot is fully booked!";
                } else {
                    $insert = $conn->prepare("
                        INSERT INTO booking (USERS_ID, SHOP_ID, TIME, DATE, TOTAL)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $insert->bind_param("iissd", $user_id, $shop_id, $time, $date, $total);
                    $insert->execute();

                    wal_resolve($conn, $wal_id, 'COMMITTED');
                    $conn->commit();
                    $message = "Booking Successful!";
                }
            }

        } catch (Exception $e) {
            if ($wal_id !== null) {
                wal_resolve($conn, $wal_id, 'ROLLED_BACK');
            }
            $conn->rollback();
            $message = "Booking failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book Appointment</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --cream:        #F7F4EF;
    --ink:          #1A1A18;
    --ink2:         #5A5A52;
    --accent:       #C8602A;
    --accent-light: #FAE9DE;
    --accent-mid:   #E8A07A;
    --success:      #2D6A4F;
    --success-bg:   #D8F3DC;
    --error:        #9B2226;
    --error-bg:     #FFE4E1;
    --border:       rgba(26,26,24,0.12);
    --card-bg:      #FFFFFF;
    --serif:        'DM Serif Display', Georgia, serif;
    --sans:         'DM Sans', system-ui, sans-serif;
  }

  body {
    font-family: var(--sans);
    background: var(--cream);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 2.5rem 1rem 4rem;
  }

  .page-wrap {
    width: 100%;
    max-width: 520px;
  }

  /* ── Header ── */
  .header {
    text-align: center;
    margin-bottom: 2.5rem;
    padding-bottom: 2rem;
    border-bottom: 0.5px solid var(--border);
  }

  .header-eyebrow {
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--accent);
    margin-bottom: 0.6rem;
  }

  .header h1 {
    font-family: var(--serif);
    font-size: 36px;
    font-weight: 400;
    color: var(--ink);
    line-height: 1.1;
    margin-bottom: 0.5rem;
  }

  .header h1 em {
    font-style: italic;
    color: var(--accent);
  }

  .header p {
    font-size: 14px;
    color: var(--ink2);
    font-weight: 300;
  }

  /* ── Cards ── */
  .card {
    background: var(--card-bg);
    border: 0.5px solid var(--border);
    border-radius: 16px;
    padding: 1.75rem 2rem;
    margin-bottom: 1rem;
  }

  .section-label {
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--ink2);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .section-label::after {
    content: '';
    flex: 1;
    height: 0.5px;
    background: var(--border);
  }

  /* ── Date picker ── */
  .date-input-wrap input[type="date"] {
    width: 100%;
    padding: 13px 16px;
    font-family: var(--sans);
    font-size: 15px;
    color: var(--ink);
    background: var(--cream);
    border: 1px solid var(--border);
    border-radius: 10px;
    outline: none;
    cursor: pointer;
    transition: border-color 0.2s;
    -webkit-appearance: none;
    appearance: none;
  }

  .date-input-wrap input[type="date"]:hover,
  .date-input-wrap input[type="date"]:focus {
    border-color: var(--accent);
  }

  /* ── Time grid ── */
  .time-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
  }

  .time-btn {
    padding: 10px 6px;
    font-family: var(--sans);
    font-size: 13px;
    font-weight: 400;
    color: var(--ink2);
    background: var(--cream);
    border: 1px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    text-align: center;
    transition: all 0.15s;
    user-select: none;
  }

  .time-btn:hover {
    border-color: var(--accent-mid);
    color: var(--ink);
    background: #fff;
  }

  .time-btn.selected {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
    font-weight: 500;
  }

  /* ── Services ── */
  .services-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .service-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 16px;
    background: var(--cream);
    border: 1px solid var(--border);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.15s;
    user-select: none;
  }

  .service-item:hover {
    border-color: var(--accent-mid);
    background: #fff;
  }

  .service-item.checked {
    border-color: var(--accent);
    background: var(--accent-light);
  }

  .service-check {
    width: 20px;
    height: 20px;
    border: 1.5px solid var(--border);
    border-radius: 5px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    transition: all 0.15s;
  }

  .service-item.checked .service-check {
    background: var(--accent);
    border-color: var(--accent);
  }

  .service-check svg { opacity: 0; transition: opacity 0.1s; }
  .service-item.checked .service-check svg { opacity: 1; }

  .service-name  { flex: 1; font-size: 14px; color: var(--ink); }
  .service-price { font-size: 13px; font-weight: 500; color: var(--ink2); }
  .service-item.checked .service-price { color: var(--accent); }

  /* Hidden real checkboxes */
  .service-item input[type="checkbox"] { display: none; }

  /* ── Summary / CTA bar ── */
  .summary-card {
    background: var(--ink);
    border-radius: 14px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    gap: 1rem;
  }

  .summary-label {
    font-size: 11px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.4);
    margin-bottom: 4px;
  }

  .summary-total {
    font-family: var(--serif);
    font-size: 32px;
    color: #fff;
    line-height: 1;
  }

  .summary-total span {
    font-size: 15px;
    font-family: var(--sans);
    font-weight: 300;
    color: rgba(255,255,255,0.55);
    margin-right: 3px;
  }

  .summary-services {
    font-size: 12px;
    color: rgba(255,255,255,0.4);
    margin-top: 5px;
  }

  /* ── Book button ── */
  .book-btn {
    padding: 15px 28px;
    font-family: var(--sans);
    font-size: 15px;
    font-weight: 500;
    color: #fff;
    background: var(--accent);
    border: none;
    border-radius: 10px;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.18s, transform 0.12s;
    flex-shrink: 0;
  }

  .book-btn:hover  { background: #B8511F; transform: translateY(-1px); }
  .book-btn:active { transform: scale(0.97); }

  /* ── Message banner ── */
  .msg {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 1rem;
  }

  .msg-success { background: var(--success-bg); color: var(--success); border: 1px solid rgba(45,106,79,0.2); }
  .msg-error   { background: var(--error-bg);   color: var(--error);   border: 1px solid rgba(155,34,38,0.2); }

  .msg-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
  .msg-success .msg-dot { background: var(--success); }
  .msg-error   .msg-dot { background: var(--error); }

  .footer-note {
    text-align: center;
    font-size: 12px;
    color: var(--ink2);
    font-weight: 300;
    margin-top: 0.25rem;
  }
  .back-btn{
    position:fixed;
    top:20px; left:20px;
    width:42px;height:42px;
    border-radius:50%;
    background:var(--accent-light);
    display:flex;align-items:center;justify-content:center;
    border:1px solid var(--border);
    color:var(--ink2);
    transition:.2s;
}
.back-btn:hover{
    background:var(--ink2);
    color:var(--cream);
}
.back-btn svg{width:18px}
</style>
</head>
<body>
<a href="javascript:history.back()" class="back-btn">
<svg viewBox="0 0 20 20">
<path d="M13 15l-4-4 4-4" stroke="currentColor" stroke-width="1.5"/>
</svg>
</a>
<div class="page-wrap">

  <!-- Header -->
  <div class="header">
    <p class="header-eyebrow">Reserve your visit</p>
    <h1>Book an <em>Appointment</em></h1>
    <p>Select your date, time, and services below</p>
  </div>

  <!-- PHP message banner -->
  <?php if (!empty($message)): ?>
  <div class="msg <?php echo ($message === 'Booking Successful!') ? 'msg-success' : 'msg-error'; ?>">
    <div class="msg-dot"></div>
    <span><?php echo htmlspecialchars($message); ?></span>
  </div>
  <?php endif; ?>

  <!-- JS validation message -->
  <div id="jsMsg" style="display:none;"></div>

  <form id="bookingForm" method="POST" onsubmit="return handleSubmit(event)">
    <input type="hidden" name="book" value="1">
    <input type="hidden" name="time" id="selectedTime" value="">
    <input type="hidden" name="date" id="selectedDate" value="">

    <!-- Date -->
    <div class="card">
      <div class="section-label">Date</div>
      <div class="date-input-wrap">
        <input type="date"
               id="datePickerVisible"
               min="<?php echo $today; ?>"
               required>
      </div>
    </div>

    <!-- Time -->
    <div class="card">
      <div class="section-label">Time slot</div>
      <div class="time-grid" id="timeGrid">
        <?php foreach ($times as $t): ?>
        <div class="time-btn" data-time="<?php echo $t; ?>">
          <?php echo date("g:i A", strtotime($t)); ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Services -->
    <div class="card">
      <div class="section-label">Services</div>
      <div class="services-list">
        <?php
        // Reset result pointer in case it was partially iterated
        $categories->data_seek(0);
        while ($cat = $categories->fetch_assoc()):
        ?>
        <div class="service-item"
             data-id="<?php echo $cat['CATEGORIES_ID']; ?>"
             data-price="<?php echo $cat['PRICE']; ?>">
          <div class="service-check">
            <svg width="11" height="9" viewBox="0 0 11 9" fill="none">
              <polyline points="1,4.5 4,7.5 10,1.5"
                        stroke="white"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"/>
            </svg>
          </div>
          <span class="service-name"><?php echo htmlspecialchars($cat['NAME']); ?></span>
          <span class="service-price">Rs. <?php echo number_format($cat['PRICE']); ?></span>
          <input type="checkbox"
                 name="categories[]"
                 value="<?php echo $cat['CATEGORIES_ID']; ?>">
        </div>
        <?php endwhile; ?>
      </div>
    </div>

    <!-- Summary + CTA -->
    <div class="summary-card">
      <div>
        <div class="summary-label">Total estimate</div>
        <div class="summary-total">
          <span>Rs.</span><span id="totalAmt">0</span>
        </div>
        <div class="summary-services" id="selectedCount">No services selected</div>
      </div>
      <button type="submit" class="book-btn">Book Now</button>
    </div>

  </form>

  <p class="footer-note">Appointments available 8 AM – 6 PM daily</p>

</div><!-- /.page-wrap -->

<script>
  /* ── Sync visible date picker → hidden input ── */
  const dp = document.getElementById('datePickerVisible');
  dp.addEventListener('change', () => {
    document.getElementById('selectedDate').value = dp.value;
  });

  /* ── Time slot toggle ── */
  document.querySelectorAll('.time-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.time-btn').forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');
      document.getElementById('selectedTime').value = btn.dataset.time;
    });
  });

  /* ── Service checkbox toggle ── */
  document.querySelectorAll('.service-item').forEach(item => {
    item.addEventListener('click', () => {
      item.classList.toggle('checked');
      const cb = item.querySelector('input[type="checkbox"]');
      cb.checked = !cb.checked;
      updateTotal();
    });
  });

  function updateTotal() {
    const checked = document.querySelectorAll('.service-item.checked');
    let sum = 0;
    checked.forEach(i => { sum += parseFloat(i.dataset.price); });
    document.getElementById('totalAmt').textContent = sum.toLocaleString();
    const el = document.getElementById('selectedCount');
    el.textContent = checked.length === 0
      ? 'No services selected'
      : checked.length === 1 ? '1 service selected' : checked.length + ' services selected';
  }

  /* ── JS-side validation (client guard before PHP) ── */
  function showJsMsg(text) {
    const box = document.getElementById('jsMsg');
    box.className = 'msg msg-error';
    box.innerHTML = '<div class="msg-dot"></div><span>' + text + '</span>';
    box.style.display = 'flex';
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function handleSubmit(e) {
    const date     = document.getElementById('selectedDate').value;
    const time     = document.getElementById('selectedTime').value;
    const services = document.querySelectorAll('.service-item.checked');

    if (!date)             { showJsMsg('Please select a date.');                       return false; }
    if (!time)             { showJsMsg('Please select a time slot.');                  return false; }
    if (!services.length)  { showJsMsg('Select at least one service before booking.'); return false; }

    return true;  // let the form POST normally to PHP
  }
</script>

</body>
</html>
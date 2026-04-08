<?php
include "Server.php";
session_start();

/* ══════════════════════════════════════
   ADMIN AUTH — check admin table
   ══════════════════════════════════════ */
$auth_error = "";

if (!isset($_SESSION['admin_logged_in'])) {

    if (isset($_POST['admin_login'])) {
        $a_user = trim($_POST['a_username']);
        $a_pass = trim($_POST['a_password']);

        // Plain-text check (no encryption as requested)
        $stmt = $conn->prepare("SELECT * FROM admin WHERE USERNAME = ? AND PASSWORD = ? LIMIT 1");
        $stmt->bind_param("ss", $a_user, $a_pass);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username']  = $a_user;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $auth_error = "Invalid username or password.";
        }
    }

    // Show login gate — not logged in
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Admin — HairDec</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
      <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
          --bg: #08090B; --surface: #0D0F14; --surface2: #13161D;
          --border: rgba(255,255,255,0.07); --border2: rgba(255,255,255,0.13);
          --text: #E8E4DC; --muted: #6B6860;
          --gold: #C9A96E; --gold-dim: rgba(201,169,110,0.12); --gold-glow: rgba(201,169,110,0.08);
          --red: #E05C5C;  --red-dim: rgba(224,92,92,0.12);
          --serif: 'Cormorant Garamond', Georgia, serif;
          --sans: 'Jost', system-ui, sans-serif;
        }
        body {
          font-family: var(--sans); background: var(--bg); min-height: 100vh;
          display: flex; align-items: center; justify-content: center; overflow: hidden;
        }
        body::before {
          content: ''; position: fixed; width: 400px; height: 400px; border-radius: 50%;
          background: radial-gradient(circle, #C9A96E 0%, transparent 70%);
          top: -140px; right: -80px; filter: blur(90px); opacity: 0.2; pointer-events: none;
          animation: drift 16s ease-in-out infinite alternate;
        }
        @keyframes drift { from { transform: translate(0,0); } to { transform: translate(-30px,40px); } }
        .card {
          width: 340px; background: var(--surface); border: 0.5px solid var(--border2);
          border-radius: 20px; padding: 2.75rem 2.25rem 2.25rem; position: relative; z-index:1;
          animation: rise 0.6s cubic-bezier(0.22,1,0.36,1) both;
        }
        @keyframes rise { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        .card::before {
          content: ''; position: absolute; top:0; left:15%; right:15%; height:1px;
          background: linear-gradient(90deg, transparent, var(--gold), transparent); opacity:0.45;
        }
        .brand { text-align:center; margin-bottom:2rem; }
        .badge {
          display:inline-flex; align-items:center; justify-content:center;
          width:42px; height:42px; background:var(--gold-dim);
          border:0.5px solid rgba(201,169,110,0.3); border-radius:11px;
          font-size:18px; margin-bottom:0.8rem;
        }
        .brand h1 { font-family:var(--serif); font-size:28px; font-weight:300; color:var(--text); }
        .brand h1 em { font-style:italic; color:var(--gold); }
        .brand p { font-size:11px; letter-spacing:0.12em; text-transform:uppercase; color:var(--muted); margin-top:4px; }
        .divider { height:0.5px; background:var(--border); margin-bottom:1.75rem; }
        .field { margin-bottom:1rem; }
        .field label { display:block; font-size:10px; font-weight:500; letter-spacing:0.12em; text-transform:uppercase; color:var(--muted); margin-bottom:6px; }
        .field input {
          width:100%; padding:12px 14px; font-family:var(--sans); font-size:14px; font-weight:300;
          color:var(--text); background:var(--surface2); border:0.5px solid var(--border2);
          border-radius:9px; outline:none; caret-color:var(--gold); transition:border-color 0.2s, box-shadow 0.2s;
        }
        .field input::placeholder { color:var(--muted); }
        .field input:focus { border-color:rgba(201,169,110,0.5); box-shadow:0 0 0 3px var(--gold-glow); }
        .error-msg {
          background:var(--red-dim); border:0.5px solid rgba(224,92,92,0.25);
          color:var(--red); font-size:13px; padding:10px 14px; border-radius:8px; margin-bottom:1rem;
          display:flex; align-items:center; gap:8px;
        }
        .error-dot { width:7px; height:7px; border-radius:50%; background:var(--red); flex-shrink:0; }
        .btn {
          width:100%; margin-top:1.25rem; padding:12px; font-family:var(--sans);
          font-size:12px; font-weight:500; letter-spacing:0.1em; text-transform:uppercase;
          color:#0D0F14; background:var(--gold); border:none; border-radius:9px; cursor:pointer;
          transition:opacity 0.2s, transform 0.15s, box-shadow 0.2s;
        }
        .btn:hover { opacity:0.85; transform:translateY(-1px); box-shadow:0 8px 24px rgba(201,169,110,0.22); }
        .btn:active { transform:scale(0.98); }
      </style>
    </head>
    <body>
    <div class="card">
      <div class="brand">
        <div class="badge">✂</div>
        <h1>Hair<em>Dec</em></h1>
        <p>Admin Portal</p>
      </div>
      <div class="divider"></div>
      <?php if ($auth_error): ?>
      <div class="error-msg"><div class="error-dot"></div><?= htmlspecialchars($auth_error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <div class="field">
          <label for="a_username">Admin Username</label>
          <input type="text" id="a_username" name="a_username" placeholder="Enter username" required autocomplete="off">
        </div>
        <div class="field">
          <label for="a_password">Password</label>
          <input type="password" id="a_password" name="a_password" placeholder="••••••••" required>
        </div>
        <button type="submit" name="admin_login" class="btn">Enter Dashboard</button>
      </form>
    </div>
    </body>
    </html>
    <?php
    exit;
}

/* ══════════════════════════════════════
   LOGGED IN — handle actions
   ══════════════════════════════════════ */

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Delete user
if (isset($_POST['delete'])) {
    $username = $_POST['username'];
    $stmt = $conn->prepare("DELETE FROM USERS WHERE USERNAME = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=users");
    exit;
}

// Stats
$total    = $conn->query("SELECT COUNT(*) AS c FROM USERS")->fetch_assoc()['c'];
$merchant = $conn->query("SELECT COUNT(*) AS c FROM USERS WHERE TYPE='merchant'")->fetch_assoc()['c'];
$user     = $conn->query("SELECT COUNT(*) AS c FROM USERS WHERE TYPE='user'")->fetch_assoc()['c'];

// Users table
$result = $conn->query("SELECT USERNAME, TYPE FROM USERS ORDER BY TYPE, USERNAME");

// Active tab
$tab = $_GET['tab'] ?? 'overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — HairDec</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:         #08090B;
      --nav:        #0B0D11;
      --surface:    #0D0F14;
      --surface2:   #13161D;
      --surface3:   #181B24;
      --border:     rgba(255,255,255,0.06);
      --border2:    rgba(255,255,255,0.11);
      --text:       #E8E4DC;
      --muted:      #5C5A54;
      --muted2:     #8A877F;
      --gold:       #C9A96E;
      --gold-dim:   rgba(201,169,110,0.12);
      --gold-glow:  rgba(201,169,110,0.08);
      --blue-dim:   rgba(99,148,210,0.12);
      --blue:       #6394D2;
      --green-dim:  rgba(86,163,117,0.12);
      --green:      #56A375;
      --red:        #E05C5C;
      --red-dim:    rgba(224,92,92,0.12);
      --serif:      'Cormorant Garamond', Georgia, serif;
      --sans:       'Jost', system-ui, sans-serif;
    }

    html, body { height: 100%; }

    body {
      font-family: var(--sans);
      background: var(--bg);
      color: var(--text);
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    /* ══ NAVBAR ══ */
    nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 2rem;
      height: 60px;
      background: var(--nav);
      border-bottom: 0.5px solid var(--border2);
      position: sticky;
      top: 0;
      z-index: 100;
      flex-shrink: 0;
    }

    .nav-brand {
      font-family: var(--serif);
      font-size: 22px;
      font-weight: 300;
      color: var(--text);
      letter-spacing: 0.04em;
    }
    .nav-brand em { font-style: italic; color: var(--gold); }
    .nav-brand span {
      font-family: var(--sans);
      font-size: 10px;
      font-weight: 500;
      letter-spacing: 0.13em;
      text-transform: uppercase;
      color: var(--muted2);
      margin-left: 10px;
      padding-left: 10px;
      border-left: 0.5px solid var(--border2);
    }

    .nav-tabs {
      display: flex;
      gap: 4px;
      list-style: none;
    }

    .nav-tabs a {
      display: block;
      padding: 7px 16px;
      font-size: 12px;
      font-weight: 500;
      letter-spacing: 0.07em;
      text-transform: uppercase;
      color: var(--muted2);
      text-decoration: none;
      border-radius: 7px;
      transition: color 0.2s, background 0.2s;
    }

    .nav-tabs a:hover { color: var(--text); background: var(--surface2); }
    .nav-tabs a.active { color: var(--gold); background: var(--gold-dim); }

    .nav-right {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 13px;
      color: var(--muted2);
    }

    .nav-right strong { color: var(--text); font-weight: 400; }

    .logout-btn {
      padding: 7px 14px;
      font-family: var(--sans);
      font-size: 11px;
      font-weight: 500;
      letter-spacing: 0.09em;
      text-transform: uppercase;
      color: var(--red);
      background: var(--red-dim);
      border: 0.5px solid rgba(224,92,92,0.2);
      border-radius: 7px;
      cursor: pointer;
      text-decoration: none;
      transition: opacity 0.2s;
    }
    .logout-btn:hover { opacity: 0.7; }

    /* ══ MAIN CONTENT ══ */
    main {
      flex: 1;
      padding: 2.5rem 2.5rem 4rem;
      max-width: 1100px;
      width: 100%;
      margin: 0 auto;
    }

    /* ══ PAGE HEADER ══ */
    .page-header {
      margin-bottom: 2rem;
      animation: fadein 0.5s ease both;
    }

    .page-header h2 {
      font-family: var(--serif);
      font-size: 28px;
      font-weight: 300;
      color: var(--text);
    }
    .page-header h2 em { font-style: italic; color: var(--gold); }
    .page-header p { font-size: 13px; color: var(--muted2); font-weight: 300; margin-top: 4px; }

    @keyframes fadein { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }

    /* ══ STAT CARDS ══ */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1rem;
      margin-bottom: 2.5rem;
    }

    .stat-card {
      background: var(--surface);
      border: 0.5px solid var(--border2);
      border-radius: 14px;
      padding: 1.5rem 1.75rem;
      position: relative;
      overflow: hidden;
      animation: fadein 0.5s ease both;
    }

    .stat-card:nth-child(2) { animation-delay: 0.08s; }
    .stat-card:nth-child(3) { animation-delay: 0.16s; }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0; left: 10%; right: 10%; height: 1px;
      opacity: 0.4;
    }

    .stat-card.total::before  { background: linear-gradient(90deg, transparent, var(--gold), transparent); }
    .stat-card.merch::before  { background: linear-gradient(90deg, transparent, var(--blue), transparent); }
    .stat-card.users::before  { background: linear-gradient(90deg, transparent, var(--green), transparent); }

    .stat-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 36px; height: 36px;
      border-radius: 9px;
      font-size: 16px;
      margin-bottom: 1rem;
    }

    .stat-card.total .stat-icon { background: var(--gold-dim); }
    .stat-card.merch .stat-icon { background: var(--blue-dim); }
    .stat-card.users .stat-icon { background: var(--green-dim); }

    .stat-label {
      font-size: 10px;
      font-weight: 500;
      letter-spacing: 0.13em;
      text-transform: uppercase;
      color: var(--muted2);
      margin-bottom: 6px;
    }

    .stat-number {
      font-family: var(--serif);
      font-size: 40px;
      font-weight: 300;
      line-height: 1;
    }

    .stat-card.total .stat-number { color: var(--gold); }
    .stat-card.merch .stat-number { color: var(--blue); }
    .stat-card.users .stat-number { color: var(--green); }

    /* ══ TABLE SECTION ══ */
    .table-wrap {
      background: var(--surface);
      border: 0.5px solid var(--border2);
      border-radius: 14px;
      overflow: hidden;
      animation: fadein 0.5s ease 0.2s both;
    }

    .table-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.25rem 1.75rem;
      border-bottom: 0.5px solid var(--border);
    }

    .table-head h3 {
      font-family: var(--serif);
      font-size: 18px;
      font-weight: 300;
      color: var(--text);
    }
    .table-head h3 em { font-style: italic; color: var(--gold); }

    .table-count {
      font-size: 11px;
      font-weight: 500;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--muted2);
      background: var(--surface2);
      padding: 5px 12px;
      border-radius: 20px;
      border: 0.5px solid var(--border2);
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    thead th {
      padding: 10px 1.75rem;
      font-size: 10px;
      font-weight: 500;
      letter-spacing: 0.13em;
      text-transform: uppercase;
      color: var(--muted);
      text-align: left;
      background: var(--surface2);
      border-bottom: 0.5px solid var(--border);
    }

    tbody tr {
      border-bottom: 0.5px solid var(--border);
      transition: background 0.15s;
    }

    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover { background: var(--surface2); }

    tbody td {
      padding: 13px 1.75rem;
      font-size: 13px;
      color: var(--text);
      font-weight: 300;
      vertical-align: middle;
    }

    .type-badge {
      display: inline-block;
      padding: 3px 10px;
      font-size: 10px;
      font-weight: 500;
      letter-spacing: 0.09em;
      text-transform: uppercase;
      border-radius: 20px;
    }

    .type-merchant { background: var(--blue-dim); color: var(--blue); border: 0.5px solid rgba(99,148,210,0.2); }
    .type-user     { background: var(--green-dim); color: var(--green); border: 0.5px solid rgba(86,163,117,0.2); }

    .del-btn {
      padding: 6px 14px;
      font-family: var(--sans);
      font-size: 11px;
      font-weight: 500;
      letter-spacing: 0.07em;
      text-transform: uppercase;
      color: var(--red);
      background: var(--red-dim);
      border: 0.5px solid rgba(224,92,92,0.2);
      border-radius: 6px;
      cursor: pointer;
      transition: opacity 0.2s, transform 0.12s;
    }

    .del-btn:hover { opacity: 0.75; transform: scale(0.97); }

    .empty-row td {
      text-align: center;
      padding: 3rem;
      color: var(--muted2);
      font-size: 14px;
      font-weight: 300;
    }

    /* ══ CONFIRM MODAL ══ */
    .modal-bg {
      display: none;
      position: fixed; inset: 0;
      background: rgba(0,0,0,0.7);
      backdrop-filter: blur(4px);
      z-index: 200;
      align-items: center;
      justify-content: center;
    }
    .modal-bg.show { display: flex; }

    .modal {
      background: var(--surface);
      border: 0.5px solid var(--border2);
      border-radius: 16px;
      padding: 2rem 2.25rem;
      width: 340px;
      text-align: center;
      position: relative;
    }

    .modal::before {
      content: '';
      position: absolute; top:0; left:20%; right:20%; height:1px;
      background: linear-gradient(90deg, transparent, var(--red), transparent); opacity:0.4;
    }

    .modal-icon {
      width: 48px; height: 48px;
      background: var(--red-dim);
      border: 0.5px solid rgba(224,92,92,0.25);
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px;
      margin: 0 auto 1rem;
    }

    .modal h3 {
      font-family: var(--serif);
      font-size: 22px; font-weight: 300; color: var(--text); margin-bottom: 0.4rem;
    }

    .modal p { font-size: 13px; color: var(--muted2); font-weight: 300; margin-bottom: 1.5rem; }
    .modal p strong { color: var(--text); font-weight: 400; }

    .modal-actions { display: flex; gap: 10px; }

    .modal-cancel {
      flex: 1; padding: 11px;
      font-family: var(--sans); font-size: 12px; font-weight: 500;
      letter-spacing: 0.08em; text-transform: uppercase;
      color: var(--muted2); background: var(--surface2);
      border: 0.5px solid var(--border2); border-radius: 8px; cursor: pointer;
      transition: opacity 0.2s;
    }
    .modal-cancel:hover { opacity: 0.7; }

    .modal-confirm {
      flex: 1; padding: 11px;
      font-family: var(--sans); font-size: 12px; font-weight: 500;
      letter-spacing: 0.08em; text-transform: uppercase;
      color: #fff; background: var(--red);
      border: none; border-radius: 8px; cursor: pointer;
      transition: opacity 0.2s;
    }
    .modal-confirm:hover { opacity: 0.8; }

    /* hidden delete form */
    #deleteForm { display: none; }
  </style>
</head>
<body>

<!-- ══ NAVBAR ══ -->
<nav>
  <div class="nav-brand">
    Hair<em>Dec</em>
    <span>Admin</span>
  </div>

  <ul class="nav-tabs">
    <li><a href="?tab=overview" class="<?= $tab === 'overview' ? 'active' : '' ?>">Overview</a></li>
    <li><a href="?tab=merchants" class="<?= $tab === 'merchants' ? 'active' : '' ?>">Merchants</a></li>
    <li><a href="?tab=users" class="<?= $tab === 'users' ? 'active' : '' ?>">Users</a></li>
  </ul>

  <div class="nav-right">
    <span>Signed in as <strong><?= htmlspecialchars($_SESSION['admin_username']) ?></strong></span>
    <a href="?logout=1" class="logout-btn">Log out</a>
  </div>
</nav>

<!-- ══ MAIN ══ -->
<main>

  <?php if ($tab === 'overview'): ?>
  <!-- ── Overview ── -->
  <div class="page-header">
    <h2>Dashboard <em>Overview</em></h2>
    <p>Account summary across all registered users</p>
  </div>

  <div class="stats-grid">
    <div class="stat-card total">
      <div class="stat-icon">✦</div>
      <div class="stat-label">Total Accounts</div>
      <div class="stat-number"><?= $total ?></div>
    </div>
    <div class="stat-card merch">
      <div class="stat-icon">🏪</div>
      <div class="stat-label">Merchants</div>
      <div class="stat-number"><?= $merchant ?></div>
    </div>
    <div class="stat-card users">
      <div class="stat-icon">👤</div>
      <div class="stat-label">Users</div>
      <div class="stat-number"><?= $user ?></div>
    </div>
  </div>

  <!-- All accounts table on overview -->
  <div class="table-wrap">
    <div class="table-head">
      <h3>All <em>Accounts</em></h3>
      <span class="table-count"><?= $total ?> total</span>
    </div>
    <table>
      <thead><tr><th>Username</th><th>Account Type</th><th>Action</th></tr></thead>
      <tbody>
        <?php
        $result->data_seek(0);
        if ($result->num_rows > 0):
          while ($row = $result->fetch_assoc()):
            $type_class = $row['TYPE'] === 'merchant' ? 'type-merchant' : 'type-user';
        ?>
        <tr>
          <td><?= htmlspecialchars($row['USERNAME']) ?></td>
          <td><span class="type-badge <?= $type_class ?>"><?= htmlspecialchars($row['TYPE']) ?></span></td>
          <td>
            <button class="del-btn"
              onclick="confirmDelete('<?= htmlspecialchars($row['USERNAME'], ENT_QUOTES) ?>')">
              Delete
            </button>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr class="empty-row"><td colspan="3">No accounts found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php elseif ($tab === 'merchants'): ?>
  <!-- ── Merchants ── -->
  <div class="page-header">
    <h2>Merchant <em>Accounts</em></h2>
    <p>All registered merchant accounts</p>
  </div>
  <div class="stats-grid" style="grid-template-columns:1fr 2fr;">
    <div class="stat-card merch">
      <div class="stat-icon">🏪</div>
      <div class="stat-label">Total Merchants</div>
      <div class="stat-number"><?= $merchant ?></div>
    </div>
  </div>
  <div class="table-wrap">
    <div class="table-head">
      <h3>Merchant <em>List</em></h3>
      <span class="table-count"><?= $merchant ?> merchants</span>
    </div>
    <table>
      <thead><tr><th>Username</th><th>Account Type</th><th>Action</th></tr></thead>
      <tbody>
        <?php
        $r = $conn->query("SELECT USERNAME, TYPE FROM USERS WHERE TYPE='merchant' ORDER BY USERNAME");
        if ($r->num_rows > 0):
          while ($row = $r->fetch_assoc()):
        ?>
        <tr>
          <td><?= htmlspecialchars($row['USERNAME']) ?></td>
          <td><span class="type-badge type-merchant">Merchant</span></td>
          <td>
            <button class="del-btn"
              onclick="confirmDelete('<?= htmlspecialchars($row['USERNAME'], ENT_QUOTES) ?>')">
              Delete
            </button>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr class="empty-row"><td colspan="3">No merchant accounts found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php elseif ($tab === 'users'): ?>
  <!-- ── Users ── -->
  <div class="page-header">
    <h2>User <em>Accounts</em></h2>
    <p>All registered user accounts</p>
  </div>
  <div class="stats-grid" style="grid-template-columns:1fr 2fr;">
    <div class="stat-card users">
      <div class="stat-icon">👤</div>
      <div class="stat-label">Total Users</div>
      <div class="stat-number"><?= $user ?></div>
    </div>
  </div>
  <div class="table-wrap">
    <div class="table-head">
      <h3>User <em>List</em></h3>
      <span class="table-count"><?= $user ?> users</span>
    </div>
    <table>
      <thead><tr><th>Username</th><th>Account Type</th><th>Action</th></tr></thead>
      <tbody>
        <?php
        $r = $conn->query("SELECT USERNAME, TYPE FROM USERS WHERE TYPE='user' ORDER BY USERNAME");
        if ($r->num_rows > 0):
          while ($row = $r->fetch_assoc()):
        ?>
        <tr>
          <td><?= htmlspecialchars($row['USERNAME']) ?></td>
          <td><span class="type-badge type-user">User</span></td>
          <td>
            <button class="del-btn"
              onclick="confirmDelete('<?= htmlspecialchars($row['USERNAME'], ENT_QUOTES) ?>')">
              Delete
            </button>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr class="empty-row"><td colspan="3">No user accounts found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</main>

<!-- ══ DELETE CONFIRM MODAL ══ -->
<div class="modal-bg" id="modalBg">
  <div class="modal">
    <div class="modal-icon">🗑</div>
    <h3>Delete <em>Account</em></h3>
    <p>Are you sure you want to delete <strong id="modalName"></strong>? This cannot be undone.</p>
    <div class="modal-actions">
      <button class="modal-cancel" onclick="closeModal()">Cancel</button>
      <button class="modal-confirm" onclick="submitDelete()">Delete</button>
    </div>
  </div>
</div>

<!-- Real delete form (hidden) -->
<form id="deleteForm" method="POST">
  <input type="hidden" name="delete" value="1">
  <input type="hidden" name="username" id="deleteUsername">
</form>

<script>
  function confirmDelete(username) {
    document.getElementById('modalName').textContent = username;
    document.getElementById('deleteUsername').value  = username;
    document.getElementById('modalBg').classList.add('show');
  }
  function closeModal() {
    document.getElementById('modalBg').classList.remove('show');
  }
  function submitDelete() {
    document.getElementById('deleteForm').submit();
  }
  // close on backdrop click
  document.getElementById('modalBg').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
  });
</script>

</body>
</html>
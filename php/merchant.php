<?php
session_start();
if ($_SESSION['type'] === "merchant"):
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Merchant Panel</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --sidebar-w:    240px;
      --bg-dark:      #0f1117;
      --bg-panel:     #161a23;
      --bg-item:      #1e2433;
      --accent:       #e8a838;
      --accent-dim:   rgba(232,168,56,.12);
      --text:         #f0ece4;
      --muted:        #7a7f8e;
      --border:       rgba(255,255,255,.07);
      --radius:       12px;
    }

    html, body {
      width: 100%; height: 100vh;
      overflow: hidden;
      font-family: 'Outfit', sans-serif;
      background: var(--bg-dark);
      color: var(--text);
    }

    /* ── LAYOUT ─────────────────────────────── */
    .shell {
      display: flex;
      width: 100%; height: 100vh;
    }

    /* ── SIDEBAR ─────────────────────────────── */
    .sidebar {
      width: var(--sidebar-w);
      min-width: var(--sidebar-w);
      height: 100%;
      background: var(--bg-panel);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      padding: 0 0 24px;
      overflow: hidden;
      position: relative;
    }

    /* subtle top glow */
    .sidebar::before {
      content: '';
      position: absolute;
      top: -60px; left: 50%;
      transform: translateX(-50%);
      width: 180px; height: 180px;
      background: radial-gradient(circle, rgba(232,168,56,.18) 0%, transparent 70%);
      pointer-events: none;
    }

    .brand {
      padding: 28px 24px 22px;
      border-bottom: 1px solid var(--border);
      margin-bottom: 12px;
    }
    .brand-label {
      font-size: .68rem;
      font-weight: 600;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 4px;
    }
    .brand-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.35rem;
      color: var(--text);
      line-height: 1.2;
    }

    /* nav items */
    .nav-section {
      padding: 0 12px;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .nav-label {
      font-size: .65rem;
      font-weight: 600;
      letter-spacing: .14em;
      text-transform: uppercase;
      color: var(--muted);
      padding: 14px 12px 6px;
    }

    .nav-link {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 11px 14px;
      border-radius: 10px;
      text-decoration: none;
      color: var(--muted);
      font-size: .92rem;
      font-weight: 500;
      transition: background .18s, color .18s;
      cursor: pointer;
      border: none;
      background: transparent;
      width: 100%;
      text-align: left;
    }
    .nav-link .icon {
      width: 25px; height: 25px;
      border-radius: 8px;
      background: var(--bg-item);
      display: flex; align-items: center; justify-content: center;
      font-size: 1rem;
      transition: background .18s;
      flex-shrink: 0;
    }
    .nav-link:hover,
    .nav-link.active {
      background: var(--accent-dim);
      color: var(--text);
    }

    .nav-link.active {
      color: var(--accent);
    }

    /* active pill bar */
    .nav-link.active::before {
      display: none;
    }

    /* logout */
    .logout-wrap {
      padding: 0 12px;
    }
    .btn-logout {
      display: flex;
      align-items: center;
      gap: 10px;
      width: 100%;
      padding: 11px 14px;
      border-radius: 10px;
      border: 1px solid rgba(220,53,69,.25);
      background: rgba(220,53,69,.06);
      color: #e06070;
      font-family: 'Outfit', sans-serif;
      font-size: .92rem;
      font-weight: 500;
      cursor: pointer;
      transition: background .18s, border-color .18s;
    }
    .btn-logout:hover {
      background: rgba(220,53,69,.15);
      border-color: rgba(220,53,69,.5);
    }
    .btn-logout .icon {
      width: 25px; height: 25px;
      border-radius: 8px;
      background: rgba(220,53,69,.12);
      display: flex; align-items: center; justify-content: center;
      font-size: 1rem;
    }

    /* ── CONTENT AREA ────────────────────────── */
    .content {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      background: var(--bg-dark);
    }

    /* top bar */
    .topbar {
      height: 56px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      padding: 0 28px;
      gap: 10px;
      background: var(--bg-panel);
      flex-shrink: 0;
    }
    .topbar-breadcrumb {
      font-size: .82rem;
      color: var(--muted);
    }
    .topbar-breadcrumb span {
      color: var(--text);
      font-weight: 500;
    }
    .topbar-dot {
      width: 4px; height: 4px;
      border-radius: 50%;
      background: var(--muted);
    }

    /* iframe */
    .frame-wrap {
      flex: 1;
      overflow: hidden;
      padding: 20px;
    }
    iframe {
      width: 100%;
      height: 100%;
      border: none;
      border-radius: var(--radius);
      background: #fff;
      box-shadow: 0 8px 32px rgba(0,0,0,.35);
    }

    /* entrance animation */
    .sidebar { animation: slideIn .35s ease both; }
    .content  { animation: fadeIn  .4s ease .1s both; }
    @keyframes slideIn {
      from { transform: translateX(-20px); opacity: 0; }
      to   { transform: translateX(0);     opacity: 1; }
    }
    @keyframes fadeIn {
      from { opacity: 0; }
      to   { opacity: 1; }
    }
  </style>
</head>
<body>

<div class="shell">

  <!-- ══ SIDEBAR ══ -->
  <aside class="sidebar">

    <div class="brand">
      <div class="brand-label">Dashboard</div>
      <div class="brand-title">Merchant<br>Panel</div>
    </div>

    <nav class="nav-section">
      <div class="nav-label">Manage</div>

      <a class="nav-link active" href="profile.php" target="Display"
         onclick="setActive(this, 'Profile')">
        <span class="icon"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M3 9.5L10 3l7 6.5V17a1 1 0 01-1 1H4a1 1 0 01-1-1V9.5z"/>
                <path d="M7 18V11h6v7"/>
            </svg></span> Profile
      </a>

      <a class="nav-link" href="categories.php" target="Display"
         onclick="setActive(this, 'Categories')">
        <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
        <circle cx="6" cy="6" r="2"/>
        <circle cx="6" cy="18" r="2"/>
        <path d="M20 4L8 16M8 8l12 12" stroke-linecap="round"/>
    </svg></span> Categories
      </a>

      <a class="nav-link" href="bookingviewmerchant.php" target="Display"
         onclick="setActive(this, 'Bookings')">
        <span class="icon"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="3" y="4" width="14" height="14" rx="2"/>
                <path d="M13 2v4M7 2v4M3 9h14"/>
                <path d="M7 13h2m2 0h2M7 16h2" stroke-linecap="round"/>
            </svg></span> Bookings
      </a>

      <a class="nav-link" href="notification.php" target="Display"
         onclick="setActive(this, 'Notifications')">
        <span class="icon">
          <svg xmlns="http://www.w3.org/2000/svg" 
             width="24" height="24" 
             viewBox="0 0 24 24" 
             fill="none" 
             stroke="currentColor" 
             stroke-width="2" 
             stroke-linecap="round" 
             stroke-linejoin="round">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7"></path>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg></span> Notifications
      </a>
    </nav>

    <div class="logout-wrap">
      <button class="btn-logout" onclick="window.open('logout.php','_self')">
        <span class="icon"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M13 15l4-4m0 0l-4-4m4 4H7m3 4a7 7 0 110-14" stroke-linecap="round" stroke-linejoin="round"/>
            </svg></span> Logout
      </button>
    </div>

  </aside>

  <!-- ══ MAIN CONTENT ══ -->
  <div class="content">

    <div class="topbar">
      <span class="topbar-breadcrumb">Merchant Panel</span>
      <div class="topbar-dot"></div>
      <span class="topbar-breadcrumb"><span id="page-title">Profile</span></span>
    </div>

    <div class="frame-wrap">
      <iframe name="Display" src="profile.php" id="mainFrame"></iframe>
    </div>

  </div>

</div>

<script>
  function setActive(el, title) {
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('page-title').textContent = title;
  }
</script>

</body>
</html>
<?php
else:
    header("Location: user.php");
    exit();
endif;
?>
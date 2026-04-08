<?php
session_start();
if($_SESSION['type']=="user"){
include "Server.php";

/* ================== SEARCH ================== */
$search = "";
if(isset($_GET['search'])){
    $search = trim($_GET['search']);
}

if($search !== ""){
    $sql = "SELECT SHOP_ID, NAME, IMAGES, LOCATION FROM shop WHERE NAME LIKE ? OR LOCATION LIKE ?";
    $stmt = $conn->prepare($sql);
    $likeSearch = "%" . $search . "%";
    $stmt->bind_param("ss", $likeSearch, $likeSearch);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT SHOP_ID, NAME, IMAGES, LOCATION FROM shop";
    $result = $conn->query($sql);
}

$shops = $result->fetch_all(MYSQLI_ASSOC);
$username = $_SESSION['username'] ?? 'Guest';
$initials = strtoupper(substr($username, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HairDec — Find a Shop</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --black:   #0a0806;
    --dark:    #111009;
    --dark2:   #1a1712;
    --dark3:   #252117;
    --gold:    #c9a84c;
    --gold2:   #e8c96a;
    --gold-dim:#7a6530;
    --cream:   #f5f0e8;
    --text:    #d4cfc6;
    --muted:   #7a756c;
    --border:  rgba(201,168,76,0.15);
    --border2: rgba(201,168,76,0.28);
    --nav-w:   240px;
    --nav-w-c: 72px;
}

html, body { height: 100%; }

body {
    font-family: 'Outfit', sans-serif;
    background: var(--black);
    color: var(--text);
    display: flex;
    overflow: hidden;
}

/* noise overlay */
body::before {
    content: '';
    position: fixed; inset: 0;
    background-image:
        linear-gradient(rgba(201,168,76,0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(201,168,76,0.02) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none; z-index: 0;
}

/* ═══════════════════ SIDEBAR ═══════════════════ */
.sidebar {
    width: var(--nav-w);
    min-height: 100vh;
    background: var(--dark);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    position: fixed; top: 0; left: 0; bottom: 0;
    z-index: 50;
    transition: width 0.3s ease;
    overflow: hidden;
}
.sidebar.collapsed { width: var(--nav-w-c); }

/* brand */
.sidebar-brand {
    padding: 28px 22px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.brand-icon {
    width: 36px; height: 36px;
    background: var(--gold);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    font-family: 'Playfair Display', serif;
    font-size: 1rem; font-weight: 900;
    color: var(--black);
}
.brand-text {
    font-family: 'Playfair Display', serif;
    font-size: 1.25rem; font-weight: 900;
    color: var(--gold);
    white-space: nowrap;
    overflow: hidden;
}
.brand-text span { color: var(--cream); font-style: italic; }

/* user info */
.sidebar-user {
    padding: 20px 22px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.user-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--gold-dim), var(--gold));
    display: flex; align-items: center; justify-content: center;
    font-family: 'Outfit', sans-serif;
    font-size: 0.78rem; font-weight: 700;
    color: var(--black); flex-shrink: 0;
}
.user-info { overflow: hidden; }
.user-name {
    font-size: 0.85rem; font-weight: 600;
    color: var(--cream);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.user-role {
    font-size: 0.7rem; font-weight: 300;
    color: var(--muted); letter-spacing: 0.5px;
}

/* nav items */
.sidebar-nav {
    flex: 1;
    padding: 16px 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    overflow-y: auto;
}
.nav-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 14px;
    border-radius: 8px;
    color: var(--muted);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    letter-spacing: 0.2px;
    transition: background 0.18s, color 0.18s;
    white-space: nowrap;
    cursor: pointer;
    border: none;
    background: transparent;
    width: 100%;
    text-align: left;
}
.nav-item svg { flex-shrink: 0; width: 18px; height: 18px; }
.nav-item:hover { background: rgba(201,168,76,0.06); color: var(--text); }
.nav-item.active {
    background: rgba(201,168,76,0.1);
    color: var(--gold);
    border: 1px solid var(--border);
}
.nav-item.active svg { color: var(--gold); }

.nav-section-label {
    font-size: 0.62rem; font-weight: 600;
    letter-spacing: 1.8px; text-transform: uppercase;
    color: rgba(122,117,108,0.5);
    padding: 10px 14px 4px;
    white-space: nowrap;
    overflow: hidden;
}

/* logout at bottom */
.sidebar-footer {
    padding: 12px;
    border-top: 1px solid var(--border);
    flex-shrink: 0;
}
.logout-btn {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 14px;
    width: 100%;
    border-radius: 8px;
    background: transparent;
    border: none;
    cursor: pointer;
    color: var(--muted);
    font-family: 'Outfit', sans-serif;
    font-size: 0.875rem; font-weight: 500;
    text-decoration: none;
    transition: background 0.18s, color 0.18s;
    white-space: nowrap;
}
.logout-btn svg { flex-shrink: 0; width: 18px; height: 18px; }
.logout-btn:hover {
    background: rgba(224,92,92,0.08);
    color: #f08080;
}

/* collapse toggle */
.collapse-btn {
    position: absolute;
    top: 28px; right: -12px;
    width: 24px; height: 24px;
    background: var(--dark2);
    border: 1px solid var(--border2);
    border-radius: 50%;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: var(--gold-dim);
    transition: background 0.2s, color 0.2s, transform 0.3s;
    z-index: 51;
}
.collapse-btn:hover { background: var(--dark3); color: var(--gold); }
.sidebar.collapsed .collapse-btn { transform: rotate(180deg); }
.sidebar.collapsed .brand-text,
.sidebar.collapsed .user-info,
.sidebar.collapsed .nav-item span,
.sidebar.collapsed .nav-section-label,
.sidebar.collapsed .logout-btn span { display: none; }

/* ═══════════════════ MAIN CONTENT ═══════════════════ */
.main {
    margin-left: var(--nav-w);
    flex: 1;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    transition: margin-left 0.3s ease;
    position: relative; z-index: 1;
    overflow-y: auto;
}
.main.expanded { margin-left: var(--nav-w-c); }

/* ── Top bar ── */
.topbar {
    position: sticky; top: 0; z-index: 40;
    background: rgba(10,8,6,0.85);
    backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--border);
    padding: 16px 36px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}
.topbar-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.3rem; font-weight: 900;
    color: var(--cream);
    white-space: nowrap;
}
.topbar-title em { font-style: italic; color: var(--gold); }

/* Search */
.search-wrap {
    display: flex;
    align-items: center;
    background: var(--dark2);
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    transition: border-color 0.2s, box-shadow 0.2s;
    flex: 1; max-width: 420px;
}
.search-wrap:focus-within {
    border-color: var(--gold-dim);
    box-shadow: 0 0 0 3px rgba(201,168,76,0.08);
}
.search-wrap input {
    flex: 1;
    padding: 10px 16px;
    background: transparent;
    border: none; outline: none;
    color: var(--cream);
    font-family: 'Outfit', sans-serif;
    font-size: 0.88rem; font-weight: 400;
}
.search-wrap input::placeholder { color: var(--muted); }
.search-wrap button {
    padding: 10px 18px;
    background: var(--gold);
    border: none; cursor: pointer;
    color: var(--black);
    font-family: 'Outfit', sans-serif;
    font-size: 0.78rem; font-weight: 700;
    letter-spacing: 1px; text-transform: uppercase;
    transition: background 0.2s;
    display: flex; align-items: center; gap: 6px;
}
.search-wrap button:hover { background: var(--gold2); }

/* ── Page content ── */
.content {
    padding: 36px 36px 60px;
}

/* results meta */
.results-meta {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 28px;
    flex-wrap: wrap; gap: 8px;
}
.results-count {
    font-size: 0.78rem; font-weight: 300; color: var(--muted);
}
.results-count strong { color: var(--cream); font-weight: 600; }
.search-tag {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(201,168,76,0.1);
    border: 1px solid var(--border2);
    color: var(--gold);
    font-size: 0.75rem; font-weight: 500;
    padding: 4px 12px;
    border-radius: 99px;
}
.search-tag a {
    color: var(--muted); text-decoration: none;
    font-size: 0.7rem; margin-left: 4px;
    transition: color 0.2s;
}
.search-tag a:hover { color: var(--text); }

/* ═══════════════════ PINTEREST MASONRY GRID ═══════════════════ */
.masonry-grid {
    columns: 4 240px;
    column-gap: 16px;
}

.shop-card {
    break-inside: avoid;
    margin-bottom: 16px;
    border-radius: 12px;
    overflow: hidden;
    background: var(--dark2);
    border: 1px solid var(--border);
    cursor: pointer;
    position: relative;
    display: block;
    text-decoration: none;
    color: inherit;
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s;
    animation: cardIn 0.5s ease both;
}
.shop-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 40px rgba(0,0,0,0.5);
    border-color: var(--gold-dim);
}

/* staggered animation per card */
<?php for($i=1;$i<=20;$i++): ?>
.shop-card:nth-child(<?php echo $i; ?>) { animation-delay: <?php echo ($i-1)*0.05; ?>s; }
<?php endfor; ?>

.shop-img-wrap {
    width: 100%;
    overflow: hidden;
    position: relative;
}
/* randomise heights for pinterest feel */
.shop-img-wrap img {
    width: 100%;
    display: block;
    object-fit: cover;
    transition: transform 0.5s ease;
}
.shop-card:hover .shop-img-wrap img { transform: scale(1.06); }

/* fallback gradient when no image */
.shop-img-placeholder {
    width: 100%;
    aspect-ratio: 1;
    background: linear-gradient(135deg, var(--dark3), var(--dark2));
    display: flex; align-items: center; justify-content: center;
    font-family: 'Playfair Display', serif;
    font-size: 2.5rem; font-weight: 900;
    color: rgba(201,168,76,0.2);
}

/* gold shimmer on hover */
.shop-img-wrap::after {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(to bottom, transparent 50%, rgba(10,8,6,0.7) 100%);
    opacity: 0;
    transition: opacity 0.3s;
}
.shop-card:hover .shop-img-wrap::after { opacity: 1; }

/* quick-book badge */
.quick-book {
    position: absolute; top: 10px; right: 10px;
    background: var(--gold);
    color: var(--black);
    font-size: 0.65rem; font-weight: 700;
    letter-spacing: 1px; text-transform: uppercase;
    padding: 4px 10px;
    border-radius: 99px;
    opacity: 0;
    transform: translateY(-4px);
    transition: opacity 0.25s, transform 0.25s;
}
.shop-card:hover .quick-book { opacity: 1; transform: translateY(0); }

/* card body */
.shop-body {
    padding: 14px 16px 16px;
}
.shop-name {
    font-family: 'Playfair Display', serif;
    font-size: 1rem; font-weight: 700;
    color: var(--cream);
    margin-bottom: 5px;
    line-height: 1.25;
}
.shop-location {
    display: flex; align-items: center; gap: 5px;
    font-size: 0.75rem; font-weight: 300;
    color: var(--muted);
}
.shop-location svg { width: 11px; height: 11px; flex-shrink: 0; color: var(--gold-dim); }

/* ── Empty state ── */
.empty-state {
    text-align: center; padding: 80px 24px;
    animation: cardIn 0.5s ease both;
}
.empty-icon {
    width: 64px; height: 64px;
    background: var(--dark2);
    border: 1px solid var(--border2);
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px; font-size: 1.6rem;
}
.empty-state h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.2rem; color: var(--cream); margin-bottom: 8px;
}
.empty-state p { font-size: 0.88rem; color: var(--muted); font-weight: 300; }

/* ── Animations ── */
@keyframes cardIn {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Responsive ── */
@media (max-width: 768px) {
    .sidebar { width: var(--nav-w-c); }
    .sidebar .brand-text, .sidebar .user-info,
    .sidebar .nav-item span, .sidebar .nav-section-label,
    .sidebar .logout-btn span { display: none; }
    .main { margin-left: var(--nav-w-c); }
    .topbar { padding: 14px 20px; }
    .content { padding: 24px 16px 48px; }
    .masonry-grid { columns: 2 160px; }
    .topbar-title { display: none; }
}
</style>
</head>
<body>

<!-- ═══════════════════ SIDEBAR ═══════════════════ -->
<aside class="sidebar" id="sidebar">

    <!-- collapse toggle -->
    <button class="collapse-btn" id="collapseBtn" title="Toggle sidebar">
        <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
            <path d="M6.5 1.5L3 5l3.5 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>

    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon">H</div>
        <div class="brand-text">Hair<span>Dec</span></div>
    </div>

    <!-- User -->
    <div class="sidebar-user">
        <div class="user-avatar"><?php echo $initials; ?></div>
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
            <div class="user-role">Customer</div>
        </div>
    </div>

    <!-- Nav -->
    <nav class="sidebar-nav">
        <div class="nav-section-label">Menu</div>

        <a href="userHome.php" class="nav-item active">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M3 9.5L10 3l7 6.5V17a1 1 0 01-1 1H4a1 1 0 01-1-1V9.5z"/>
                <path d="M7 18V11h6v7"/>
            </svg>
            <span>Browse Shops</span>
        </a>

        <a href="bookingview.php" class="nav-item">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="3" y="4" width="14" height="14" rx="2"/>
                <path d="M13 2v4M7 2v4M3 9h14"/>
                <path d="M7 13h2m2 0h2M7 16h2" stroke-linecap="round"/>
            </svg>
            <span>My Bookings</span>
        </a>
    </nav>

    <!-- Logout -->
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M13 15l4-4m0 0l-4-4m4 4H7m3 4a7 7 0 110-14" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Logout</span>
        </a>
    </div>

</aside>

<!-- ═══════════════════ MAIN ═══════════════════ -->
<main class="main" id="main">

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-title">Get your <em>Fav</em></div>

        <form method="GET" action="" class="search-wrap">
            <input type="text" name="search"
                   placeholder="Search by name or location…"
                   value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">
                <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                    <circle cx="5.5" cy="5.5" r="4" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M8.5 8.5l2.5 2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                Search
            </button>
        </form>
    </div>

    <!-- Content -->
    <div class="content">

        <!-- Results meta -->
        <div class="results-meta">
            <p class="results-count">
                Showing <strong><?php echo count($shops); ?></strong>
                <?php echo count($shops) === 1 ? 'shop' : 'shops'; ?>
                <?php if($search !== ""): ?>in <strong>"<?php echo htmlspecialchars($search); ?>"</strong><?php endif; ?>
            </p>
            <?php if($search !== ""): ?>
            <span class="search-tag">
                <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                    <circle cx="4" cy="4" r="3" stroke="currentColor" stroke-width="1.2"/>
                    <path d="M6.5 6.5l2 2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
                <?php echo htmlspecialchars($search); ?>
                <a href="userHome.php">✕</a>
            </span>
            <?php endif; ?>
        </div>

        <!-- Pinterest Grid -->
        <?php if(empty($shops)): ?>
        <div class="empty-state">
            <div class="empty-icon">✂️</div>
            <h3>No shops found</h3>
            <p>Try a different search term or browse all shops.</p>
        </div>
        <?php else: ?>
        <div class="masonry-grid">
            <?php
            /* Vary image heights for genuine Pinterest feel */
            $heights = [220, 280, 200, 320, 260, 240, 300, 180, 290, 250];
            foreach($shops as $i => $row):
                $h = $heights[$i % count($heights)];
            ?>
            <a class="shop-card"
               href="userHomedetail.php?shop_id=<?php echo $row['SHOP_ID']; ?>">

                <div class="shop-img-wrap">
                    <?php if(!empty($row['IMAGES'])): ?>
                    <img src="<?php echo htmlspecialchars($row['IMAGES']); ?>"
                         alt="<?php echo htmlspecialchars($row['NAME']); ?>"
                         style="height:<?php echo $h; ?>px;">
                    <?php else: ?>
                    <div class="shop-img-placeholder" style="height:<?php echo $h; ?>px;">
                        <?php echo strtoupper(substr($row['NAME'],0,1)); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="quick-book">Book Now</div>

                <div class="shop-body">
                    <div class="shop-name"><?php echo htmlspecialchars($row['NAME']); ?></div>
                    <div class="shop-location">
                        <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.4">
                            <path d="M6 1C4.067 1 2.5 2.567 2.5 4.5 2.5 7.5 6 11 6 11S9.5 7.5 9.5 4.5C9.5 2.567 7.933 1 6 1z"/>
                            <circle cx="6" cy="4.5" r="1.2"/>
                        </svg>
                        <?php echo htmlspecialchars($row['LOCATION']); ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<script>
const sidebar    = document.getElementById('sidebar');
const main       = document.getElementById('main');
const collapseBtn = document.getElementById('collapseBtn');

collapseBtn.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    main.classList.toggle('expanded');
});
</script>
</body>
</html>
<?php
}
else{
    header("Location: ../merchant.php");
}
?>
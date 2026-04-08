<?php
session_start();
include "Server.php";
if(!isset($_SESSION['SHOP_ID'])){
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT SHOP_ID FROM shop WHERE USERS_ID=? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if($result){
        $_SESSION['SHOP_ID'] = $result['SHOP_ID'];
        $shop_id = $result['SHOP_ID'];
    } else {
        die("No shop found. Please create a shop first.");
    }
}
$shop_id = $_SESSION['SHOP_ID'];

$flash = "";
$flash_type = "";

/* ================= ADD CATEGORY ================= */
if(isset($_POST['add_category'])){
    $name  = trim($_POST['name']);
    $price = max(0, $_POST['price']);
    $stmt = $conn->prepare("INSERT INTO category (SHOP_ID, NAME, PRICE) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $shop_id, $name, $price);
    $stmt->execute();
    $flash = "Category \"" . htmlspecialchars($name) . "\" added successfully.";
    $flash_type = "success";
}

/* ================= UPDATE CATEGORY ================= */
if(isset($_POST['update_category'])){
    $id    = $_POST['category_id'];
    $name  = trim($_POST['name']);
    $price = max(0, $_POST['price']);
    $stmt = $conn->prepare("UPDATE category SET NAME=?, PRICE=? WHERE CATEGORIES_ID=? AND SHOP_ID=?");
    $stmt->bind_param("ssii", $name, $price, $id, $shop_id);
    $stmt->execute();
    $flash = "Category updated.";
    $flash_type = "success";
}

/* ================= DELETE CATEGORY ================= */
if(isset($_POST['delete_category'])){
    $id = $_POST['category_id'];
    $stmt = $conn->prepare("DELETE FROM category WHERE CATEGORIES_ID=? AND SHOP_ID=?");
    $stmt->bind_param("ii", $id, $shop_id);
    $stmt->execute();
    $flash = "Category deleted.";
    $flash_type = "danger";
}

/* ================= FETCH DATA ================= */
$stmt = $conn->prepare("SELECT * FROM category WHERE SHOP_ID=? ORDER BY CATEGORIES_ID DESC");
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$result = $stmt->get_result();
$categories = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Categories</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:        #080b12;
    --surface:   #0e1420;
    --surface2:  #141928;
    --border:    rgba(255,255,255,0.07);
    --border2:   rgba(255,255,255,0.12);
    --accent:    #4f8fff;
    --accent2:   #7c5cfc;
    --green:     #22c97a;
    --red:       #ff4f6a;
    --text:      #e8eaf0;
    --muted:     #6b7280;
    --glow:      rgba(79,143,255,0.18);
}

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    padding: 0;
    overflow-x: hidden;
}
body::-webkit-scrollbar {
    display: none;
}

/* ── Background grid ── */
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
        linear-gradient(rgba(79,143,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(79,143,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
    z-index: 0;
}

/* ── Glow orbs ── */
body::after {
    content: '';
    position: fixed;
    top: -200px; left: -200px;
    width: 600px; height: 600px;
    background: radial-gradient(circle, rgba(124,92,252,0.08) 0%, transparent 70%);
    pointer-events: none;
    z-index: 0;
}

.page-wrapper {
    position: relative;
    z-index: 1;
    max-width: 860px;
    margin: 0 auto;
    padding: 48px 24px 80px;
}

/* ── Header ── */
.page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 40px;
    padding-bottom: 28px;
    border-bottom: 1px solid var(--border);
}
.page-header-left h1 {
    font-family: 'Syne', sans-serif;
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: -0.5px;
    background: linear-gradient(135deg, #fff 30%, #7c9fff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1.1;
}
.page-header-left p {
    font-size: 0.85rem;
    color: var(--muted);
    margin-top: 6px;
    font-weight: 300;
}
.category-count {
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    color: white;
    font-family: 'Syne', sans-serif;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: 6px 14px;
    border-radius: 99px;
}

/* ── Flash message ── */
.flash {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 13px 18px;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 28px;
    animation: slideDown 0.35s ease;
}
.flash.success {
    background: rgba(34,201,122,0.1);
    border: 1px solid rgba(34,201,122,0.25);
    color: #5ee8a6;
}
.flash.danger {
    background: rgba(255,79,106,0.1);
    border: 1px solid rgba(255,79,106,0.25);
    color: #ff8fa1;
}
.flash-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}
.flash.success .flash-dot { background: var(--green); }
.flash.danger  .flash-dot { background: var(--red); }

/* ── Cards ── */
.card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 28px 30px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}
.card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
}

.card-title {
    font-family: 'Syne', sans-serif;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 20px;
}

/* ── Add form ── */
.add-form {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.input-wrap {
    position: relative;
    flex: 1;
    min-width: 160px;
}
.input-wrap label {
    display: block;
    font-size: 0.7rem;
    font-weight: 500;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 6px;
}
.input-wrap input {
    width: 100%;
    padding: 11px 14px;
    background: var(--surface2);
    border: 1px solid var(--border2);
    border-radius: 10px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.input-wrap input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(79,143,255,0.12);
}
.input-wrap input::placeholder { color: #3a4050; }

/* ── Buttons ── */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 11px 20px;
    border: none;
    border-radius: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
    white-space: nowrap;
    text-decoration: none;
}
.btn:hover { transform: translateY(-1px); opacity: 0.9; }
.btn:active { transform: translateY(0); }

.btn-add {
    background: linear-gradient(135deg, var(--green), #1ba862);
    color: #fff;
    box-shadow: 0 4px 18px rgba(34,201,122,0.2);
    align-self: flex-end;
    margin-top: 22px;
}
.btn-edit {
    background: rgba(79,143,255,0.12);
    border: 1px solid rgba(79,143,255,0.25);
    color: #7caaff;
    padding: 8px 14px;
    font-size: 0.82rem;
}
.btn-edit:hover { background: rgba(79,143,255,0.2); }
.btn-delete {
    background: rgba(255,79,106,0.1);
    border: 1px solid rgba(255,79,106,0.2);
    color: #ff8fa1;
    padding: 8px 14px;
    font-size: 0.82rem;
}
.btn-delete:hover { background: rgba(255,79,106,0.18); }

/* ── Table ── */
.table-wrapper { overflow-x: auto; }

table { width: 100%; border-collapse: collapse; }

thead tr {
    border-bottom: 1px solid var(--border2);
}
thead th {
    padding: 10px 14px;
    text-align: left;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: var(--muted);
}
thead th:last-child { text-align: right; }

tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background 0.15s;
}
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,0.02); }

tbody td {
    padding: 12px 14px;
    vertical-align: middle;
}
tbody td:last-child { text-align: right; }

/* ── Inline edit inputs ── */
.row-input {
    padding: 8px 12px;
    background: var(--surface2);
    border: 1px solid var(--border2);
    border-radius: 8px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem;
    outline: none;
    width: 100%;
    max-width: 220px;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.row-input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(79,143,255,0.12);
}

.price-input { max-width: 110px; }

.price-badge {
    font-family: 'Syne', sans-serif;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--green);
    background: rgba(34,201,122,0.1);
    border: 1px solid rgba(34,201,122,0.18);
    padding: 3px 10px;
    border-radius: 99px;
    display: inline-block;
    margin-right: 8px;
    letter-spacing: 0.3px;
}

.action-group {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    justify-content: flex-end;
}

/* ── Empty state ── */
.empty-state {
    text-align: center;
    padding: 48px 20px;
    color: var(--muted);
}
.empty-state .icon {
    font-size: 2.5rem;
    margin-bottom: 12px;
    opacity: 0.4;
}
.empty-state p { font-size: 0.9rem; }

/* ── Animations ── */
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
.card { animation: fadeIn 0.4s ease both; }
.card:nth-child(2) { animation-delay: 0.07s; }
</style>
</head>
<body>

<div class="page-wrapper">

    <!-- Header -->
    <div class="page-header">
        <div class="page-header-left">
            <h1>Services &amp;<br>Categories</h1>
            <p>Manage the services your shop offers</p>
        </div>
        <span class="category-count"><?php echo count($categories); ?> service<?php echo count($categories) !== 1 ? 's' : ''; ?></span>
    </div>

    <!-- Flash message -->
    <?php if(!empty($flash)): ?>
    <div class="flash <?php echo $flash_type; ?>">
        <span class="flash-dot"></span>
        <?php echo $flash; ?>
    </div>
    <?php endif; ?>

    <!-- Add Category Card -->
    <div class="card">
        <div class="card-title">Add new service</div>
        <form method="POST" class="add-form">
            <div class="input-wrap">
                <label for="svc-name">Service name</label>
                <input type="text" id="svc-name" name="name" placeholder="e.g. Haircut, Massage…" required>
            </div>
            <div class="input-wrap">
                <label for="svc-price">Price (Rs.)</label>
                <input type="number" id="svc-price" name="price" step="0.01" min="0" placeholder="0.00" required>
            </div>
            <button type="submit" name="add_category" class="btn btn-add">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Add Service
            </button>
        </form>
    </div>

    <!-- Categories Table Card -->
    <div class="card">
        <div class="card-title">Current services</div>

        <?php if(empty($categories)): ?>
        <div class="empty-state">
            <div class="icon">✦</div>
            <p>No services yet. Add your first one above.</p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Service name</th>
                    <th>Price</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($categories as $row): ?>
            <tr>
                <form method="POST">
                <td>
                    <input class="row-input"
                           type="text"
                           name="name"
                           value="<?php echo htmlspecialchars($row['NAME']); ?>"
                           required>
                </td>
                <td>
                    <span class="price-badge">Rs.</span>
                    <input class="row-input price-input"
                           type="number"
                           step="0.01"
                           name="price"
                           min="0"
                           value="<?php echo $row['PRICE']; ?>"
                           required>
                </td>
                <td>
                    <input type="hidden" name="category_id" value="<?php echo $row['CATEGORIES_ID']; ?>">
                    <div class="action-group">
                        <button type="submit" name="update_category" class="btn btn-edit">
                            <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M9.5 1.5l2 2L4 11H2v-2L9.5 1.5z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Save
                        </button>
                        <button type="submit" name="delete_category" class="btn btn-delete"
                                onclick="return confirm('Delete this service?')">
                            <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M2 3.5h9M5 3.5V2.5h3v1M5.5 6v3.5M7.5 6v3.5M3 3.5l.5 7h6l.5-7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Delete
                        </button>
                    </div>
                </td>
                </form>
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
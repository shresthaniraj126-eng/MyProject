<?php
session_start();
include "server.php";

/* ============================================================
   🔐 AUTH CHECK
   ============================================================ */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

/* ============================================================
   🖼️  IMAGE UPLOAD HELPER  (secure)
   ============================================================ */
function uploadImage() {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return null; // no new image chosen
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo         = finfo_open(FILEINFO_MIME_TYPE);
    $mime          = finfo_file($finfo, $_FILES['image']['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_types)) {
        return false; // invalid type — caller surfaces error
    }

    if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
        return false; // too large
    }

    $ext_map = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    $folder = "images/";
    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    $fileName   = time() . "_" . bin2hex(random_bytes(8)) . "." . $ext_map[$mime];
    $targetFile = $folder . $fileName;

    if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
        return false;
    }

    return $targetFile;
}

/* ============================================================
   ✅ VALIDATE INPUTS
   ============================================================ */
function validateInputs($data) {
    $errors = [];

    if (empty(trim($data['name']))) {
        $errors[] = "Shop name is required.";
    }

    if (empty(trim($data['location']))) {
        $errors[] = "Location is required.";
    }

    if (!preg_match('/^[0-9\+\-\s\(\)]{7,20}$/', $data['contact'])) {
        $errors[] = "Contact number is invalid (7–20 digits, +  -  () allowed).";
    }

    if (!is_numeric($data['seat_no']) || (int)$data['seat_no'] < 1) {
        $errors[] = "Number of seats must be at least 1.";
    }

    return $errors;
}

/* ============================================================
   🔄 HANDLE UPDATE
   ============================================================ */
$update_errors   = [];
$update_success  = false;

if (isset($_POST['update_shop'])) {

    $raw = [
        'name'        => trim($_POST['shop_name']        ?? ''),
        'description' => trim($_POST['shop_description'] ?? ''),
        'location'    => trim($_POST['location']         ?? ''),
        'contact'     => trim($_POST['contact']          ?? ''),
        'seat_no'     => $_POST['seat_no']               ?? 0,
    ];

    $update_errors = validateInputs($raw);

    // Handle image upload
    $uploadResult = uploadImage();

    if ($uploadResult === false) {
        $update_errors[] = "Image must be JPG, PNG, GIF or WEBP and under 2 MB.";
    }

    if (empty($update_errors)) {

        if ($uploadResult !== null) {
            // New image uploaded — update image column too
            $sql  = "UPDATE shop 
                     SET NAME=?, DESCRIPTION=?, LOCATION=?, CONTACT=?, SEAT_NO=?, IMAGES=? 
                     WHERE USERS_ID=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssssisi",
                $raw['name'],
                $raw['description'],
                $raw['location'],
                $raw['contact'],
                $raw['seat_no'],
                $uploadResult,
                $user_id
            );
        } else {
            // No new image — leave IMAGES column unchanged
            $sql  = "UPDATE shop 
                     SET NAME=?, DESCRIPTION=?, LOCATION=?, CONTACT=?, SEAT_NO=? 
                     WHERE USERS_ID=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssssi" . "i",
                $raw['name'],
                $raw['description'],
                $raw['location'],
                $raw['contact'],
                $raw['seat_no'],
                $user_id
            );
        }

        if (!$stmt) {
            $update_errors[] = "Database prepare failed.";
        } elseif ($stmt->execute()) {
            $stmt->close();
            // Store SHOP_ID in session while we still have a connection
            $sid_sql  = "SELECT SHOP_ID FROM shop WHERE USERS_ID=? LIMIT 1";
            $sid_stmt = $conn->prepare($sid_sql);
            $sid_stmt->bind_param("i", $user_id);
            $sid_stmt->execute();
            $sid_row = $sid_stmt->get_result()->fetch_assoc();
            $sid_stmt->close();
            if ($sid_row) {
                $_SESSION['SHOP_ID'] = (int) $sid_row['SHOP_ID'];
            }
            // PRG — prevents duplicate submission on refresh
            header("Location: profile.php?updated=1");
            exit();
        } else {
            $update_errors[] = "Error updating shop: " . htmlspecialchars($stmt->error);
        }
    }
}

/* ============================================================
   📥 FETCH SHOP + USER DATA
   ============================================================ */
$sql  = "SELECT shop.SHOP_ID, shop.NAME, shop.DESCRIPTION, shop.IMAGES,
                shop.LOCATION, shop.CONTACT, shop.SEAT_NO,
                users.USERNAME
         FROM shop
         JOIN users ON shop.USERS_ID = users.USERS_ID
         WHERE shop.USERS_ID = ? LIMIT 1";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    // Merchant has no shop yet
    header("Location: add_shop.php");
    exit();
}

// Store SHOP_ID in session
$_SESSION['SHOP_ID'] = (int) $data['SHOP_ID'];

$shopName    = $data['NAME'];
$description = $data['DESCRIPTION'];
$username    = $data['USERNAME'];
$image       = $data['IMAGES'] ?: "images/default.png";
$location    = $data['LOCATION'];
$contact     = $data['CONTACT'];
$seat_no     = $data['SEAT_NO'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shop Profile</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:       #0f1117;
    --card:     #0e1420;
    --accent:   #c84b2f;
    --text:     #e8eaf0;
    --muted:    #6b6b6b;
    --border:   #ddd8d0;
    --radius:   16px;
    --shadow:   0 8px 32px rgba(0,0,0,.09);
  }

  body {
    min-height: 100vh;
    background: var(--bg);
    font-family: 'DM Sans', sans-serif;
    color: var(--text);
    padding: 32px 24px;
  }
  
  body::-webkit-scrollbar {
    display: none;
  }

  .hero_container {
    display: flex;
    gap: 24px;
    max-width: 1100px;
    margin: 0 auto;
    flex-wrap: wrap;
  }

  .card {
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    border: 1px solid rgba(255,255,255,0.12);
    padding: 28px;
    flex: 1 1 340px;
    display: flex;
    flex-direction: column;
    gap: 18px;
    animation: fadeUp .4s ease both;
  }
  .card:nth-child(2) { animation-delay: .1s; }

  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  /* Shop hero banner */
  .shop_header {
    height: 200px;
    border-radius: 12px;
    display: flex;
    align-items: flex-end;
    padding: 18px;
    color: #fff;
    font-family: 'DM Serif Display', serif;
    font-size: 1.8rem;
    background-size: cover;
    background-position: center;
    position: relative;
    overflow: hidden;
  }
  .shop_header::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,.65) 0%, transparent 60%);
    border-radius: 12px;
  }
  .shop_header span { position: relative; z-index: 1; }

  /* Info rows */
  .info-row { font-size: .95rem; line-height: 1.6; }
  .info-row strong { color: var(--muted); font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; display: block; margin-bottom: 2px; }

  details summary { cursor: pointer; font-weight: 600; font-size: .9rem; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; }
  details p { margin-top: 8px; font-size: .95rem; line-height: 1.7; }

  /* Success / error notices */
  .notice {
    padding: 11px 15px;
    border-radius: 9px;
    font-size: .875rem;
    line-height: 1.6;
  }
  .notice.success { background: #edfaf3; border-left: 3px solid #27ae60; color: #1e8449; }
  .notice.error   { background: #fdf0ed; border-left: 3px solid var(--accent); color: var(--accent); }
  .notice.error p + p { margin-top: 4px; }

  /* Buttons */
  .btn {
    padding: 11px 20px;
    border: none;
    border-radius: 10px;
    font-family: inherit;
    font-size: .9rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform .15s, box-shadow .15s;
  }
  .btn:active { transform: scale(.97); }
  .btn-primary   { background: var(--accent); color: #fff; box-shadow: 0 4px 12px rgba(200,75,47,.3); }
  .btn-primary:hover { box-shadow: 0 6px 18px rgba(200,75,47,.4); }
  .btn-secondary { background: var(--bg); color: var(--muted); border: 1.5px solid var(--border); }
  .btn-secondary:hover { background: #ece7e0; }

  /* Edit form */
  .edit_form { display: none; flex-direction: column; gap: 12px; padding-top: 6px; border-top: 1px solid var(--border); }
  .edit_form.open { display: flex; }

  .field label {
    font-size: .75rem;
    font-weight: 600;
    color: var(--muted);
    letter-spacing: .04em;
    text-transform: uppercase;
    display: block;
    margin-bottom: 4px;
  }
  .field input,
  .field textarea {
    width: 100%;
    padding: 11px 13px;
    border: 1.5px solid var(--border);
    border-radius: 9px;
    font-family: inherit;
    font-size: .9rem;
    background: var(--bg);
    color: var(--text);
    outline: none;
    transition: border-color .2s, box-shadow .2s;
    resize: vertical;
  }
  .field input:focus, .field textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(200,75,47,.1);
  }
  .field textarea { min-height: 80px; }

  /* Image upload */
  #image_selector { display: none; }
  .upload-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 14px;
    border: 1.5px dashed var(--border);
    border-radius: 9px;
    color: var(--muted);
    font-size: .875rem;
    cursor: pointer;
    background: #faf8f6;
    transition: border-color .2s, color .2s;
    user-select: none;
  }
  .upload-btn:hover { border-color: var(--accent); color: var(--accent); }
  #file-label { font-size: .78rem; color: var(--muted); margin-top: 4px; display: block; }

  .btn-row { display: flex; gap: 10px; }

  /* Right card — user info */
  .shop_img {
    width: 100%;
    max-width: 260px;
    border-radius: 12px;
    box-shadow: 0 4px 14px rgba(0,0,0,.12);
    display: block;
    margin: 0 auto;
  }
  .user-name {
    font-family: 'DM Serif Display', serif;
    font-size: 1.6rem;
  }
  .badge {
    display: inline-block;
    background: #fff3ef;
    color: var(--accent);
    font-size: .78rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    padding: 4px 12px;
    border-radius: 20px;
    border: 1px solid #f5c9be;
  }

  @media (max-width: 680px) {
    .hero_container { flex-direction: column; }
  }
</style>
</head>
<body>

<div class="hero_container">

  <!-- ====== LEFT CARD: Shop Info + Edit ====== -->
  <div class="card">

    <div class="shop_header"
         style="background-image:url('<?= htmlspecialchars($image) ?>')">
      <span><?= htmlspecialchars($shopName) ?></span>
    </div>

    <?php if (isset($_GET['updated'])): ?>
      <div class="notice success">&#10003; Shop updated successfully.</div>
    <?php endif; ?>

    <?php if (!empty($update_errors)): ?>
      <div class="notice error">
        <?php foreach ($update_errors as $e): ?>
          <p>&#9679; <?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="info-row"><strong>Location</strong><?= htmlspecialchars($location) ?></div>
    <div class="info-row"><strong>Contact</strong><?= htmlspecialchars($contact) ?></div>
    <div class="info-row"><strong>Seats</strong><?= htmlspecialchars($seat_no) ?></div>

    <details open>
      <summary>Description</summary>
      <p><?= nl2br(htmlspecialchars($description)) ?></p>
    </details>

    <button class="btn btn-secondary" onclick="toggleEdit(this)">&#9998; Edit Shop</button>

    <!-- Edit form -->
    <form method="post" enctype="multipart/form-data" class="edit_form" id="editForm">

      <div class="field">
        <label>Shop Name</label>
        <input type="text" name="shop_name"
               value="<?= htmlspecialchars($shopName) ?>" required>
      </div>

      <div class="field">
        <label>Description</label>
        <textarea name="shop_description"><?= htmlspecialchars($description) ?></textarea>
      </div>

      <div class="field">
        <label>Location</label>
        <input type="text" name="location"
               value="<?= htmlspecialchars($location) ?>" required>
      </div>

      <div class="field">
        <label>Contact Number</label>
        <input type="tel" name="contact"
               value="<?= htmlspecialchars($contact) ?>" required>
      </div>

      <div class="field">
        <label>Number of Seats</label>
        <input type="number" name="seat_no"
               value="<?= htmlspecialchars($seat_no) ?>" min="1" required>
      </div>

      <div class="field">
        <label>Shop Image (JPG / PNG / GIF / WEBP, max 2 MB)</label>
        <input type="file" name="image" id="image_selector" accept="image/*">
        <div class="upload-btn"
             onclick="document.getElementById('image_selector').click()">
          &#128247; Change image
        </div>
        <span id="file-label">No file chosen — current image kept</span>
      </div>

      <div class="btn-row">
        <button type="submit" name="update_shop" class="btn btn-primary">Save Changes</button>
        <button type="button" class="btn btn-secondary"
                onclick="toggleEdit(null)">Cancel</button>
      </div>

    </form>

  </div><!-- /left card -->

  <!-- ====== RIGHT CARD: Merchant Info ====== -->
  <div class="card" style="flex: 0 1 300px; align-items: center; text-align: center;">

    <img src="<?= htmlspecialchars($image) ?>"
         alt="Shop image"
         class="shop_img">

    <div class="user-name"><?= htmlspecialchars($username) ?></div>
    <span class="badge">Merchant</span>

  </div>

</div><!-- /hero_container -->

<script>
function toggleEdit(btn) {
    const form = document.getElementById('editForm');
    const isOpen = form.classList.toggle('open');
    if (btn) btn.textContent = isOpen ? '✕ Cancel Edit' : '✎ Edit Shop';
}

document.getElementById('image_selector').addEventListener('change', function () {
    document.getElementById('file-label').textContent =
        this.files.length ? this.files[0].name : 'No file chosen — current image kept';
});
</script>

</body>
</html>
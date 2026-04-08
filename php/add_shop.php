<?php
session_start();
include "server.php";

/* ============================================================
   🔐 AUTH CHECK
   ============================================================ */
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: merchant.php");
        exit();
    }
    return (int) $_SESSION['user_id'];
}

/* ============================================================
   🔎 CHECK IF MERCHANT ALREADY HAS A SHOP
   ============================================================ */
function hasShop($conn, $user_id) {
    $sql  = "SELECT SHOP_ID FROM shop WHERE USERS_ID = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Prepare failed: " . htmlspecialchars($conn->error));
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    return $result->num_rows > 0;
}

/* ============================================================
   🖼️  HANDLE IMAGE UPLOAD  (with security validation)
   ============================================================ */
function uploadImage() {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return "images/default.png";          // default fallback
    }

    /* --- allowed MIME types ---------------------------------- */
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo         = finfo_open(FILEINFO_MIME_TYPE);
    $mime          = finfo_file($finfo, $_FILES['image']['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_types)) {
        return null;   // caller will surface the error
    }

    /* --- max 2 MB ------------------------------------------- */
    if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
        return null;
    }

    /* --- safe file extension --------------------------------- */
    $ext_map = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $ext = $ext_map[$mime];

    $folder = "images/";
    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    $fileName   = time() . "_" . bin2hex(random_bytes(8)) . "." . $ext;
    $targetFile = $folder . $fileName;

    if (!move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
        return null;
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
        $errors[] = "Contact number is invalid (7–20 digits, + - () allowed).";
    }

    if (!is_numeric($data['seat_no']) || (int)$data['seat_no'] < 1) {
        $errors[] = "Number of seats must be a positive number.";
    }

    return $errors;
}

/* ============================================================
   🏪 CREATE SHOP
   ============================================================ */
function createShop($conn, $data) {
    $sql = "INSERT INTO shop (NAME, DESCRIPTION, IMAGES, LOCATION, CONTACT, SEAT_NO, USERS_ID)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Prepare failed: " . htmlspecialchars($conn->error));
    }

    $stmt->bind_param(
        "sssssii",
        $data['name'],
        $data['description'],
        $data['image'],
        $data['location'],
        $data['contact'],
        $data['seat_no'],
        $data['user_id']
    );

    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/* ============================================================
   MAIN FLOW
   ============================================================ */
$user_id = checkAuth();

if (hasShop($conn, $user_id)) {
    header("Location: profile.php");
    exit();
}

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* Sanitise raw input */
    $raw = [
        'name'        => trim($_POST['shop_name']        ?? ''),
        'description' => trim($_POST['shop_description'] ?? ''),
        'location'    => trim($_POST['location']         ?? ''),
        'contact'     => trim($_POST['contact']          ?? ''),
        'seat_no'     => $_POST['seat_no']               ?? 0,
    ];

    $errors = validateInputs($raw);

    /* Image upload — validate separately so we can give a clear message */
    $imagePath = uploadImage();

    if ($imagePath === null) {
        $errors[] = "Image must be JPG, PNG, GIF or WEBP and under 2 MB.";
    }

    if (empty($errors)) {
        $data = [
            'name'        => $raw['name'],
            'description' => $raw['description'],
            'location'    => $raw['location'],
            'contact'     => $raw['contact'],
            'seat_no'     => (int) $raw['seat_no'],
            'image'       => $imagePath,
            'user_id'     => $user_id,
        ];

        if (createShop($conn, $data)) {
            header("Location: profile.php");
            exit();
        } else {
            $errors[] = "Database error — please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Shop</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:       #08090B;
    --card:     #0D0F14;
    --accent:   #c84b2f;
    --text:     #E8E4DC;
    --muted:    #6b6b6b;
    --border:   #ddd8d0;
    --radius:   14px;
    --shadow:   0 8px 32px rgba(0,0,0,.10);
  }

  body {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background: var(--bg);
    font-family: 'DM Sans', sans-serif;
    padding: 24px;
  }

  .card {
    background: var(--card);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    padding: 48px 40px;
    width: 100%;
    max-width: 440px;
    animation: fadeUp .45s ease both;
  }

  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .card legend {
    font-family: 'DM Serif Display', serif;
    font-size: 2rem;
    color: var(--text);
    margin-bottom: 28px;
    display: block;
  }

  /* Error list */
  .error-box {
    background: #fdf0ed;
    border-left: 3px solid var(--accent);
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 20px;
  }
  .error-box p {
    color: var(--accent);
    font-size: .875rem;
    line-height: 1.6;
  }

  /* Fields */
  .field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 18px;
  }
  .field label {
    font-size: .8rem;
    font-weight: 600;
    color: var(--muted);
    letter-spacing: .04em;
    text-transform: uppercase;
  }
  .field input,
  .field textarea {
    padding: 12px 14px;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    font-family: inherit;
    font-size: .95rem;
    color: var(--text);
    background: var(--bg);
    transition: border-color .2s, box-shadow .2s;
    outline: none;
    resize: vertical;
  }
  .field input:focus,
  .field textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(200,75,47,.12);
  }
  .field textarea { min-height: 90px; }

  /* Image upload */
  #image_selector { display: none; }
  .upload-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 11px 16px;
    border: 1.5px dashed var(--border);
    border-radius: 10px;
    color: var(--muted);
    font-size: .9rem;
    cursor: pointer;
    background: #faf8f6;
    transition: border-color .2s, color .2s;
    user-select: none;
  }
  .upload-btn:hover { border-color: var(--accent); color: var(--accent); }
  .upload-btn .icon { font-size: 1.1rem; }
  #file-label {
    font-size: .8rem;
    color: var(--muted);
    margin-top: 4px;
    display: block;
  }

  /* Buttons */
  .btn-row {
    display: flex;
    gap: 12px;
    margin-top: 10px;
  }
  .btn {
    flex: 1;
    padding: 13px;
    border: none;
    border-radius: 10px;
    font-family: inherit;
    font-size: .95rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform .15s, box-shadow .15s;
  }
  .btn:active { transform: scale(.97); }
  .btn-primary {
    background: var(--accent);
    color: #fff;
    box-shadow: 0 4px 14px rgba(200,75,47,.35);
  }
  .btn-primary:hover { box-shadow: 0 6px 20px rgba(200,75,47,.45); }
  .btn-secondary {
    background: var(--bg);
    color: var(--muted);
    border: 1.5px solid var(--border);
  }
  .btn-secondary:hover { background: #ece7e0; }
</style>
</head>

<body>

<div class="card">
  <form method="post" enctype="multipart/form-data" novalidate>
    <legend>Add Your Shop</legend>

    <?php if (!empty($errors)): ?>
      <div class="error-box">
        <?php foreach ($errors as $e): ?>
          <p>&#9679; <?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="field">
      <label for="shop_name">Shop Name</label>
      <input type="text" id="shop_name" name="shop_name"
             placeholder="e.g. The Corner Bakery"
             value="<?= htmlspecialchars($raw['name'] ?? '') ?>" required>
    </div>

    <div class="field">
      <label for="shop_description">Description</label>
      <textarea id="shop_description" name="shop_description"
                placeholder="Tell customers what makes your shop special…"><?= htmlspecialchars($raw['description'] ?? '') ?></textarea>
    </div>

    <div class="field">
      <label for="location">Location</label>
      <input type="text" id="location" name="location"
             placeholder="e.g. 12 Market Street, Block B"
             value="<?= htmlspecialchars($raw['location'] ?? '') ?>" required>
    </div>

    <div class="field">
      <label for="contact">Contact Number</label>
      <input type="tel" id="contact" name="contact"
             placeholder="e.g. +977 98XXXXXXXX"
             value="<?= htmlspecialchars($raw['contact'] ?? '') ?>" required>
    </div>

    <div class="field">
      <label for="seat_no">Number of Seats</label>
      <input type="number" id="seat_no" name="seat_no"
             placeholder="e.g. 20" min="1"
             value="<?= htmlspecialchars($raw['seat_no'] ?? '') ?>" required>
    </div>

    <div class="field">
      <label>Shop Image (JPG / PNG / GIF / WEBP, max 2 MB)</label>
      <input type="file" name="image" id="image_selector" accept="image/*">
      <div class="upload-btn" onclick="document.getElementById('image_selector').click()">
        <span class="icon">&#128247;</span> Choose image
      </div>
      <span id="file-label">No file chosen</span>
    </div>

    <div class="btn-row">
      <button type="submit" class="btn btn-primary">Create Shop</button>
      <button type="reset"  class="btn btn-secondary"
              onclick="document.getElementById('file-label').textContent='No file chosen'">Reset</button>
    </div>
  </form>
</div>

<script>
  document.getElementById('image_selector').addEventListener('change', function () {
    const label = document.getElementById('file-label');
    label.textContent = this.files.length ? this.files[0].name : 'No file chosen';
  });
</script>

</body>
</html>
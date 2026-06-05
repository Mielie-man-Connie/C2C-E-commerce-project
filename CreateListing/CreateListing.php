<?php
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../LoginAndRegister/LoginPage.php');
        exit;
    }
    require_once __DIR__ . '/../data/database.php';
    require_once __DIR__ . '/../data/imageHelper.php';

    $userId  = (int) $_SESSION['user_id'];
    $editId  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $isEdit  = (bool) $editId;
    $errors  = [];
    $success = '';

    function h(string $v): string {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /* ── Load existing item when editing ── */
    $item = null;
    $existingImages = [];   // [{id, url}]

    if ($isEdit) {
        try {
            $stmt = $pdo->prepare(
                'SELECT i.*, l.province, l.city, l.suburb AS itemSuburb
                FROM items i
                LEFT JOIN locations l ON l.locationID = i.locationID
                WHERE i.itemID = :id AND i.userID = :uid LIMIT 1'
            );
            $stmt->execute([':id' => $editId, ':uid' => $userId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                header('Location: ../History/History.php');
                exit;
            }
            $ids = json_decode($item['imageID'] ?? '[]', true);
            if (is_array($ids)) {
                foreach ($ids as $imgId) {
                    $existingImages[] = ['id' => (int)$imgId, 'url' => '../data/getImage.php?id=' . (int)$imgId];
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Error loading item: ' . $e->getMessage();
            $item = null;
            $isEdit = false;
        }
    }

    /* ── Load all locations for cascade dropdowns ── */
    $locationTree = [];
    try {
        $locs = $pdo->query('SELECT province, city, suburb FROM locations ORDER BY province, city, suburb')
                    ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($locs as $loc) {
            $p = $loc['province']; $c = $loc['city']; $s = $loc['suburb'];
            if (!isset($locationTree[$p]))     $locationTree[$p] = [];
            if (!isset($locationTree[$p][$c])) $locationTree[$p][$c] = [];
            if ($s && !in_array($s, $locationTree[$p][$c], true)) $locationTree[$p][$c][] = $s;
        }
    } catch (PDOException $e) {}

    $locTreeJson = json_encode($locationTree, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

    /* ── POST handler ── */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $itemName       = trim($_POST['itemName']        ?? '');
        $itemDesc       = trim($_POST['itemDescription'] ?? '');
        $itemPrice      = trim($_POST['itemPrice']       ?? '');
        $itemsAvail     = trim($_POST['itemsAvailable']  ?? '');
        $deliveryType   = trim($_POST['deliveryType']    ?? '');
        $province       = trim($_POST['province']        ?? '');
        $city           = trim($_POST['city']            ?? '');
        $suburb         = trim($_POST['suburb']          ?? '');
        $keepImages     = json_decode($_POST['keepImages'] ?? '[]', true); // existing IDs to keep
        if (!is_array($keepImages)) $keepImages = [];

        /* Validation */
        if (!$itemName)                          $errors[] = 'Item name is required.';
        elseif (strlen($itemName) > 100)         $errors[] = 'Item name must be 100 characters or less.';
        if ($itemDesc && strlen($itemDesc) > 255) $errors[] = 'Description must be 255 characters or less.';
        if ($itemPrice === '')                    $errors[] = 'Price is required.';
        elseif (!is_numeric($itemPrice) || (float)$itemPrice < 0) $errors[] = 'Price must be a valid number.';
        if ($itemsAvail === '')                   $errors[] = 'Quantity is required.';
        elseif (!ctype_digit($itemsAvail) || (int)$itemsAvail < 1) $errors[] = 'Quantity must be at least 1.';
        if (!in_array($deliveryType, ['P','A','E'], true)) $errors[] = 'Please select a delivery type.';
        if (!$province || !$city || !$suburb)    $errors[] = 'Please fill in all location fields.';

        /* Validate and compress uploaded images */
        $newImageData = [];
        if (isset($_FILES['newImages']) && !empty($_FILES['newImages']['name'][0])) {
            $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
            $count = count($_FILES['newImages']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['newImages']['error'][$i] !== UPLOAD_ERR_OK) continue;
                // Function = detectMime(), Class = data/imageHelper.php
                $mime = detectMime($_FILES['newImages']['tmp_name'][$i], $_FILES['newImages']['type'][$i] ?? null);
                if (!in_array($mime, $allowed, true)) { $errors[] = "File #{$i}: unsupported type."; continue; }
                if ($_FILES['newImages']['size'][$i] > 8 * 1024 * 1024) { $errors[] = "File #{$i}: must be under 8 MB."; continue; }
                // Use image compression helper (compresses to max 512x512)
                $newImageData[] = $_FILES['newImages']['tmp_name'][$i]; // Store tmpFile path for later processing
            }
        }

        if (empty($errors)) {
            try {
                /* Find or create location */
                $ls = $pdo->prepare('SELECT locationID FROM locations WHERE province=:p AND city=:c AND suburb=:s LIMIT 1');
                $ls->execute([':p'=>$province,':c'=>$city,':s'=>$suburb]);
                $loc = $ls->fetch(PDO::FETCH_ASSOC);

                if ($loc) {
                    $locationID = (int)$loc['locationID'];
                } else {
                    $pdo->prepare('INSERT INTO locations (province, city, suburb) VALUES (:p,:c,:s)')
                        ->execute([':p'=>$province,':c'=>$city,':s'=>$suburb]);
                    $locationID = (int)$pdo->lastInsertId();
                }

                /* Upload and compress new images */
                $newImageIds = [];
                foreach ($newImageData as $tmpFile) {
                    // Function = detectMime(), Class = data/imageHelper.php
                    $mime = detectMime($tmpFile, null);
                    // Use image compression helper (compresses to max 512x512)
                    // Function = compressAndStoreImage(), Class = data/imageHelper.php
                    $imageID = compressAndStoreImage($pdo, $tmpFile, $mime, $userId);
                    if ($imageID !== null) {
                        $newImageIds[] = $imageID;
                    }
                }

                /* Delete images that were removed by the user */
                if ($isEdit && !empty($existingImages)) {
                    $keepSet = [];
                    foreach ($keepImages as $keepId) {
                        $keepSet[] = (int)$keepId;
                    }
                    foreach ($existingImages as $ei) {
                        if (!in_array($ei['id'], $keepSet, true)) {
                            $pdo->prepare('DELETE FROM images WHERE imageID=:id AND userID=:uid')
                                ->execute([':id'=>$ei['id'],':uid'=>$userId]);
                        }
                    }
                }

                /* Build final image JSON: kept existing + new */
                $finalIds = [];
                foreach ($keepImages as $keepId) {
                    $finalIds[] = (int)$keepId;
                }
                foreach ($newImageIds as $newId) {
                    $finalIds[] = $newId;
                }
                $imageJson = json_encode(array_values($finalIds));

                if ($isEdit) {
                    $oldStmt = $pdo->prepare('SELECT itemName, itemDescription, itemPrice, itemsAvailable, deliveryType, locationID, imageID FROM items WHERE itemID=:id AND userID=:uid LIMIT 1');
                    $oldStmt->execute([':id'=>$editId, ':uid'=>$userId]);
                    $oldItem = $oldStmt->fetch(PDO::FETCH_ASSOC) ?: [];

                    $pdo->prepare(
                        'UPDATE items SET itemName=:n, itemDescription=:d, itemPrice=:p,
                        itemsAvailable=:a, deliveryType=:t, locationID=:l, imageID=:img
                        WHERE itemID=:id AND userID=:uid'
                    )->execute([
                        ':n'=>$itemName,':d'=>$itemDesc ?: null,':p'=>(float)$itemPrice,
                        ':a'=>(int)$itemsAvail,':t'=>$deliveryType,':l'=>$locationID,
                        ':img'=>$imageJson,':id'=>$editId,':uid'=>$userId,
                    ]);

                    $changes = [];
                    $newValues = [
                        'itemName' => $itemName,
                        'itemDescription' => $itemDesc ?: null,
                        'itemPrice' => (float)$itemPrice,
                        'itemsAvailable' => (int)$itemsAvail,
                        'deliveryType' => $deliveryType,
                        'locationID' => $locationID,
                        'imageID' => $imageJson,
                    ];
                    foreach ($newValues as $col => $newVal) {
                        $oldVal = $oldItem[$col] ?? null;
                        if ((string)$oldVal !== (string)$newVal) {
                            $changes[] = "$col: '" . ($oldVal ?? '(null)') . "' → '" . ($newVal ?? '(null)') . "'";
                        }
                    }
                    $auditDesc = $changes ? implode(' | ', $changes) : "Updated listing: '$itemName'";
                    // Function = logAudit(), Class = data/database.php
                    logAudit($pdo, $userId, 'modified', "items: itemID=$editId", $auditDesc);

                    $success = 'Listing updated successfully.';
                } else {
                    $pdo->prepare(
                        'INSERT INTO items (userID, itemName, itemDescription, itemPrice,
                        itemsAvailable, deliveryType, locationID, imageID)
                        VALUES (:uid,:n,:d,:p,:a,:t,:l,:img)'
                    )->execute([
                        ':uid'=>$userId,':n'=>$itemName,':d'=>$itemDesc ?: null,
                        ':p'=>(float)$itemPrice,':a'=>(int)$itemsAvail,
                        ':t'=>$deliveryType,':l'=>$locationID,':img'=>$imageJson,
                    ]);
                    $newItemId = (int)$pdo->lastInsertId();
                    
                    /* Log audit: item created */
                    // Function = logAudit(), Class = data/database.php
                    logAudit($pdo, $userId, 'created', "items: itemID=$newItemId", "Created new listing: '$itemName' (price: " . (float)$itemPrice . ")");
                    
                    header('Location: ../ViewItem/ViewItem.php?id=' . $newItemId);
                    exit;
                }

                /* Reload updated item + images on edit success */
                if ($isEdit) {
                    $stmt->execute([':id'=>$editId,':uid'=>$userId]);
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);
                    $ids = json_decode($item['imageID'] ?? '[]', true);
                    $existingImages = [];
                    if (is_array($ids)) {
                        foreach ($ids as $imgId) {
                            $existingImages[] = ['id' => (int)$imgId, 'url' => '../data/getImage.php?id=' . (int)$imgId];
                        }
                    }
                }

            } catch (PDOException $e) {
                $errors[] = 'DB error: ' . $e->getMessage();
            }
        }
    }

    /* Default form values */
    $fName     = h($item['itemName']        ?? ($_POST['itemName']        ?? ''));
    $fDesc     = h($item['itemDescription'] ?? ($_POST['itemDescription'] ?? ''));
    $fPrice    = h($item['itemPrice']       ?? ($_POST['itemPrice']       ?? ''));
    $fAvail    = h($item['itemsAvailable']  ?? ($_POST['itemsAvailable']  ?? '1'));
    $fDelivery = $item['deliveryType']      ?? ($_POST['deliveryType']    ?? '');
    $fProvince = h($item['province']        ?? ($_POST['province']        ?? ''));
    $fCity     = h($item['city']            ?? ($_POST['city']            ?? ''));
    $fSuburb   = h($item['itemSuburb']      ?? ($_POST['suburb']          ?? ''));
    $existingJson = json_encode($existingImages, JSON_HEX_TAG | JSON_HEX_AMP);
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>TradeSA – <?= $isEdit ? 'Edit' : 'Create' ?> Listing</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="CreateListing.css">
        <link rel="stylesheet" href="../data/preset.css">
    </head>
    <body>
        <?php include '../HeaderAndFooter/Header.php'; ?>

        <div class="cl-page">

            <div class="cl-header">
                <a href="../History/History.php" class="back-link">← Back</a>
                <h1><?= $isEdit ? 'Edit listing' : 'Create new listing' ?></h1>
                <?php if ($isEdit && $item): ?>
                    <p class="cl-sub">Editing: <strong><?= h($item['itemName']) ?></strong></p>
                <?php endif; ?>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="feedback error" role="alert">
                    <?php foreach ($errors as $e): ?><p>⚠ <?= $e ?></p><?php endforeach; ?>
                </div>
            <?php elseif ($success): ?>
                <div class="feedback success" role="status"><p>✓ <?= h($success) ?></p></div>
            <?php endif; ?>

            <form action="CreateListing.php<?= $isEdit ? '?id='.$editId : '' ?>"
                method="POST"
                enctype="multipart/form-data"
                id="listingForm"
                novalidate>

                <input type="hidden" name="keepImages" id="keepImages" value="<?= h(json_encode(array_column($existingImages,'id'))) ?>">

                <div class="cl-layout">

                    <!-- LEFT: Images -->
                    <div class="cl-images-col">
                        <div class="section-label">Images</div>

                        <!-- Preview strip -->
                        <div class="img-strip-wrap">
                            <div class="img-strip" id="imgStrip">
                                <!-- Filled by JS -->
                            </div>
                            <div class="img-empty" id="imgEmpty">
                                <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <p>No images yet</p>
                            </div>
                        </div>

                        <input type="file" name="newImages[]" id="imageInput"
                            accept="image/*" multiple hidden>
                        <button type="button" class="btn-add-img" onclick="document.getElementById('imageInput').click()">
                            + Add images
                        </button>
                        <p class="img-hint">JPEG · PNG · WebP — max 8 MB each</p>
                    </div>

                    <!-- RIGHT: Details -->
                    <div class="cl-details-col">

                        <div class="section-label">Item details</div>

                        <div class="field-group">
                            <label for="itemName">Item name <span class="req">*</span></label>
                            <input type="text" id="itemName" name="itemName"
                                value="<?= $fName ?>" placeholder="e.g. Vintage Denim Jacket"
                                maxlength="100" required>
                        </div>

                        <div class="field-group">
                            <label for="itemDescription">Description</label>
                            <textarea id="itemDescription" name="itemDescription"
                                    rows="4" maxlength="255"
                                    placeholder="Describe the item's condition, size, features…"><?= $fDesc ?></textarea>
                            <span class="hint char-count" id="descCount"><?= mb_strlen(strip_tags($fDesc)) ?> / 255</span>
                        </div>

                        <div class="two-col">
                            <div class="field-group">
                                <label for="itemPrice">Price (R) <span class="req">*</span></label>
                                <input type="number" id="itemPrice" name="itemPrice"
                                    value="<?= $fPrice ?>" placeholder="0.00"
                                    min="0" step="0.01" required>
                            </div>
                            <div class="field-group">
                                <label for="itemsAvailable">Quantity <span class="req">*</span></label>
                                <input type="number" id="itemsAvailable" name="itemsAvailable"
                                    value="<?= $fAvail ?>" placeholder="1"
                                    min="1" step="1" required>
                            </div>
                        </div>

                        <div class="field-group">
                            <label>Delivery type <span class="req">*</span></label>
                            <div class="radio-row">
                                <label class="radio-opt <?= $fDelivery==='P'?'selected':'' ?>">
                                    <input type="radio" name="deliveryType" value="P"
                                        <?= $fDelivery==='P'?'checked':'' ?> required>
                                    <span>📦 Pickup</span>
                                </label>
                                <label class="radio-opt <?= $fDelivery==='A'?'selected':'' ?>">
                                    <input type="radio" name="deliveryType" value="A"
                                        <?= $fDelivery==='A'?'checked':'' ?>>
                                    <span>🔁 Any</span>
                                </label>
                                <label class="radio-opt <?= $fDelivery==='E'?'selected':'' ?>">
                                    <input type="radio" name="deliveryType" value="E"
                                        <?= $fDelivery==='E'?'checked':'' ?>>
                                    <span>🔒 Escrow</span>
                                </label>
                            </div>
                        </div>

                        <div class="section-label" style="margin-top:1.4rem">Location <span class="req">*</span></div>

                        <div class="field-group">
                            <label for="provinceSelect">Province</label>
                            <select id="provinceSelect" name="province" required>
                                <option value="">Select province…</option>
                            </select>
                        </div>
                        <div class="two-col">
                            <div class="field-group">
                                <label for="citySelect">City</label>
                                <select id="citySelect" name="city" disabled required>
                                    <option value="">Select city…</option>
                                </select>
                            </div>
                            <div class="field-group">
                                <label for="suburbInput">Suburb</label>
                                <input type="text" id="suburbInput" name="suburb"
                                    value="<?= $fSuburb ?>" placeholder="e.g. Sandton"
                                    list="suburbDatalist">
                                <datalist id="suburbDatalist"></datalist>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit">
                            <?= $isEdit ? 'Save changes' : 'Publish listing' ?>
                        </button>

                    </div>
                </div>
            </form>
        </div>

        <?php include '../HeaderAndFooter/Footer.php'; ?>

        <script>
            /* ══════════════════════════════════════════════
            IMAGE MANAGEMENT
            ══════════════════════════════════════════════ */
            const LOC_TREE      = <?= $locTreeJson ?>;
            const EXISTING_IMGS = <?= $existingJson ?>;  // [{id, url}, ...]

            /* Virtual image list:
            {type:'existing', id:N, url:'...'}  or  {type:'new', file:File, url:blobUrl} */
            let images = EXISTING_IMGS.map(i => ({ type: 'existing', id: i.id, url: i.url }));

            const strip    = document.getElementById('imgStrip');
            const imgEmpty = document.getElementById('imgEmpty');
            const keepInp  = document.getElementById('keepImages');

            function renderStrip() {
                strip.innerHTML = '';
                imgEmpty.style.display = images.length ? 'none' : 'flex';

                images.forEach((img, idx) => {
                    const wrap = document.createElement('div');
                    wrap.className = 'img-preview';

                    const im = document.createElement('img');
                    im.src = img.url;
                    im.alt = 'Image ' + (idx + 1);

                    const del = document.createElement('button');
                    del.type = 'button';
                    del.className = 'img-del-btn';
                    del.innerHTML = '✕';
                    del.title = 'Remove image';
                    del.onclick = () => { removeImage(idx); };

                    wrap.appendChild(im);
                    wrap.appendChild(del);
                    strip.appendChild(wrap);
                });

                /* Update keepImages hidden input */
                const keepIds = images.filter(i => i.type === 'existing').map(i => i.id);
                keepInp.value = JSON.stringify(keepIds);

                /* Rebuild the FileList for the file input from 'new' type images */
                rebuildFileInput();
            }

            function removeImage(idx) {
                const img = images[idx];
                if (img.type === 'new') URL.revokeObjectURL(img.url);
                images.splice(idx, 1);
                renderStrip();
            }

            /* Rebuild a DataTransfer to set new file input's files */
            function rebuildFileInput() {
                const dt = new DataTransfer();
                images.filter(i => i.type === 'new').forEach(i => dt.items.add(i.file));
                document.getElementById('imageInput').files = dt.files;
            }

            document.getElementById('imageInput').addEventListener('change', function () {
                Array.from(this.files).forEach(file => {
                    const url = URL.createObjectURL(file);
                    images.push({ type: 'new', file, url });
                });
                this.value = ''; // reset so same file can be re-added if removed
                renderStrip();
            });

            renderStrip(); // initial render

            /* CASCADING LOCATION DROPDOWNS */
            const provinceEl = document.getElementById('provinceSelect');
            const cityEl     = document.getElementById('citySelect');
            const suburbEl   = document.getElementById('suburbInput');
            const suburbDL   = document.getElementById('suburbDatalist');

            /* Populate provinces */
            Object.keys(LOC_TREE).sort().forEach(p => {
                const o = document.createElement('option');
                o.value = p; o.textContent = p;
                provinceEl.appendChild(o);
            });

            function populateCities(province) {
                cityEl.innerHTML = '<option value="">Select city…</option>';
                cityEl.disabled = !province;
                if (!province || !LOC_TREE[province]) return;
                Object.keys(LOC_TREE[province]).sort().forEach(c => {
                    const o = document.createElement('option');
                    o.value = c; o.textContent = c;
                    cityEl.appendChild(o);
                });
            }

            function populateSuburbs(province, city) {
                suburbDL.innerHTML = '';
                if (!province || !city || !LOC_TREE[province]?.[city]) return;
                [...LOC_TREE[province][city]].sort().forEach(s => {
                    const o = document.createElement('option');
                    o.value = s;
                    suburbDL.appendChild(o);
                });
            }

            provinceEl.addEventListener('change', function () {
                populateCities(this.value);
                suburbEl.value = '';
                suburbDL.innerHTML = '';
            });
            cityEl.addEventListener('change', function () {
                populateSuburbs(provinceEl.value, this.value);
                suburbEl.value = '';
            });

            /* Pre-select edit values */
            const preProvince = <?= json_encode($fProvince) ?>;
            const preCity     = <?= json_encode($fCity) ?>;
            const preSuburb   = <?= json_encode($fSuburb) ?>;
            if (preProvince) {
                provinceEl.value = preProvince;
                populateCities(preProvince);
                if (preCity) {
                    cityEl.value = preCity;
                    populateSuburbs(preProvince, preCity);
                }
            }

            /* ══════════════════════════════════════════════
            MISC
            ══════════════════════════════════════════════ */
            /* Char counter */
            const descTA = document.getElementById('itemDescription');
            const descCt = document.getElementById('descCount');
            descTA.addEventListener('input', () => { descCt.textContent = `${descTA.value.length} / 255`; });

            /* Radio highlight */
            document.querySelectorAll('.radio-opt input[type=radio]').forEach(r => {
                r.addEventListener('change', () => {
                    document.querySelectorAll('.radio-opt').forEach(o => o.classList.remove('selected'));
                    r.closest('.radio-opt').classList.add('selected');
                });
            });
        </script>
    </body>
</html>
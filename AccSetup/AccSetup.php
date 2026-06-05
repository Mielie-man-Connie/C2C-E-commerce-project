<?php
/* Account Setup */
// Session guard
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../LoginAndRegister/LoginPage.php');
    exit;
}

require_once __DIR__ . '/../data/database.php';
require_once __DIR__ . '/../data/imageHelper.php';

$userId = (int) $_SESSION['user_id'];
$errors = [];

/* Fetch location data for cascading dropdowns */
$locations = [];
try {
    $locStmt = $pdo->query('SELECT locationID, province, city, suburb FROM locations ORDER BY province, city, suburb');
    $locations = $locStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $locations = [];
}

// Build nested structure for client-side location picker
$locTree = [];
$locMap  = [];
foreach ($locations as $loc) {
    $prov = $loc['province'] ?? '';
    $city = $loc['city'] ?? '';
    $locTree[$prov][$city][] = [
        'id'     => (int)$loc['locationID'], 
        'suburb' => $loc['suburb'] ?? '',
    ];
    $locMap[(int)$loc['locationID']] = [
        'province' => $prov,
        'city'     => $city,
        'suburb'   => $loc['suburb'] ?? '',
    ];
}
$locTreeJson = json_encode($locTree, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$locMapJson  = json_encode($locMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

/* Helper functions */

/**
 * HTML-safe string output (prevents XSS)
 * @param string $v Value to escape
 * @return string Escaped HTML-safe string
 */
function h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Validate and parse South African 13-digit ID number
 * Uses Luhn algorithm and date validation
 * 
 * Returns array with:
 *   - valid (bool): Whether the ID is valid
 *   - dob (string|null): Parsed date of birth in YYYY-MM-DD format
 * 
 * @param string $id 13-digit ID number
 * @return array Validation result with DOB
 */
function parseZAID(string $id): array {
    if (!preg_match('/^\d{13}$/', $id)) return ['valid' => false, 'dob' => null];

    // Luhn algorithm validation
    $digits = str_split($id);
    $sum = 0;
    for ($i = 0; $i < 13; $i++) {
        $d = (int) $digits[$i];
        if ($i % 2 === 0) { $sum += $d; }
        else { $d *= 2; $sum += ($d > 9) ? $d - 9 : $d; }
    }
    if ($sum % 10 !== 0) return ['valid' => false, 'dob' => null];

    /* Extract date components from ID */
    $year  = (int) substr($id, 0, 2);
    $month = (int) substr($id, 2, 2);
    $day   = (int) substr($id, 4, 2);

    /* Century heuristic: IDs up to year 24 are 2000s, older than 24 are 1900s */
    $fullYear = ($year <= 24) ? 2000 + $year : 1900 + $year;

    /* Validate the date exists */
    if (!checkdate($month, $day, $fullYear)) return ['valid' => false, 'dob' => null];

    return ['valid' => true, 'dob' => sprintf('%04d-%02d-%02d', $fullYear, $month, $day)];
}

/* Init form values for re-population on errors */
$fName       = '';
$fSurname    = '';
$fDob        = '';
$fMobile     = '';
$fZaID       = '';
$fLocationID = '';
$fDesc       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        /* Sanitize and trim user input */
    $name        = trim($_POST['name']        ?? '');
    $surname     = trim($_POST['surname']     ?? '');
    $dob         = trim($_POST['dob']         ?? '');
    $mobile      = preg_replace('/\D/', '', trim($_POST['mobile'] ?? ''));
    $zaID        = preg_replace('/\D/', '', trim($_POST['zaID'] ?? ''));
    $locationID  = filter_input(INPUT_POST, 'locationID', FILTER_VALIDATE_INT);

    /* Check if a valid location ID is provided via the suburb select dropdown */
    if (($locationID === null || $locationID === false) && isset($_POST['suburbSelect']) && preg_match('/^\d+$/', $_POST['suburbSelect'])) {
        $locationID = (int) $_POST['suburbSelect'];
    }
    if ($locationID === false) {
        $locationID = null;
    }
    $description = trim($_POST['description'] ?? '');

    /* Preserve form values for re-population on error */
    $fName    = h($name);
    $fSurname = h($surname);
    $fDob     = h($dob);
    $fMobile   = h($_POST['mobile'] ?? '');
    $fZaID     = h($zaID);
    $fLocationID = h($_POST['locationID'] ?? '');
    $fDesc     = h($description);

    /* Required field validation */
    if ($name === '') {
        $errors[] = 'First name is required.';
    } elseif (!preg_match('/^[A-Za-z\s\-\']{2,50}$/', $name)) {
        $errors[] = 'First name may only contain letters (2–50 characters).';
    }

    if ($surname === '') {
        $errors[] = 'Surname is required.';
    } elseif (!preg_match('/^[A-Za-z\s\-\']{2,50}$/', $surname)) {
        $errors[] = 'Surname may only contain letters (2–50 characters).';
    }

    if ($dob === '') {
        $errors[] = 'Date of birth is required.';
    } else {
        $dobTs = strtotime($dob);
        if (!$dobTs || $dobTs >= strtotime('today')) {
            $errors[] = 'Date of birth must be a valid past date.';
        } elseif ($dobTs > strtotime('-13 years')) {
            $errors[] = 'You must be at least 13 years old to register.';
        }
    }

    /* Optional field validation (if provided) */
    $idParsed    = null;
    $idProvided  = $zaID  !== '';
    $mobProvided = $mobile !== '';

    /* Validate ID number if provided */
    if ($idProvided) {
        $idParsed = parseZAID($zaID);

        if (!$idParsed['valid']) {
            $errors[] = 'ID number is invalid. Please enter a valid 13-digit South African ID.';
            $zaID    = '';
            $fZaID   = '';
        } elseif ($dob !== '') {
            /* Cross-check ID DOB with form DOB */
            if ($idParsed['dob'] !== $dob) {
                $errors[] = 'Date of birth does not match the ID number. Both fields have been cleared — please re-enter carefully.';
                $dob      = '';
                $zaID    = '';
                $fDob     = '';
                $fZaID   = '';
                $idParsed = null;
            }
        }
    }

   /*  Validate mobile number if provided */
    if ($mobProvided) {
        if (!preg_match('/^0[6-8][0-9]{8}$/', $mobile)) {
            $errors[] = 'Mobile number must be a valid 10-digit South African number (e.g. 0821234567).';
            $mobile   = '';
            $fMobile  = '';
        }
    }

   /*  Validate location if provided */
    if ($locationID !== null) {
        try {
            $s = $pdo->prepare('SELECT locationID FROM locations WHERE locationID=:lid LIMIT 1');
            $s->execute([':lid'=>$locationID]);
            if (!$s->fetch()) {
                $errors[] = 'Selected location is invalid.';
                $locationID = null;
            }
        } catch (PDOException $e) {
            $errors[] = 'DB error: '.$e->getMessage();
            $locationID = null;
        }
    }

    /* Uniqueness checks (optional fields) */
    if (empty($errors)) {
        try {
            if ($mobProvided && $mobile !== '') {
                $stmt = $pdo->prepare('SELECT userID FROM accounts WHERE mobile = :mobile AND userID != :uid LIMIT 1');
                $stmt->execute([':mobile' => $mobile, ':uid' => $userId]);
                if ($stmt->fetch()) $errors[] = 'That mobile number is already linked to another account.';
            }
            if ($idProvided && $zaID !== '') {
                $stmt = $pdo->prepare('SELECT userID FROM accounts WHERE zaID = :id AND userID != :uid LIMIT 1');
                $stmt->execute([':id' => $zaID, ':uid' => $userId]);
                if ($stmt->fetch()) $errors[] = 'That ID number is already linked to another account.';
            }
        } catch (PDOException $e) {
            $errors[] = 'DB Error: ' . $e->getMessage();
        }
    }

    /* Profile image upload & compression */
    $imageID = null;
    if (empty($errors) && isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        // Function = detectMime(), Class = data/imageHelper.php
        $mime    = detectMime($_FILES['profileImage']['tmp_name'], $_FILES['profileImage']['type'] ?? null);
        if (!in_array($mime, $allowed, true)) {
            $errors[] = 'Profile image must be JPEG, PNG, WebP, or GIF.';
        } elseif ($_FILES['profileImage']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Profile image must be under 5 MB.';
        } else {
            /* Use image compression helper (compresses to max 512x512) */
            // Function = compressAndStoreImage(), Class = data/imageHelper.php
            $imageID = compressAndStoreImage($pdo, $_FILES['profileImage']['tmp_name'], $mime, $userId);
            if ($imageID === null) {
                $errors[] = 'Failed to compress and save profile image. Please try again.';
            }
        }
    }

    /* Determine verified status */
    $isVerified = (
        $name !== '' && $surname !== '' && $dob !== '' &&
        $mobProvided && $mobile !== '' &&
        $idProvided  && $zaID  !== '' && $idParsed !== null
    ) ? 1 : 0;

    /* Persist to database (if no errors) */
    if (empty($errors)) {
        try {
            $sql = 'UPDATE accounts SET
                        name               = :name,
                        surname            = :surname,
                        dob                = :dob,
                        mobile             = :mobile,
                        zaID               = :zaID,
                        locationID         = :locationID,
                        profileDescription = :desc,
                        isVerified         = :verified'
                  . ($imageID !== null ? ', imageID = :imgID' : '')
                  . ' WHERE userID = :uid';

            $params = [
                ':name'       => $name,
                ':surname'    => $surname,
                ':dob'        => $dob     !== '' ? $dob     : null,
                ':mobile'     => $mobile  !== '' ? $mobile  : null,
                ':zaID'       => $zaID   !== '' ? $zaID   : null,
                ':locationID' => $locationID !== null ? $locationID : null,
                ':desc'       => $description !== '' ? $description : null,
                ':verified'   => $isVerified,
                ':uid'        => $userId,
            ];
            if ($imageID !== null) $params[':imgID'] = $imageID;

            $pdo->prepare($sql)->execute($params);

            // Clear setup flag and redirect to browse page
            $_SESSION['setup_pending'] = false;
            header('Location: ../Browse/Browse.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'DB Error (update): ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TradeSA – Complete Your Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="AccSetup.css">
    <link rel="stylesheet" href="../data/preset.css">
    <script src="../data/translations.js"></script>
</head>
    <body>

        <div class="page-bg"></div>

        <!-- Language selector -->
        <div class="language-switcher">
            <select id="language-select">
                <option value="en">English</option>
                <option value="af">Afrikaans</option>
                <option value="zu">Zulu</option>
                <option value="xh">Xhosa</option>
                <option value="nso">Northern Sotho</option>
            </select>
        </div>

        <div class="setup-panel" id="setup-panel">

            <div class="panel-header">
                <p class="eyebrow" data-i18n="welcomeToTradeSA">Welcome to TradeSA</p>
                <h1 data-i18n="completeYourProfile">Complete your profile</h1>
                <p class="subtitle">
                    Fields marked <span class="req-star">*</span> are required.
                    Fill in all optional fields to become a
                    <span class="verified-badge">✓ Verified</span> seller.
                </p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-messages" role="alert">
                    <?php foreach ($errors as $err): ?>
                        <p><?= $err ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="AccSetup.php" method="POST" enctype="multipart/form-data" id="setup-form" novalidate>

                <!-- ── Avatar + Name/Surname row ── -->
                <div class="top-section">

                    <div class="avatar-zone" id="avatar-zone">
                        <input type="file" name="profileImage" id="profileImage" accept="image/*" hidden>
                        <div class="avatar-preview" id="avatar-preview">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="12" cy="8" r="4"/>
                                <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                            </svg>
                        </div>
                        <button type="button" class="avatar-btn" id="avatar-btn" data-i18n="uploadPhoto">Upload photo</button>
                        <p class="avatar-hint" data-i18n="photoHint">JPG · PNG · WebP | max 5 MB · optional</p>
                    </div>

                    <div class="name-fields">
                        <div class="field-group">
                            <label for="name" data-i18n="firstName">First name <span class="req-star">*</span></label>
                            <input type="text" id="name" name="name"
                                value="<?= $fName ?>" placeholder="e.g. Sipho"
                                maxlength="50" required>
                        </div>
                        <div class="field-group">
                            <label for="surname" data-i18n="surname">Surname <span class="req-star">*</span></label>
                            <input type="text" id="surname" name="surname"
                                value="<?= $fSurname ?>" placeholder="e.g. Dlamini"
                                maxlength="50" required>
                        </div>
                    </div>
                </div>

                <!-- ── Required ── -->
                <div class="section-label">Required</div>
                <div class="fields-grid">
                    <div class="field-group span-half">
                        <label for="dob" data-i18n="dateOfBirth">Date of birth <span class="req-star">*</span></label>
                        <input type="date" id="dob" name="dob"
                            value="<?= $fDob ?>"
                            max="<?= date('Y-m-d', strtotime('-13 years')) ?>"
                            required>
                    </div>
                </div>

                <!-- ── Optional ── -->
                <div class="section-label">
                    Optional — fill all four to unlock
                    <span class="verified-badge">✓ Verified</span> status
                </div>
                <div class="fields-grid">

                    <div class="field-group span-half">
                        <label for="mobile" data-i18n="mobileNumber">Mobile number</label>
                        <input type="tel" id="mobile" name="mobile"
                            value="<?= $fMobile ?>" placeholder="0821234567"
                            maxlength="10">
                        <span class="field-hint">10-digit South African number</span>
                    </div>

                    <div class="field-group span-half">
                        <label for="zaID" data-i18n="saIDNumber">SA ID number</label>
                        <input type="text" id="zaID" name="zaID"
                            value="<?= $fZaID ?>" placeholder="8001015009087"
                            maxlength="13" inputmode="numeric">
                        <span class="field-hint">13 digits · must match your date of birth</span>
                    </div>

                    <div class="field-group span-half">
                        <label data-i18n="location">Location</label>
                        <div class="field-grid location-picker">
                            <div class="field-group">
                                <label data-i18n="province">Province</label>
                                <select id="provinceSelect" name="provinceSelect"></select>
                            </div>
                            <div class="field-group">
                                <label data-i18n="city">City</label>
                                <select id="citySelect" name="citySelect" disabled></select>
                            </div>
                            <div class="field-group">
                                <label data-i18n="suburb">Suburb</label>
                                <select id="suburbSelect" name="suburbSelect" disabled></select>
                            </div>
                        </div>
                        <input type="hidden" name="locationID" id="locationID" value="<?= $fLocationID ?>">
                        <span class="field-hint">Select your TradeSA location. If it is missing, report it.</span>
                        <a href="../CreateReport/CreateReport.php?return=<?= urlencode($_SERVER['REQUEST_URI']) ?>&reportType=location" class="btn-outline" style="margin-top:0.75rem;display:inline-flex;" data-i18n="reportMissingLocation">Report missing location</a>
                    </div>

                    <div class="field-group span-full">
                        <label for="description" data-i18n="profileDescription">Profile description</label>
                        <textarea id="description" name="description"
                                rows="3" maxlength="500"
                                placeholder="Tell other traders a little about yourself…"></textarea>
                        <span class="field-hint char-count" id="desc-count">0 / 500</span>
                    </div>
                </div>

                <!-- Live verified notice -->
                <div class="verified-notice" id="verified-notice" aria-live="polite"></div>

                <button type="submit" class="submit-btn" data-i18n="completeAccount">Complete account</button>

            </form>
        </div>

        <script>
            /* Avatar preview */
            const avatarBtn   = document.getElementById('avatar-btn');
            const avatarInput = document.getElementById('profileImage');
            const avatarPrev  = document.getElementById('avatar-preview');

            avatarBtn.addEventListener('click', () => avatarInput.click());
            avatarInput.addEventListener('change', function () {
                const file = this.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = e => {
                    avatarPrev.innerHTML = `<img src="${e.target.result}" alt="Profile preview">`;
                };
                reader.readAsDataURL(file);
            });

            /* ═══════════════════════════════════════════════════════════
            CHARACTER COUNTER FOR PROFILE DESCRIPTION
            ═══════════════════════════════════════════════════════════ */
            const descTA    = document.getElementById('description');
            const descCount = document.getElementById('desc-count');
            descTA.addEventListener('input', () => {
                descCount.textContent = `${descTA.value.length} / 500`;
            });

            /* ═══════════════════════════════════════════════════════════
            CASCADING LOCATION SELECTOR
            ═══════════════════════════════════════════════════════════
            
            Uses nested structure: Province → City → Suburb
            Each dropdown filters the next based on selection.
            
            - LOC_TREE: { Province: { City: [Suburbs...] } }
            - LOC_MAP: { LocationID: { province, city, suburb } }
            ═══════════════════════════════════════════════════════════ */
            const LOC_TREE      = <?= $locTreeJson ?>;
            const LOC_MAP       = <?= $locMapJson ?>;
            const provinceSelect = document.getElementById('provinceSelect');
            const citySelect     = document.getElementById('citySelect');
            const suburbSelect   = document.getElementById('suburbSelect');
            const locationIDInput= document.getElementById('locationID');
            const preLocationID  = <?= json_encode($fLocationID !== '' ? (int)$fLocationID : null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

            /**
             * Reset city dropdown to initial state
             */
            function resetCitySelect() {
                citySelect.innerHTML = '<option value="">Choose city</option>';
                citySelect.disabled = true;
                resetSuburbSelect();
            }

            /**
             * Reset suburb dropdown to initial state
             */
            function resetSuburbSelect() {
                suburbSelect.innerHTML = '<option value="">Choose suburb</option>';
                suburbSelect.disabled = true;
            }

            /**
             * Populate city dropdown based on selected province
             * @param {string} province - Province name
             * @param {string} selectedCity - City to pre-select (optional)
             */
            function populateCities(province, selectedCity = '') {
                resetCitySelect();
                if (!province || !LOC_TREE[province]) return;
                Object.keys(LOC_TREE[province]).sort().forEach(city => {
                    const opt = document.createElement('option');
                    opt.value = city;
                    opt.textContent = city;
                    if (city === selectedCity) opt.selected = true;
                    citySelect.appendChild(opt);
                });
                citySelect.disabled = false;
            }

            /**
             * Populate suburb dropdown based on selected province and city
             * @param {string} province - Province name
             * @param {string} city - City name
             * @param {number|null} selectedId - LocationID to pre-select (optional)
             */
            function populateSuburbs(province, city, selectedId = null) {
                resetSuburbSelect();
                if (!province || !city || !LOC_TREE[province]?.[city]) return;
                LOC_TREE[province][city].forEach(entry => {
                    const opt = document.createElement('option');
                    opt.value = entry.id;
                    opt.textContent = entry.suburb;
                    if (String(entry.id) === String(selectedId)) opt.selected = true;
                    suburbSelect.appendChild(opt);
                });
                suburbSelect.disabled = false;
            }

            /**
             * Update hidden locationID input with selected suburb value
             * @param {number|null} value - LocationID or null
             */
            function setLocationId(value) {
                locationIDInput.value = value ? String(value) : '';
            }

            // Event listeners for cascading dropdowns
            provinceSelect.addEventListener('change', () => {
                populateCities(provinceSelect.value);
                setLocationId(null);
            });
            citySelect.addEventListener('change', () => {
                populateSuburbs(provinceSelect.value, citySelect.value);
                setLocationId(null);
            });
            suburbSelect.addEventListener('change', () => {
                setLocationId(suburbSelect.value || null);
            });

            // Ensure locationID is set before form submission
            const accSetupForm = document.querySelector('form[action="AccSetup.php"]');
            if (accSetupForm) {
                accSetupForm.addEventListener('submit', () => {
                    if (suburbSelect.value) {
                        locationIDInput.value = suburbSelect.value;
                    }
                });
            }

            /**
             * Initialize location selector on page load
             * Populates provinces and restores previously selected location if available
             */
            function initLocationSelector() {
                provinceSelect.innerHTML = '<option value="">Choose province</option>';
                Object.keys(LOC_TREE).sort().forEach(province => {
                    const opt = document.createElement('option');
                    opt.value = province;
                    opt.textContent = province;
                    provinceSelect.appendChild(opt);
                });
                resetCitySelect();
                
                // Restore previously selected location if available
                if (preLocationID && LOC_MAP[preLocationID]) {
                    const loc = LOC_MAP[preLocationID];
                    provinceSelect.value = loc.province;
                    populateCities(loc.province, loc.city);
                    populateSuburbs(loc.province, loc.city, preLocationID);
                    setLocationId(preLocationID);
                }
            }
            initLocationSelector();

            /* ═══════════════════════════════════════════════════════════
            LIVE VERIFIED STATUS NOTICE
            ═══════════════════════════════════════════════════════════
            
            Shows users what fields are still needed to achieve
            "Verified" status on their account.
            ═══════════════════════════════════════════════════════════ */
            const dobInput    = document.getElementById('dob');
            const mobileInput = document.getElementById('mobile');
            const zaIDInput  = document.getElementById('zaID');
            const notice      = document.getElementById('verified-notice');

            /**
             * Update verified status notice based on form completion
             * Shows remaining fields needed for verification
             */
            function updateNotice() {
                const dob    = dobInput.value.trim();
                const mobile = mobileInput.value.replace(/\D/g, '');
                const id     = zaIDInput.value.trim();

                const allFilled = dob && mobile.length === 10 && id.length === 13;

                if (allFilled) {
                    notice.className = 'verified-notice show is-verified';
                    notice.textContent = '✓ All optional fields complete — your account will be Verified upon saving.';
                } else {
                    const missing = [];
                    if (!dob)               missing.push('date of birth');
                    if (mobile.length < 10) missing.push('mobile number');
                    if (id.length < 13)     missing.push('SA ID number');
                    notice.className = 'verified-notice show';
                    notice.textContent = missing.length
                        ? `Still needed for Verified status: ${missing.join(', ')}.`
                        : '';
                }
            }

            // Update notice when optional fields change
            [dobInput, mobileInput, zaIDInput].forEach(el => el.addEventListener('input', updateNotice));
            updateNotice();

            /* Client-side pre-validation */
            document.getElementById('setup-form').addEventListener('submit', function (e) {
                const errs    = [];
                const name    = document.getElementById('name').value.trim();
                const surname = document.getElementById('surname').value.trim();
                const dob     = dobInput.value;
                const mobile  = mobileInput.value.replace(/\D/g, '');
                const id      = zaIDInput.value.trim();

                if (!name)                               errs.push('First name is required.');
                if (!surname)                            errs.push('Surname is required.');
                if (!dob)                                errs.push('Date of birth is required.');
                if (mobile && !/^0[6-8]\d{8}$/.test(mobile)) errs.push('Mobile must be a valid 10-digit SA number.');
                if (id     && !/^\d{13}$/.test(id))          errs.push('SA ID must be exactly 13 digits.');

                if (errs.length) {
                    e.preventDefault();
                    let box = document.querySelector('.error-messages');
                    if (!box) {
                        box = document.createElement('div');
                        box.className = 'error-messages';
                        box.setAttribute('role', 'alert');
                        this.prepend(box);
                    }
                    box.innerHTML = errs.map(m => `<p>${m}</p>`).join('');
                    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });

            /* Entry animation */
            window.addEventListener('load', () => {
                setTimeout(() => document.getElementById('setup-panel').classList.add('visible'), 80);
            });

            /* ═══════════════════════════════════════════════════════════
            LANGUAGE SWITCHING SYSTEM
            ═══════════════════════════════════════════════════════════ */

            const languageSelect = document.getElementById('language-select');

            /**
             * Apply language translation to all elements with data-i18n attribute
             * @param {string} lang - Language code (en, af, zu, xh, nso)
             */
            function applyLanguage(lang) {
                // Use global function to translate all page elements
                window.applyPageLanguage(lang);
                
                // Apply page-specific translations
                const descTextarea = document.getElementById('description');
                if (descTextarea) {
                    descTextarea.placeholder = window.translate('descriptionPlaceholder', lang);
                }

                // Update select options text
                const provinceSelect = document.getElementById('provinceSelect');
                const citySelect = document.getElementById('citySelect');
                const suburbSelect = document.getElementById('suburbSelect');
                
                if (provinceSelect && provinceSelect.querySelector('option')) {
                    provinceSelect.querySelector('option').textContent = 
                        window.translate('chooseProvince', lang);
                }
                if (citySelect && citySelect.querySelector('option')) {
                    citySelect.querySelector('option').textContent = 
                        window.translate('chooseCity', lang);
                }
                if (suburbSelect && suburbSelect.querySelector('option')) {
                    suburbSelect.querySelector('option').textContent = 
                        window.translate('chooseSuburb', lang);
                }
            }

            // Load saved language on page load
            window.addEventListener('load', () => {
                const savedLanguage = window.getCurrentLanguage();
                applyLanguage(savedLanguage);
                
                // Listen for header language changes
                const headerLangSelect = document.getElementById('header-language-select');
                if (headerLangSelect) {
                    headerLangSelect.addEventListener('change', function() {
                        applyLanguage(this.value);
                    });
                }
            });
        </script>
    </body>
</html>
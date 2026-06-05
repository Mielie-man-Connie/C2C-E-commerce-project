<?php
/* Admin Home: simple admin dashboard */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../LoginAndRegister/LoginPage.php'); exit; }

require_once __DIR__ . '/../data/database.php';
$userId = (int)$_SESSION['user_id'];

$isAdmin = false;
try {
    $s = $pdo->prepare('SELECT isAdmin FROM accounts WHERE userID=:id LIMIT 1');
    $s->execute([':id'=>$userId]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    $isAdmin = !empty($row['isAdmin']);
} catch (PDOException $e) {
    $isAdmin = ($userId === 1); // fallback: userID 1 is admin
}
if (!$isAdmin) { header('Location: ../Browse/Browse.php'); exit; }

// Run a query and safely return rows as an associative array.
// If the query fails, return an empty array so the admin UI can still render.
function safeQuery(PDO $pdo, string $sql): array {
    try { return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
    catch (PDOException $e) { return []; }
}

/* ── Check if reports.response column exists ── */
$reportsSelect = 'SELECT reportID, userID, reportTitle, reportDescription, reportedUserID, reportedItemID FROM reports ORDER BY reportID DESC';
try {
    $pdo->query('SELECT response FROM reports LIMIT 1');
    $reportsSelect = 'SELECT reportID, userID, reportTitle, reportDescription, reportedUserID, reportedItemID, response FROM reports ORDER BY reportID DESC';
} catch (PDOException $e) {}

$tables = [
    'accounts'  => safeQuery($pdo, 'SELECT userID, username, email, created_at, name, surname, dob, imageID, profileDescription, locationID, mobile, zaID, isVerified, isAdmin FROM accounts ORDER BY userID'),
    'items'     => safeQuery($pdo, 'SELECT itemID, userID, imageID, itemName, itemDescription, itemPrice, itemsAvailable, locationID, deliveryType FROM items ORDER BY itemID DESC'),
    'images'    => safeQuery($pdo, 'SELECT imageID, userID FROM images ORDER BY imageID DESC'),
    'locations' => safeQuery($pdo, 'SELECT locationID, province, city, suburb FROM locations ORDER BY province, city'),
    'orders'    => safeQuery($pdo, 'SELECT orderID, userID, itemID, orderDate, collectionDate FROM orders ORDER BY orderID DESC'),
    'escrow'    => safeQuery($pdo, 'SELECT escrowID, orderID, escrowState, stateDate FROM escrow ORDER BY stateDate DESC'),
    'reports'   => safeQuery($pdo, $reportsSelect),
    'reviews'   => safeQuery($pdo, 'SELECT reviewID, userID, itemID, reviewScore, reviewTitle, reviewDescription, reviewDate FROM reviews ORDER BY reviewID DESC'),
    'audits'    => safeQuery($pdo, 'SELECT auditID, action, auditDate, userID, auditTitle, auditDescription FROM audits ORDER BY auditDate DESC'),
];

/* ── Sanitize bad MySQL zero-dates to null so JS doesn't choke ── */
$badDates = ['0000-00-00', '0000-00-00 00:00:00'];
array_walk_recursive($tables, function (&$val) use ($badDates) {
    if (in_array($val, $badDates, true)) $val = null;
});

// Alphabetically sorted table configuration matching your schema
$tableConfig = [
    'accounts'  => ['pk'=>'userID',    'label'=>'Accounts',  'icon'=>'👤', 'cols'=>['userID','username','email','name','surname','dob','mobile','zaID','imageID','locationID','isVerified','isAdmin','created_at']],
    'audits'    => ['pk'=>'auditID',   'label'=>'Audits',    'icon'=>'📋', 'cols'=>['auditID','action','auditDate','userID','auditTitle','auditDescription']],
    'escrow'    => ['pk'=>'escrowID',  'label'=>'Escrow',    'icon'=>'🔒', 'cols'=>['escrowID','orderID','escrowState','stateDate']],
    'images'    => ['pk'=>'imageID',   'label'=>'Images',    'icon'=>'🖼️', 'cols'=>['imageID','userID']],
    'items'     => ['pk'=>'itemID',    'label'=>'Items',     'icon'=>'📦', 'cols'=>['itemID','userID','itemName','itemPrice','itemsAvailable','deliveryType','locationID','imageID']],
    'locations' => ['pk'=>'locationID','label'=>'Locations', 'icon'=>'📍', 'cols'=>['locationID','province','city','suburb']],
    'orders'    => ['pk'=>'orderID',   'label'=>'Orders',    'icon'=>'🛒', 'cols'=>['orderID','userID','itemID','orderDate','collectionDate']],
    'reports'   => ['pk'=>'reportID',  'label'=>'Reports',   'icon'=>'⚠️', 'cols'=>['reportID','userID','reportTitle','reportedUserID','reportedItemID','response']],
    'reviews'   => ['pk'=>'reviewID',  'label'=>'Reviews',   'icon'=>'⭐', 'cols'=>['reviewID','userID','itemID','reviewScore','reviewTitle','reviewDescription','reviewDate']],
];

$editFields = [
    'accounts'  => [
        ['key'=>'username','label'=>'Username','type'=>'text'],
        ['key'=>'email','label'=>'Email','type'=>'email'],
        ['key'=>'name','label'=>'First name','type'=>'text'],
        ['key'=>'surname','label'=>'Surname','type'=>'text'],
        ['key'=>'dob','label'=>'Date of birth','type'=>'date'],
        ['key'=>'mobile','label'=>'Mobile','type'=>'text'],
        ['key'=>'zaID','label'=>'SA ID','type'=>'text'],
        ['key'=>'locationID','label'=>'Location ID','type'=>'number'],
        ['key'=>'profileDescription','label'=>'Description','type'=>'textarea'],
        ['key'=>'isVerified','label'=>'Verified','type'=>'checkbox'],
        ['key'=>'isAdmin','label'=>'Admin','type'=>'checkbox'],
        ['key'=>'imageID','label'=>'Profile Image ID','type'=>'text'],
    ],
    'items'     => [
        ['key'=>'userID','label'=>'User ID','type'=>'number'],
        ['key'=>'itemName','label'=>'Name','type'=>'text'],
        ['key'=>'itemDescription','label'=>'Description','type'=>'textarea'],
        ['key'=>'itemPrice','label'=>'Price','type'=>'number'],
        ['key'=>'itemsAvailable','label'=>'Qty','type'=>'number'],
        ['key'=>'locationID','label'=>'Location ID','type'=>'number'],
        ['key'=>'deliveryType','label'=>'Delivery','type'=>'select','options'=>['P','E']],
        ['key'=>'imageID','label'=>'Image IDs (JSON array)','type'=>'textarea'],
    ],
    'images'    => [
        ['key'=>'userID','label'=>'User ID','type'=>'number'],
    ],
    'locations' => [
        ['key'=>'province','label'=>'Province','type'=>'text'],
        ['key'=>'city','label'=>'City','type'=>'text'],
        ['key'=>'suburb','label'=>'Suburb','type'=>'text'],
    ],
    'orders'    => [
        ['key'=>'userID','label'=>'User ID','type'=>'number'],
        ['key'=>'itemID','label'=>'Item ID','type'=>'number'],
        ['key'=>'orderDate','label'=>'Order Date','type'=>'datetime-local'],
        ['key'=>'collectionDate','label'=>'Collection Date','type'=>'datetime-local'],
    ],
    'escrow'    => [
        ['key'=>'orderID','label'=>'Order ID','type'=>'number'],
        ['key'=>'escrowState','label'=>'State','type'=>'text'],
        ['key'=>'stateDate','label'=>'Date','type'=>'datetime-local'],
    ],
    'reports'   => [
        ['key'=>'userID','label'=>'Reporter User ID','type'=>'number'],
        ['key'=>'reportTitle','label'=>'Title','type'=>'text'],
        ['key'=>'reportDescription','label'=>'Description','type'=>'textarea'],
        ['key'=>'reportedUserID','label'=>'Reported User ID','type'=>'number'],
        ['key'=>'reportedItemID','label'=>'Reported Item ID','type'=>'number'],
        ['key'=>'response','label'=>'Admin Response','type'=>'textarea'],
    ],
    'reviews'   => [
        ['key'=>'userID','label'=>'Reviewer User ID','type'=>'number'],
        ['key'=>'itemID','label'=>'Item ID','type'=>'number'],
        ['key'=>'reviewScore','label'=>'Score (1-10)','type'=>'number'],
        ['key'=>'reviewTitle','label'=>'Title','type'=>'text'],
        ['key'=>'reviewDescription','label'=>'Description','type'=>'textarea'],
        ['key'=>'reviewDate','label'=>'Review Date','type'=>'datetime-local'],
    ],
    'audits'    => [
        ['key'=>'userID','label'=>'User ID','type'=>'number'],
        ['key'=>'action','label'=>'Action','type'=>'text'],
        ['key'=>'auditTitle','label'=>'Title','type'=>'text'],
        ['key'=>'auditDescription','label'=>'Description','type'=>'textarea'],
        ['key'=>'auditDate','label'=>'Date','type'=>'datetime-local'],
    ],
];

$tablesJson     = json_encode($tables,      JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
$configJson     = json_encode($tableConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
$editFieldsJson = json_encode($editFields,  JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TradeSA – Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="AdminHome.css">
    <link rel="stylesheet" href="../data/preset.css">
</head>
<body>
<?php include '../HeaderAndFooter/Header.php'; ?>

<div class="admin-wrap">

    <nav class="admin-nav">
        <div class="nav-title">Database</div>
        <?php foreach ($tableConfig as $tName => $cfg): ?>
        <button class="nav-btn <?= $tName==='accounts'?'active':'' ?>"
                data-table="<?= $tName ?>"
                onclick="switchTable('<?= $tName ?>')">
            <span class="nav-icon"><?= $cfg['icon'] ?></span>
            <span class="nav-label"><?= $cfg['label'] ?></span>
            <span class="nav-count"><?= count($tables[$tName]) ?></span>
        </button>
        <?php endforeach; ?>
    </nav>

    <div class="admin-main">
        <div class="main-toolbar">
            <div class="toolbar-left">
                <span class="toolbar-title" id="toolbarTitle">Accounts</span>
                <span class="toolbar-count" id="toolbarCount"></span>
            </div>
            <div class="toolbar-right">
                <input type="text" class="search-input" id="tableSearch"
                       placeholder="Search…" oninput="filterTable()">
                <button class="btn-add" onclick="openAddPanel()">+ Add row</button>
            </div>
        </div>

        <div class="relations-banner" id="relationsBanner" style="display:none"></div>

        <div class="table-wrap">
            <table class="data-table" id="dataTable">
                <thead id="tableHead"></thead>
                <tbody id="tableBody"></tbody>
            </table>
            <div class="table-empty" id="tableEmpty" style="display:none">
                <div class="te-icon">🗂️</div>
                <div class="te-msg">No records found.</div>
            </div>
        </div>
    </div>

    <aside class="edit-panel" id="editPanel">
        <div class="ep-head">
            <span class="ep-title" id="epTitle">Edit row</span>
            <button class="ep-close" onclick="closePanel()">✕</button>
        </div>
        <div class="ep-body" id="epBody"></div>
        <div class="ep-foot" id="epFoot"></div>
    </aside>

</div>

<div class="modal-overlay" id="delModal" onclick="closeDelModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-icon">🗑️</div>
        <h2 class="modal-h">Delete record?</h2>
        <p class="modal-p" id="delModalMsg">This cannot be undone.</p>
        <div class="modal-btns">
            <button class="mbtn-cancel" onclick="closeDelModal()">Cancel</button>
            <button class="mbtn-delete" id="delConfirmBtn">Delete</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const DB          = <?= $tablesJson ?>;
const TABLE_CFG   = <?= $configJson ?>;
const EDIT_FIELDS = <?= $editFieldsJson ?>;

let activeTable  = 'accounts';
let selectedRow  = null;
let selectedRels = {};
let pendingDelCb = null;
let filteredRows = [];

function sanitiseDateVal(raw, inputType) {
    if (!raw || raw === '0000-00-00' || raw === '0000-00-00 00:00:00') return '';
    if (inputType === 'datetime-local') {
        return String(raw).replace(' ', 'T').slice(0, 16);
    }
    return String(raw).slice(0, 10);
}

function itemImageIds(item) {
    try { return JSON.parse(item.imageID || '[]').map(Number); } catch { return []; }
}

/* ══ RELATIONS (Now fully handles Reviews table tracking) ══ */
function computeRelations(table, row) {
    const rel = {};
    Object.keys(TABLE_CFG).forEach(t => rel[t] = new Set());

    if (table === 'accounts') {
        const id = parseInt(row.userID);
        DB.items.forEach(i   => { if (parseInt(i.userID)   === id) rel.items.add(i.itemID); });
        DB.images.forEach(i  => { if (parseInt(i.userID)   === id) rel.images.add(i.imageID); });
        DB.orders.forEach(o  => { if (parseInt(o.userID)   === id) rel.orders.add(o.orderID); });
        DB.audits.forEach(a  => { if (parseInt(a.userID)   === id) rel.audits.add(a.auditID); });
        DB.reviews.forEach(r => { if (parseInt(r.userID)   === id) rel.reviews.add(r.reviewID); });
        DB.reports.forEach(r => {
            if (parseInt(r.userID) === id || parseInt(r.reportedUserID) === id) rel.reports.add(r.reportID);
        });
    }
    if (table === 'items') {
        const id = parseInt(row.itemID);
        rel.accounts.add(parseInt(row.userID));
        itemImageIds(row).forEach(imgId => rel.images.add(imgId));
        if (row.locationID) rel.locations.add(parseInt(row.locationID));
        DB.orders.forEach(o  => { if (parseInt(o.itemID)        === id) rel.orders.add(o.orderID); });
        DB.reviews.forEach(r => { if (parseInt(r.itemID)       === id) rel.reviews.add(r.reviewID); });
        DB.reports.forEach(r => { if (parseInt(r.reportedItemID)=== id) rel.reports.add(r.reportID); });
    }
    if (table === 'images') {
        rel.accounts.add(parseInt(row.userID));
        const id = parseInt(row.imageID);
        DB.items.forEach(i => { if (itemImageIds(i).includes(id)) rel.items.add(i.itemID); });
    }
    if (table === 'locations') {
        const id = parseInt(row.locationID);
        DB.items.forEach(i => { if (parseInt(i.locationID) === id) rel.items.add(i.itemID); });
    }
    if (table === 'orders') {
        const id = parseInt(row.orderID);
        rel.accounts.add(parseInt(row.userID));
        rel.items.add(parseInt(row.itemID));
        DB.escrow.forEach(e => { if (parseInt(e.orderID) === id) rel.escrow.add(e.escrowID); });
        const item = DB.items.find(i => parseInt(i.itemID) === parseInt(row.itemID));
        if (item?.locationID) rel.locations.add(parseInt(item.locationID));
    }
    if (table === 'escrow') {
        const oId = parseInt(row.orderID);
        rel.orders.add(oId);
        const order = DB.orders.find(o => parseInt(o.orderID) === oId);
        if (order) { rel.accounts.add(parseInt(order.userID)); rel.items.add(parseInt(order.itemID)); }
    }
    if (table === 'reports') {
        if (row.userID)         rel.accounts.add(parseInt(row.userID));
        if (row.reportedUserID) rel.accounts.add(parseInt(row.reportedUserID));
        if (row.reportedItemID) rel.items.add(parseInt(row.reportedItemID));
    }
    if (table === 'reviews') {
        if (row.userID) rel.accounts.add(parseInt(row.userID));
        if (row.itemID) rel.items.add(parseInt(row.itemID));
    }
    if (table === 'audits') {
        if (row.userID) rel.accounts.add(parseInt(row.userID));
    }
    Object.keys(rel).forEach(t => { rel[t].delete(0); rel[t].delete(NaN); });
    return rel;
}

/* ══ RENDER TABLE ══ */
function switchTable(name) {
    activeTable = name;
    document.querySelectorAll('.nav-btn').forEach(b => b.classList.toggle('active', b.dataset.table === name));
    document.getElementById('toolbarTitle').textContent = TABLE_CFG[name].label;
    document.getElementById('tableSearch').value = '';
    renderTable();
    updateRelationsBanner();
    closePanel();
}

function filterTable() { renderTable(); }

function renderTable() {
    const tableCfg = TABLE_CFG[activeTable];
    const rows     = DB[activeTable] || [];
    const search   = document.getElementById('tableSearch').value.trim().toLowerCase();
    const pk = tableCfg.pk, cols = tableCfg.cols;
    document.getElementById('tableHead').innerHTML =
        '<tr>' + cols.map(c => `<th>${c}</th>`).join('') + '<th class="th-actions">Actions</th></tr>';
    filteredRows = search
        ? rows.filter(r => Object.values(r).some(v => String(v??'').toLowerCase().includes(search)))
        : rows;

    const tbody = document.getElementById('tableBody');
    tbody.innerHTML = '';
    document.getElementById('tableEmpty').style.display  = filteredRows.length ? 'none' : 'block';
    document.getElementById('toolbarCount').textContent  = `${filteredRows.length} record${filteredRows.length!==1?'s':''}`;
    filteredRows.forEach(row => {
        const pkVal     = row[pk];
        const isSel     = selectedRow && selectedRow.table === activeTable && selectedRow.pkVal == pkVal;
        const isRel     = !isSel && selectedRels[activeTable]?.has(Number(pkVal));
        const tr        = document.createElement('tr');
        tr.className    = isSel ? 'row-selected' : isRel ? 'row-related' : '';
        tr.dataset.pk   = pkVal;

        cols.forEach(col => {
            const td  = document.createElement('td');
            const val = row[col] ?? '';
            if (activeTable === 'images' && col === 'imageID') {
                td.innerHTML = `<span class="img-thumb-wrap"><img src="../data/getImage.php?id=${val}" class="thumb-img" loading="lazy"> <span class="thumb-id">#${val}</span></span>`;
            } else if (col === 'isVerified' || col === 'isAdmin') {
                td.innerHTML = val ? '<span class="badge-yes">✓ Yes</span>' : '<span class="badge-no">No</span>';
            } else if (col === 'deliveryType') {
                td.innerHTML = val === 'E' ? '<span class="badge-escrow">Escrow</span>' : '<span class="badge-pickup">Pickup</span>';
            } else {
                const str = String(val ?? '');
                td.textContent = str.length > 40 ? str.slice(0,40) + '…' : str;
                td.title = str;
            }
            tr.appendChild(td);
        });

        const actTd = document.createElement('td');
        actTd.className = 'td-actions';
        actTd.innerHTML = `
            <button class="act-btn act-edit"   onclick="openEditPanel(event,'${activeTable}',${JSON.stringify(pkVal)})">✏ Edit</button>
            <button class="act-btn act-delete" onclick="promptDelete(event,'${activeTable}',${JSON.stringify(pkVal)})">🗑</button>`;
        tr.appendChild(actTd);

        tr.addEventListener('click', e => { if (!e.target.closest('.act-btn')) selectRow(activeTable, pkVal, row); });
        tbody.appendChild(tr);
    });
}

/* ══ SELECTION ══ */
function selectRow(table, pkVal, row) {
    if (selectedRow && selectedRow.table === table && selectedRow.pkVal == pkVal) {
        selectedRow = null;
        selectedRels = {};
    } else {
        selectedRow  = { table, pkVal, row };
        selectedRels = computeRelations(table, row);
    }
    renderTable();
    updateRelationsBanner();
    if (selectedRow) openEditPanel(null, table, pkVal);
    else closePanel();
}

function updateRelationsBanner() {
    const banner = document.getElementById('relationsBanner');
    if (!selectedRow) { banner.style.display = 'none'; return; }
    const chips = Object.entries(selectedRels)
        .filter(([t,s]) => s.size > 0 && t !== selectedRow.table)
        .map(([t,s]) => {
            const c = TABLE_CFG[t];
            return `<button class="rel-chip${t===activeTable?' chip-active':''}" onclick="switchTable('${t}')">${c.icon} ${c.label} <strong>${s.size}</strong></button>`;
        }).join('');
    if (!chips) { banner.style.display = 'none'; return; }
    banner.style.display = 'flex';
    banner.innerHTML = `<span class="rb-label">Related:</span> ${chips}`;
}

/* ══ EDIT PANEL (Duplicate elements removed from Reports view) ══ */
function openEditPanel(e, table, pkVal) {
    if (e) e.stopPropagation();
    const tableCfg = TABLE_CFG[table];
    const record   = DB[table].find(r => String(r[tableCfg.pk]) === String(pkVal));
    if (!record) return;

    document.getElementById('epTitle').textContent = `Edit ${tableCfg.label} #${pkVal}`;
    document.getElementById('editPanel').classList.add('open');
    const fields = EDIT_FIELDS[table] || [];

    let imgPreview = '';
    if (table === 'images') {
        imgPreview = `<div class="ep-img-preview"><img src="../data/getImage.php?id=${pkVal}" alt="img" onerror="this.parentElement.innerHTML='<span class=ep-no-img>No image</span>'"></div>
                     <div class="ep-upload-section">
                        <label class="ep-label">Upload new image</label>
                        <input type="file" class="ep-file-input" id="imageUpload" accept="image/*">
                        <button class="ep-upload-btn" onclick="uploadImage(${pkVal})">📤 Upload</button>
                     </div>`;
    }

    document.getElementById('epBody').innerHTML = imgPreview + fields.map(f => {
        const fieldValue = record[f.key] ?? '';
        if (f.type === 'checkbox') {
            return `<div class="ep-field"><label class="ep-label">${f.label}</label>
                    <input type="checkbox" class="ep-check" data-key="${f.key}" ${fieldValue?'checked':''}></div>`;
        }
        if (f.type === 'textarea') {
            return `<div class="ep-field"><label class="ep-label">${f.label}</label>
                    <textarea class="ep-input ep-ta" data-key="${f.key}" rows="3">${escHtml(String(fieldValue))}</textarea></div>`;
        }
        if (f.type === 'select') {
            const opts = (f.options||[]).map(o=>`<option value="${o}"${fieldValue==o?' selected':''}>${o}</option>`).join('');
            return `<div class="ep-field"><label class="ep-label">${f.label}</label>
                    <select class="ep-input ep-select" data-key="${f.key}">${opts}</select></div>`;
        }

        const isDateType = f.type === 'date' || f.type === 'datetime-local';
        const displayVal = isDateType ? sanitiseDateVal(fieldValue, f.type) : escHtml(String(fieldValue));
        return `<div class="ep-field"><label class="ep-label">${f.label}</label>
                <input type="${f.type}" class="ep-input" data-key="${f.key}" value="${displayVal}"></div>`;
    }).join('');

    document.getElementById('epFoot').innerHTML = `
        <button class="ep-save" onclick="saveRow('${table}',${JSON.stringify(pkVal)})">💾 Save changes</button>
        <button class="ep-del"  onclick="promptDelete(null,'${table}',${JSON.stringify(pkVal)})">🗑 Delete</button>`;
    attachPanelKeyHandler();
}

function openAddPanel() {
    const table  = activeTable;
    const fields = EDIT_FIELDS[table] || [];
    document.getElementById('epTitle').textContent = `Add to ${TABLE_CFG[table].label}`;
    document.getElementById('editPanel').classList.add('open');
    let imageUploadHtml = '';
    if (table === 'images') {
        imageUploadHtml = `<div style="background:var(--surface-dim);padding:0.75rem;border-radius:0.5rem;margin-bottom:1rem">
                            <label class="ep-label">📤 Upload image file</label>
                            <input type="file" class="ep-file-input" id="imageUploadNew" accept="image/*">
                            <p style="font-size:0.8rem;color:var(--muted);margin-top:0.5rem">Image will be compressed to 512x512 max</p>
                          </div>`;
    }
    
    document.getElementById('epBody').innerHTML = imageUploadHtml + fields.map(f => {
        if (f.type === 'checkbox')
            return `<div class="ep-field"><label class="ep-label">${f.label}</label><input type="checkbox" class="ep-check" data-key="${f.key}"></div>`;
        if (f.type === 'textarea')
            return `<div class="ep-field"><label class="ep-label">${f.label}</label><textarea class="ep-input ep-ta" data-key="${f.key}" rows="3"></textarea></div>`;
        if (f.type === 'select') {
            const opts = (f.options||[]).map(o=>`<option value="${o}">${o}</option>`).join('');
            return `<div class="ep-field"><label class="ep-label">${f.label}</label><select class="ep-input ep-select" data-key="${f.key}">${opts}</select></div>`;
        }
        return `<div class="ep-field"><label class="ep-label">${f.label}</label><input type="${f.type}" class="ep-input" data-key="${f.key}" value=""></div>`;
    }).join('');
    document.getElementById('epFoot').innerHTML = `<button class="ep-save" onclick="insertRow('${table}')">➕ Insert row</button>`;
    attachPanelKeyHandler();
}

function closePanel() { document.getElementById('editPanel').classList.remove('open'); }

function attachPanelKeyHandler() {
    const panel = document.getElementById('editPanel');
    const inputs = panel.querySelectorAll('.ep-input:not(.ep-ta), .ep-check, .ep-select');
    inputs.forEach(inp => {
        const newInp = inp.cloneNode(true);
        inp.parentNode.replaceChild(newInp, inp);
        newInp.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const saveBtn = panel.querySelector('.ep-save');
                if (saveBtn) {
                    console.log('Enter pressed, clicking save button');
                    saveBtn.click();
                }
            }
        });
    });
}

/* ══ CRUD ══ */
function collectPanelData() {
    const data = {};
    document.querySelectorAll('#epBody [data-key]').forEach(el => {
        data[el.dataset.key] = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value;
    });
    return data;
}

async function uploadImage(imageID) {
    const fileInput = document.getElementById('imageUpload');
    if (!fileInput.files.length) { showToast('⚠ Select an image first', true); return; }
    
    const file = fileInput.files[0];
    const reader = new FileReader();
    reader.onload = async (e) => {
        const base64 = e.target.result.split(',')[1];
        const res = await apiCall({ action:'upload_image', imageID, imgData: base64 });
        if (res.ok) {
            const img = DB.images.find(i => parseInt(i.imageID) === parseInt(imageID));
            if (img) img.imgData = base64;
            document.querySelector('.ep-img-preview img').src = '../data/getImage.php?id=' + imageID + '&t=' + Date.now();
            showToast('✓ Image uploaded');
            fileInput.value = '';
        } else {
            showToast('⚠ ' + (res.error || 'Upload failed'), true);
        }
    };
    reader.readAsDataURL(file);
}

const ADMIN_API_URL = new URL('adminActions.php', window.location.href).href;

async function apiCall(body) {
    const r = await fetch(ADMIN_API_URL, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(body)
    });
    const text = await r.text();
    try { return JSON.parse(text); }
    catch (e) {
        console.error('adminActions response was not JSON:', text);
        return { ok: false, error: 'Server returned non-JSON. Check adminActions.php exists in /Admin/ folder.' };
    }
}

async function saveRow(table, pkVal) {
    const data = collectPanelData();
    const res  = await apiCall({ action:'update', table, pk: pkVal, data });
    if (res.ok) {
        const cfg = TABLE_CFG[table];
        const idx = DB[table].findIndex(r => String(r[cfg.pk]) === String(pkVal));
        if (idx >= 0) Object.assign(DB[table][idx], data);
        showToast('✓ Saved');
        renderTable();
    } else {
        showToast('⚠ ' + (res.error || 'Save failed'), true);
    }
}

async function insertRow(table) {
    const data = collectPanelData();
    console.log('Inserting into', table, 'with data:', data);
    const res  = await apiCall({ action:'insert', table, data });
    console.log('Insert response:', res);
    if (res.ok) {
        if (table === 'images') {
            const fileInput = document.getElementById('imageUploadNew');
            if (fileInput && fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const reader = new FileReader();
                reader.onload = async (e) => {
                    const base64 = e.target.result.split(',')[1];
                    const uploadRes = await apiCall({ action:'upload_image', imageID: res.data.newId, imgData: base64 });
                    if (uploadRes.ok) {
                        showToast('✓ Image inserted and uploaded');
                    } else {
                        showToast('⚠ Image created but upload failed: ' + (uploadRes.error || 'Unknown error'), true);
                    }
                    closePanel();
                    location.reload();
                };
                reader.readAsDataURL(file);
                return;
            }
        }
        showToast('✓ Inserted');
        closePanel();
        location.reload();
    } else {
        showToast('⚠ ' + (res.error || 'Insert failed'), true);
    }
}

async function resolveReport(reportId) {
    const response = document.getElementById('resolveTA')?.value ?? '';
    const res = await apiCall({ action:'resolve_report', reportID: reportId, response });
    if (res.ok) {
        const r = DB.reports.find(x => parseInt(x.reportID) === reportId);
        if (r) r.response = response;
        showToast('✓ Response saved');
    } else {
        showToast('⚠ ' + (res.error || 'Could not save'), true);
    }
}

function promptDelete(e, table, pkVal) {
    if (e) e.stopPropagation();
    const cfg = TABLE_CFG[table];
    document.getElementById('delModalMsg').textContent = `Delete ${cfg.label} #${pkVal}?\nThis cannot be undone.`;
    document.getElementById('delModal').classList.add('open');
    document.body.style.overflow = 'hidden';
    pendingDelCb = async () => {
        const res = await apiCall({ action:'delete', table, pk: pkVal });
        closeDelModal();
        if (res.ok) {
            const idx = DB[table].findIndex(r => String(r[cfg.pk]) === String(pkVal));
            if (idx >= 0) DB[table].splice(idx, 1);
            if (selectedRow?.table === table && selectedRow.pkVal == pkVal) { selectedRow = null; selectedRels = {}; }
            closePanel(); renderTable(); updateRelationsBanner();
            showToast('✓ Deleted');
        } else {
            showToast('⚠ ' + (res.error || 'Delete failed'), true);
        }
    };
}
document.getElementById('delConfirmBtn').addEventListener('click', () => pendingDelCb?.());

function closeDelModal() {
    document.getElementById('delModal').classList.remove('open');
    document.body.style.overflow = '';
    pendingDelCb = null;
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(msg, err=false) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className   = 'toast show' + (err?' toast-err':'');
    clearTimeout(t._t);
    t._t = setTimeout(() => t.className = 'toast', 3000);
}
document.addEventListener('keydown', e => { if (e.key==='Escape') { closeDelModal(); closePanel(); } });

switchTable('accounts');
</script>
</body>
</html>
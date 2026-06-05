<?php
session_start();
require_once __DIR__ . '/../data/database.php';

// 1. Fetch all items joined with their location
try {
    $stmt = $pdo->query(
        'SELECT
            i.itemID,
            i.userID,
            i.imageID        AS imageJson,   -- longtext JSON array e.g. [4,7,12]
            i.itemName,
            i.itemDescription,
            i.itemPrice,
            i.itemsAvailable,
            i.deliveryType,                  -- char(1): P = Pickup, E = Escrow
            l.province,
            l.city,
            l.suburb
         FROM items i
         LEFT JOIN locations l ON l.locationID = i.locationID
         ORDER BY i.itemID DESC'
    );
    $rawItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $rawItems = [];
    error_log('Browse items error: ' . $e->getMessage());
}

/*
 * Parse the imageJson field → take the first image ID in the array.
 * items.imageID stores a JSON array like [4, 7, 12].
 * Only need the first one for the thumbnail.
 */
$items = [];
foreach ($rawItems as $row) {
    $ids = json_decode($row['imageJson'] ?? '[]', true);
    $row['firstImageID'] = (!empty($ids) && is_array($ids)) ? (int) $ids[0] : null;
    unset($row['imageJson']);
    $items[] = $row;
}

// 2. Fetch all locations for cascading dropdowns
try {
    $locStmt   = $pdo->query('SELECT locationID, province, city, suburb FROM locations ORDER BY province, city, suburb');
    $locations = $locStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $locations = [];
    error_log('Browse locations error: ' . $e->getMessage());
}

/* Build a nested structure for JS:
   { "Gauteng": { "Johannesburg": ["Sandton","Melville",...], ... }, ... }
*/
$locationTree = [];
foreach ($locations as $loc) {
    $p = $loc['province'];
    $c = $loc['city'];
    $s = $loc['suburb'];
    if (!isset($locationTree[$p]))       $locationTree[$p] = [];
    if (!isset($locationTree[$p][$c]))   $locationTree[$p][$c] = [];
    if ($s && !in_array($s, $locationTree[$p][$c], true)) {
        $locationTree[$p][$c][] = $s;
    }
}
ksort($locationTree);

$itemsJson    = json_encode($items,        JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$locTreeJson  = json_encode($locationTree, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TradeSA – Browse Marketplace</title>
    <link rel="stylesheet" href="Browse.css">
    <link rel="stylesheet" href="../data/preset.css">
    <script src="../data/translations.js"></script>
</head>
<body>

    <?php include '../HeaderAndFooter/Header.php'; ?>

    <div class="browse-wrap">

        <!-- ── Title row — over the background, outside the white panel ── -->
        <div class="browse-title">
            <div>
                <p class="eyebrow" data-i18n="marketplace">Marketplace</p>
                <h1 data-i18n="findTreasure">Find your next secondhand treasure</h1>
            </div>
        </div>

        <!-- ── Two columns ── -->
        <div class="browse-columns">

            <!-- LEFT: dark sidebar -->
            <aside class="sidebar">
                <div class="sidebar-top">
                    <h2 data-i18n="filter">Filter items</h2>
                    <p data-i18n="filterHint">Narrow results by location, price, or delivery type.</p>
                </div>

                <div class="filter-group">
                    <label for="searchInput" data-i18n="search">Search</label>
                    <input type="text" class="input-field" id="searchInput" data-i18n-placeholder="searchPlaceholder" placeholder="Search items…">
                </div>

                <!-- Cascading location filters -->
                <div class="filter-group">
                    <label for="provinceSelect" data-i18n="province">Province</label>
                    <select class="input-field" id="provinceSelect">
                        <option value="" data-i18n="allProvinces">All provinces</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="citySelect" data-i18n="city">City</label>
                    <select class="input-field" id="citySelect" disabled>
                        <option value="" data-i18n="allCities">All cities</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="suburbSelect" data-i18n="suburb">Suburb</label>
                    <select class="input-field" id="suburbSelect" disabled>
                        <option value="" data-i18n="allSuburbs">All suburbs</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label data-i18n="price">Price Range (R)</label>
                    <input type="number" class="input-field" id="minPrice" placeholder="Min" min="0">
                    <input type="number" class="input-field" id="maxPrice" placeholder="Max" min="0">
                </div>

                <div class="filter-group">
                    <label for="deliverySelect" data-i18n="deliveryType">Delivery type</label>
                    <select class="input-field" id="deliverySelect">
                        <option value="" data-i18n="any">Any</option>
                        <option value="P" data-i18n="pickup">Pickup</option>
                        <option value="E" data-i18n="escrow">Escrow</option>
                    </select>
                </div>

                <button class="clear-btn" id="clearFilters" data-i18n="clearFilters">Clear all filters</button>
            </aside>

            

            <!-- RIGHT: white item panel -->
            <div class="white-panel">
                
                <p class="result-count" id="resultCount"></p>

                <div class="scroll-container">
                    <div class="items-grid" id="itemsGrid"></div>
                </div>
            </div>

        </div>
    </div>   

    <?php include '../HeaderAndFooter/Footer.php'; ?>

<script>
/* Initialize language on page load */
window.addEventListener('load', () => {
    if (window.applyPageLanguage) {
        window.applyPageLanguage(window.getCurrentLanguage());
        updateSelectPlaceholders();
    }
    
    // Listen for header language changes
    const headerLangSelect = document.getElementById('header-language-select');
    if (headerLangSelect) {
        headerLangSelect.addEventListener('change', function() {
            window.applyPageLanguage(this.value);
            updateSelectPlaceholders();
            applyFilters(); // Re-render items with translated delivery labels
        });
    }
});

/* ── Update select placeholder options when language changes ── */
function updateSelectPlaceholders() {
    const lang = window.getCurrentLanguage();
    
    // Update the first option in each select
    const firstProvinceOpt = provinceSelect.querySelector('option[value=""]');
    if (firstProvinceOpt) {
        firstProvinceOpt.textContent = window.translate('allProvinces', lang);
    }
    
    const firstCityOpt = citySelect.querySelector('option[value=""]');
    if (firstCityOpt) {
        firstCityOpt.textContent = window.translate('allCities', lang);
    }
    
    const firstSuburbOpt = suburbSelect.querySelector('option[value=""]');
    if (firstSuburbOpt) {
        firstSuburbOpt.textContent = window.translate('allSuburbs', lang);
    }
    
    const firstDeliveryOpt = deliverySelect.querySelector('option[value=""]');
    if (firstDeliveryOpt) {
        firstDeliveryOpt.textContent = window.translate('any', lang);
    }
}

/* ── Data from PHP ── */
const ALL_ITEMS   = <?= $itemsJson ?>;
const LOC_TREE    = <?= $locTreeJson ?>;

/* ── DOM refs ── */
const searchInput    = document.getElementById('searchInput');
const provinceSelect = document.getElementById('provinceSelect');
const citySelect     = document.getElementById('citySelect');
const suburbSelect   = document.getElementById('suburbSelect');
const minPriceInput  = document.getElementById('minPrice');
const maxPriceInput  = document.getElementById('maxPrice');
const deliverySelect = document.getElementById('deliverySelect');
const resultCount    = document.getElementById('resultCount');
const clearBtn       = document.getElementById('clearFilters');

/* ── ZAR currency formatter ── */
const zarFmt = new Intl.NumberFormat('en-ZA', { style: 'currency', currency: 'ZAR', minimumFractionDigits: 2 });

/* ── Populate province dropdown from location tree ── */
Object.keys(LOC_TREE).sort().forEach(province => {
    const opt = document.createElement('option');
    opt.value = province;
    opt.textContent = province;
    provinceSelect.appendChild(opt);
});

/* ── Cascade: province → city ── */
provinceSelect.addEventListener('change', function () {
    const prov = this.value;

    // Reset city
    citySelect.innerHTML = '<option value="">All cities</option>';
    citySelect.disabled = true;

    // Reset suburb
    suburbSelect.innerHTML = '<option value="">All suburbs</option>';
    suburbSelect.disabled = true;

    if (prov && LOC_TREE[prov]) {
        Object.keys(LOC_TREE[prov]).sort().forEach(city => {
            const opt = document.createElement('option');
            opt.value = city;
            opt.textContent = city;
            citySelect.appendChild(opt);
        });
        citySelect.disabled = false;
    }

    applyFilters();
});

/* ── Cascade: city → suburb ── */
citySelect.addEventListener('change', function () {
    const prov = provinceSelect.value;
    const city = this.value;

    suburbSelect.innerHTML = '<option value="">All suburbs</option>';
    suburbSelect.disabled = true;

    if (prov && city && LOC_TREE[prov]?.[city]) {
        [...LOC_TREE[prov][city]].sort().forEach(suburb => {
            const opt = document.createElement('option');
            opt.value = suburb;
            opt.textContent = suburb;
            suburbSelect.appendChild(opt);
        });
        suburbSelect.disabled = false;
    }

    applyFilters();
});

suburbSelect.addEventListener('change', applyFilters);

/* ── Render cards ── */
function renderItems(list) {
    const grid = document.getElementById('itemsGrid');
    grid.innerHTML = '';
    
    // Translate the result count
    const lang = window.getCurrentLanguage();
    const itemsText = list.length !== 1 
        ? window.translate('itemsFound', lang)
        : window.translate('itemFound', lang);
    resultCount.textContent = `${list.length} ${itemsText}`;

    if (list.length === 0) {
        const emptyTitle = window.translate('noResults', lang);
        const emptySub = window.translate('noResultsHint', lang);
        grid.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">🔍</div>
                <div class="empty-title">${emptyTitle}</div>
                <div class="empty-sub">${emptySub}</div>
            </div>`;
        return;
    }

    list.forEach(item => {
        const card = document.createElement('article');
        card.className = 'item-card';
        card.title = item.itemName;
        
        /* Check if out of stock */
        const isOutOfStock = parseInt(item.itemsAvailable) <= 0;
        if (isOutOfStock) card.classList.add('out-of-stock');

        /* Thumbnail — first image from the JSON array */
        const thumbHtml = item.firstImageID
            ? `<img src="../data/getImage.php?id=${item.firstImageID}"
                    alt="${item.itemName}"
                    class="item-img"
                    loading="lazy">`
            : `<div class="item-img-placeholder">${item.itemName.charAt(0).toUpperCase()}</div>`;

        /* Delivery badge - translate the labels */
        let deliveryLabel = '';
        if (item.deliveryType === 'A') {
            deliveryLabel = window.translate('any', lang);
        } else if (item.deliveryType === 'E') {
            deliveryLabel = window.translate('escrow', lang);
        } else if (item.deliveryType === 'P') {
            deliveryLabel = window.translate('pickup', lang);
        }
        const badgeClass    =   item.deliveryType === 'E' ? 'badge-escrow' : 'badge-pickup';

        /* Location string */
        const locParts = [item.suburb, item.city, item.province].filter(Boolean);
        const locStr   = locParts.length ? `📍 ${locParts[0]}` : '';
        
        /* Out of stock overlay */
        const outOfStockOverlay = isOutOfStock ? `<div class="out-of-stock-overlay">OUT OF STOCK</div>` : '';

        card.innerHTML = `
            <div class="item-thumb">${thumbHtml}${outOfStockOverlay}</div>
            <div class="item-info">
                <div class="item-price">${zarFmt.format(item.itemPrice)}</div>
                <h3 class="item-name">${item.itemName}</h3>
                <div class="item-bottom">
                    ${locStr ? `<span class="item-location">${locStr}</span>` : ''}
                    ${deliveryLabel ? `<span class="badge ${badgeClass}">${deliveryLabel}</span>` : ''}
                </div>
            </div>`;

        card.addEventListener('click', () => {
            window.location.href = `../ViewItem/ViewItem.php?id=${item.itemID}`;
        });

        grid.appendChild(card);
    });
}

/* ── Filter logic ── */
function applyFilters() {
    const q        = searchInput.value.trim().toLowerCase();
    const province = provinceSelect.value;
    const city     = citySelect.value;
    const suburb   = suburbSelect.value;
    const minP     = parseFloat(minPriceInput.value);
    const maxP     = parseFloat(maxPriceInput.value);
    const delivery = deliverySelect.value;

    renderItems(ALL_ITEMS.filter(item => {
        const price = parseFloat(item.itemPrice);

        // Text search across name and description
        if (q && !item.itemName.toLowerCase().includes(q) &&
                 !(item.itemDescription ?? '').toLowerCase().includes(q)) return false;

        // Location cascade — only filter if a value is selected
        if (province && item.province !== province) return false;
        if (city     && item.city     !== city)     return false;
        if (suburb   && item.suburb   !== suburb)   return false;

        // Price range
        if (!isNaN(minP) && price < minP) return false;
        if (!isNaN(maxP) && price > maxP) return false;

        // Delivery type
        if (
            delivery &&
            item.deliveryType !== delivery &&
            item.deliveryType !== 'A'
        ) {
            return false;
}

        return true;
    }));
}

/* ── Clear all filters ── */
clearBtn.addEventListener('click', () => {
    searchInput.value   = '';
    minPriceInput.value = '';
    maxPriceInput.value = '';
    deliverySelect.value = '';

    provinceSelect.value = '';
    citySelect.innerHTML = '<option value="">All cities</option>';
    citySelect.disabled  = true;
    suburbSelect.innerHTML = '<option value="">All suburbs</option>';
    suburbSelect.disabled  = true;

    applyFilters();
});

/* ── Wire up remaining inputs ── */
[searchInput, minPriceInput, maxPriceInput].forEach(el =>
    el.addEventListener('input', applyFilters));
deliverySelect.addEventListener('change', applyFilters);

/* ── Initial render ── */
renderItems(ALL_ITEMS);
</script>
</body>
</html>
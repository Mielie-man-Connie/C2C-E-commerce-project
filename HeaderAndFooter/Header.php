<?php
/* Header: top-of-page header, user menu and modals */

    // Safe session start (pages may have already called it)
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    // Fetch user row from DB if logged in (graceful if DB not available)
    $headerUser = null;
    if (isset($_SESSION['user_id'])) {
        try {
            if (!isset($pdo)) require_once __DIR__ . '/../data/database.php';
            $stmt = $pdo->prepare(
                'SELECT username, name, surname, imageID FROM accounts WHERE userID = :id LIMIT 1'
            );
            $stmt->execute([':id' => (int) $_SESSION['user_id']]);
            $headerUser = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $headerUser = null;
        }
    }
    
    // Derive display values
    $displayName    = $headerUser['name']    ?? $_SESSION['username'] ?? 'User';
    $displaySurname = $headerUser['surname'] ?? '';
    $displayInitial = strtoupper(mb_substr($displayName, 0, 1));
    $hasImage       = !empty($headerUser['imageID']);
    $imageUrl       = $hasImage ? '../data/getImage.php?id=' . (int) $headerUser['imageID'] : null;
?>

<link rel="stylesheet" href="../data/preset.css">
<script src="../data/translations.js"></script>

<!-- ══════════════════════════════════════════════════════════
     HEADER STYLES  (scoped with .site-header prefix where possible)
     ══════════════════════════════════════════════════════════ -->
<style> 

 
/* ── Base reset for header elements ── */
.site-header *, .site-header *::before, .site-header *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

/* ── Sticky header bar ── */
.site-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 64px;
    z-index: 9000;
    background: linear-gradient(90deg, #094d40, #149079 60%, #0f6b58);
    padding: 0 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 18px rgba(0,0,0,0.22);
    font-family: 'Poppins', 'Segoe UI', sans-serif;
}

/* ── Logo ── */
.header-logo {
    display: flex;
    align-items: center;
    text-decoration: none;
    gap: 0.6rem;
    flex-shrink: 0;
}
.header-logo img {
    height: 40px;
    width: auto;
    display: block;
    /* override any global max-width from preset.css */
    max-width: none !important;
    object-fit: contain;
}
/* Fallback text shown if image fails */
.header-logo .logo-fallback {
    color: #fff;
    font-size: 1.3rem;
    font-weight: 800;
    letter-spacing: -0.04em;
}

/* ── Right side group ── */
.header-right { display: flex; align-items: center; gap: 1.2rem; }

/* ── Language selector ── */
.header-language-selector {
    display: flex;
    align-items: center;
}

.header-language-selector select {
    padding: 0.5rem 0.85rem;
    border-radius: 999px;
    border: 1.5px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    font-family: inherit;
    font-size: 0.85rem;
    font-weight: 500;
    outline: none;
    cursor: pointer;
    transition: all 0.2s ease;
    backdrop-filter: blur(8px);
}

.header-language-selector select:hover {
    border-color: rgba(255, 255, 255, 0.6);
    background: rgba(255, 255, 255, 0.18);
}

.header-language-selector select:focus {
    border-color: rgba(255, 255, 255, 0.8);
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
}

.header-language-selector select option {
    background: #094d40;
    color: #fff;
}

/* ── Profile button ── */
.profile-dropdown { position: relative; }

.profile-btn {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: 2.5px solid rgba(255,255,255,0.55);
    background: linear-gradient(135deg, #215381, #51387a);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ghost-white);
    font-weight: 700;
    font-size: 1rem;
    font-family: inherit;
    overflow: hidden;           /* clips the profile image to the circle */
    transition: transform 0.2s, border-color 0.2s, box-shadow 0.2s;
    flex-shrink: 0;
    padding: 0;
}
.profile-btn:hover {
    border-color: rgba(255,255,255,0.9);
    box-shadow: 0 4px 18px rgba(0,0,0,0.15);
}

/* Profile image inside the button */
.profile-btn img {
    position: absolute;
    inset: 0;
    width: 100% !important;
    height: 100% !important;
    max-width: none !important;
    object-fit: cover;
    display: block;
}

/* ── Dropdown menu ── */
.dropdown-menu {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 12px 50px rgba(0,0,0,0.16), 0 2px 8px rgba(0,0,0,0.08);
    min-width: 210px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px) scale(0.97);
    transform-origin: top right;
    transition: opacity 0.18s ease, transform 0.18s ease, visibility 0.18s;
    overflow: hidden;
    z-index: 9100;
}
.dropdown-menu.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

/* User info block at top of dropdown */
.dropdown-user {
    padding: 1rem 1.1rem 0.75rem;
    border-bottom: 1px solid #f0f0f0;
}
.dropdown-user .du-name {
    font-size: 0.92rem;
    font-weight: 700;
    color: #141924;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dropdown-user .du-handle {
    font-size: 0.78rem;
    color: #9ca3af;
    margin-top: 0.12rem;
}

/* Menu items */
.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.8rem 1.1rem;
    text-decoration: none;
    color: #1e2635;
    font-size: 0.9rem;
    font-family: inherit;
    font-weight: 500;
    background: none;
    border: none;
    width: 100%;
    cursor: pointer;
    transition: background 0.15s;
    text-align: left;
}
.dropdown-item:hover { background: #f7f8fa; }
.dropdown-item svg   { flex-shrink: 0; opacity: 0.65; }

/* Sign-out is red */
.dropdown-item.danger        { color: #e74c3c; border-top: 1px solid #f3f3f3; }
.dropdown-item.danger:hover  { background: #fdf2f2; }
.dropdown-item.danger svg    { opacity: 0.8; }

/* ════════════════════════════════════════════════════════
   MODAL SHARED STYLES
   ════════════════════════════════════════════════════════ */
.hdr-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(10,16,30,0.55);
    backdrop-filter: blur(4px);
    z-index: 9500;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.22s ease, visibility 0.22s;
}
.hdr-modal-overlay.open {
    opacity: 1;
    visibility: visible;
}

.hdr-modal {
    background: #fff;
    border-radius: 24px;
    width: min(92vw, 640px);
    max-height: 80vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 24px 80px rgba(0,0,0,0.22);
    transform: translateY(18px) scale(0.97);
    transition: transform 0.22s ease;
    overflow: hidden;
}
.hdr-modal-overlay.open .hdr-modal {
    transform: translateY(0) scale(1);
}

.hdr-modal-head {
    padding: 1.4rem 1.6rem 1.1rem;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.hdr-modal-head h2 {
    font-size: 1.2rem;
    font-weight: 700;
    color: #141924;
}
.hdr-modal-close {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: #f3f4f6;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: #6b7280;
    transition: background 0.15s, color 0.15s;
    flex-shrink: 0;
}
.hdr-modal-close:hover { background: #e5e7eb; color: #141924; }

.hdr-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.2rem 1.6rem 1.6rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(20,144,121,0.2) transparent;
}

/* ── Listings grid inside modal ── */
.modal-listings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 1rem;
}
.modal-listing-card {
    border: 1px solid #e9ecef;
    border-radius: 14px;
    overflow: hidden;
    cursor: pointer;
    transition: box-shadow 0.2s, transform 0.2s;
    background: #faf8f4;
}
.modal-listing-card:hover {
    box-shadow: 0 6px 22px rgba(12,18,40,0.1);
    transform: translateY(-2px);
}
.modal-listing-thumb {
    width: 100%;
    aspect-ratio: 4/3;
    background: rgba(20,144,121,0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    font-weight: 800;
    color: #149079;
    overflow: hidden;
}
.modal-listing-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    max-width: none !important;
}
.modal-listing-info { padding: 0.6rem 0.7rem 0.7rem; }
.modal-listing-name  { font-size: 0.83rem; font-weight: 700; color: #141924; line-height: 1.3; }
.modal-listing-price { font-size: 0.9rem; font-weight: 800; color: #e74c3c; margin-top: 0.2rem; }

/* ── History list ── */
.modal-history-list { display: flex; flex-direction: column; gap: 0.6rem; }
.modal-history-item {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 0.75rem 0.9rem;
    border: 1px solid #f0f0f0;
    border-radius: 12px;
    cursor: pointer;
    transition: background 0.15s;
}
.modal-history-item:hover { background: #f7f8fa; }
.modal-history-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: rgba(20,144,121,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.modal-history-text .mht-title { font-size: 0.88rem; font-weight: 600; color: #141924; }
.modal-history-text .mht-date  { font-size: 0.75rem; color: #9ca3af; margin-top: 0.1rem; }



/* ── Generic modal empty state ── */
.modal-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: #9ca3af;
}
.modal-empty .me-icon  { font-size: 2.5rem; margin-bottom: 0.5rem; }
.modal-empty .me-title { font-size: 0.98rem; font-weight: 600; color: #6b7280; }
.modal-empty .me-sub   { font-size: 0.83rem; margin-top: 0.25rem; }

/* ── Toast notification ── */
.hdr-toast {
    position: fixed;
    bottom: 1.5rem;
    left: 50%;
    transform: translateX(-50%) translateY(30px);
    background: #141924;
    color: #fff;
    padding: 0.65rem 1.3rem;
    border-radius: 999px;
    font-size: 0.88rem;
    font-weight: 500;
    opacity: 0;
    transition: opacity 0.25s, transform 0.25s;
    z-index: 9999;
    pointer-events: none;
    white-space: nowrap;
}
.hdr-toast.show {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}
</style>

<!-- ══════════════════════════════════════════════════════════
     HEADER BAR
     ══════════════════════════════════════════════════════════ -->
<header class="site-header">

    <!-- Logo -->
    <a href="../Browse/Browse.php" class="header-logo">
        <img src="../img/TradeSA_Logo.png"
             alt="TradeSA"
             onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
        <span class="logo-fallback" style="display:none">TradeSA</span>
    </a>

    <!-- Right side -->
    <div class="header-right">
        <!-- Language selector -->
        <div class="header-language-selector">
            <select id="header-language-select" onchange="hdrChangeLanguage(this.value)">
                <option value="en">English</option>
                <option value="af">Afrikaans</option>
                <option value="zu">Zulu</option>
                <option value="xh">Xhosa</option>
                <option value="nso">Northern Sotho</option>
            </select>
        </div>

        <div class="profile-dropdown" id="profileDropdown">

            <!-- Profile button -->
            <button class="profile-btn"
                    id="profileBtn"
                    onclick="hdrToggleDropdown(event)"
                    aria-label="Profile menu"
                    aria-expanded="false"
                    aria-haspopup="true">
                <?php if ($imageUrl): ?>
                    <img src="<?= htmlspecialchars($imageUrl) ?>"
                         alt="Profile"
                         onerror="this.style.display='none';this.parentElement.querySelector('.profile-initial').style.display='flex'">
                    <span class="profile-initial" style="display:none"><?= $displayInitial ?></span>
                <?php else: ?>
                    <span class="profile-initial"><?= $displayInitial ?></span>
                <?php endif; ?>
            </button>

            <!-- Dropdown -->
            <div class="dropdown-menu" id="dropdownMenu" role="menu">

                <!-- User info -->
                <div class="dropdown-user">
                    <div class="du-name">
                        <?= htmlspecialchars(trim($displayName . ' ' . $displaySurname)) ?>
                    </div>
                    <div class="du-handle">
                        @<?= htmlspecialchars($_SESSION['username'] ?? 'user') ?>
                    </div>
                </div>

                <!-- Settings -->
                <a href="../Settings/Settings.php" class="dropdown-item" role="menuitem">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Settings
                </a>

                <!-- My Listings -->
                <a href="../History/History.php?tab=listings" class="dropdown-item" role="menuitem">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    My Listings
                </a>

                <!-- History -->
                <a href="../History/History.php?tab=orders" class="dropdown-item" role="menuitem">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    History
                </a>

                <!-- Report Issue -->
                <a href="../CreateReport/CreateReport.php" class="dropdown-item" role="menuitem" style="text-decoration: none; color: inherit;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    Report an Issue
                </a>

                <!-- Sign Out -->
                <a href="../data/logout.php" class="dropdown-item danger" role="menuitem">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Sign Out
                </a>
            </div>
        </div>
    </div>
</header>



<!-- ── Toast notification ── -->
<div class="hdr-toast" id="hdrToast"></div>

<!-- ══════════════════════════════════════════════════════════
     HEADER JAVASCRIPT
     ══════════════════════════════════════════════════════════ -->
<script>
(function () {
    /* ── ZAR formatter ── */
    const zarFmt = new Intl.NumberFormat('en-ZA', {
        style: 'currency', currency: 'ZAR', minimumFractionDigits: 2
    });

    /* ── Language selector initialization ── */
    window.hdrChangeLanguage = function (lang) {
        // Use the global page language function to translate everything
        window.applyPageLanguage(lang);
    };

    /* ── Dropdown toggle ── */
    window.hdrToggleDropdown = function (e) {
        e.stopPropagation();
        const menu = document.getElementById('dropdownMenu');
        const btn  = document.getElementById('profileBtn');
        const open = menu.classList.toggle('active');
        btn.setAttribute('aria-expanded', open);
    };

    // Close dropdown on outside click
    document.addEventListener('click', function (e) {
        const pd = document.getElementById('profileDropdown');
        if (pd && !pd.contains(e.target)) {
            document.getElementById('dropdownMenu').classList.remove('active');
            document.getElementById('profileBtn').setAttribute('aria-expanded', 'false');
        }
    });

    /* ── Modal helpers ── */
    window.hdrOpenModal = function (id) {
        document.getElementById('dropdownMenu').classList.remove('active');
        document.getElementById(id).classList.add('open');
        document.body.style.overflow = 'hidden';
    };
    window.hdrCloseModal = function (id) {
        document.getElementById(id).classList.remove('open');
        document.body.style.overflow = '';
    };
    window.hdrOverlayClose = function (e, id) {
        if (e.target === document.getElementById(id)) hdrCloseModal(id);
    };

    // Close on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.hdr-modal-overlay.open').forEach(el => {
                hdrCloseModal(el.id);
            });
        }
    });

    /* ── Toast ── */
    function showToast(msg, duration = 3000) {
        const t = document.getElementById('hdrToast');
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), duration);
    }

    /* ── Fetch My Listings via AJAX ── */
    let listingsFetched = false;
    function fetchListings() {
        if (listingsFetched) return;
        listingsFetched = true;

        fetch('../HeaderAndFooter/getUserListings.php')
            .then(r => r.json())
            .then(data => {
                const el = document.getElementById('listingsModalContent');
                if (!data.length) {
                    el.innerHTML = `
                        <div class="modal-empty">
                            <div class="me-icon">📦</div>
                            <div class="me-title">No listings yet</div>
                            <div class="me-sub">Items you list for sale will appear here.</div>
                        </div>`;
                    return;
                }
                const grid = document.createElement('div');
                grid.className = 'modal-listings-grid';
                data.forEach(item => {
                    const card = document.createElement('div');
                    card.className = 'modal-listing-card';
                    const firstId = item.firstImageID;
                    const thumb   = firstId
                        ? `<img src="../data/getImage.php?id=${firstId}" alt="${item.itemName}" loading="lazy">`
                        : item.itemName.charAt(0).toUpperCase();
                    card.innerHTML = `
                        <div class="modal-listing-thumb">${thumb}</div>
                        <div class="modal-listing-info">
                            <div class="modal-listing-name">${item.itemName}</div>
                            <div class="modal-listing-price">${zarFmt.format(item.itemPrice)}</div>
                        </div>`;
                    card.addEventListener('click', () => {
                        window.location.href = `../ViewItem/ViewItem.php?id=${item.itemID}`;
                    });
                    grid.appendChild(card);
                });
                el.innerHTML = '';
                el.appendChild(grid);
            })
            .catch(() => {
                document.getElementById('listingsModalContent').innerHTML = `
                    <div class="modal-empty">
                        <div class="me-icon">⚠️</div>
                        <div class="me-title">Could not load listings</div>
                        <div class="me-sub">Please try again later.</div>
                    </div>`;
            });
    }

    /* ── Fetch History via AJAX ── */
    let historyFetched = false;
    function fetchHistory() {
        if (historyFetched) return;
        historyFetched = true;

        fetch('../HeaderAndFooter/getUserHistory.php')
            .then(r => r.json())
            .then(data => {
                const el = document.getElementById('historyModalContent');
                if (!data.length) {
                    el.innerHTML = `
                        <div class="modal-empty">
                            <div class="me-icon">🕐</div>
                            <div class="me-title">No history yet</div>
                            <div class="me-sub">Items you've viewed or purchased will appear here.</div>
                        </div>`;
                    return;
                }
                const list = document.createElement('div');
                list.className = 'modal-history-list';
                data.forEach(entry => {
                    const row = document.createElement('div');
                    row.className = 'modal-history-item';
                    const icon = entry.type === 'purchase' ? '🛒' :
                                 entry.type === 'view'     ? '👁️' : '📋';
                    row.innerHTML = `
                        <div class="modal-history-icon">${icon}</div>
                        <div class="modal-history-text">
                            <div class="mht-title">${entry.itemName}</div>
                            <div class="mht-date">${entry.dateLabel}</div>
                        </div>`;
                    if (entry.itemID) {
                        row.style.cursor = 'pointer';
                        row.addEventListener('click', () => {
                            window.location.href = `../ViewItem/ViewItem.php?id=${entry.itemID}`;
                        });
                    }
                    list.appendChild(row);
                });
                el.innerHTML = '';
                el.appendChild(list);
            })
            .catch(() => {
                document.getElementById('historyModalContent').innerHTML = `
                    <div class="modal-empty">
                        <div class="me-icon">⚠️</div>
                        <div class="me-title">Could not load history</div>
                        <div class="me-sub">Please try again later.</div>
                    </div>`;
            });
    }


})();

/* ── Global language initialization ── */
document.addEventListener('DOMContentLoaded', function() {
    // Set the language selector dropdown to match the saved language
    const headerLangSelect = document.getElementById('header-language-select');
    if (headerLangSelect && window.getCurrentLanguage) {
        const savedLang = window.getCurrentLanguage();
        headerLangSelect.value = savedLang;
        // Ensure the page is translated with the saved language
        if (window.applyPageLanguage) {
            window.applyPageLanguage(savedLang);
        }
    }
});
</script>
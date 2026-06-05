<?php /* Footer.php — include at the bottom of every page, before </body> */ ?>
<style>
.site-footer {
    background: linear-gradient(135deg, #0f2e28, #094d40);
    color: rgba(255,255,255,0.55);
    padding: 2.4rem 2rem 1.2rem;
    font-family: 'Poppins', 'Segoe UI', sans-serif;
    font-size: 0.875rem;
    flex-shrink: 0;
}
.footer-content {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1.6fr 1fr 1fr;
    gap: 2rem 3rem;
}
.footer-brand .fb-logo {
    font-size: 1.25rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.03em;
    margin-bottom: 0.5rem;
}
.footer-brand p {
    color: rgba(255,255,255,0.5);
    line-height: 1.6;
    max-width: 260px;
}
.footer-col h4 {
    color: rgba(255,255,255,0.85);
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 0.9rem;
}
.footer-col a {
    display: block;
    color: rgba(255,255,255,0.5);
    text-decoration: none;
    padding: 0.22rem 0;
    font-size: 0.875rem;
    transition: color 0.18s;
}
.footer-col a:hover { color: #fff; }

.footer-bottom {
    max-width: 1200px;
    margin: 1.8rem auto 0;
    padding-top: 1.2rem;
    border-top: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    font-size: 0.8rem;
    color: rgba(255,255,255,0.35);
}
.footer-bottom a { color: rgba(255,255,255,0.45); text-decoration: none; }
.footer-bottom a:hover { color: #fff; }

@media (max-width: 640px) {
    .footer-content { grid-template-columns: 1fr 1fr; }
    .footer-brand   { grid-column: 1 / -1; }
}
@media (max-width: 400px) {
    .footer-content { grid-template-columns: 1fr; }
}
</style>

<footer class="site-footer">
    <div class="footer-content">
        <div class="footer-brand">
            <div class="fb-logo">TradeSA</div>
            <p>South Africa's trusted secondhand marketplace. Buy and sell safely with escrow protection.</p>
        </div>
        <div class="footer-col">
            <h4>Marketplace</h4>
            <a href="../Browse/Browse.php">Browse items</a>
            <a href="../NewListing/NewListing.php">Sell an item</a>
        </div>
        <div class="footer-col">
            <h4>Account</h4>
            <a href="../Settings/Settings.php">Settings</a>
            <a href="../data/logout.php">Sign out</a>
        </div>
    </div>
    <div class="footer-bottom">
        <span>&copy; <?= date('Y') ?> TradeSA. All rights reserved.</span>
        <span>Built for South Africa 🇿🇦</span>
    </div>
</footer>
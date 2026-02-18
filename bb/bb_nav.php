<?php
/**
 * Shared icon navigation bar for all /bb/ admin pages.
 * Include this file after <body> on every admin page.
 *
 * Dependencies: bb_nav.css (link it in the page <head> or inline).
 * Badge refresh: AJAX → bb_nav_badge.php every 60s.
 */

// Don't show nav on index.php (it has its own icon grid)
$_bb_nav_self = basename($_SERVER['PHP_SELF']);
if ($_bb_nav_self === 'index.php')
    return;

// Determine active page for highlighting
$_bb_nav_active = $_bb_nav_self;

// SVG home icon (no PNG available)
$_bb_home_svg = '<svg class="bb-icon-nav__home-icon" viewBox="0 0 24 24" fill="none" stroke="#3a4a5c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v9a1 1 0 001 1h3v-5h6v5h3a1 1 0 001-1v-9"/></svg>';

$_bb_nav_items = [
    ['label' => 'На главную', 'href' => '/bb/index.php', 'icon' => $_bb_home_svg, 'page' => 'index.php'],
    ['label' => 'Товары', 'href' => '/bb/kr_baza_new.php', 'icon' => '<img src="/public/img/topmenu/1_mm.png">', 'page' => 'kr_baza_new.php'],
    ['label' => 'Брони', 'href' => '/bb/rent_orders.php', 'icon' => '<img src="/bb/assets/images/png/menu-broni.png">', 'page' => 'rent_orders.php', 'badge' => true],
    ['label' => 'Сделки', 'href' => '/bb/rda.php', 'icon' => '<img src="/bb/assets/images/png/menu-deals.png">', 'page' => 'rda.php'],
    ['label' => 'Новый договор', 'href' => '/bb/dogovor_new.php', 'icon' => '<img src="/bb/assets/images/png/menu-clients.png">', 'page' => 'dogovor_new.php'],
    ['label' => 'Курьер', 'href' => '/bb/cur_page2.php', 'icon' => '<img src="/bb/assets/images/png/menu-cur.png">', 'page' => 'cur_page2.php'],
    ['label' => 'Карнавал', 'href' => '/bb/kb.php', 'icon' => '<img src="/public/img/topmenu/mask.png">', 'page' => 'kb.php'],
];
?>
<link rel="stylesheet" href="/bb/bb_nav.css?v=1">
<nav class="bb-icon-nav">
    <?php foreach ($_bb_nav_items as $item): ?>
        <a class="bb-icon-nav__link<?= ($item['page'] === $_bb_nav_active ? ' bb-icon-nav__link--active' : '') ?>"
            href="<?= $item['href'] ?>">
            <?= $item['icon'] ?>
            <?= $item['label'] ?>
            <?php if (!empty($item['badge'])): ?>
                <span class="bb-icon-nav__badge" id="bb-nav-badge"></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<script>
    (function () {
        function refreshBadge() {
            fetch('/bb/bb_nav_badge.php')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var badge = document.getElementById('bb-nav-badge');
                    if (!badge) return;
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.classList.add('bb-icon-nav__badge--visible');
                    } else {
                        badge.classList.remove('bb-icon-nav__badge--visible');
                    }
                })
                .catch(function () { });
        }
        refreshBadge();
        setInterval(refreshBadge, 60000);
    })();
</script>
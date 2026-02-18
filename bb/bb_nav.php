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
    ['label' => 'Обработка', 'href' => '/bb/obrabotka.php', 'icon' => '<svg class="bb-icon-nav__home-icon" viewBox="0 0 24 24" fill="none" stroke="#3a4a5c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>', 'page' => 'obrabotka.php'],
    [
        'label' => 'Архив',
        'type' => 'dropdown',
        'icon' => '<svg class="bb-icon-nav__home-icon" viewBox="0 0 24 24" fill="none" stroke="#3a4a5c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>',
        'items' => [
            ['label' => 'Завершенные сделки', 'href' => '/bb/deals_arch.php'],
            ['label' => 'Удаленные брони', 'href' => '/bb/rent_orders_arch.php'],
        ],
        'pages' => ['deals_arch.php', 'rent_orders_arch.php']
    ],
];
?>
<link rel="stylesheet" href="/bb/bb_nav.css?v=2">
<nav class="bb-icon-nav">
    <?php foreach ($_bb_nav_items as $item): ?>
        <?php if (!empty($item['type']) && $item['type'] === 'dropdown'): ?>
            <div class="bb-icon-nav__dropdown">
                <button
                    class="bb-icon-nav__link bb-icon-nav__dropdown-toggle<?= (in_array($_bb_nav_active, $item['pages'] ?? []) ? ' bb-icon-nav__link--active' : '') ?>"
                    type="button">
                    <?= $item['icon'] ?>
                    <?= $item['label'] ?>
                    <svg class="bb-icon-nav__caret" viewBox="0 0 10 6" width="10" height="6">
                        <path d="M1 1l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                </button>
                <div class="bb-icon-nav__dropdown-menu">
                    <?php foreach ($item['items'] as $sub): ?>
                        <a class="bb-icon-nav__dropdown-item" href="<?= $sub['href'] ?>"><?= $sub['label'] ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <a class="bb-icon-nav__link<?= ($item['page'] === $_bb_nav_active ? ' bb-icon-nav__link--active' : '') ?>"
                href="<?= $item['href'] ?>">
                <?= $item['icon'] ?>
                <?= $item['label'] ?>
                <?php if (!empty($item['badge'])): ?>
                    <span class="bb-icon-nav__badge" id="bb-nav-badge"></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
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
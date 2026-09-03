<?php

use bb\classes\PageVisitCatalog;
use bb\models\User;

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/classes/Permission.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/classes/PageVisitCatalog.php';

if (!User::getCurrentUser()->isDima()) {
    die('
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml">
    <head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><title>Нет доступа</title></head>
    <body>Эта страница доступна только владельцу.</body></html>');
}

$mysqli = \bb\Db::getInstance()->getConnection();

$i_from_date = (isset($_GET['i_from_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['i_from_date']))
    ? $_GET['i_from_date']
    : date('Y-m-d', strtotime('-30 days'));
$i_to_date = (isset($_GET['i_to_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['i_to_date']))
    ? $_GET['i_to_date']
    : date('Y-m-d');

$from_dt = $i_from_date . ' 00:00:00';
$to_dt   = $i_to_date . ' 23:59:59';

echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>BB: Использование CRM</title>
<link href="stile.css" rel="stylesheet" type="text/css" />
</head>
<body>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
</div>

<form name="srch_form" method="get" id="srch_form" action="page_track.php">
	За период:
		c <input type="date" name="i_from_date" value="' . htmlspecialchars($i_from_date) . '" />
		по <input type="date" name="i_to_date" value="' . htmlspecialchars($i_to_date) . '" />
		<input type="submit" value="показать" /><br />
</form>

<h3>По сотрудникам</h3>
<table border="1" cellspacing="0">
<tr><th>сотрудник</th><th>заходов</th><th>уникальных страниц</th><th>последний визит</th></tr>
';

$q_users = "
    SELECT bpv.user_id, COUNT(*) AS visits, COUNT(DISTINCT bpv.page) AS distinct_pages, MAX(bpv.visited_at) AS last_visit
    FROM bb_page_visits bpv
    WHERE bpv.visited_at BETWEEN '{$from_dt}' AND '{$to_dt}'
    GROUP BY bpv.user_id
    ORDER BY visits DESC
";
$result_users = $mysqli->query($q_users);
if (!$result_users) {
    die('Сбой при доступе к базе данных: ' . $q_users . ' (' . $mysqli->error . ')');
}
while ($row = $result_users->fetch_assoc()) {
    $u = User::getUserById($row['user_id']);
    echo '
    <tr>
        <td>' . htmlspecialchars($u ? $u->getShortName() : ('#' . $row['user_id'])) . '</td>
        <td>' . (int) $row['visits'] . '</td>
        <td>' . (int) $row['distinct_pages'] . '</td>
        <td>' . htmlspecialchars($row['last_visit']) . '</td>
    </tr>';
}

echo '
</table>

<h3>По страницам (сначала неиспользуемые)</h3>
<table border="1" cellspacing="0">
<tr><th>страница</th><th>заходов</th><th>уникальных сотрудников</th><th>последний визит</th></tr>
';

$q_pages = "
    SELECT bpv.page, COUNT(*) AS visits, COUNT(DISTINCT bpv.user_id) AS distinct_users, MAX(bpv.visited_at) AS last_visit
    FROM bb_page_visits bpv
    WHERE bpv.visited_at BETWEEN '{$from_dt}' AND '{$to_dt}'
    GROUP BY bpv.page
";
$result_pages = $mysqli->query($q_pages);
if (!$result_pages) {
    die('Сбой при доступе к базе данных: ' . $q_pages . ' (' . $mysqli->error . ')');
}
$stats_by_page = [];
while ($row = $result_pages->fetch_assoc()) {
    $stats_by_page[$row['page']] = $row;
}

$rows = [];
foreach (PageVisitCatalog::listTrackablePages() as $page) {
    $stat = $stats_by_page[$page] ?? null;
    $rows[] = [
        'page'           => $page,
        'visits'         => $stat ? (int) $stat['visits'] : 0,
        'distinct_users' => $stat ? (int) $stat['distinct_users'] : 0,
        'last_visit'     => $stat ? $stat['last_visit'] : null,
    ];
}
usort($rows, function ($a, $b) { return $a['visits'] <=> $b['visits']; });

foreach ($rows as $row) {
    echo '
    <tr>
        <td>' . htmlspecialchars($row['page']) . '</td>
        <td>' . $row['visits'] . '</td>
        <td>' . $row['distinct_users'] . '</td>
        <td>' . htmlspecialchars($row['last_visit'] ?? '—') . '</td>
    </tr>';
}

echo '
</table>
</body></html>
';

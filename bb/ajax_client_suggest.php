<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');

header('Content-Type: application/json; charset=utf-8');

$in_level = array(0, 5, 7);
isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    echo json_encode(['candidates' => []]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['candidates' => []]);
    exit;
}

$mysqli = \bb\Db::getInstance()->getConnection();

$family  = trim($_POST['family']  ?? '');
$name    = trim($_POST['name']    ?? '');
$otch    = trim($_POST['otch']    ?? '');
$phone   = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
$pas_ln  = mb_strtoupper(trim($_POST['pas_ln'] ?? ''));
$pas_n   = mb_strtoupper(trim($_POST['pas_n']  ?? ''));

$pas_ln_len = mb_strlen($pas_ln);
$pas_n_len  = mb_strlen($pas_n);

if (mb_strlen($family) < 3 && strlen($phone) < 7 && $pas_ln_len < 6 && $pas_n_len < 4) {
    echo json_encode(['candidates' => []]);
    exit;
}

$candidates = [];

// pas_ln prefix search (6+ chars) — covers both partial and full match
if ($pas_ln_len >= 6) {
    $pl = $mysqli->real_escape_string($pas_ln);
    $res = $mysqli->query("SELECT * FROM clients WHERE UPPER(pas_ln) LIKE '$pl%' LIMIT 15");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $candidates[$row['client_id']] = $row;
        }
    }
}

// pas_n prefix search (4+ chars)
if ($pas_n_len >= 4) {
    $pn = $mysqli->real_escape_string($pas_n);
    $res = $mysqli->query("SELECT * FROM clients WHERE UPPER(pas_n) LIKE '$pn%' LIMIT 15");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (!isset($candidates[$row['client_id']])) {
                $candidates[$row['client_id']] = $row;
            }
        }
    }
}

// Phone match in phone_1 or phone_2
if (strlen($phone) >= 7) {
    $ph = $mysqli->real_escape_string($phone);
    $clean = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(%s, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '')";
    $c1 = sprintf($clean, 'phone_1');
    $c2 = sprintf($clean, 'phone_2');
    $res = $mysqli->query("SELECT * FROM clients WHERE $c1 LIKE '%$ph%' OR $c2 LIKE '%$ph%' LIMIT 20");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (!isset($candidates[$row['client_id']])) {
                $candidates[$row['client_id']] = $row;
            }
        }
    }
}

// Family LIKE (+ optional name prefix)
if (mb_strlen($family) >= 3) {
    $fam = $mysqli->real_escape_string($family);
    $where = "UPPER(family) LIKE UPPER('$fam%')";
    if (mb_strlen($name) >= 2) {
        $nm = $mysqli->real_escape_string($name);
        $where .= " AND UPPER(name) LIKE UPPER('$nm%')";
    }
    $res = $mysqli->query("SELECT * FROM clients WHERE $where LIMIT 30");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (!isset($candidates[$row['client_id']])) {
                $candidates[$row['client_id']] = $row;
            }
        }
    }
}

if (empty($candidates)) {
    echo json_encode(['candidates' => []]);
    exit;
}

// Score each candidate
$scored = [];
foreach ($candidates as $c) {
    $score = 0;

    // pas_ln: exact full match (+80), prefix partial match (+40)
    if ($pas_ln_len >= 6 && !empty($c['pas_ln'])) {
        $c_ln = mb_strtoupper(trim($c['pas_ln']));
        if ($pas_ln_len >= 14 && $c_ln === $pas_ln) {
            $score += 80;
        } elseif (mb_strpos($c_ln, $pas_ln) === 0) {
            $score += 40;
        }
    }

    // pas_n: exact match (+30), prefix match (+15)
    if ($pas_n_len >= 4 && !empty($c['pas_n'])) {
        $c_pn = mb_strtoupper(trim($c['pas_n']));
        if ($c_pn === $pas_n) {
            $score += 30;
        } elseif (mb_strpos($c_pn, $pas_n) === 0) {
            $score += 15;
        }
    }

    // Phone match (+60)
    if (strlen($phone) >= 7) {
        $cph1 = preg_replace('/[^0-9]/', '', $c['phone_1'] ?? '');
        $cph2 = preg_replace('/[^0-9]/', '', $c['phone_2'] ?? '');
        if ($cph1 === $phone || $cph2 === $phone) {
            $score += 60;
        } elseif (strpos($cph1, $phone) !== false || strpos($cph2, $phone) !== false) {
            $score += 30;
        }
    }

    // Family (+20 exact, +10 prefix)
    if (mb_strlen($family) >= 3) {
        $c_fam = mb_strtoupper(trim($c['family']));
        if ($c_fam === mb_strtoupper($family)) {
            $score += 20;
        } elseif (mb_strpos($c_fam, mb_strtoupper($family)) === 0) {
            $score += 10;
        }
    }

    // Name (+15 exact)
    if (mb_strlen($name) >= 2 && mb_strtoupper(trim($c['name'])) === mb_strtoupper($name)) {
        $score += 15;
    }

    // Otch (+10 exact)
    if (mb_strlen($otch) >= 2 && mb_strtoupper(trim($c['otch'])) === mb_strtoupper($otch)) {
        $score += 10;
    }

    $pas_date_fmt = '';
    if (!empty($c['pas_date']) && $c['pas_date'] > 0) {
        $pas_date_fmt = date('Y-m-d', (int)$c['pas_date']);
    }

    $scored[] = [
        'client_id'    => (int)$c['client_id'],
        'family'       => (string)$c['family'],
        'name'         => (string)$c['name'],
        'otch'         => (string)$c['otch'],
        'phone_1'      => (string)$c['phone_1'],
        'phone_2'      => (string)$c['phone_2'],
        'pas_n'        => (string)$c['pas_n'],
        'pas_ln'       => (string)$c['pas_ln'],
        'pas_date_fmt' => $pas_date_fmt,
        'pas_who'      => (string)$c['pas_who'],
        'city'         => (string)$c['city'],
        'str'          => (string)$c['str'],
        'dom'          => (string)$c['dom'],
        'kv'           => (string)$c['kv'],
        'reg_city'     => (string)$c['reg_city'],
        'reg_str'      => (string)$c['reg_str'],
        'reg_dom'      => (string)$c['reg_dom'],
        'reg_kv'       => (string)$c['reg_kv'],
        'info'         => (string)$c['info'],
        'arch_n'       => (int)($c['arch_n'] ?? 0),
        'arch_amount'  => $c['arch_amount'],
        'score'        => $score,
    ];
}

usort($scored, function ($a, $b) { return $b['score'] - $a['score']; });
$scored = array_slice($scored, 0, 7);

echo json_encode(['candidates' => $scored], JSON_UNESCAPED_UNICODE);

<?php
session_start();
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
$mysqli = \bb\Db::getInstance()->getConnection();

// Проверка авторизации
$in_level = array(0, 5, 7);
isset($_SESSION['svoi']) ? $_SESSION['svoi'] = $_SESSION['svoi'] : $_SESSION['svoi'] = 0;
if ($_SESSION['svoi'] != 8941 || !(in_array($_SESSION['level'], $in_level))) {
    die('Доступ запрещен.');
}

// Обработка AJAX запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'find_duplicates') {
        $groups_high = [];
        $groups_medium = [];
        $groups_low = [];
        
        // Helper function for PHP scoring
        if (!function_exists('calc_confidence')) {
            function clean_phone($phone) {
                return preg_replace('/[^0-9]/', '', $phone);
            }
            function calc_confidence($c1, $c2) {
                $score = 0;
                // pas_ln
                if (!empty($c1['pas_ln']) && $c1['pas_ln'] === $c2['pas_ln'] && mb_strlen($c1['pas_ln']) >= 14) $score += 50;
                
                // phone
                $p1_1 = clean_phone($c1['phone_1']); $p1_2 = clean_phone($c1['phone_2']);
                $p2_1 = clean_phone($c2['phone_1']); $p2_2 = clean_phone($c2['phone_2']);
                $phone_match = false;
                if ($p1_1 && ($p1_1 === $p2_1 || $p1_1 === $p2_2)) $phone_match = true;
                if ($p1_2 && ($p1_2 === $p2_1 || $p1_2 === $p2_2)) $phone_match = true;
                if ($phone_match) $score += 40;
                
                // Name
                $f1 = mb_strtoupper(trim($c1['family'])); $n1 = mb_strtoupper(trim($c1['name'])); $o1 = mb_strtoupper(trim($c1['otch']));
                $f2 = mb_strtoupper(trim($c2['family'])); $n2 = mb_strtoupper(trim($c2['name'])); $o2 = mb_strtoupper(trim($c2['otch']));
                $f_match = ($f1 && $f1 === $f2); $n_match = ($n1 && $n1 === $n2); $o_match = ($o1 && $o1 === $o2);
                if ($f_match && $n_match && $o_match) $score += 40;
                elseif ($f_match && $n_match) $score += 20;
                
                // Additional
                if (!empty($c1['pas_n']) && $c1['pas_n'] === $c2['pas_n']) $score += 20;
                $str1 = mb_strtoupper(trim($c1['str'])); $dom1 = mb_strtoupper(trim($c1['dom']));
                $str2 = mb_strtoupper(trim($c2['str'])); $dom2 = mb_strtoupper(trim($c2['dom']));
                if ($str1 && $str1 === $str2 && $dom1 === $dom2) $score += 10;
                if (!empty($c1['pas_date']) && $c1['pas_date'] != 0 && $c1['pas_date'] == $c2['pas_date']) $score += 10;
                
                return $score;
            }
        }
        
        $process_group = function($title, $clients) use (&$groups_high, &$groups_medium, &$groups_low) {
            if (count($clients) < 2) return;
            $group_score = 999;
            // Calculate min pairwise score relative to the first element
            for ($i = 1; $i < count($clients); $i++) {
                $s = calc_confidence($clients[0], $clients[$i]);
                if ($s < $group_score) $group_score = $s;
            }
            if ($group_score >= 80) $conf = 'high';
            elseif ($group_score >= 50) $conf = 'medium';
            else $conf = 'low';
            
            $g = ['title' => $title, 'clients' => array_values($clients), 'confidence' => $conf, 'score' => $group_score];
            if ($conf === 'high') $groups_high[] = $g;
            elseif ($conf === 'medium') $groups_medium[] = $g;
            else $groups_low[] = $g;
        };

        // Graph Algorithm Start
        $mysqli->query("CREATE TEMPORARY TABLE temp_dup_ids (client_id INT PRIMARY KEY)");
        $mysqli->query("INSERT IGNORE INTO temp_dup_ids SELECT client_id FROM clients WHERE pas_ln IN (SELECT pas_ln FROM clients WHERE LENGTH(pas_ln) >= 14 GROUP BY pas_ln HAVING COUNT(*) > 1)");
        $mysqli->query("INSERT IGNORE INTO temp_dup_ids SELECT client_id FROM clients WHERE phone_1 IN (SELECT phone_1 FROM clients WHERE LENGTH(phone_1) >= 7 GROUP BY phone_1 HAVING COUNT(*) > 1)");
        $mysqli->query("INSERT IGNORE INTO temp_dup_ids SELECT client_id FROM clients WHERE phone_2 IN (SELECT phone_2 FROM clients WHERE LENGTH(phone_2) >= 7 GROUP BY phone_2 HAVING COUNT(*) > 1)");
        $mysqli->query("INSERT IGNORE INTO temp_dup_ids SELECT client_id FROM clients c JOIN (SELECT family, name, otch FROM clients WHERE family != '' AND name != '' GROUP BY family, name, otch HAVING COUNT(*) > 1) dup ON c.family = dup.family AND c.name = dup.name AND c.otch = dup.otch");
        
        // Disable address lev distance to keep it fast and only rely on strict name + otch + ln + phone edges for components
        
        $res = $mysqli->query("SELECT * FROM clients WHERE client_id IN (SELECT client_id FROM temp_dup_ids)");
        $clients = [];
        $map_ln = []; $map_phone = []; $map_name = [];
        
        while ($c = $res->fetch_assoc()) {
            $clients[$c['client_id']] = $c;
            
            if (mb_strlen($c['pas_ln']) >= 14) $map_ln[$c['pas_ln']][] = $c['client_id'];
            
            $p1 = clean_phone($c['phone_1']);
            if (strlen($p1) >= 7) $map_phone[substr($p1, -7)][] = $c['client_id'];
            
            $p2 = clean_phone($c['phone_2']);
            if (strlen($p2) >= 7) $map_phone[substr($p2, -7)][] = $c['client_id'];
            
            $f = mb_strtolower(trim($c['family']));
            $n = mb_strtolower(trim($c['name']));
            $o = mb_strtolower(trim($c['otch']));
            if ($f && $n && $o) $map_name[$f.'|'.$n.'|'.$o][] = $c['client_id'];
        }
        
        $parent = [];
        foreach ($clients as $cid => $c) $parent[$cid] = $cid;
        
        function uf_find(&$parent, $i) {
            if ($parent[$i] == $i) return $i;
            return $parent[$i] = uf_find($parent, $parent[$i]);
        }
        function uf_union(&$parent, $i, $j) {
            $root_i = uf_find($parent, $i);
            $root_j = uf_find($parent, $j);
            if ($root_i != $root_j) $parent[$root_i] = $root_j;
        }
        
        foreach ($map_ln as $ids) { if (count($ids) > 1) for ($i=1; $i<count($ids); $i++) uf_union($parent, $ids[0], $ids[$i]); }
        foreach ($map_phone as $ids) { if (count($ids) > 1) for ($i=1; $i<count($ids); $i++) uf_union($parent, $ids[0], $ids[$i]); }
        foreach ($map_name as $ids) { if (count($ids) > 1) for ($i=1; $i<count($ids); $i++) uf_union($parent, $ids[0], $ids[$i]); }
        
        $clusters = [];
        foreach ($clients as $cid => $c) {
            $root = uf_find($parent, $cid);
            $clusters[$root][] = $c;
        }
        
        foreach ($clusters as $root_id => $group_clients) {
            if (count($group_clients) > 1) {
                // Name the cluster dynamically
                $first = $group_clients[0];
                $title = "Умная группа: {$first['family']} {$first['name']}";
                $process_group($title, $group_clients);
            }
        }
        
        // Sort groups inside by score DESC (so 100 confidence is first)
        usort($groups_high, function($a, $b) { return $b['score'] <=> $a['score']; });
        usort($groups_medium, function($a, $b) { return $b['score'] <=> $a['score']; });
        usort($groups_low, function($a, $b) { return $b['score'] <=> $a['score']; });
        
        $all_groups = array_merge($groups_high, $groups_medium, $groups_low);
        // Take top 50 to avoid crashing browser if there are thousands
        $all_groups = array_slice($all_groups, 0, 50);

        echo json_encode(['status' => 'ok', 'groups' => $all_groups]);
        exit;
    }

    if ($action === 'merge') {
        $master_id = (int)$_POST['master_id'];
        $slave_ids = $_POST['slave_ids'] ?? [];

        if (empty($slave_ids)) {
            die(json_encode(['error' => 'Нет slave_ids']));
        }

        $mysqli->begin_transaction();
        try {
            // Get master
            $res_m = $mysqli->query("SELECT * FROM clients WHERE client_id = $master_id");
            if (!$res_m || $res_m->num_rows === 0) throw new Exception("Мастер не найден");
            $master = $res_m->fetch_assoc();

            foreach ($slave_ids as $slave_id) {
                $slave_id = (int)$slave_id;
                if ($slave_id === $master_id) continue;

                $res_s = $mysqli->query("SELECT * FROM clients WHERE client_id = $slave_id");
                if (!$res_s || $res_s->num_rows === 0) continue;
                $slave = $res_s->fetch_assoc();

                // 1. Update all related tables
                $tables = ['rent_deals_act', 'rent_deals_arch', 'deals', 'rent_orders', 'rent_orders_arch'];
                foreach ($tables as $t) {
                    $mysqli->query("UPDATE $t SET client_id = $master_id WHERE client_id = $slave_id");
                }
                
                // karn_brons uses cl_id
                $mysqli->query("UPDATE karn_brons SET cl_id = $master_id WHERE cl_id = $slave_id");
                $mysqli->query("UPDATE karn_brons_arch SET cl_id = $master_id WHERE cl_id = $slave_id");

                // 2. Aggregate finances
                $mysqli->query("UPDATE clients SET arch_amount = arch_amount + " . (float)$slave['arch_amount'] . ", arch_n = arch_n + " . (int)$slave['arch_n'] . " WHERE client_id = $master_id");

                // 3. Append missing phones or info
                $new_info = $master['info'];
                $added = false;
                if (!empty($slave['phone_1']) && $slave['phone_1'] !== $master['phone_1'] && $slave['phone_1'] !== $master['phone_2']) {
                    $new_info .= ($new_info ? " | " : "") . "Тел дубля: " . $slave['phone_1'];
                    $added = true;
                }
                if (!empty($slave['phone_2']) && $slave['phone_2'] !== $master['phone_1'] && $slave['phone_2'] !== $master['phone_2']) {
                    $new_info .= ($new_info ? " | " : "") . "Тел дубля: " . $slave['phone_2'];
                    $added = true;
                }
                if (!empty(trim($slave['info']))) {
                    $new_info .= ($new_info ? " | " : "") . "Инфо дубля: " . trim($slave['info']);
                    $added = true;
                }

                if ($added) {
                    $stmt = $mysqli->prepare("UPDATE clients SET info = ? WHERE client_id = ?");
                    $stmt->bind_param("si", $new_info, $master_id);
                    $stmt->execute();
                    $stmt->close();
                    $master['info'] = $new_info; // update local copy for next slaves
                }
                // If master has empty phone slots, fill them from slave
                if (empty($master['phone_1']) && !empty($slave['phone_1'])) {
                    $stmt = $mysqli->prepare("UPDATE clients SET phone_1 = ? WHERE client_id = ?");
                    $stmt->bind_param("si", $slave['phone_1'], $master_id);
                    $stmt->execute(); $stmt->close();
                    $master['phone_1'] = $slave['phone_1'];
                } elseif (empty($master['phone_2']) && !empty($slave['phone_2'])) {
                    $stmt = $mysqli->prepare("UPDATE clients SET phone_2 = ? WHERE client_id = ?");
                    $stmt->bind_param("si", $slave['phone_2'], $master_id);
                    $stmt->execute(); $stmt->close();
                    $master['phone_2'] = $slave['phone_2'];
                }

                // 4. Archive the slave
                $ins = $mysqli->prepare("INSERT INTO clients_arch 
                    (arch_time, arch_who_id, main_cl_id, client_id, family, name, otch, city, str, dom, kv, pas_n, pas_ln, pas_date, pas_who, reg_city, reg_str, reg_dom, reg_kv, phone_1, phone_2, info, status, cr_time, arch_n, arch_amount, arch_l_date, cr_who, source)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $arch_time = time();
                $user_id = $_SESSION['user_id'];
                
                // bind_param types (29 params, matching clients_arch column order):
                // iiii = arch_time, arch_who_id, main_cl_id, client_id
                // sssssss = family, name, otch, city, str, dom, kv
                // ss = pas_n, pas_ln
                // i = pas_date
                // s = pas_who
                // ssss = reg_city, reg_str, reg_dom, reg_kv
                // ss = phone_1, phone_2
                // ss = info, status
                // ii = cr_time, arch_n
                // d = arch_amount
                // ii = arch_l_date, cr_who
                // s = source
                $slave_pas_date = (int)$slave['pas_date'];
                $slave_cr_time  = (int)$slave['cr_time'];
                $slave_arch_n   = (int)$slave['arch_n'];
                $slave_arch_amount = (float)$slave['arch_amount'];
                $slave_arch_l_date = (int)$slave['arch_l_date'];
                $slave_cr_who   = (int)$slave['cr_who'];
                $ins->bind_param("iiiisssssssssisssssssssiidiis",
                    $arch_time, $user_id, $master_id, $slave['client_id'],
                    $slave['family'], $slave['name'], $slave['otch'], $slave['city'], $slave['str'], $slave['dom'], $slave['kv'],
                    $slave['pas_n'], $slave['pas_ln'], $slave_pas_date, $slave['pas_who'],
                    $slave['reg_city'], $slave['reg_str'], $slave['reg_dom'], $slave['reg_kv'],
                    $slave['phone_1'], $slave['phone_2'], $slave['info'], $slave['status'], $slave_cr_time,
                    $slave_arch_n, $slave_arch_amount, $slave_arch_l_date, $slave_cr_who, $slave['source']
                );
                $ins->execute();
                $ins->close();

                // 5. Delete slave
                $mysqli->query("DELETE FROM clients WHERE client_id = $slave_id");
            }

            $mysqli->commit();
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            $mysqli->rollback();
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Слияние дубликатов клиентов</title>
    <link href="/bb/stile.css" rel="stylesheet" type="text/css" />
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background-color: #f9f9f9; }
        .controls { margin-bottom: 20px; background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .group { margin-bottom: 30px; background: #fff; padding: 15px; border: 1px solid #c00; border-radius: 5px; }
        .group h3 { margin-top: 0; color: #c00; }
        .clients-flex { display: flex; flex-wrap: nowrap; overflow-x: auto; gap: 15px; padding-bottom: 10px; }
        .client-card { flex: 0 0 300px; border: 1px solid #ccc; border-radius: 4px; padding: 10px; background: #fafafa; position: relative; }
        .client-card.master { border: 2px solid #28a745; background: #e8f5e9; }
        .client-card h4 { margin: 0 0 10px 0; font-size: 16px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .field { font-size: 12px; margin-bottom: 4px; }
        .field strong { color: #555; }
        .btn-make-master { display: block; width: 100%; padding: 8px; margin-top: 10px; background: #28a745; color: #fff; border: none; cursor: pointer; border-radius: 3px; font-weight: bold;}
        .btn-make-master:hover { background: #218838; }
        .btn-merge-group { background: #007bff; color: #fff; border: none; padding: 10px 15px; cursor: pointer; border-radius: 3px; font-weight: bold; margin-top: 15px; }
        .btn-merge-group:hover { background: #0069d9; }
        .hidden { display: none; }
        .loading { color: #666; font-style: italic; }
        .badge { display: inline-block; padding: 4px 8px; font-size: 14px; font-weight: bold; border-radius: 4px; color: #fff; margin-left: 15px; vertical-align: middle; }
        .badge-high { background-color: #28a745; }
        .badge-medium { background-color: #ffc107; color: #212529; }
        .badge-low { background-color: #dc3545; }
    </style>
</head>
<body>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
    <a class="div_item" href="/bb/client_cleanup.php">Очистка БД</a>
</div>

<h2>Слияние дубликатов клиентов (Stage 3)</h2>

<div class="controls" style="text-align: center;">
    <button onclick="findDuplicates()" style="padding: 12px 24px; font-size: 16px; background-color: #007bff; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">Найти дубликаты (Умный алгоритм)</button>
</div>

<div id="results"></div>

<script>
async function findDuplicates() {
    const resDiv = document.getElementById('results');
    resDiv.innerHTML = '<div class="loading" style="text-align:center; padding: 30px; font-size: 18px;">Запуск графового алгоритма поиска... Пожалуйста, подождите (это может занять до 10 секунд).</div>';

    const formData = new FormData();
    formData.append('action', 'find_duplicates');

    try {
        const response = await fetch('client_merge.php', { method: 'POST', body: formData });
        const data = await response.json();

        if (data.error) {
            resDiv.innerHTML = '<div style="color:red">Ошибка: ' + data.error + '</div>';
            return;
        }

        if (data.groups.length === 0) {
            resDiv.innerHTML = '<div>Дубликатов по выбранному критерию не найдено.</div>';
            return;
        }

        renderGroups(data.groups);

    } catch (e) {
        resDiv.innerHTML = '<div style="color:red">Сетевая ошибка: ' + e.message + '</div>';
    }
}

function renderGroups(groups) {
    const resDiv = document.getElementById('results');
    resDiv.innerHTML = '';

    groups.forEach((g, gIndex) => {
        const gDiv = document.createElement('div');
        gDiv.className = 'group';
        gDiv.id = 'group-' + gIndex;
        gDiv.setAttribute('data-confidence', g.confidence);
        
        let confText = g.confidence === 'high' ? 'High Confidence' : (g.confidence === 'medium' ? 'Medium Confidence' : 'Low Confidence');
        
        let html = `<h3>${g.title} (Записей: ${g.clients.length}) <span class="badge badge-${g.confidence}">${confText} (Мин. Score: ${g.score})</span></h3><div class="clients-flex">`;
        
        g.clients.forEach(c => {
            const dateObj = new Date(c.cr_time * 1000);
            const crDate = dateObj.toLocaleDateString('ru-RU');
            
            html += `
            <div class="client-card" id="card-${gIndex}-${c.client_id}" data-id="${c.client_id}">
                <h4>ID: ${c.client_id}</h4>
                <div class="field"><strong>ФИО:</strong> ${c.family} ${c.name} ${c.otch}</div>
                <div class="field"><strong>Паспорт:</strong> ${c.pas_n} ЛН: ${c.pas_ln}</div>
                <div class="field"><strong>Прописка:</strong> ${c.reg_city}, ${c.reg_str} ${c.reg_dom}-${c.reg_kv}</div>
                <div class="field"><strong>Телефоны:</strong> ${c.phone_1} / ${c.phone_2}</div>
                <div class="field"><strong>Финансы:</strong> Архивов: ${c.arch_n}, Сумма: ${c.arch_amount}</div>
                <div class="field"><strong>Создан:</strong> ${crDate}</div>
                <div class="field"><strong>Инфо:</strong> ${c.info}</div>
                <button class="btn-make-master" onclick="selectMaster(${gIndex}, ${c.client_id})">Выбрать Главным</button>
            </div>
            `;
        });
        
        html += `</div>
        <button class="btn-merge-group hidden" id="btn-merge-${gIndex}" onclick="mergeGroup(${gIndex})">Объединить остальных в Главного</button>
        <div id="status-${gIndex}"></div>
        `;
        
        gDiv.innerHTML = html;
        resDiv.appendChild(gDiv);
    });
}

function selectMaster(gIndex, clientId) {
    // reset all cards in group
    const groupDiv = document.getElementById('group-' + gIndex);
    const cards = groupDiv.querySelectorAll('.client-card');
    cards.forEach(c => c.classList.remove('master'));
    
    // set new master
    const masterCard = document.getElementById(`card-${gIndex}-${clientId}`);
    masterCard.classList.add('master');
    groupDiv.setAttribute('data-master-id', clientId);
    
    // show merge button
    document.getElementById(`btn-merge-${gIndex}`).classList.remove('hidden');
}

async function mergeGroup(gIndex) {
    const groupDiv = document.getElementById('group-' + gIndex);
    const conf = groupDiv.getAttribute('data-confidence');
    
    if (conf === 'low') {
        if (!confirm('ВНИМАНИЕ! У этих клиентов низкий индекс сходства. Это могут быть разные люди или опечатка. Вы ТОЧНО уверены, что хотите их объединить?')) return;
    } else {
        if (!confirm('Вы уверены? Это действие необратимо.')) return;
    }
    
    const masterId = groupDiv.getAttribute('data-master-id');
    const cards = groupDiv.querySelectorAll('.client-card');
    
    let slaveIds = [];
    cards.forEach(c => {
        const id = c.getAttribute('data-id');
        if (id !== masterId) slaveIds.push(id);
    });
    
    if (slaveIds.length === 0) {
        alert("Нет записей для поглощения.");
        return;
    }
    
    const statusDiv = document.getElementById('status-' + gIndex);
    statusDiv.innerHTML = '<span class="loading">Объединяем...</span>';
    document.getElementById(`btn-merge-${gIndex}`).disabled = true;

    const formData = new FormData();
    formData.append('action', 'merge');
    formData.append('master_id', masterId);
    slaveIds.forEach(id => formData.append('slave_ids[]', id));

    try {
        const response = await fetch('client_merge.php', { method: 'POST', body: formData });
        const data = await response.json();

        if (data.error) {
            statusDiv.innerHTML = '<span style="color:red">Ошибка: ' + data.error + '</span>';
            document.getElementById(`btn-merge-${gIndex}`).disabled = false;
        } else {
            statusDiv.innerHTML = '<span style="color:green; font-weight:bold;">Успешно объединено!</span>';
            cards.forEach(c => {
                if (c.getAttribute('data-id') !== masterId) {
                    c.style.opacity = '0.3';
                    c.querySelector('button').style.display = 'none';
                }
            });
            document.getElementById(`btn-merge-${gIndex}`).style.display = 'none';
        }
    } catch (e) {
        statusDiv.innerHTML = '<span style="color:red">Сетевая ошибка: ' + e.message + '</span>';
        document.getElementById(`btn-merge-${gIndex}`).disabled = false;
    }
}
</script>

</body>
</html>

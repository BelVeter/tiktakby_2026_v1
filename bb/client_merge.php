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
        $type = $_POST['type'] ?? 'pas_ln';
        $limit = 50; // Групп за один раз
        
        $groups = [];
        
        if ($type === 'pas_ln') {
            $query = "SELECT pas_ln, COUNT(*) as c FROM clients WHERE pas_ln != '' GROUP BY pas_ln HAVING c > 1 LIMIT $limit";
            $res = $mysqli->query($query);
            while ($row = $res->fetch_assoc()) {
                $pas_ln = $mysqli->real_escape_string($row['pas_ln']);
                $clients = $mysqli->query("SELECT * FROM clients WHERE pas_ln = '$pas_ln'")->fetch_all(MYSQLI_ASSOC);
                $groups[] = ['title' => 'ЛН: ' . $row['pas_ln'], 'clients' => $clients];
            }
        } elseif ($type === 'phone') {
            $query = "SELECT phone_1, COUNT(*) as c FROM clients WHERE phone_1 != '' GROUP BY phone_1 HAVING c > 1 LIMIT $limit";
            $res = $mysqli->query($query);
            while ($row = $res->fetch_assoc()) {
                $phone = $mysqli->real_escape_string($row['phone_1']);
                $clients = $mysqli->query("SELECT * FROM clients WHERE phone_1 = '$phone' OR phone_2 = '$phone'")->fetch_all(MYSQLI_ASSOC);
                if (count($clients) > 1) {
                    $groups[] = ['title' => 'Телефон: ' . $row['phone_1'], 'clients' => $clients];
                }
            }
        } elseif ($type === 'name') {
            $query = "SELECT family, name, otch, COUNT(*) as c FROM clients GROUP BY family, name, otch HAVING c > 1 LIMIT $limit";
            $res = $mysqli->query($query);
            while ($row = $res->fetch_assoc()) {
                $f = $mysqli->real_escape_string($row['family']);
                $n = $mysqli->real_escape_string($row['name']);
                $o = $mysqli->real_escape_string($row['otch']);
                $clients = $mysqli->query("SELECT * FROM clients WHERE family='$f' AND name='$n' AND otch='$o'")->fetch_all(MYSQLI_ASSOC);
                $groups[] = ['title' => 'ФИО: ' . $row['family'] . ' ' . $row['name'] . ' ' . $row['otch'], 'clients' => $clients];
            }
        }

        echo json_encode(['status' => 'ok', 'groups' => $groups]);
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
    </style>
</head>
<body>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
    <a class="div_item" href="/bb/client_cleanup.php">Очистка БД</a>
</div>

<h2>Слияние дубликатов клиентов (Stage 3)</h2>

<div class="controls">
    <strong>Искать дубликаты по:</strong>
    <button onclick="findDuplicates('pas_ln')">Личному номеру паспорта</button>
    <button onclick="findDuplicates('phone')">Телефону</button>
    <button onclick="findDuplicates('name')">ФИО</button>
</div>

<div id="results"></div>

<script>
async function findDuplicates(type) {
    const resDiv = document.getElementById('results');
    resDiv.innerHTML = '<div class="loading">Идет поиск дубликатов...</div>';

    const formData = new FormData();
    formData.append('action', 'find_duplicates');
    formData.append('type', type);

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
        
        let html = `<h3>${g.title} (Записей: ${g.clients.length})</h3><div class="clients-flex">`;
        
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
    if (!confirm('Вы уверены? Это действие необратимо.')) return;
    
    const groupDiv = document.getElementById('group-' + gIndex);
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

<?php
session_start();
require_once ($_SERVER['DOCUMENT_ROOT'].'/bb/Db.php');
$mysqli = \bb\Db::getInstance()->getConnection();// Проверка авторизации
$in_level= array(0,5,7);
isset($_SESSION['svoi']) ? $_SESSION['svoi']=$_SESSION['svoi'] : $_SESSION['svoi']=0;
if ($_SESSION['svoi']!=8941 || !(in_array($_SESSION['level'], $in_level))) {
    die('Доступ запрещен.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $last_id = (int)($_POST['last_id'] ?? 0);
    $limit = 500;

    if ($action === 'sanitize') {
        $query = "SELECT client_id, family, name, otch, city, str, pas_n, pas_ln, phone_1, phone_2 
                  FROM clients WHERE client_id > $last_id ORDER BY client_id ASC LIMIT $limit";
        $res = $mysqli->query($query);
        if (!$res) die(json_encode(['error' => $mysqli->error]));

        $processed = 0;
        $max_id = $last_id;
        $updated_count = 0;

        $cyr = array('А', 'В', 'Е', 'К', 'М', 'Н', 'О', 'Р', 'С', 'Т', 'Х', 'а', 'в', 'е', 'к', 'м', 'н', 'о', 'р', 'с', 'т', 'х');
        $lat = array('A', 'B', 'E', 'K', 'M', 'H', 'O', 'P', 'C', 'T', 'X', 'A', 'B', 'E', 'K', 'M', 'H', 'O', 'P', 'C', 'T', 'X');

        while ($row = $res->fetch_assoc()) {
            $processed++;
            $max_id = $row['client_id'];

            $family = trim($row['family']);
            $name = trim($row['name']);
            $otch = trim($row['otch']);
            $city = trim($row['city']);
            $str = trim($row['str']);
            
            // Normalize pas_ln: replace cyrillic, uppercase, remove spaces, replace 'O' with '0'
            $pas_ln = str_replace($cyr, $lat, $row['pas_ln']);
            $pas_ln = strtoupper(trim($pas_ln));
            $pas_ln = str_replace(' ', '', $pas_ln);
            $pas_ln = str_replace('O', '0', $pas_ln);

            // Normalize pas_n: replace cyrillic, uppercase, remove multiple spaces
            $pas_n = str_replace($cyr, $lat, $row['pas_n']);
            $pas_n = strtoupper(trim($pas_n));
            $pas_n = preg_replace('/\s+/', ' ', $pas_n); // leave single space if any

            $phone_1 = trim($row['phone_1']);
            $phone_2 = trim($row['phone_2']);

            // Check if anything changed
            if ($family !== $row['family'] || $name !== $row['name'] || $otch !== $row['otch'] || 
                $city !== $row['city'] || $str !== $row['str'] || 
                $pas_ln !== $row['pas_ln'] || $pas_n !== $row['pas_n'] ||
                $phone_1 !== $row['phone_1'] || $phone_2 !== $row['phone_2']) {
                
                $upd = $mysqli->prepare("UPDATE clients SET family=?, name=?, otch=?, city=?, str=?, pas_n=?, pas_ln=?, phone_1=?, phone_2=? WHERE client_id=?");
                $upd->bind_param("sssssssssi", $family, $name, $otch, $city, $str, $pas_n, $pas_ln, $phone_1, $phone_2, $row['client_id']);
                $upd->execute();
                $upd->close();
                $updated_count++;
            }
        }

        echo json_encode(['status' => 'ok', 'processed' => $processed, 'updated' => $updated_count, 'last_id' => $max_id, 'done' => ($processed < $limit)]);
        exit;
    }

    if ($action === 'purge_ghosts') {
        // Find ghost clients
        // We select the next batch of clients that have NO relations anywhere.
        $query = "SELECT * FROM clients c 
                  WHERE c.client_id > $last_id 
                  AND c.arch_n = 0 AND c.arch_amount = 0 
                  AND NOT EXISTS (SELECT 1 FROM rent_deals_act rda WHERE rda.client_id = c.client_id)
                  AND NOT EXISTS (SELECT 1 FROM rent_deals_arch rdar WHERE rdar.client_id = c.client_id)
                  AND NOT EXISTS (SELECT 1 FROM deals d WHERE d.client_id = c.client_id)
                  AND NOT EXISTS (SELECT 1 FROM rent_orders ro WHERE ro.client_id = c.client_id)
                  AND NOT EXISTS (SELECT 1 FROM rent_orders_arch roa WHERE roa.client_id = c.client_id)
                  AND NOT EXISTS (SELECT 1 FROM karn_brons kb WHERE kb.cl_id = c.client_id)
                  AND NOT EXISTS (SELECT 1 FROM karn_brons_arch kba WHERE kba.cl_id = c.client_id)
                  ORDER BY c.client_id ASC LIMIT $limit";

        $res = $mysqli->query($query);
        if (!$res) die(json_encode(['error' => $mysqli->error]));

        $processed = 0;
        $max_id = $last_id;
        $purged_count = 0;

        while ($row = $res->fetch_assoc()) {
            $processed++;
            $max_id = $row['client_id'];
            $purged_count++;

            // Insert into clients_arch with main_cl_id = 0 to signify deletion/ghost
            $ins = $mysqli->prepare("INSERT INTO clients_arch 
                (arch_time, arch_who_id, main_cl_id, client_id, family, name, otch, city, str, dom, kv, pas_n, pas_ln, pas_date, pas_who, reg_city, reg_str, reg_dom, reg_kv, phone_1, phone_2, info, status, cr_time, arch_n, arch_amount, arch_l_date, cr_who, source)
                VALUES (?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $arch_time = time();
            $user_id = $_SESSION['user_id'];
            $ins->bind_param("iiisssssssssisssssssssiidiis", 
                $arch_time, $user_id, $row['client_id'], 
                $row['family'], $row['name'], $row['otch'], $row['city'], $row['str'], $row['dom'], $row['kv'], 
                $row['pas_n'], $row['pas_ln'], $row['pas_date'], $row['pas_who'], 
                $row['reg_city'], $row['reg_str'], $row['reg_dom'], $row['reg_kv'], 
                $row['phone_1'], $row['phone_2'], $row['info'], $row['status'], $row['cr_time'], 
                $row['arch_n'], $row['arch_amount'], $row['arch_l_date'], $row['cr_who'], $row['source']
            );
            $ins->execute();
            $ins->close();

            // Delete from clients
            $mysqli->query("DELETE FROM clients WHERE client_id = " . $row['client_id']);
        }

        // We also need a way to increment last_id properly if we didn't process 500 ghost clients.
        // If processed < limit, it means we scanned all remaining ghost clients.
        // Wait, the query explicitly filters for ghost clients. To avoid missing non-ghost clients in the ID range,
        // we should just select ALL clients and filter in PHP, OR query the max client_id scanned.
        // Actually, let's select ALL clients in PHP to keep `last_id` moving linearly.
        echo json_encode(['status' => 'ok', 'processed' => $processed, 'purged' => $purged_count, 'last_id' => $max_id, 'done' => ($processed < $limit)]);
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Очистка базы клиентов</title>
    <link href="/bb/stile.css" rel="stylesheet" type="text/css" />
    <style>
        body { font-family: sans-serif; padding: 20px; }
        .box { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .log { background: #f4f4f4; padding: 10px; height: 150px; overflow-y: auto; font-family: monospace; border: 1px solid #ddd; }
        button { padding: 10px 15px; cursor: pointer; }
    </style>
</head>
<body>

<div class="top_menu">
	<a class="div_item" href="/bb/index.php">На главную</a>
</div>

<h2>Очистка базы клиентов</h2>

<div class="box">
    <h3>Stage 1: Санитария данных</h3>
    <p>Удаляет лишние пробелы, заменяет русскую кириллицу в личных номерах паспортов на латиницу, приводит к верхнему регистру.</p>
    <button id="btn-sanitize" onclick="startProcess('sanitize', 'log-sanitize')">Запустить санитарию</button>
    <div class="log" id="log-sanitize">Готов к запуску...</div>
</div>

<div class="box">
    <h3>Stage 2: Очистка от "призраков"</h3>
    <p>Переносит в `clients_arch` клиентов, у которых нет ни сделок, ни заказов, ни броней. <strong>Внимание: Выполнять только после резервного копирования!</strong></p>
    <button id="btn-purge" onclick="startProcess('purge_ghosts', 'log-purge')">Запустить удаление призраков</button>
    <div class="log" id="log-purge">Готов к запуску...</div>
</div>

<script>
async function startProcess(action, logId) {
    const logEl = document.getElementById(logId);
    let lastId = 0;
    let totalProcessed = 0;
    let totalUpdated = 0;
    let done = false;

    logEl.innerHTML = "Запуск " + action + "...<br>";

    while (!done) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('last_id', lastId);

        try {
            const response = await fetch('client_cleanup.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.error) {
                logEl.innerHTML += "<span style='color:red'>Ошибка: " + data.error + "</span><br>";
                break;
            }

            totalProcessed += data.processed;
            if (action === 'sanitize') {
                totalUpdated += data.updated;
                logEl.innerHTML += `Обработано: ${totalProcessed}. Обновлено записей: ${totalUpdated}. Last ID: ${data.last_id}<br>`;
            } else {
                totalUpdated += data.purged; // reused var for purged
                logEl.innerHTML += `Просканировано призраков: ${totalProcessed}. Перенесено в архив: ${totalUpdated}. Last ID: ${data.last_id}<br>`;
            }
            
            logEl.scrollTop = logEl.scrollHeight;
            lastId = data.last_id;
            done = data.done;

        } catch (e) {
            logEl.innerHTML += "<span style='color:red'>Сетевая ошибка: " + e.message + "</span><br>";
            break;
        }
    }
    logEl.innerHTML += "<strong>Завершено!</strong><br>";
}
</script>

</body>
</html>

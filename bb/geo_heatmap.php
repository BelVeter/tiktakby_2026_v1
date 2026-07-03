<?php
namespace bb;

session_start();
ini_set('display_errors', (isset($_SESSION['svoi']) && $_SESSION['svoi'] == 8941) ? 1 : 0);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Base.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/Db.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/bb/models/User.php');
require($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');

// Authenticate user
Base::loginCheck(array(0, 3, 5, 7));

$dotenv = \Dotenv\Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
$dotenv->safeLoad();
// $apiKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? ''; // Больше не нужно для Leaflet

$mysqli = \bb\Db::getInstance()->getConnection();

// Default values: last 30 days
$from_date = isset($_POST['from_date']) ? $_POST['from_date'] : date('Y-m-d', strtotime('-30 days'));
$to_date = isset($_POST['to_date']) ? $_POST['to_date'] : date('Y-m-d');
$delivery_type = isset($_POST['delivery_type']) ? $_POST['delivery_type'] : 'all';

$start_ts = strtotime($from_date . ' 00:00:00');
$end_ts = strtotime($to_date . ' 23:59:59');

$points = [];

$delivery_condition = "";
if ($delivery_type === 'courier') {
    $delivery_condition = " AND s.delivery_yn = '1' ";
} elseif ($delivery_type === 'office') {
    $delivery_condition = " AND (s.delivery_yn != '1' OR s.delivery_yn IS NULL) ";
}

$query = "
    SELECT g.lat, g.lng
    FROM rent_sub_deals_act s
    JOIN rent_deals_act d ON s.deal_id = d.deal_id
    JOIN clients_geo g ON d.client_id = g.client_id
    WHERE s.type IN ('first_rent', 'takeaway_plan')
      AND s.acc_date BETWEEN ? AND ?
      AND g.geo_status = 1
      $delivery_condition
    UNION ALL
    SELECT g.lat, g.lng
    FROM rent_sub_deals_arch s
    JOIN rent_deals_arch d ON s.deal_id = d.deal_id
    JOIN clients_geo g ON d.client_id = g.client_id
    WHERE s.type IN ('first_rent', 'takeaway_plan')
      AND s.acc_date BETWEEN ? AND ?
      AND g.geo_status = 1
      $delivery_condition
";

$stmt = $mysqli->prepare($query);
if ($stmt) {
    $stmt->bind_param("iiii", $start_ts, $end_ts, $start_ts, $end_ts);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['lat']) && !empty($row['lng'])) {
            $points[] = "[{$row['lat']}, {$row['lng']}, 1]";
        }
    }
    $stmt->close();
}

$points_js = implode(",\n", $points);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Heatmap: Новые выдачи</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; background: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { font-size: 24px; color: #333; }
        .filter-form { background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; display: flex; gap: 15px; align-items: center; }
        .filter-form label { font-weight: bold; }
        .filter-form input[type="date"], .filter-form select { padding: 5px; border: 1px solid #ccc; border-radius: 4px; }
        .filter-form button { padding: 6px 15px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .filter-form button:hover { background: #218838; }
        #map { height: 600px; width: 100%; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .stats { margin-bottom: 10px; font-weight: bold; color: #555; }
    </style>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
</head>
<body>

<?php include_once($_SERVER['DOCUMENT_ROOT'] . '/bb/bb_nav.php'); ?>

<div class="container">
    <h1>Heatmap (Тепловая карта): Новые выдачи товаров</h1>

    <form method="POST" class="filter-form">
        <label for="from_date">Период с:</label>
        <input type="date" id="from_date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" required>
        
        <label for="to_date">по:</label>
        <input type="date" id="to_date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" required>
        
        <label for="delivery_type">Тип выдачи:</label>
        <select name="delivery_type" id="delivery_type">
            <option value="all" <?= $delivery_type === 'all' ? 'selected' : '' ?>>Все</option>
            <option value="office" <?= $delivery_type === 'office' ? 'selected' : '' ?>>С офиса</option>
            <option value="courier" <?= $delivery_type === 'courier' ? 'selected' : '' ?>>Курьером</option>
        </select>
        
        <button type="submit">Показать</button>
    </form>

    <div class="stats">
        Найдено выдач с координатами за период: <?= count($points) ?>
    </div>

    <div id="map"></div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <!-- Leaflet Heatmap Plugin -->
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>

    <script>
        // Инициализируем карту (Центрируем на Минск)
        var map = L.map('map').setView([53.9006, 27.5590], 11);

        // Добавляем базовый слой OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 18
        }).addTo(map);

        // Данные для тепловой карты: [lat, lng, intensity]
        var heatMapData = [
            <?= $points_js ?>
        ];

        // Добавляем тепловой слой
        var heat = L.heatLayer(heatMapData, {
            radius: 20,
            blur: 15,
            maxZoom: 17
        }).addTo(map);

        // Добавляем маркер офиса
        var officeIcon = L.icon({
            iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
            iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
            shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

        L.marker([53.9401009, 27.5680041], {icon: officeIcon})
            .addTo(map)
            .bindPopup('Офис TIKTAK.BY (Литературная 22)');
    </script>

</div>

</body>
</html>

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

$mysqli = \bb\Db::getInstance()->getConnection();

// Default values: 01.01.2026 to today
$from_date = isset($_POST['from_date']) ? $_POST['from_date'] : '2026-01-01';
$to_date = isset($_POST['to_date']) ? $_POST['to_date'] : date('Y-m-d');

$start_ts = strtotime($from_date . ' 00:00:00');
$end_ts = strtotime($to_date . ' 23:59:59');

// Fetch user names
$lp_list = [];
$res_lp = $mysqli->query("SELECT logpass_id, lp_fio FROM logpass");
while ($row = $res_lp->fetch_assoc()) {
    $lp_list[$row['logpass_id']] = $row['lp_fio'];
}

// Fetch category details
$categories = [];
$res_cats = $mysqli->query("SELECT ri_code, ri_text FROM rash_items");
while ($row = $res_cats->fetch_assoc()) {
    $categories[$row['ri_code']] = $row['ri_text'];
}
$categories['other'] = 'прочие расходы';

// Query expenses (exclude shifts/transfers, type1 = 'rash')
$query = "SELECT * FROM doh_rash WHERE type1='rash' AND acc_date BETWEEN $start_ts AND $end_ts ORDER BY acc_date DESC";
$res = $mysqli->query($query);

$total_amount = 0;
$tx_count = 0;
$category_totals = [];
$transactions = [];

while ($row = $res->fetch_assoc()) {
    $amount = abs($row['amount']);
    $cat_code = $row['type2'];
    $total_amount += $amount;
    $tx_count++;
    
    if (!isset($category_totals[$cat_code])) {
        $category_totals[$cat_code] = 0;
    }
    $category_totals[$cat_code] += $amount;
    
    $transactions[] = [
        'id' => $row['dr_id'],
        'date_ts' => $row['acc_date'],
        'date' => date('d.m.Y', $row['acc_date']),
        'amount' => $amount,
        'cat_code' => $cat_code,
        'cat_name' => isset($categories[$cat_code]) ? $categories[$cat_code] : $cat_code,
        'channel' => $row['channel'],
        'kassa' => $row['kassa'],
        'info' => $row['info'],
        'who' => isset($lp_list[$row['cr_who_id']]) ? $lp_list[$row['cr_who_id']] : 'ID: ' . $row['cr_who_id'],
        'zp_name' => $row['dr_name_id']
    ];
}

arsort($category_totals);

$avg_expense = $tx_count > 0 ? $total_amount / $tx_count : 0;

// Sub-nav helper function for rendering channel names
function getChannelName($channel, $kassa) {
    if ($channel == 'bank') return 'Банк';
    if ($channel == '1') return 'Литературная (К' . str_replace('k', '', $kassa) . ')';
    if ($channel == '2') return 'Ложинская (К' . str_replace('k', '', $kassa) . ')';
    if ($channel == '3') return 'Победителей (К' . str_replace('k', '', $kassa) . ')';
    if ($channel == '4') return 'Склад (К' . str_replace('k', '', $kassa) . ')';
    if ($channel == 'cur') return 'Курьер (К' . str_replace('k', '', $kassa) . ')';
    
    $key = $channel . $kassa;
    $map = [
        "of1k1" => "Литературная_22 (К1)",
        "of1k2" => "Литературная_22 (К2)",
        "of2k1" => "Ложинская (К1)",
        "of2k2" => "Ложинская (К2)",
        "of3k1" => "Победителей_127 (К1)",
        "of3k2" => "Победителей_127 (К2)",
        "of4k1" => "Склад (К1)",
        "of4k2" => "Склад (К2)",
        "curk1" => "Курьер (К1)",
        "curk2" => "Курьер (К2)",
        "bankbank" => "Банк",
        "bank" => "Банк"
    ];
    if (isset($map[$key])) return $map[$key];
    if (isset($map[$channel])) return $map[$channel];
    return $channel . ' ' . $kassa;
}

echo Base::pageStartB5('Анализ расходов');
?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f3f4f6;
        color: #1f2937;
    }
    
    /* Navigation override */
    .bb-icon-nav {
        margin-bottom: 20px;
    }

    /* Dashboard Header */
    .dash-header {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #ffffff;
        padding: 2.5rem 2rem;
        border-radius: 16px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        margin-bottom: 2rem;
    }
    
    .dash-header h1 {
        font-weight: 700;
        letter-spacing: -0.025em;
        margin-bottom: 0.5rem;
    }
    
    .dash-header p {
        color: #94a3b8;
        font-size: 0.95rem;
    }

    /* KPI Cards */
    .kpi-card {
        background: #ffffff;
        border: none;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
        padding: 1.5rem;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .kpi-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
    }
    
    .kpi-title {
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #6b7280;
        margin-bottom: 0.5rem;
    }
    
    .kpi-val {
        font-size: 1.8rem;
        font-weight: 700;
        letter-spacing: -0.025em;
    }
    
    .kpi-val.total {
        color: #ef4444;
    }
    .kpi-val.count {
        color: #3b82f6;
    }
    .kpi-val.avg {
        color: #10b981;
    }

    /* Filters Section */
    .filters-card {
        background: #ffffff;
        border: none;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .form-label {
        font-weight: 600;
        color: #374151;
        font-size: 0.9rem;
    }
    
    .form-control-custom {
        border-radius: 8px;
        border: 1px solid #d1d5db;
        padding: 0.5rem 0.75rem;
        font-size: 0.95rem;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    .form-control-custom:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        outline: 0;
    }
    
    .btn-preset {
        font-size: 0.85rem;
        padding: 0.35rem 0.75rem;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        background: #f9fafb;
        color: #4b5563;
        transition: all 0.2s;
    }
    
    .btn-preset:hover, .btn-preset.active {
        background: #3b82f6;
        color: #ffffff;
        border-color: #3b82f6;
    }
    
    .btn-submit {
        background: #3b82f6;
        color: #ffffff;
        border-radius: 8px;
        font-weight: 500;
        padding: 0.5rem 1.5rem;
        border: none;
        transition: background 0.2s;
    }
    
    .btn-submit:hover {
        background: #2563eb;
        color: #ffffff;
    }

    /* Content Cards */
    .content-card {
        background: #ffffff;
        border: none;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        padding: 1.5rem;
        height: 100%;
    }
    
    .card-title-custom {
        font-size: 1.15rem;
        font-weight: 700;
        color: #111827;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* Category Table */
    .table-custom {
        margin-bottom: 0;
    }
    
    .table-custom th {
        font-weight: 600;
        color: #4b5563;
        background: #f9fafb;
        border-bottom: 2px solid #f3f4f6;
        padding: 0.75rem 1rem;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .table-custom td {
        padding: 1rem;
        vertical-align: middle;
        font-size: 0.95rem;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .category-row {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .category-row:hover {
        background-color: #f9fafb;
    }
    
    .category-row.active {
        background-color: #eff6ff;
        border-left: 4px solid #3b82f6;
    }
    
    .color-indicator {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 8px;
        vertical-align: middle;
    }

    /* Progress bar custom styling */
    .progress-custom {
        height: 8px;
        border-radius: 9999px;
        background-color: #f3f4f6;
        overflow: hidden;
    }

    /* Chart Area */
    .chart-container-custom {
        position: relative;
        margin: auto;
        height: 300px;
        width: 300px;
    }

    /* Details Section */
    .details-section {
        margin-top: 2rem;
    }
    
    .badge-channel {
        background-color: #f3f4f6;
        color: #374151;
        font-weight: 500;
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
    }

    .badge-category-lbl {
        background-color: #fee2e2;
        color: #ef4444;
        font-weight: 600;
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
    }

    .details-row {
        transition: opacity 0.2s;
    }
    
    .text-muted-custom {
        color: #9ca3af;
        font-size: 0.85rem;
    }

    .reset-btn {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        display: none;
    }
</style>

<div class="container-fluid px-4 py-3">
    <!-- Navbar -->
    <?php include_once($_SERVER['DOCUMENT_ROOT'] . '/bb/bb_nav.php'); ?>

    <!-- Sub-Navbar for accounting reports -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white rounded-3 shadow-sm mb-4 px-3">
        <a class="navbar-brand fw-bold text-primary" href="/bb/index.php">Отчетность</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#reportNavbar" aria-controls="reportNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="reportNavbar">
            <div class="navbar-nav">
                <a class="nav-item nav-link" href="/bb/sales_breakdown.php">Динамика выручки</a>
                <a class="nav-item nav-link" href="/bb/dohrash2.php">Свод доходов и расходов</a>
                <a class="nav-item nav-link" href="/bb/cat_analysis.php">Анализ выдачи</a>
                <a class="nav-item nav-link" href="/bb/tovar_report.php">Товары (динамика)</a>
                <a class="nav-item nav-link active fw-semibold text-primary" href="/bb/rash_analysis.php">Анализ расходов</a>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="dash-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1>Анализ расходов</h1>
                <p class="mb-0">Наглядный финансовый анализ и распределение затрат по статьям за выбранный период.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <span class="badge bg-danger fs-6 px-3 py-2">Период: <?= date('d.m.Y', $start_ts) ?> — <?= date('d.m.Y', $end_ts) ?></span>
            </div>
        </div>
    </div>

    <!-- Date Filters Card -->
    <div class="filters-card">
        <form action="rash_analysis.php" method="post" id="filter_form">
            <div class="row align-items-end g-3">
                <div class="col-md-3">
                    <label for="from_date" class="form-label">Дата начала</label>
                    <input type="date" name="from_date" id="from_date" class="form-control form-control-custom" value="<?= $from_date ?>">
                </div>
                <div class="col-md-3">
                    <label for="to_date" class="form-label">Дата окончания</label>
                    <input type="date" name="to_date" id="to_date" class="form-control form-control-custom" value="<?= $to_date ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label d-block">Быстрый выбор</label>
                    <div class="btn-group w-100" role="group">
                        <button type="button" class="btn btn-preset" onclick="setPreset('year')">С начала года</button>
                        <button type="button" class="btn btn-preset" onclick="setPreset('month')">Этот месяц</button>
                        <button type="button" class="btn btn-preset" onclick="setPreset('last_month')">Прошлый месяц</button>
                        <button type="button" class="btn btn-preset" onclick="setPreset('30days')">30 дней</button>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-submit w-100">Показать</button>
                </div>
            </div>
        </form>
    </div>

    <!-- KPI Summary Grid -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="kpi-card text-center text-md-start">
                <div class="kpi-title">Общие расходы</div>
                <div class="kpi-val total"><?= number_format($total_amount, 2, ',', ' ') ?> <span class="fs-5">руб.</span></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card text-center text-md-start">
                <div class="kpi-title">Количество операций</div>
                <div class="kpi-val count"><?= $tx_count ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card text-center text-md-start">
                <div class="kpi-title">Средний чек расхода</div>
                <div class="kpi-val avg"><?= number_format($avg_expense, 2, ',', ' ') ?> <span class="fs-5">руб.</span></div>
            </div>
        </div>
    </div>

    <!-- Main Analytics Grid -->
    <div class="row g-4">
        <!-- Breakdown Table -->
        <div class="col-lg-8">
            <div class="content-card">
                <div class="card-title-custom">
                    <span>Распределение затрат по статьям</span>
                    <button id="reset_filter_btn" class="btn btn-outline-secondary reset-btn" onclick="clearCategoryFilter()">Сбросить фильтр</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom table-hover" id="category_table">
                        <thead>
                            <tr>
                                <th style="width: 40%">Статья расходов</th>
                                <th class="text-end" style="width: 25%">Сумма</th>
                                <th style="width: 35%">Доля в расходах</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($category_totals)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">Нет данных за выбранный период</td>
                                </tr>
                            <?php else: ?>
                                <?php
                                $index = 0;
                                // Colors mapped to categories
                                $color_palette = [
                                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                                    '#858796', '#5a5c69', '#f472b6', '#3b82f6', '#10b981',
                                    '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#6366f1',
                                    '#14b8a6', '#f97316', '#a855f7', '#6b7280', '#06b6d4'
                                ];
                                $cat_colors_js = [];
                                
                                foreach ($category_totals as $code => $sum):
                                    $percentage = $total_amount > 0 ? ($sum / $total_amount) * 100 : 0;
                                    $color = $color_palette[$index % count($color_palette)];
                                    $cat_colors_js[$code] = $color;
                                    $name = isset($categories[$code]) ? $categories[$code] : $code;
                                    $index++;
                                ?>
                                    <tr class="category-row" id="row_<?= $code ?>" onclick="filterByCategory('<?= $code ?>', '<?= htmlspecialchars($name, ENT_QUOTES) ?>')">
                                        <td>
                                            <span class="color-indicator" style="background-color: <?= $color ?>"></span>
                                            <span class="fw-semibold text-dark"><?= htmlspecialchars($name) ?></span>
                                        </td>
                                        <td class="text-end fw-bold"><?= number_format($sum, 2, ',', ' ') ?> руб.</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="me-2 text-muted small" style="width: 45px"><?= number_format($percentage, 1) ?>%</span>
                                                <div class="progress progress-custom flex-grow-1">
                                                    <div class="progress-bar" role="progressbar" style="width: <?= $percentage ?>%; background-color: <?= $color ?>" aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <div class="col-lg-4">
            <div class="content-card d-flex flex-column justify-content-center">
                <h2 class="card-title-custom text-center mb-4">Диаграмма затрат</h2>
                <div class="chart-container-custom my-3">
                    <canvas id="expenseChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Transactions Table -->
    <div class="details-section">
        <div class="content-card">
            <div class="card-title-custom">
                <span id="details_title">Детализация всех расходов</span>
                <span class="badge bg-secondary fs-7" id="details_count"><?= count($transactions) ?> зап.</span>
            </div>
            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                <table class="table table-hover align-middle table-sm" id="details_table">
                    <thead>
                        <tr class="table-light">
                            <th>Дата</th>
                            <th>Статья</th>
                            <th>Касса / Канал</th>
                            <th>Сотрудник</th>
                            <th>Комментарий</th>
                            <th class="text-end">Сумма</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Нет записей расходов за этот период</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $tx): ?>
                                <tr class="details-row" data-cat="<?= $tx['cat_code'] ?>">
                                    <td><?= $tx['date'] ?></td>
                                    <td>
                                        <span class="badge-category-lbl"><?= htmlspecialchars($tx['cat_name']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge-channel"><?= htmlspecialchars(getChannelName($tx['channel'], $tx['kassa'])) ?></span>
                                    </td>
                                    <td class="fw-semibold small text-secondary">
                                        <?= htmlspecialchars($tx['who']) ?>
                                    </td>
                                    <td class="small text-wrap" style="max-width: 300px;">
                                        <?= htmlspecialchars($tx['info']) ?>
                                    </td>
                                    <td class="text-end fw-bold text-danger"><?= number_format($tx['amount'], 2, ',', ' ') ?> руб.</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js and Custom Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Prepare data for the Chart.js doughnut chart
    const rawData = {
        <?php
        $js_labels = [];
        $js_data = [];
        $js_colors = [];
        foreach ($category_totals as $code => $sum) {
            $js_labels[] = isset($categories[$code]) ? $categories[$code] : $code;
            $js_data[] = $sum;
            $js_colors[] = isset($cat_colors_js[$code]) ? $cat_colors_js[$code] : '#858796';
        }
        echo "labels: " . json_encode($js_labels) . ",\n";
        echo "data: " . json_encode($js_data) . ",\n";
        echo "colors: " . json_encode($js_colors) . "\n";
        ?>
    };

    let expenseChart = null;

    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('expenseChart').getContext('2d');
        
        if (rawData.data.length > 0) {
            expenseChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: rawData.labels,
                    datasets: [{
                        data: rawData.data,
                        backgroundColor: rawData.colors,
                        borderWidth: 2,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false // Hide legend to avoid cluttering, table already shows it nicely
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += parseFloat(context.parsed).toLocaleString('ru-RU', { minimumFractionDigits: 2 }) + ' руб.';
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });
        } else {
            // Draw empty state chart or handle gracefully
            ctx.font = '14px sans-serif';
            ctx.fillStyle = '#6b7280';
            ctx.textAlign = 'center';
            ctx.fillText('Нет данных', 150, 150);
        }
    });

    // Date range helper presets
    function setPreset(presetType) {
        const fromInput = document.getElementById('from_date');
        const toInput = document.getElementById('to_date');
        const today = new Date();
        
        // Adjust for Europe/Minsk timezone (UTC+3)
        // Locally it might be different, let's construct UTC values
        let fromDate = new Date();
        let toDate = new Date();
        
        if (presetType === 'year') {
            // "С начала года": 01.01.2026 to today
            fromDate = new Date(2026, 0, 1);
            toDate = today;
        } else if (presetType === 'month') {
            // "Этот месяц": 1st of current month to today
            fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
            toDate = today;
        } else if (presetType === 'last_month') {
            // "Прошлый месяц": 1st of last month to last day of last month
            fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            toDate = new Date(today.getFullYear(), today.getMonth(), 0);
        } else if (presetType === '30days') {
            // "Последние 30 дней": 30 days ago to today
            fromDate.setDate(today.getDate() - 30);
            toDate = today;
        }
        
        // Format as YYYY-MM-DD
        const formatDate = (d) => {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        };
        
        fromInput.value = formatDate(fromDate);
        toInput.value = formatDate(toDate);
        
        document.getElementById('filter_form').submit();
    }

    // Dynamic category filtering in details table via JavaScript
    let activeCategoryFilter = null;

    function filterByCategory(catCode, catName) {
        const rows = document.querySelectorAll('.details-row');
        const catRows = document.querySelectorAll('.category-row');
        const resetBtn = document.getElementById('reset_filter_btn');
        const detailsTitle = document.getElementById('details_title');
        const detailsCount = document.getElementById('details_count');
        
        // If clicking the currently active filter, disable it (clear filter)
        if (activeCategoryFilter === catCode) {
            clearCategoryFilter();
            return;
        }
        
        activeCategoryFilter = catCode;
        
        // Highlight selected row in categories table
        catRows.forEach(row => {
            if (row.id === 'row_' + catCode) {
                row.classList.add('active');
            } else {
                row.classList.remove('active');
            }
        });
        
        // Show reset button
        resetBtn.style.display = 'inline-block';
        
        // Update details title
        detailsTitle.innerHTML = `Детализация расходов: <span class="text-primary fw-bold">${catName}</span>`;
        
        // Filter rows in transactions list
        let visibleCount = 0;
        rows.forEach(row => {
            if (row.getAttribute('data-cat') === catCode) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        detailsCount.textContent = `${visibleCount} зап.`;
        
        // Scroll details section into view smoothly
        document.querySelector('.details-section').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function clearCategoryFilter() {
        const rows = document.querySelectorAll('.details-row');
        const catRows = document.querySelectorAll('.category-row');
        const resetBtn = document.getElementById('reset_filter_btn');
        const detailsTitle = document.getElementById('details_title');
        const detailsCount = document.getElementById('details_count');
        
        activeCategoryFilter = null;
        
        // Remove highlighting from categories table
        catRows.forEach(row => {
            row.classList.remove('active');
        });
        
        // Hide reset button
        resetBtn.style.display = 'none';
        
        // Restore title
        detailsTitle.textContent = 'Детализация всех расходов';
        
        // Show all transaction rows
        rows.forEach(row => {
            row.style.display = '';
        });
        
        detailsCount.textContent = `${rows.length} зап.`;
    }
</script>

<?php
echo Base::pageEndHtmlB5();
?>

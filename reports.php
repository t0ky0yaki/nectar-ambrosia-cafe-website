<?php
date_default_timezone_set('Asia/Manila');
$serverName = "LAPTOP-5SE5KLER\\SQLEXPRESS";
$connectionOptions = ["Database" => "DLSU", "Uid" => "", "PWD" => ""];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) die(print_r(sqlsrv_errors(), true));

function getq($k, $d=''){ return (isset($_GET[$k]) && $_GET[$k] !== '') ? $_GET[$k] : $d; }
function escSql($s){ return str_replace("'", "''", $s); }

$today    = date('Y-m-d');
$defStart = date('Y') . '-11-30';

$sd   = getq('start_date', $defStart);
$ed   = getq('end_date',   $today);
$per  = getq('period', 'day');
$prod = getq('product', 'all');

if ($per !== 'day' && $per !== 'week' && $per !== 'month') $per = 'day';

$prodList = [];
$rsProd = sqlsrv_query($conn, "SELECT DISTINCT PRODUCT_NAME FROM ORDER_ITEMS ORDER BY PRODUCT_NAME");
if ($rsProd) {
    while ($r = sqlsrv_fetch_array($rsProd, SQLSRV_FETCH_ASSOC)) $prodList[] = $r;
    sqlsrv_free_stmt($rsProd);
}

$where = "o.ORDER_DATE >= '$sd' AND o.ORDER_DATE < DATEADD(day, 1, '$ed')";
if ($prod !== 'all') {
    $pSafe = escSql($prod);
    $where .= " AND oi.PRODUCT_NAME = '$pSafe'";
}
if ($per === 'day') {
    $labelExpr = "CONVERT(varchar(10), o.ORDER_DATE, 23)";
    $groupExpr = $labelExpr;
    $orderExpr = "Label";
} elseif ($per === 'week') {
    $labelExpr = "CONCAT(YEAR(o.ORDER_DATE), '-W', RIGHT('0' + CAST(DATEPART(week, o.ORDER_DATE) AS VARCHAR(2)), 2))";
    $groupExpr = "YEAR(o.ORDER_DATE), DATEPART(week, o.ORDER_DATE)";
    $orderExpr = "YEAR(o.ORDER_DATE), DATEPART(week, o.ORDER_DATE)";
} else { // month
    $labelExpr = "CONCAT(YEAR(o.ORDER_DATE), '-', RIGHT('0' + CAST(MONTH(o.ORDER_DATE) AS VARCHAR(2)), 2))";
    $groupExpr = "YEAR(o.ORDER_DATE), MONTH(o.ORDER_DATE)";
    $orderExpr = "YEAR(o.ORDER_DATE), MONTH(o.ORDER_DATE)";
}

$repSql = "SELECT $labelExpr AS Label, SUM(oi.SUBTOTAL) AS TotalSales
           FROM [ORDER] o
           INNER JOIN ORDER_ITEMS oi ON o.ORDER_ID = oi.ORDER_ID
           WHERE $where
           GROUP BY $groupExpr
           ORDER BY $orderExpr";

$qtySql = "SELECT $labelExpr AS Label, SUM(oi.QUANTITY) AS TotalQty
           FROM [ORDER] o
           INNER JOIN ORDER_ITEMS oi ON o.ORDER_ID = oi.ORDER_ID
           WHERE $where
           GROUP BY $groupExpr
           ORDER BY $orderExpr";
$result = [];
$labels = [];
$totals = [];

$rs = sqlsrv_query($conn, $repSql);
if ($rs) {
    while ($row = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC)) {
        $lbl = (string)$row['Label'];
        $val = (float)$row['TotalSales'];

        $result[] = ['Label' => $lbl, 'TotalSales' => $row['TotalSales']];
        $labels[] = $lbl;
        $totals[] = $val;
    }
    sqlsrv_free_stmt($rs);
}

$qtyMap = [];
$rsQty = sqlsrv_query($conn, $qtySql);
if ($rsQty) {
    while ($row = sqlsrv_fetch_array($rsQty, SQLSRV_FETCH_ASSOC)) {
        $qtyMap[(string)$row['Label']] = (int)$row['TotalQty'];
    }
    sqlsrv_free_stmt($rsQty);
}
$qtyData = [];
foreach ($labels as $lbl) $qtyData[] = isset($qtyMap[$lbl]) ? $qtyMap[$lbl] : 0;

$grandTotal = array_sum($totals);
$bestSql = "SELECT c.CATEGORY_NAME, oi.PRODUCT_NAME, SUM(oi.QUANTITY) AS Qty
            FROM [ORDER] o
            INNER JOIN ORDER_ITEMS oi ON o.ORDER_ID = oi.ORDER_ID
            INNER JOIN MENU m ON oi.PRODUCT_NAME = m.MENU_NAME
            INNER JOIN CATEGORY c ON m.CATEGORY_ID = c.CATEGORY_ID
            WHERE $where
            GROUP BY c.CATEGORY_NAME, oi.PRODUCT_NAME";

$bestRaw = [];
$rsBest = sqlsrv_query($conn, $bestSql);
if ($rsBest) {
    while ($r = sqlsrv_fetch_array($rsBest, SQLSRV_FETCH_ASSOC)) {
        $cat = (string)$r['CATEGORY_NAME'];
        if (!isset($bestRaw[$cat])) $bestRaw[$cat] = [];
        $bestRaw[$cat][] = $r;
    }
    sqlsrv_free_stmt($rsBest);
}

$best = [];
foreach ($bestRaw as $cat => $items) {
    usort($items, function ($a, $b) { return (int)$b['Qty'] <=> (int)$a['Qty']; });
    $best[$cat] = $items[0];
}

sqlsrv_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Reports</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
@font-face { font-family: "AUGUSTUS"; src: url("fonts/AUGUSTUS.ttf") format("truetype"); }
@font-face { font-family: "freak"; src: url("fonts/freak.ttf") format("truetype"); }

html, body { margin: 0; width: 100%; height: 100%; }

body {
    font-family: Arial, sans-serif;
    background-image: url("bg/orderbg4.png");
    background-repeat: no-repeat;
    background-position: center 56px;
    background-size: cover;
    background-attachment: fixed;
}

header {
    background: linear-gradient(90deg, #4cabdc 0%, #e1ecff 40%, #9cd0ec 100%);
    padding: 6px 14px;
    border-bottom: 3px solid #d4af37;
    box-shadow: 0 4px 12px #0f172a47;
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 1000;
}

.header-inner {
    max-width: 1500px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    padding-left: 24px;
    padding-right: 24px;
}

.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 999px;
    border: 1px solid #1f3b5c;
    background: #f9fafb;
    color: #1f3b5c;
    font-size: 15px;
    font-weight: 600;
    text-decoration: none;
    box-shadow: 0 3px 8px #1f406840;
    transition: 0.15s;
    white-space: nowrap;
}
.back-btn:hover { background: #e5edf7; transform: translateY(-1px); color: #111827; }

.brand-wrap { display: flex; justify-content: center; }
.brand-logo { width: 260px; height: auto; display: block; }

.page-shell {
    max-width: 1500px;
    margin: 0 auto;
    padding: 110px 18px 26px;
    display: grid;
    grid-template-columns: minmax(740px, 1fr) 420px;
    gap: 16px;
    align-items: start;
    font-family: "AUGUSTUS", sans-serif;
}

@media (max-width: 1200px) { .page-shell { grid-template-columns: 1fr; } }

.wrapper {
    width: 100%;
    margin: 0;
    background: #ffffff6e;
    border: 3px solid #d4af37;
    box-shadow: 0 10px 30px #a7c7e780;
    padding: 18px;
    border-radius: 20px;
    font-family: "AUGUSTUS", serif;
}

.rightbar {
    border: 3px solid #d4af37;
    border-radius: 20px;
    padding: 14px;
    background: #ffffff6e;
    box-shadow: 0 10px 30px #a7c7e773;
    font-family: "AUGUSTUS", sans-serif;
}
@media (min-width: 1201px) { .rightbar { position: sticky; top: 90px; } }

.panel {
    background: #fff;
    padding: 15px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 0 10px #1f406814;
    font-family: "AUGUSTUS", sans-serif;
}

.panel h3 {
    font-size: 18px;
    margin-bottom: 10px;
    color: #1f3b5c;
    font-family: "AUGUSTUS", sans-serif;
}

.chart-container { width: 100%; max-width: 900px; margin: 0 auto; }
</style>
</head>

<body>
<header>
    <div class="header-inner">
        <a href="direct.php" class="back-btn">&#8592; Go Back</a>
        <div class="brand-wrap">
            <img src="bg/LOGOS.png" class="brand-logo" alt="Nectar & Ambrosia Logo">
        </div>
    </div>
</header>

<div class="page-shell">
    <div class="wrapper">

        <div class="panel mb-3">
            <h3>Filters</h3>

            <form class="row g-3" method="get">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($sd) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($ed) ?>" max="<?= date('Y-m-d') ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">View By</label>
                    <select name="period" class="form-select">
                        <option value="day"   <?= $per === 'day' ? 'selected' : '' ?>>Daily</option>
                        <option value="week"  <?= $per === 'week' ? 'selected' : '' ?>>Weekly</option>
                        <option value="month" <?= $per === 'month' ? 'selected' : '' ?>>Monthly</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Product</label>
                    <select name="product" class="form-select">
                        <option value="all" <?= $prod === 'all' ? 'selected' : '' ?>>All Products</option>
                        <?php foreach ($prodList as $m): ?>
                            <option value="<?= htmlspecialchars($m['PRODUCT_NAME']) ?>" <?= ($prod === $m['PRODUCT_NAME']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['PRODUCT_NAME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 d-flex gap-2 flex-wrap">
                    <button class="btn btn-primary" type="submit">Apply Filters</button>
                    <a class="btn btn-outline-warning"
                    href="receipts.php?start_date=<?= urlencode($sd) ?>&end_date=<?= urlencode($ed) ?>&period=<?= urlencode($per) ?>&product=<?= urlencode($prod) ?>">
                        View Receipt Orders
                    </a>
                </div>
            </form>
        </div>

        <div class="panel mb-3">
            <h3>Sales Bar Chart</h3>
            <div class="chart-container mb-4">
                <canvas id="barChart"></canvas>
            </div>

            <h3>Products Sold Line Chart</h3>
            <div class="chart-container">
                <canvas id="lineChart"></canvas>
            </div>

            <?php if (empty($result)): ?>
                <p class="mt-3 text-muted">No data found for the selected filters.</p>
            <?php endif; ?>
        </div>

    </div>

    <aside class="rightbar">

        <div class="panel mb-3">
            <h3>Best-Selling Item per Category</h3>

            <?php if (empty($best)): ?>
                <p class="text-muted mb-0">No category best-sellers found for the selected filters.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Category</th>
                                <th>Top Item</th>
                                <th class="text-end">Quantity Sold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($best as $cat => $b): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cat) ?></td>
                                    <td><?= htmlspecialchars($b['PRODUCT_NAME']) ?></td>
                                    <td class="text-end"><?= number_format((float)$b['Qty']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h3>Sales Table</h3>

            <?php if (!empty($result)): ?>
                <p class="mb-2"><strong>Overall Total Sales (PHP):</strong> <?= number_format($grandTotal, 2) ?></p>
            <?php endif; ?>

            <div class="table-responsive" style="max-height:400px; overflow-y:auto;">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><?= $per === 'day' ? 'Date' : ($per === 'week' ? 'Year-Week' : 'Year-Month') ?></th>
                            <th class="text-end">Total Sales (PHP)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($result)): ?>
                            <tr><td colspan="2" class="text-center py-3">No data to display.</td></tr>
                        <?php else: ?>
                            <?php foreach ($result as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['Label']) ?></td>
                                    <td class="text-end"><?= number_format((float)$row['TotalSales'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </aside>
</div>

<script>
var labels   = <?= json_encode($labels) ?>;
var sales    = <?= json_encode($totals) ?>;
var quantity = <?= json_encode($qtyData) ?>;

var barCtx  = document.getElementById('barChart').getContext('2d');
var lineCtx = document.getElementById('lineChart').getContext('2d');

new Chart(barCtx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: "Total Sales (PHP)",
            data: sales,
            borderWidth: 2,
            borderColor: "#f59e0b",
            backgroundColor: "#f59e0b"
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true, title: { display: true, text: "Sales Amount (PHP)" } }
        }
    }
});

new Chart(lineCtx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: "Products Sold (Units)",
            data: quantity,
            borderWidth: 2,
            borderColor: "#2563eb",
            backgroundColor: "rgba(37, 99, 235, 0.15)",
            tension: 0.35,
            fill: true,
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true, title: { display: true, text: "Number of Products Sold" } }
        }
    }
});
</script>

</body>
</html>

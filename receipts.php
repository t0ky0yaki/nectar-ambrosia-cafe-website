<?php
date_default_timezone_set('Asia/Manila');

$serverName = "LAPTOP-5SE5KLER\\SQLEXPRESS";
$connectionOptions = ["Database" => "DLSU", "Uid" => "", "PWD" => ""];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) die(print_r(sqlsrv_errors(), true));

function getq($k, $d=''){ return (isset($_GET[$k]) && $_GET[$k] !== '') ? $_GET[$k] : $d; }

$today    = date('Y-m-d');
$defStart = date('Y') . '-11-30';

$sd   = getq('start_date', $defStart);
$ed   = getq('end_date',   $today);
$sort = getq('sort', 'new');
if ($sort !== 'old' && $sort !== 'new') $sort = 'old';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

function keepFilters($sd, $ed, $sort){
    return "start_date=" . urlencode($sd) . "&end_date=" . urlencode($ed) . "&sort=" . urlencode($sort);
}

/* WHERE clause (date only) */
$where = "o.ORDER_DATE >= '$sd' AND o.ORDER_DATE < DATEADD(day, 1, '$ed')";

/* ORDER BY for list */
$orderBy = ($sort === 'new')
    ? "ORDER BY o.ORDER_DATE DESC, o.ORDER_ID DESC"
    : "ORDER BY o.ORDER_DATE ASC, o.ORDER_ID ASC";

if ($orderId > 0) {

    // 1) Header (your ORDER table has TOTAL, CUSTOMER_NAME, ORDER_STATUS)
    $osql = "SELECT ORDER_ID, TOTAL, ORDER_DATE, CUSTOMER_NAME, ORDER_STATUS
             FROM [ORDER]
             WHERE ORDER_ID = $orderId";
    $ors = sqlsrv_query($conn, $osql);
    if (!$ors) die(print_r(sqlsrv_errors(), true));
    $order = sqlsrv_fetch_array($ors, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($ors);

    if (!$order) {
        sqlsrv_close($conn);
        die("Order not found.");
    }

    // 2) Items (NOTE: UNIT_PRICE is the correct column)
    $isql = "SELECT PRODUCT_NAME, QUANTITY, UNIT_PRICE, SUBTOTAL
             FROM ORDER_ITEMS
             WHERE ORDER_ID = $orderId
             ORDER BY ITEM_ID ASC";
    $irs = sqlsrv_query($conn, $isql);
    if (!$irs) die(print_r(sqlsrv_errors(), true));

    $items = [];
    $sumSubtotal = 0;

    while ($r = sqlsrv_fetch_array($irs, SQLSRV_FETCH_ASSOC)) {
        $items[] = $r;
        $sumSubtotal += (float)$r['SUBTOTAL'];
    }
    sqlsrv_free_stmt($irs);

    $subtotal = $sumSubtotal;
    $vat = $subtotal * 0.12;
    $discount = 0;

    $dbTotal = isset($order['TOTAL']) ? (float)$order['TOTAL'] : 0;
    $total = ($dbTotal > 0) ? $dbTotal : ($subtotal + $vat - $discount);

    // Date format
    $dt = $order['ORDER_DATE'];
    $dtStr = ($dt instanceof DateTime) ? $dt->format('Y-m-d H:i') : (string)$dt;

    $cust = isset($order['CUSTOMER_NAME']) ? (string)$order['CUSTOMER_NAME'] : '';
    $stat = isset($order['ORDER_STATUS']) ? (string)$order['ORDER_STATUS'] : '';

    sqlsrv_close($conn);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Receipt #<?= $orderId ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { 
                background:#f6f7fb;
                background:url("bg/mainbg.jpg") no-repeat center center fixed;
                background-size: cover;
            }
            .receipt-card{
                max-width: 720px;
                margin: 20px auto;
                background:#fff;
                border: 3px solid #d4af37;
                border-radius: 18px;
                padding: 18px 18px 12px;
                box-shadow: 0 12px 28px rgba(15,23,42,.12);
            }
            .muted{ color:#64748b; }
            .dash{ border-top:1px dashed #cbd5e1; margin: 12px 0; }
            .logo{ width: 240px; height: auto; }
            @media print {
                .no-print{ display:none !important; }
                body { background:#fff; }
                .receipt-card{ box-shadow:none; margin:0; border:1px solid #111; }
            }
        </style>
    </head>
    <body>

    <div class="receipt-card">
        <div class="text-center">
            <img src="bg/LOGOS.png" class="logo" alt="Nectar & Ambrosia Logo">
            <div class="muted">Official Receipt</div>
        </div>

        <div class="dash"></div>

        <div class="row g-2">
            <div class="col-md-6">
                <div><strong>Order ID:</strong> <?= $orderId ?></div>
                <div><strong>Date:</strong> <?= htmlspecialchars($dtStr) ?></div>
                <?php if ($cust !== ''): ?>
                    <div><strong>Customer:</strong> <?= htmlspecialchars($cust) ?></div>
                <?php endif; ?>
                <?php if ($stat !== ''): ?>
                    <div><strong>Status:</strong> <?= htmlspecialchars($stat) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="muted">Nectar & Ambrosia Café</div>
                <div class="muted">Mythic brews & bites</div>
            </div>
        </div>

        <div class="dash"></div>

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Item</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="4" class="text-center muted py-3">No items found for this order.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $it): ?>
                        <?php
                            $nm  = (string)$it['PRODUCT_NAME'];
                            $qty = (int)$it['QUANTITY'];
                            $pr  = (float)$it['UNIT_PRICE'];
                            $st  = (float)$it['SUBTOTAL'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($nm) ?></td>
                            <td class="text-end"><?= number_format($qty) ?></td>
                            <td class="text-end"><?= number_format($pr, 2) ?></td>
                            <td class="text-end"><strong><?= number_format($st, 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="dash"></div>

        <div class="row">
            <div class="col-md-6 muted">
                <div>Thank you for your order!</div>
                <div style="font-size:13px;">Keep this receipt for reference.</div>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-between"><div class="muted">Subtotal</div><div><?= number_format($subtotal, 2) ?></div></div>
                <div class="d-flex justify-content-between"><div class="muted">VAT (12%)</div><div><?= number_format($vat, 2) ?></div></div>
                <div class="dash"></div>
                <div class="d-flex justify-content-between"><div><strong>Total</strong></div><div><strong><?= number_format($total, 2) ?></strong></div></div>
            </div>
        </div>

        <div class="no-print d-flex gap-2 justify-content-center mt-3 flex-wrap">
            <button class="btn btn-outline-primary" onclick="window.print()">Print Receipt</button>
            <a class="btn btn-secondary" href="receipts.php?<?= keepFilters($sd,$ed,$sort) ?>">Back to Orders</a>
            <a class="btn btn-outline-dark" href="reports.php?<?= keepFilters($sd,$ed,$sort) ?>">Back to Sales</a>
        </div>
    </div>

    </body>
    </html>
    <?php
    exit;
}

/* =========================
   ORDER LIST VIEW
   ========================= */
$listSql = "SELECT o.ORDER_ID, o.TOTAL, o.ORDER_DATE, o.CUSTOMER_NAME, o.ORDER_STATUS
            FROM [ORDER] o
            WHERE $where
            $orderBy";

$orders = [];
$rsList = sqlsrv_query($conn, $listSql);
if ($rsList) {
    while ($r = sqlsrv_fetch_array($rsList, SQLSRV_FETCH_ASSOC)) $orders[] = $r;
    sqlsrv_free_stmt($rsList);
}

sqlsrv_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt Orders</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: url("bg/orderbg4.png") no-repeat center 56px fixed; background-size: cover; }
        .shell{ max-width: 1300px; margin: 0 auto; padding: 100px 16px 30px; }
        .cardish{
            background: #ffffffd9;
            border: 3px solid #d4af37;
            border-radius: 18px;
            padding: 16px;
            box-shadow: 0 12px 28px rgba(15,23,42,.12);
        }
        header{
            position: fixed; top:0; left:0; right:0;
            background: linear-gradient(90deg,#4cabdc 0%,#e1ecff 40%,#9cd0ec 100%);
            border-bottom: 3px solid #d4af37;
            padding: 8px 14px;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(15,23,42,.25);
        }
        .top{
            max-width:1300px; margin:0 auto;
            display:flex; align-items:center; justify-content:space-between;
            padding: 0 10px;
        }
        .logo{ width: 220px; height:auto; }
        .btn-pill{
            border-radius: 999px;
            border: 1px solid #1f3b5c;
            background: #f9fafb;
            color: #1f3b5c;
            font-weight: 600;
            text-decoration: none;
            padding: 8px 14px;
            box-shadow: 0 3px 8px rgba(31,64,104,.25);
        }
        .btn-pill:hover{ background:#e5edf7; }
        .muted{ color:#64748b; }
        .brand-wrap { display: flex; justify-content: center; }
        .brand-logo { width: 260px; height: auto; display: block; }
    </style>
</head>
<body>

<header>
    <div class="top">
        <a class="btn-pill" href="reports.php?<?= keepFilters($sd,$ed,$sort) ?>">&#8592; Back to Sales</a>
        <div class="brand-wrap">
            <img src="bg/LOGOS.png" class="brand-logo" alt="Nectar & Ambrosia Logo">
        </div>
        <div style="width:180px;"></div>
    </div>
</header>

<div class="shell">
    <div class="cardish mb-3">
        <h4 class="mb-3">Receipt Orders</h4>

        <!-- Date + Sort Filters -->
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($sd) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($ed) ?>" max="<?= date('Y-m-d') ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Sort</label>
                <select name="sort" class="form-select">
                    <option value="old" <?= $sort === 'old' ? 'selected' : '' ?>>Oldest to Newest</option>
                    <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Newest to Oldest</option>
                </select>
            </div>

            <div class="col-md-3 d-grid">
                <button class="btn btn-primary">Apply</button>
            </div>
        </form>

        <div class="mt-2 muted">
            Showing orders from <strong><?= htmlspecialchars($sd) ?></strong> to <strong><?= htmlspecialchars($ed) ?></strong>
        </div>
    </div>

    <div class="cardish">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:95px;">Order ID</th>
                        <th style="width:200px;">Order Date</th>
                        <th>Customer</th>
                        <th style="width:140px;">Status</th>
                        <th class="text-end" style="width:140px;">Total (PHP)</th>
                        <th style="width:140px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No orders found for the selected date range.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <?php
                            $oid = (int)$o['ORDER_ID'];
                            $dt  = $o['ORDER_DATE'];
                            $dtStr = ($dt instanceof DateTime) ? $dt->format('Y-m-d H:i') : (string)$dt;

                            $cust = isset($o['CUSTOMER_NAME']) ? (string)$o['CUSTOMER_NAME'] : '';
                            $stat = isset($o['ORDER_STATUS']) ? (string)$o['ORDER_STATUS'] : '';
                            $tot  = isset($o['TOTAL']) ? (float)$o['TOTAL'] : 0;
                        ?>
                        <tr>
                            <td><strong><?= $oid ?></strong></td>
                            <td><?= htmlspecialchars($dtStr) ?></td>
                            <td><?= htmlspecialchars($cust) ?></td>
                            <td><?= htmlspecialchars($stat) ?></td>
                            <td class="text-end"><strong><?= number_format($tot, 2) ?></strong></td>
                            <td>
                                <a class="btn btn-warning btn-sm"
                                   href="receipts.php?order_id=<?= $oid ?>&<?= keepFilters($sd,$ed,$sort) ?>">
                                    View Receipt
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>

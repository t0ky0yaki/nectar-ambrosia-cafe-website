<?php
session_start();
date_default_timezone_set('Asia/Manila');
$serverName = "LAPTOP-5SE5KLER\\SQLEXPRESS";
$connectionOptions = [
    "Database" => "DLSU",
    "Uid"      => "",
    "PWD"      => ""
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
if (empty($cart)) {
    header("Location: direct.php");
    exit;
}
$customer_name = trim($_POST['customer_name'] ?? '');
if ($customer_name === '') {
    $customer_name = 'Walk-in Customer';
}

$payment_method = $_POST['payment'] ?? 'cash';
$discount_type= $_POST['discount_type'] ?? 'none';
$voucher_code= trim($_POST['voucher_code'] ?? '');
$pay_amount= (float) str_replace(',', '', $_POST['payment_amount'] ?? 0);
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += ((int)$item['qty']) * ((float)$item['price']);
}

$vat = $subtotal * 0.12;

$discountRate = 0;
if ($discount_type === 'pwd' || $discount_type === 'senior') {
    $discountRate = 0.20;
} elseif ($discount_type === 'student') {
    $discountRate = 0.10;
}

$discount = $subtotal * $discountRate;
$total= max(0, $subtotal + $vat - $discount);
$change= max(0, $pay_amount - $total);
$_SESSION['subtotal'] = $subtotal;
$_SESSION['vat']= $vat;
$_SESSION['discount']= $discount;
$_SESSION['total']= $total;
$_SESSION['payment_method']= $payment_method;
$_SESSION['discount_type'] = $discount_type;
$_SESSION['voucher_code'] = $voucher_code;
$_SESSION['payment_amount']= $pay_amount;
$_SESSION['change']= $change;
$order_status = 'PAID';

$sqlOrder = "INSERT INTO [ORDER] (TOTAL, ORDER_DATE, CUSTOMER_NAME, ORDER_STATUS) OUTPUT INSERTED.ORDER_ID VALUES ($total, GETDATE(), '$customer_name', '$order_status')";
$stmtOrder = sqlsrv_query($conn, $sqlOrder);
if ($stmtOrder === false || sqlsrv_fetch($stmtOrder) === false) {
    die(print_r(sqlsrv_errors(), true));
}

$order_id = sqlsrv_get_field($stmtOrder, 0);
foreach ($_SESSION['cart'] as $item) {

    $name  = $item['name'];           
    $qty   = (int) $item['qty'];        
    $price = (float) $item['price'];   
    $sub   = $qty * $price;

    $nameSafe = str_replace("'", "''", $name);

    $sqlItem = "
        INSERT INTO ORDER_ITEMS
            (ORDER_ID, PRODUCT_NAME, QUANTITY, UNIT_PRICE, SUBTOTAL)
        VALUES
            ($order_id, '$nameSafe', $qty, $price, $sub)
    ";

    if (sqlsrv_query($conn, $sqlItem) === false) {
        die(print_r(sqlsrv_errors(), true));
    }
}
unset($_SESSION['cart']);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Nectar &amp; Ambrosia – Receipt</title>
    <style>
        @font-face { font-family: "freak"; src: url("fonts/freak.ttf") format("truetype"); }
        @font-face { font-family: "AUGUSTUS"; src: url("fonts/AUGUSTUS.ttf") format("truetype"); }

        body {
            font-family: "AUGUSTUS", serif;
            background: #f3f1f5;
            margin: 0;
            padding: 30px 10px;
            background: url("bg/mainbg.jpg") no-repeat center center fixed;
            background-size: cover;
        }
        .receipt-wrapper { max-width: 700px; margin: 0 auto; }
        .receipt-card {
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 12px 28px rgba(0,0,0,0.15);
            padding: 24px 28px;
        }
        .order-meta {
            font-size: 13px;
            color: #444;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 6px;
        }
        .line { border-top: 1px dashed #ccc; margin: 10px 0 18px; }

        table.receipt-table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 14px; 
        }
        table.receipt-table thead { 
            background: #f5f0ff; 
        }
        table.receipt-table th, table.receipt-table td { 
            padding: 8px 10px; 
            text-align: left; 
        }
        table.receipt-table th { 
            font-weight: 600; 
            border-bottom: 1px solid #ded7f0; 
        }
        table.receipt-table tbody tr:nth-child(even) { 
            background: #faf7ff; 
        }
        table.receipt-table tfoot td { 
            padding-top: 6px; 
            font-weight: 600; 
            border-top: 1px solid #ddd; 
        }

        .right { 
            text-align: right; 
        }
        .total-label {
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
            color: #555;
            font-family: "AUGUSTUS", serif;
        }
        .total-amount { 
            font-size: 18px; 
            font-family: "AUGUSTUS", serif; 
        }

        .footer-note {
            margin-top: 18px;
            font-size: 12px;
            text-align: center;
            color: #777;
            font-family: "AUGUSTUS", serif;
        }
        .btn-row {
            margin-top: 20px;
            text-align: center;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 8px 18px;
            border-radius: 999px;
            border: none;
            background: #7c4bff;
            color: #fff;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn.secondary { 
            background: #ddd; 
            color: #333; 
        }
        .brand-logo {
            width: 320px;
            margin-bottom: 10px;
            margin-top: -10px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
    </style>
</head>
<body>
<div class="receipt-wrapper">
    <div class="receipt-card">
        <img src="bg/LOGOS.png" class="brand-logo" alt="Nectar & Ambrosia Logo">

        <div class="order-meta">
            <span><strong>Order #:</strong> <?= htmlspecialchars($order_id) ?></span>
            <span><strong>Customer:</strong> <?= htmlspecialchars($customer_name) ?></span>
            <span><strong>Status:</strong> <?= htmlspecialchars($order_status) ?></span>
            <span><strong>Payment Method:</strong> <?= htmlspecialchars(ucfirst($payment_method)) ?></span>
            <span><strong>Date:</strong> <?= date('Y-m-d H:i') ?></span>
        </div>

        <div class="line"></div>

        <table class="receipt-table">
            <thead>
            <tr>
                <th>Item</th>
                <th class="right">Qty</th>
                <th class="right">Price</th>
                <th class="right">Subtotal</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($cart as $item): ?>
                <?php
                    $qty   = isset($item['qty']) ? (int) $item['qty'] : 0;
                    $price = isset($item['price']) ? (float) $item['price'] : 0.0;
                    $sub   = $qty * $price;
                ?>
                <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td class="right"><?= $qty ?></td>
                    <td class="right"><?= number_format($price, 2) ?></td>
                    <td class="right"><?= number_format($sub, 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr>
                <td colspan="3" class="right total-label">Subtotal</td>
                <td class="right">₱<?= number_format($subtotal, 2) ?></td>
            </tr>
            <tr>
                <td colspan="3" class="right total-label">
                    Discount<?= ($discountRate > 0 ? " (" . ($discountRate * 100) . "%)" : "") ?>
                </td>
                <td class="right">-₱<?= number_format($discount, 2) ?></td>
            </tr>
            <tr>
                <td colspan="3" class="right total-label">VAT (12%)</td>
                <td class="right">₱<?= number_format($vat, 2) ?></td>
            </tr>
            <tr>
                <td colspan="3" class="right total-label">Grand Total</td>
                <td class="right total-amount">₱<?= number_format($total, 2) ?></td>
            </tr>
            <tr>
                <td colspan="3" class="right total-label">Payment Amount</td>
                <td class="right">₱<?= number_format($pay_amount, 2) ?></td>
            </tr>
            <tr>
                <td colspan="3" class="right total-label">Change</td>
                <td class="right">₱<?= number_format($change, 2) ?></td>
            </tr>
            </tfoot>
        </table>

        <div class="footer-note">
            Thank you for sipping with us. May your day be as legendary as the gods.
        </div>

        <div class="btn-row">
            <button class="btn" onclick="window.print()">Print Receipt</button>
            <a href="order.php" class="btn secondary">Back to Menu</a>
        </div>
    </div>
</div>
</body>
</html>

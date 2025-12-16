<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name  = isset($_POST['name'])  ? trim($_POST['name'])  : '';
    $size  = isset($_POST['size'])  ? trim($_POST['size'])  : '';
    $qty   = isset($_POST['qty'])   ? (int)$_POST['qty']    : 1;
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;

    if ($qty < 1) {
        $qty = 1;
    }

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $found = false;

    foreach ($_SESSION['cart'] as $i => $item) {

        $iname  = $item['name']  ?? '';
        $isize  = $item['size']  ?? '';
        $iprice = isset($item['price']) ? (float)$item['price'] : 0;

        if ($iname === $name && $isize === $size && $iprice == $price) {
            $oldQty = isset($_SESSION['cart'][$i]['qty']) ? (int)$_SESSION['cart'][$i]['qty'] : 0;
            $_SESSION['cart'][$i]['qty'] = $oldQty + $qty;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $_SESSION['cart'][] = [
            'name'  => $name,
            'size'  => $size,
            'qty'   => $qty,
            'price' => $price
        ];
    }
}

$back = $_SERVER['HTTP_REFERER'] ?? 'order.php';
header("Location: " . $back);
exit;

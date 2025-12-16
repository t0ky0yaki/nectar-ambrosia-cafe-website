<?php
session_start();
function post($k, $d = null){ return isset($_POST[$k]) ? $_POST[$k] : $d; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // clear cart
    if (isset($_POST['clear_cart'])) {
        unset($_SESSION['cart']);
        header("Location: cart.php");
        exit;
    }
    if (isset($_POST['update_qty'])) {
        $idx = (int) post('idx', -1);
        $delta = (int) post('delta', 0);

        if (isset($_SESSION['cart'][$idx])) {
            $cur = (int) ($_SESSION['cart'][$idx]['qty'] ?? 0);
            $new = $cur + $delta;

            if ($new <= 0) {
                unset($_SESSION['cart'][$idx]);
                $_SESSION['cart'] = array_values($_SESSION['cart']); 
            } else {
                $_SESSION['cart'][$idx]['qty'] = $new;
            }
        }

        header("Location: cart.php");
        exit;
    }
}

$cart = $_SESSION['cart'] ?? [];

$subtotal = 0;
foreach ($cart as $c) {
    $price = (float)($c['price'] ?? 0);
    $qty   = (int)($c['qty'] ?? 0);
    $subtotal += $price * $qty;
}
$vat = $subtotal * 0.12;

// store for billing page if you need them
$_SESSION['subtotal'] = $subtotal;
$_SESSION['vat']      = $vat;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cart – Nectar & Ambrosia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
    body{
        margin:0; min-height:100vh;
        font-family: Arial, sans-serif;
        background: url("bg/orderbg4.png") no-repeat center 70px fixed;
        background-size: cover;
    }
    header{
        position: sticky; top:0; z-index: 1000;
        padding: 10px 18px;
        background: linear-gradient(90deg,#4cabdc 0%,#e1ecff 40%,#9cd0ec 100%);
        border-bottom: 3px solid #d4af37;
        box-shadow: 0 4px 12px rgba(15,23,42,.28);
    }
.wrap {
    max-width: 1100px;
    margin: 18px auto 28px;
    padding: 18px;

    background: #fff;
    border: 3px solid #d4af37;
    border-radius: 18px;
    box-shadow: 0 12px 30px rgba(167, 199, 231, 0.55);
}

.grid {
    display: grid;
    grid-template-columns: 60% 40%;
    gap: 16px;
}
.box, .side {
    border-radius: 14px;
    padding: 14px;
}

.box {
    background: #f9fbff;
    border: 1px solid #dde4f0ff;
    box-shadow: 0 10px 24px #94a3b859;
    font-family: "AUGUSTUS", serif;
}
    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 10px 16px;
        border-radius: 999px;
        border: 1px solid #1f3b5c;
        background: #f9fafb;
        color: #1f3b5c;
        font-size: 16px;
        font-weight: 600;
        text-decoration: none;
        box-shadow: 0 3px 8px #1f406840;
        transition: 0.15s;
    }

.side {
    background: #fff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 9px 22px #94a3b84d;
    font-family: "AUGUSTUS", serif;
    font-size: 14px;
}
.brand-logo {
    width: 280px;
    display: block;
    margin: 0 auto;
}
.qty-wrap {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.qty-btn {
    width: 28px;
    height: 28px;

    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #fff;

    font-weight: 800;
    cursor: pointer;
}
.qty-btn:hover {
    background: #f1f5f9;
}
.qty-btn:active {
    transform: scale(0.95);
}

.qty-num {
    min-width: 22px;
    text-align: center;
    font-weight: 700;
}
@media (max-width: 900px) {
    .grid {
        grid-template-columns: 1fr;
    }

    .brand-logo {
        width: 260px;
    }
}
</style>
</head>
<body>

<header>
    <div class="d-flex align-items-center justify-content-between" style="max-width:1200px;margin:0 auto;">
        <a href="order.php" class="back-btn">← Back to Menu</a>
        <img src="bg/LOGOS.png" class="brand-logo" alt="Nectar & Ambrosia Logo">
        <div style="width:120px;"></div>
    </div>
</header>

<div class="wrap">
    <div class="grid">

        <!-- LEFT: CART ITEMS -->
        <div class="box">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h5 class="mb-1">Items in Your Cart</h5>
                    <div class="text-muted" style="font-size:12px;">Review items, then confirm payment and discounts.</div>
                </div>

                <?php if (!empty($cart)): ?>
                    <form method="post">
                        <input type="hidden" name="clear_cart" value="1">
                        <button class="btn btn-sm btn-outline-danger">Clear Cart</button>
                    </form>
                <?php endif; ?>
            </div>

            <hr>

            <?php if (empty($cart)): ?>
                <div class="text-muted">Your cart is currently empty.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-primary">
                            <tr>
                                <th style="width:40%;">Item</th>
                                <th style="width:12%;">Size</th>
                                <th style="width:18%;">Qty</th>
                                <th style="width:15%;">Price</th>
                                <th style="width:15%;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cart as $i => $c): 
                            $name  = $c['name'] ?? '';
                            $size  = $c['size'] ?? '';
                            $qty   = (int)($c['qty'] ?? 0);
                            $price = (float)($c['price'] ?? 0);
                            $line  = $price * $qty;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($name) ?></td>
                                <td><?= htmlspecialchars($size) ?></td>
                                <td>
                                    <div class="qty-wrap">
                                        <form method="post" class="m-0">
                                            <input type="hidden" name="update_qty" value="1">
                                            <input type="hidden" name="idx" value="<?= (int)$i ?>">
                                            <button class="qty-btn qty-minus" type="submit" name="delta" value="-1" data-qty="<?= $qty ?>">−</button>
                                        </form>

                                        <span class="qty-num"><?= $qty ?></span>

                                        <form method="post" class="m-0">
                                            <input type="hidden" name="update_qty" value="1">
                                            <input type="hidden" name="idx" value="<?= (int)$i ?>">
                                            <button class="qty-btn" type="submit" name="delta" value="1">+</button>
                                        </form>
                                    </div>
                                </td>
                                <td>₱<?= number_format($price, 2) ?></td>
                                <td>₱<?= number_format($line, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: CHECKOUT -->
        <form class="d-flex flex-column gap-3" action="billing.php" method="post" id="checkoutForm">

            <div class="side">
                <h6 class="mb-1">Payment Method</h6>
                <div class="text-muted" style="font-size:12px;">Select how the customer will pay.</div>

                <div class="mt-2">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment" id="pay_cash" value="cash" checked>
                        <label class="form-check-label" for="pay_cash">Cash</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment" id="pay_gcash" value="gcash">
                        <label class="form-check-label" for="pay_gcash">GCash / E-Wallet</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment" id="pay_card" value="card">
                        <label class="form-check-label" for="pay_card">Credit / Debit Card</label>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label mb-1" style="font-size:12px;">Enter Payment Amount (₱)</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                           name="payment_amount" id="payment_amount" placeholder="0.00" required>
                    <div class="mt-2" style="font-size:12px;">
                        <div class="d-flex justify-content-between">
                            <span>Change (preview)</span>
                            <span id="changePreview">₱0.00</span>
                        </div>
                        <div id="payWarn" class="text-warning" style="display:none;margin-top:4px;">
                            Payment is less than total.
                        </div>
                    </div>
                </div>
            </div>

            <div class="side">
                <h6 class="mb-1">Discounts</h6>
                <div class="text-muted" style="font-size:12px;">PWD/Senior 20%, Student 10%.</div>

                <div class="mt-2">
                    <div class="form-check">
                        <input class="form-check-input disc" type="radio" name="discount_type" id="disc_none" value="none" checked>
                        <label class="form-check-label" for="disc_none">No Discount</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input disc" type="radio" name="discount_type" id="disc_pwd" value="pwd">
                        <label class="form-check-label" for="disc_pwd">PWD (20%)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input disc" type="radio" name="discount_type" id="disc_senior" value="senior">
                        <label class="form-check-label" for="disc_senior">Senior (20%)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input disc" type="radio" name="discount_type" id="disc_student" value="student">
                        <label class="form-check-label" for="disc_student">Student (10%)</label>
                    </div>
                </div>
            </div>

            <div class="side">
                <h6 class="mb-1">Voucher / Promo Code</h6>
                <div class="text-muted" style="font-size:12px;">Optional promo codes.</div>

                <div class="input-group input-group-sm mt-2">
                    <input type="text" class="form-control" placeholder="Enter code" name="voucher_code">
                    <button class="btn btn-outline-primary" type="button">Apply</button>
                </div>
            </div>

            <div class="side">
                <h6 class="mb-1">Order Summary</h6>
                <div class="text-muted" style="font-size:12px;">12% VAT is applied.</div>

                <div class="mt-2 mb-2">
                    <label class="form-label mb-1" style="font-size:12px;">Customer Name (optional)</label>
                    <input type="text" name="customer_name" class="form-control form-control-sm" placeholder="Walk-in Customer">
                </div>

                <div style="font-size:13px;">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Subtotal</span><b id="subAmount">₱<?= number_format($subtotal, 2) ?></b>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Discount</span><b id="discAmount">₱0.00</b>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>VAT</span><b id="vatAmount">₱<?= number_format($vat, 2) ?></b>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between">
                        <span>Total</span><b id="totalAmount">₱<?= number_format($subtotal + $vat, 2) ?></b>
                    </div>
                </div>

                <!-- punta lahat eto sa billing.php-->
                <input type="hidden" name="final_subtotal" id="final_subtotal" value="<?= number_format($subtotal, 2, '.', '') ?>">
                <input type="hidden" name="final_vat" id="final_vat" value="<?= number_format($vat, 2, '.', '') ?>">
                <input type="hidden" name="final_discount" id="final_discount" value="0.00">
                <input type="hidden" name="final_total" id="final_total" value="<?= number_format($subtotal + $vat, 2, '.', '') ?>">
                <input type="hidden" name="change_due" id="change_due" value="0.00">

                <button class="btn btn-primary w-100 rounded-pill mt-3" type="submit">Confirm Order</button>
            </div>

        </form>

    </div>
</div>

<script>
let baseSubtotal = <?= json_encode((float)$subtotal) ?>;
let baseVat      = <?= json_encode((float)$vat) ?>;
let currentTotal = baseSubtotal + baseVat;

function getDiscRate(){
    if (document.getElementById('disc_pwd').checked) return 0.20;
    if (document.getElementById('disc_senior').checked) return 0.20;
    if (document.getElementById('disc_student').checked) return 0.10;
    return 0;
}

function updateSummary(){
    let rate = getDiscRate();
    let disc = baseSubtotal * rate;
    currentTotal = baseSubtotal + baseVat - disc;

    document.getElementById('discAmount').innerText  = "₱" + disc.toFixed(2);
    document.getElementById('totalAmount').innerText = "₱" + currentTotal.toFixed(2);

    document.getElementById('final_subtotal').value = baseSubtotal.toFixed(2);
    document.getElementById('final_vat').value      = baseVat.toFixed(2);
    document.getElementById('final_discount').value = disc.toFixed(2);
    document.getElementById('final_total').value    = currentTotal.toFixed(2);

    updateChange();
}

function updateChange(){
    let paid = parseFloat(document.getElementById('payment_amount').value);
    if (isNaN(paid)) paid = 0;

    let change = paid - currentTotal;

    if (change < 0) {
        document.getElementById('payWarn').style.display = "block";
        document.getElementById('changePreview').innerText = "₱0.00";
        document.getElementById('change_due').value = "0.00";
    } else {
        document.getElementById('payWarn').style.display = "none";
        document.getElementById('changePreview').innerText = "₱" + change.toFixed(2);
        document.getElementById('change_due').value = change.toFixed(2);
    }
}


document.querySelectorAll('.disc').forEach(r => r.addEventListener('change', updateSummary));
document.getElementById('payment_amount').addEventListener('input', updateChange);

//pag nagzero ang quantity, magtatanong tas confirm
document.querySelectorAll('.qty-minus').forEach(btn => {
    btn.addEventListener('click', function(e){
        let q = parseInt(this.dataset.qty || "0", 10);
        if (q <= 1 && !confirm("Quantity will become 0. Remove this item?")) {
            e.preventDefault();
        }
    });
});

updateSummary();
</script>

</body>
</html>

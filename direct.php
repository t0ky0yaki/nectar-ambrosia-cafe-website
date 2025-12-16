<?php


session_start();

/* Handle logout via URL flag */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header("Location: home.html");
    exit();
}

// wlang access pag wlang role
if (!isset($_SESSION['user']) || !isset($_SESSION['role'])) {
    header("Location: login.html");
    exit();
}

//get user info from sessioon
$user = $_SESSION['user'];
$role = $_SESSION['role'];
$role_lower = strtolower($role);

//only admin can access. all of this is just for security measures kasi nagkaaccess ang cashier once dito.
if ($role_lower !== "admin") {
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Direct Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
@font-face{
    font-family:"AUGUSTUS";
    src:url("fonts/AUGUSTUS.ttf") format("truetype");
}
@keyframes fadeUp{
    from{opacity:0;transform:translateY(18px);}
    to{opacity:1;transform:translateY(0);}
}
body{
    margin:0;
    font-family:"AUGUSTUS",serif;
    background:url("bg/mainbg.jpg") no-repeat center center fixed;
    background-size:cover;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}

.container-main{
    width:100%;
    max-width:960px;
    background:#ffffffd1;
    border:3px solid #d4af37;
    box-shadow:0 0 26px #a7c7e773;
    border-radius:26px;
    padding:22px 26px 26px;
    margin:28px;
}

.topbar{
    display:flex;
    justify-content:center;
    align-items:center;
    margin-bottom:14px;
}

.brand-logo{
    width:340px;
    max-width:80vw;
    height:auto;
    filter:
        drop-shadow(0 14px 26px #0f172a2e)
        drop-shadow(0 0 18px #4cabdc59);
}
.welcome{
    margin:6px 0 18px;
    padding:18px 20px;
    border-radius:18px;
    border:1px solid rgba(212,175,55,0.55);
    background:linear-gradient(
        90deg,
        #e1ecffc7 0%,
        #ffffffd1 45%,
        #fde68a52 100%
    );

    box-shadow:0 12px 24px #0f172a14;
}

.welcome h2{
    margin:0;
    font-size:22px;
    color:#2f5f9a;
    font-weight:900;
}

.welcome p{
    margin-top:6px;
    font-size:13px;
    color:#475569;
}
.panel{
    border-radius:22px;
    border:2px solid rgba(212,175,55,0.72);
    background:rgba(255,255,255,0.68);
    box-shadow:0 16px 30px rgba(15,23,42,0.10);
    padding:16px;
}
.grid{
    display:grid;
    grid-template-columns:repeat(2, 1fr);
    gap:18px;
}
.card-link{
    text-decoration:none;
    color:inherit;
}

.card{
    height:132px;
    border-radius:22px;
    border:3px solid rgba(212,175,55,0.90);
    background:
        radial-gradient(circle at 18% 25%, #4cacdc73, transparent 55%),
        radial-gradient(circle at 85% 75%, #d4af3559, transparent 60%),
        linear-gradient(145deg, #ffffff, #dbeafe);
    box-shadow:
        0 16px 32px #0f172a2e,
        inset 0 0 0 1px #ffffff99;

    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:20px 26px;
    transition:0.16s;
}
.card:hover{
    transform:translateY(-6px) scale(1.01);
    box-shadow:
        0 22px 44px rgba(15,23,42,0.22),
        0 0 22px rgba(76,171,220,0.35);
}
.card-left{
    display:flex;
    align-items:center;
    gap:20px;
}
.icon-badge{
    width:60px;
    height:60px;
    border-radius:18px;
    background:linear-gradient(135deg, #4cabdc, #e1ecff);
    border:2px solid #d4af37;
    display:flex;
    align-items:center;
    justify-content:center;
}

.icon-badge i{
    font-size:24px;
    color:#0f172a;
}
.card-title{
    font-size:16px;
    font-weight:900;
    color:#1f3b5c;
}

.card-sub{
    font-size:12px;
    color:#64748b;
}
.chev{
    font-size:20px;
    color:rgba(31,59,92,0.70);
}
.danger{
    border-color:rgba(220,53,69,0.55);
    background:
        radial-gradient(circle at 22% 28%, #2c060a24, transparent 60%),
        linear-gradient(145deg, #ffffff, #fee2e2);
}

.danger .card-title,
.danger .icon-badge i{
    color:#7f1d1d;
}
@media (max-width:760px){
    .grid{grid-template-columns:1fr;}
    .brand-logo{width:190px;}
}
</style>
</head>

<body>
<div class="container-main">
    <div class="topbar">
        <img src="bg/LOGOS.png" class="brand-logo" alt="Nectar & Ambrosia Logo">
    </div>
    <div class="welcome">
        <h2>Welcome back, <?= htmlspecialchars($user) ?>.</h2>
        <p>Choose what you want to manage today.</p>
    </div>
    <div class="panel">
        <div class="grid">
            <a href="order.php" class="card-link">
                <div class="card">
                    <div class="card-left">
                        <div class="icon-badge"><i class="fa-solid fa-bag-shopping"></i></div>
                        <div>
                            <div class="card-title">Take an Order</div>
                            <div class="card-sub">Start a new transaction</div>
                        </div>
                    </div>
                    <div class="chev">&#8250;</div>
                </div>
            </a>
            <a href="reports.php" class="card-link">
                <div class="card">
                    <div class="card-left">
                        <div class="icon-badge"><i class="fa-solid fa-chart-column"></i></div>
                        <div>
                            <div class="card-title">View Reports</div>
                            <div class="card-sub">Sales and summaries</div>
                        </div>
                    </div>
                    <div class="chev">&#8250;</div>
                </div>
            </a>
            <a href="update.php" class="card-link">
                <div class="card">
                    <div class="card-left">
                        <div class="icon-badge"><i class="fa-solid fa-pen-to-square"></i></div>
                        <div>
                            <div class="card-title">Update Menu</div>
                            <div class="card-sub">Items, prices, images</div>
                        </div>
                    </div>
                    <div class="chev">&#8250;</div>
                </div>
            </a>
            <a href="home.html?logout=1" class="card-link">
                <div class="card danger">
                    <div class="card-left">
                        <div class="icon-badge"><i class="fa-solid fa-right-from-bracket"></i></div>
                        <div>
                            <div class="card-title">Logout</div>
                            <div class="card-sub">End this session</div>
                        </div>
                    </div>
                    <div class="chev">&#8250;</div>
                </div>
            </a>

        </div>
    </div>
</div>
</body>
</html>

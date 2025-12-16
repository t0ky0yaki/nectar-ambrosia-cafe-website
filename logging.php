<?php
session_start();

$serverName = "LAPTOP-5SE5KLER\\SQLEXPRESS";
$connectionOptions = [
    "Database" => "DLSU",
    "Uid" => "",
    "PWD" => ""
];

$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

$user = isset($_POST['username']) ? trim($_POST['username']) : '';
$pass = isset($_POST['password']) ? $_POST['password'] : '';

$role = isset($_POST['role']) ? strtolower(trim($_POST['role'])) : 'admin';

$sql = "SELECT USERID, USERNAME, PASSWORD FROM LOGS WHERE USERNAME = ?";
$params = [$user];
$result = sqlsrv_query($conn, $sql, $params);

if ($result === false) {
    die(print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);

if ($row == null) {
    sqlsrv_close($conn);
    header("Location: login.html?role=$role&error=nouser");
    exit();
}
$dbpass = $row['PASSWORD'];

if ($pass !== $dbpass) {
    sqlsrv_close($conn);
    header("Location: login.html?role=$role&error=wrongpass");
    exit();
}

if (strtolower($user) === "percy") {
    $dbrole = "admin";
} else {
    $dbrole = "cashier";
}

if ($dbrole !== $role) {
    sqlsrv_close($conn);
    header("Location: login.html?role=$role&error=wrongportal");
    exit();
}
$_SESSION['user'] = $row['USERNAME'];
$_SESSION['role'] = $dbrole;

sqlsrv_close($conn);

if ($dbrole === 'cashier') {
    header("Location: order.php");
} else {
    header("Location: direct.php");
}
exit();

?>

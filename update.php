<?php
$serverName = "LAPTOP-5SE5KLER\\SQLEXPRESS";
$connectionOptions = [
    "Database" => "DLSU",
    "Uid"      => "",
    "PWD"      => ""
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) die(print_r(sqlsrv_errors(), true));

date_default_timezone_set('Asia/Manila');

$uploadWebPath  = "uploads/";
$uploadDiskPath = __DIR__ . "/uploads/";

$allowedExt = ['jpg','jpeg','png','gif','webp'];

function post($k, $d='') { return isset($_POST[$k]) ? $_POST[$k] : $d; }
function get($k, $d='')  { return isset($_GET[$k])  ? $_GET[$k]  : $d; }
function escSql($s)      { return str_replace("'", "''", $s); }

function numOrNull($k){
    if (!isset($_POST[$k]) || $_POST[$k] === '') return null;
    return (float)$_POST[$k];
}

function uploadImage($field, $uploadDiskPath, $uploadWebPath, $allowedExt, $prefix=''){
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;

    $orig = basename($_FILES[$field]['name']);
    $tmp  = $_FILES[$field]['tmp_name'];
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) return null;
    $newName = time() . ($prefix ? "_{$prefix}_" : "_") . $orig;
    $disk    = $uploadDiskPath . $newName;
    $web     = $uploadWebPath  . $newName;

    return move_uploaded_file($tmp, $disk) ? $web : null;
}

function getCategoryCode($conn, $catId){
    if ($catId === null) return null;
    $sql = "SELECT CATEGORY_CODE FROM CATEGORY WHERE CATEGORY_ID = $catId";
    $res = sqlsrv_query($conn, $sql);
    if (!$res) return null;

    $row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($res);

    return $row ? $row['CATEGORY_CODE'] : null;
}

function buildRedirect($mode){
    $redir = "update.php?mode=" . $mode;
    if ($mode !== 'update') return $redir;

    $keep = [];
    if (isset($_GET['q']) && $_GET['q'] !== '')    $keep[] = "q=" . urlencode($_GET['q']);
    if (isset($_GET['cat']) && $_GET['cat'] !== '') $keep[] = "cat=" . urlencode($_GET['cat']);
    if (isset($_GET['sort']) && $_GET['sort'] !== '') $keep[] = "sort=" . urlencode($_GET['sort']);

    if ($keep) $redir .= "&" . implode("&", $keep);
    return $redir;
}

//here you choose if you want to add or just update menu
$mode = get('mode', 'add');
if ($mode !== 'add' && $mode !== 'update') $mode = 'add';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act   = post('action', '');
    $id    = (int)post('MENU_ID', 0);
    $name  = post('MENU_NAME', '');
    $desc  = post('DESCRIPTION', '');
    $catId = (post('CATEGORY_ID','') !== '') ? (int)post('CATEGORY_ID') : null;
    $pS = numOrNull('PRICE_S');
    $pM = numOrNull('PRICE_M');
    $pL = numOrNull('PRICE_L');
    $catCode = getCategoryCode($conn, $catId);
    $img = post('CURRENT_IMAGE', '');

    // para sa lattes lang toh
    if ($catCode === 'lattes') {

        $imgHot  = post('CURRENT_IMAGE_HOT', '');
        $imgCold = post('CURRENT_IMAGE_COLD', '');

        $upHot  = uploadImage('IMAGE_HOT',  $uploadDiskPath, $uploadWebPath, $allowedExt, 'hot');
        $upCold = uploadImage('IMAGE_COLD', $uploadDiskPath, $uploadWebPath, $allowedExt, 'cold');

        if ($upHot)  $imgHot  = $upHot;
        if ($upCold) $imgCold = $upCold;

        if ($imgHot !== '' && $imgCold !== '') $img = $imgHot . '|' . $imgCold;
        elseif ($imgHot !== '')                $img = $imgHot;
        elseif ($imgCold !== '')               $img = $imgCold;
        else                                   $img = '';

    //specifically para sa atlas lang toh
    } elseif ($id === 31 && $act === 'edit') {

        $tap  = post('CURRENT_IMAGE_TAPSILOG', '');
        $toc  = post('CURRENT_IMAGE_TOCILOG', '');
        $hot  = post('CURRENT_IMAGE_HOTSILOG', '');
        $long = post('CURRENT_IMAGE_LONGSILOG', '');

        $upTap  = uploadImage('IMAGE_TAPSILOG',  $uploadDiskPath, $uploadWebPath, $allowedExt, 'tapsilog');
        $upToc  = uploadImage('IMAGE_TOCILOG',   $uploadDiskPath, $uploadWebPath, $allowedExt, 'tocilog');
        $upHot  = uploadImage('IMAGE_HOTSILOG',  $uploadDiskPath, $uploadWebPath, $allowedExt, 'hotsilog');
        $upLong = uploadImage('IMAGE_LONGSILOG', $uploadDiskPath, $uploadWebPath, $allowedExt, 'longsilog');

        if ($upTap)  $tap  = $upTap;
        if ($upToc)  $toc  = $upToc;
        if ($upHot)  $hot  = $upHot;
        if ($upLong) $long = $upLong;

        $img = $tap . '|' . $toc . '|' . $hot . '|' . $long;

    // normal single image
    } else {
        $up = uploadImage('IMAGE_FILE', $uploadDiskPath, $uploadWebPath, $allowedExt, '');
        if ($up) $img = $up;
    }

    $nameSql = escSql($name);
    $descSql = escSql($desc);
    $imgSql  = escSql($img);

    $catIdVal = ($catId === null) ? "NULL" : $catId;
    $pSVal    = ($pS === null) ? "NULL" : $pS;
    $pMVal    = ($pM === null) ? "NULL" : $pM;
    $pLVal    = ($pL === null) ? "NULL" : $pL;

    if ($act === 'add') {
        $sql = "INSERT INTO MENU
                (MENU_NAME, CATEGORY_ID, PRICE_S, PRICE_M, PRICE_L, DESCRIPTION, IMAGE, DATE_CREATED, DATE_UPDATED)
                VALUES ('$nameSql', $catIdVal, $pSVal, $pMVal, $pLVal, '$descSql', '$imgSql', GETDATE(), GETDATE())";
        $q = sqlsrv_query($conn, $sql);
        if (!$q) die(print_r(sqlsrv_errors(), true));
    }

    if ($act === 'edit') {
        $sql = "UPDATE MENU
                SET MENU_NAME = '$nameSql',
                    CATEGORY_ID = $catIdVal,
                    PRICE_S = $pSVal,
                    PRICE_M = $pMVal,
                    PRICE_L = $pLVal,
                    DESCRIPTION = '$descSql',
                    IMAGE = '$imgSql',
                    DATE_UPDATED = GETDATE()
                WHERE MENU_ID = $id";
        $q = sqlsrv_query($conn, $sql);
        if (!$q) die(print_r(sqlsrv_errors(), true));
    }

    if ($act === 'delete') {
        $sql = "DELETE FROM MENU WHERE MENU_ID = $id";
        $q = sqlsrv_query($conn, $sql);
        if (!$q) die(print_r(sqlsrv_errors(), true));
    }

    header("Location: " . buildRedirect($mode));
    exit;
}
$categories = [];
$catRes = sqlsrv_query($conn, "SELECT CATEGORY_ID, CATEGORY_NAME FROM CATEGORY ORDER BY CATEGORY_NAME");
if ($catRes) {
    while ($r = sqlsrv_fetch_array($catRes, SQLSRV_FETCH_ASSOC)) $categories[] = $r;
    sqlsrv_free_stmt($catRes);
}

$editItem = null;
if (get('edit_id','') !== '') {
    $editId = (int)get('edit_id');
    $eSql = "SELECT m.*, c.CATEGORY_CODE
             FROM MENU m
             LEFT JOIN CATEGORY c ON m.CATEGORY_ID = c.CATEGORY_ID
             WHERE m.MENU_ID = $editId";
    $eRes = sqlsrv_query($conn, $eSql);
    if ($eRes) {
        $row = sqlsrv_fetch_array($eRes, SQLSRV_FETCH_ASSOC);
        if ($row) { $editItem = $row; $mode = 'update'; }
        sqlsrv_free_stmt($eRes);
    }
}

$qText     = trim(get('q',''));
$catFilter = trim(get('cat',''));
$sort      = trim(get('sort',''));

$where = [];
if ($qText !== '') {
    $qEsc = escSql($qText);
    $where[] = "(m.MENU_NAME LIKE '%$qEsc%' OR m.DESCRIPTION LIKE '%$qEsc%')";
}
if ($catFilter !== '') {
    $catIdF = (int)$catFilter;
    $where[] = "(m.CATEGORY_ID = $catIdF)";
}
$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$orderSql = "ORDER BY c.CATEGORY_NAME, m.MENU_NAME";
if     ($sort === 'az')    $orderSql = "ORDER BY m.MENU_NAME";
elseif ($sort === 'id')    $orderSql = "ORDER BY m.MENU_ID";
elseif ($sort === 'plow')  $orderSql = "ORDER BY MIN_PRICE, m.MENU_NAME";
elseif ($sort === 'phigh') $orderSql = "ORDER BY MIN_PRICE DESC, m.MENU_NAME";

$suggestions = [];
$sugSql = "SELECT m.MENU_ID, m.MENU_NAME, c.CATEGORY_NAME
           FROM MENU m
           LEFT JOIN CATEGORY c ON m.CATEGORY_ID = c.CATEGORY_ID
           ORDER BY m.MENU_NAME";
$sugRes = sqlsrv_query($conn, $sugSql);
if ($sugRes) {
    while ($r = sqlsrv_fetch_array($sugRes, SQLSRV_FETCH_ASSOC)) {
        $suggestions[] = [
            "id"  => (int)$r["MENU_ID"],
            "nm"  => (string)$r["MENU_NAME"],
            "cat" => (string)$r["CATEGORY_NAME"]
        ];
    }
    sqlsrv_free_stmt($sugRes);
}

$menuItems = [];
$listSql = "SELECT
                m.MENU_ID,
                m.MENU_NAME,
                m.PRICE_S,
                m.PRICE_M,
                m.PRICE_L,
                m.DESCRIPTION,
                m.IMAGE,
                m.CATEGORY_ID,
                c.CATEGORY_NAME,
                (SELECT MIN(v) FROM (VALUES (m.PRICE_S),(m.PRICE_M),(m.PRICE_L)) AS p(v) WHERE v IS NOT NULL) AS MIN_PRICE
            FROM MENU m
            LEFT JOIN CATEGORY c ON m.CATEGORY_ID = c.CATEGORY_ID
            $whereSql
            $orderSql";
$listRes = sqlsrv_query($conn, $listSql);
if ($listRes) {
    while ($r = sqlsrv_fetch_array($listRes, SQLSRV_FETCH_ASSOC)) $menuItems[] = $r;
    sqlsrv_free_stmt($listRes);
}

sqlsrv_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Menu Items</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    @font-face { font-family: "AUGUSTUS"; src: url("fonts/AUGUSTUS.ttf") format("truetype"); }
    @font-face { font-family: "freak"; src: url("fonts/freak.ttf") format("truetype"); }
    html, body { margin: 0; width: 100%; height: 100%; }
    body {
        font-family: Arial, sans-serif;
        background-image: url("bg/orderbg4.png");
        background-repeat: no-repeat;
        background-position: center 70px;
        background-size: cover;
        background-attachment: fixed;
    }
    header {
        background: linear-gradient(90deg,#4cabdc 0%,#e1ecff 40%,#9cd0ec 100%);
        padding: 10px 18px;
        color: #0f172a;
        font-size: 20px;
        font-weight: bold;
        letter-spacing: 0.5px;
        border-bottom: 3px solid #d4af37;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.28);
        position: sticky; top: 0; z-index: 1000;
    }
    .header-inner {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
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
    .back-btn:hover { background: #e5edf7; transform: translateY(-1px); }
    .container {
        max-width: 1200px;
        margin: 20px auto 40px;
        background: #ffffff6e;
        border: 3px solid #d4af37;
        box-shadow: 0 10px 30px #a7c7e780;
        padding: 20px 24px;
        border-radius: 20px;
        font-family: "AUGUSTUS", serif;
    }
    .panel {
        background: white;
        padding: 15px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 0 10px #1f406814;
    }
    .brand-logo {
        width: 320px;
        margin-bottom: -10px;
        margin-top: -10px;
        align-items: center;
        margin-left: 350px;
        margin-right: auto;
    }
    h3{ font-family: "freak", serif; }
    .search-drop{
        position:absolute; top: calc(100% + 6px); left: 0; right: 0;
        background:#fff; border:1px solid #cbd5e1; border-radius:14px;
        box-shadow:0 16px 35px rgba(15,23,42,.18);
        max-height:320px; overflow:auto; z-index:2000;
    }
    .search-item{
        display:flex; align-items:center; justify-content:space-between; gap:14px;
        padding:10px 14px; cursor:pointer; border-bottom:1px solid #eef2f7;
    }
    .search-item:last-child{ border-bottom:none; }
    .search-item:hover,.search-item.active{ background:#eaf3ff; }
    .search-left{ font-weight:700; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .search-right{ font-size:13px; color:#64748b; white-space:nowrap; }
    </style>
</head>
<body>
<header>
    <div class="header-inner">
        <a href="direct.php" class="back-btn">&#8592; Go Back</a>
        <img src="bg/LOGOS.png" class="brand-logo" alt="Nectar & Ambrosia Logo">
    </div>
</header>

<div class="container">

    <div class="mb-3 d-flex gap-2">
        <a href="update.php?mode=add" class="btn <?= $mode === 'add' ? 'btn-primary' : 'btn-outline-primary' ?>">Add Items</a>
        <a href="update.php?mode=update" class="btn <?= $mode === 'update' ? 'btn-primary' : 'btn-outline-primary' ?>">Update Items</a>
    </div>

<?php if ($mode === 'add'): ?>

    <div class="panel">
        <h3>Add New Menu Item</h3>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">

            <div class="mb-2">
                <label class="form-label">Name</label>
                <input type="text" name="MENU_NAME" class="form-control" required>
            </div>

            <div class="mb-2">
                <label class="form-label">Category</label>
                <select name="CATEGORY_ID" class="form-select" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['CATEGORY_ID'] ?>"><?= htmlspecialchars($c['CATEGORY_NAME']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <div class="col-4 mb-2">
                    <label class="form-label">Price S</label>
                    <input type="number" step="0.01" name="PRICE_S" class="form-control">
                </div>
                <div class="col-4 mb-2">
                    <label class="form-label">Price M</label>
                    <input type="number" step="0.01" name="PRICE_M" class="form-control">
                </div>
                <div class="col-4 mb-2">
                    <label class="form-label">Price L</label>
                    <input type="number" step="0.01" name="PRICE_L" class="form-control">
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label">Description</label>
                <textarea name="DESCRIPTION" class="form-control" rows="3"></textarea>
            </div>

            <div class="mb-3" id="imageNormalBox">
                <label class="form-label">Image</label>
                <input type="file" name="IMAGE_FILE" class="form-control" accept="image/*">
                <small class="text-muted">Use this for all categories except lattes.</small>
            </div>

            <div class="mb-3" id="imageLatteBox" style="display:none;">
                <label class="form-label d-block">Latte Images</label>

                <div class="mb-2">
                    <label class="form-label">Hot Image</label>
                    <input type="file" name="IMAGE_HOT" class="form-control" accept="image/*">
                </div>

                <div class="mb-2">
                    <label class="form-label">Cold Image</label>
                    <input type="file" name="IMAGE_COLD" class="form-control" accept="image/*">
                </div>

                <small class="text-muted">
                    For lattes: upload separate images for hot and cold.
                    If you leave one blank, only the other will be saved.
                </small>
            </div>

            <button class="btn btn-primary">Add Item</button>
        </form>
    </div>

<?php else: ?>

    <div class="panel">
        <h3><?= $editItem ? "Edit Menu Item" : "Select Item to Edit" ?></h3>

        <?php
        $isLatte  = false;
        $isAtlas  = false;
        $imgHot   = '';
        $imgCold  = '';
        $atlasTap = '';
        $atlasToc = '';
        $atlasHot = '';
        $atlasLong= '';

        if ($editItem) {
            $isLatte = (isset($editItem['CATEGORY_CODE']) && $editItem['CATEGORY_CODE'] === 'lattes');
            $isAtlas = ((int)$editItem['MENU_ID'] === 31);

            if (!empty($editItem['IMAGE']) && $isLatte) {
                $parts   = explode('|', $editItem['IMAGE']);
                $imgHot  = $parts[0] ?? '';
                $imgCold = $parts[1] ?? '';
            } else {
                $imgHot = $editItem['IMAGE'] ?? '';
            }

            if (!empty($editItem['IMAGE']) && $isAtlas) {
                $aParts     = explode('|', $editItem['IMAGE']);
                $atlasTap   = $aParts[0] ?? '';
                $atlasToc   = $aParts[1] ?? '';
                $atlasHot   = $aParts[2] ?? '';
                $atlasLong  = $aParts[3] ?? '';
            }
        }
        ?>

        <?php if ($editItem): ?>
        <form method="post" enctype="multipart/form-data" class="mb-3">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="MENU_ID" value="<?= $editItem['MENU_ID'] ?>">
            <input type="hidden" name="CURRENT_IMAGE" value="<?= htmlspecialchars($editItem['IMAGE']) ?>">

            <?php if ($isLatte): ?>
                <input type="hidden" name="CURRENT_IMAGE_HOT" value="<?= htmlspecialchars($imgHot) ?>">
                <input type="hidden" name="CURRENT_IMAGE_COLD" value="<?= htmlspecialchars($imgCold) ?>">
            <?php elseif ($isAtlas): ?>
                <input type="hidden" name="CURRENT_IMAGE_TAPSILOG" value="<?= htmlspecialchars($atlasTap) ?>">
                <input type="hidden" name="CURRENT_IMAGE_TOCILOG" value="<?= htmlspecialchars($atlasToc) ?>">
                <input type="hidden" name="CURRENT_IMAGE_HOTSILOG" value="<?= htmlspecialchars($atlasHot) ?>">
                <input type="hidden" name="CURRENT_IMAGE_LONGSILOG" value="<?= htmlspecialchars($atlasLong) ?>">
            <?php endif; ?>

            <div class="mb-2">
                <label class="form-label">Name</label>
                <input type="text" name="MENU_NAME" class="form-control" value="<?= htmlspecialchars($editItem['MENU_NAME']) ?>" required>
            </div>

            <div class="mb-2">
                <label class="form-label">Category</label>
                <select name="CATEGORY_ID" class="form-select" required>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['CATEGORY_ID'] ?>" <?= $c['CATEGORY_ID'] == $editItem['CATEGORY_ID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['CATEGORY_NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <div class="col-4 mb-2">
                    <label class="form-label">Price S</label>
                    <input type="number" step="0.01" name="PRICE_S" class="form-control"
                        value="<?= $editItem['PRICE_S'] !== null ? htmlspecialchars($editItem['PRICE_S']) : '' ?>">
                </div>
                <div class="col-4 mb-2">
                    <label class="form-label">Price M</label>
                    <input type="number" step="0.01" name="PRICE_M" class="form-control"
                        value="<?= $editItem['PRICE_M'] !== null ? htmlspecialchars($editItem['PRICE_M']) : '' ?>">
                </div>
                <div class="col-4 mb-2">
                    <label class="form-label">Price L</label>
                    <input type="number" step="0.01" name="PRICE_L" class="form-control"
                        value="<?= $editItem['PRICE_L'] !== null ? htmlspecialchars($editItem['PRICE_L']) : '' ?>">
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label">Description</label>
                <textarea name="DESCRIPTION" class="form-control" rows="3"><?= htmlspecialchars($editItem['DESCRIPTION']) ?></textarea>
            </div>

            <?php if ($isLatte): ?>
                <div class="mb-3">
                    <label class="form-label">Hot Image</label>
                    <?php if ($imgHot !== ''): ?>
                        <div class="mb-1"><small>Current Hot:</small><br>
                            <img src="<?= htmlspecialchars($imgHot) ?>" style="max-width:120px; border:1px solid #ccc; border-radius:6px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="IMAGE_HOT" class="form-control" accept="image/*">
                    <small class="text-muted">Leave blank to keep current hot image.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Cold Image</label>
                    <?php if ($imgCold !== ''): ?>
                        <div class="mb-1"><small>Current Cold:</small><br>
                            <img src="<?= htmlspecialchars($imgCold) ?>" style="max-width:120px; border:1px solid #ccc; border-radius:6px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="IMAGE_COLD" class="form-control" accept="image/*">
                    <small class="text-muted">Leave blank to keep current cold image.</small>
                </div>

            <?php elseif ($isAtlas): ?>

                <?php
                // tiny helper for showing atlas image blocks
                function atlasBlock($label, $cur, $field){
                    ?>
                    <div class="mb-3">
                        <label class="form-label"><?= $label ?> Image</label>
                        <?php if ($cur !== ''): ?>
                            <div class="mb-1"><small>Current:</small><br>
                                <img src="<?= htmlspecialchars($cur) ?>" style="max-width:120px; border:1px solid #ccc; border-radius:6px;">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="<?= $field ?>" class="form-control" accept="image/*">
                        <small class="text-muted">Leave blank to keep current.</small>
                    </div>
                    <?php
                }
                atlasBlock('Tapsilog',  $atlasTap,  'IMAGE_TAPSILOG');
                atlasBlock('Tocilog',   $atlasToc,  'IMAGE_TOCILOG');
                atlasBlock('Hotsilog',  $atlasHot,  'IMAGE_HOTSILOG');
                atlasBlock('Longsilog', $atlasLong, 'IMAGE_LONGSILOG');
                ?>

            <?php else: ?>
                <div class="mb-3">
                    <label class="form-label">Image</label>
                    <?php if (!empty($editItem['IMAGE'])): ?>
                        <div class="mb-1"><small>Current:</small><br>
                        <?php
                            $singleImg = $editItem['IMAGE'];
                            if (strpos($singleImg, '|') !== false) $singleImg = explode('|', $singleImg)[0];
                        ?>
                        <img src="<?= htmlspecialchars($singleImg) ?>" style="max-width:120px; border:1px solid #ccc; border-radius:6px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="IMAGE_FILE" class="form-control" accept="image/*">
                    <small class="text-muted">Leave blank to keep current image.</small>
                </div>
            <?php endif; ?>

            <button class="btn btn-primary">Save Changes</button>
            <a href="update.php?mode=update" class="btn btn-secondary ms-2">Cancel</a>
        </form>
        <?php endif; ?>

        <h5 class="mt-2">Search & Filters</h5>

        <form method="get" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="mode" value="update">

            <div class="col-md-5 position-relative">
                <label class="form-label">Search item</label>
                <input type="text" id="qBox" name="q" class="form-control" value="<?= htmlspecialchars($qText) ?>"
                       autocomplete="off" placeholder="Type menu name...">
                <div id="qDrop" class="search-drop" style="display:none;"></div>
            </div>

            <div class="col-md-3">
                <label class="form-label">Category</label>
                <select name="cat" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['CATEGORY_ID'] ?>" <?= ($catFilter !== '' && (int)$catFilter === (int)$c['CATEGORY_ID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['CATEGORY_NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Sort</label>
                <select name="sort" class="form-select">
                    <option value="" <?= $sort === '' ? 'selected' : '' ?>>Default</option>
                    <option value="cat" <?= $sort === 'cat' ? 'selected' : '' ?>>By Category</option>
                    <option value="az" <?= $sort === 'az' ? 'selected' : '' ?>>Alphabetical (A-Z)</option>
                    <option value="id" <?= $sort === 'id' ? 'selected' : '' ?>>By ID</option>
                    <option value="plow" <?= $sort === 'plow' ? 'selected' : '' ?>>Price (Low to High)</option>
                    <option value="phigh" <?= $sort === 'phigh' ? 'selected' : '' ?>>Price (High to Low)</option>
                </select>
            </div>

            <div class="col-md-1 d-grid">
                <button class="btn btn-primary">Apply</button>
            </div>

            <div class="col-12 d-flex gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="update.php?mode=update">Reset</a>
            </div>
        </form>

        <h5>Current Menu</h5>
        <div class="table-responsive" style="max-height:400px;">
            <table class="table table-sm table-hover">
                <thead class="table-light">
                    <tr>
                        <th>ID</th><th>Name</th><th>Category</th><th>S</th><th>M</th><th>L</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($menuItems as $m): ?>
                    <tr>
                        <td><?= $m['MENU_ID'] ?></td>
                        <td><?= htmlspecialchars($m['MENU_NAME']) ?></td>
                        <td><?= htmlspecialchars($m['CATEGORY_NAME']) ?></td>
                        <td><?= $m['PRICE_S'] !== null ? number_format($m['PRICE_S'], 2) : '-' ?></td>
                        <td><?= $m['PRICE_M'] !== null ? number_format($m['PRICE_M'], 2) : '-' ?></td>
                        <td><?= $m['PRICE_L'] !== null ? number_format($m['PRICE_L'], 2) : '-' ?></td>
                        <td>
                            <div class="d-flex gap-2">
                                <?php
                                    $qs = "mode=update";
                                    if ($qText !== '')    $qs .= "&q=" . urlencode($qText);
                                    if ($catFilter !== '') $qs .= "&cat=" . urlencode($catFilter);
                                    if ($sort !== '')     $qs .= "&sort=" . urlencode($sort);
                                ?>
                                <a href="update.php?<?= $qs ?>&edit_id=<?= $m['MENU_ID'] ?>" class="btn btn-warning btn-sm">Edit</a>

                                <form method="post" onsubmit="return confirm('Delete item: <?= htmlspecialchars($m['MENU_NAME'], ENT_QUOTES) ?>?');" style="margin:0;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="MENU_ID" value="<?= $m['MENU_ID'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
<?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

//toggle para sa lattes
  function toggleForSelect(selCat) {
    if (!selCat) return;

    var form = selCat.closest('form') || document;
    var normalBox = form.querySelector('#imageNormalBox');
    var latteBox  = form.querySelector('#imageLatteBox');
    if (!normalBox || !latteBox) return;

    var opt = selCat.options[selCat.selectedIndex];
    var txt = opt ? opt.text.toLowerCase().trim() : '';

    if (txt === 'lattes' || txt === 'latte') {
      normalBox.style.display = 'none';
      latteBox.style.display  = '';
    } else {
      normalBox.style.display = '';
      latteBox.style.display  = 'none';
    }
  }

  var catSelects = document.querySelectorAll('select[name="CATEGORY_ID"]');
  catSelects.forEach(function(sel){
    sel.addEventListener('change', function(){ toggleForSelect(sel); });
    toggleForSelect(sel);
  });

  // suggestions data from PHP
  var data = <?= json_encode($suggestions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  var box  = document.getElementById('qBox');
  var drop = document.getElementById('qDrop');
  if (!box || !drop) return;

  var active = -1;     
  var shown  = [];     
  function esc(s){
    s = s || '';
    return s.replace(/[&<>"']/g, function(m){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
    });
  }

  function hideDrop(){
    drop.style.display = 'none';
    drop.innerHTML = '';
    active = -1;
  }

  function render(list){
    if (!list.length) { hideDrop(); return; }

    var html = '';
    for (var i = 0; i < list.length; i++) {
      html += '<div class="search-item" data-i="' + i + '" data-nm="' + esc(list[i].nm) + '">'
           +    '<div class="search-left">' + esc(list[i].nm) + '</div>'
           +    '<div class="search-right">' + esc(list[i].cat) + '</div>'
           +  '</div>';
    }

    drop.innerHTML = html;
    drop.style.display = '';
    active = -1;
  }

  function setActive(idx){
    var items = drop.querySelectorAll('.search-item');
    // remove highlight
    for (var i = 0; i < items.length; i++) {
      items[i].classList.remove('active');
    }
    // add highlight to active item
    if (idx >= 0 && idx < items.length) {
      items[idx].classList.add('active');
      items[idx].scrollIntoView({ block: 'nearest' });
    }
  }
  function filterList(){
    var q = (box.value || '').toLowerCase().trim();
    if (q === '') { shown = []; hideDrop(); return; }

    shown = data.filter(function(x){
      return (x.nm || '').toLowerCase().indexOf(q) !== -1;
    }).slice(0, 12);

    render(shown);
  }

  // Typing updates suggestions
  box.addEventListener('input', filterList);
  box.addEventListener('keydown', function(e){
    var items = drop.querySelectorAll('.search-item');
    if (drop.style.display === 'none' || !items.length) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      active = Math.min(active + 1, items.length - 1);
      setActive(active);
    }

    if (e.key === 'ArrowUp') {
      e.preventDefault();
      active = Math.max(active - 1, 0);
      setActive(active);
    }

    if (e.key === 'Enter') {
      if (active >= 0 && active < items.length) {
        e.preventDefault();
        box.value = items[active].getAttribute('data-nm') || '';
        hideDrop();
      }
    }

    if (e.key === 'Escape') {
      hideDrop();
    }
  });

  drop.addEventListener('mousedown', function(e){
    var t = e.target.closest('.search-item');
    if (!t) return;

    e.preventDefault();
    box.value = t.getAttribute('data-nm') || '';
    hideDrop();
    box.focus();
  });

  document.addEventListener('click', function(e){
    if (e.target === box) return;
    if (drop.contains(e.target)) return;
    hideDrop();
  });

});
</script>


</body>
</html>

<?php
session_start();
$serverName = "LAPTOP-5SE5KLER\\SQLEXPRESS";
$connectionOptions = ["Database"=>"DLSU","Uid"=>"","PWD"=>""];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) 
  die(print_r(sqlsrv_errors(), true));
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

$user = $_SESSION['user'] ?? 'Cashier';
$role = $_SESSION['role'] ?? '';

//this is for th search baar
$cat = strtolower(trim($_GET['category'] ?? 'lattes'));
$q   = trim($_GET['search'] ?? '');

//basically assigns roles onto the items
function itemType($code){
    $code = strtolower($code);
    if (in_array($code, ['lattes','frappes'])) return 'drink';
    if ($code === 'breakfast') return 'breakfast';
    if ($code === 'sides') return 'sides';
    return 'food';
}

//prices for the items
function basePrice($r){
    foreach (['PRICE_S','PRICE_M','PRICE_L'] as $k){
        if ($r[$k] !== null && $r[$k] !== '') return (float)$r[$k];
    }
    return 0;
}
$cats = [];
$catname = ucfirst($cat);
$catres = sqlsrv_query($conn, "SELECT CATEGORY_ID, CATEGORY_NAME, CATEGORY_CODE FROM CATEGORY ORDER BY CATEGORY_ID");
if ($catres){
    while ($r = sqlsrv_fetch_array($catres, SQLSRV_FETCH_ASSOC)){
        $cats[] = $r;
        if (strtolower($r['CATEGORY_CODE']) === $cat) $catname = $r['CATEGORY_NAME'];
    }
    sqlsrv_free_stmt($catres);
}
$all = [];
$resAll = sqlsrv_query($conn, "SELECT m.MENU_ID, m.MENU_NAME, c.CATEGORY_CODE, c.CATEGORY_NAME
                               FROM MENU m INNER JOIN CATEGORY c ON m.CATEGORY_ID=c.CATEGORY_ID
                               ORDER BY m.MENU_NAME
");
if ($resAll){
    while ($r = sqlsrv_fetch_array($resAll, SQLSRV_FETCH_ASSOC)) $all[] = $r;
    sqlsrv_free_stmt($resAll);
}

//gets items using the category code
$sections = [$cat];
$sql = "SELECT m.MENU_ID, m.MENU_NAME, m.PRICE_S, m.PRICE_M, m.PRICE_L, m.DESCRIPTION, m.IMAGE, c.CATEGORY_CODE, c.CATEGORY_NAME
        FROM MENU m INNER JOIN CATEGORY c ON m.CATEGORY_ID=c.CATEGORY_ID
        WHERE LOWER(c.CATEGORY_CODE)=?
";
if ($q !== ''){
    //for searching names
    $sql .= " AND m.MENU_NAME LIKE ?";
    $sections[] = "%$q%";
}
$sql .= " ORDER BY m.MENU_NAME";

$res = sqlsrv_query($conn, $sql, $sections);
if (!$res) die(print_r(sqlsrv_errors(), true));

/* Build items list with extra computed fields (TYPE + BASE_PRICE) */
$items = [];
while ($r = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)){
    $r['TYPE'] = itemType($r['CATEGORY_CODE']);
    $r['BASE_PRICE'] = basePrice($r);
    $items[] = $r;
}
sqlsrv_free_stmt($res);
sqlsrv_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Page</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="orderstyle.css">
</head>
<body>

<header>
  <div class="header-inner">
    <div class="dropdown header-user-wrap">
      <button type="button" class="header-userbtn" data-bs-toggle="dropdown">
        <div class="header-avatar"></div>
        <div class="header-usertext">
          <div class="header-username"><?= h($user) ?></div>
          <div class="header-userrole"><?= $role!=='' ? h($role) : 'Account' ?></div>
        </div>

        <div class="header-caret">&#9662;</div>
      </button>
      <ul class="dropdown-menu">
        <?php if (strtolower($role)==='admin'): ?>
          <li><a class="dropdown-item" href="direct.php">Dashboard</a></li>
          <li><hr class="dropdown-divider"></li>
        <?php endif; ?>
        <li><a class="dropdown-item text-danger" href="home.html">Log out</a></li>
      </ul>
    </div>
    <img src="bg/LOGOS.png" class="brand-logo" alt="Nectar & Ambrosia Logo">
    <div class="header-right">
      <?php if (strtolower($role)==='admin'): ?>
        <a href="update.php" class="header-action">Update Menu</a>
        <a href="reports.php" class="header-action">Sales Report</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="container-main">
  <div class="sidebar">
    <?php foreach ($cats as $c): ?>
      <?php $active = (strtolower($c['CATEGORY_CODE']) === $cat); ?>
      <a href="order.php?category=<?= h($c['CATEGORY_CODE']) ?>">
        <button <?= $active ? 'style="background:#93c5fd;font-weight:bold;"' : '' ?>>
          <div class="catgbtn-inner">
            <div class="cat-thumb" style="background-image:url('cat_imgs/<?= h($c['CATEGORY_CODE']) ?>.png');"></div>
            <span class="cat-name"><?= h($c['CATEGORY_NAME']) ?></span>
          </div>
        </button>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="menu-area">
    <div class="menu-hud">
      <div class="menu-hud-top">
        <div>
          <h2><?= h($catname) ?></h2>
          <small><?= $q!=='' ? 'Showing results for “'.h($q).'”' : 'Browse and tap an item to customize your order.' ?></small>
        </div>
        <a href="cart.php" class="view-cart-btn">View Cart</a>
      </div>
      <form class="search-bar" method="get" action="order.php" id="searchForm">
        <input type="hidden" name="category" id="searchCategory" value="<?= h($cat) ?>">
        <div class="input-group">
          <input type="text" name="search" id="searchInput" class="form-control"
                 placeholder="Search for a drink or dish..." value="<?= h($q) ?>" autocomplete="off">
          <button class="btn btn-primary" type="submit">Search</button>
        </div>

        <--si js na bahala sa pagfill up ng autosuggest-->
        <div id="searchSuggestions" class="search-suggestions"></div>
      </form>
    </div>

    <div class="items-scroll">
      <?php if (empty($items)): ?>
        <div class="no-results">No items found<?= $q!=='' ? ' for “'.h($q).'”' : '' ?> in this category.</div>
      <?php else: ?>
        <div class="grid">
          <?php foreach ($items as $it): ?>
            <?php
              $imgRaw = $it['IMAGE'] ?? '';
              $imgPath = $imgRaw ?: 'images/placeholder.png';

              //checks for "|" kasi nakaseperate hot at cold imgs
              if ($imgRaw && strpos($imgRaw,'|')!==false){
                  $p = explode('|',$imgRaw);
                  $imgPath = ($it['MENU_ID']==31) ? ($p[0] ?? $p[1] ?? 'images/placeholder.png')
                                                 : ($p[1] ?? $p[0] ?? 'images/placeholder.png');
              }

              //captions based on role-items
              $lineText = "Select quantity";
              if ($it['TYPE']==='drink') $lineText = "Select size and quantity";
              if ($it['TYPE']==='breakfast') $lineText = "Choose solo or with sides + drink";
              if ($it['TYPE']==='sides') $lineText = "Choose size and quantity";
            ?>
            <div class="menu-card"
                 onclick="openModal(
                   <?= (int)$it['MENU_ID'] ?>,
                   '<?= h($it['MENU_NAME']) ?>',
                   '<?= h($it['TYPE']) ?>',
                   <?= $it['PRICE_S']!==null ? (float)$it['PRICE_S'] : 'null' ?>,
                   <?= $it['PRICE_M']!==null ? (float)$it['PRICE_M'] : 'null' ?>,
                   <?= $it['PRICE_L']!==null ? (float)$it['PRICE_L'] : 'null' ?>,
                   '<?= h($it['DESCRIPTION'] ?? '') ?>',
                   '<?= h($it['IMAGE'] ?? '') ?>'
                 )">

              <div class="card-img" style="background-image:url('<?= h($imgPath) ?>');"></div>
              <h4><?= h($it['MENU_NAME']) ?></h4>
              <p><?= $lineText ?><br><strong>₱<?= number_format((float)$it['BASE_PRICE'], 2) ?></strong></p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<div class="modal fade modal-design" id="itemModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="modalItemName"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <div id="modalImgBox" class="modal-img"></div>
          <p id="modalDesc" class="modal-subtext mt-2"></p>
          <div id="modalPrice"></div>
        </div>
        <div id="tempBox" class="mb-3">
          <label class="form-label">Temperature</label>
          <div class="d-flex gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="temp" id="temp_cold" value="Cold" checked>
              <label class="form-check-label" for="temp_cold">Cold</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="temp" id="temp_hot" value="Hot">
              <label class="form-check-label" for="temp_hot">Hot</label>
            </div>
          </div>
        </div>

        <div id="sizeSelector" class="mb-3">
          <label class="form-label d-block mb-1">Select Size</label>
          <div id="sizeRadios" class="d-flex flex-wrap gap-2"></div>
        </div>
        <div id="breakfastOptions" class="mb-3" style="display:none;">
          <label class="form-label">Sides &amp; Drink <span style="font-size:11px;color:#6b7280;">(for Regular / Large only)</span></label>
          <div class="row g-2">
            <div class="col-6"><select id="breakfastSide" class="form-select form-select-sm"><option value="">Choose side</option></select></div>
            <div class="col-6"><select id="breakfastDrink" class="form-select form-select-sm"><option value="">Choose drink</option></select></div>
          </div>
          <div id="breakfastNote" style="font-size:11px;color:#6b7280;margin-top:4px;"></div>
        </div>
        <div id="atlasVariantBox" class="mb-3" style="display:none;">
          <label class="form-label">Choose Meat</label>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach (['Tapsilog','Tocilog','Hotsilog','Longsilog'] as $v): ?>
              <button type="button" class="btn btn-outline-secondary btn-sm atlas-variant-btn" data-variant="<?= $v ?>"><?= $v ?></button>
            <?php endforeach; ?>
          </div>
          <div style="font-size:11px;color:#6b7280;margin-top:4px;">Image changes based on your chosen silog.</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Quantity</label>
          <input type="number" id="modalQty" class="form-control" min="1" value="1">
        </div>
        <div id="sweetBox" class="mb-3" style="display:none;">
          <label class="form-label">Sweetness Level</label>
          <div id="sweetCaption" style="font-size:11px;color:#6b7280;margin-bottom:4px;"></div>
          <input type="range" min="1" max="5" value="3" class="form-range" id="sweetLevel">
        </div>
        <div id="caffBox" class="mb-1" style="display:none;">
          <label class="form-label">Caffeine Strength</label>
          <div id="caffCaption" style="font-size:11px;color:#6b7280;margin-bottom:4px;"></div>
          <input type="range" min="1" max="5" value="3" class="form-range" id="caffLevel">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-primary" onclick="addToCart()" data-bs-dismiss="modal">Add to Cart</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>

//dropdowns for breakfast cats and 
const allItems = <?= json_encode($all, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const catcode = "<?= h($cat) ?>";
let selItem = null;
let atlasVariant = "Tapsilog";
const $ = (id)=>document.getElementById(id);
function pickPrice(size){
  if(!selItem) return 0;
  const map = {S:selItem.priceS, M:selItem.priceM, L:selItem.priceL};
  return (map[size] ?? selItem.priceS ?? selItem.priceM ?? selItem.priceL ?? 0) || 0;
}

/* Small helpers to update text/show-hide blocks */
function setText(id, txt){ if($(id)) $(id).innerText = txt; }
function show(id, on){ if($(id)) $(id).style.display = on ? "block" : "none"; }

function setModalImg(path){
  $("modalImgBox").style.backgroundImage = `url('${path || "images/placeholder.png"}')`;
}
function atlasImgsFromRaw(img){
  const p = (img||"").split("|").map(s=>s.trim());
  const base = p.find(x=>x) || "";
  return {
    Tapsilog:  p[0]||base,
    Tocilog:   p[1]||base,
    Hotsilog:  p[2]||base,
    Longsilog: p[3]||base
  };
}

function updatePrice(){
  if(!selItem) return;
  const sr = document.querySelector('input[name="size"]:checked');
  const size = sr ? sr.value : "";
  const p = pickPrice(size);
  setText("modalPrice", p ? `₱${p.toFixed(2)}` : "");
  updateBreakfastBox();
  updateImage();
}

function updateBreakfastBox(){
  if(!selItem || selItem.type!=="breakfast"){ show("breakfastOptions", false); return; }
  const size = (document.querySelector('input[name="size"]:checked')||{}).value || "";
  if(size==="M" || size==="L"){
    buildBreakfastLists();
    show("breakfastOptions", true);
    setText("breakfastNote", "Choose your preferred side and drink for this set.");
  } else {
    show("breakfastOptions", false);
    if($("breakfastSide")) $("breakfastSide").value="";
    if($("breakfastDrink")) $("breakfastDrink").value="";
    setText("breakfastNote","");
  }
}
function buildBreakfastLists(){
  const sideSel = $("breakfastSide"), drinkSel = $("breakfastDrink");
  if(!sideSel || !drinkSel) return;

  const sides = allItems.filter(x=>(x.CATEGORY_CODE||"").toLowerCase()==="sides");
  const drinks = allItems.filter(x => (x.CATEGORY_CODE || "").toLowerCase() === "drinks");

  sideSel.innerHTML = '<option value="">Choose side</option>' + sides.map(x=>`<option>${x.MENU_NAME}</option>`).join("");
  drinkSel.innerHTML = '<option value="">Choose drink</option>' + drinks.map(x=>`<option>${x.MENU_NAME}</option>`).join("");
}

function updateImage(){
  if(!selItem) return;
  if(selItem.id===31){
    setModalImg(selItem.atlasImgs[atlasVariant] || selItem.imgCold || selItem.imgHot);
    return;
  }
  const t = (document.querySelector('input[name="temp"]:checked')||{}).value || "";
  if(t==="Hot") setModalImg(selItem.imgHot || selItem.imgCold);
  else setModalImg(selItem.imgCold || selItem.imgHot);
}


function openModal(id, name, type, priceS, priceM, priceL, desc, img){
  //split images pag sa lattes
  const parts = (img||"").includes("|") ? img.split("|") : [img,img];
  const imgHot = (parts[0]||"").trim();
  const imgCold = (parts[1]||parts[0]||"").trim();


  selItem = {
    id, name, type,
    priceS: priceS!==null ? Number(priceS) : null,
    priceM: priceM!==null ? Number(priceM) : null,
    priceL: priceL!==null ? Number(priceL) : null,
    desc: desc && desc.trim() ? desc : "A signature item from Nectar & Ambrosia.",
    imgRaw: img||"",
    imgHot, imgCold,
    atlasImgs: id===31 ? atlasImgsFromRaw(img) : {}
  };

  $("modalItemName").innerText = name;
  $("modalDesc").innerText = selItem.desc;
  $("modalQty").value = 1;

  if(type==="drink" && catcode!=="frappes"){ show("tempBox", true); $("temp_cold").checked=true; }
  else { show("tempBox", false); $("temp_cold").checked=true; }
  const sizeRad = $("sizeRadios");
  sizeRad.innerHTML = "";
  const labels = (type==="breakfast")
    ? {S:"Solo", M:"Regular (Sides + Drink)", L:"Large (Sides + Drink)"}
    : (type==="sides")
      ? {S:"Regular", M:"Large"}
      : {S:"Demigod", M:"God", L:"Titan"};

  const allowL = (type!=="sides");
  const sizes = [
    selItem.priceS!=null ? "S" : null,
    selItem.priceM!=null ? "M" : null,
    (allowL && selItem.priceL!=null) ? "L" : null
  ].filter(Boolean);

  if(sizes.length){
    sizes.forEach((s,i)=>{
      sizeRad.insertAdjacentHTML("beforeend", `
        <div class="form-check">
          <input class="form-check-input" type="radio" name="size" id="size_${s}" value="${s}">
          <label class="form-check-label" for="size_${s}">${labels[s]||s}</label>
        </div>
      `);


      if(i===0) $("size_"+s).checked = true;

      //size change price
      $("size_"+s).addEventListener("change", updatePrice);
    });

    show("sizeSelector", true);
  } else {
    show("sizeSelector", false);
  }

  //para sa sliders
  const showSliders = (type==="drink" && (catcode==="lattes" || catcode==="frappes"));
  show("sweetBox", showSliders);
  show("caffBox", showSliders);

  if(showSliders){
    $("sweetLevel").value = 3;
    $("caffLevel").value = 3;
    setText("sweetCaption","");
    setText("caffCaption","");
  }

  /* Atlas variants UI only for item id==31 */
  show("atlasVariantBox", id===31);
  document.querySelectorAll(".atlas-variant-btn").forEach(btn=>{
    btn.onclick = ()=>{
      atlasVariant = btn.dataset.variant;
      highlightAtlas();
      updateImage();
    };
  });
  highlightAtlas();

  /* Initial render */
  updatePrice();
  updateImage();

  /* Show the modal */
  new bootstrap.Modal($("itemModal")).show();
}

/* Highlight active Atlas variant button */
function highlightAtlas(){
  document.querySelectorAll(".atlas-variant-btn").forEach(b=>{
    b.classList.toggle("active", b.dataset.variant===atlasVariant);
  });
}

$("sweetLevel")?.addEventListener("input", ()=>{
  const v = Number($("sweetLevel").value);
  setText("sweetCaption", v===5 ? "Aphrodite admires your love for sugar" : (v===1 ? "Hades understands your plight" : ""));
});
$("caffLevel")?.addEventListener("input", ()=>{
  const v = Number($("caffLevel").value);
  setText("caffCaption", v===5 ? "Heracles fears your resilience" : (v===1 ? "Hypnos tips his glass to you" : ""));
});

// Temp change refreshes image
$("temp_cold")?.addEventListener("change", updateImage);
$("temp_hot")?.addEventListener("change", updateImage);

function addToCart(){
  if(!selItem) return;

  //read kung ilan
  let qty = parseInt($("modalQty").value, 10);
  if(isNaN(qty) || qty<1) qty = 1;
  const size = (document.querySelector('input[name="size"]:checked')||{}).value || "";
  const price = pickPrice(size);
  const temp = (document.querySelector('input[name="temp"]:checked')||{}).value || "";

  //dugtong dugtong mga pangalan
  let displayName = selItem.name;

  if(selItem.id===31) displayName += ` (${atlasVariant})`;

  if(selItem.type==="breakfast"){
    const sizeLabel = (size==="S")?"Solo":(size==="M")?"Regular Set":(size==="L")?"Large Set":"";
    if(sizeLabel) displayName += ` - ${sizeLabel}`;

    if(size==="M" || size==="L"){
      const side = $("breakfastSide")?.value || "";
      const drink = $("breakfastDrink")?.value || "";
      const parts = [side, drink].filter(Boolean);
      if(parts.length) displayName += ` (${parts.join(" + ")})`;
    }
  }

  if(selItem.type==="sides"){
    const sLabel = (size==="S")?"Regular":(size==="M")?"Large":"";
    if(sLabel) displayName += ` - ${sLabel}`;
  }
  const f = document.createElement("form");
  f.method="post"; f.action="cart_add.php";
  const data = {name:displayName, size, temp, qty, price};

  Object.keys(data).forEach(k=>{
    const inp=document.createElement("input");
    inp.type="hidden"; inp.name=k; inp.value=data[k];
    f.appendChild(inp);
  });

  document.body.appendChild(f);
  f.submit();
}
const searchInput = $("searchInput"), sugBox = $("searchSuggestions");
//clears
function clearSug(){
  sugBox.innerHTML="";
  sugBox.style.display="none";
}

//show suggestions while typing
searchInput?.addEventListener("input", ()=>{
  const txt = searchInput.value.trim().toLowerCase();
  if(!txt) return clearSug();

  const matches = allItems
    .filter(x => (x.MENU_NAME||"").toLowerCase().includes(txt))
    .slice(0,8);

  sugBox.innerHTML = matches.map(x=>`
    <button type="button" class="suggest-btn" data-cat="${x.CATEGORY_CODE}" data-name="${x.MENU_NAME}">
      <span class="suggest-name">${x.MENU_NAME}</span>
      <span class="suggest-cat">${x.CATEGORY_NAME}</span>
    </button>
  `).join("");

  sugBox.style.display = matches.length ? "block" : "none";
});

//pang confirm sa search
sugBox?.addEventListener("click", (e)=>{
  const btn = e.target.closest("button");
  if(!btn) return;
  $("searchCategory").value = btn.dataset.cat;
  searchInput.value = btn.dataset.name;
  clearSug();
  $("searchForm").submit();
});
document.addEventListener("click", (e)=>{
  if(!$("searchForm").contains(e.target)) clearSug();
});
</script>
</body>
</html>

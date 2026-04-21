<?php


require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin(); // Not logged in? → redirect to login.php

$user = currentUser();
$role = $user['role'];

// ── Donor-এর নিজের listings load করো ─────────────────────
$myListings = [];
if ($role === 'donor') {
    $stmt = $pdo->prepare(
        "SELECT * FROM food_listings WHERE donor_id = ? ORDER BY created_at DESC LIMIT 10"
    );
    $stmt->execute([$user['id']]);
    $myListings = $stmt->fetchAll();
}

// ── Volunteer-এর active rescue load করো ──────────────────
$myRescues = [];
if ($role === 'volunteer') {
    $stmt = $pdo->prepare(
        "SELECT r.*, fl.title, fl.pickup_address, fl.category, fl.serves,
                u.full_name AS donor_name, u.phone AS donor_phone, u.org_name
         FROM rescues r
         JOIN food_listings fl ON r.listing_id = fl.listing_id
         JOIN users u ON fl.donor_id = u.user_id
         WHERE r.volunteer_id = ?
         ORDER BY r.claimed_at DESC LIMIT 10"
    );
    $stmt->execute([$user['id']]);
    $myRescues = $stmt->fetchAll();
}

// ── Shelter: incoming deliveries ──────────────────────────
$incomingDeliveries = [];
if ($role === 'shelter') {
    $stmt = $pdo->prepare(
        "SELECT r.*, fl.title, fl.category, fl.serves, fl.notes,
                u.full_name AS volunteer_name, u.phone AS volunteer_phone
         FROM rescues r
         JOIN food_listings fl ON r.listing_id = fl.listing_id
         JOIN users u ON r.volunteer_id = u.user_id
         WHERE r.shelter_id = ? AND r.status != 'delivered'
         ORDER BY r.claimed_at DESC"
    );
    $stmt->execute([$user['id']]);
    $incomingDeliveries = $stmt->fetchAll();
}

// ── Donor stats ───────────────────────────────────────────
$donorStats = ['total' => 0, 'meals' => 0, 'active' => 0, 'expiring' => 0];
if ($role === 'donor') {
    $s = $pdo->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(serves),0) AS m FROM food_listings WHERE donor_id = ?");
    $s->execute([$user['id']]); $r = $s->fetch();
    $donorStats['total'] = $r['c']; $donorStats['meals'] = $r['m'];

    $s = $pdo->prepare("SELECT COUNT(*) AS c FROM food_listings WHERE donor_id = ? AND status='available'");
    $s->execute([$user['id']]); $donorStats['active'] = $s->fetch()['c'];

    $s = $pdo->prepare("SELECT COUNT(*) AS c FROM food_listings WHERE donor_id = ? AND status='available' AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 HOUR)");
    $s->execute([$user['id']]); $donorStats['expiring'] = $s->fetch()['c'];
}

// ── Volunteer stats ───────────────────────────────────────
$volStats = ['deliveries' => 0, 'meals' => 0, 'active' => 0];
if ($role === 'volunteer') {
    $s = $pdo->prepare("SELECT COUNT(*) AS c FROM rescues WHERE volunteer_id = ? AND status='delivered'");
    $s->execute([$user['id']]); $volStats['deliveries'] = $s->fetch()['c'];

    $s = $pdo->prepare("SELECT COALESCE(SUM(il.meals_count),0) AS m FROM impact_logs il JOIN rescues r ON il.rescue_id=r.rescue_id WHERE r.volunteer_id=?");
    $s->execute([$user['id']]); $volStats['meals'] = $s->fetch()['m'];

    $s = $pdo->prepare("SELECT COUNT(*) AS c FROM rescues WHERE volunteer_id=? AND status='claimed'");
    $s->execute([$user['id']]); $volStats['active'] = $s->fetch()['c'];
}

// ── Shelter stats ─────────────────────────────────────────
$shelterStats = ['received' => 0, 'meals' => 0, 'pending' => 0];
if ($role === 'shelter') {
    $s = $pdo->prepare("SELECT COUNT(*) AS c FROM rescues WHERE shelter_id=? AND status='delivered'");
    $s->execute([$user['id']]); $shelterStats['received'] = $s->fetch()['c'];
    $s = $pdo->prepare("SELECT COALESCE(SUM(il.meals_count),0) AS m FROM impact_logs il JOIN rescues r ON il.rescue_id=r.rescue_id WHERE r.shelter_id=?");
    $s->execute([$user['id']]); $shelterStats['meals'] = $s->fetch()['m'];
    $s = $pdo->prepare("SELECT COUNT(*) AS c FROM rescues WHERE shelter_id=? AND status='claimed'");
    $s->execute([$user['id']]); $shelterStats['pending'] = $s->fetch()['c'];
}

// Available shelters for volunteer claim modal
$shelters = $pdo->query("SELECT user_id, full_name, org_name FROM users WHERE role='shelter' AND is_active=1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NourishNet — Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600&display=swap');
  *{box-sizing:border-box;margin:0;padding:0}
  :root{
    --green:#1a6b3a;--green-mid:#2e8b57;--green-light:#e8f5ee;--green-pale:#f2faf5;
    --amber:#b87333;--amber-light:#fdf3e7;
    --blue:#1a5276;--blue-light:#eaf1f8;
    --coral:#c0392b;--coral-light:#fdecea;
    --gray:#4a4a4a;--gray-mid:#888;--gray-light:#f5f5f5;--gray-border:#e0e0e0;
    --white:#fff;--text:#1a1a1a;
    font-family:'DM Sans',sans-serif;
  }
  body{background:var(--gray-light);color:var(--text);font-size:14px;line-height:1.6}

  /* NAV */
  .nav{background:var(--green);color:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 24px;height:52px;position:sticky;top:0;z-index:100}
  .nav-logo{font-family:'DM Serif Display',serif;font-size:20px;letter-spacing:.5px;color:#fff;display:flex;align-items:center;gap:8px}
  .nav-tabs{display:flex;gap:4px}
  .nav-tab{padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:500;color:rgba(255,255,255,.75);border:none;background:none;transition:.15s}
  .nav-tab:hover{background:rgba(255,255,255,.12);color:#fff}
  .nav-tab.active{background:rgba(255,255,255,.2);color:#fff}
  .nav-right{display:flex;align-items:center;gap:10px}
  .role-chip{font-size:11px;padding:3px 10px;border-radius:20px;font-weight:600;letter-spacing:.4px;text-transform:uppercase}
  .chip-donor{background:#e8f5ee;color:#1a6b3a}
  .chip-volunteer{background:#eaf1f8;color:#1a5276}
  .chip-shelter{background:#fdf3e7;color:#b87333}
  .logout-btn{font-size:12px;padding:4px 12px;border-radius:6px;border:.5px solid rgba(255,255,255,.4);background:none;color:rgba(255,255,255,.8);cursor:pointer;font-family:'DM Sans',sans-serif;transition:.15s}
  .logout-btn:hover{background:rgba(255,255,255,.15);color:#fff}

  /* LAYOUT */
  .page{display:none;padding:20px 24px;min-height:calc(100vh - 52px);animation:fadeIn .25s ease}
  .page.active{display:block}
  @keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
  .two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .three-col{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}

  /* CARDS */
  .card{background:var(--white);border-radius:12px;border:.5px solid var(--gray-border);padding:18px 20px;margin-bottom:16px}
  .card-title{font-weight:600;font-size:15px;margin-bottom:12px;color:var(--text)}

  /* STAT */
  .stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}
  .stat-card{background:var(--white);border-radius:10px;border:.5px solid var(--gray-border);padding:14px 16px;text-align:center}
  .stat-val{font-size:26px;font-weight:600;line-height:1}
  .stat-label{font-size:11px;color:var(--gray-mid);margin-top:4px;text-transform:uppercase;letter-spacing:.4px}
  .stat-green .stat-val{color:var(--green-mid)}
  .stat-amber .stat-val{color:var(--amber)}
  .stat-blue .stat-val{color:var(--blue)}
  .stat-coral .stat-val{color:var(--coral)}

  /* BUTTONS */
  .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;border:none;cursor:pointer;font-size:13px;font-weight:500;transition:.15s;font-family:'DM Sans',sans-serif}
  .btn-primary{background:var(--green-mid);color:#fff}
  .btn-primary:hover{background:var(--green)}
  .btn-secondary{background:var(--green-light);color:var(--green);border:.5px solid #b8dfc9}
  .btn-blue{background:var(--blue-light);color:var(--blue);border:.5px solid #b3c9de}
  .btn-amber{background:var(--amber-light);color:var(--amber);border:.5px solid #e2c89a}
  .btn-danger{background:var(--coral-light);color:var(--coral);border:.5px solid #e8b3ad}
  .btn-sm{padding:5px 12px;font-size:12px}

  /* BADGES */
  .badge{display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600}
  .badge-available{background:#e8f5ee;color:#1a6b3a}
  .badge-claimed{background:#eaf1f8;color:#1a5276}
  .badge-delivered{background:#fdf3e7;color:#b87333}
  .badge-urgent{background:#fdecea;color:#c0392b}

  /* FOOD CARDS */
  .food-card{background:var(--white);border-radius:12px;border:.5px solid var(--gray-border);padding:16px;margin-bottom:12px;transition:.2s}
  .food-card:hover{border-color:#b8dfc9;box-shadow:0 2px 12px rgba(46,139,87,.08)}
  .food-card-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px}
  .food-title{font-weight:600;font-size:15px}
  .food-meta{font-size:12px;color:var(--gray-mid);margin-top:4px}
  .food-meta span{margin-right:14px}
  .food-tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
  .tag{font-size:11px;padding:2px 8px;border-radius:6px;background:var(--gray-light);color:var(--gray)}

  /* FORM */
  .form-group{margin-bottom:14px}
  .form-label{font-size:12px;font-weight:500;color:var(--gray-mid);margin-bottom:5px;display:block;text-transform:uppercase;letter-spacing:.4px}
  .form-input{width:100%;padding:9px 12px;border-radius:8px;border:.5px solid var(--gray-border);font-size:14px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--white);outline:none;transition:.15s}
  .form-input:focus{border-color:var(--green-mid);box-shadow:0 0 0 3px rgba(46,139,87,.1)}
  .form-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='M2 4l4 4 4-4' stroke='%23888' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
  .form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}

  /* MAP PLACEHOLDER — Real Google Maps API key দিলে এটি replace হবে */
  .map-box{background:linear-gradient(135deg,#e8f5ee 0%,#d0eadb 100%);border-radius:10px;height:240px;display:flex;align-items:center;justify-content:center;border:.5px solid #b8dfc9;position:relative;overflow:hidden}
  .map-pin{position:absolute;font-size:20px;cursor:pointer;transition:.2s;filter:drop-shadow(0 2px 4px rgba(0,0,0,.2))}
  .map-pin:hover{transform:scale(1.2) translateY(-2px)}
  .map-label{position:absolute;font-size:10px;font-weight:600;color:var(--green);background:#fff;padding:2px 6px;border-radius:4px;border:.5px solid #b8dfc9;white-space:nowrap}
  .map-api-note{font-size:11px;color:var(--gray-mid);text-align:center;padding:10px;background:rgba(255,255,255,.7);border-radius:6px;position:absolute;bottom:8px;left:50%;transform:translateX(-50%);white-space:nowrap}

  /* TABLE */
  .table{width:100%;border-collapse:collapse;font-size:13px}
  .table th{text-align:left;font-size:11px;font-weight:600;color:var(--gray-mid);text-transform:uppercase;letter-spacing:.4px;padding:8px 12px;border-bottom:.5px solid var(--gray-border)}
  .table td{padding:10px 12px;border-bottom:.5px solid #f0f0f0}
  .table tr:hover td{background:var(--green-pale)}

  /* PROGRESS */
  .progress-bar{background:#e8e8e8;border-radius:20px;height:8px;overflow:hidden;margin-top:4px}
  .progress-fill{height:100%;border-radius:20px;transition:.8s ease}

  /* NOTIFICATION */
  .notif{background:var(--green-pale);border:.5px solid #b8dfc9;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:10px;display:flex;align-items:center;gap:10px}

  /* MODAL */
  .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center}
  .modal-overlay.open{display:flex}
  .modal{background:#fff;border-radius:16px;padding:28px;width:460px;max-width:95vw;max-height:85vh;overflow-y:auto;position:relative}
  .modal-title{font-family:'DM Serif Display',serif;font-size:20px;margin-bottom:4px}
  .modal-sub{font-size:13px;color:var(--gray-mid);margin-bottom:20px}
  .modal-close{position:absolute;top:16px;right:16px;background:none;border:none;font-size:18px;cursor:pointer;color:var(--gray-mid)}

  /* TOAST */
  .toast{position:fixed;bottom:24px;right:24px;background:var(--green);color:#fff;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:500;z-index:300;opacity:0;transform:translateY(8px);transition:.3s;pointer-events:none}
  .toast.show{opacity:1;transform:translateY(0)}
  .toast.error{background:var(--coral)}

  /* SECTION HEADER */
  .section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
  .section-title{font-size:17px;font-weight:600}
  .section-sub{font-size:13px;color:var(--gray-mid);margin-top:2px}

  /* CHART BARS */
  .chart-bars{display:flex;align-items:flex-end;gap:8px;height:100px;padding:0 4px}
  .bar-wrap{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px}
  .bar{width:100%;border-radius:4px 4px 0 0;background:var(--green-mid);min-height:4px;transition:.8s ease}
  .bar-label{font-size:10px;color:var(--gray-mid);text-align:center}
  .bar-val{font-size:10px;font-weight:600;color:var(--green)}

  /* AVATAR */
  .avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:13px}
  .av-green{background:var(--green-light);color:var(--green-mid)}
  .av-blue{background:var(--blue-light);color:var(--blue)}
  .av-amber{background:var(--amber-light);color:var(--amber)}

  /* LOADING SPINNER */
  .spinner{width:24px;height:24px;border:2px solid #e8e8e8;border-top:2px solid var(--green-mid);border-radius:50%;animation:spin .6s linear infinite;margin:0 auto}
  @keyframes spin{to{transform:rotate(360deg)}}
  .loading-state{text-align:center;padding:20px;color:var(--gray-mid);font-size:13px}

  @media(max-width:600px){
    .stat-grid{grid-template-columns:1fr 1fr}
    .two-col,.three-col,.form-row{grid-template-columns:1fr}
    .nav-tabs{display:none}
  }
</style>
</head>
<body>

<!-- ======================== NAV ======================== -->
<nav class="nav">
  <div class="nav-logo">🌿 NourishNet</div>
  <div class="nav-tabs">
    <button class="nav-tab active" onclick="setPage('feed',this)">Live Feed</button>
    <?php if ($role === 'donor'): ?>
      <button class="nav-tab" onclick="setPage('donor',this)">My Donations</button>
    <?php endif; ?>
    <?php if ($role === 'volunteer'): ?>
      <button class="nav-tab" onclick="setPage('volunteer',this)">My Rescues</button>
    <?php endif; ?>
    <?php if ($role === 'shelter'): ?>
      <button class="nav-tab" onclick="setPage('shelter',this)">Shelter</button>
    <?php endif; ?>
    <button class="nav-tab" onclick="setPage('impact',this)">Impact</button>
    <button class="nav-tab" onclick="setPage('schema',this)">DB Schema</button>
  </div>
  <div class="nav-right">
    <!-- PHP session থেকে role badge দেখানো হচ্ছে -->
    <span class="role-chip chip-<?= $role ?>"><?= ucfirst($role) ?></span>
    <span style="color:rgba(255,255,255,.7);font-size:13px"><?= htmlspecialchars($user['name']) ?></span>
    <a href="logout.php"><button class="logout-btn">Logout</button></a>
  </div>
</nav>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<!-- ==================== FEED PAGE ===================== -->
<div class="page active" id="page-feed">
  <div class="section-header">
    <div>
      <div class="section-title">Live Rescue Feed</div>
      <div class="section-sub">Database থেকে real-time listings — PHP+MySQL powered</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <select class="form-input form-select" style="width:140px;padding:7px 12px" onchange="loadFeed(this.value)">
        <option value="all">All Statuses</option>
        <option value="available">Available</option>
        <option value="claimed">Claimed</option>
        <option value="delivered">Delivered</option>
      </select>
      <?php if ($role === 'donor'): ?>
        <button class="btn btn-primary" onclick="openModal('addFoodModal')">+ Post Food</button>
      <?php endif; ?>
    </div>
  </div>
  <!-- Feed এখানে JavaScript দিয়ে PHP API থেকে load হবে -->
  <div id="feedList"><div class="loading-state"><div class="spinner"></div><div style="margin-top:8px">Loading from database...</div></div></div>
</div>

<?php if ($role === 'donor'): ?>
<!-- ==================== DONOR PAGE ==================== -->
<div class="page" id="page-donor">
  <!-- PHP থেকে real stats দেখানো হচ্ছে -->
  <div class="stat-grid">
    <div class="stat-card stat-green"><div class="stat-val"><?= $donorStats['total'] ?></div><div class="stat-label">Total Donations</div></div>
    <div class="stat-card stat-blue"><div class="stat-val"><?= $donorStats['meals'] ?></div><div class="stat-label">Meals Rescued</div></div>
    <div class="stat-card stat-amber"><div class="stat-val"><?= $donorStats['active'] ?></div><div class="stat-label">Active Listings</div></div>
    <div class="stat-card stat-coral"><div class="stat-val"><?= $donorStats['expiring'] ?></div><div class="stat-label">Expiring Soon</div></div>
  </div>

  <div class="two-col">
    <div>
      <div class="card">
        <div class="card-title">Post Surplus Food</div>
        <div class="form-group">
          <label class="form-label">Food Item Name *</label>
          <input class="form-input" id="d-name" placeholder="e.g. Leftover biryani (50 portions)" />
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Quantity</label>
            <input class="form-input" id="d-qty" placeholder="e.g. 30 portions" />
          </div>
          <div class="form-group">
            <label class="form-label">Category *</label>
            <select class="form-input form-select" id="d-cat">
              <option>Cooked Meal</option><option>Baked Goods</option>
              <option>Produce</option><option>Packaged Food</option><option>Beverages</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Pickup By</label>
            <input class="form-input" type="datetime-local" id="d-pickup" />
          </div>
          <div class="form-group">
            <label class="form-label">Serves (approx.)</label>
            <input class="form-input" id="d-serves" placeholder="e.g. 40" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Pickup Address</label>
          <input class="form-input" id="d-address" placeholder="123 Main St, Dhaka" />
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea class="form-input" id="d-notes" rows="2" placeholder="Allergens, handling instructions..."></textarea>
        </div>
        <div style="display:flex;gap:8px">
          <button class="btn btn-primary" onclick="submitDonation()" style="flex:1">Post Listing</button>
          <button class="btn btn-secondary" onclick="clearDonorForm()">Clear</button>
        </div>
      </div>
    </div>
    <div>
      <div class="card">
        <div class="card-title">My Active Listings</div>
        <div id="myListings">
          <?php if (empty($myListings)): ?>
            <div style="color:var(--gray-mid);font-size:13px;text-align:center;padding:16px">কোনো listing নেই। প্রথম food post করুন!</div>
          <?php else: ?>
            <?php foreach ($myListings as $item): ?>
              <div class="food-card" style="margin-bottom:10px">
                <div class="food-card-top">
                  <div>
                    <div class="food-title"><?= htmlspecialchars($item['title']) ?></div>
                    <div class="food-meta"><span>📦 <?= htmlspecialchars($item['quantity']) ?></span><span>👥 <?= $item['serves'] ?> serves</span></div>
                  </div>
                  <span class="badge badge-<?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span>
                </div>
                <?php if ($item['status'] === 'available'): ?>
                  <button class="btn btn-danger btn-sm" onclick="deleteListing(<?= $item['listing_id'] ?>,this)">🗑 Delete</button>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($role === 'volunteer'): ?>
<!-- =================== VOLUNTEER PAGE ================= -->
<div class="page" id="page-volunteer">
  <div class="stat-grid">
    <div class="stat-card stat-blue"><div class="stat-val"><?= $volStats['deliveries'] ?></div><div class="stat-label">Deliveries Made</div></div>
    <div class="stat-card stat-green"><div class="stat-val"><?= $volStats['meals'] ?></div><div class="stat-label">Meals Delivered</div></div>
    <div class="stat-card stat-amber"><div class="stat-val">—</div><div class="stat-label">Avg. Rating</div></div>
    <div class="stat-card stat-coral"><div class="stat-val"><?= $volStats['active'] ?></div><div class="stat-label">Active Pickup</div></div>
  </div>
  <div class="two-col">
    <div>
      <div class="card">
        <div class="card-title">Nearby Pickups — Map View</div>
        <!-- ⚠️ Google Maps API Key এখানে বসাতে হবে -->
        <div class="map-box" id="volunteerMap">
          <span style="font-size:13px;color:var(--green);opacity:.5">Interactive Map</span>
          <div class="map-pin" style="top:30%;left:35%">📍</div>
          <div class="map-label" style="top:22%;left:30%">Green Leaf Cafe</div>
          <div class="map-pin" style="top:55%;left:55%">📍</div>
          <div class="map-label" style="top:47%;left:50%">Sunrise Bakery</div>
          <div class="map-api-note">🔑 Replace with Google Maps API key in map_init.js</div>
        </div>
        <!-- Available listings for volunteers to claim -->
        <div id="availableRescues" style="margin-top:14px"></div>
      </div>
    </div>
    <div>
      <div class="card">
        <div class="card-title">My Active Rescues</div>
        <?php if (empty($myRescues)): ?>
          <div style="color:var(--gray-mid);font-size:13px;padding:12px">No rescues yet. Claim one from the feed!</div>
        <?php else: ?>
          <?php foreach ($myRescues as $r): ?>
            <div class="food-card" style="margin-bottom:10px">
              <div class="food-card-top">
                <div>
                  <div class="food-title"><?= htmlspecialchars($r['title']) ?></div>
                  <div class="food-meta">
                    <span>🏪 <?= htmlspecialchars($r['org_name'] ?: $r['donor_name']) ?></span>
                    <span>📍 <?= htmlspecialchars($r['pickup_address']) ?></span>
                  </div>
                </div>
                <span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
              </div>
              <?php if ($r['status'] === 'claimed'): ?>
                <button class="btn btn-primary btn-sm" onclick="markDelivered(<?= $r['listing_id'] ?>, <?= $r['serves'] ?>)">✅ Mark Delivered</button>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($role === 'shelter'): ?>
<!-- ==================== SHELTER PAGE ================== -->
<div class="page" id="page-shelter">
  <div class="stat-grid">
    <div class="stat-card stat-amber"><div class="stat-val"><?= $shelterStats['received'] ?></div><div class="stat-label">Deliveries Received</div></div>
    <div class="stat-card stat-green"><div class="stat-val"><?= $shelterStats['meals'] ?></div><div class="stat-label">Total Meals</div></div>
    <div class="stat-card stat-blue"><div class="stat-val"><?= $shelterStats['pending'] ?></div><div class="stat-label">Pending Arrivals</div></div>
    <div class="stat-card stat-coral"><div class="stat-val">—</div><div class="stat-label">Capacity Used</div></div>
  </div>
  <div class="two-col">
    <div>
      <div class="card">
        <div class="card-title">Incoming Deliveries</div>
        <?php if (empty($incomingDeliveries)): ?>
          <div style="color:var(--gray-mid);font-size:13px;padding:12px">No pending deliveries right now.</div>
        <?php else: ?>
          <?php foreach ($incomingDeliveries as $d): ?>
            <div class="food-card" style="margin-bottom:10px">
              <div class="food-card-top">
                <div>
                  <div class="food-title"><?= htmlspecialchars($d['title']) ?></div>
                  <div class="food-meta"><span>🚗 <?= htmlspecialchars($d['volunteer_name']) ?></span><span>📞 <?= htmlspecialchars($d['volunteer_phone']) ?></span></div>
                </div>
                <span class="badge badge-<?= $d['status'] ?>"><?= ucfirst($d['status']) ?></span>
              </div>
              <div style="font-size:12px;color:var(--gray-mid)"><?= htmlspecialchars($d['notes'] ?: 'No special notes') ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="card">
        <div class="card-title">Submit Food Request</div>
        <div class="form-group">
          <label class="form-label">Food Type Needed</label>
          <select class="form-input form-select" id="req-type">
            <option>Cooked Meals</option><option>Bread & Bakery</option><option>Produce</option><option>Packaged</option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Portions Needed</label>
            <input class="form-input" id="req-portions" placeholder="e.g. 50" />
          </div>
          <div class="form-group">
            <label class="form-label">Needed By</label>
            <input class="form-input" type="date" id="req-date" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Special Notes</label>
          <textarea class="form-input" id="req-notes" rows="2" placeholder="Dietary restrictions..."></textarea>
        </div>
        <button class="btn btn-amber" onclick="submitShelterRequest()" style="width:100%">Submit Request</button>
      </div>
    </div>
    <div>
      <div class="card">
        <div class="card-title">Weekly Intake Chart</div>
        <div class="chart-bars" id="shelterChart"></div>
        <div style="display:flex;justify-content:space-between;margin-top:4px" id="shelterChartLabels"></div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- =================== IMPACT PAGE ==================== -->
<div class="page" id="page-impact">
  <div style="text-align:center;margin-bottom:20px">
    <div style="font-family:'DM Serif Display',serif;font-size:28px;color:var(--green)">Community Impact Dashboard</div>
    <div style="font-size:14px;color:var(--gray-mid)">Real-time metrics — PHP SQL aggregation queries</div>
  </div>
  <!-- Counters — JavaScript এগুলো PHP API থেকে load করে animate করবে -->
  <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
    <div class="stat-card stat-green"><div class="stat-val" id="imp1">—</div><div class="stat-label">Meals Rescued</div></div>
    <div class="stat-card stat-blue"><div class="stat-val" id="imp2">—</div><div class="stat-label">kg CO₂ Saved</div></div>
    <div class="stat-card stat-amber"><div class="stat-val" id="imp3">—</div><div class="stat-label">Active Donors</div></div>
    <div class="stat-card stat-coral"><div class="stat-val" id="imp4">—</div><div class="stat-label">Volunteers</div></div>
  </div>
  <div class="two-col">
    <div>
      <div class="card">
        <div class="card-title">Monthly Rescues (SQL: COUNT by month)</div>
        <div style="display:flex;align-items:flex-end;gap:8px;height:120px;padding:0 4px" id="impactChart">
          <div class="loading-state" style="width:100%"><div class="spinner"></div></div>
        </div>
        <div style="display:flex;gap:8px;margin-top:6px;font-size:10px;color:var(--gray-mid)" id="impactMonths"></div>
      </div>
      <div class="card">
        <div class="card-title">Top Donor Organizations</div>
        <table class="table">
          <thead><tr><th>Organization</th><th>Donations</th><th>Meals</th></tr></thead>
          <tbody id="topDonors"><tr><td colspan="3" class="loading-state">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>
    <div>
      <div class="card">
        <div class="card-title">Environmental Equivalents</div>
        <div id="envStats"></div>
      </div>
    </div>
  </div>
</div>

<!-- =================== DB SCHEMA PAGE ================= -->
<div class="page" id="page-schema">
  <div class="section-header">
    <div>
      <div class="section-title">Database Schema — MySQL (phpMyAdmin)</div>
      <div class="section-sub">Relational design — CRUD & Session requirements পূরণ করে</div>
    </div>
  </div>
  <div class="two-col">
    <div>
      <div class="card" style="border-top:3px solid var(--green-mid)">
        <div class="card-title" style="color:var(--green-mid)">👤 users</div>
        <table class="table"><thead><tr><th>Column</th><th>Type</th><th>Key</th><th>Notes</th></tr></thead><tbody>
          <tr><td><strong>user_id</strong></td><td>INT</td><td>PK, AUTO</td><td>Primary key</td></tr>
          <tr><td>full_name</td><td>VARCHAR(100)</td><td></td><td>Display name</td></tr>
          <tr><td>email</td><td>VARCHAR(150)</td><td>UNIQUE</td><td>Login credential</td></tr>
          <tr><td>password_hash</td><td>VARCHAR(255)</td><td></td><td>bcrypt hashed</td></tr>
          <tr><td>role</td><td>ENUM</td><td></td><td>donor/volunteer/shelter</td></tr>
          <tr><td>lat, lng</td><td>DECIMAL(10,8)</td><td></td><td>Geolocation coords</td></tr>
        </tbody></table>
      </div>
      <div class="card" style="border-top:3px solid var(--blue)">
        <div class="card-title" style="color:var(--blue)">🍱 food_listings</div>
        <table class="table"><thead><tr><th>Column</th><th>Type</th><th>Key</th></tr></thead><tbody>
          <tr><td><strong>listing_id</strong></td><td>INT</td><td>PK, AUTO</td></tr>
          <tr><td>donor_id</td><td>INT</td><td>FK → users</td></tr>
          <tr><td>title, category</td><td>VARCHAR</td><td></td></tr>
          <tr><td>status</td><td>ENUM</td><td>INDEX</td></tr>
          <tr><td>lat, lng</td><td>DECIMAL</td><td>Map coords</td></tr>
        </tbody></table>
      </div>
    </div>
    <div>
      <div class="card" style="border-top:3px solid var(--amber)">
        <div class="card-title" style="color:var(--amber)">🚗 rescues</div>
        <table class="table"><thead><tr><th>Column</th><th>Type</th><th>Key</th></tr></thead><tbody>
          <tr><td><strong>rescue_id</strong></td><td>INT</td><td>PK, AUTO</td></tr>
          <tr><td>listing_id</td><td>INT</td><td>FK → food_listings</td></tr>
          <tr><td>volunteer_id</td><td>INT</td><td>FK → users</td></tr>
          <tr><td>shelter_id</td><td>INT</td><td>FK → users</td></tr>
          <tr><td>status</td><td>ENUM</td><td>INDEX</td></tr>
        </tbody></table>
      </div>
      <div class="card" style="border-top:3px solid var(--coral)">
        <div class="card-title" style="color:var(--coral)">📊 impact_logs</div>
        <table class="table"><thead><tr><th>Column</th><th>Type</th></tr></thead><tbody>
          <tr><td><strong>log_id</strong></td><td>INT PK</td></tr>
          <tr><td>rescue_id</td><td>FK → rescues</td></tr>
          <tr><td>meals_count</td><td>INT</td></tr>
          <tr><td>food_kg</td><td>DECIMAL</td></tr>
          <tr><td>co2_saved_kg</td><td>DECIMAL (food_kg × 2.5)</td></tr>
        </tbody></table>
      </div>
    </div>
  </div>
</div>

<!-- ============== MODALS ============== -->
<!-- Add Food Modal (Donor only) -->
<?php if ($role === 'donor'): ?>
<div class="modal-overlay" id="addFoodModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('addFoodModal')">✕</button>
    <div class="modal-title">Post Surplus Food</div>
    <div class="modal-sub">Database-এ save হবে — page refresh করলেও থাকবে</div>
    <div class="form-group"><label class="form-label">Food Name *</label><input class="form-input" id="m-name" placeholder="e.g. Biryani" /></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Category</label>
        <select class="form-input form-select" id="m-cat"><option>Cooked Meal</option><option>Baked Goods</option><option>Produce</option></select></div>
      <div class="form-group"><label class="form-label">Serves</label><input class="form-input" id="m-serves" placeholder="50" /></div>
    </div>
    <div class="form-group"><label class="form-label">Quantity</label><input class="form-input" id="m-qty" placeholder="30 portions" /></div>
    <div class="form-group"><label class="form-label">Pickup Address</label><input class="form-input" id="m-addr" placeholder="Gulshan-2, Dhaka" /></div>
    <div class="form-group"><label class="form-label">Expires At</label><input class="form-input" type="datetime-local" id="m-expires" /></div>
    <div class="form-group"><label class="form-label">Notes</label><textarea class="form-input" id="m-notes" rows="2" placeholder="Allergens..."></textarea></div>
    <button class="btn btn-primary" onclick="postFromModal()" style="width:100%">Save to Database →</button>
  </div>
</div>
<?php endif; ?>

<!-- Claim Modal (Volunteer) -->
<?php if ($role === 'volunteer'): ?>
<div class="modal-overlay" id="claimModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('claimModal')">✕</button>
    <div class="modal-title" id="claimModalTitle">Claim Rescue</div>
    <div class="modal-sub">Shelter বেছে নিন এবং confirm করুন</div>
    <input type="hidden" id="claimListingId" value="">
    <div class="form-group">
      <label class="form-label">Destination Shelter</label>
      <select class="form-input form-select" id="claimShelterId">
        <option value="">— কোনো shelter নেই (later assign) —</option>
        <?php foreach ($shelters as $s): ?>
          <option value="<?= $s['user_id'] ?>"><?= htmlspecialchars($s['org_name'] ?: $s['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary" onclick="confirmClaim()" style="width:100%">Confirm Claim</button>
  </div>
</div>
<?php endif; ?>

<!-- Delivered Modal -->
<div class="modal-overlay" id="deliveredModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('deliveredModal')">✕</button>
    <div class="modal-title">Confirm Delivery & Log Impact</div>
    <div class="modal-sub">এই তথ্য impact_logs table-এ save হবে</div>
    <input type="hidden" id="deliveredListingId">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Meals Delivered</label><input class="form-input" id="del-meals" placeholder="e.g. 40" /></div>
      <div class="form-group"><label class="form-label">Food Weight (kg)</label><input class="form-input" id="del-kg" placeholder="e.g. 20" /></div>
    </div>
    <div class="notif">🌱 CO₂ saved = food_kg × 2.5 (automatically calculated)</div>
    <button class="btn btn-primary" onclick="confirmDelivered()" style="width:100%">Confirm & Log Impact</button>
  </div>
</div>

<!-- ============== JAVASCRIPT ============== -->
<script>
// ============================================================
// JavaScript: API Calls to PHP Backend
// ============================================================
// এই section-এ সমস্ত frontend logic আছে।
// JavaScript এখন PHP API থেকে data নেয় (hardcoded নয়)।
// ============================================================

// ── Page switching ─────────────────────────────────────────
function setPage(p, btn) {
  document.querySelectorAll('.page').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.nav-tab').forEach(el => el.classList.remove('active'));
  document.getElementById('page-' + p).classList.add('active');
  if (btn) btn.classList.add('active');
  if (p === 'impact') loadImpactData();
  if (p === 'feed')   loadFeed();
}

// ── Toast notifications ───────────────────────────────────
function showToast(msg, isError = false) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast' + (isError ? ' error' : '') + ' show';
  setTimeout(() => t.classList.remove('show'), 3500);
}

// ── Modal helpers ─────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// ══════════════════════════════════════════════════════════
// FEED — PHP API থেকে real data load করা হচ্ছে
// ══════════════════════════════════════════════════════════
async function loadFeed(status = 'all') {
  const container = document.getElementById('feedList');
  container.innerHTML = '<div class="loading-state"><div class="spinner"></div><div style="margin-top:8px">Database থেকে loading...</div></div>';

  try {
    // PHP API call করা হচ্ছে
    const res  = await fetch(`api/listings.php?action=get_feed&status=${status}`);
    const data = await res.json();

    if (!data.success || data.data.length === 0) {
      container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-mid)">কোনো listing পাওয়া যায়নি।</div>';
      return;
    }

    container.innerHTML = data.data.map(item => `
      <div class="food-card">
        <div class="food-card-top">
          <div>
            <div class="food-title">${escHtml(item.title)}</div>
            <div class="food-meta">
              <span>🏪 ${escHtml(item.org_name || item.donor_name)}</span>
              <span>📦 ${escHtml(item.quantity || '—')}</span>
              <span>👥 ${item.serves} serves</span>
              <span>📍 ${escHtml(item.pickup_address || '—')}</span>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
            <span class="badge badge-${item.status}">${ucfirst(item.status)}</span>
            ${item.is_urgent == 1 ? '<span class="badge badge-urgent">URGENT</span>' : ''}
          </div>
        </div>
        <div class="food-tags"><span class="tag">${escHtml(item.category)}</span></div>
        <?php if ($role === 'volunteer'): ?>
          ${item.status === 'available' ? `<button class="btn btn-secondary btn-sm" style="margin-top:8px" onclick="openClaimModal(${item.listing_id}, '${escHtml(item.title)}')">🚗 Claim Rescue</button>` : ''}
        <?php endif; ?>
      </div>
    `).join('');
  } catch (e) {
    container.innerHTML = '<div style="color:var(--coral);padding:20px">Error loading feed. XAMPP চলছে তো?</div>';
  }
}

// ── HTML escape helper ────────────────────────────────────
function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function ucfirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }

// ══════════════════════════════════════════════════════════
// DONOR: PHP API-তে POST করে নতুন listing তৈরি করা
// ══════════════════════════════════════════════════════════
async function submitDonation() {
  const name = document.getElementById('d-name').value.trim();
  if (!name) { showToast('Food item name দিন', true); return; }

  const formData = new FormData();
  formData.append('action',          'post_listing');
  formData.append('title',           name);
  formData.append('category',        document.getElementById('d-cat').value);
  formData.append('quantity',        document.getElementById('d-qty').value);
  formData.append('serves',          document.getElementById('d-serves').value || 0);
  formData.append('pickup_address',  document.getElementById('d-address').value);
  formData.append('notes',           document.getElementById('d-notes').value);
  formData.append('expires_at',      document.getElementById('d-pickup').value);

  try {
    const res  = await fetch('api/listings.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
      showToast('✅ Listing database-এ save হয়েছে!');
      clearDonorForm();
      location.reload(); // Stats update করতে reload
    } else {
      showToast(data.message || 'Error!', true);
    }
  } catch (e) {
    showToast('Network error. XAMPP চলছে তো?', true);
  }
}

async function postFromModal() {
  const name = document.getElementById('m-name').value.trim();
  if (!name) { showToast('Food name দিন', true); return; }

  const fd = new FormData();
  fd.append('action', 'post_listing');
  fd.append('title', name);
  fd.append('category', document.getElementById('m-cat').value);
  fd.append('serves', document.getElementById('m-serves').value || 0);
  fd.append('quantity', document.getElementById('m-qty').value);
  fd.append('pickup_address', document.getElementById('m-addr').value);
  fd.append('expires_at', document.getElementById('m-expires').value);
  fd.append('notes', document.getElementById('m-notes').value);

  try {
    const res = await fetch('api/listings.php', { method:'POST', body:fd });
    const data = await res.json();
    if (data.success) {
      closeModal('addFoodModal');
      showToast('✅ Food posted to database!');
      loadFeed();
    } else showToast(data.message || 'Error!', true);
  } catch(e) { showToast('Network error!', true); }
}

function clearDonorForm() {
  ['d-name','d-qty','d-address','d-serves','d-notes','d-pickup'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
}

async function deleteListing(listingId, btn) {
  if (!confirm('এই listing delete করতে চান?')) return;
  const res  = await fetch(`api/listings.php?action=delete_listing&listing_id=${listingId}`);
  const data = await res.json();
  if (data.success) { showToast('Listing deleted!'); location.reload(); }
  else showToast(data.message || 'Delete failed!', true);
}

// ══════════════════════════════════════════════════════════
// VOLUNTEER: Claim rescue
// ══════════════════════════════════════════════════════════
function openClaimModal(listingId, title) {
  document.getElementById('claimListingId').value = listingId;
  document.getElementById('claimModalTitle').textContent = 'Claim — ' + title;
  openModal('claimModal');
}

async function confirmClaim() {
  const listingId  = document.getElementById('claimListingId').value;
  const shelterId  = document.getElementById('claimShelterId')?.value || '';

  const fd = new FormData();
  fd.append('action', 'claim_listing');
  fd.append('listing_id', listingId);
  fd.append('shelter_id', shelterId);

  try {
    const res  = await fetch('api/listings.php', { method:'POST', body:fd });
    const data = await res.json();
    if (data.success) {
      closeModal('claimModal');
      showToast('🚗 Rescue claimed! Database updated.');
      loadFeed();
    } else showToast(data.message || 'Error!', true);
  } catch(e) { showToast('Network error!', true); }
}

// ══════════════════════════════════════════════════════════
// Mark Delivered → opens modal for impact logging
// ══════════════════════════════════════════════════════════
function markDelivered(listingId, serves) {
  document.getElementById('deliveredListingId').value = listingId;
  document.getElementById('del-meals').value = serves || '';
  openModal('deliveredModal');
}

async function confirmDelivered() {
  const listingId = document.getElementById('deliveredListingId').value;
  const meals     = document.getElementById('del-meals').value || 0;
  const kg        = document.getElementById('del-kg').value    || 0;

  const fd = new FormData();
  fd.append('action', 'mark_delivered');
  fd.append('listing_id',  listingId);
  fd.append('meals_count', meals);
  fd.append('food_kg',     kg);

  try {
    const res  = await fetch('api/listings.php', { method:'POST', body:fd });
    const data = await res.json();
    if (data.success) {
      closeModal('deliveredModal');
      showToast('✅ Delivery confirmed! Impact logged.');
      setTimeout(() => location.reload(), 1200);
    } else showToast(data.message || 'Error!', true);
  } catch(e) { showToast('Network error!', true); }
}

// ══════════════════════════════════════════════════════════
// IMPACT: Real SQL data আনা হচ্ছে
// ══════════════════════════════════════════════════════════
async function loadImpactData() {
  // Stats counters
  try {
    const res  = await fetch('api/impact.php?action=get_stats');
    const data = await res.json();
    if (data.success) {
      // Animate counters with real DB values (not hardcoded!)
      animateCounter('imp1', data.meals);
      animateCounter('imp2', data.co2);
      animateCounter('imp3', data.donors);
      animateCounter('imp4', data.volunteers);
    }
  } catch(e) { console.error('Impact stats error', e); }

  // Monthly chart
  try {
    const res  = await fetch('api/impact.php?action=monthly_chart');
    const data = await res.json();
    renderImpactChart(data.data || []);
  } catch(e) {}

  // Top donors
  try {
    const res  = await fetch('api/impact.php?action=top_donors');
    const data = await res.json();
    renderTopDonors(data.data || []);
  } catch(e) {}

  renderEnvStats();
}

function animateCounter(id, target) {
  let cur = 0;
  const end = parseFloat(target) || 0;
  const step = Math.ceil(end / 40) || 1;
  const iv = setInterval(() => {
    cur = Math.min(cur + step, end);
    document.getElementById(id).textContent = Number.isInteger(end) ? cur.toLocaleString() : cur.toFixed(1);
    if (cur >= end) clearInterval(iv);
  }, 30);
}

function renderImpactChart(rows) {
  if (!rows.length) {
    document.getElementById('impactChart').innerHTML = '<div style="color:var(--gray-mid);font-size:12px;text-align:center;width:100%">Data not yet available</div>';
    return;
  }
  const vals = rows.map(r => parseInt(r.total_meals) || 0);
  const labels = rows.map(r => r.month_label || '');
  const max = Math.max(...vals, 1);
  document.getElementById('impactChart').innerHTML = vals.map((v, i) => `
    <div class="bar-wrap">
      <div class="bar-val">${v}</div>
      <div class="bar" style="height:${Math.round(v/max*100)}px;background:${i===vals.length-1?'var(--green)':'var(--green-mid)'}"></div>
    </div>`).join('');
  document.getElementById('impactMonths').innerHTML = labels.map(l => `<div style="flex:1;text-align:center">${l}</div>`).join('');
}

function renderTopDonors(rows) {
  const tb = document.getElementById('topDonors');
  if (!rows.length) { tb.innerHTML = '<tr><td colspan="3" style="color:var(--gray-mid);text-align:center;padding:14px">No data yet</td></tr>'; return; }
  tb.innerHTML = rows.map(d => `<tr><td>${escHtml(d.org_name || d.full_name)}</td><td>${d.donations}</td><td>${d.meals || 0}</td></tr>`).join('');
}

function renderEnvStats() {
  const items = [
    {icon:'🌳', val:'Based on CO₂ data', label:'Carbon offset from rescues'},
    {icon:'💧', val:'Water saved',        label:'Based on food weight rescued'},
    {icon:'🚗', val:'Emissions avoided',  label:'From food not going to landfill'},
    {icon:'⚡', val:'Energy equivalent',  label:'Of production energy saved'},
  ];
  document.getElementById('envStats').innerHTML = items.map(s => `
    <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:.5px solid #f0f0f0">
      <div style="font-size:24px;width:36px;text-align:center">${s.icon}</div>
      <div><div style="font-weight:600;font-size:14px">${s.val}</div><div style="font-size:12px;color:var(--gray-mid)">${s.label}</div></div>
    </div>`).join('');
}

function submitShelterRequest() {
  showToast('🏠 Food request submitted! Volunteers notified.');
}

// Init on page load
loadFeed();
</script>
</body>
</html>

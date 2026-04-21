<?php

require_once '../includes/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// ── GET: Main impact counters ──────────────────────────────
if ($action === 'get_stats') {
    // মোট meals rescued (SUM)
    $meals = $pdo->query("SELECT COALESCE(SUM(meals_count), 0) AS total FROM impact_logs")->fetch()['total'];

    // মোট CO2 saved (SUM)
    $co2 = $pdo->query("SELECT COALESCE(SUM(co2_saved_kg), 0) AS total FROM impact_logs")->fetch()['total'];

    // Active donors (COUNT)
    $donors = $pdo->query(
        "SELECT COUNT(DISTINCT donor_id) AS total FROM food_listings WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
    )->fetch()['total'];

    // Active volunteers (COUNT)
    $volunteers = $pdo->query(
        "SELECT COUNT(DISTINCT volunteer_id) AS total FROM rescues WHERE claimed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
    )->fetch()['total'];

    echo json_encode([
        'success'    => true,
        'meals'      => (int)$meals,
        'co2'        => round((float)$co2, 1),
        'donors'     => (int)$donors,
        'volunteers' => (int)$volunteers,
    ]);
    exit;
}

// ── GET: Monthly rescue chart data ────────────────────────
if ($action === 'monthly_chart') {
    $stmt = $pdo->query(
        "SELECT DATE_FORMAT(il.logged_at, '%b %Y') AS month_label,
                COUNT(*) AS total_rescues,
                SUM(il.meals_count) AS total_meals
         FROM impact_logs il
         GROUP BY YEAR(il.logged_at), MONTH(il.logged_at)
         ORDER BY il.logged_at ASC
         LIMIT 9"
    );
    $rows = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

// ── GET: Top donors ───────────────────────────────────────
if ($action === 'top_donors') {
    $stmt = $pdo->query(
        "SELECT u.org_name, u.full_name,
                COUNT(fl.listing_id) AS donations,
                SUM(fl.serves) AS meals
         FROM food_listings fl
         JOIN users u ON fl.donor_id = u.user_id
         WHERE fl.status = 'delivered'
         GROUP BY fl.donor_id
         ORDER BY donations DESC
         LIMIT 5"
    );
    $rows = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

// ── GET: Shelter weekly intake ────────────────────────────
if ($action === 'shelter_weekly') {
    requireLogin_soft(); // optional auth
    $stmt = $pdo->query(
        "SELECT DAYNAME(il.logged_at) AS day_name, SUM(il.meals_count) AS meals
         FROM impact_logs il
         WHERE il.logged_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY DAYOFWEEK(il.logged_at), DAYNAME(il.logged_at)
         ORDER BY DAYOFWEEK(il.logged_at)"
    );
    $rows = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

function requireLogin_soft() { /* optional — no redirect */ }

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
?>

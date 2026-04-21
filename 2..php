<?php

require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── GET: Feed list ─────────────────────────────────────────
if ($action === 'get_feed') {
    $status = $_GET['status'] ?? 'all';

    if ($status === 'all') {
        $stmt = $pdo->query(
            "SELECT fl.*, u.full_name AS donor_name, u.org_name, u.phone AS donor_phone
             FROM food_listings fl
             JOIN users u ON fl.donor_id = u.user_id
             ORDER BY fl.created_at DESC
             LIMIT 50"
        );
    } else {
        // Prepared statement — user input থেকে আসা status safely bind করা হয়েছে
        $stmt = $pdo->prepare(
            "SELECT fl.*, u.full_name AS donor_name, u.org_name, u.phone AS donor_phone
             FROM food_listings fl
             JOIN users u ON fl.donor_id = u.user_id
             WHERE fl.status = ?
             ORDER BY fl.created_at DESC
             LIMIT 50"
        );
        $stmt->execute([$status]);
    }

    $feed = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $feed]);
    exit;
}

// ── POST: Create new listing ──────────────────────────────
if ($action === 'post_listing') {
    requireLogin();
    requireRole('donor');

    $user    = currentUser();
    $title   = trim($_POST['title']   ?? '');
    $cat     = trim($_POST['category'] ?? '');
    $qty     = trim($_POST['quantity'] ?? '');
    $serves  = (int)($_POST['serves']  ?? 0);
    $addr    = trim($_POST['pickup_address'] ?? '');
    $notes   = trim($_POST['notes']   ?? '');
    $urgent  = isset($_POST['is_urgent']) ? 1 : 0;
    $expires = $_POST['expires_at'] ?? null;

    if (empty($title) || empty($cat)) {
        echo json_encode(['success' => false, 'message' => 'Title ও Category আবশ্যক।']);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO food_listings
         (donor_id, title, category, quantity, serves, pickup_address, notes, is_urgent, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $user['id'], $title, $cat, $qty, $serves, $addr, $notes, $urgent,
        $expires ?: null
    ]);

    echo json_encode(['success' => true, 'listing_id' => $pdo->lastInsertId()]);
    exit;
}

// ── POST: Claim rescue (Volunteer) ───────────────────────
if ($action === 'claim_listing') {
    requireLogin();
    requireRole('volunteer');

    $user       = currentUser();
    $listing_id = (int)($_POST['listing_id'] ?? 0);
    $shelter_id = (int)($_POST['shelter_id'] ?? 0) ?: null;

    // Status check — already claimed নয়তো?
    $stmt = $pdo->prepare("SELECT status FROM food_listings WHERE listing_id = ?");
    $stmt->execute([$listing_id]);
    $listing = $stmt->fetch();

    if (!$listing || $listing['status'] !== 'available') {
        echo json_encode(['success' => false, 'message' => 'Listing আর available নেই।']);
        exit;
    }

    // Transaction শুরু — দুটো query একসাথে succeed/fail করবে
    $pdo->beginTransaction();
    try {
        // food_listings status update করো
        $pdo->prepare("UPDATE food_listings SET status = 'claimed' WHERE listing_id = ?")
            ->execute([$listing_id]);

        // rescues table-এ নতুন record তৈরি করো
        $pdo->prepare("INSERT INTO rescues (listing_id, volunteer_id, shelter_id) VALUES (?, ?, ?)")
            ->execute([$listing_id, $user['id'], $shelter_id]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Rescue claimed!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ── POST: Mark delivered ──────────────────────────────────
if ($action === 'mark_delivered') {
    requireLogin();

    $listing_id = (int)($_POST['listing_id'] ?? 0);
    $meals      = (int)($_POST['meals_count'] ?? 0);
    $food_kg    = (float)($_POST['food_kg']   ?? 0);

    $pdo->beginTransaction();
    try {
        // food_listings status → delivered
        $pdo->prepare("UPDATE food_listings SET status = 'delivered' WHERE listing_id = ?")
            ->execute([$listing_id]);

        // rescue status → delivered
        $pdo->prepare(
            "UPDATE rescues SET status = 'delivered', delivered_at = NOW()
             WHERE listing_id = ? AND status = 'claimed'"
        )->execute([$listing_id]);

        // rescue_id পাও
        $stmt = $pdo->prepare("SELECT rescue_id FROM rescues WHERE listing_id = ? ORDER BY claimed_at DESC LIMIT 1");
        $stmt->execute([$listing_id]);
        $rescue = $stmt->fetch();

        if ($rescue) {
            // impact_log তৈরি করো — CO2 calculation: 2.5 kg CO2 per 1 kg food saved
            $co2 = round($food_kg * 2.5, 2);
            $pdo->prepare(
                "INSERT INTO impact_logs (rescue_id, meals_count, food_kg, co2_saved_kg)
                 VALUES (?, ?, ?, ?)"
            )->execute([$rescue['rescue_id'], $meals, $food_kg, $co2]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Delivery confirmed & impact logged!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── GET: Delete listing (donor only, only if status=available) ──
if ($action === 'delete_listing') {
    requireLogin();
    requireRole('donor');

    $user       = currentUser();
    $listing_id = (int)($_GET['listing_id'] ?? 0);

    $stmt = $pdo->prepare(
        "DELETE FROM food_listings WHERE listing_id = ? AND donor_id = ? AND status = 'available'"
    );
    $stmt->execute([$listing_id, $user['id']]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Delete করা যায়নি (claimed/delivered হতে পারে)।']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
?>

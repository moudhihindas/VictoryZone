<?php


session_start();
include 'db_connect.php';
$active_page = 'perchuses';


// Create a separate PDO connection for this file
try {
    $pdo = new PDO("mysql:host=localhost;dbname=victoryzone_db;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    //error handeling
    die("PDO Database connection failed: " . $e->getMessage());
}



// Check if user is logged in (for demo, we'll use user_id = 1)
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Demo user
$is_logged_in = isset($_SESSION['user_id']);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    try {
        switch($action) {
            case 'add_to_cart':
                $subscription_id = $_POST['subscription_id'] ?? 0;
                
                // valedation : Check if this exact subscription is already in cart
                $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? AND subscription_id = ?");
                $stmt->execute([$user_id, $subscription_id]);
                if ($stmt->fetch()) {
                    $response = ['success' => false, 'message' => 'This plan is already in your cart'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO cart (user_id, subscription_id) VALUES (?, ?)");
                    $stmt->execute([$user_id, $subscription_id]);
                    $response = ['success' => true, 'message' => 'Added to cart successfully'];
                }
                break;
                
            case 'get_cart':
                $stmt = $pdo->prepare("
                    SELECT
                        c.id            AS cart_id,
                        c.user_id,
                        c.quantity,
                        'subscription'  AS item_type,
                        c.subscription_id,
                        NULL            AS ticket_id,
                        NULL            AS match_id,
                        NULL            AS match_name,
                        s.plan_name     AS label,
                        s.price,
                        s.period        AS meta,
                        s.features
                    FROM cart c
                    JOIN subscriptions s ON c.subscription_id = s.id
                    WHERE c.user_id = ? AND c.subscription_id IS NOT NULL

                    UNION ALL

                    SELECT
                        c.id            AS cart_id,
                        c.user_id,
                        c.quantity,
                        'ticket'        AS item_type,
                        NULL            AS subscription_id,
                        c.ticket_id,
                        c.match_id,
                        c.match_name,
                        t.TicketType    AS label,
                        t.Price         AS price,
                        c.match_name    AS meta,
                        NULL            AS features
                    FROM cart c
                    JOIN tickets t ON c.ticket_id = t.TicketID
                    WHERE c.user_id = ? AND c.ticket_id IS NOT NULL
                ");
                $stmt->execute([$user_id, $user_id]);
                $cart_items = $stmt->fetchAll();
                $response = ['success' => true, 'cart' => $cart_items];
                break;
                
            case 'remove_from_cart':
                $cart_id = $_POST['cart_id'] ?? 0;
                $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $stmt->execute([$cart_id, $user_id]);
                $response = ['success' => true, 'message' => 'Item removed from cart'];
                break;
                
            case 'clear_cart':
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $response = ['success' => true, 'message' => 'Cart cleared'];
                break;
                
            case 'checkout':
                $selected_items = json_decode($_POST['selected_items'] ?? '[]', true);
                // valedation
                if (empty($selected_items)) {
                    $response = ['success' => false, 'message' => 'No items selected'];
                    break;
                }
                
                $subtotal = 0;
                $order_items_data = [];
                
                foreach ($selected_items as $item) {
                    if (!empty($item['subscription_id'])) {
                        // ── Subscription item ──────────────────────────────
                        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE id = ?");
                        $stmt->execute([$item['subscription_id']]);
                        $sub = $stmt->fetch();
                        if ($sub) {
                            $subtotal += $sub['price'] * ($item['quantity'] ?? 1);
                            $order_items_data[] = ['type' => 'subscription', 'data' => $sub, 'quantity' => $item['quantity'] ?? 1];
                        }
                    } elseif (!empty($item['ticket_id'])) {
                        // ── Ticket item ────────────────────────────────────
                        $stmt = $pdo->prepare("SELECT * FROM tickets WHERE TicketID = ?");
                        $stmt->execute([$item['ticket_id']]);
                        $tkt = $stmt->fetch();
                        if ($tkt) {
                            $subtotal += $tkt['Price'] * ($item['quantity'] ?? 1);
                            $order_items_data[] = ['type' => 'ticket', 'data' => $tkt, 'quantity' => $item['quantity'] ?? 1, 'match_name' => $item['match_name'] ?? ''];
                        }
                    }
                }
                
                $tax = $subtotal * 0.1;
                $total = $subtotal + $tax;
                $order_number = 'ORD-' . strtoupper(uniqid());
                
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    INSERT INTO orders (user_id, order_number, total_amount, tax_amount, status, created_at) 
                    VALUES (?, ?, ?, ?, 'completed', NOW())
                ");
                $stmt->execute([$user_id, $order_number, $total, $tax]);
                $order_id = $pdo->lastInsertId();
                
                foreach ($order_items_data as $item) {
                    if ($item['type'] === 'subscription') {
                        $sub = $item['data'];
                        $stmt = $pdo->prepare("
                            INSERT INTO order_items (order_id, subscription_id, price_at_time, subscription_duration_months) 
                            VALUES (?, ?, ?, 1)
                        ");
                        $stmt->execute([$order_id, $sub['id'], $sub['price']]);
                        
                        $start_date = date('Y-m-d');
                        $end_date   = date('Y-m-d', strtotime('+1 month'));
                        $stmt = $pdo->prepare("
                            INSERT INTO user_subscriptions (user_id, subscription_id, status, start_date, end_date, auto_renew) 
                            VALUES (?, ?, 'active', ?, ?, 1)
                        ");
                        $stmt->execute([$user_id, $sub['id'], $start_date, $end_date]);

                    } elseif ($item['type'] === 'ticket') {
                        $tkt = $item['data'];
                        // Insert into match_tickets as a purchase record
                        $purchase_id = 'TKT-' . strtoupper(uniqid());
                        $stmt = $pdo->prepare("
                            INSERT INTO match_tickets (user_id, match_name, event_name, match_date, price, access_level, purchase_id, status) 
                            VALUES (?, ?, ?, CURDATE(), ?, ?, ?, 'upcoming')
                        ");
                        $stmt->execute([
                            $user_id,
                            $item['match_name'],
                            $item['match_name'],
                            $tkt['Price'],
                            $tkt['TicketType'],
                            $purchase_id
                        ]);
                        // Decrement available quantity
                        $stmt = $pdo->prepare("UPDATE tickets SET QuantityAvailable = QuantityAvailable - 1 WHERE TicketID = ? AND QuantityAvailable > 0");
                        $stmt->execute([$tkt['TicketID']]);
                    }
                }
                
                // Remove only the checked-out cart rows, not the whole cart
                $cart_ids = array_column($selected_items, 'cart_id');
                $placeholders = implode(',', array_fill(0, count($cart_ids), '?'));
                $stmt = $pdo->prepare("DELETE FROM cart WHERE id IN ($placeholders) AND user_id = ?");
                $stmt->execute(array_merge($cart_ids, [$user_id]));
                
                $pdo->commit();
                
                $response = ['success' => true, 'message' => 'Purchase completed successfully!', 'order_number' => $order_number];
                break;
                
            case 'get_user_subscriptions':
                $stmt = $pdo->prepare("
                    SELECT us.*, s.plan_name, s.price, s.period 
                    FROM user_subscriptions us
                    JOIN subscriptions s ON us.subscription_id = s.id
                    WHERE us.user_id = ? AND us.status = 'active'
                ");
                $stmt->execute([$user_id]);
                $subscriptions = $stmt->fetchAll();
                $response = ['success' => true, 'subscriptions' => $subscriptions];
                break;
                
				
			case 'get_purchases':
                // جلب الاشتراكات المشتراة
                $stmt = $pdo->prepare("
        SELECT 
            us.id as purchase_id,
            s.plan_name as name,
            s.price,
            us.start_date as date,
            us.status,
            'subscription' as type,
            CONCAT('Subscription: ', s.plan_name) as description,
            us.end_date as extra_info
        FROM user_subscriptions us
        JOIN subscriptions s ON us.subscription_id = s.id
        WHERE us.user_id = ?
        
        UNION ALL
        
        -- جلب تذاكر المباريات
        SELECT 
            mt.id as purchase_id,
            mt.match_name as name,
            mt.price,
            mt.match_date as date,
            mt.status,
            'ticket' as type,
            CONCAT(mt.event_name, ' · ', mt.stream_platform) as description,
            mt.access_level as extra_info
        FROM match_tickets mt
        WHERE mt.user_id = ?
        
        ORDER BY date DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $purchases = $stmt->fetchAll();
    $response = ['success' => true, 'purchases' => $purchases];
    break;
        }
    } catch(Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit;
}

// Get subscription plans for display
include 'header.php';
$stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE is_active = 1 ORDER BY price");
$stmt->execute();
$subscriptions = $stmt->fetchAll();

// Map subscription IDs to names for easy access
$plan_map = [];
foreach ($subscriptions as $sub) {
    $plan_map[$sub['plan_name']] = $sub;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VictoryZone — Perchuses</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="H&F.css">

	<style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --neon-orange: #ff8c1a;
            --neon-blue: #00f2ff;
            --neon-pink: #ff0055;
            --bg-dark: #02060c;
            --bg-darker: #010408;
            --border: rgba(255, 140, 26, 0.2);
            --text-gray: rgba(255, 255, 255, 0.7);
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-bg-hover: rgba(255, 140, 26, 0.1);
            --primary-dark: #02060c;
            --light-text: #ffffff;
            --accent-orange: #ff8c1a;
            --success-green: #22c55e;
            --past-gray: #4a5568;
            --free-gray: #9ca3af;
            --milestone-gold: #FFD700;
        }

        body {
            font-family: 'Rajdhani', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-dark);
            color: #fff;
            line-height: 1.6;
        }


        .page-wrapper {
            padding-top: 40px;
            padding-bottom: 80px;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 32px;
        }

        .tab-navigation {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 30px 0 40px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 12px 32px;
            background: rgba(2, 6, 12, 0.6);
            border: 1px solid var(--border);
            color: var(--text-gray);
            font-family: 'Orbitron', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s;
            transform: skew(-20deg);
            border-radius: 0;
            backdrop-filter: blur(10px);
            position: relative;
        }

        .tab-btn span {
            display: inline-block;
            transform: skew(20deg);
        }

        .tab-btn:hover {
            border-color: var(--neon-orange);
            color: #fff;
        }

        .tab-btn.active {
            background: var(--neon-orange);
            color: #000;
            border-color: var(--neon-orange);
            box-shadow: 0 0 20px rgba(255, 140, 26, 0.5);
        }

        .cart-counter {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--neon-pink);
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            padding: 0 4px;
            margin-left: 6px;
            animation: pulse 1.5s infinite;
            box-shadow: 0 0 10px var(--neon-pink);
        }

        .pricing-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: var(--neon-orange);
            color: #000;
            border-radius: 999px;
            font-family: 'Orbitron', sans-serif;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 1px;
            transform: skew(-10deg);
            margin-bottom: 1rem;
        }

        .badge-icon {
            width: 18px;
            height: 18px;
            transform: skew(10deg);
        }

        .pricing-badge span {
            transform: skew(10deg);
        }

        .section-title-large {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 1rem;
        }

        .title-accent {
            color: var(--neon-orange);
            position: relative;
        }

        .title-accent::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--neon-orange);
            box-shadow: 0 0 10px var(--neon-orange);
        }

        .section-subtitle-large {
            color: var(--text-gray);
            font-size: 1.125rem;
            max-width: 700px;
            margin: 0 auto;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
            margin: 40px 0;
        }

        .plan-card {
            position: relative;
            border-radius: 20px;
            padding: 36px 28px;
            border: 1px solid var(--border);
            transition: transform 0.25s, box-shadow 0.25s;
            background: rgba(2, 6, 12, 0.6);
            backdrop-filter: blur(10px);
        }
        .plan-card:hover {
            transform: translateY(-6px);
            border-color: var(--neon-orange);
            box-shadow: 0 20px 40px rgba(255, 140, 26, 0.2);
        }

        .plan-free {
            background: linear-gradient(135deg, #00f2ff 0%, #0066cc 100%);
            border-color: var(--neon-blue);
        }

        .plan-pro {
            background: linear-gradient(135deg, #EB7300 0%, #702006 100%);
            border-color: #EB7300;
            transform: scale(1.04);
            box-shadow: 0 20px 60px rgba(235, 115, 0, 0.25);
        }
        .plan-pro:hover {
            transform: scale(1.04) translateY(-6px);
        }

        .plan-elite {
            background: linear-gradient(135deg, #FFD700 0%, #EB7300 100%);
            border-color: #FFD700;
        }

        .plan-badge {
            position: absolute;
            top: -14px;
            left: 50%;
            transform: translateX(-50%);
            padding: 4px 16px;
            background: var(--neon-orange);
            color: #000;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            font-family: 'Orbitron', sans-serif;
            letter-spacing: 1px;
        }
        .plan-elite .plan-badge {
            background: #FFD700;
            color: #001524;
        }

        .plan-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(0, 21, 36, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 28px;
        }

        .plan-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: #fff;
            text-align: center;
            margin-bottom: 8px;
            font-family: 'Orbitron', sans-serif;
        }

        .plan-price {
            font-size: 2.5rem;
            font-weight: 900;
            color: #fff;
            text-align: center;
            font-family: 'Orbitron', sans-serif;
        }
        .plan-elite .plan-price {
            color: #FFD700;
        }

        .plan-period {
            color: rgba(255, 236, 209, 0.6);
            font-size: 14px;
            margin-left: 6px;
        }

        .plan-desc {
            text-align: center;
            color: rgba(255, 236, 209, 0.7);
            font-size: 14px;
            margin: 8px 0 24px;
        }

        .plan-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
        }

        .btn-subscribe {
            flex: 1;
            padding: 12px 0;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            text-align: center;
            transition: opacity 0.2s;
            cursor: pointer;
            text-decoration: none;
            display: block;
            font-family: 'Orbitron', sans-serif;
            letter-spacing: 1px;
            transform: skew(-20deg);
            border: none;
        }
        .btn-subscribe span {
            display: inline-block;
            transform: skew(20deg);
        }
        .btn-subscribe:hover { opacity: 0.88; }

        .btn-free-sub  { background: var(--neon-blue); color: #000; }
        .btn-pro-sub   { background: var(--neon-orange); color: #000; }
        .btn-elite-sub { background: linear-gradient(90deg, #FFD700, #EB7300); color: #000; }

        .btn-cart-plan {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 12px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 18px;
            border: 2px solid rgba(255, 236, 209, 0.3);
            background: rgba(0, 21, 36, 0.4);
            color: #fff;
            cursor: pointer;
            transition: border-color 0.2s, color 0.2s, background 0.2s;
            white-space: nowrap;
            font-family: 'Orbitron', sans-serif;
            transform: skew(-20deg);
        }
        .btn-cart-plan span {
            display: inline-block;
            transform: skew(20deg);
        }
        .btn-cart-plan:hover:not(:disabled) {
            border-color: var(--neon-orange);
            color: var(--neon-orange);
        }
        .btn-cart-plan:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-cart-plan.added {
            background: #22c55e;
            border-color: #22c55e;
            color: #fff;
        }

        .features-list {
            list-style: none;
        }
        .features-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 14px;
            color: rgba(255, 236, 209, 0.9);
        }
        .features-list li::before {
            content: "✓";
            color: var(--neon-orange);
            font-weight: 900;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .plan-elite .features-list li::before {
            color: #FFD700;
        }
        .plan-free .features-list li::before {
            color: var(--neon-blue);
        }

        .comparison-wrap {
            background: rgba(2, 6, 12, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px;
            margin: 40px 0;
            overflow-x: auto;
        }

        .comparison-wrap h2 {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            text-align: center;
            margin-bottom: 32px;
            font-family: 'Orbitron', sans-serif;
        }

        .modern-pricing-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Rajdhani', sans-serif;
        }

        .modern-pricing-table th {
            padding: 20px;
            font-family: 'Orbitron', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            text-align: center;
            border-bottom: 2px solid var(--border);
        }

        .modern-pricing-table th:first-child {
            text-align: left;
            color: var(--text-gray);
        }

        .pro-header {
            color: var(--neon-orange);
        }

        .elite-header {
            color: var(--milestone-gold);
        }

        .modern-pricing-table td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
        }

        .row-label {
            color: var(--text-gray);
            font-weight: 500;
        }

        .modern-pricing-table tr:hover td {
            background: rgba(255, 140, 26, 0.05);
        }

        .price-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-family: 'Orbitron', sans-serif;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .price-tag img {
            width: 20px;
            height: 20px;
            object-fit: contain;
        }

        .price-sub {
            font-size: 0.85rem;
            color: var(--text-gray);
            margin-left: 5px;
        }

        .fas.fa-infinity {
            color: var(--neon-orange);
            font-size: 1.2rem;
        }

        .fas.fa-check {
            color: var(--neon-orange);
        }

        .fas.fa-times {
            color: #ff4444;
            opacity: 0.7;
        }

        .pro-cell, .elite-cell {
            text-align: center;
        }

        .cart-page {
            max-width: 1200px;
            margin: 0 auto;
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .cart-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
        }

        .cart-actions {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .btn-cart-action {
            padding: 12px 24px;
            background: rgba(255, 140, 26, 0.1);
            border: 1px solid var(--neon-orange);
            color: #fff;
            border-radius: 8px;
            font-family: 'Orbitron', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            transform: skew(-20deg);
        }
        .btn-cart-action span {
            display: inline-block;
            transform: skew(20deg);
        }
        .btn-cart-action:hover:not(:disabled) {
            background: var(--neon-orange);
            color: #000;
        }
        .btn-cart-action.danger:hover:not(:disabled) {
            background: #ff0000;
            border-color: #ff0000;
            color: #fff;
        }
        .btn-cart-action:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .cart-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 32px;
            margin-bottom: 48px;
        }

        .cart-items {
            background: rgba(2, 6, 12, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 24px;
        }

        .cart-select-all {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 16px;
        }

        .cart-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--neon-orange);
        }

        .cart-item {
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            gap: 20px;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid var(--border);
        }
        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--neon-orange);
        }

        .cart-item-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .cart-item-icon.ticket { background: var(--neon-orange); color: #000; }
        .cart-item-icon.pro { background: var(--neon-orange); color: #000; }
        .cart-item-icon.elite { background: #FFD700; color: #000; }

        .cart-item-details h4 {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.1rem;
            color: #fff;
            margin-bottom: 4px;
        }
        .cart-item-details p {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .cart-item-price {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--neon-orange);
        }

        .cart-item-actions {
            display: flex;
            gap: 8px;
        }

        .btn-item-action {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-item-action:hover {
            border-color: var(--neon-orange);
            color: var(--neon-orange);
        }
        .btn-item-action.delete:hover {
            border-color: #ff0000;
            color: #ff0000;
        }

        .cart-summary {
            background: rgba(2, 6, 12, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .summary-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.3rem;
            color: #fff;
            margin-bottom: 24px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            color: var(--text-gray);
            border-bottom: 1px solid var(--border);
        }
        .summary-row.total {
            border-bottom: none;
            color: #fff;
            font-size: 1.2rem;
            font-weight: 700;
            margin-top: 12px;
        }

        .btn-checkout {
            width: 100%;
            padding: 16px;
            background: var(--neon-orange);
            color: #000;
            border: none;
            border-radius: 8px;
            font-family: 'Orbitron', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 24px;
            transform: skew(-20deg);
        }
        .btn-checkout span {
            display: inline-block;
            transform: skew(20deg);
        }
        .btn-checkout:hover:not(:disabled) {
            opacity: 0.9;
            box-shadow: 0 0 20px rgba(255, 140, 26, 0.5);
        }
        .btn-checkout:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .cart-empty {
            text-align: center;
            padding: 60px 20px;
        }
        .cart-empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        .cart-empty h3 {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.5rem;
            color: #fff;
            margin-bottom: 12px;
        }
        .cart-empty p {
            color: var(--text-gray);
            margin-bottom: 24px;
        }

        .purchases-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
            margin-top: 40px;
        }

        .purchase-card {
            background: rgba(2, 6, 12, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 24px;
            transition: all 0.3s;
        }
        .purchase-card:hover {
            border-color: var(--neon-orange);
            transform: translateY(-5px);
        }

        .purchase-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .purchase-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--neon-orange), #702006);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .purchase-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.2rem;
            color: #fff;
            margin-bottom: 4px;
        }

        .purchase-subtitle {
            color: var(--neon-orange);
            font-size: 0.9rem;
            font-weight: 600;
        }

        .purchase-date {
            color: var(--text-gray);
            font-size: 0.85rem;
        }

        .purchase-details {
            margin-bottom: 16px;
        }

        .purchase-detail {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .purchase-detail span:last-child {
            color: #fff;
            font-weight: 600;
        }

        .purchase-status {
            display: inline-block;
            padding: 4px 12px;
            background: var(--success-green);
            color: #000;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            font-family: 'Orbitron', sans-serif;
        }

        .live-badge-small {
            display: inline-block;
            padding: 2px 8px;
            background: #ff0000;
            color: white;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-left: 8px;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 0.8; }
            50% { opacity: 1; }
            100% { opacity: 0.8; }
        }

        .site-footer-mini {
            background: rgba(2, 6, 12, 0.95);
            border-top: 1px solid var(--border);
            padding: 32px;
            text-align: center;
            margin-top: 0;
        }

        .site-footer-mini p {
            color: rgba(255, 255, 255, 0.5);
            font-size: 13px;
        }

        .site-footer-mini a {
            color: var(--neon-orange);
            text-decoration: none;
        }

        .site-footer-mini a:hover {
            text-decoration: underline;
        }

        .page {
            display: none;
        }
        .page.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .tab-page {
            display: none;
        }
        .tab-page.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 1024px) {
            .pricing-grid { 
                grid-template-columns: 1fr 1fr; 
            }
            .plan-pro { transform: none; }
            .plan-pro:hover { transform: translateY(-6px); }
            .cart-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .pricing-grid   { grid-template-columns: 1fr; }
            .section-title-large { font-size: 2rem; }
            .cart-header { flex-direction: column; align-items: flex-start; }
            .cart-item { grid-template-columns: 1fr; text-align: center; }
            .cart-item-icon { margin: 0 auto; }
            .tab-navigation { flex-direction: column; align-items: center; }
            .tab-btn { width: 100%; max-width: 300px; }
            .comparison-wrap { padding: 20px; }
        }

        @media (max-width: 480px) {
            .container { padding: 0 16px; }
            .plan-actions { flex-direction: column; }
            .cart-actions { flex-direction: column; width: 100%; }
            .btn-cart-action { width: 100%; }
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: var(--text-gray);
        }
    </style>
</head>
<body>

    <div class="page active" id="perchuses-page">
        <main class="page-wrapper">
            <div class="container">

                <div style="text-align: center;">
                    <div class="pricing-badge">
                        <svg class="badge-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                        </svg>
                        <span>PERCHUSES HUB</span>
                    </div>

                    <h1 class="section-title-large">
                        Manage Your <span class="title-accent">Purchases</span>
                    </h1>

                    <p class="section-subtitle-large">
                        Browse subscription plans, view your cart, and track past online match tickets
                    </p>
                </div>

                <div class="tab-navigation">
                    <button class="tab-btn" onclick="switchTab('cart')" style="position: relative;">
                        <span>🛒 Cart <span id="cartTabCounter" class="cart-counter" style="display: none;">0</span></span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('subscriptions')">
                        <span>📦 Subscriptions</span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('past')">
                        <span>📜 Past Purchases</span>
                    </button>
                </div>

                <div class="tab-page" id="cart-tab">
                    <div class="cart-page">
                        <div class="cart-header">
                            <h1 class="cart-title">Your Shopping Cart</h1>
                            <div class="cart-actions">
                                <button class="btn-cart-action danger" onclick="clearCart()" id="emptyCartBtn" disabled>
                                    <span>🗑️ Empty Cart</span>
                                </button>
                            </div>
                        </div>

                        <div class="cart-grid">
                            <div class="cart-items" id="cartItemsContainer">
                                <div class="loading">Loading cart...</div>
                            </div>

                            <div class="cart-summary">
                                <h3 class="summary-title">Order Summary</h3>
                                <div class="summary-row">
                                    <span>Selected Items</span>
                                    <span id="selectedCount">0</span>
                                </div>
                                <div class="summary-row">
                                    <span>Subtotal</span>
                                    <span id="cartSubtotal">SAR 0.00</span>
                                </div>
                                <div class="summary-row">
                                    <span>Tax (10%)</span>
                                    <span id="cartTax">SAR 0.00</span>
                                </div>
                                <div class="summary-row total">
                                    <span>Total</span>
                                    <span id="cartTotal">SAR 0.00</span>
                                </div>
                                <button class="btn-checkout" onclick="checkoutSelected()" id="checkoutBtn" disabled>
                                    <span>Checkout Selected</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-page" id="subscriptions-tab">
                    <div class="pricing-grid">
                        <?php foreach ($subscriptions as $sub): 
                            $plan_class = $sub['plan_name'];
                            $icon = $sub['plan_name'] == 'free' ? '🛡️' : ($sub['plan_name'] == 'pro' ? '⚡' : '👑');
                            $badge = $sub['plan_name'] == 'pro' ? '<div class="plan-badge">⚡ Most Popular</div>' : 
                                    ($sub['plan_name'] == 'elite' ? '<div class="plan-badge">👑 Best Value</div>' : '');
                            $price_display = $sub['price'] == 0 ? 'Free' : 'SAR ' . $sub['price'];
                            $features = json_decode($sub['features'], true);
                            $feature_list = '';
                            if ($features) {
                                foreach ($features as $feature) {
                                    $feature_list .= '<li>' . htmlspecialchars($feature) . '</li>';
                                }
                            }
                        ?>
                        <div class="plan-card plan-<?php echo $plan_class; ?>">
                            <?php echo $badge; ?>
                            <div class="plan-icon"><?php echo $icon; ?></div>
                            <div class="plan-name"><?php echo ucfirst($sub['plan_name']); ?></div>
                            <div style="text-align:center;">
                                <span class="plan-price"><?php echo $price_display; ?></span>
                                <?php if ($sub['price'] > 0): ?>
                                <span class="plan-period">/<?php echo $sub['period']; ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="plan-desc"><?php echo $sub['plan_name'] == 'free' ? 'Perfect for casual gamers getting started' : ($sub['plan_name'] == 'pro' ? 'For serious competitors and active players' : 'Ultimate package for pros and streamers'); ?></p>

                            <div class="plan-actions">
                                <?php if ($sub['price'] == 0): ?>
                                <a href="#" onclick="showLoginMessage(); return false;" class="btn-subscribe btn-free-sub">
                                    <span>Get Started</span>
                                </a>
                                <?php else: ?>
                                <a class="btn-subscribe btn-<?php echo $sub['plan_name']; ?>-sub" onclick="showLoginMessage(); return false;">
                                    <span>Subscribe</span>
                                </a>
                                //js even handle
                                <button class="btn-cart-plan" data-sub-id="<?php echo $sub['id']; ?>" data-sub-name="<?php echo ucfirst($sub['plan_name']); ?> Plan" data-sub-price="<?php echo $sub['price']; ?>" data-sub-period="<?php echo $sub['period']; ?>" onclick="addToCart(this, <?php echo $sub['id']; ?>, '<?php echo ucfirst($sub['plan_name']); ?> Plan', <?php echo $sub['price']; ?>, '<?php echo $sub['period']; ?>')" title="Add to cart">
                                    <span>🛒 +</span>
                                </button>
                                <?php endif; ?>
                            </div>

                            <ul class="features-list">
                                <?php echo $feature_list; ?>
                            </ul>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="comparison-wrap">
                        <h2>Detailed Comparison</h2>
                        <table class="modern-pricing-table">
                            <thead>
                                <tr>
                                    <th>Feature</th>
                                    <th>Free</th>
                                    <th class="pro-header">Pro</th>
                                    <th class="elite-header">Elite</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="row-label">Price</td>
                                    <td><span class="price-tag" style="color: var(--free-gray);">Free</span></td>
                                    <td class="pro-cell"><span class="price-tag" style="color: var(--neon-orange);">SAR 20</span><span class="price-sub">/month</span></td>
                                    <td class="elite-cell"><span class="price-tag" style="color: var(--milestone-gold);">SAR 40</span><span class="price-sub">/month</span></td>
                                </tr>
                                <tr>
                                    <td class="row-label">Changing Name</td>
                                    <td>Once / month</td>
                                    <td class="pro-cell"><i class="fas fa-infinity"></i></td>
                                    <td class="elite-cell"><i class="fas fa-infinity" style="color: var(--milestone-gold);"></i></td>
                                </tr>
                                <tr>
                                    <td class="row-label">Changing PFP</td>
                                    <td>Once / month</td>
                                    <td class="pro-cell"><i class="fas fa-infinity"></i></td>
                                    <td class="elite-cell"><i class="fas fa-infinity" style="color: var(--milestone-gold);"></i></td>
                                </tr>
                                <tr>
                                    <td class="row-label">Changing Username</td>
                                    <td><i class="fas fa-times"></i></td>
                                    <td class="pro-cell">Once / month</td>
                                    <td class="elite-cell" style="color: var(--milestone-gold); font-weight:bold;">Once / week</td>
                                </tr>
                                <tr>
                                    <td class="row-label">Watching Lives</td>
                                    <td><i class="fas fa-times"></i></td>
                                    <td class="pro-cell"><i class="fas fa-check"></i></td>
                                    <td class="elite-cell"><i class="fas fa-check" style="color: var(--milestone-gold);"></i></td>
                                </tr>
                                <tr>
                                    <td class="row-label">Additional Settings</td>
                                    <td><i class="fas fa-times"></i></td>
                                    <td class="pro-cell"><i class="fas fa-times"></i></td>
                                    <td class="elite-cell"><i class="fas fa-check" style="color: var(--milestone-gold);"></i></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-page" id="past-tab">
                    <div class="purchases-grid" id="pastPurchasesContainer">
                        <div class="loading">Loading purchases...</div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    
 <!-- FOOTER -->
    <footer class="site-footer">
        <div class="footer-inner">
            <div>
                <div class="footer-logo">
                    <img src="https://i.imgur.com/Pm7kYQg.png" alt="VictoryZone" class="footer-logo-img">
                    <span class="footer-logo-text">Victory<span class="logo-accent">Zone</span></span>
                </div>
                <p class="footer-about">
                    From the field to the screen. Follow live matches, geek out in gaming forums,
                    and connect with fans across sports and esports.
                </p>
                <div class="social-links">
                    <a href="https://www.facebook.com/" class="social-link" target="_blank" rel="noopener noreferrer">Facebook</a>
                    <a href="https://twitter.com/" class="social-link" target="_blank" rel="noopener noreferrer">Twitter</a>
                    <a href="https://discord.com/" class="social-link" target="_blank" rel="noopener noreferrer">Discord</a>
                    <a href="https://www.youtube.com/" class="social-link" target="_blank" rel="noopener noreferrer">YouTube</a>
                </div>
            </div>
            <div>
                <h4 class="footer-heading">Quick Links</h4>
                <div class="footer-links">
                    <a href="homepage.php">Home</a>
                    <a href="news-page.php">News</a>
                    <a href="tournaments.php">Tournaments</a>
                    <a href="Perchuses.php">Perchuses</a>
                </div>
            </div>
            <div>
                <h4 class="footer-heading">Support</h4>
                <div class="footer-links">
                    <a href="HomePage.php#contact">Help Center</a>
                    <button class="read-more-btn" style="padding: 3px; min-width: 10px;" onclick="openRulesModal()">Rules & Guidelines</button>
                  <button class="read-more-btn"style="padding: 3px; min-width: 10px;" onclick="openTermsModal()">Terms of Service</button>
                    
                </div>
            </div>
            <div>
    <h4 class="footer-heading">Newsletter</h4>
    <p class="footer-newsletter-text">
        Subscribe to get the latest news, tournament updates, and exclusive offers.
    </p>
    <form class="newsletter-form" id="newsletterForm" onsubmit="handleNewsletterSubmit(event)">
        <input type="email" id="newsletterEmail" class="newsletter-input" placeholder="Enter your email" required>
        <button type="submit" class="read-more-btn full-width" style="padding: 12px; min-width: auto;">
            <span>Subscribe</span>
        </button>
        <div id="newsletterSuccess" class="newsletter-success" style="display: none;">
            ✓ Thanks for subscribing!
        </div>
    </form>
</div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 VictoryZone. All rights reserved. Crafted with passion for dedicated gamers and sports enthusiasts everywhere.</p>
        </div>
    </footer>

    <script>
        let selectedCartItems = [];

        const profileIcon = document.getElementById('profileIcon');
        const profileDropdown = document.getElementById('profileDropdown');

        if (profileIcon) {
            profileIcon.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
            });
        }

        document.addEventListener('click', (e) => {
            if (!profileIcon?.contains(e.target) && !profileDropdown?.contains(e.target)) {
                profileDropdown?.classList.remove('show');
            }
        });

        function showLoginMessage() {
            showToast('Please login or create an account to continue');
        }
// --- EVENT HANDLING (TAB SWITCHING) ---
        function switchTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.querySelectorAll('.tab-page').forEach(page => {
                page.classList.remove('active');
            });
            
            if (tabName === 'cart') {
                document.querySelectorAll('.tab-btn')[0].classList.add('active');
                document.getElementById('cart-tab').classList.add('active');
                loadCart();
            } else if (tabName === 'subscriptions') {
                document.querySelectorAll('.tab-btn')[1].classList.add('active');
                document.getElementById('subscriptions-tab').classList.add('active');
            } else if (tabName === 'past') {
                document.querySelectorAll('.tab-btn')[2].classList.add('active');
                document.getElementById('past-tab').classList.add('active');
                loadPastPurchases();
            }
        }
 // --- API CALL (ADD TO CART) ---
        // This sends data to the PHP block at the top of this file.
        async function loadCart() {
            const container = document.getElementById('cartItemsContainer');
            const emptyCartBtn = document.getElementById('emptyCartBtn');
            
            try {
                const formData = new FormData();
                formData.append('action', 'get_cart');
                
                const response = await fetch('perchuses.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                const data = await response.json();
                
                if (data.success && data.cart.length === 0) {
                    container.innerHTML = `
                        <div class="cart-empty">
                            <div class="cart-empty-icon">🛒</div>
                            <h3>Your cart is empty</h3>
                            <p>Browse our subscription plans and add items to get started.</p>
                            <button class="btn-cart-action" onclick="switchTab('subscriptions')">
                                <span>Browse Plans</span>
                            </button>
                        </div>
                    `;
                    emptyCartBtn.disabled = true;
                    document.getElementById('checkoutBtn').disabled = true;
                    document.getElementById('cartTabCounter').style.display = 'none';
                    return;
                }
                
                if (data.success && data.cart.length > 0) {
                    emptyCartBtn.disabled = false;
                    document.getElementById('cartTabCounter').textContent = data.cart.length;
                    document.getElementById('cartTabCounter').style.display = 'inline-flex';
                    
                    let html = `
                        <div class="cart-select-all">
                            <input type="checkbox" class="cart-checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()">
                            <label for="selectAllCheckbox">Select All Items</label>
                        </div>
                    `;
                    
                    data.cart.forEach((item, index) => {
                        let icon, iconClass, title, subtitle;

                        if (item.item_type === 'subscription') {
                            icon      = item.label === 'pro' ? '⚡' : '👑';
                            iconClass = item.label === 'pro' ? 'pro' : 'elite';
                            title     = item.label.charAt(0).toUpperCase() + item.label.slice(1) + ' Plan';
                            subtitle  = (item.label === 'pro' ? 'For serious competitors' : 'Ultimate package') + ' · ' + item.meta;
                        } else {
                            icon      = '🎫';
                            iconClass = 'ticket';
                            title     = item.label;                          // TicketType e.g. PREMIUM GOLD
                            subtitle  = item.meta || 'Match Ticket';         // match_name
                        }

                        const qty = item.quantity > 1 ? ` ×${item.quantity}` : '';

                        html += `
                            <div class="cart-item">
                                <input type="checkbox" class="cart-item-checkbox" id="itemCheckbox_${index}" 
                                       onchange="toggleSelectItem(${index})">
                                <div class="cart-item-icon ${iconClass}">${icon}</div>
                                <div class="cart-item-details">
                                    <h4>${escapeHtml(title)}${qty}</h4>
                                    <p>${escapeHtml(subtitle)}</p>
                                </div>
                                <div class="cart-item-price">SAR ${(parseFloat(item.price) * (item.quantity || 1)).toFixed(2)}</div>
                                <div class="cart-item-actions">
                                    <button class="btn-item-action delete" onclick="removeFromCart(${item.cart_id})" title="Remove">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    
                    container.innerHTML = html;
                    
                    window.cartData = data.cart;
                    selectedCartItems = [];
                    updateSummary();
                }
            } catch (error) {
                console.error('Error loading cart:', error);
                container.innerHTML = '<div class="loading">Error loading cart</div>';
            }
        }

        async function addToCart(btnElement, subId, planName, price, period) {
            try {
                const formData = new FormData();
                formData.append('action', 'add_to_cart');
                formData.append('subscription_id', subId);
                
                const response = await fetch('perchuses.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(`${planName} added to cart!`);
                    loadCart();
                    
                    btnElement.classList.add('added');
                    btnElement.innerHTML = '<span>✓</span>';
                    
                    setTimeout(() => {
                        btnElement.classList.remove('added');
                        btnElement.innerHTML = '<span>🛒 +</span>';
                    }, 1500);
                } else {
                    showToast(data.message);
                }
            } catch (error) {
                console.error('Error adding to cart:', error);
                showToast('Error adding to cart');
            }
        }

        async function removeFromCart(cartId) {
            try {
                const formData = new FormData();
                formData.append('action', 'remove_from_cart');
                formData.append('cart_id', cartId);
                
                const response = await fetch('perchuses.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('Item removed from cart');
                    loadCart();
                }
            } catch (error) {
                console.error('Error removing from cart:', error);
                showToast('Error removing item');
            }
        }

        async function clearCart() {
            if (!confirm('Are you sure you want to empty your cart?')) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'clear_cart');
                
                const response = await fetch('perchuses.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('Cart emptied');
                    loadCart();
                }
            } catch (error) {
                console.error('Error clearing cart:', error);
                showToast('Error clearing cart');
            }
        }

        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const checkboxes = document.querySelectorAll('.cart-item-checkbox');
            
            selectedCartItems = [];
            
            if (selectAllCheckbox.checked) {
                checkboxes.forEach((checkbox, index) => {
                    checkbox.checked = true;
                    selectedCartItems.push(window.cartData[index]);
                });
            } else {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
            }
            
            updateSummary();
        }

        function toggleSelectItem(index) {
            const checkbox = document.getElementById(`itemCheckbox_${index}`);
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            
            if (checkbox.checked) {
                selectedCartItems.push(window.cartData[index]);
            } else {
                selectedCartItems = selectedCartItems.filter(item => item.cart_id !== window.cartData[index].cart_id);
            }
            
            const checkboxes = document.querySelectorAll('.cart-item-checkbox');
            selectAllCheckbox.checked = checkboxes.length === selectedCartItems.length;
            
            updateSummary();
        }

        function updateSummary() {
            const subtotal = selectedCartItems.reduce((sum, item) => sum + parseFloat(item.price) * (item.quantity || 1), 0);
            const tax = subtotal * 0.1;
            const total = subtotal + tax;
            
            document.getElementById('selectedCount').textContent = selectedCartItems.length;
            document.getElementById('cartSubtotal').textContent = `SAR ${subtotal.toFixed(2)}`;
            document.getElementById('cartTax').textContent = `SAR ${tax.toFixed(2)}`;
            document.getElementById('cartTotal').textContent = `SAR ${total.toFixed(2)}`;
            
            const checkoutBtn = document.getElementById('checkoutBtn');
            checkoutBtn.disabled = selectedCartItems.length === 0;
        }

        async function checkoutSelected() {
            if (selectedCartItems.length === 0) {
                showToast('No items selected for checkout');
                return;
            }
            
            const total = selectedCartItems.reduce((sum, item) => sum + parseFloat(item.price), 0) * 1.1;
            showToast(`Processing checkout for SAR ${total.toFixed(2)}...`);
            
            try {
                const selectedData = selectedCartItems.map(item => ({
                    cart_id:         item.cart_id,
                    item_type:       item.item_type,
                    subscription_id: item.subscription_id || null,
                    ticket_id:       item.ticket_id       || null,
                    match_name:      item.match_name       || '',
                    quantity:        item.quantity         || 1,
                }));
                
                const formData = new FormData();
                formData.append('action', 'checkout');
                formData.append('selected_items', JSON.stringify(selectedData));
                
                const response = await fetch('perchuses.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('✓ Purchase completed successfully! Check your email for confirmation.');
                    selectedCartItems = [];
                    loadCart();
                } else {
                    showToast(data.message || 'Checkout failed');
                }
            } catch (error) {
                console.error('Error during checkout:', error);
                showToast('Error processing checkout');
            }
        }

        async function loadPastPurchases() {
    const container = document.getElementById('pastPurchasesContainer');
    
    try {
        const formData = new FormData();
        formData.append('action', 'get_purchases');  // تغيير الاسم
        
        const response = await fetch('perchuses.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        const data = await response.json();
        
        if (data.success && (!data.purchases || data.purchases.length === 0)) {
            container.innerHTML = `
                <div class="cart-empty">
                    <div class="cart-empty-icon">📦</div>
                    <h3>No past purchases</h3>
                    <p>You haven't purchased any subscriptions or match tickets yet.</p>
                    <button class="btn-cart-action" onclick="switchTab('subscriptions')">
                        <span>Browse Plans</span>
                    </button>
                </div>
            `;
            return;
        }
        
        if (data.success && data.purchases.length > 0) {
            let html = '';
            
            data.purchases.forEach(purchase => {
                // تحديد الأيقونة واللون حسب النوع
                let icon = '🎫';
                let iconBg = 'linear-gradient(135deg, var(--neon-orange), #702006)';
                let statusText = '';
                let statusBg = '';
                
                if (purchase.type === 'subscription') {
                    icon = purchase.name === 'pro' ? '⚡' : '👑';
                    statusText = purchase.status === 'active' ? '✓ Active Subscription' : 'Expired';
                    statusBg = purchase.status === 'active' ? '#22c55e' : '#ff4444';
                } else {
                    icon = '🎫';
                    statusText = purchase.status === 'upcoming' ? '🔴 LIVE SOON' : '✓ Watched';
                    statusBg = purchase.status === 'upcoming' ? '#ff0000' : '#22c55e';
                }
                
                html += `
                    <div class="purchase-card">
                        <div class="purchase-header">
                            <div class="purchase-icon" style="background: ${iconBg}">
                                ${icon}
                            </div>
                            <div>
                                <h3 class="purchase-title">${escapeHtml(purchase.name)}</h3>
                                <div class="purchase-subtitle">${escapeHtml(purchase.description || (purchase.type === 'subscription' ? 'Monthly Subscription' : 'Match Ticket'))}</div>
                                <div class="purchase-date">${purchase.date}</div>
                            </div>
                        </div>
                        <div class="purchase-details">
                            <div class="purchase-detail">
                                <span>Purchase ID</span>
                                <span>#${purchase.purchase_id}</span>
                            </div>
                            <div class="purchase-detail">
                                <span>Amount Paid</span>
                                <span style="color: var(--neon-orange); font-weight:700;">SAR ${parseFloat(purchase.price).toFixed(2)}</span>
                            </div>
                            ${purchase.extra_info ? `
                            <div class="purchase-detail">
                                <span>Additional Info</span>
                                <span>${escapeHtml(purchase.extra_info)}</span>
                            </div>
                            ` : ''}
                        </div>
                        <div>
                            <span class="purchase-status" style="background: ${statusBg}; color: white;">${statusText}</span>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading purchases:', error);
        container.innerHTML = '<div class="loading">Error loading purchases</div>';
    }
}

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showToast(message) {
            let toast = document.getElementById('dynamicToast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'dynamicToast';
                toast.style.cssText = `
                    position: fixed;
                    bottom: 30px;
                    right: 30px;
                    background: rgba(2, 6, 12, 0.95);
                    border: 1px solid var(--neon-orange);
                    border-radius: 10px;
                    padding: 15px 25px;
                    color: #fff;
                    font-family: 'Rajdhani', sans-serif;
                    z-index: 9999;
                    animation: slideIn 0.3s ease;
                    box-shadow: 0 10px 30px rgba(255, 140, 26, 0.3);
                `;
                document.body.appendChild(toast);
            }
            
            toast.textContent = message;
            toast.style.display = 'block';
            
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }

        window.switchTab = switchTab;
        window.addToCart = addToCart;
        window.removeFromCart = removeFromCart;
        window.clearCart = clearCart;
        window.checkoutSelected = checkoutSelected;
        window.toggleSelectAll = toggleSelectAll;
        window.toggleSelectItem = toggleSelectItem;
        window.showLoginMessage = showLoginMessage;

        loadCart();
		
		// ========== NEWSLETTER SUBSCRIBE ==========
        function handleNewsletterSubmit(event) {
            event.preventDefault();
            const email = document.getElementById('newsletterEmail').value;
            const successDiv = document.getElementById('newsletterSuccess');
            
            if (!email) {
                alert('Please enter an email address');
                return;
            }
            
            successDiv.style.display = 'block';
            document.getElementById('newsletterEmail').value = '';
            setTimeout(() => {
                successDiv.style.display = 'none';
            }, 5000);
        }
		// ===================== SIMPLE POPUP FUNCTIONS =====================

// Close popup function
function closeLegalPopup() {
    const popup = document.getElementById('legalPopupContainer');
    if (popup) {
        popup.remove();
    }
    document.body.style.overflow = '';
}

// Open Terms of Service Popup
// Open Terms of Service Popup - FIXED GLASS VERSION
function openTermsModal() {
    // Remove existing if any
    const existing = document.getElementById('legalPopupContainer');
    if (existing) existing.remove();
    
    // Create popup container with inline styles
    const popupContainer = document.createElement('div');
    popupContainer.id = 'legalPopupContainer';
    popupContainer.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(2, 6, 12, 0.6);
        -webkit-backdrop-filter: blur(8px);
        backdrop-filter: blur(8px);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    // Create popup content
    popupContainer.innerHTML = `
        <div style="
            width: 90%;
            max-width: 600px;
            max-height: 85vh;
            background: rgba(2, 6, 12, 0.85);
            -webkit-backdrop-filter: blur(12px);
            backdrop-filter: blur(12px);
            border: 2px solid #ff8c1a;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 0 50px rgba(255, 140, 26, 0.3);
        ">
            <div style="
                padding: 20px;
                border-bottom: 1px solid rgba(255, 140, 26, 0.3);
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: rgba(0, 0, 0, 0.3);
            ">
                <h2 style="
                    color: #ff8c1a;
                    font-family: 'Orbitron', sans-serif;
                    margin: 0;
                    font-size: 1.5rem;
                ">📄 Terms of Service</h2>
                <button onclick="closeLegalPopup()" style="
                    background: none;
                    border: none;
                    color: rgba(255, 255, 255, 0.7);
                    font-size: 28px;
                    cursor: pointer;
                    transition: all 0.3s;
                " onmouseover="this.style.color='#ff8c1a'; this.style.transform='rotate(90deg)'" onmouseout="this.style.color='rgba(255,255,255,0.7)'; this.style.transform='rotate(0deg)'">×</button>
            </div>
            <div style="
                padding: 20px;
                overflow-y: auto;
                max-height: calc(85vh - 80px);
                color: rgba(255, 255, 255, 0.8);
                font-family: 'Rajdhani', sans-serif;
                line-height: 1.6;
            ">
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 0 0 15px 0;">📜 1. Acceptance of Terms</h3>
                <p>By accessing VictoryZone, you agree to be bound by these Terms of Service. If you disagree with any part, please do not use our platform.</p>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">👥 2. Account Registration</h3>
                <ul style="margin: 10px 0 15px 20px;">
                    <li>You must be at least 13 years old to create an account</li>
                    <li>Provide accurate and complete registration information</li>
                    <li>You are responsible for maintaining account security</li>
                    <li>Notify us immediately of unauthorized account access</li>
                </ul>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">💰 3. Purchases & Payments</h3>
                <p>All purchases made through VictoryZone are final unless otherwise stated. We reserve the right to modify prices or cancel orders due to errors.</p>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">🔒 4. Privacy & Data</h3>
                <p>Your privacy is important to us. We collect and process personal data according to our Privacy Policy. By using VictoryZone, you consent to such processing.</p>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">📱 5. User Content</h3>
                <ul>
                    <li>You retain ownership of content you post</li>
                    <li>You grant VictoryZone license to use, modify, and display your content</li>
                    <li>You are responsible for content you submit</li>
                </ul>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">⚠️ 6. Prohibited Activities</h3>
                <ul>
                    <li>Unauthorized access to other accounts or systems</li>
                    <li>Uploading malicious code or viruses</li>
                    <li>Impersonating VictoryZone staff or other users</li>
                </ul>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">⚖️ 7. Intellectual Property</h3>
                <p>All content on VictoryZone including logos, designs, and graphics are property of VictoryZone.</p>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">⛔ 8. Termination</h3>
                <p>We may terminate or suspend your account immediately for violations of these Terms.</p>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">📅 9. Changes to Terms</h3>
                <p>We reserve the right to modify these Terms at any time. Continued use of VictoryZone after changes constitutes acceptance of new Terms.</p>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">📧 10. Contact Us</h3>
                <p>Email: <strong style="color: #ff8c1a;">legal@victoryzone.gg</strong></p>
                <p><em style="color: #00f2ff;">Last Updated: March 2026</em></p>
            </div>
            <div style="
                padding: 15px 20px;
                border-top: 1px solid rgba(255, 140, 26, 0.3);
                display: flex;
                justify-content: flex-end;
                background: rgba(0, 0, 0, 0.3);
            ">
                <button onclick="closeLegalPopup()" style="
                    padding: 8px 25px;
                    background: transparent;
                    border: 1px solid #ff8c1a;
                    color: rgba(255, 255, 255, 0.7);
                    font-family: 'Orbitron', sans-serif;
                    font-size: 0.85rem;
                    cursor: pointer;
                    transition: all 0.3s;
                    transform: skew(-20deg);
                " onmouseover="this.style.background='#ff8c1a'; this.style.color='#000'; this.style.boxShadow='0 0 20px rgba(255,140,26,0.5)'" onmouseout="this.style.background='transparent'; this.style.color='rgba(255,255,255,0.7)'; this.style.boxShadow='none'">
                    <span style="display: inline-block; transform: skew(20deg);">Close</span>
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(popupContainer);
    document.body.style.overflow = 'hidden';
}
// Open Rules & Guidelines Popup
function openRulesModal() {
    // Remove existing if any
    const existing = document.getElementById('legalPopupContainer');
    if (existing) existing.remove();
    
    // Create popup container with inline styles
    const popupContainer = document.createElement('div');
    popupContainer.id = 'legalPopupContainer';
    popupContainer.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(2, 6, 12, 0.7);
        backdrop-filter: blur(8px);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    // Create popup content
    popupContainer.innerHTML = `
        <div style="
            width: 90%;
            max-width: 600px;
            max-height: 85vh;
            background: rgba(2, 6, 12, 0.9);
            backdrop-filter: blur(10px);
            border: 2px solid #ff8c1a;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 0 50px rgba(255, 140, 26, 0.3);
        ">
            <div style="
                padding: 20px;
                border-bottom: 1px solid rgba(255, 140, 26, 0.2);
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: rgba(0, 0, 0, 0.5);
            ">
                <h2 style="
                    color: #ff8c1a;
                    font-family: 'Orbitron', sans-serif;
                    margin: 0;
                    font-size: 1.5rem;
                ">📋 Rules & Guidelines</h2>
                <button onclick="closeLegalPopup()" style="
                    background: none;
                    border: none;
                    color: rgba(255, 255, 255, 0.7);
                    font-size: 28px;
                    cursor: pointer;
                    transition: all 0.3s;
                " onmouseover="this.style.color='#ff8c1a'; this.style.transform='rotate(90deg)'" onmouseout="this.style.color='rgba(255,255,255,0.7)'; this.style.transform='rotate(0deg)'">×</button>
            </div>
            <div style="
                padding: 20px;
                overflow-y: auto;
                max-height: calc(85vh - 80px);
                color: rgba(255, 255, 255, 0.7);
                font-family: 'Rajdhani', sans-serif;
                line-height: 1.6;
            ">
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 0 0 15px 0;">🎮 1. Community Guidelines</h3>
                <p>Welcome to VictoryZone! Our community is built on respect, fair play, and positive engagement. All members must follow these guidelines to ensure a safe and enjoyable experience for everyone.</p>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">📝 2. Posting Rules</h3>
                <ul style="margin: 10px 0 15px 20px;">
                    <li><strong>No Spam:</strong> Repeated posting of similar content is prohibited.</li>
                    <li><strong>No Hate Speech:</strong> Discrimination, harassment, or offensive language is strictly forbidden.</li>
                    <li><strong>No Cheating:</strong> Sharing exploits, hacks, or cheating methods results in immediate ban.</li>
                    <li><strong>No NSFW Content:</strong> Mature or inappropriate content is not allowed.</li>
                </ul>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">🏆 3. Tournament Conduct</h3>
                <p>Participants in VictoryZone tournaments must:</p>
                <ul>
                    <li>Respect all players and officials</li>
                    <li>Follow tournament-specific rules and schedules</li>
                    <li>Report any violations to tournament admins</li>
                    <li>Accept final decisions made by tournament organizers</li>
                </ul>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">💬 4. Forum & Comments</h3>
                <ul>
                    <li>Stay on topic in discussion threads</li>
                    <li>No personal attacks or toxic behavior</li>
                    <li>Use constructive criticism when disagreeing</li>
                    <li>Respect moderators' decisions</li>
                </ul>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">⚠️ 5. Consequences of Violation</h3>
                <p>Violations may result in:</p>
                <ul>
                    <li>Warning notification</li>
                    <li>Temporary suspension (1-30 days)</li>
                    <li>Permanent account ban</li>
                    <li>Removal from tournaments</li>
                    <li>Legal action for severe violations</li>
                </ul>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">📞 6. Reporting Issues</h3>
                <p>To report violations, contact our support team at <strong style="color: #ff8c1a;">support@victoryzone.gg</strong></p>
            </div>
            <div style="
                padding: 15px 20px;
                border-top: 1px solid rgba(255, 140, 26, 0.2);
                display: flex;
                justify-content: flex-end;
                background: rgba(0, 0, 0, 0.5);
            ">
                <button onclick="closeLegalPopup()" style="
                    padding: 8px 25px;
                    background: transparent;
                    border: 1px solid #ff8c1a;
                    color: rgba(255, 255, 255, 0.7);
                    font-family: 'Orbitron', sans-serif;
                    font-size: 0.85rem;
                    cursor: pointer;
                    transition: all 0.3s;
                    transform: skew(-20deg);
                " onmouseover="this.style.background='#ff8c1a'; this.style.color='#000'; this.style.boxShadow='0 0 20px rgba(255,140,26,0.5)'" onmouseout="this.style.background='transparent'; this.style.color='rgba(255,255,255,0.7)'; this.style.boxShadow='none'">
                    <span style="display: inline-block; transform: skew(20deg);">Close</span>
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(popupContainer);
    document.body.style.overflow = 'hidden';
}

// Close with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLegalPopup();
    }
});

console.log('Popup functions ready!');
    </script>
</body>
</html>
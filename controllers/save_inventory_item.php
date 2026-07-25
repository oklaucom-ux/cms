<?php
// controllers/save_inventory_item.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

try {
    $id              = (int)($_POST['id'] ?? 0);
    $sku             = trim($_POST['sku'] ?? '');
    $name            = trim($_POST['name'] ?? '');
    $category        = trim($_POST['category'] ?? 'OTC Medicine');
    $dosage_form     = trim($_POST['dosage_form'] ?? 'Tablet');
    $manufacturer    = trim($_POST['manufacturer'] ?? '');
    $batch_number    = trim($_POST['batch_number'] ?? '');
    $expiry_date     = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $hsn_code        = trim($_POST['hsn_code'] ?? '');
    $unit_price      = floatval($_POST['unit_price'] ?? 0);
    $purchase_price  = floatval($_POST['purchase_price'] ?? 0);
    $quantity        = (int)($_POST['quantity'] ?? 0);
    $min_stock_alert = (int)($_POST['min_stock_alert'] ?? 10);
    $warehouse_zone  = trim($_POST['warehouse_zone'] ?? 'Main Store');
    $rack_location   = trim($_POST['rack_location'] ?? 'Rack A-1');
    $prescription    = isset($_POST['prescription_required']) && $_POST['prescription_required'] == '1' ? 1 : 0;

    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Item name is required.']);
        exit();
    }

    if (empty($sku)) {
        $sku = 'MED-' . strtoupper(substr(md5(uniqid()), 0, 8));
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE inventory_items SET 
            sku = ?, name = ?, category = ?, dosage_form = ?, manufacturer = ?, batch_number = ?, expiry_date = ?, hsn_code = ?, unit_price = ?, purchase_price = ?, quantity = ?, min_stock_alert = ?, warehouse_zone = ?, rack_location = ?, prescription_required = ?
            WHERE id = ?");
        $stmt->execute([
            $sku, $name, $category, $dosage_form, $manufacturer, $batch_number, $expiry_date, $hsn_code,
            $unit_price, $purchase_price, $quantity, $min_stock_alert, $warehouse_zone, $rack_location, $prescription,
            $id
        ]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO inventory_items 
            (sku, name, category, dosage_form, manufacturer, batch_number, expiry_date, hsn_code, unit_price, purchase_price, quantity, min_stock_alert, warehouse_zone, rack_location, prescription_required, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute([
            $sku, $name, $category, $dosage_form, $manufacturer, $batch_number, $expiry_date, $hsn_code,
            $unit_price, $purchase_price, $quantity, $min_stock_alert, $warehouse_zone, $rack_location, $prescription,
            $_SESSION['login_id']
        ]);
        $id = $pdo->lastInsertId();

        // Initial transaction log
        if ($quantity > 0) {
            $stmtLog = $pdo->prepare("INSERT INTO inventory_transactions (item_id, type, quantity_change, reason, created_by, created_at) VALUES (?, 'Stock In', ?, 'Initial Stock Entry', ?, CURRENT_TIMESTAMP)");
            $stmtLog->execute([$id, $quantity, $_SESSION['login_id']]);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Inventory item saved successfully!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

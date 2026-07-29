<?php
// controllers/save_inventory_item.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_items (
        id {$pkDef},
        sku VARCHAR(100) UNIQUE,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100) DEFAULT 'OTC Medicine',
        dosage_form VARCHAR(100) DEFAULT 'Tablet',
        manufacturer VARCHAR(255),
        batch_number VARCHAR(100),
        expiry_date DATE,
        hsn_code VARCHAR(50),
        unit_price DECIMAL(12,2) DEFAULT 0,
        purchase_price DECIMAL(12,2) DEFAULT 0,
        quantity INT DEFAULT 0,
        min_stock_alert INT DEFAULT 10,
        warehouse_zone VARCHAR(100) DEFAULT 'Main Store',
        rack_location VARCHAR(100) DEFAULT 'Rack A-1',
        prescription_required INT DEFAULT 0,
        storage_temp VARCHAR(50) DEFAULT 'Room Temp',
        discount_percent DECIMAL(5,2) DEFAULT 0,
        created_by VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_transactions (
        id {$pkDef},
        item_id INT NOT NULL,
        type VARCHAR(50) DEFAULT 'Stock In',
        quantity_change INT DEFAULT 0,
        reason TEXT,
        created_by VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

try {
    $action = $_REQUEST['action'] ?? 'save';
    $id     = (int)($_POST['id'] ?? $_POST['item_id'] ?? 0);

    if ($action === 'delete') {
        if ($id > 0) {
            $pdo->prepare("DELETE FROM inventory_items WHERE id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM inventory_transactions WHERE item_id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Inventory item deleted successfully.']);
            exit();
        }
        echo json_encode(['success' => false, 'error' => 'Invalid Item ID.']);
        exit();
    }
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

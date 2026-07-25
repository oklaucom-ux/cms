<?php
// inventory.php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Check permissions
if (!hasPermission($pdo, 'view_assets') && !hasPermission($pdo, 'view_crm')) {
    requirePermission($pdo, 'view_dashboard');
}

// Fetch Inventory Stats
$totalItems = $pdo->query("SELECT COUNT(*) FROM inventory_items")->fetchColumn() ?: 0;
$totalValuation = $pdo->query("SELECT SUM(quantity * unit_price) FROM inventory_items")->fetchColumn() ?: 0;
$lowStockCount = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE quantity <= min_stock_alert")->fetchColumn() ?: 0;

$today = date('Y-m-d');
$expiring30Days = date('Y-m-d', strtotime('+30 days'));

$expiringCount = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE expiry_date IS NOT NULL AND expiry_date != '' AND expiry_date <= '{$expiring30Days}'")->fetchColumn() ?: 0;

// Fetch All Items
$items = $pdo->query("SELECT * FROM inventory_items ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Recent Transactions
$transactions = $pdo->query("SELECT t.*, i.name as item_name, i.sku FROM inventory_transactions t LEFT JOIN inventory_items i ON t.item_id = i.id ORDER BY t.id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
?>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<style>
.inv-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.inv-title {
    font-size: 26px;
    font-weight: 800;
    color: var(--text-heading);
    display: flex;
    align-items: center;
    gap: 12px;
}
.inv-badge {
    font-size: 12px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 99px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 18px;
    margin-bottom: 28px;
}
.stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 18px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
}
.stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: white;
    flex-shrink: 0;
}
.stat-num { font-size: 24px; font-weight: 800; color: var(--text-heading); }
.stat-label { font-size: 13px; color: var(--text-muted); font-weight: 600; }

.inv-box {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    margin-bottom: 28px;
}

.inv-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.inv-table th {
    text-align: left;
    padding: 12px 14px;
    background: var(--bg-main);
    color: var(--text-heading);
    font-weight: 800;
    border-bottom: 2px solid var(--border-card);
}
.inv-table td {
    padding: 14px;
    border-bottom: 1px solid var(--border-card);
    color: var(--text-body);
}
.inv-table tr:hover {
    background: rgba(255,255,255,0.02);
}

.badge-exp-red { background: #ef4444; color: white; padding: 3px 8px; border-radius: 99px; font-weight: 800; font-size: 10.5px; }
.badge-exp-amber { background: #f59e0b; color: white; padding: 3px 8px; border-radius: 99px; font-weight: 800; font-size: 10.5px; }
.badge-exp-green { background: #10b981; color: white; padding: 3px 8px; border-radius: 99px; font-weight: 800; font-size: 10.5px; }

.inv-input, .inv-select, .inv-textarea {
    width: 100%;
    padding: 10px 14px;
    border-radius: 12px;
    border: 1px solid var(--border-card);
    background: var(--bg-main);
    color: var(--text-body);
    font-size: 13.5px;
    outline: none;
    transition: border 0.3s;
    box-sizing: border-box;
}
.inv-input:focus, .inv-select:focus, .inv-textarea:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16,185,129,0.15);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 700;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-primary { background: #10b981; color: white; }
.btn-primary:hover { background: #059669; }
.btn-outline { background: transparent; border: 1px solid var(--border-card); color: var(--text-body); }
.btn-outline:hover { background: rgba(255,255,255,0.05); }

#barcodeModalBox {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(8px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
</style>

<div class="main-content" style="padding: 24px;">
    <!-- Header -->
    <div class="inv-header">
        <div>
            <div class="inv-title">
                📦 Multipurpose Advanced Inventory Engine
                <span class="inv-badge">Medicine & OTC Ready</span>
            </div>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                Manage pharmaceutical medicines, OTC products, medical supplies, batch #, expiry dates, stock inward/outward, and storage racks.
            </p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="inventory_pos.php" class="btn-sm btn-primary" style="padding: 10px 16px; font-size:13.5px; background:linear-gradient(135deg, #6366f1, #4f46e5);">
                <i class="fas fa-cash-register"></i> 🏥 Pharmacy POS
            </a>
            <button type="button" onclick="executeAutoPo()" class="btn-sm btn-outline" style="padding: 10px 16px; font-size:13.5px;" title="Auto generate Purchase Order for all low-stock items">
                <i class="fas fa-shopping-cart"></i> 🛒 Auto Restock PO
            </button>
            <button type="button" onclick="openItemModal()" class="btn-sm btn-primary" style="padding: 10px 18px; font-size:13.5px;">
                <i class="fas fa-plus"></i> Add New Item
            </button>
        </div>
    </div>

    <!-- Analytics Dashboard -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div>
                <div class="stat-num"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($totalValuation, 0) ?></div>
                <div class="stat-label">Total Stock Valuation</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <div class="stat-num"><?= $lowStockCount ?></div>
                <div class="stat-label">Low Stock Alerts</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <i class="fas fa-calendar-times"></i>
            </div>
            <div>
                <div class="stat-num"><?= $expiringCount ?></div>
                <div class="stat-label">Expiring (< 30 Days)</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
                <i class="fas fa-boxes"></i>
            </div>
            <div>
                <div class="stat-num"><?= $totalItems ?></div>
                <div class="stat-label">Total Active SKUs</div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="inv-box" style="padding:16px 20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
            <input type="text" id="invSearchInput" onkeyup="filterInventoryTable()" placeholder="Search by SKU, item name, batch #, manufacturer, HSN..." class="inv-input" style="width: 340px;" />

            <div style="display:flex; gap:12px;">
                <select id="invCategoryFilter" onchange="filterInventoryTable()" class="inv-select" style="width: 180px;">
                    <option value="">All Categories</option>
                    <option value="Rx Medicine">Rx Medicine (Prescription)</option>
                    <option value="OTC Medicine">OTC Medicine</option>
                    <option value="Medical Supplies">Medical Supplies</option>
                    <option value="Equipment">General Equipment</option>
                    <option value="Consumable">Consumable</option>
                </select>

                <select id="invExpiryFilter" onchange="filterInventoryTable()" class="inv-select" style="width: 160px;">
                    <option value="">All Expiry Status</option>
                    <option value="expired">Expired Items</option>
                    <option value="near_expiry">Expiring Soon</option>
                    <option value="fresh">Fresh Items</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Inventory Master Table -->
    <div class="inv-box" style="padding:0; overflow:hidden;">
        <div style="overflow-x:auto;">
            <table class="inv-table">
                <thead>
                    <tr>
                        <th>SKU / HSN</th>
                        <th>Item & Manufacturer</th>
                        <th>Category & Dosage</th>
                        <th>Batch # & Expiry</th>
                        <th>Stock Level</th>
                        <th>Price (Unit)</th>
                        <th>Storage Rack</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="inventoryTableBody">
                    <?php foreach($items as $it): 
                        $qty = (int)$it['quantity'];
                        $min = (int)$it['min_stock_alert'];

                        // Expiry Status Calculation
                        $expStatus = 'fresh';
                        $expBadgeHtml = '<span class="badge-exp-green">FRESH</span>';
                        if (!empty($it['expiry_date'])) {
                            if ($it['expiry_date'] < $today) {
                                $expStatus = 'expired';
                                $expBadgeHtml = '<span class="badge-exp-red">EXPIRED</span>';
                            } elseif ($it['expiry_date'] <= $expiring30Days) {
                                $expStatus = 'near_expiry';
                                $expBadgeHtml = '<span class="badge-exp-amber">NEAR EXPIRY</span>';
                            }
                        }
                    ?>
                    <tr class="inv-row-item" data-search="<?= strtolower(htmlspecialchars($it['sku'].' '.$it['name'].' '.$it['batch_number'].' '.$it['manufacturer'].' '.$it['hsn_code'])) ?>" data-category="<?= htmlspecialchars($it['category']) ?>" data-expstatus="<?= $expStatus ?>">
                        <td>
                            <strong style="color:var(--text-heading);"><?= htmlspecialchars($it['sku']) ?></strong>
                            <?php if(!empty($it['hsn_code'])): ?>
                                <div style="font-size:11px; color:var(--text-muted);">HSN: <?= htmlspecialchars($it['hsn_code']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight:800; font-size:14px; color:var(--text-heading);">
                                <?= htmlspecialchars($it['name']) ?>
                                <?php if($it['prescription_required'] == 1): ?>
                                    <span style="font-size:9.5px; background:#ef4444; color:white; padding:1px 5px; border-radius:4px; font-weight:800;" title="Prescription Required">Rx</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:11.5px; color:var(--text-muted);"><?= htmlspecialchars($it['manufacturer'] ?: 'Generic') ?></div>
                        </td>
                        <td>
                            <div style="font-weight:700; color:#10b981;"><?= htmlspecialchars($it['category']) ?></div>
                            <div style="font-size:11.5px; color:var(--text-muted);"><?= htmlspecialchars($it['dosage_form']) ?></div>
                        </td>
                        <td>
                            <div style="font-weight:700; font-size:12px;"><?= htmlspecialchars($it['batch_number'] ?: 'Batch # N/A') ?></div>
                            <div style="margin-top:2px;">
                                <?= $expBadgeHtml ?>
                                <span style="font-size:11px; color:var(--text-muted); font-weight:600; margin-left:4px;"><?= htmlspecialchars($it['expiry_date'] ?: 'No Expiry') ?></span>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:14px; font-weight:900; color: <?= $qty <= $min ? '#ef4444' : 'var(--text-heading)' ?>;">
                                <?= $qty ?> Units
                            </div>
                            <?php if($qty <= $min): ?>
                                <div style="font-size:10px; color:#ef4444; font-weight:800;">⚠️ LOW STOCK (Min: <?= $min ?>)</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight:800; font-size:13.5px; color:var(--text-heading);"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($it['unit_price'], 2) ?></div>
                            <div style="font-size:10.5px; color:var(--text-muted);">Cost: <?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($it['purchase_price'], 2) ?></div>
                        </td>
                        <td>
                            <div style="font-weight:700; font-size:12px; color:var(--text-heading);"><?= htmlspecialchars($it['warehouse_zone']) ?></div>
                            <div style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($it['rack_location']) ?></div>
                        </td>
                        <td style="text-align:right;">
                            <div style="display:flex; gap:6px; justify-content:flex-end;">
                                <button type="button" onclick="openStockAdjustModal(<?= htmlspecialchars(json_encode($it)) ?>)" class="btn-sm btn-primary" title="Adjust Stock (In/Out)">
                                    <i class="fas fa-exchange-alt"></i> Stock
                                </button>
                                <button type="button" onclick="editItem(<?= htmlspecialchars(json_encode($it)) ?>)" class="btn-sm btn-outline" title="Edit Item">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" onclick="openHistoryModal(<?= $it['id'] ?>, '<?= htmlspecialchars(addslashes($it['name'])) ?>')" class="btn-sm btn-outline" title="View Stock Audit Trail">
                                    <i class="fas fa-history"></i> Logs
                                </button>
                                <button type="button" onclick="showBarcodeModal('<?= htmlspecialchars($it['sku']) ?>', '<?= htmlspecialchars(addslashes($it['name'])) ?>')" class="btn-sm btn-outline" title="Print Barcode">
                                    <i class="fas fa-barcode"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD / EDIT ITEM MODAL -->
<div id="itemModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(8px); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--bg-card); border:1px solid var(--border-card); border-radius:24px; padding:28px; width:90%; max-width:680px; box-shadow:0 20px 50px rgba(0,0,0,0.4); max-height:90vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div style="font-weight:800; font-size:20px; color:var(--text-heading);" id="itemModalTitle">Add New Inventory Item</div>
            <button type="button" onclick="closeItemModal()" style="background:none; border:none; color:var(--text-muted); font-size:24px; cursor:pointer;">&times;</button>
        </div>

        <form id="itemForm" onsubmit="submitItemForm(event)">
            <input type="hidden" id="item_id" name="id" value="0" />

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div style="grid-column:span 2;">
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Item Full Name *</label>
                    <input type="text" id="name" name="name" class="inv-input" placeholder="e.g. Paracetamol 500mg Tablets" required />
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Category</label>
                    <select id="category" name="category" class="inv-select">
                        <option value="Rx Medicine">Rx Medicine (Prescription)</option>
                        <option value="OTC Medicine">OTC Medicine</option>
                        <option value="Medical Supplies">Medical Supplies</option>
                        <option value="Equipment">General Equipment</option>
                        <option value="Consumable">Consumable</option>
                    </select>
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Dosage Form / Packaging</label>
                    <select id="dosage_form" name="dosage_form" class="inv-select">
                        <option value="Tablet">Tablet</option>
                        <option value="Capsule">Capsule</option>
                        <option value="Syrup">Syrup</option>
                        <option value="Injection">Injection</option>
                        <option value="Ointment">Ointment / Cream</option>
                        <option value="Strip">Strip</option>
                        <option value="Box">Box</option>
                        <option value="Bottle">Bottle</option>
                        <option value="Unit">Unit / Piece</option>
                    </select>
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Batch / Lot Number</label>
                    <input type="text" id="batch_number" name="batch_number" class="inv-input" placeholder="e.g. B2026-09" />
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Expiry Date</label>
                    <input type="date" id="expiry_date" name="expiry_date" class="inv-input" />
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">SKU Code (Auto-generated if empty)</label>
                    <input type="text" id="sku" name="sku" class="inv-input" placeholder="MED-123456" />
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">HSN / Tax Code</label>
                    <input type="text" id="hsn_code" name="hsn_code" class="inv-input" placeholder="3004" />
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Selling Unit Price *</label>
                    <input type="number" step="0.01" id="unit_price" name="unit_price" class="inv-input" placeholder="0.00" required />
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Purchase Cost Price</label>
                    <input type="number" step="0.01" id="purchase_price" name="purchase_price" class="inv-input" placeholder="0.00" />
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Initial Stock Quantity</label>
                    <input type="number" id="quantity" name="quantity" class="inv-input" placeholder="0" />
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Min Stock Alert Level</label>
                    <input type="number" id="min_stock_alert" name="min_stock_alert" class="inv-input" value="10" />
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Warehouse Zone</label>
                    <input type="text" id="warehouse_zone" name="warehouse_zone" class="inv-input" placeholder="Main Pharmacy Store" />
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Rack / Shelf Location</label>
                    <input type="text" id="rack_location" name="rack_location" class="inv-input" placeholder="Rack A - Shelf 2" />
                </div>

                <div style="grid-column:span 2; display:flex; align-items:center; gap:8px; margin-top:8px;">
                    <input type="checkbox" id="prescription_required" name="prescription_required" value="1" style="width:18px; height:18px; accent-color:#10b981;" />
                    <label for="prescription_required" style="font-size:13px; font-weight:700; color:var(--text-heading); cursor:pointer;">
                        Requires Medical Doctor Prescription (Rx Required)
                    </label>
                </div>
            </div>

            <div style="margin-top:24px; display:flex; gap:12px;">
                <button type="submit" class="btn-sm btn-primary" style="flex:1; padding:12px; font-size:14px;">
                    <i class="fas fa-save"></i> Save Inventory Item
                </button>
            </div>
        </form>
    </div>
</div>

<!-- STOCK ADJUSTMENT MODAL -->
<div id="stockAdjustModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(8px); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--bg-card); border:1px solid var(--border-card); border-radius:24px; padding:28px; width:90%; max-width:480px; box-shadow:0 20px 50px rgba(0,0,0,0.4);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div style="font-weight:800; font-size:18px; color:var(--text-heading);" id="stockAdjustTitle">Stock Adjustment</div>
            <button type="button" onclick="closeStockAdjustModal()" style="background:none; border:none; color:var(--text-muted); font-size:24px; cursor:pointer;">&times;</button>
        </div>

        <form id="stockAdjustForm" onsubmit="submitStockAdjust(event)">
            <input type="hidden" id="sa_item_id" name="item_id" />

            <div style="display:flex; flex-direction:column; gap:14px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Movement Type *</label>
                    <select id="sa_type" name="type" class="inv-select">
                        <option value="Stock In">Stock Inward (PO / Restock)</option>
                        <option value="Stock Out">Stock Outward (Sales / Dispense)</option>
                        <option value="Expired Write-off">Expired Stock Write-off</option>
                        <option value="Damage Write-off">Damaged Stock Write-off</option>
                    </select>
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Quantity Units *</label>
                    <input type="number" id="sa_quantity_change" name="quantity_change" class="inv-input" placeholder="e.g. 50" required min="1" />
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Reason / Notes</label>
                    <input type="text" id="sa_reason" name="reason" class="inv-input" placeholder="e.g. Purchase order PO-2026-88" />
                </div>

                <button type="submit" class="btn-sm btn-primary" style="padding:12px; margin-top:10px;">
                    <i class="fas fa-check"></i> Execute Stock Update
                </button>
            </div>
        </form>
    </div>
</div>

<!-- BARCODE PRINT MODAL -->
<div id="barcodeModalBox">
    <div style="background:var(--bg-card); border:1px solid var(--border-card); border-radius:24px; padding:28px; text-align:center; width:90%; max-width:400px;">
        <div style="font-weight:800; font-size:18px; color:var(--text-heading); margin-bottom:4px;" id="bcItemName">Barcode Label</div>
        <div style="font-size:12px; color:var(--text-muted); margin-bottom:18px;" id="bcItemSku">SKU</div>

        <div style="background:white; padding:16px; border-radius:12px; margin-bottom:20px; display:flex; justify-content:center;">
            <svg id="barcodeCanvas"></svg>
        </div>

        <div style="display:flex; gap:10px;">
            <button type="button" onclick="window.print()" class="btn-sm btn-primary" style="flex:1;">Print Label</button>
            <button type="button" onclick="closeBarcodeModal()" class="btn-sm btn-outline">Close</button>
        </div>
    </div>
</div>

<script>
function filterInventoryTable() {
    const q = document.getElementById('invSearchInput').value.toLowerCase();
    const cat = document.getElementById('invCategoryFilter').value;
    const exp = document.getElementById('invExpiryFilter').value;

    document.querySelectorAll('.inv-row-item').forEach(row => {
        const matchQ = !q || row.dataset.search.includes(q);
        const matchCat = !cat || row.dataset.category === cat;
        const matchExp = !exp || row.dataset.expstatus === exp;
        row.style.display = (matchQ && matchCat && matchExp) ? 'table-row' : 'none';
    });
}

function openItemModal() {
    document.getElementById('itemForm').reset();
    document.getElementById('item_id').value = 0;
    document.getElementById('itemModalTitle').innerText = 'Add New Inventory Item';
    document.getElementById('itemModal').style.display = 'flex';
}

function closeItemModal() {
    document.getElementById('itemModal').style.display = 'none';
}

function editItem(it) {
    document.getElementById('item_id').value = it.id;
    document.getElementById('sku').value = it.sku;
    document.getElementById('name').value = it.name;
    document.getElementById('category').value = it.category;
    document.getElementById('dosage_form').value = it.dosage_form;
    document.getElementById('manufacturer').value = it.manufacturer || '';
    document.getElementById('batch_number').value = it.batch_number || '';
    document.getElementById('expiry_date').value = it.expiry_date || '';
    document.getElementById('hsn_code').value = it.hsn_code || '';
    document.getElementById('unit_price').value = it.unit_price;
    document.getElementById('purchase_price').value = it.purchase_price;
    document.getElementById('quantity').value = it.quantity;
    document.getElementById('min_stock_alert').value = it.min_stock_alert;
    document.getElementById('warehouse_zone').value = it.warehouse_zone;
    document.getElementById('rack_location').value = it.rack_location;
    document.getElementById('prescription_required').checked = (it.prescription_required == 1);

    document.getElementById('itemModalTitle').innerText = 'Edit Inventory Item';
    document.getElementById('itemModal').style.display = 'flex';
}

async function submitItemForm(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('itemForm'));

    Swal.fire({ title: 'Saving Item...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const resp = await fetch('controllers/save_inventory_item.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) {
            Swal.fire('Saved!', res.message, 'success').then(() => window.location.reload());
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Server connection error.', 'error');
    }
}

function openStockAdjustModal(it) {
    document.getElementById('sa_item_id').value = it.id;
    document.getElementById('stockAdjustTitle').innerText = 'Stock Adjustment - ' + it.name;
    document.getElementById('stockAdjustForm').reset();
    document.getElementById('sa_item_id').value = it.id;
    document.getElementById('stockAdjustModal').style.display = 'flex';
}

function closeStockAdjustModal() {
    document.getElementById('stockAdjustModal').style.display = 'none';
}

async function submitStockAdjust(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('stockAdjustForm'));

    Swal.fire({ title: 'Updating Stock...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const resp = await fetch('controllers/adjust_stock.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) {
            Swal.fire('Updated!', res.message, 'success').then(() => window.location.reload());
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Server connection failed.', 'error');
    }
}

function showBarcodeModal(sku, name) {
    document.getElementById('bcItemName').innerText = name;
    document.getElementById('bcItemSku').innerText = 'SKU: ' + sku;

    JsBarcode("#barcodeCanvas", sku, {
        format: "CODE128",
        lineColor: "#000",
        width: 2,
        height: 50,
        displayValue: true
    });

    document.getElementById('barcodeModalBox').style.display = 'flex';
}

function closeBarcodeModal() {
    document.getElementById('barcodeModalBox').style.display = 'none';
}

async function executeAutoPo() {
    Swal.fire({ title: 'Generating Auto Restock PO...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const resp = await fetch('controllers/auto_po_inventory.php', { method: 'POST' });
        const res = await resp.json();

        if (res.success) {
            Swal.fire('PO Draft Created!', res.message, 'success').then(() => {
                window.location.href = 'procurement.php';
            });
        } else {
            Swal.fire('Restock Info', res.error, 'info');
        }
    } catch (err) {
        Swal.fire('Error', 'Failed to communicate with server.', 'error');
    }
}
<!-- STOCK MOVEMENT HISTORY MODAL -->
<div id="historyModalBox" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(8px); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--bg-card); border:1px solid var(--border-card); border-radius:24px; padding:28px; width:90%; max-width:680px; box-shadow:0 20px 50px rgba(0,0,0,0.4); max-height:85vh; display:flex; flex-direction:column;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div style="font-weight:800; font-size:18px; color:var(--text-heading);" id="histModalTitle">Item Audit Trail History</div>
            <button type="button" onclick="document.getElementById('historyModalBox').style.display='none'" style="background:none; border:none; color:var(--text-muted); font-size:24px; cursor:pointer;">&times;</button>
        </div>

        <div style="overflow-y:auto; flex:1;">
            <table class="inv-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Type</th>
                        <th>Qty Change</th>
                        <th>Reason / Notes</th>
                        <th>Logged By</th>
                    </tr>
                </thead>
                <tbody id="histTableBody">
                    <tr><td colspan="5" style="text-align:center;">Loading audit trail...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
async function openHistoryModal(itemId, itemName) {
    document.getElementById('histModalTitle').innerText = 'Stock Audit Trail - ' + itemName;
    document.getElementById('histTableBody').innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px;">Loading history...</td></tr>';
    document.getElementById('historyModalBox').style.display = 'flex';

    try {
        const resp = await fetch(`controllers/get_stock_history.php?item_id=${itemId}`);
        const res = await resp.json();

        if (res.success) {
            if (res.transactions.length === 0) {
                document.getElementById('histTableBody').innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px; color:var(--text-muted);">No stock transactions logged yet.</td></tr>';
                return;
            }

            let html = '';
            res.transactions.forEach(t => {
                const isPlus = parseInt(t.quantity_change) > 0;
                html += `
                <tr>
                    <td style="font-size:11.5px; color:var(--text-muted);">${t.created_at}</td>
                    <td><strong style="color:${isPlus ? '#10b981' : '#ef4444'};">${t.type}</strong></td>
                    <td style="font-weight:800; color:${isPlus ? '#10b981' : '#ef4444'};">${isPlus ? '+' : ''}${t.quantity_change}</td>
                    <td style="font-size:12px;">${t.reason || '—'}</td>
                    <td style="font-size:11.5px; color:var(--text-muted);">${t.created_by || 'System'}</td>
                </tr>`;
            });
            document.getElementById('histTableBody').innerHTML = html;
        } else {
            document.getElementById('histTableBody').innerHTML = `<tr><td colspan="5" style="color:#ef4444; text-align:center; padding:20px;">${res.error}</td></tr>`;
        }
    } catch (err) {
        document.getElementById('histTableBody').innerHTML = '<tr><td colspan="5" style="color:#ef4444; text-align:center; padding:20px;">Server connection error.</td></tr>';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>

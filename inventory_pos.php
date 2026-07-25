<?php
// inventory_pos.php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Fetch All Available Items
$items = $pdo->query("SELECT * FROM inventory_items WHERE quantity > 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.pos-container {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 24px;
    height: calc(100vh - 110px);
}

.pos-catalog {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 20px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    overflow: hidden;
}

.pos-cart {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 20px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
}

.pos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 14px;
    overflow-y: auto;
    padding-right: 6px;
    flex: 1;
    margin-top: 16px;
}

.pos-item-card {
    background: var(--bg-main);
    border: 1px solid var(--border-card);
    border-radius: 14px;
    padding: 14px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.pos-item-card:hover {
    border-color: #10b981;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(16,185,129,0.15);
}

.pos-input {
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
.pos-input:focus { border-color: #10b981; }

.cart-list {
    flex: 1;
    overflow-y: auto;
    margin: 16px 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.cart-item {
    background: var(--bg-main);
    border: 1px solid var(--border-card);
    border-radius: 12px;
    padding: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.rx-warning {
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.3);
    border-radius: 12px;
    padding: 10px 14px;
    color: #ef4444;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 12px;
    display: none;
}
</style>

<div class="main-content" style="padding: 24px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div style="font-size:24px; font-weight:800; color:var(--text-heading); display:flex; align-items:center; gap:10px;">
            🏥 Pharmacy POS & Prescription Dispenser
        </div>
        <a href="inventory.php" class="btn-sm btn-outline" style="padding:8px 16px; font-weight:700;">
            <i class="fas fa-arrow-left"></i> Back to Inventory
        </a>
    </div>

    <div class="pos-container">
        <!-- Item Catalog Panel -->
        <div class="pos-catalog">
            <div style="display:flex; gap:12px;">
                <input type="text" id="posSearch" onkeyup="filterPosItems()" placeholder="🔍 Scan Barcode or Search by Medicine, SKU, Batch..." class="pos-input" autofocus />
            </div>

            <div class="pos-grid" id="posGrid">
                <?php foreach($items as $it): 
                    $dis = floatval($it['discount_percent']);
                    $price = floatval($it['unit_price']);
                    $finalPrice = $dis > 0 ? $price * (1 - ($dis / 100)) : $price;
                ?>
                <div class="pos-item-card" data-search="<?= strtolower(htmlspecialchars($it['name'].' '.$it['sku'].' '.$it['batch_number'])) ?>" onclick="addToCart(<?= htmlspecialchars(json_encode($it)) ?>)">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <div style="font-weight:800; font-size:13.5px; color:var(--text-heading); margin-bottom:2px;">
                                <?= htmlspecialchars($it['name']) ?>
                            </div>
                            <?php if($it['prescription_required'] == 1): ?>
                                <span style="font-size:9px; background:#ef4444; color:white; padding:1px 5px; border-radius:4px; font-weight:800;">Rx</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($it['category']) ?> • Batch: <?= htmlspecialchars($it['batch_number'] ?: 'N/A') ?></div>
                        
                        <?php if(!empty($it['storage_temp']) && stripos($it['storage_temp'], 'cold') !== false): ?>
                            <div style="font-size:10px; color:#3b82f6; font-weight:700; margin-top:2px;">❄️ <?= htmlspecialchars($it['storage_temp']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top:10px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <span style="font-size:14px; font-weight:900; color:#10b981;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($finalPrice, 2) ?></span>
                            <?php if($dis > 0): ?>
                                <span style="font-size:10px; text-decoration:line-through; color:var(--text-muted);"><?= number_format($price, 2) ?></span>
                            <?php endif; ?>
                        </div>
                        <span style="font-size:11px; font-weight:700; color:var(--text-muted);"><?= $it['quantity'] ?> Left</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Checkout Cart Panel -->
        <div class="pos-cart">
            <div style="font-weight:800; font-size:16px; color:var(--text-heading); margin-bottom:12px;">
                🛒 Sales Checkout Cart
            </div>

            <!-- Rx Prescription Alert Warning -->
            <div class="rx-warning" id="rxWarning">
                ⚠️ Contains Prescription (Rx) Medicine. Doctor Name required!
            </div>

            <div style="display:flex; flex-direction:column; gap:10px;">
                <input type="text" id="patient_name" placeholder="Patient Name (Default: Walk-in)" class="pos-input" value="Walk-in Patient" />
                <input type="text" id="doctor_name" placeholder="Prescribing Doctor Name (Required for Rx)" class="pos-input" />
            </div>

            <div class="cart-list" id="cartList">
                <div style="text-align:center; padding:40px; color:var(--text-muted); font-size:13px;">
                    Cart is empty. Click any item on the left to add to sale.
                </div>
            </div>

            <!-- Cart Summary Totals -->
            <div style="border-top:1px solid var(--border-card); padding-top:14px; display:flex; flex-direction:column; gap:8px;">
                <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--text-body);">
                    <span>Subtotal:</span>
                    <span id="lblSubtotal">₹0.00</span>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; font-size:13px; color:var(--text-body);">
                    <span>Discount:</span>
                    <input type="number" id="posDiscount" onchange="renderCart()" onkeyup="renderCart()" value="0" class="pos-input" style="width:80px; padding:4px 8px; text-align:right;" />
                </div>

                <div style="display:flex; justify-content:space-between; font-size:18px; font-weight:900; color:var(--text-heading); margin-top:4px;">
                    <span>Grand Total:</span>
                    <span id="lblGrandTotal" style="color:#10b981;">₹0.00</span>
                </div>

                <button type="button" onclick="checkoutPOS()" class="premium-btn" style="width:100%; padding:12px; justify-content:center; background:linear-gradient(135deg, #10b981, #059669); margin-top:10px; font-size:15px;">
                    ⚡ Complete POS Sale & Print Invoice
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];

function filterPosItems() {
    const q = document.getElementById('posSearch').value.toLowerCase();
    document.querySelectorAll('.pos-item-card').forEach(card => {
        card.style.display = (!q || card.dataset.search.includes(q)) ? 'flex' : 'none';
    });
}

function addToCart(item) {
    const existing = cart.find(c => c.id === item.id);
    if (existing) {
        if (existing.qty < item.quantity) {
            existing.qty++;
        } else {
            Swal.fire('Stock Limit', `Maximum available stock is ${item.quantity} units.`, 'warning');
        }
    } else {
        const dis = parseFloat(item.discount_percent || 0);
        const p = parseFloat(item.unit_price);
        const finalP = dis > 0 ? p * (1 - (dis / 100)) : p;

        cart.push({
            id: item.id,
            name: item.name,
            price: finalP,
            rx: (item.prescription_required == 1),
            stock: item.quantity,
            qty: 1
        });
    }
    renderCart();
}

function updateCartQty(id, delta) {
    const item = cart.find(c => c.id === id);
    if (!item) return;

    item.qty += delta;
    if (item.qty <= 0) {
        cart = cart.filter(c => c.id !== id);
    } else if (item.qty > item.stock) {
        item.qty = item.stock;
        Swal.fire('Stock Limit', `Maximum available stock is ${item.stock} units.`, 'warning');
    }
    renderCart();
}

function renderCart() {
    const cartList = document.getElementById('cartList');
    const rxWarning = document.getElementById('rxWarning');

    if (cart.length === 0) {
        cartList.innerHTML = '<div style="text-align:center; padding:40px; color:var(--text-muted); font-size:13px;">Cart is empty. Click any item on the left to add to sale.</div>';
        document.getElementById('lblSubtotal').innerText = '₹0.00';
        document.getElementById('lblGrandTotal').innerText = '₹0.00';
        rxWarning.style.display = 'none';
        return;
    }

    let html = '';
    let subtotal = 0;
    let hasRx = false;

    cart.forEach(c => {
        const lineTotal = c.price * c.qty;
        subtotal += lineTotal;
        if (c.rx) hasRx = true;

        html += `
        <div class="cart-item">
            <div>
                <div style="font-weight:700; font-size:13px; color:var(--text-heading);">
                    ${c.name} ${c.rx ? '<span style="color:#ef4444;font-weight:bold;">[Rx]</span>' : ''}
                </div>
                <div style="font-size:11px; color:var(--text-muted);">₹${c.price.toFixed(2)} x ${c.qty}</div>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <button type="button" onclick="updateCartQty(${c.id}, -1)" style="width:24px; height:24px; border-radius:6px; border:1px solid var(--border-card); background:var(--bg-card); cursor:pointer;">-</button>
                <span style="font-weight:800; font-size:13px;">${c.qty}</span>
                <button type="button" onclick="updateCartQty(${c.id}, 1)" style="width:24px; height:24px; border-radius:6px; border:1px solid var(--border-card); background:var(--bg-card); cursor:pointer;">+</button>
            </div>
        </div>`;
    });

    cartList.innerHTML = html;
    rxWarning.style.display = hasRx ? 'block' : 'none';

    const discount = parseFloat(document.getElementById('posDiscount').value || 0);
    const grandTotal = Math.max(0, subtotal - discount);

    document.getElementById('lblSubtotal').innerText = '₹' + subtotal.toFixed(2);
    document.getElementById('lblGrandTotal').innerText = '₹' + grandTotal.toFixed(2);
}

async function checkoutPOS() {
    if (cart.length === 0) {
        Swal.fire('Empty Cart', 'Please add items to cart before completing sale.', 'warning');
        return;
    }

    const patientName = document.getElementById('patient_name').value.trim() || 'Walk-in Patient';
    const doctorName  = document.getElementById('doctor_name').value.trim();
    const discount    = parseFloat(document.getElementById('posDiscount').value || 0);

    const hasRx = cart.some(c => c.rx);
    if (hasRx && !doctorName) {
        Swal.fire('Rx Required', 'This cart contains Prescription medicines. Please enter Prescribing Doctor Name.', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('patient_name', patientName);
    formData.append('doctor_name', doctorName);
    formData.append('discount', discount);
    formData.append('items', JSON.stringify(cart));

    Swal.fire({ title: 'Processing Sale...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const resp = await fetch('controllers/pos_checkout.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.success) {
            Swal.fire({
                title: 'Sale Completed!',
                text: `${res.message}\nTotal Amount: ₹${res.grand_total.toFixed(2)}`,
                icon: 'success',
                showCancelButton: true,
                confirmButtonText: 'Print Tax Invoice',
                cancelButtonText: 'New Sale'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.print();
                }
                window.location.reload();
            });
        } else {
            Swal.fire('Checkout Error', res.error, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Server connection failed.', 'error');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>

<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'manage_website');

if (!in_array($_SESSION['role'], ['Admin', 'Super Admin'])) die("Unauthorized Setting Access");

// Fetch the website blocks JSON
$blocksJson = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'public_website_blocks'")->fetchColumn();

if (!$blocksJson) {
    // Default initial blocks
    $blocks = [
        [
            'id' => 'hero_1',
            'type' => 'hero',
            'visible' => true,
            'title' => 'The Future of Corporate Management.',
            'subtitle' => 'A completely unified ecosystem for tasks, HR, communication, and learning. Engineered for extreme modularity.'
        ],
        [
            'id' => 'features_1',
            'type' => 'features',
            'visible' => true,
            'title' => 'Enterprise Modules',
            'subtitle' => 'Discover our suite of fully integrated tools designed for extreme efficiency.'
        ],
        [
            'id' => 'about_1',
            'type' => 'about',
            'visible' => true,
            'title' => 'Our Mission',
            'subtitle' => 'We are dedicated to building scalable infrastructure that empowers global enterprises to achieve more with less friction.'
        ],
        [
            'id' => 'careers_1',
            'type' => 'careers',
            'visible' => true,
            'title' => 'Open Positions',
            'subtitle' => 'Join our rapidly growing team of engineers and operators.'
        ]
    ];
} else {
    $blocks = json_decode($blocksJson, true);
}

// Ensure new block types exist in the array (backward compatibility)
$existingTypes = array_column($blocks, 'type');
$newBlocks = [
    [
        'id' => 'testimonials_1', 'type' => 'testimonials', 'visible' => false,
        'title' => 'Trusted by Industry Leaders', 'subtitle' => 'See what our clients say about the platform.',
        'content' => json_encode([
            ['quote' => 'This ERP changed how we operate globally.', 'author' => 'Jane Doe, CEO'],
            ['quote' => 'The modularity is unmatched in the industry.', 'author' => 'John Smith, CTO']
        ])
    ],
    [
        'id' => 'pricing_1', 'type' => 'pricing', 'visible' => false,
        'title' => 'Transparent Pricing', 'subtitle' => 'Simple, modular pricing for teams of all sizes.',
        'content' => json_encode([
            ['name' => 'Starter', 'price' => '$49/mo', 'features' => 'Up to 10 Users, Core HR, Tasks'],
            ['name' => 'Professional', 'price' => '$199/mo', 'features' => 'Up to 50 Users, Full CRM, Payroll'],
            ['name' => 'Enterprise', 'price' => 'Custom', 'features' => 'Unlimited Users, Custom Modules']
        ])
    ],
    [
        'id' => 'faq_1', 'type' => 'faq', 'visible' => false,
        'title' => 'Frequently Asked Questions', 'subtitle' => 'Everything you need to know about the platform.',
        'content' => json_encode([
            ['q' => 'Is data encrypted?', 'a' => 'Yes, AES-256 encryption.'],
            ['q' => 'Can we host on-premise?', 'a' => 'Yes, enterprise plans support self-hosting.']
        ])
    ],
    [
        'id' => 'custom_html_1', 'type' => 'custom_html', 'visible' => false,
        'title' => 'Watch the Demo', 'subtitle' => 'A complete overview of the system.',
        'content' => '<div style="text-align:center; padding: 20px; background:#1e293b; border-radius:12px; color:white;">Paste your custom HTML or Video iFrame here!</div>'
    ]
];

foreach ($newBlocks as $nb) {
    if (!in_array($nb['type'], $existingTypes)) {
        $blocks[] = $nb;
    }
}
?>

<style>
.block-item {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: var(--radius-md);
    margin-bottom: 16px;
    padding: 16px;
    display: flex;
    gap: 16px;
    align-items: flex-start;
    box-shadow: var(--shadow-xs);
    box-shadow: var(--shadow-xs);
    transition: box-shadow 0.2s, transform 0.2s;
}
.block-item.dragging { opacity: 0.5; box-shadow: var(--shadow-soft); transform: scale(1.01); }
.block-handle { font-size: 24px; color: var(--text-muted); padding-top: 4px; cursor: grab; }
.block-handle:active { cursor: grabbing; }
.block-content { flex: 1; display: flex; flex-direction: column; gap: 12px; }
.block-header { display: flex; justify-content: space-between; align-items: center; }
.block-type-badge { background: var(--primary-light); color: var(--primary-color); font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 99px; text-transform: uppercase; }
.visibility-toggle { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; cursor: pointer; color: var(--text-body); }
</style>

<div class="content-section active">
    <div class="section-header">
        <h2>🌐 Dynamic Website Builder</h2>
        <button class="add-button" onclick="document.getElementById('websiteForm').submit();">💾 Save Website Layout</button>
    </div>

    <div style="background: white; padding: 32px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); max-width: 800px;">
        <p style="color: var(--text-muted); margin-bottom: 24px;">Drag and drop the blocks below to reorder them on the public landing page. You can edit the text or toggle their visibility.</p>
        
        <form id="websiteForm" method="POST" action="controllers/save_website.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div id="blockContainer">
                <?php foreach($blocks as $index => $block): ?>
                <div class="block-item" data-index="<?= $index ?>">
                    <div class="block-handle">≡</div>
                    <div class="block-content">
                        
                        <div class="block-header">
                            <div class="block-type-badge"><?= htmlspecialchars(ucfirst($block['type'])) ?> Block</div>
                            <label class="visibility-toggle">
                                <input type="hidden" name="blocks[<?= $index ?>][visible]" value="0">
                                <input type="checkbox" name="blocks[<?= $index ?>][visible]" value="1" <?= $block['visible'] ? 'checked' : '' ?>>
                                Visible on Site
                            </label>
                        </div>

                        <input type="hidden" name="blocks[<?= $index ?>][type]" value="<?= htmlspecialchars($block['type']) ?>">
                        <input type="hidden" name="blocks[<?= $index ?>][id]" value="<?= htmlspecialchars($block['id']) ?>">

                        <div class="form-group" style="margin-bottom:0;">
                            <label>Headline</label>
                            <input type="text" name="blocks[<?= $index ?>][title]" value="<?= htmlspecialchars($block['title'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Subheadline / Description</label>
                            <textarea name="blocks[<?= $index ?>][subtitle]" rows="2" required><?= htmlspecialchars($block['subtitle'] ?? '') ?></textarea>
                        </div>

                        <?php if(in_array($block['type'], ['testimonials', 'pricing', 'faq', 'custom_html'])): ?>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Data / Content (JSON or HTML)</label>
                            <textarea name="blocks[<?= $index ?>][content]" rows="4" style="font-family:monospace; font-size:12px;"><?= htmlspecialchars($block['content'] ?? '') ?></textarea>
                        </div>
                        <?php endif; ?>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Optional Button Text</label>
                                <input type="text" name="blocks[<?= $index ?>][button_text]" value="<?= htmlspecialchars($block['button_text'] ?? '') ?>" placeholder="e.g. Request a Call">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Optional Button Link</label>
                                <input type="text" name="blocks[<?= $index ?>][button_url]" value="<?= htmlspecialchars($block['button_url'] ?? '') ?>" placeholder="e.g. #contact or /login.php">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </form>

    </div>
</div>

<script>
// Simple HTML5 Drag and Drop for reordering blocks
const container = document.getElementById('blockContainer');
let draggedItem = null;

// Allow dragging only from the handle
document.querySelectorAll('.block-handle').forEach(handle => {
    handle.addEventListener('mousedown', function() {
        this.closest('.block-item').setAttribute('draggable', 'true');
    });
    handle.addEventListener('mouseup', function() {
        this.closest('.block-item').removeAttribute('draggable');
    });
    handle.addEventListener('mouseleave', function() {
        this.closest('.block-item').removeAttribute('draggable');
    });
});

container.addEventListener('dragstart', e => {
    if(e.target.classList.contains('block-item')) {
        draggedItem = e.target;
        setTimeout(() => e.target.classList.add('dragging'), 0);
    }
});

container.addEventListener('dragend', e => {
    if(e.target.classList.contains('block-item')) {
        e.target.classList.remove('dragging');
        draggedItem = null;
        updateIndices();
    }
});

container.addEventListener('dragover', e => {
    e.preventDefault();
    const afterElement = getDragAfterElement(container, e.clientY);
    const dragging = document.querySelector('.dragging');
    if (afterElement == null) {
        container.appendChild(dragging);
    } else {
        container.insertBefore(dragging, afterElement);
    }
});

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.block-item:not(.dragging)')];
    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

function updateIndices() {
    // Re-index the name attributes so PHP parses the array correctly
    const items = container.querySelectorAll('.block-item');
    items.forEach((item, index) => {
        item.querySelectorAll('input[name^="blocks["], textarea[name^="blocks["]').forEach(input => {
            const name = input.getAttribute('name');
            const newName = name.replace(/blocks\[\d+\]/, `blocks[${index}]`);
            input.setAttribute('name', newName);
        });
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>

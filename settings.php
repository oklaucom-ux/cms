<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if (!in_array($_SESSION['role'], ['Admin', 'Super Admin']))
    die("Unauthorized Setting Access");

// Auto-migrate settings table
try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id {$pkDef},
        setting_key VARCHAR(255) NOT NULL,
        setting_value TEXT
    )");
} catch (Exception $e) {}

// Fetch current settings
$currentSettings = [];
try {
    foreach ($pdo->query("SELECT * FROM settings") as $row) {
        $currentSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}

$cName = $currentSettings['company_name'] ?? 'Cyno Management';
$cEmail = $currentSettings['company_email'] ?? 'admin@cyno.com';
$cCurrency = $currentSettings['currency'] ?? '₹';
$cTimezone = $currentSettings['timezone'] ?? 'UTC';
$cWebsite = $currentSettings['enable_public_website'] ?? 'false';

// Fetch Custom Statuses
try {
    $customStatuses = $pdo->query("SELECT * FROM custom_statuses ORDER BY module, sort_order")->fetchAll(PDO::FETCH_ASSOC);
    $projectStatuses = array_filter($customStatuses, fn($s) => $s['module'] === 'projects');
    $taskStatuses = array_filter($customStatuses, fn($s) => $s['module'] === 'tasks');
} catch (Exception $e) {
    $customStatuses = []; $projectStatuses = []; $taskStatuses = [];
}
?>

<div class="content-section active">
    <div class="section-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
            <h2 style="margin:0; font-size:22px; font-weight:700; color:var(--text-heading);">⚙️ System Configuration & Integrations</h2>
            <p style="margin:4px 0 0 0; color:var(--text-muted); font-size:13px;">Manage global organization defaults, branding logos, mailers, currency, and custom workflow statuses.</p>
        </div>
    </div>

    <!-- Top Executive Settings Analytics -->
    <div class="dashboard-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:28px;">
        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Organization Entity</div>
            <div style="font-size:18px; font-weight:800; color:var(--text-heading);"><?= htmlspecialchars($cName) ?></div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;"><?= htmlspecialchars($cEmail) ?></div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">System Currency</div>
            <div style="font-size:28px; font-weight:800; color:#10b981;"><?= htmlspecialchars($cCurrency) ?></div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Global Billing Default</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Public Portal Web</div>
            <div style="font-size:16px; font-weight:700; margin-top:6px; color:<?= $cWebsite === 'true' ? '#10b981' : '#64748b' ?>;">
                <?= $cWebsite === 'true' ? '🌐 Active & Live' : '🔒 Internal Only' ?>
            </div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Landing Page Visibility</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">System Security</div>
            <div style="font-size:16px; font-weight:700; margin-top:6px; color:#6366f1;">
                🛡️ CSRF Protected
            </div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Session Token Validation</div>
        </div>
    </div>

    <div style="background: var(--bg-card); padding: 32px; border-radius: 16px; border: 1px solid var(--border-card); max-width: 600px;">
        <form method="POST" action="controllers/save_settings.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label>Company Name (Displayed Globally)</label>
                <input type="text" name="company_name" value="<?= htmlspecialchars($cName) ?>" required>
            </div>

            <div class="form-group">
                <label>Company Logo</label>
                <?php if (!empty($currentSettings['company_logo'])): ?>
                    <div style="margin-bottom: 10px;">
                        <img src="<?= htmlspecialchars($currentSettings['company_logo']) ?>" alt="Current Logo"
                            style="max-height: 50px; border-radius: 4px;">
                    </div>
                <?php endif; ?>
                <input type="file" name="company_logo" accept="image/png, image/jpeg, image/gif, image/svg+xml"
                    style="padding: 8px;">
                <p style="font-size: 12px; color: #6b7280; margin-top: 4px;">Leave blank to keep the current logo.
                    Recommended height: 40px - 80px.</p>
            </div>

            <div class="form-group">
                <label>System Email Address</label>
                <input type="email" name="company_email" value="<?= htmlspecialchars($cEmail) ?>" required>
            </div>

            <div class="form-group">
                <label>Primary Currency Symbol (For Invoices)</label>
                <select name="currency">
                    <option value="₹" <?= $cCurrency == '₹' ? 'selected' : '' ?>>₹ (INR)</option>
                    <option value="$" <?= $cCurrency == '$' ? 'selected' : '' ?>>$ (USD)</option>
                    <option value="€" <?= $cCurrency == '€' ? 'selected' : '' ?>>€ (EUR)</option>
                    <option value="£" <?= $cCurrency == '£' ? 'selected' : '' ?>>£ (GBP)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Primary Theme Color (Hex or RGB)</label>
                <div style="display: flex; gap: 10px;">
                    <input type="color" id="theme_color_picker" value="<?= htmlspecialchars($currentSettings['primary_color'] ?? '#4f46e5') ?>" style="width: 50px; height: 40px; padding: 0; border: none; cursor: pointer;">
                    <input type="text" name="primary_color" id="theme_color_text" value="<?= htmlspecialchars($currentSettings['primary_color'] ?? '#4f46e5') ?>" required style="flex: 1;">
                </div>
                <script>
                    document.getElementById('theme_color_picker').addEventListener('input', (e) => {
                        document.getElementById('theme_color_text').value = e.target.value;
                    });
                    document.getElementById('theme_color_text').addEventListener('input', (e) => {
                        document.getElementById('theme_color_picker').value = e.target.value;
                    });
                </script>
            </div>

            <h3 style="margin-top: 32px; margin-bottom: 16px; color: var(--text-heading);">SMTP Email Engine</h3>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Configure outbound mail server for
                automated system emails.</p>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label>SMTP Host</label>
                    <input type="text" name="smtp_host"
                        value="<?= htmlspecialchars($currentSettings['smtp_host'] ?? '') ?>"
                        placeholder="smtp.gmail.com">
                </div>
                <div class="form-group">
                    <label>SMTP Port</label>
                    <input type="number" name="smtp_port"
                        value="<?= htmlspecialchars($currentSettings['smtp_port'] ?? '587') ?>">
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label>SMTP Username</label>
                    <input type="text" name="smtp_user"
                        value="<?= htmlspecialchars($currentSettings['smtp_user'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>SMTP Password</label>
                    <input type="password" name="smtp_pass"
                        value="<?= htmlspecialchars($currentSettings['smtp_pass'] ?? '') ?>" placeholder="••••••••">
                </div>
            </div>
            <div class="form-group">
                <label>From Email Address</label>
                <input type="email" name="smtp_from"
                    value="<?= htmlspecialchars($currentSettings['smtp_from'] ?? $cEmail) ?>">
            </div>

            <div class="form-group">
                <label>System Timezone</label>
                <select name="timezone">
                    <option value="UTC" <?= $cTimezone == 'UTC' ? 'selected' : '' ?>>UTC</option>
                    <option value="America/New_York" <?= $cTimezone == 'America/New_York' ? 'selected' : '' ?>>
                        America/New_York
                    </option>
                    <option value="Europe/London" <?= $cTimezone == 'Europe/London' ? 'selected' : '' ?>>Europe/London
                    </option>
                    <option value="Asia/Kolkata" <?= $cTimezone == 'Asia/Kolkata' ? 'selected' : '' ?>>Asia/Kolkata
                    </option>
                </select>
            </div>

            <div class="form-group" style="background:var(--bg-hover); padding:15px; border-radius:8px; margin-top:20px;">
                <label style="color:var(--text-heading);">Enable Public Enterprise CMS Website</label>
                <select name="enable_public_website" style="background:var(--bg-main);">
                    <option value="false" <?= $cWebsite == 'false' ? 'selected' : '' ?>>Disabled (Private Intranet Only)
                    </option>
                    <option value="true" <?= $cWebsite == 'true' ? 'selected' : '' ?>>Enabled (Public Launch Configured)
                    </option>
                </select>
            </div>

            <h3 style="margin-top: 32px; margin-bottom: 16px; color: var(--text-heading);">Global Module Configurations</h3>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Disabling a module will completely hide it
                from the sidebar and block access to its pages across the entire system, even for Administrators.</p>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label>CRM & Sales</label>
                    <select name="module_crm">
                        <option value="true" <?= ($currentSettings['module_crm'] ?? 'true') == 'true' ? 'selected' : '' ?>>
                            Enabled</option>
                        <option value="false" <?= ($currentSettings['module_crm'] ?? 'true') == 'false' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Project Management</label>
                    <select name="module_projects">
                        <option value="true" <?= ($currentSettings['module_projects'] ?? 'true') == 'true' ? 'selected' : '' ?>>Enabled</option>
                        <option value="false" <?= ($currentSettings['module_projects'] ?? 'true') == 'false' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Finance & Expenses</label>
                    <select name="module_finance">
                        <option value="true" <?= ($currentSettings['module_finance'] ?? 'true') == 'true' ? 'selected' : '' ?>>Enabled</option>
                        <option value="false" <?= ($currentSettings['module_finance'] ?? 'true') == 'false' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Human Resources</label>
                    <select name="module_hr">
                        <option value="true" <?= ($currentSettings['module_hr'] ?? 'true') == 'true' ? 'selected' : '' ?>>
                            Enabled</option>
                        <option value="false" <?= ($currentSettings['module_hr'] ?? 'true') == 'false' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Corporate Communication</label>
                    <select name="module_communication">
                        <option value="true" <?= ($currentSettings['module_communication'] ?? 'true') == 'true' ? 'selected' : '' ?>>Enabled</option>
                        <option value="false" <?= ($currentSettings['module_communication'] ?? 'true') == 'false' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>IT Asset Management</label>
                    <select name="module_assets">
                        <option value="true" <?= ($currentSettings['module_assets'] ?? 'true') == 'true' ? 'selected' : '' ?>>Enabled</option>
                        <option value="false" <?= ($currentSettings['module_assets'] ?? 'true') == 'false' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Service & Support Desk</label>
                    <select name="module_support">
                        <option value="true" <?= ($currentSettings['module_support'] ?? 'true') == 'true' ? 'selected' : '' ?>>Enabled</option>
                        <option value="false" <?= ($currentSettings['module_support'] ?? 'true') == 'false' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Workspace & Utilities</label>
                    <select name="module_workspace">
                        <option value="true" <?= ($currentSettings['module_workspace'] ?? 'true') == 'true' ? 'selected' : '' ?>>Enabled</option>
                        <option value="false" <?= ($currentSettings['module_workspace'] ?? 'true') == 'false' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Dynamic Form Builder</label>
                    <select name="module_forms">
                        <option value="true" <?= ($currentSettings['module_forms'] ?? 'true') == 'true' ? 'selected' : '' ?>>Enabled</option>
                        <option value="false" <?= ($currentSettings['module_forms'] ?? 'true') == 'false' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Website Builder</label>
                    <select name="module_website">
                        <option value="true" <?= ($currentSettings['module_website'] ?? 'true') == 'true' ? 'selected' : '' ?>>Enabled</option>
                        <option value="false" <?= ($currentSettings['module_website'] ?? 'true') == 'false' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </div>
            </div>

            <h3 style="margin-top: 32px; margin-bottom: 16px; color: var(--text-heading);">AI Configuration</h3>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Provide an OpenAI API Key to enable the
                Smart Assistant. If left blank, the system falls back to the built-in offline simulated AI.</p>

            <div class="form-group">
                <label>OpenAI API Key (sk-...)</label>
                <input type="password" name="openai_api_key"
                    value="<?= htmlspecialchars($currentSettings['openai_api_key'] ?? '') ?>" placeholder="sk-...">
            </div>

            <div class="form-group" style="background:var(--bg-hover); padding:15px; border-radius:8px; margin-top:20px;">
                <label style="color:var(--text-heading);">Enable True Offline Local AI (llama.cpp / Dokploy)</label>
                <select name="use_local_ai" style="background:var(--bg-main); margin-bottom: 10px;">
                    <option value="false" <?= ($currentSettings['use_local_ai'] ?? 'false') == 'false' ? 'selected' : '' ?>>Disabled</option>
                    <option value="true" <?= ($currentSettings['use_local_ai'] ?? 'false') == 'true' ? 'selected' : '' ?>>
                        Enabled</option>
                </select>

                <label style="color:var(--text-heading); margin-top: 10px; display: block;">Local AI Base URL</label>
                <input type="text" name="local_ai_url"
                    value="<?= htmlspecialchars($currentSettings['local_ai_url'] ?? 'http://127.0.0.1:8080') ?>"
                    placeholder="http://192.168.71.2:8081" style="background:var(--bg-main);">

                <p style="font-size:12px; color:var(--text-muted); margin-top:8px; margin-bottom:0;">Overrides OpenAI API Key. In
                    Dokploy, point this to your AI container's IP/Port (e.g., http://192.168.71.2:8081).</p>
            </div>

            <h3 style="margin-top: 32px; margin-bottom: 16px; color: var(--text-heading);">Geo-Fenced Clock-ins</h3>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Enforce location-based clock-ins via
                Timesheets.</p>

            <div class="form-group">
                <label>Enable Geo-Fencing</label>
                <select name="geo_fence_enabled">
                    <option value="false" <?= ($currentSettings['geo_fence_enabled'] ?? 'false') == 'false' ? 'selected' : '' ?>>Disabled</option>
                    <option value="true" <?= ($currentSettings['geo_fence_enabled'] ?? 'false') == 'true' ? 'selected' : '' ?>>Enabled</option>
                </select>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label>HQ Latitude</label>
                    <input type="text" name="geo_lat" value="<?= htmlspecialchars($currentSettings['geo_lat'] ?? '') ?>"
                        placeholder="e.g. 37.7749">
                </div>
                <div class="form-group">
                    <label>HQ Longitude</label>
                    <input type="text" name="geo_lng" value="<?= htmlspecialchars($currentSettings['geo_lng'] ?? '') ?>"
                        placeholder="e.g. -122.4194">
                </div>
                <div class="form-group">
                    <label>Radius (Meters)</label>
                    <input type="number" name="geo_radius"
                        value="<?= htmlspecialchars($currentSettings['geo_radius'] ?? '500') ?>">
                </div>
            </div>

            <h3 style="margin-top: 32px; margin-bottom: 16px; color: var(--text-heading);">Bottom Bar Configuration</h3>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Customize the global footer text and add
                quick links.</p>

            <div class="form-group">
                <label>Main Footer Text (e.g. Copyright)</label>
                <input type="text" name="footer_text"
                    value="<?= htmlspecialchars($currentSettings['footer_text'] ?? '© 2026 Cyno Management System. All rights reserved.') ?>">
            </div>

            <div class="form-group">
                <label>Custom Footer Links</label>
                <div id="footerLinksContainer">
                    <?php
                    $links = json_decode($currentSettings['footer_links'] ?? '[]', true);
                    if (!is_array($links))
                        $links = [];
                    foreach ($links as $i => $link) {
                        echo '<div class="footer-link-row" style="display:flex; gap:10px; margin-bottom:10px;">';
                        echo '<input type="text" name="footer_link_names[]" value="' . htmlspecialchars($link['name']) . '" placeholder="Link Name (e.g. Helpdesk)" style="flex:1;" required>';
                        echo '<input type="text" name="footer_link_urls[]" value="' . htmlspecialchars($link['url']) . '" placeholder="URL (e.g. /helpdesk.php or https://...)" style="flex:2;" required>';
                        echo '<button type="button" onclick="this.parentElement.remove()" style="background:#ef4444; color:white; border:none; border-radius:6px; padding:0 12px; cursor:pointer;">X</button>';
                        echo '</div>';
                    }
                    ?>
                </div>
                <button type="button" onclick="addFooterLink()"
                    style="margin-top:10px; background:#f3f4f6; color:#374151; border:1px solid #d1d5db; padding:6px 12px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;">+
                    Add Link</button>
            </div>

            <script>
                function addFooterLink() {
                    const container = document.getElementById('footerLinksContainer');
                    const row = document.createElement('div');
                    row.className = 'footer-link-row';
                    row.style.cssText = 'display:flex; gap:10px; margin-bottom:10px;';
                    row.innerHTML = `
                    <input type="text" name="footer_link_names[]" placeholder="Link Name" style="flex:1;" required>
                    <input type="text" name="footer_link_urls[]" placeholder="URL" style="flex:2;" required>
                    <button type="button" onclick="this.parentElement.remove()" style="background:#ef4444; color:white; border:none; border-radius:6px; padding:0 12px; cursor:pointer;">X</button>
                `;
                    container.appendChild(row);
                }
            </script>

            <div class="form-actions" style="margin-top: 32px;">
                <button type="submit" class="submit" style="width: 100%;">Save Global Settings</button>
            </div>

        </form>
    </div>

    <!-- Custom Statuses Panel -->
    <div style="background: var(--bg-card); padding: 32px; border-radius: 16px; border: 1px solid var(--border-card); max-width: 600px; margin-top: 24px;">
        <h3 style="margin-bottom: 20px; color: var(--text-heading);">Custom Statuses</h3>
        <p style="color: var(--text-muted); margin-bottom: 12px; font-size: 14px;">Tailor your project and task statuses to match your workflow.</p>
        
        <form method="POST" action="controllers/save_statuses.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <h4 style="margin-top:20px; margin-bottom:10px; color:var(--text-heading);">Projects Statuses</h4>
            <div id="projectStatusesContainer">
                <?php foreach($projectStatuses as $s): ?>
                    <div style="display:flex; gap:10px; margin-bottom:10px;">
                        <input type="hidden" name="status_id[]" value="<?= $s['id'] ?>">
                        <input type="hidden" name="module[]" value="projects">
                        <input type="text" name="status_name[]" value="<?= htmlspecialchars($s['status_name']) ?>" required style="flex:2; padding:8px; border-radius:6px; border:1px solid #d1d5db;" placeholder="Status Name">
                        <input type="color" name="color[]" value="<?= htmlspecialchars($s['color']) ?>" style="flex:1; height:40px; border:none; padding:0; cursor:pointer; background:transparent;" title="Status Color">
                        <input type="number" name="sort_order[]" value="<?= $s['sort_order'] ?>" style="flex:1; padding:8px; border-radius:6px; border:1px solid #d1d5db;" placeholder="Order">
                        <button type="button" onclick="this.parentElement.remove()" style="background:#ef4444; color:white; border:none; border-radius:6px; padding:0 12px; cursor:pointer;">X</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" onclick="addStatusRow('projectStatusesContainer', 'projects')" style="background:#f3f4f6; color:#374151; border:1px solid #d1d5db; padding:6px 12px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; margin-bottom: 20px;">+ Add Project Status</button>

            <h4 style="margin-bottom:10px; color:var(--text-heading);">Tasks Statuses</h4>
            <div id="taskStatusesContainer">
                <?php foreach($taskStatuses as $s): ?>
                    <div style="display:flex; gap:10px; margin-bottom:10px;">
                        <input type="hidden" name="status_id[]" value="<?= $s['id'] ?>">
                        <input type="hidden" name="module[]" value="tasks">
                        <input type="text" name="status_name[]" value="<?= htmlspecialchars($s['status_name']) ?>" required style="flex:2; padding:8px; border-radius:6px; border:1px solid #d1d5db;" placeholder="Status Name">
                        <input type="color" name="color[]" value="<?= htmlspecialchars($s['color']) ?>" style="flex:1; height:40px; border:none; padding:0; cursor:pointer; background:transparent;" title="Status Color">
                        <input type="number" name="sort_order[]" value="<?= $s['sort_order'] ?>" style="flex:1; padding:8px; border-radius:6px; border:1px solid #d1d5db;" placeholder="Order">
                        <button type="button" onclick="this.parentElement.remove()" style="background:#ef4444; color:white; border:none; border-radius:6px; padding:0 12px; cursor:pointer;">X</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" onclick="addStatusRow('taskStatusesContainer', 'tasks')" style="background:#f3f4f6; color:#374151; border:1px solid #d1d5db; padding:6px 12px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; margin-bottom: 20px;">+ Add Task Status</button>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="submit" style="width: 100%;">Save Statuses</button>
            </div>
        </form>

        <script>
            function addStatusRow(containerId, module) {
                const container = document.getElementById(containerId);
                const row = document.createElement('div');
                row.style.cssText = 'display:flex; gap:10px; margin-bottom:10px;';
                row.innerHTML = `
                    <input type="hidden" name="status_id[]" value="new">
                    <input type="hidden" name="module[]" value="${module}">
                    <input type="text" name="status_name[]" required style="flex:2; padding:8px; border-radius:6px; border:1px solid #d1d5db;" placeholder="Status Name">
                    <input type="color" name="color[]" value="#6b7280" style="flex:1; height:40px; border:none; padding:0; cursor:pointer; background:transparent;">
                    <input type="number" name="sort_order[]" value="0" style="flex:1; padding:8px; border-radius:6px; border:1px solid #d1d5db;" placeholder="Order">
                    <button type="button" onclick="this.parentElement.remove()" style="background:#ef4444; color:white; border:none; border-radius:6px; padding:0 12px; cursor:pointer;">X</button>
                `;
                container.appendChild(row);
            }
        </script>
    </div>

    <!-- Backup and Restore Panel -->
    <div
        style="background: var(--bg-card); padding: 32px; border-radius: 16px; border: 1px solid var(--border-card); max-width: 600px; margin-top: 24px;">
        <h3 style="margin-bottom: 20px; color: var(--text-heading);">System Backup & Restore</h3>

        <?php if (isset($_GET['success'])): ?>
            <div
                style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                <?= htmlspecialchars($_GET['success']) ?>
            </div>
        <?php endif; ?>

        <div style="margin-bottom: 30px;">
            <p style="color: var(--text-muted); margin-bottom: 12px; font-size: 14px;">Download a complete snapshot of the system
                database (SQLite).</p>
            <button onclick="window.location.href='controllers/backup_db.php'" class="add-button"
                style="background: #10b981; box-shadow: none;">📥 Download Database Backup</button>
        </div>

        <hr style="border:0; border-top: 1px solid var(--border-card); margin-bottom: 20px;">

        <div>
            <p style="color: var(--text-muted); margin-bottom: 12px; font-size: 14px;">Restore database from a previously
                downloaded .sqlite backup file. <strong>Warning: This replaces all current data!</strong></p>
            <form method="POST" action="controllers/restore_db.php" enctype="multipart/form-data"
                style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="file" name="backup_file" accept=".sqlite" required
                    style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
                <button type="submit" class="submit" style="background: #ef4444; box-shadow: none;"
                    onclick="event.preventDefault(); Swal.fire({title:'Are you strictly positive?', text:'This will erase all current data!', icon:'warning', showCancelButton:true, confirmButtonColor:'#dc2626', confirmButtonText:'Yes, wipe it!'}).then(r=>{if(r.isConfirmed) this.closest('form').submit()})">⚠️
                    Restore System</button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
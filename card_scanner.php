<?php
// card_scanner.php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'view_crm');

// Fetch analytics stats
$totalCards = $pdo->query("SELECT COUNT(*) FROM visiting_cards")->fetchColumn() ?: 0;
$totalLeads = $pdo->query("SELECT COUNT(*) FROM visiting_cards WHERE lead_id IS NOT NULL")->fetchColumn() ?: 0;
$totalVoice = $pdo->query("SELECT COUNT(*) FROM visiting_cards WHERE voice_note_path IS NOT NULL AND voice_note_path != ''")->fetchColumn() ?: 0;
$totalTemplates = $pdo->query("SELECT COUNT(*) FROM card_templates")->fetchColumn() ?: 0;

// Fetch saved cards
$savedCards = $pdo->query("SELECT * FROM visiting_cards ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch message templates
$templates = $pdo->query("SELECT * FROM card_templates ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

<style>
.vcard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.vcard-title {
    font-size: 26px;
    font-weight: 800;
    color: var(--text-heading);
    display: flex;
    align-items: center;
    gap: 12px;
}
.vcard-badge {
    font-size: 12px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 99px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
}

/* Glassmorphism Stat Cards */
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
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0,0,0,0.12);
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
.stat-num {
    font-size: 24px;
    font-weight: 800;
    color: var(--text-heading);
}
.stat-label {
    font-size: 13px;
    color: var(--text-muted);
    font-weight: 600;
}

/* Tabs Navigation */
.scanner-tabs {
    display: flex;
    gap: 12px;
    border-bottom: 2px solid var(--border-card);
    margin-bottom: 24px;
    overflow-x: auto;
}
.tab-btn {
    padding: 12px 20px;
    font-size: 14px;
    font-weight: 700;
    color: var(--text-muted);
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}
.tab-btn:hover {
    color: #8b5cf6;
}
.tab-btn.active {
    color: #6366f1;
    border-bottom-color: #6366f1;
}

/* Main Studio Layout */
.scanner-layout {
    display: grid;
    grid-template-columns: 1.1fr 1fr;
    gap: 24px;
}
@media (max-width: 1024px) {
    .scanner-layout { grid-template-columns: 1fr; }
}

/* Capture Section Cards */
.capture-box {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
}
.capture-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-heading);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* Dual Side + Selfie Photo Slots */
.photo-slots-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}
@media (max-width: 640px) {
    .photo-slots-grid { grid-template-columns: 1fr; }
}
.photo-slot {
    border: 2px dashed rgba(99, 102, 241, 0.3);
    background: rgba(99, 102, 241, 0.03);
    border-radius: 16px;
    padding: 16px;
    text-align: center;
    cursor: pointer;
    position: relative;
    transition: all 0.3s ease;
    min-height: 150px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.photo-slot:hover {
    border-color: #6366f1;
    background: rgba(99, 102, 241, 0.08);
}
.photo-slot img {
    width: 100%;
    height: 110px;
    object-fit: cover;
    border-radius: 10px;
}
.photo-slot-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-heading);
    margin-top: 8px;
}
.photo-slot-hint {
    font-size: 10px;
    color: var(--text-muted);
}
.slot-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    font-size: 10px;
    font-weight: 800;
    background: #6366f1;
    color: white;
    padding: 2px 8px;
    border-radius: 99px;
}

/* Live Camera Modal / Overlay */
.camera-viewfinder {
    width: 100%;
    height: 280px;
    background: #000;
    border-radius: 16px;
    overflow: hidden;
    position: relative;
    margin-bottom: 16px;
}
.camera-viewfinder video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.camera-controls {
    display: flex;
    justify-content: center;
    gap: 14px;
}

/* OCR Progress Banner */
.ocr-progress-box {
    display: none;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 14px;
    padding: 14px 20px;
    margin-bottom: 20px;
    align-items: center;
    gap: 16px;
}
.ocr-spinner {
    width: 24px;
    height: 24px;
    border: 3px solid rgba(99, 102, 241, 0.3);
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Form Field Inputs */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.form-group-full { grid-column: span 2; }
.card-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-heading);
    margin-bottom: 6px;
    display: block;
}
.card-input, .card-select, .card-textarea {
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
.card-input:focus, .card-select:focus, .card-textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

/* Voice Recorder Widget */
.voice-recorder-box {
    background: rgba(236, 72, 153, 0.04);
    border: 1px dashed rgba(236, 72, 153, 0.3);
    border-radius: 16px;
    padding: 16px;
    margin-top: 16px;
}
.recorder-controls {
    display: flex;
    align-items: center;
    gap: 12px;
}
.rec-btn {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: none;
    background: #ef4444;
    color: white;
    font-size: 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s, background 0.2s;
}
.rec-btn.recording {
    animation: pulse-red 1.2s infinite;
}
@keyframes pulse-red {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
    70% { transform: scale(1.1); box-shadow: 0 0 0 12px rgba(239, 68, 68, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

/* Cards Grid Display */
.saved-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}
.vcard-item {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 6px 18px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}
.vcard-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0,0,0,0.12);
    border-color: rgba(99, 102, 241, 0.4);
}
.vcard-preview-strip {
    height: 120px;
    background: rgba(15, 23, 42, 0.8);
    position: relative;
    display: flex;
}
.vcard-preview-img {
    flex: 1;
    height: 100%;
    object-fit: cover;
}
.vcard-avatar-selfie {
    position: absolute;
    bottom: -18px;
    right: 18px;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: 3px solid var(--bg-card);
    object-fit: cover;
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}
.vcard-body {
    padding: 24px 20px 20px 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.vcard-name {
    font-size: 17px;
    font-weight: 800;
    color: var(--text-heading);
    margin-bottom: 2px;
}
.vcard-company {
    font-size: 13px;
    font-weight: 600;
    color: #6366f1;
    margin-bottom: 12px;
}
.vcard-detail-line {
    font-size: 12.5px;
    color: var(--text-body);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.vcard-actions {
    margin-top: auto;
    padding-top: 14px;
    border-top: 1px solid var(--border-card);
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
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
.btn-primary { background: #6366f1; color: white; }
.btn-primary:hover { background: #4f46e5; }
.btn-success { background: #10b981; color: white; }
.btn-success:hover { background: #059669; }
.btn-whatsapp { background: #25d366; color: white; }
.btn-whatsapp:hover { background: #1da851; }
.btn-outline { background: transparent; border: 1px solid var(--border-card); color: var(--text-body); }
.btn-outline:hover { background: rgba(255,255,255,0.05); }

/* QR Code Modal Box */
#qrModalBox {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(8px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

/* Batch Importer Dropzone */
.batch-dropzone {
    border: 3px dashed rgba(99, 102, 241, 0.4);
    background: rgba(99, 102, 241, 0.04);
    border-radius: 20px;
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}
.batch-dropzone:hover {
    border-color: #6366f1;
    background: rgba(99, 102, 241, 0.1);
}
</style>

<div class="main-content" style="padding: 24px;">
    <!-- Header -->
    <div class="vcard-header">
        <div>
            <div class="vcard-title">
                🎴 Visiting Card Scanner & Lead Capture
                <span class="vcard-badge">AI OCR & Enterprise Batch</span>
            </div>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                Scan cards, capture selfie, run AI OCR, record voice notes, GPS field tag, bulk batch import, and auto-schedule tasks.
            </p>
        </div>
        <div style="display:flex; gap:10px;">
            <button onclick="switchTab('tab-scan')" class="btn-sm btn-primary" style="padding: 10px 18px; font-size:13.5px;">
                <i class="fas fa-camera"></i> New Card Scan
            </button>
            <button onclick="switchTab('tab-batch')" class="btn-sm btn-outline" style="padding: 10px 18px; font-size:13.5px; color:#8b5cf6; border-color:rgba(139,92,246,0.4);">
                <i class="fas fa-layer-group"></i> Bulk Batch Studio
            </button>
            <button onclick="switchTab('tab-templates')" class="btn-sm btn-outline" style="padding: 10px 18px; font-size:13.5px;">
                <i class="fas fa-paper-plane"></i> Message Templates
            </button>
        </div>
    </div>

    <!-- Analytics Dashboard Widgets -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
                <i class="fas fa-address-card"></i>
            </div>
            <div>
                <div class="stat-num"><?= $totalCards ?></div>
                <div class="stat-label">Total Cards Scanned</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                <i class="fas fa-user-check"></i>
            </div>
            <div>
                <div class="stat-num"><?= $totalLeads ?></div>
                <div class="stat-label">Converted CRM Leads</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #ec4899, #d946ef);">
                <i class="fas fa-microphone"></i>
            </div>
            <div>
                <div class="stat-num"><?= $totalVoice ?></div>
                <div class="stat-label">Voice Remarks Recorded</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <i class="fas fa-robot"></i>
            </div>
            <div>
                <div class="stat-num"><?= $totalTemplates ?></div>
                <div class="stat-label">Trigger Templates</div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="scanner-tabs">
        <button id="btn-tab-scan" onclick="switchTab('tab-scan')" class="tab-btn active">
            <i class="fas fa-qrcode"></i> Card Scanner Studio
        </button>
        <button id="btn-tab-batch" onclick="switchTab('tab-batch')" class="tab-btn">
            <i class="fas fa-layer-group"></i> Bulk Batch Importer
        </button>
        <button id="btn-tab-library" onclick="switchTab('tab-library')" class="tab-btn">
            <i class="fas fa-folder-open"></i> Saved Cards Library (<?= count($savedCards) ?>)
        </button>
        <button id="btn-tab-templates" onclick="switchTab('tab-templates')" class="tab-btn">
            <i class="fas fa-envelope-open-text"></i> Email & WhatsApp Templates
        </button>
    </div>

    <!-- SECTION 1: SCANNER & CAPTURE STUDIO -->
    <div id="tab-scan" class="tab-content">
        <div class="scanner-layout">
            <!-- Left Side: Image Capture & Camera -->
            <div class="capture-box">
                <div class="capture-title">
                    <span><i class="fas fa-camera" style="color: #6366f1;"></i> 1. Card & Selfie Capture</span>
                    <button type="button" onclick="openCameraModal()" class="btn-sm btn-outline">
                        <i class="fas fa-video"></i> Live Camera Feed
                    </button>
                </div>

                <!-- 3 Photo Slots: Front, Back, Selfie -->
                <div class="photo-slots-grid">
                    <!-- Slot 1: Front Image -->
                    <div class="photo-slot" onclick="triggerFileInput('front')">
                        <span class="slot-badge">Front Card</span>
                        <div id="slot-front-placeholder">
                            <i class="fas fa-id-card" style="font-size: 32px; color: #6366f1; margin-bottom: 6px;"></i>
                            <div class="photo-slot-label">Front Side</div>
                            <div class="photo-slot-hint">Click or Upload</div>
                        </div>
                        <img id="slot-front-img" style="display:none;" />
                        <input type="file" id="input-front-file" accept="image/*" style="display:none;" onchange="handleFileSelected(this, 'front')" />
                    </div>

                    <!-- Slot 2: Back Image -->
                    <div class="photo-slot" onclick="triggerFileInput('back')">
                        <span class="slot-badge" style="background:#8b5cf6;">Back Card</span>
                        <div id="slot-back-placeholder">
                            <i class="fas fa-id-card-alt" style="font-size: 32px; color: #8b5cf6; margin-bottom: 6px;"></i>
                            <div class="photo-slot-label">Back Side</div>
                            <div class="photo-slot-hint">Click or Upload</div>
                        </div>
                        <img id="slot-back-img" style="display:none;" />
                        <input type="file" id="input-back-file" accept="image/*" style="display:none;" onchange="handleFileSelected(this, 'back')" />
                    </div>

                    <!-- Slot 3: Selfie Image -->
                    <div class="photo-slot" onclick="triggerFileInput('selfie')">
                        <span class="slot-badge" style="background:#ec4899;">Selfie</span>
                        <div id="slot-selfie-placeholder">
                            <i class="fas fa-user-circle" style="font-size: 32px; color: #ec4899; margin-bottom: 6px;"></i>
                            <div class="photo-slot-label">User Selfie</div>
                            <div class="photo-slot-hint">Click or Upload</div>
                        </div>
                        <img id="slot-selfie-img" style="display:none;" />
                        <input type="file" id="input-selfie-file" accept="image/*" style="display:none;" onchange="handleFileSelected(this, 'selfie')" />
                    </div>
                </div>

                <!-- OCR Progress Banner -->
                <div class="ocr-progress-box" id="ocrProgressBox">
                    <div class="ocr-spinner"></div>
                    <div>
                        <div style="font-size: 13px; font-weight:700; color:var(--text-heading);" id="ocrStatusText">Reading card text...</div>
                        <div style="font-size: 11px; color:var(--text-muted);" id="ocrSubText">Applying Tesseract OCR engine</div>
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-bottom: 20px; flex-wrap:wrap;">
                    <button type="button" onclick="runOCRScan()" class="btn-sm btn-primary" style="flex:1; padding: 12px;">
                        <i class="fas fa-magic"></i> Run Auto OCR
                    </button>
                    <button type="button" onclick="runAICardParser()" class="btn-sm btn-outline" style="color:#ec4899; border-color:rgba(236,72,153,0.4);">
                        <i class="fas fa-brain"></i> AI Structuring
                    </button>
                    <button type="button" onclick="fetchGPSLocation()" class="btn-sm btn-outline" style="color:#10b981; border-color:rgba(16,185,129,0.4);">
                        <i class="fas fa-map-marker-alt"></i> GPS Location
                    </button>
                    <button type="button" onclick="resetCaptureForm()" class="btn-sm btn-outline">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>

                <!-- Voice & Text Remarks Section -->
                <div class="voice-recorder-box">
                    <div style="font-weight: 800; font-size: 14px; color: var(--text-heading); margin-bottom: 8px; display:flex; justify-content:space-between;">
                        <span><i class="fas fa-microphone" style="color: #ec4899;"></i> Voice & Text Remarks</span>
                        <span id="voiceStatusBadge" style="font-size: 11px; font-weight: 700; color: #ec4899;">Idle</span>
                    </div>

                    <div class="recorder-controls" style="margin-bottom: 12px;">
                        <button type="button" id="btnRecordVoice" onclick="toggleVoiceRecord()" class="rec-btn" title="Click to Record / Stop">
                            <i class="fas fa-microphone" id="recIcon"></i>
                        </button>
                        <div>
                            <div id="recTimer" style="font-size: 14px; font-weight: 800; color: var(--text-heading);">00:00</div>
                            <div style="font-size: 11px; color: var(--text-muted);">Tap mic to record voice note or speech-to-text dictation</div>
                        </div>
                    </div>

                    <audio id="voiceAudioPlayer" controls style="width: 100%; display: none; margin-bottom: 10px; border-radius: 8px;"></audio>

                    <label class="card-label">Meeting Notes / Text Remarks</label>
                    <textarea id="text_remarks" class="card-textarea" rows="3" placeholder="Add custom networking remarks, meeting outcomes, or context..."></textarea>
                </div>
            </div>

            <!-- Right Side: Extracted Details Form & Lead Converter -->
            <div class="capture-box">
                <div class="capture-title">
                    <span><i class="fas fa-edit" style="color: #10b981;"></i> 2. Extracted Card Details</span>
                    <span style="font-size: 11px; color: #10b981; font-weight: 700;">Verified Fields</span>
                </div>

                <form id="visitingCardForm" onsubmit="submitCardForm(event)">
                    <input type="hidden" id="front_b64" name="front_image_b64" />
                    <input type="hidden" id="back_b64" name="back_image_b64" />
                    <input type="hidden" id="selfie_b64" name="selfie_image_b64" />
                    <input type="hidden" id="ocr_raw_text" name="ocr_raw_text" />
                    <input type="hidden" id="latitude" name="latitude" />
                    <input type="hidden" id="longitude" name="longitude" />
                    <input type="hidden" id="location_name" name="location_name" />

                    <div class="form-grid">
                        <div>
                            <label class="card-label">Contact Full Name *</label>
                            <input type="text" id="contact_name" name="contact_name" class="card-input" placeholder="e.g. John Doe" required />
                        </div>

                        <div>
                            <label class="card-label">Job Title / Designation</label>
                            <input type="text" id="job_title" name="job_title" class="card-input" placeholder="e.g. Managing Director" />
                        </div>

                        <div>
                            <label class="card-label">Company Name</label>
                            <input type="text" id="company_name" name="company_name" class="card-input" placeholder="e.g. Acme Corp" />
                        </div>

                        <div>
                            <label class="card-label">Email Address</label>
                            <input type="email" id="email" name="email" class="card-input" placeholder="john@example.com" />
                        </div>

                        <div>
                            <label class="card-label">Phone / Mobile Number</label>
                            <input type="text" id="phone" name="phone" class="card-input" placeholder="+1 234 567 890" />
                        </div>

                        <div>
                            <label class="card-label">Website URL</label>
                            <input type="text" id="website" name="website" class="card-input" placeholder="www.example.com" />
                        </div>

                        <div class="form-group-full">
                            <label class="card-label">Office Address</label>
                            <input type="text" id="address" name="address" class="card-input" placeholder="Suite 100, Innovation Way, Tech Park" />
                        </div>

                        <div>
                            <label class="card-label">Category / Tag</label>
                            <select id="category" name="category" class="card-select">
                                <option value="Networking">Networking Event</option>
                                <option value="Client">Client Prospect</option>
                                <option value="Vendor">Vendor / Supplier</option>
                                <option value="Partner">Strategic Partner</option>
                                <option value="Investor">Investor / VC</option>
                            </select>
                        </div>

                        <div>
                            <label class="card-label">Follow-Up Target Date</label>
                            <input type="date" id="follow_up_date" name="follow_up_date" class="card-input" value="<?= date('Y-m-d', strtotime('+2 days')) ?>" />
                        </div>

                        <div class="form-group-full" style="display:flex; flex-direction:column; gap:8px; margin-top: 12px; background:rgba(99,102,241,0.04); padding:12px; border-radius:12px; border:1px solid rgba(99,102,241,0.1);">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <input type="checkbox" id="sync_crm_lead" name="sync_crm_lead" value="1" checked style="width:18px; height:18px; accent-color:#6366f1;" />
                                <label for="sync_crm_lead" style="font-size:13px; font-weight:700; color:var(--text-heading); cursor:pointer;">
                                    Auto-sync & Save to CRM Leads Pipeline
                                </label>
                            </div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <input type="checkbox" id="schedule_followup_task" name="schedule_followup_task" value="1" checked style="width:18px; height:18px; accent-color:#10b981;" />
                                <label for="schedule_followup_task" style="font-size:13px; font-weight:700; color:var(--text-heading); cursor:pointer;">
                                    Auto-Schedule Follow-up Task in Task Tracker (tasks.php)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 24px;">
                        <button type="submit" class="btn-sm btn-success" style="width: 100%; padding: 14px; font-size: 15px; font-weight: 800;">
                            <i class="fas fa-save"></i> Save Visiting Card & Execute Triggers
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SECTION 2: BULK BATCH IMPORTER -->
    <div id="tab-batch" class="tab-content" style="display:none;">
        <div class="capture-box">
            <div class="capture-title">
                <span><i class="fas fa-layer-group" style="color: #8b5cf6;"></i> Bulk Batch Card Importer (Up to 50 Cards)</span>
            </div>

            <div class="batch-dropzone" onclick="document.getElementById('batchFileInput').click()">
                <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #6366f1; margin-bottom: 12px;"></i>
                <div style="font-weight: 800; font-size: 18px; color: var(--text-heading);">Drag & Drop Multiple Card Images Here</div>
                <div style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">Supports JPG, PNG, WEBP files from Trade Shows or Expos</div>
                <input type="file" id="batchFileInput" multiple accept="image/*" style="display:none;" onchange="handleBatchFilesSelected(this)" />
            </div>

            <div id="batchProgressContainer" style="display:none; margin-top: 24px;">
                <div style="font-weight:700; font-size:14px; color:var(--text-heading); margin-bottom:10px;" id="batchProgressTitle">Processing Batch Cards...</div>
                <div style="background:rgba(255,255,255,0.1); border-radius:99px; height:12px; overflow:hidden;">
                    <div id="batchProgressBar" style="width:0%; height:100%; background:linear-gradient(135deg, #6366f1, #10b981); transition:width 0.3s;"></div>
                </div>
            </div>

            <div id="batchQueueContainer" style="margin-top: 24px; display:flex; flex-direction:column; gap:12px;"></div>
        </div>
    </div>

    <!-- SECTION 3: SAVED CARDS LIBRARY -->
    <div id="tab-library" class="tab-content" style="display:none;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; flex-wrap:wrap; gap:12px;">
            <input type="text" id="librarySearch" onkeyup="filterCardLibrary()" placeholder="Search by name, company, email, or designation..." class="card-input" style="width: 320px;" />
            
            <div style="display:flex; gap:10px;">
                <select id="libraryCategoryFilter" onchange="filterCardLibrary()" class="card-select" style="width: 180px;">
                    <option value="">All Categories</option>
                    <option value="Networking">Networking</option>
                    <option value="Client">Client Prospect</option>
                    <option value="Vendor">Vendor</option>
                    <option value="Partner">Partner</option>
                    <option value="Batch Import">Batch Import</option>
                </select>
            </div>
        </div>

        <div class="saved-cards-grid" id="cardsGridContainer">
            <?php foreach ($savedCards as $c): ?>
            <div class="vcard-item card-item-element" data-search="<?= strtolower(htmlspecialchars($c['contact_name'].' '.$c['company_name'].' '.$c['email'].' '.$c['phone'])) ?>" data-category="<?= htmlspecialchars($c['category']) ?>">
                <div class="vcard-preview-strip">
                    <?php if(!empty($c['front_image'])): ?>
                        <img src="<?= htmlspecialchars($c['front_image']) ?>" class="vcard-preview-img" onclick="previewMedia('<?= htmlspecialchars($c['front_image']) ?>')" />
                    <?php else: ?>
                        <div class="vcard-preview-img" style="display:flex; align-items:center; justify-content:center; color:#64748b;">
                            <i class="fas fa-id-card" style="font-size:36px;"></i>
                        </div>
                    <?php endif; ?>

                    <?php if(!empty($c['selfie_image'])): ?>
                        <img src="<?= htmlspecialchars($c['selfie_image']) ?>" class="vcard-avatar-selfie" title="Contact Selfie" onclick="previewMedia('<?= htmlspecialchars($c['selfie_image']) ?>')" />
                    <?php endif; ?>
                </div>

                <div class="vcard-body">
                    <div class="vcard-name"><?= htmlspecialchars($c['contact_name'] ?: 'Unnamed Contact') ?></div>
                    <div class="vcard-company"><?= htmlspecialchars(($c['job_title'] ? $c['job_title'] . ' • ' : '') . ($c['company_name'] ?: 'N/A')) ?></div>

                    <?php if(!empty($c['email'])): ?>
                    <div class="vcard-detail-line">
                        <i class="fas fa-envelope" style="color:#6366f1;"></i> <?= htmlspecialchars($c['email']) ?>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($c['phone'])): ?>
                    <div class="vcard-detail-line">
                        <i class="fas fa-phone" style="color:#10b981;"></i> <?= htmlspecialchars($c['phone']) ?>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($c['latitude'])): ?>
                    <div class="vcard-detail-line">
                        <i class="fas fa-map-marker-alt" style="color:#ef4444;"></i> GPS: <?= htmlspecialchars($c['latitude'].', '.$c['longitude']) ?>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($c['voice_note_path'])): ?>
                    <div style="margin-top: 10px; margin-bottom: 10px;">
                        <audio controls style="width:100%; height:32px;">
                            <source src="<?= htmlspecialchars($c['voice_note_path']) ?>" type="audio/webm">
                            <source src="<?= htmlspecialchars($c['voice_note_path']) ?>" type="audio/wav">
                        </audio>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($c['text_remarks'])): ?>
                    <div style="font-size:11.5px; color:var(--text-muted); background:rgba(255,255,255,0.03); padding:8px 10px; border-radius:8px; margin-top:8px;">
                        <i class="fas fa-sticky-note" style="color:#f59e0b;"></i> <?= htmlspecialchars($c['text_remarks']) ?>
                    </div>
                    <?php endif; ?>

                    <div class="vcard-actions">
                        <?php if(!empty($c['email'])): ?>
                        <button type="button" onclick="openSendModal(<?= $c['id'] ?>, 'email')" class="btn-sm btn-primary" title="Send Email">
                            <i class="fas fa-paper-plane"></i> Email
                        </button>
                        <?php endif; ?>

                        <?php if(!empty($c['phone'])): ?>
                        <button type="button" onclick="openSendModal(<?= $c['id'] ?>, 'whatsapp')" class="btn-sm btn-whatsapp" title="Send WhatsApp Message">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </button>
                        <?php endif; ?>

                        <?php if(!empty($c['latitude'])): ?>
                        <a href="https://www.google.com/maps?q=<?= $c['latitude'] ?>,<?= $c['longitude'] ?>" target="_blank" class="btn-sm btn-outline" title="Open Map Location">
                            <i class="fas fa-map-marked-alt" style="color:#ef4444;"></i> Map
                        </a>
                        <?php endif; ?>

                        <button type="button" onclick="generateVCard(<?= htmlspecialchars(json_encode($c)) ?>)" class="btn-sm btn-outline" title="Download .vcf Contact Card">
                            <i class="fas fa-download"></i> vCard
                        </button>

                        <button type="button" onclick="showContactQR(<?= htmlspecialchars(json_encode($c)) ?>)" class="btn-sm btn-outline" title="Show Phone QR Code">
                            <i class="fas fa-qrcode"></i> QR
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- SECTION 4: EMAIL & WHATSAPP TEMPLATES -->
    <div id="tab-templates" class="tab-content" style="display:none;">
        <div class="scanner-layout">
            <!-- Create/Edit Template Form -->
            <div class="capture-box">
                <div class="capture-title">
                    <span><i class="fas fa-edit" style="color: #6366f1;"></i> Design Message Template</span>
                </div>

                <form id="templateForm" onsubmit="submitTemplateForm(event)">
                    <input type="hidden" id="tpl_id" name="id" value="0" />

                    <div class="form-grid">
                        <div class="form-group-full">
                            <label class="card-label">Template Name *</label>
                            <input type="text" id="tpl_name" name="name" class="card-input" placeholder="e.g. Initial Networking Follow-up" required />
                        </div>

                        <div>
                            <label class="card-label">Channel *</label>
                            <select id="tpl_channel" name="channel" class="card-select" onchange="toggleSubjectField()">
                                <option value="email">Email</option>
                                <option value="whatsapp">WhatsApp</option>
                            </select>
                        </div>

                        <div id="subjectGroup">
                            <label class="card-label">Email Subject</label>
                            <input type="text" id="tpl_subject" name="subject" class="card-input" placeholder="Great connecting with you, {{name}}!" />
                        </div>

                        <div class="form-group-full">
                            <label class="card-label">Message Body / Content *</label>
                            <textarea id="tpl_body" name="body" class="card-textarea" rows="6" placeholder="Hi {{name}},&#10;&#10;It was a pleasure meeting you at {{company}}. Let's connect soon!" required></textarea>
                            <div style="font-size:11px; color:var(--text-muted); margin-top:6px;">
                                Available Dynamic Tags: <code>{{name}}</code>, <code>{{company}}</code>, <code>{{job_title}}</code>, <code>{{email}}</code>, <code>{{phone}}</code>, <code>{{remarks}}</code>, <code>{{user_name}}</code>
                            </div>
                        </div>

                        <div class="form-group-full" style="display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" id="tpl_is_auto_trigger" name="is_auto_trigger" value="1" style="width:18px; height:18px; accent-color:#6366f1;" />
                            <label for="tpl_is_auto_trigger" style="font-size:13px; font-weight:700; color:var(--text-heading); cursor:pointer;">
                                Set as Default Auto-Trigger for New Scanned Cards
                            </label>
                        </div>
                    </div>

                    <div style="margin-top: 20px; display:flex; gap:10px;">
                        <button type="submit" class="btn-sm btn-primary" style="flex:1; padding: 12px;">
                            <i class="fas fa-save"></i> Save Template
                        </button>
                        <button type="button" onclick="resetTemplateForm()" class="btn-sm btn-outline">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- List Existing Templates -->
            <div class="capture-box">
                <div class="capture-title">
                    <span><i class="fas fa-list" style="color: #8b5cf6;"></i> Saved Templates (<?= count($templates) ?>)</span>
                </div>

                <div style="display:flex; flex-direction:column; gap:14px;">
                    <?php foreach($templates as $t): ?>
                    <div style="background:var(--bg-main); border:1px solid var(--border-card); border-radius:14px; padding:16px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <div style="font-weight:800; font-size:15px; color:var(--text-heading);">
                                <?= htmlspecialchars($t['name']) ?>
                                <?php if($t['is_auto_trigger'] == 1): ?>
                                    <span style="font-size:10px; background:#10b981; color:white; padding:2px 6px; border-radius:99px; font-weight:800;">AUTO TRIGGER</span>
                                <?php endif; ?>
                            </div>
                            <span style="font-size:11px; font-weight:800; text-transform:uppercase; color: <?= $t['channel'] === 'email' ? '#6366f1' : '#25d366' ?>;">
                                <?= htmlspecialchars($t['channel']) ?>
                            </span>
                        </div>

                        <?php if($t['channel'] === 'email' && !empty($t['subject'])): ?>
                        <div style="font-size:12.5px; font-weight:600; color:var(--text-muted); margin-bottom:4px;">
                            Subj: <?= htmlspecialchars($t['subject']) ?>
                        </div>
                        <?php endif; ?>

                        <div style="font-size:12px; color:var(--text-body); white-space:pre-wrap; background:rgba(0,0,0,0.1); padding:8px; border-radius:8px; max-height:80px; overflow-y:auto;">
                            <?= htmlspecialchars($t['body']) ?>
                        </div>

                        <div style="display:flex; gap:8px; margin-top:10px; justify-content:flex-end;">
                            <button type="button" onclick="editTemplate(<?= htmlspecialchars(json_encode($t)) ?>)" class="btn-sm btn-outline">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button type="button" onclick="deleteTemplate(<?= $t['id'] ?>)" class="btn-sm btn-outline" style="color:#ef4444; border-color:rgba(239,68,68,0.3);">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- LIVE CAMERA MODAL -->
<div id="cameraModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(8px); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--bg-card); border:1px solid var(--border-card); border-radius:20px; padding:24px; width:90%; max-width:540px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div style="font-weight:800; font-size:16px; color:var(--text-heading);" id="cameraModalTitle">Live Camera Capture</div>
            <button type="button" onclick="closeCameraModal()" style="background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer;">&times;</button>
        </div>

        <div class="camera-viewfinder">
            <video id="cameraStream" autoplay playsinline></video>
        </div>

        <div class="camera-controls">
            <button type="button" onclick="switchCameraFacing()" class="btn-sm btn-outline">
                <i class="fas fa-sync"></i> Switch Camera
            </button>
            <button type="button" onclick="capturePhotoFromCamera()" class="btn-sm btn-primary" style="padding:10px 24px; font-size:14px;">
                <i class="fas fa-camera"></i> Capture Photo
            </button>
        </div>
    </div>
</div>

<!-- SEND MESSAGE MODAL -->
<div id="sendMessageModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(8px); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--bg-card); border:1px solid var(--border-card); border-radius:20px; padding:24px; width:90%; max-width:520px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div style="font-weight:800; font-size:16px; color:var(--text-heading);" id="sendModalTitle">Send Message</div>
            <button type="button" onclick="closeSendModal()" style="background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer;">&times;</button>
        </div>

        <form id="sendMessageForm" onsubmit="submitSendMessage(event)">
            <input type="hidden" id="sm_card_id" name="card_id" />
            <input type="hidden" id="sm_channel" name="channel" />

            <div class="form-grid">
                <div class="form-group-full">
                    <label class="card-label">Select Message Template</label>
                    <select id="sm_template_id" name="template_id" class="card-select" onchange="loadTemplatePreviewIntoModal()">
                        <option value="0">-- Custom / Blank --</option>
                        <?php foreach($templates as $t): ?>
                        <option value="<?= $t['id'] ?>" data-channel="<?= $t['channel'] ?>" data-subject="<?= htmlspecialchars($t['subject']) ?>" data-body="<?= htmlspecialchars($t['body']) ?>">
                            [<?= strtoupper($t['channel']) ?>] <?= htmlspecialchars($t['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="sm_subject_wrap" class="form-group-full">
                    <label class="card-label">Subject</label>
                    <input type="text" id="sm_subject" name="custom_subject" class="card-input" />
                </div>

                <div class="form-group-full">
                    <label class="card-label">Message Content</label>
                    <textarea id="sm_body" name="custom_body" class="card-textarea" rows="5" required></textarea>
                </div>
            </div>

            <div style="margin-top:20px; display:flex; gap:10px;">
                <button type="submit" class="btn-sm btn-success" style="flex:1; padding:12px;" id="sm_submit_btn">
                    <i class="fas fa-paper-plane"></i> Dispatch Message
                </button>
            </div>
        </form>
    </div>
</div>

<!-- QR CODE MODAL -->
<div id="qrModalBox">
    <div style="background:var(--bg-card); border:1px solid var(--border-card); border-radius:20px; padding:28px; text-align:center; max-width:360px; width:90%;">
        <div style="font-weight:800; font-size:18px; color:var(--text-heading); margin-bottom:4px;" id="qrNameTitle">Contact QR</div>
        <div style="font-size:12px; color:var(--text-muted); margin-bottom:18px;">Scan with phone camera to add contact</div>
        
        <div id="qrcodeDisplay" style="display:flex; justify-content:center; margin-bottom:20px;"></div>

        <button type="button" onclick="closeQRModal()" class="btn-sm btn-outline" style="width:100%;">Close</button>
    </div>
</div>

<script>
let currentActiveSlot = 'front';
let mediaStream = null;
let currentFacing = 'environment';
let mediaRecorder = null;
let audioChunks = [];
let voiceBlob = null;
let timerInterval = null;

// Tab Switching
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

    document.getElementById(tabId).style.display = 'block';
    document.getElementById('btn-' + tabId).classList.add('active');
}

// Trigger hidden file inputs
function triggerFileInput(slot) {
    currentActiveSlot = slot;
    document.getElementById('input-' + slot + '-file').click();
}

function handleFileSelected(input, slot) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            setSlotImage(slot, e.target.result);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function setSlotImage(slot, dataUrl) {
    document.getElementById('slot-' + slot + '-placeholder').style.display = 'none';
    const img = document.getElementById('slot-' + slot + '-img');
    img.src = dataUrl;
    img.style.display = 'block';

    document.getElementById(slot + '_b64').value = dataUrl;

    if (slot === 'front' || slot === 'back') {
        runOCRScan();
    }
}

// Live Camera
async function openCameraModal(slot = 'front') {
    currentActiveSlot = slot;
    document.getElementById('cameraModalTitle').innerText = 'Live Camera - Capture ' + slot.toUpperCase();
    document.getElementById('cameraModal').style.display = 'flex';
    await startCameraStream();
}

async function startCameraStream() {
    if (mediaStream) {
        mediaStream.getTracks().forEach(track => track.stop());
    }
    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: currentFacing }
        });
        document.getElementById('cameraStream').srcObject = mediaStream;
    } catch (err) {
        Swal.fire('Camera Error', 'Could not access camera device: ' + err.message, 'error');
        closeCameraModal();
    }
}

function switchCameraFacing() {
    currentFacing = (currentFacing === 'user') ? 'environment' : 'user';
    startCameraStream();
}

function capturePhotoFromCamera() {
    const video = document.getElementById('cameraStream');
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const dataUrl = canvas.toDataURL('image/png');

    setSlotImage(currentActiveSlot, dataUrl);
    closeCameraModal();
}

function closeCameraModal() {
    if (mediaStream) {
        mediaStream.getTracks().forEach(track => track.stop());
        mediaStream = null;
    }
    document.getElementById('cameraModal').style.display = 'none';
}

// GPS Field Location
function fetchGPSLocation() {
    if ("geolocation" in navigator) {
        Swal.fire({ title: 'Acquiring GPS Coordinates...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        navigator.geolocation.getCurrentPosition(pos => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            document.getElementById('location_name').value = `GPS (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
            Swal.fire('Location Tagged!', `GPS coordinates captured: ${lat.toFixed(4)}, ${lng.toFixed(4)}`, 'success');
        }, err => {
            Swal.fire('GPS Warning', 'Could not acquire GPS location: ' + err.message, 'warning');
        });
    } else {
        Swal.fire('Not Supported', 'Geolocation is not supported by your browser.', 'info');
    }
}

// Client-Side Tesseract.js OCR Execution
async function runOCRScan() {
    const frontImg = document.getElementById('slot-front-img').src;
    const backImg  = document.getElementById('slot-back-img').src;

    let targetImage = null;
    if (frontImg && frontImg.startsWith('data:image')) targetImage = frontImg;
    else if (backImg && backImg.startsWith('data:image')) targetImage = backImg;

    if (!targetImage) {
        Swal.fire('No Image Selected', 'Please capture or upload a front or back card image first.', 'warning');
        return;
    }

    const progressBox = document.getElementById('ocrProgressBox');
    progressBox.style.display = 'flex';
    document.getElementById('ocrStatusText').innerText = 'Initializing AI OCR Engine...';

    try {
        const worker = await Tesseract.createWorker('eng');
        document.getElementById('ocrStatusText').innerText = 'Scanning Visiting Card text patterns...';
        
        const ret = await worker.recognize(targetImage);
        await worker.terminate();

        const rawText = ret.data.text;
        document.getElementById('ocr_raw_text').value = rawText;

        parseOCRData(rawText);

        progressBox.style.display = 'none';
        Swal.fire({
            icon: 'success',
            title: 'OCR Scan Complete!',
            text: 'Extracted contact details populated into form fields.',
            timer: 1500,
            showConfirmButton: false
        });

    } catch (err) {
        progressBox.style.display = 'none';
        console.error('OCR Error:', err);
    }
}

// AI Structuring API Call
async function runAICardParser() {
    const raw = document.getElementById('ocr_raw_text').value;
    if (!raw) {
        Swal.fire('No OCR Text', 'Run OCR Scan first before applying AI Structuring.', 'warning');
        return;
    }

    Swal.fire({ title: 'AI Structuring Text...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const formData = new FormData();
        formData.append('raw_text', raw);
        const resp = await fetch('controllers/ai_card_parser.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.success && res.data) {
            if (res.data.contact_name) document.getElementById('contact_name').value = res.data.contact_name;
            if (res.data.job_title) document.getElementById('job_title').value = res.data.job_title;
            if (res.data.company_name) document.getElementById('company_name').value = res.data.company_name;
            if (res.data.email) document.getElementById('email').value = res.data.email;
            if (res.data.phone) document.getElementById('phone').value = res.data.phone;
            if (res.data.website) document.getElementById('website').value = res.data.website;
            if (res.data.address) document.getElementById('address').value = res.data.address;

            Swal.fire('AI Structuring Done!', 'Cleaned & formatted contact details.', 'success');
        } else {
            Swal.fire('AI Parser', res.error || 'Failed to structure card text.', 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'AI Parser connection failed.', 'error');
    }
}

// Regex Auto-Parser for Card Details
function parseOCRData(text) {
    const lines = text.split('\n').map(l => l.trim()).filter(l => l.length > 0);

    const emailMatch = text.match(/[\w\.-]+@[\w\.-]+\.\w+/);
    if (emailMatch) document.getElementById('email').value = emailMatch[0];

    const phoneMatch = text.match(/(\+?\d{1,4}[\s\-]?)?(\(?\d{2,5}\)?[\s\-]?)?[\d\s\-]{6,15}/);
    if (phoneMatch && phoneMatch[0].trim().length >= 8) {
        document.getElementById('phone').value = phoneMatch[0].trim();
    }

    const webMatch = text.match(/(https?:\/\/)?(www\.)?[\w\.-]+\.(com|org|net|co|io|in|tech|biz)/i);
    if (webMatch) document.getElementById('website').value = webMatch[0];

    if (lines.length > 0) {
        const candidateName = lines.find(l => !l.includes('@') && !/\d{5,}/.test(l) && l.length < 40);
        if (candidateName) document.getElementById('contact_name').value = candidateName;

        const candidateTitle = lines.find(l => /manager|director|ceo|cto|cfo|head|executive|engineer|founder|consultant|developer|president/i.test(l));
        if (candidateTitle) document.getElementById('job_title').value = candidateTitle;

        const candidateCompany = lines.find(l => /inc|ltd|corp|llc|solutions|technologies|group|enterprises|co\./i.test(l));
        if (candidateCompany) document.getElementById('company_name').value = candidateCompany;
    }
}

// Voice Recorder (MediaRecorder API)
async function toggleVoiceRecord() {
    const btn = document.getElementById('btnRecordVoice');
    const badge = document.getElementById('voiceStatusBadge');

    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
        btn.classList.remove('recording');
        badge.innerText = 'Recorded';
        clearInterval(timerInterval);
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        audioChunks = [];

        mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
        mediaRecorder.onstop = () => {
            voiceBlob = new Blob(audioChunks, { type: 'audio/webm' });
            const audioUrl = URL.createObjectURL(voiceBlob);
            const player = document.getElementById('voiceAudioPlayer');
            player.src = audioUrl;
            player.style.display = 'block';
        };

        mediaRecorder.start();
        btn.classList.add('recording');
        badge.innerText = 'Recording...';

        let sec = 0;
        timerInterval = setInterval(() => {
            sec++;
            const m = String(Math.floor(sec/60)).padStart(2,'0');
            const s = String(sec%60).padStart(2,'0');
            document.getElementById('recTimer').innerText = `${m}:${s}`;
        }, 1000);

    } catch (err) {
        Swal.fire('Microphone Error', 'Could not access microphone: ' + err.message, 'error');
    }
}

// Submit Card Save
async function submitCardForm(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('visitingCardForm'));

    if (voiceBlob) {
        formData.append('voice_note_file', voiceBlob, 'voice_recording.webm');
    }

    const frontFile = document.getElementById('input-front-file').files[0];
    if (frontFile) formData.append('front_image_file', frontFile);

    const backFile = document.getElementById('input-back-file').files[0];
    if (backFile) formData.append('back_image_file', backFile);

    const selfieFile = document.getElementById('input-selfie-file').files[0];
    if (selfieFile) formData.append('selfie_image_file', selfieFile);

    Swal.fire({ title: 'Saving Visiting Card...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const resp = await fetch('controllers/save_visiting_card.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.success) {
            let msg = res.message;
            if (res.auto_email_sent) msg += ' Auto welcome email dispatched!';
            Swal.fire('Saved!', msg, 'success').then(() => window.location.reload());
        } else {
            Swal.fire('Save Failed', res.error, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Server connection error.', 'error');
    }
}

// Batch Files Processing
let batchFilesList = [];
function handleBatchFilesSelected(input) {
    if (input.files && input.files.length > 0) {
        batchFilesList = Array.from(input.files);
        processBatchQueue();
    }
}

async function processBatchQueue() {
    const queueBox = document.getElementById('batchQueueContainer');
    const progressBox = document.getElementById('batchProgressContainer');
    const progressBar = document.getElementById('batchProgressBar');
    
    queueBox.innerHTML = '';
    progressBox.style.display = 'block';

    const preparedCards = [];
    const total = batchFilesList.length;

    for (let i = 0; i < total; i++) {
        const file = batchFilesList[i];
        document.getElementById('batchProgressTitle').innerText = `OCR Scanning Card ${i+1} of ${total}: ${file.name}`;
        progressBar.style.width = Math.round(((i+1)/total)*100) + '%';

        const b64 = await fileToDataUrl(file);
        
        // OCR read
        let name = file.name.replace(/\.[^/.]+$/, "");
        let text = "";
        try {
            const worker = await Tesseract.createWorker('eng');
            const ret = await worker.recognize(b64);
            await worker.terminate();
            text = ret.data.text;
        } catch (e) {}

        const parsed = parseOCRDataString(text);

        preparedCards.push({
            contact_name: parsed.name || name,
            job_title: parsed.title || '',
            company_name: parsed.company || '',
            email: parsed.email || '',
            phone: parsed.phone || '',
            website: parsed.website || '',
            address: parsed.address || '',
            category: 'Batch Import',
            text_remarks: `Batch imported card (${file.name})`,
            front_image_b64: b64,
            sync_crm: true
        });

        queueBox.innerHTML += `<div style="background:var(--bg-main); padding:10px 14px; border-radius:10px; border:1px solid var(--border-card); font-size:13px; color:var(--text-body);">
            ✅ Parsed Card ${i+1}: <strong>${parsed.name || name}</strong> (${parsed.email || 'No email'})
        </div>`;
    }

    document.getElementById('batchProgressTitle').innerText = 'Saving Batch Cards into Database...';

    // Submit batch request
    try {
        const resp = await fetch('controllers/batch_save_cards.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cards: preparedCards })
        });
        const res = await resp.json();
        if (res.success) {
            Swal.fire('Batch Complete!', res.message, 'success').then(() => window.location.reload());
        } else {
            Swal.fire('Batch Error', res.error, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Batch API request failed.', 'error');
    }
}

function fileToDataUrl(file) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = e => resolve(e.target.result);
        reader.readAsDataURL(file);
    });
}

function parseOCRDataString(text) {
    const lines = text.split('\n').map(l => l.trim()).filter(l => l.length > 0);
    const emailMatch = text.match(/[\w\.-]+@[\w\.-]+\.\w+/);
    const phoneMatch = text.match(/(\+?\d{1,4}[\s\-]?)?(\(?\d{2,5}\)?[\s\-]?)?[\d\s\-]{6,15}/);
    const webMatch   = text.match(/(https?:\/\/)?(www\.)?[\w\.-]+\.(com|org|net|co|io|in|tech|biz)/i);

    let name = lines.find(l => !l.includes('@') && !/\d{5,}/.test(l) && l.length < 40) || '';
    let title = lines.find(l => /manager|director|ceo|cto|cfo|head|executive|engineer|founder|consultant/i.test(l)) || '';
    let company = lines.find(l => /inc|ltd|corp|llc|solutions|technologies|group|enterprises/i.test(l)) || '';

    return {
        name: name,
        title: title,
        company: company,
        email: emailMatch ? emailMatch[0] : '',
        phone: phoneMatch ? phoneMatch[0].trim() : '',
        website: webMatch ? webMatch[0] : ''
    };
}

// Reset Capture Form
function resetCaptureForm() {
    document.getElementById('visitingCardForm').reset();
    ['front','back','selfie'].forEach(slot => {
        document.getElementById('slot-' + slot + '-placeholder').style.display = 'flex';
        document.getElementById('slot-' + slot + '-img').style.display = 'none';
        document.getElementById(slot + '_b64').value = '';
    });
    document.getElementById('voiceAudioPlayer').style.display = 'none';
    voiceBlob = null;
}

// Card Library Filter
function filterCardLibrary() {
    const q = document.getElementById('librarySearch').value.toLowerCase();
    const cat = document.getElementById('libraryCategoryFilter').value;

    document.querySelectorAll('.card-item-element').forEach(item => {
        const matchQ = !q || item.dataset.search.includes(q);
        const matchCat = !cat || item.dataset.category === cat;
        item.style.display = (matchQ && matchCat) ? 'flex' : 'none';
    });
}

// Generate .vcf Contact Download
function generateVCard(card) {
    const vcardContent = `BEGIN:VCARD
VERSION:3.0
FN:${card.contact_name || ''}
TITLE:${card.job_title || ''}
ORG:${card.company_name || ''}
EMAIL;TYPE=INTERNET:${card.email || ''}
TEL;TYPE=CELL:${card.phone || ''}
URL:${card.website || ''}
ADR;TYPE=WORK:;;${card.address || ''};;;;
END:VCARD`;

    const blob = new Blob([vcardContent], { type: 'text/vcard' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = (card.contact_name || 'Contact') + '.vcf';
    a.click();
}

// Contact QR Code Modal
function showContactQR(card) {
    document.getElementById('qrNameTitle').innerText = card.contact_name || 'Contact';
    document.getElementById('qrcodeDisplay').innerHTML = '';

    const qrData = `MECARD:N:${card.contact_name || ''};TEL:${card.phone || ''};EMAIL:${card.email || ''};ORG:${card.company_name || ''};;`;

    new QRCode(document.getElementById('qrcodeDisplay'), {
        text: qrData,
        width: 180,
        height: 180
    });

    document.getElementById('qrModalBox').style.display = 'flex';
}
function closeQRModal() {
    document.getElementById('qrModalBox').style.display = 'none';
}

function previewMedia(src) {
    Swal.fire({
        imageUrl: src,
        imageAlt: 'Card Image Preview',
        showCloseButton: true,
        showConfirmButton: false
    });
}

// Template Manager logic
function toggleSubjectField() {
    const ch = document.getElementById('tpl_channel').value;
    document.getElementById('subjectGroup').style.display = (ch === 'email') ? 'block' : 'none';
}

async function submitTemplateForm(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('templateForm'));
    formData.append('action', 'save');

    try {
        const resp = await fetch('controllers/card_templates.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) {
            Swal.fire('Saved!', res.message, 'success').then(() => window.location.reload());
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Failed to save template.', 'error');
    }
}

function editTemplate(t) {
    document.getElementById('tpl_id').value = t.id;
    document.getElementById('tpl_name').value = t.name;
    document.getElementById('tpl_channel').value = t.channel;
    document.getElementById('tpl_subject').value = t.subject || '';
    document.getElementById('tpl_body').value = t.body;
    document.getElementById('tpl_is_auto_trigger').checked = (t.is_auto_trigger == 1);
    toggleSubjectField();
}

function resetTemplateForm() {
    document.getElementById('templateForm').reset();
    document.getElementById('tpl_id').value = 0;
    toggleSubjectField();
}

async function deleteTemplate(id) {
    if (!confirm('Delete this template?')) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    const resp = await fetch('controllers/card_templates.php', { method: 'POST', body: formData });
    const res = await resp.json();
    if (res.success) window.location.reload();
}

// Send Message Modal logic
function openSendModal(cardId, channel) {
    document.getElementById('sm_card_id').value = cardId;
    document.getElementById('sm_channel').value = channel;
    document.getElementById('sendModalTitle').innerText = 'Send ' + (channel === 'email' ? 'Email' : 'WhatsApp') + ' Message';
    document.getElementById('sm_subject_wrap').style.display = (channel === 'email') ? 'block' : 'none';

    const select = document.getElementById('sm_template_id');
    for (let i = 0; i < select.options.length; i++) {
        const opt = select.options[i];
        if (opt.dataset.channel === channel) {
            select.selectedIndex = i;
            break;
        }
    }
    loadTemplatePreviewIntoModal();
    document.getElementById('sendMessageModal').style.display = 'flex';
}

function closeSendModal() {
    document.getElementById('sendMessageModal').style.display = 'none';
}

function loadTemplatePreviewIntoModal() {
    const select = document.getElementById('sm_template_id');
    const opt = select.options[select.selectedIndex];
    if (opt && opt.value !== '0') {
        document.getElementById('sm_subject').value = opt.dataset.subject || '';
        document.getElementById('sm_body').value = opt.dataset.body || '';
    } else {
        document.getElementById('sm_subject').value = '';
        document.getElementById('sm_body').value = '';
    }
}

async function submitSendMessage(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('sendMessageForm'));

    Swal.fire({ title: 'Processing Action...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const resp = await fetch('controllers/trigger_card_action.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.success) {
            closeSendModal();
            if (res.wa_url) {
                Swal.fire({
                    icon: 'success',
                    title: 'WhatsApp Ready!',
                    text: 'Click below to open WhatsApp with pre-filled message.',
                    showCancelButton: true,
                    confirmButtonText: 'Open WhatsApp Chat',
                    confirmButtonColor: '#25d366'
                }).then(result => {
                    if (result.isConfirmed) {
                        window.open(res.wa_url, '_blank');
                    }
                });
            } else {
                Swal.fire('Sent!', res.message, 'success');
            }
        } else {
            Swal.fire('Dispatch Failed', res.error, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Server connection failed.', 'error');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>

# CynoCMS Enterprise System — Administrator & User Documentation Guide

Welcome to the official **CynoCMS Enterprise System Administrator & User Guide**. This document provides detailed operational instructions, architectural specifications, workflow diagrams, and security protocols across all core and enterprise modules.

---

## 📋 Table of Contents
1. [System Overview & Architecture](#1-system-overview--architecture)
2. [🎴 Visiting Card Scanner & Lead Capture Studio](#2--visiting-card-scanner--lead-capture-studio)
3. [🎯 Sales CRM & Marketing Campaign Engine](#3--sales-crm--marketing-campaign-engine)
4. [📦 Multipurpose Advanced Inventory & Pharmacy POS](#4--multipurpose-advanced-inventory--pharmacy-pos)
5. [🛒 Procurement & Auto-Restock PO Generator](#5--procurement--auto-restock-po-generator)
6. [📊 Executive BI Dashboard & PWA Offline Engine](#6--executive-bi-dashboard--pwa-offline-engine)
7. [🔔 Background Automation Crons (`cron_tasks.php`)](#7--background-automation-crons-cron_tasksphp)
8. [🛡️ Security Hardening & RBAC Permission Matrix](#8--security-hardening--rbac-permission-matrix)

---

## 1. System Overview & Architecture

CynoCMS is a high-performance, modular enterprise management ecosystem engineered with PHP 8+, PDO MySQL / SQLite dual database abstraction, Vanilla JS, Dark Glassmorphism CSS styling, and client-side AI integration.

### Core Stack Specifications:
- **Backend**: PHP 8.x with PDO Object-Relational Database Layer (`includes/db.php`).
- **UI Engine**: Dark Glassmorphism CSS Design System with responsive grid layouts.
- **Client AI & OCR**: Tesseract.js client-side OCR engine + AI Text Parser.
- **Barcodes & QR**: Code-128 barcode rendering (`JsBarcode`) + vCard QR code generator.
- **PWA Engine**: Service Worker offline caching (`sw.js`) + Web App Manifest (`manifest.json`).

---

## 2. 🎴 Visiting Card Scanner & Lead Capture Studio

**Access URL**: [`card_scanner.php`](file:///c:/Users/Administrator/Desktop/cms/card_scanner.php)

The Visiting Card Scanner Studio allows sales representatives, field agents, and executives to capture, digitize, and convert physical business cards into actionable CRM leads in real time.

### Key Capabilities:
1. **WebRTC Live Viewfinder**:
   - Live camera stream with 1-click **Flip Camera** switcher (Front vs Back camera).
   - Capture **Card Front**, **Card Back**, and **Contact Selfie** photo.
2. **Client-Side Tesseract.js OCR & AI Parser**:
   - Extracts raw text directly inside the browser using Tesseract OCR.
   - Passes text to [`controllers/ai_card_parser.php`](file:///c:/Users/Administrator/Desktop/cms/controllers/ai_card_parser.php) to automatically structure Contact Name, Designation, Company, Email, Phone, Website, and Physical Address.
3. **Bulk Batch Card Importer**:
   - Drag-and-drop or select up to **50 business cards** in parallel.
   - Processes batch OCR and saves cards in bulk ([`controllers/batch_save_cards.php`](file:///c:/Users/Administrator/Desktop/cms/controllers/batch_save_cards.php)).
4. **Voice Remarks & GPS Geo-Tagging**:
   - Built-in HTML5 MediaRecorder to record audio meeting remarks.
   - Captures GPS latitude and longitude with 1-click **Google Maps** location links.
5. **vCard Export & Contact QR Codes**:
   - Download `.vcf` vCard files directly to phone contacts.
   - Generates scannable contact QR codes.
6. **Message Templates & Auto Outreach**:
   - Create Email & WhatsApp templates with dynamic variables (`{{name}}`, `{{company}}`).
   - Trigger 1-click email or direct WhatsApp chat dispatch.

---

## 3. 🎯 Sales CRM & Marketing Campaign Engine

**Access URLs**: [`crm.php`](file:///c:/Users/Administrator/Desktop/cms/crm.php) | [`crm_campaigns.php`](file:///c:/Users/Administrator/Desktop/cms/crm_campaigns.php)

The Sales CRM integrates multi-channel lead acquisition with AI lead scoring and automated sales campaign outreach.

### Multi-Source Lead Importer (5 Channels):
1. **CSV Upload**: Bulk import leads via CSV file upload.
2. **CMS Dynamic Forms**: Automatically imports leads submitted on public web forms ([`forms.php`](file:///c:/Users/Administrator/Desktop/cms/forms.php)).
3. **CMS Drive Files**: Imports contacts from spreadsheets saved in Document Drive ([`documents.php`](file:///c:/Users/Administrator/Desktop/cms/documents.php)).
4. **Reception Visitor Desk**: Converts reception visitor logs into CRM leads ([`reception.php`](file:///c:/Users/Administrator/Desktop/cms/reception.php)).
5. **Inbound Webhook API**: Accepts inbound leads from third-party websites or Facebook/Google Ads via API endpoint [`api_v1/lead_webhook.php`](file:///c:/Users/Administrator/Desktop/cms/api_v1/lead_webhook.php).

### AI Lead Scoring Heuristic & 🔥 Hot Leads Filter:
- Evaluates deal size, job title hierarchy, and profile completeness to calculate an engagement score from 0 to 100.
- Renders **🔥 HOT (Score ≥ 75)** badges on Kanban cards.
- **🔥 Hot Leads Only** toggle button in the CRM header to filter high-value prospects instantly.

### Sales Marketing Campaigns & 50/50 A/B Testing:
- Create Email & WhatsApp outreach campaigns targetable by CRM Lead Stage.
- **50/50 A/B Split-Testing Engine**: Automatically alternates between Variant A and Variant B content ([`controllers/execute_campaign.php`](file:///c:/Users/Administrator/Desktop/cms/controllers/execute_campaign.php)).
- **Drip Nurture Sequences**: Configure multi-step automated follow-up sequences.
- **Delivery Logs Modal**: Click **"📊 Logs"** on any campaign card to inspect recipient dispatch statuses (`Sent`, `Queued WhatsApp`, `Failed`).

---

## 4. 📦 Multipurpose Advanced Inventory & Pharmacy POS

**Access URLs**: [`inventory.php`](file:///c:/Users/Administrator/Desktop/cms/inventory.php) | [`inventory_pos.php`](file:///c:/Users/Administrator/Desktop/cms/inventory_pos.php)

The Inventory Engine manages general retail assets as well as specialized Pharmaceutical / Medicine & OTC products.

### Pharmaceutical & OTC Product Management:
- **Batch & Lot Tracking**: Track Batch Numbers, Expiry Dates, and Stock Quantities.
- **Expiry Status Badges**:
  - **🔴 Expired**: Item past expiry date.
  - **🟠 Near Expiry**: Expiring within 30 days.
  - **🟢 Fresh**: Healthy shelf life.
- **Rx Prescription Flags**: Flag medicines requiring a Doctor's prescription before dispensing.
- **Dosage Forms & Storage**: Tablets, Syrups, Injections, Ointments, Boxes, Strips + Warehouse Zone & Rack/Shelf Location.
- **Printable Barcodes**: Generates Code-128 barcodes rendered via `JsBarcode`.
- **Stock History Audit Trail Modal**: Click **"📋 Logs"** on any inventory row to view complete transaction logs (Stock In, POS Sales Out, Audits, Write-offs) with date and user details ([`controllers/get_stock_history.php`](file:///c:/Users/Administrator/Desktop/cms/controllers/get_stock_history.php)).

### Pharmacy POS Billing Studio (`inventory_pos.php`):
- Fast counter sales checkout with real-time item search and barcode scanner support.
- Rx Prescription Doctor validation alert requiring Doctor Name before checkout.
- Automatically updates inventory stock and generates tax invoices in [`invoices.php`](file:///c:/Users/Administrator/Desktop/cms/invoices.php).

---

## 5. 🛒 Procurement & Auto-Restock PO Generator

**Access URLs**: [`procurement.php`](file:///c:/Users/Administrator/Desktop/cms/procurement.php) | [`controllers/auto_po_inventory.php`](file:///c:/Users/Administrator/Desktop/cms/controllers/auto_po_inventory.php)

### 1-Click Auto Restock PO Generator:
- Scans `inventory_items` for products where `quantity <= min_stock_alert`.
- Automatically groups low-stock items by manufacturer and creates draft Purchase Orders in [`procurement.php`](file:///c:/Users/Administrator/Desktop/cms/procurement.php).

### PO Approval to Stock Crediting Loop:
- When an Admin or Finance user marks a Purchase Order as **Approved** in [`controllers/save_po.php`](file:///c:/Users/Administrator/Desktop/cms/controllers/save_po.php), the system automatically credits item quantities back into `inventory_items` and logs a `Stock In` transaction.

### Physical Stock Audit Reconciliation ([`controllers/audit_stock.php`](file:///c:/Users/Administrator/Desktop/cms/controllers/audit_stock.php)):
- Reconciles physical stock counts against system stock, computes variance, updates inventory levels, and records entries in `inventory_audits`.

---

## 6. 📊 Executive BI Dashboard & PWA Offline Engine

**Access URLs**: [`dashboard.php`](file:///c:/Users/Administrator/Desktop/cms/dashboard.php) | `sw.js`

### Executive BI Visualizations:
- **Chart.js Charts**:
  - **Revenue vs Expense Breakdown**: Tracks monthly income vs corporate expenditure.
  - **CRM Lead Pipeline Funnel**: Displays distribution of prospects across Lead Stages.
  - **Enterprise Activity Bar Chart**: Visualizes 7-day system interactions.
  - **Global Task Load Pie Chart**: Visualizes Pending, In Progress, and Completed tasks.

### Progressive Web App (PWA) Offline Engine:
- Registered via [`includes/header.php`](file:///c:/Users/Administrator/Desktop/cms/includes/header.php).
- Uses [`sw.js`](file:///c:/Users/Administrator/Desktop/cms/sw.js) Service Worker to cache core pages (`dashboard.php`, `crm.php`, `card_scanner.php`, `inventory.php`, `inventory_pos.php`) for offline field agent usage.

---

## 7. 🔔 Background Automation Crons (`cron_tasks.php`)

**Execution Command**: `php cron_tasks.php Admin123!SecureCronKey`

The background cron script handles automated enterprise tasks across 8 dedicated sections:

1. **Task Deadline Reminders**: Sends in-app and email notifications for overdue or due-today tasks.
2. **Attendance Auto-Closure**: Clocks out forgotten attendance entries from previous days.
3. **Invoice Overdue Marking**: Marks unpaid invoices past due date as `Overdue`.
4. **CRM Follow-up Reminders**: Alerts lead owners of scheduled follow-ups.
5. **LMS Compliance Expirations**: Resets expired training certifications for re-taking.
6. **Helpdesk SLA Escalations**: Escalates SLA-breached support tickets to Super Admins.
7. **Inventory Expiry Alerts (< 30 Days)**: Scans medicines expiring within 30 days and notifies management.
8. **Automated Drip Campaign Step Execution**: Dispatches scheduled multi-step nurture drip emails to matching leads.

---

## 8. 🛡️ Security Hardening & RBAC Permission Matrix

### Security Protections:
- **SQL Injection (SQLi) Defense**: 100% Parameterized PDO Prepared Statements.
- **Cross-Site Scripting (XSS)**: `htmlspecialchars()` sanitization on all dynamic outputs.
- **Clickjacking Protection**: `X-Frame-Options: SAMEORIGIN` HTTP headers.
- **Upload Executable Block (`uploads/.htaccess`)**: Completely blocks `.php`, `.exe`, or script execution inside upload folders.
- **Silent Logging**: `display_errors = 0` prevents database stack trace leaks.

### Role-Based Access Control (RBAC) Matrix ([`roles.php`](file:///c:/Users/Administrator/Desktop/cms/roles.php)):
Configurable permission groups available under **System Settings → Roles Management**:

| Module Group | Available Permissions |
| :--- | :--- |
| **🎴 Visiting Card Scanner** | `view_visiting_cards`, `scan_visiting_cards`, `manage_visiting_cards` |
| **📢 Sales Campaigns & Drips** | `view_campaigns`, `create_campaigns`, `execute_campaigns`, `manage_campaigns` |
| **📦 Advanced Inventory & POS** | `view_inventory`, `manage_inventory`, `adjust_stock`, `access_pharmacy_pos` |
| **🎯 Sales & CRM** | `view_crm`, `create_leads`, `edit_leads`, `delete_leads`, `convert_leads`, `export_leads` |
| **🧾 Invoices & Accounting** | `view_invoices`, `create_invoices`, `edit_invoices`, `delete_invoices` |
| **🛒 Procurement** | `manage_procurement` |
| **📁 Projects & Tasks** | `view_projects`, `create_projects`, `edit_projects`, `delete_projects`, `view_tasks`, `create_tasks`, `edit_tasks`, `delete_tasks` |
| **👥 User Administration** | `view_users`, `create_users`, `edit_users`, `delete_users`, `manage_users` |

---

*CynoCMS Enterprise System — Documentation Compiled & Verified.*

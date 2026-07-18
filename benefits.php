<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Check if user is logged in
if (!isset($_SESSION['login_id'])) {
    header('Location: login.php');
    exit;
}
?>

<div class="content-section active" style="padding-top:0;">
    <!-- Hero Banner -->
    <div style="background: linear-gradient(135deg, #ec4899, #8b5cf6); border-radius: 0 0 24px 24px; padding: 40px 40px 80px 40px; margin: -20px -20px 20px -20px; color: white; position: relative; overflow: hidden; box-shadow: 0 10px 30px rgba(236, 72, 153, 0.2);">
        <div style="position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="margin: 0 0 10px 0; font-size: 32px; font-weight: 800; letter-spacing: -0.5px;"><i class="fas fa-heartbeat" style="margin-right:10px;"></i> Health & Benefits</h1>
                <p style="margin: 0; font-size: 16px; opacity: 0.9;">Your well-being is our priority. Explore your corporate perks and health coverage.</p>
            </div>
            <button onclick="alert('Feature coming soon: Download Benefits PDF')" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.4); color: white; padding: 12px 24px; border-radius: 12px; font-weight: 700; font-size: 15px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <i class="fas fa-download"></i> Download Summary
            </button>
        </div>
        <div style="position: absolute; right: -50px; top: -50px; width: 250px; height: 250px; background: rgba(255,255,255,0.1); border-radius: 50%; filter: blur(30px);"></div>
    </div>

    <div style="margin-top: -50px; position: relative; z-index: 5; padding: 0 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; max-width: 1200px; margin-left: auto; margin-right: auto;">
        
        <!-- Health Insurance -->
        <div class="glass-card" style="padding: 30px; border-radius: 20px; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 15px 35px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-card)'">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 20px;">
                <i class="fas fa-notes-medical"></i>
            </div>
            <h3 style="margin: 0 0 10px 0; font-size: 20px; font-weight: 700; color: var(--text-heading);">Comprehensive Health Insurance</h3>
            <p style="color: var(--text-muted); font-size: 15px; line-height: 1.6; margin-bottom: 20px;">Full medical, dental, and vision coverage for you and your dependents through our premium provider network.</p>
            <div style="font-weight: 600; color: #3b82f6; font-size: 14px; display: flex; align-items: center; gap: 5px;">
                View Plan Details <i class="fas fa-arrow-right"></i>
            </div>
        </div>

        <!-- Mental Health -->
        <div class="glass-card" style="padding: 30px; border-radius: 20px; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 15px 35px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-card)'">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #10b981, #059669); color: white; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 20px;">
                <i class="fas fa-brain"></i>
            </div>
            <h3 style="margin: 0 0 10px 0; font-size: 20px; font-weight: 700; color: var(--text-heading);">Mental Wellness Program</h3>
            <p style="color: var(--text-muted); font-size: 15px; line-height: 1.6; margin-bottom: 20px;">Free access to licensed therapists, meditation apps, and wellness days to keep your mind healthy and focused.</p>
            <div style="font-weight: 600; color: #10b981; font-size: 14px; display: flex; align-items: center; gap: 5px;">
                Access Resources <i class="fas fa-arrow-right"></i>
            </div>
        </div>

        <!-- Fitness & Gym -->
        <div class="glass-card" style="padding: 30px; border-radius: 20px; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 15px 35px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-card)'">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 20px;">
                <i class="fas fa-dumbbell"></i>
            </div>
            <h3 style="margin: 0 0 10px 0; font-size: 20px; font-weight: 700; color: var(--text-heading);">Fitness Memberships</h3>
            <p style="color: var(--text-muted); font-size: 15px; line-height: 1.6; margin-bottom: 20px;">Get reimbursed up to $100/month for gym memberships, fitness classes, or home workout equipment.</p>
            <div style="font-weight: 600; color: #f59e0b; font-size: 14px; display: flex; align-items: center; gap: 5px;">
                Submit Reimbursement <i class="fas fa-arrow-right"></i>
            </div>
        </div>

        <!-- 401k & Retirement -->
        <div class="glass-card" style="padding: 30px; border-radius: 20px; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 15px 35px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-card)'">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: white; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 20px;">
                <i class="fas fa-piggy-bank"></i>
            </div>
            <h3 style="margin: 0 0 10px 0; font-size: 20px; font-weight: 700; color: var(--text-heading);">401(k) Matching</h3>
            <p style="color: var(--text-muted); font-size: 15px; line-height: 1.6; margin-bottom: 20px;">Secure your future with our 401(k) program. The company matches up to 5% of your contributions.</p>
            <div style="font-weight: 600; color: #8b5cf6; font-size: 14px; display: flex; align-items: center; gap: 5px;">
                Manage Retirement <i class="fas fa-arrow-right"></i>
            </div>
        </div>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

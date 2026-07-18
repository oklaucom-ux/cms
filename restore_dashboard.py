import re

# old_dashboard.php has the correct PHP structure (admin + user)
with open('old_dashboard.php', 'r', encoding='utf-8') as f:
    old_content = f.read()

# dashboard.php has the new glassmorphic admin HTML
with open('dashboard.php', 'r', encoding='utf-8') as f:
    new_content = f.read()

# 1. Extract the new admin HTML from dashboard.php
# It starts at <div class="content-section active" id="printableDashboard">
# and ends right before <script> (which is where the bug happened)
new_html_match = re.search(r'(<div class="content-section active" id="printableDashboard">.*?)<script>', new_content, re.DOTALL)
if new_html_match:
    new_admin_html = new_html_match.group(1)
    
    # In old_dashboard.php, we want to replace the old admin HTML with this new admin HTML.
    # The old admin HTML starts at <div class="content-section active" id="printableDashboard">
    # and ends right before <script>\n        // Chart Initialization (or similar)
    old_html_match = re.search(r'(<div class="content-section active" id="printableDashboard">.*?)(?=<script>\s*//)', old_content, re.DOTALL)
    
    if old_html_match:
        old_admin_html = old_html_match.group(1)
        
        # Replace the old admin HTML with the new admin HTML
        restored_content = old_content.replace(old_admin_html, new_admin_html)
        
        with open('dashboard_restored.php', 'w', encoding='utf-8') as f:
            f.write(restored_content)
        print("Restoration successful! Output written to dashboard_restored.php")
    else:
        print("Could not find old admin HTML in old_dashboard.php")
else:
    print("Could not find new admin HTML in dashboard.php")

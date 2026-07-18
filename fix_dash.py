import re

with open('old_dashboard.php', 'r', encoding='utf-8') as f:
    old = f.read()
with open('dashboard.php', 'r', encoding='utf-8') as f:
    new = f.read()

m = re.search(r'(        <script>.*?)(?=\n</div>\n\n<script>\nfunction generatePDF\(\))', old, re.DOTALL)
if m:
    missing_part = m.group(1)
    new_m = re.search(r'(        <script>.*?)(?=\n</div>\n\n<script>\nfunction generatePDF\(\))', new, re.DOTALL)
    if new_m:
        wrong_part = new_m.group(1)
        fixed = new.replace(wrong_part, missing_part)
        with open('dashboard.php', 'w', encoding='utf-8') as f:
            f.write(fixed)
        print("Fixed!")
    else:
        print("Could not find wrong part in dashboard.php")
else:
    print("Could not find missing part in old_dashboard.php")

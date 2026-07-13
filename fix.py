import os, glob, re

directory = 'c:/Users/Administrator/Desktop/cms'
pattern = re.compile(r"execute\(\[\'\{\$_SESSION\[\', \'login_id\'\]\}\'\', \'([^\']+)\'\]\)")

count = 0
for filepath in glob.glob(directory + '/**/*.php', recursive=True):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
        encoding_used = 'utf-8'
    except UnicodeDecodeError:
        try:
            with open(filepath, 'r', encoding='utf-16') as f:
                content = f.read()
            encoding_used = 'utf-16'
        except UnicodeDecodeError:
            print(f"Skipping {filepath} due to encoding issues.")
            continue
    
    new_content, num_subs = pattern.subn(r"execute([$_SESSION['login_id'], '\1', ''])", content)
    
    if num_subs > 0:
        with open(filepath, 'w', encoding=encoding_used) as f:
            f.write(new_content)
        count += 1
        print(f'Fixed {num_subs} occurrences in {filepath}')

print(f'Total files fixed: {count}')

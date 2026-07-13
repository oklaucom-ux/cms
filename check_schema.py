import os, glob, re

directory = 'c:/Users/Administrator/Desktop/cms'

# 1. Parse init_db.php for CREATE TABLE and ALTER TABLE
with open(os.path.join(directory, 'init_db.php'), 'r', encoding='utf-8') as f:
    init_db = f.read()

schemas = {}
# Find CREATE TABLE
create_pattern = re.compile(r"CREATE TABLE IF NOT EXISTS (\w+) \((.*?)\)", re.IGNORECASE | re.DOTALL)
for match in create_pattern.finditer(init_db):
    table = match.group(1).lower()
    columns_str = match.group(2)
    # remove anything in parentheses to avoid splitting DECIMAL(10, 2)
    columns_str = re.sub(r'\(.*?\)', '', columns_str)
    
    columns = []
    for col_def in columns_str.split(','):
        col_def = col_def.strip()
        if col_def:
            col_name = col_def.split()[0].lower()
            columns.append(col_name)
    schemas[table] = set(columns)

# Find ALTER TABLE
alter_pattern = re.compile(r"ALTER TABLE (\w+) ADD COLUMN (\w+)", re.IGNORECASE)
for match in alter_pattern.finditer(init_db):
    table = match.group(1).lower()
    column = match.group(2).lower()
    if table not in schemas:
        schemas[table] = set()
    schemas[table].add(column)

# Include tables created inside specific APIs (which aren't in init_db.php)
# We should probably parse ALL php files for CREATE TABLE IF NOT EXISTS
create_all_pattern = re.compile(r"CREATE TABLE IF NOT EXISTS (\w+) \((.*?)\)", re.IGNORECASE | re.DOTALL)
alter_all_pattern = re.compile(r"ALTER TABLE (\w+) ADD COLUMN (\w+)", re.IGNORECASE)

for filepath in glob.glob(directory + '/**/*.php', recursive=True):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
    except UnicodeDecodeError:
        continue
        
    for match in create_all_pattern.finditer(content):
        table = match.group(1).lower()
        if table in schemas: continue # skip if already defined in init_db
        columns_str = match.group(2)
        columns_str = re.sub(r'\(.*?\)', '', columns_str)
        columns = []
        for col_def in columns_str.split(','):
            col_def = col_def.strip()
            if col_def:
                col_name = col_def.split()[0].lower()
                columns.append(col_name)
        schemas[table] = set(columns)

    for match in alter_all_pattern.finditer(content):
        table = match.group(1).lower()
        column = match.group(2).lower()
        if table not in schemas:
            schemas[table] = set()
        schemas[table].add(column)

# 2. Parse all PHP files for INSERT and UPDATE
insert_pattern = re.compile(r"INSERT INTO (\w+)\s*\((.*?)\)", re.IGNORECASE)
update_pattern = re.compile(r"UPDATE (\w+)\s+SET\s+(.*?)(?:\s+WHERE|$)", re.IGNORECASE)

mismatches = []

for filepath in glob.glob(directory + '/**/*.php', recursive=True):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
    except UnicodeDecodeError:
        continue
        
    for match in insert_pattern.finditer(content):
        table = match.group(1).lower()
        cols_str = match.group(2)
        cols = [c.strip().lower() for c in cols_str.split(',') if c.strip()]
        
        if table in schemas:
            missing = [c for c in cols if c not in schemas[table]]
            if missing:
                mismatches.append(f"INSERT in {os.path.basename(filepath)} to {table} missing: {missing}")
        else:
             mismatches.append(f"INSERT in {os.path.basename(filepath)} to {table}: TABLE NOT FOUND")
             
    for match in update_pattern.finditer(content):
        table = match.group(1).lower()
        set_str = match.group(2)
        # extract column names from SET a=?, b=?, c=...
        cols = []
        for pair in set_str.split(','):
            if '=' in pair:
                cols.append(pair.split('=')[0].strip().lower())
                
        if table in schemas:
            missing = [c for c in cols if c not in schemas[table]]
            if missing:
                mismatches.append(f"UPDATE in {os.path.basename(filepath)} to {table} missing: {missing}")
        else:
             mismatches.append(f"UPDATE in {os.path.basename(filepath)} to {table}: TABLE NOT FOUND")

if mismatches:
    for m in mismatches:
        print(m)
else:
    print("No schema mismatches found!")

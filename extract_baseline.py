import re

with open('old_init_db.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Extract queries
queries_match = re.search(r'\$queries\s*=\s*\[(.*?)\];', content, re.DOTALL)
queries = queries_match.group(1) if queries_match else ''

# Extract indexes
indexes_match = re.search(r'\$indexes\s*=\s*\[(.*?)\];', content, re.DOTALL)
indexes = indexes_match.group(1) if indexes_match else ''

# Extract migrations
migrations_match = re.search(r'\$migrations\s*=\s*\[(.*?)\];', content, re.DOTALL)
migrations = migrations_match.group(1) if migrations_match else ''

# Write to migrations/001_baseline.php
with open('migrations/001_baseline.php', 'w', encoding='utf-8') as f:
    f.write('<?php\nreturn [\n')
    
    if queries.strip():
        f.write(queries)
        if not queries.strip().endswith(','):
            f.write(',')
            
    if indexes.strip():
        f.write(indexes)
        if not indexes.strip().endswith(','):
            f.write(',')
            
    if migrations.strip():
        f.write(migrations)
        
    f.write('\n];\n')

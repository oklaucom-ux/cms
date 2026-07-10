import os, glob

# 1. client_portal.php & projects.php (Add client column)
for f in ['client_portal.php', 'projects.php']:
    if os.path.exists(f):
        c = open(f, 'r', encoding='utf-8').read()
        if 'ALTER TABLE projects ADD COLUMN client' not in c:
            if f == 'client_portal.php':
                c = c.replace('($clientName = $_SESSION[\'name\'];)', 'try { $pdo->exec("ALTER TABLE projects ADD COLUMN client VARCHAR(255) DEFAULT \'Internal\'"); } catch(Exception $e){}\n\\1')
            else:
                c = c.replace('ALTER TABLE projects ADD COLUMN ai_forecast', 'ALTER TABLE projects ADD COLUMN ai_forecast VARCHAR(255) DEFAULT NULL"); } catch(Exception $e){}\ntry { $pdo->exec("ALTER TABLE projects ADD COLUMN client VARCHAR(255) DEFAULT \'Internal\'')
            open(f, 'w', encoding='utf-8').write(c)

# 2. omni_desk.php
f = 'omni_desk.php'
if os.path.exists(f):
    c = open(f, 'r', encoding='utf-8').read()
    if 'CREATE TABLE IF NOT EXISTS unified_tickets' not in c:
        tbl = 'try { $pdo->exec("CREATE TABLE IF NOT EXISTS unified_tickets (id INTEGER PRIMARY KEY AUTO_INCREMENT, source VARCHAR(255) NOT NULL, ticket_number VARCHAR(255), requester_id VARCHAR(255), requester_name VARCHAR(255), department VARCHAR(255), subject TEXT NOT NULL, description TEXT NOT NULL, priority VARCHAR(255) DEFAULT \'Medium\', status VARCHAR(255) DEFAULT \'Open\', assigned_agent_id VARCHAR(255), resolution_notes TEXT, is_anonymous INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)"); } catch(Exception $e){}\n'
        c = c.replace('requirePermission($pdo, \'view_helpdesk\');', 'requirePermission($pdo, \'view_helpdesk\');\n' + tbl)
        open(f, 'w', encoding='utf-8').write(c)

# 3. crm.php
f = 'crm.php'
if os.path.exists(f):
    c = open(f, 'r', encoding='utf-8').read()
    if 'CREATE TABLE IF NOT EXISTS api_keys' not in c:
        tbl = 'try { $pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id VARCHAR(255) NOT NULL, api_key VARCHAR(255) UNIQUE NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)"); } catch(Exception $e){}\n'
        c = c.replace('requirePermission($pdo, \'view_crm\');', 'requirePermission($pdo, \'view_crm\');\n' + tbl)
        open(f, 'w', encoding='utf-8').write(c)

# 4. expenses.php
f = 'expenses.php'
if os.path.exists(f):
    c = open(f, 'r', encoding='utf-8').read()
    if 'ALTER TABLE expenses ADD COLUMN user_id' not in c:
        alters = 'try { $pdo->exec("ALTER TABLE expenses ADD COLUMN user_id VARCHAR(255)"); } catch(Exception $e){}\ntry { $pdo->exec("ALTER TABLE expenses ADD COLUMN category VARCHAR(255)"); } catch(Exception $e){}\ntry { $pdo->exec("ALTER TABLE expenses ADD COLUMN description TEXT"); } catch(Exception $e){}\ntry { $pdo->exec("ALTER TABLE expenses ADD COLUMN receipt_url TEXT"); } catch(Exception $e){}\ntry { $pdo->exec("ALTER TABLE expenses ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP"); } catch(Exception $e){}\n'
        c = c.replace('try { $pdo->exec("ALTER TABLE expenses ADD COLUMN branch_id', alters + 'try { $pdo->exec("ALTER TABLE expenses ADD COLUMN branch_id')
        open(f, 'w', encoding='utf-8').write(c)

# 5. assets.php
f = 'assets.php'
if os.path.exists(f):
    c = open(f, 'r', encoding='utf-8').read()
    c = c.replace('condition VARCHAR', '`condition` VARCHAR')
    c = c.replace(' condition ', ' `condition` ')
    c = c.replace(' condition=', ' `condition`=')
    c = c.replace(' condition,', ' `condition`,')
    open(f, 'w', encoding='utf-8').write(c)

# 6. onboarding.php
f = 'onboarding.php'
if os.path.exists(f):
    c = open(f, 'r', encoding='utf-8').read()
    if 'CREATE TABLE IF NOT EXISTS onboarding_applications' not in c:
        tbl = 'try { $pdo->exec("CREATE TABLE IF NOT EXISTS onboarding_applications (id INTEGER PRIMARY KEY AUTO_INCREMENT, first_name VARCHAR(255), last_name VARCHAR(255), email VARCHAR(255), position_applied VARCHAR(255), resume_link VARCHAR(255), status VARCHAR(255) DEFAULT \'Pending\', applied_at DATETIME DEFAULT CURRENT_TIMESTAMP)"); } catch(Exception $e){}\n'
        c = c.replace('requirePermission($pdo, \'manage_onboarding\');', 'requirePermission($pdo, \'manage_onboarding\');\n' + tbl)
        open(f, 'w', encoding='utf-8').write(c)

# 7. Global currency fix
for f in glob.glob('**/*.php', recursive=True):
    try:
        c = open(f, 'r', encoding='utf-8').read()
        nc = c.replace(r"'\xe2\x82\xb9'", "'₹'")
        if nc != c:
            open(f, 'w', encoding='utf-8').write(nc)
            print('Fixed currency in', f)
    except:
        pass

print('Python Mega Fix Completed.')

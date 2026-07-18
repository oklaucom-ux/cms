import sqlite3

conn = sqlite3.connect('database.sqlite')
cursor = conn.cursor()
cursor.execute("PRAGMA table_info(pulse_responses)")
columns = cursor.fetchall()

print("Columns in pulse_responses:")
for col in columns:
    print(col[1], "-", col[2])

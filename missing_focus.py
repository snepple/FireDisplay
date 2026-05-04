import re

with open('admin.php', 'r') as f:
    content = f.read()

# Look for button:focus-visible or a:focus-visible
if 'focus-visible' not in content:
    print("No focus-visible styles found in admin.php")
else:
    print("Found focus-visible styles in admin.php")

with open('index.php', 'r') as f:
    content = f.read()
if 'focus-visible' not in content:
    print("No focus-visible styles found in index.php")
else:
    print("Found focus-visible styles in index.php")

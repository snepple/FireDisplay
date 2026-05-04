import re

with open('api/save_locations.php', 'r') as f:
    content = f.read()

# Replace $input['csrf_token'] !== $_SESSION['csrf_token'] with !hash_equals((string)$_SESSION['csrf_token'], (string)$input['csrf_token'])
content = re.sub(
    r"\$input\['csrf_token'\] !== \$_SESSION\['csrf_token'\]",
    r"!hash_equals((string)$_SESSION['csrf_token'], (string)$input['csrf_token'])",
    content
)

with open('api/save_locations.php', 'w') as f:
    f.write(content)

with open('admin.php', 'r') as f:
    content = f.read()

content = re.sub(
    r"!hash_equals\(\$_SESSION\['csrf_token'\], \$_POST\['csrf_token'\]\)",
    r"!hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])",
    content
)

content = re.sub(
    r"!hash_equals\(\$_SESSION\['csrf_token'\], \$_GET\['csrf_token'\]\)",
    r"!hash_equals((string)$_SESSION['csrf_token'], (string)$_GET['csrf_token'])",
    content
)

with open('admin.php', 'w') as f:
    f.write(content)

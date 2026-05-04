import re

with open('admin.php', 'r') as f:
    content = f.read()

# Find all <label> tags
labels = re.finditer(r'<label[^>]*>(.*?)</label>', content, re.IGNORECASE | re.DOTALL)
for match in labels:
    label_tag = match.group(0)
    if 'for=' not in label_tag.lower():
        print(f"Missing 'for' in: {label_tag}")

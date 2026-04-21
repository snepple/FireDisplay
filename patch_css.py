import re

css = """
        .no-burn-permits { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; text-align: center; padding: 20px; box-sizing: border-box; }
        .no-burn-permits img { max-width: 400px; width: 50%; height: auto; margin-bottom: 30px; }
        .no-burn-permits p { font-size: clamp(3em, 4vw, 5em); font-weight: 700; color: var(--text-color); margin: 0; line-height: 1.2; }
"""

for filename in ['index.php', 'current_index.php']:
    with open(filename, 'r') as f:
        content = f.read()

    # insert after .no-events class
    new_content = re.sub(r'(\.no-events \{[^\}]+\})', r'\1\n' + css, content)

    with open(filename, 'w') as f:
        f.write(new_content)

print("CSS added.")

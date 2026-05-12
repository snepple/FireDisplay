import sys
import re
from datetime import datetime

with open(".jules/bolt.md", "r") as f:
    content = f.read()

# Replace the incorrect date format
content = re.sub(r'## \$\(date \+\%Y-\%m-\%d\)', f'## {datetime.now().strftime("%Y-%m-%d")}', content)

with open(".jules/bolt.md", "w") as f:
    f.write(content)

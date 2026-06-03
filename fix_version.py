import sys

with open('juniors-banner-manager.php', 'r') as f:
    content = f.read()

# Update header version
content = content.replace("* Version: 3.1", "* Version: 3.2")

# Update admin UI version
content = content.replace("v3.1", "v3.2")

with open('juniors-banner-manager.php', 'w') as f:
    f.write(content)

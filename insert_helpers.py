import sys

with open('juniors-banner-manager.php', 'r') as f:
    lines = f.readlines()

with open('fix_functions.php', 'r') as f:
    helpers = f.read()

# Insert before handle_custom_banner_save (around line 135)
new_lines = []
inserted = False
for line in lines:
    if "public function handle_custom_banner_save()" in line and not inserted:
        new_lines.append(helpers + "\n")
        inserted = True
    new_lines.append(line)

with open('juniors-banner-manager.php', 'w') as f:
    f.writelines(new_lines)

import re

with open('plugin classificação.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Fix the escaped quotes that were accidentally injected
content = content.replace('id="cb_html_desktop_wrapper\\"', 'id="cb_html_desktop_wrapper"')
content = content.replace('id="cb_html_mobile_wrapper\\"', 'id="cb_html_mobile_wrapper"')
content = content.replace('id="scb_html_desktop_wrapper\\"', 'id="scb_html_desktop_wrapper"')
content = content.replace('id="scb_html_mobile_wrapper\\"', 'id="scb_html_mobile_wrapper"')

with open('plugin classificação.php', 'w', encoding='utf-8') as f:
    f.write(content)

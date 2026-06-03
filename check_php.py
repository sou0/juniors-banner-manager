import sys

def check_php(filename):
    with open(filename, 'r') as f:
        code = f.read()

    # Simplistic check for obvious syntax errors
    print("Code length:", len(code))

check_php('/home/basilica/produtos/plugin-classificacao/plugin classificação.php')

import difflib

def read_file(filepath):
    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
        return f.readlines()

active_lines = read_file('c:/Users/iWexort/Documents/Github/mirzabot-main/function.php')
default_lines = read_file('c:/Users/iWexort/Documents/Github/mirzabot-main/temp_mirzabot/function.php')

diff = difflib.unified_diff(default_lines, active_lines, fromfile='temp_mirzabot/function.php', tofile='function.php')
with open('c:/Users/iWexort/Documents/Github/mirzabot-main/diff_function.txt', 'w', encoding='utf-8') as f:
    f.writelines(diff)

print("Diff completed. Written to diff_function.txt")

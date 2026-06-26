import difflib

file1 = r'c:\Users\iWexort\Documents\Github\mirzabot-main\temp_mirzabot\panels.php'
file2 = r'c:\Users\iWexort\Documents\Github\mirzabot-main\panels.php'

with open(file1, 'r', encoding='utf-8') as f1, open(file2, 'r', encoding='utf-8') as f2:
    lines1 = f1.readlines()
    lines2 = f2.readlines()

diff = difflib.unified_diff(lines1, lines2, fromfile=file1, tofile=file2, n=3)

with open(r'c:\Users\iWexort\Documents\Github\mirzabot-main\diff_panels.txt', 'w', encoding='utf-8') as out:
    out.writelines(diff)

print("Diff complete. Written to diff_panels.txt.")

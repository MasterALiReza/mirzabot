import difflib

def read_lines(filepath, start_line, end_line):
    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
        lines = f.readlines()
    return lines[start_line-1:end_line]

# Active index.phpconfirmandgetservice block (around lines 3830 to 4100)
active_lines = read_lines('c:/Users/iWexort/Documents/Github/mirzabot-main/index.php', 3830, 4100)

# Default temp_mirzabot/index.php confirmandgetservice block (around lines 3758 to 3975)
default_lines = read_lines('c:/Users/iWexort/Documents/Github/mirzabot-main/temp_mirzabot/index.php', 3758, 3975)

# Write output to diff_confirm.txt
diff = difflib.unified_diff(default_lines, active_lines, fromfile='temp_mirzabot/index.php', tofile='index.php')
with open('c:/Users/iWexort/Documents/Github/mirzabot-main/diff_confirm.txt', 'w', encoding='utf-8') as f:
    f.writelines(diff)

print("Diff completed. Written to diff_confirm.txt")

import os


root_dir = '/opt/lampp/htdocs/CityUMapNav'


print(f"Directory Structure for: {root_dir}\n")
for dirpath, dirnames, filenames in os.walk(root_dir):
    # Skip hidden directories like .git if any
    dirnames[:] = [d for d in dirnames if not d.startswith('.')]
    
    level = dirpath.replace(root_dir, '').count(os.sep)
    indent = ' ' * 4 * level
    print(f"{indent}{os.path.basename(dirpath)}/")
    
    subindent = ' ' * 4 * (level + 1)
    for f in filenames:
        if not f.startswith('.'):
            print(f"{subindent}{f}")
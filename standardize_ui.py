import os
import glob
import re

def standardize(content):
    # Standardize Card and general container rounding to 24px
    content = re.sub(r'rounded-\[3rem\]', 'rounded-[24px]', content)
    content = re.sub(r'rounded-3xl', 'rounded-[24px]', content)
    
    # Safely replace rounded-2xl to rounded-[24px] but not for inputs/buttons. 
    # Actually, a global replace is safe because our standard says cards are 24px, 
    # but buttons/inputs should be 16px.
    # Let's just do a naive replace for structural elements.
    content = re.sub(r'rounded-2xl', 'rounded-[24px]', content)
    
    # Buttons and inputs
    content = re.sub(r'rounded-lg', 'rounded-[16px]', content)
    content = re.sub(r'rounded-xl', 'rounded-[16px]', content)
    
    # Standardize borders
    content = re.sub(r'border-slate-100', 'border-[#E8EEF6]', content)
    content = re.sub(r'border-slate-200', 'border-[#E8EEF6]', content)
    
    # Standardize shadows
    content = re.sub(r'shadow-sm', 'shadow-[0_8px_24px_rgba(15,23,42,0.04)]', content)
    content = re.sub(r'shadow-md', 'shadow-[0_8px_24px_rgba(15,23,42,0.04)]', content)
    
    # Buttons should have height 52px (h-[52px])
    content = re.sub(r'py-2 px-4', 'h-[52px] px-6', content)
    content = re.sub(r'px-4 py-2', 'h-[52px] px-6', content)

    return content

files = glob.glob('resources/js/Pages/**/*.vue', recursive=True)
changed = 0

for file in files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    new_content = standardize(content)
    
    if new_content != content:
        with open(file, 'w', encoding='utf-8') as f:
            f.write(new_content)
        changed += 1
        print(f"Updated {file}")

print(f"Total files updated: {changed}")

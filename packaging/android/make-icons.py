"""Génère les icônes de lancement Android (classiques + adaptatives) à partir de icon-source.png. Usage : python3 make-icons.py <dossier res>"""
import sys, os
from PIL import Image
res = sys.argv[1]; src = Image.open(os.path.join(os.path.dirname(__file__), 'icon-source.png')).convert('RGBA')
legacy = {'mdpi': 48, 'hdpi': 72, 'xhdpi': 96, 'xxhdpi': 144, 'xxxhdpi': 192}
fg = {'mdpi': 108, 'hdpi': 162, 'xhdpi': 216, 'xxhdpi': 324, 'xxxhdpi': 432}
for d, size in legacy.items():
    out = os.path.join(res, 'mipmap-' + d); os.makedirs(out, exist_ok=True)
    img = src.resize((size, size), Image.LANCZOS)
    img.save(os.path.join(out, 'ic_launcher.png')); img.save(os.path.join(out, 'ic_launcher_round.png'))
for d, size in fg.items():
    out = os.path.join(res, 'mipmap-' + d); os.makedirs(out, exist_ok=True)
    canvas = Image.new('RGBA', (size, size), (0, 0, 0, 0)); inner = int(size * 0.62)
    canvas.paste(src.resize((inner, inner), Image.LANCZOS), ((size - inner) // 2, (size - inner) // 2))
    canvas.save(os.path.join(out, 'ic_launcher_foreground.png'))
vals = os.path.join(res, 'values'); os.makedirs(vals, exist_ok=True)
open(os.path.join(vals, 'ic_launcher_background.xml'), 'w').write('<?xml version="1.0" encoding="utf-8"?>\n<resources>\n    <color name="ic_launcher_background">#FFFFFF</color>\n</resources>\n')

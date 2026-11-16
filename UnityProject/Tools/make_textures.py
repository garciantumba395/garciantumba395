"""Génère les textures de la ville (asphalte avec marquages, façade à fenêtres) -> Assets/Textures/.
Usage : python3 Tools/make_textures.py   (Pillow + numpy). Les PNG générés sont versionnés."""
import numpy as np
from PIL import Image, ImageDraw, ImageFilter
rng = np.random.default_rng(7)
OUT = "Assets/Textures/"

def noise(w, h, scale, amp):
    small = rng.normal(0, 1, (max(h // scale, 2), max(w // scale, 2)))
    im = Image.fromarray(((small - small.min()) / (np.ptp(small) + 1e-6) * 255).astype(np.uint8)).resize((w, h), Image.BICUBIC)
    return (np.asarray(im, dtype=np.float32) / 255.0 - 0.5) * 2 * amp

# ---------- Asphalte : u = largeur (0..1), v = longueur (12 m par motif) ----------
W, H = 256, 512
base = np.full((H, W), 92, np.float32)
base += noise(W, H, 2, 10) + noise(W, H, 16, 8)
img = np.stack([base, base, base * 1.02], -1)
# traces de pneus (deux bandes plus sombres et lisses par demi-voie)
for cx in (0.25, 0.75):
    for off in (-0.07, 0.07):
        x = int((cx + off) * W)
        img[:, max(x - 7, 0):x + 7, :] -= 5
im = Image.fromarray(np.clip(img, 0, 255).astype(np.uint8))
d = ImageDraw.Draw(im)
# fissures et rustines
for _ in range(14):
    x, y = rng.integers(10, W - 10), rng.integers(0, H)
    pts = [(x, y)]
    for _ in range(rng.integers(3, 8)):
        x += rng.integers(-14, 14); y += rng.integers(6, 28); pts.append((x, y))
    d.line(pts, fill=(48, 48, 50), width=1)
for _ in range(4):
    x, y = rng.integers(20, W - 60), rng.integers(0, H - 60)
    d.rectangle([x, y, x + rng.integers(25, 55), y + rng.integers(20, 50)], fill=(82, 82, 84))
# lignes de rive (blanches, légèrement usées) et ligne centrale discontinue (6 m / 6 m)
line = (205, 205, 198)
d.rectangle([int(.04 * W), 0, int(.06 * W), H], fill=line)
d.rectangle([int(.94 * W), 0, int(.96 * W), H], fill=line)
d.rectangle([int(.485 * W), 0, int(.515 * W), H // 2], fill=(214, 214, 205))
im = im.filter(ImageFilter.GaussianBlur(0.6))
a = np.asarray(im, np.float32) + noise(W, H, 1, 9)[..., None]
Image.fromarray(np.clip(a, 0, 255).astype(np.uint8)).save(OUT + "road_asphalt.png", optimize=True)

# ---------- Façade : 6 m x 3,2 m (un étage, deux fenêtres) ----------
W, H = 512, 256            # ~85 px/m
wall = np.full((H, W, 3), (205, 196, 182), np.float32)   # béton clair (la teinte du matériau le colore)
wall += noise(W, H, 2, 7)[..., None] + noise(W, H, 24, 9)[..., None]
im = Image.fromarray(np.clip(wall, 0, 255).astype(np.uint8))
d = ImageDraw.Draw(im)
px = lambda m: int(m * 85.3)
# dalle d'étage (bande pleine en bas : sert aussi de toit grâce aux coordonnées de texture)
d.rectangle([0, H - px(0.26), W, H], fill=(176, 170, 160))
for x0 in (0.9, 3.9):      # deux fenêtres de 1,2 m x 1,4 m
    X0, X1 = px(x0), px(x0 + 1.2); Y0, Y1 = px(0.7), px(0.7 + 1.4)
    d.rectangle([X0 - 6, Y0 - 6, X1 + 6, Y1 + 6], fill=(150, 144, 134))        # encadrement
    d.rectangle([X0, Y0, X1, Y1], fill=(46, 62, 84))                             # vitre
    d.rectangle([X0, Y0, X1, Y0 + (Y1 - Y0) // 3], fill=(86, 112, 140))          # reflet du ciel
    d.line([(X0 + (X1 - X0) // 2, Y0), (X0 + (X1 - X0) // 2, Y1)], fill=(150, 144, 134), width=4)
    d.rectangle([X0 - 10, Y1 + 6, X1 + 10, Y1 + 14], fill=(130, 124, 116))       # rebord
    for gx in range(X0 - 4, X1 + 5, 12):                                         # grille de balcon
        d.line([(gx, Y1 + 14), (gx, Y1 + px(0.45))], fill=(60, 60, 62), width=2)
    d.line([(X0 - 4, Y1 + px(0.45)), (X1 + 4, Y1 + px(0.45))], fill=(60, 60, 62), width=3)
im = im.filter(ImageFilter.GaussianBlur(0.5))
a = np.asarray(im, np.float32)
# salissures verticales (coulures d'eau)
for _ in range(10):
    x = rng.integers(0, W); a[:, x:x + rng.integers(2, 7), :] *= rng.uniform(0.86, 0.95)
a += noise(W, H, 1, 5)[..., None]
Image.fromarray(np.clip(a, 0, 255).astype(np.uint8)).save(OUT + "facade_01.png", optimize=True)
print("ok")

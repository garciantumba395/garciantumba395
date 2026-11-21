import sys, glob
from PIL import Image
prefix, out = sys.argv[1], sys.argv[2]
fs = sorted(glob.glob(f"out/qa/{prefix}-f*.png"), key=lambda p: int(p.split("-f")[-1][:-4]))
ims = [Image.open(f).convert("RGB").resize((360, 640)) for f in fs]
sheet = Image.new("RGB", (360 * len(ims), 640))
for i, im in enumerate(ims): sheet.paste(im, (i * 360, 0))
sheet.save(out); print(fs)

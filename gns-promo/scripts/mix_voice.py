#!/usr/bin/env python3
"""Cale chaque phrase de la narration (prise 1) sur son plan.
Découpe aux pauses relevées par la transcription Scribe, tempo +6 %, placement à l'échantillon près
(temps vidéo en secondes), puis normalisation de volume (-16 LUFS) vers public/audio/voix-mix.mp3."""
import subprocess, wave, numpy as np, sys, os
os.chdir(os.path.join(os.path.dirname(os.path.abspath(__file__)), ".."))
SRC = "public/audio/voix-take1.mp3"; SR = 44100; TOTAL = 45.06; TEMPO = 1.06
SEGS = [  # nom, début, fin (secondes de la prise), placement (secondes de la vidéo)
    ("A", 0.00, 2.04, 0.35),    # Vous cherchez le smartphone qu'il vous faut ?
    ("B", 2.04, 6.84, 4.75),    # Quatre grandes marques. Une seule boutique : GNS Business RDC.
    ("C", 6.84, 10.08, 9.30),   # Apple
    ("D", 10.08, 13.96, 14.80), # Samsung Galaxy
    ("E", 13.96, 17.76, 20.70), # Google Pixel
    ("F", 17.76, 22.66, 26.10), # TECNO
    ("G", 22.66, 26.70, 32.40), # Commandez : nous expédions votre colis partout en RDC
    ("H", 26.70, 30.84, 36.50), # Appelez le +243 983 468 341
    ("I", 30.84, 35.40, 40.60), # Et visitez GNS Business RDC point store...
]
def decode(s, e):
    cmd = ["ffmpeg", "-v", "error", "-i", SRC, "-af", f"atrim=start={s}:end={e},asetpts=PTS-STARTPTS,atempo={TEMPO}",
           "-ac", "1", "-ar", str(SR), "-f", "s16le", "-"]
    return np.frombuffer(subprocess.run(cmd, check=True, capture_output=True).stdout, dtype=np.int16).astype(np.float64)
buf = np.zeros(int(TOTAL * SR))
for name, s, e, at in SEGS:
    x = decode(s, e); i = int(round(at * SR))
    n = min(len(x), len(buf) - i); buf[i:i + n] += x[:n]
    print(f"{name}: {at:6.2f} s → {at + len(x)/SR:6.2f} s")
pk = np.abs(buf).max(); buf = buf / max(pk, 1) * 0.9
raw = "/tmp/voix-raw.wav"
with wave.open(raw, "wb") as w:
    w.setnchannels(1); w.setsampwidth(2); w.setframerate(SR); w.writeframes((buf * 32767).astype(np.int16).tobytes())
subprocess.run(["ffmpeg", "-v", "error", "-y", "-i", raw, "-af", "loudnorm=I=-16:TP=-1.5:LRA=9", "-ar", str(SR), "-b:a", "192k", "public/audio/voix-mix.mp3"], check=True)
print("ok")

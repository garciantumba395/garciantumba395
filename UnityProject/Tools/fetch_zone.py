#!/usr/bin/env python3
"""
Télécharge les données OpenStreetMap d'une zone (API officielle, petite zone) puis les convertit en fichier de zone.

Usage :
  python3 Tools/fetch_zone.py --zone gare_centrale            # une zone de Tools/zones.json
  python3 Tools/fetch_zone.py --all                           # toutes les zones

Si la zone dépasse la limite de l'API (50 000 points), elle est découpée en quatre et les morceaux sont fusionnés.
Données © contributeurs OpenStreetMap (ODbL). Utilisation raisonnable : une requête par seconde au plus.
"""
import argparse, json, math, os, subprocess, sys, time, urllib.error, urllib.request
import xml.etree.ElementTree as ET

API = "https://api.openstreetmap.org/api/0.6/map?bbox={w:.6f},{s:.6f},{e:.6f},{n:.6f}"
UA = "TaxiKinMapTool/0.1 (jeu mobile ; contact via le dépôt)"
HERE = os.path.dirname(os.path.abspath(__file__))


def bbox_for(zone):
    """Boîte englobante (ouest, sud, est, nord) autour du centre, rayon en mètres (+5 % de marge)."""
    r = zone["radius"] * 1.05
    dlat = r / 110574.0
    dlon = r / (111320.0 * math.cos(math.radians(zone["lat"])))
    return zone["lon"] - dlon, zone["lat"] - dlat, zone["lon"] + dlon, zone["lat"] + dlat


def fetch(w, s, e, n, depth=0):
    """Retourne la liste des éléments XML (node/way) de la boîte ; découpe en 4 si l'API refuse (trop de points)."""
    url = API.format(w=w, s=s, e=e, n=n)
    time.sleep(1.0)
    try:
        req = urllib.request.Request(url, headers={"User-Agent": UA})
        with urllib.request.urlopen(req, timeout=120) as resp:
            root = ET.fromstring(resp.read())
        return [el for el in root if el.tag in ("node", "way")]
    except urllib.error.HTTPError as err:
        if err.code in (400, 509) and depth < 3:        # 400 : trop de points ; 509 : limite de bande passante
            mx, my = (w + e) / 2, (s + n) / 2
            print(f"  zone trop dense (HTTP {err.code}) : découpage en 4 (niveau {depth + 1})")
            out = []
            for box in ((w, s, mx, my), (mx, s, e, my), (w, my, mx, n), (mx, my, e, n)):
                out += fetch(*box, depth + 1)
            return out
        raise


def merge_to_file(elements, path):
    """Fusionne sans doublons (un même point peut être dans deux morceaux) et écrit un .osm."""
    seen, root = set(), ET.Element("osm", version="0.6")
    for el in elements:
        key = (el.tag, el.get("id"))
        if key in seen: continue
        seen.add(key); root.append(el)
    ET.ElementTree(root).write(path, encoding="utf-8", xml_declaration=True)
    return sum(1 for k in seen if k[0] == "node"), sum(1 for k in seen if k[0] == "way")


def run(zone_id, zones_file, keep_raw):
    zones = json.load(open(zones_file, encoding="utf-8"))
    zone = next((z for z in zones["zones"] if z["id"] == zone_id), None)
    if zone is None: sys.exit("Zone inconnue : " + zone_id)
    print(f"== {zone['name']} ({zone_id})")
    raw = os.path.join(os.environ.get("TMPDIR", "/tmp"), f"taxikin_{zone_id}.osm")
    nodes, ways = merge_to_file(fetch(*bbox_for(zone)), raw)
    print(f"  téléchargé : {nodes} points, {ways} chemins")
    out = os.path.join(HERE, "..", "Assets", "Resources", "Zones", f"{zone_id}.json")
    subprocess.check_call([sys.executable, os.path.join(HERE, "osm_to_zone.py"), "--input", raw, "--zone", zone_id,
                           "--zones", zones_file, "--out", out])
    if not keep_raw: os.remove(raw)


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--zone"); ap.add_argument("--all", action="store_true")
    ap.add_argument("--zones", default=os.path.join(HERE, "zones.json"))
    ap.add_argument("--keep-raw", action="store_true", help="conserve le fichier .osm brut (dossier temporaire)")
    a = ap.parse_args()
    ids = [z["id"] for z in json.load(open(a.zones, encoding="utf-8"))["zones"]] if a.all else [a.zone]
    if not ids or ids == [None]: sys.exit("Indiquer --zone <id> ou --all")
    for i in ids: run(i, a.zones, a.keep_raw)

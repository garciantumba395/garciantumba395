#!/usr/bin/env python3
"""
Convertit un export OpenStreetMap (.osm XML ou Overpass JSON) en fichier de zone Taxi Kin.

Usage :
  python3 osm_to_zone.py --input export.osm --zone gare_centrale \
      --zones zones.json --out ../Assets/Resources/Zones/gare_centrale.json

Sortie : JSON compact (mètres, repère local centré sur la zone, +x = est, +z = nord).
Données © contributeurs OpenStreetMap (ODbL) : l'attribution doit figurer dans le jeu.
"""
import argparse, hashlib, json, math, sys
import xml.etree.ElementTree as ET

ROAD_WIDTH = {  # largeur en mètres (le jeu est à voie large pour rester jouable au doigt)
    "motorway": 14, "trunk": 14, "primary": 12, "secondary": 10, "tertiary": 8,
    "unclassified": 6, "residential": 6, "living_street": 5, "service": 4,
}
ROAD_KIND = {k: i for i, k in enumerate(ROAD_WIDTH)}
POI_KINDS = [
    ("railway", {"station", "halt"}, "station"),
    ("amenity", {"marketplace"}, "marche"),
    ("amenity", {"bus_station"}, "gare_bus"),
    ("amenity", {"hospital"}, "hopital"),
    ("amenity", {"fuel"}, "station_service"),
    ("aeroway", {"terminal", "aerodrome"}, "aeroport"),
    ("tourism", {"hotel"}, "hotel"),
    ("highway", {"bus_stop"}, "arret"),
]


# Le cahier des charges interdit les noms et marques d'entreprises réelles : on ne conserve le nom d'un point d'intérêt
# que pour les lieux publics (gares, aéroports, marchés) ; hôtels, stations-service, hôpitaux, arrêts et gares routières
# (souvent nommés d'après une entreprise) sont rendus anonymes.
PUBLIC_NAME_KINDS = {"station", "aeroport", "marche"}


def load_osm(path):
    """Retourne (nodes{id:(lat,lon)}, ways[{id,nodes,tags}], poi_nodes[{lat,lon,tags}])."""
    nodes, ways, poi = {}, [], []
    if path.lower().endswith(".json"):
        for e in json.load(open(path, encoding="utf-8")).get("elements", []):
            if e["type"] == "node":
                nodes[e["id"]] = (e["lat"], e["lon"])
                if e.get("tags"): poi.append({"lat": e["lat"], "lon": e["lon"], "tags": e["tags"]})
            elif e["type"] == "way":
                if "geometry" in e and "nodes" in e:   # out geom
                    for nid, g in zip(e["nodes"], e["geometry"]):
                        if g: nodes[nid] = (g["lat"], g["lon"])
                ways.append({"id": e["id"], "nodes": e.get("nodes", []), "tags": e.get("tags", {})})
    else:
        for el in ET.parse(path).getroot():
            if el.tag == "node":
                lat, lon = float(el.get("lat")), float(el.get("lon"))
                nodes[int(el.get("id"))] = (lat, lon)
                tags = {t.get("k"): t.get("v") for t in el.findall("tag")}
                if tags: poi.append({"lat": lat, "lon": lon, "tags": tags})
            elif el.tag == "way":
                ways.append({"id": int(el.get("id")),
                             "nodes": [int(n.get("ref")) for n in el.findall("nd")],
                             "tags": {t.get("k"): t.get("v") for t in el.findall("tag")}})
    return nodes, ways, poi


class Projector:
    """Projection équirectangulaire locale (précise à quelques cm sur 2 km)."""
    def __init__(self, lat0, lon0):
        self.lat0, self.lon0 = lat0, lon0
        self.kx = 111320.0 * math.cos(math.radians(lat0))
        self.kz = 110574.0
    def xz(self, lat, lon):
        return (lon - self.lon0) * self.kx, (lat - self.lat0) * self.kz


def polygon_area(pts):
    return 0.5 * sum(pts[i][0] * pts[(i + 1) % len(pts)][1] - pts[(i + 1) % len(pts)][0] * pts[i][1]
                     for i in range(len(pts)))


def building_height(way):
    t = way["tags"]
    try:
        if "height" in t: return max(3.0, float(str(t["height"]).split()[0]))
        if "building:levels" in t: return max(3.0, float(t["building:levels"]) * 3.2)
    except ValueError:
        pass
    # Hauteur stable (même immeuble = même hauteur à chaque génération) entre 6 et 14 m
    h = int(hashlib.md5(str(way["id"]).encode()).hexdigest()[:4], 16) % 9
    return 6.0 + h


def clip_polyline(pts, half):
    """Découpe une polyligne : on ne garde que les tronçons dont le milieu est dans le carré (centre 0,0 ; demi-côté 'half').
    Retourne une liste de polylignes (une route qui sort puis rentre donne plusieurs morceaux)."""
    out, cur = [], []
    for i in range(len(pts) - 1):
        a, b = pts[i], pts[i + 1]
        mx, mz = (a[0] + b[0]) / 2, (a[1] + b[1]) / 2
        if abs(mx) <= half and abs(mz) <= half:
            if not cur: cur = [a]
            cur.append(b)
        elif cur:
            out.append(cur); cur = []
    if cur: out.append(cur)
    return out


def flat(pts):
    out = []
    for x, z in pts: out += [round(x, 1), round(z, 1)]
    return out


def convert(args):
    zones = json.load(open(args.zones, encoding="utf-8"))
    z = next((q for q in zones["zones"] if q["id"] == args.zone), None)
    if z is None: sys.exit("Zone inconnue : " + args.zone)
    radius = args.radius or z["radius"]
    proj = Projector(z["lat"], z["lon"])
    origin = Projector(z["lat"], z["lon"]).xz(zones["origin"]["lat"], zones["origin"]["lon"])

    nodes, ways, poi_nodes = load_osm(args.input)
    xz = {nid: proj.xz(*ll) for nid, ll in nodes.items()}

    def near(pts):  # au moins un point dans le carré de la zone
        return any(abs(x) <= radius and abs(zz) <= radius for x, zz in pts)

    roads, buildings = [], []
    for w in ways:
        pts = [xz[n] for n in w["nodes"] if n in xz]
        if len(pts) < 2 or not near(pts): continue
        tags = w["tags"]
        hw = tags.get("highway")
        if hw in ROAD_WIDTH:
            for piece in clip_polyline(pts, radius + 80.0):      # aucune route ne flotte au-delà du sol de la zone
                roads.append({"kind": ROAD_KIND[hw], "width": ROAD_WIDTH[hw], "pts": flat(piece)})
        elif "building" in tags and len(pts) >= 4 and w["nodes"][0] == w["nodes"][-1]:
            poly = pts[:-1]
            if abs(polygon_area(poly)) < 15: continue          # trop petit : inutile en jeu
            if polygon_area(poly) < 0: poly.reverse()          # toujours anti-horaire
            cx = sum(p[0] for p in poly) / len(poly); cz = sum(p[1] for p in poly) / len(poly)
            if abs(cx) > radius + 80.0 or abs(cz) > radius + 80.0: continue
            buildings.append((cx * cx + cz * cz, {"h": round(building_height(w), 1), "pts": flat(poly)}))

    buildings.sort(key=lambda b: b[0])                           # garde les plus proches du centre
    buildings = [b for _, b in buildings[:args.max_buildings]]

    pois = []
    for p in poi_nodes:
        x, zz = proj.xz(p["lat"], p["lon"])
        if abs(x) > radius or abs(zz) > radius: continue
        for key, values, kind in POI_KINDS:
            if p["tags"].get(key) in values:
                name = p["tags"].get("name", "") if kind in PUBLIC_NAME_KINDS else ""
                pois.append({"name": name, "kind": kind, "x": round(x, 1), "z": round(zz, 1)})
                break

    # Repères posés à la main dans zones.json (approche hybride) : ex. un aéroport cartographié comme une zone dans OSM
    for e in z.get("extra_pois", []):
        snap = e.get("snap_to")
        if snap:
            target = next((q for q in pois if q["kind"] == snap), None)
            if target is None: continue
            pois.append({"name": e.get("name", ""), "kind": e["kind"], "x": target["x"], "z": target["z"]})
            continue
        x, zz = proj.xz(e["lat"], e["lon"])
        if abs(x) <= radius and abs(zz) <= radius:
            pois.append({"name": e.get("name", ""), "kind": e["kind"], "x": round(x, 1), "z": round(zz, 1)})

    return {"id": z["id"], "name": z["name"], "lat": z["lat"], "lon": z["lon"], "radius": radius,
            "originX": round(-origin[0], 1), "originZ": round(-origin[1], 1),   # position réelle de la zone par rapport à la Gare
            "attribution": "© contributeurs OpenStreetMap (ODbL)",
            "roads": roads, "buildings": buildings, "pois": pois}


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--input", required=True)
    ap.add_argument("--zone", required=True)
    ap.add_argument("--zones", default="zones.json")
    ap.add_argument("--out", required=True)
    ap.add_argument("--radius", type=float, default=0)
    ap.add_argument("--max-buildings", type=int, default=1500, help="plafond pour tenir sur mobile")
    a = ap.parse_args()
    data = convert(a)
    with open(a.out, "w", encoding="utf-8") as f: json.dump(data, f, ensure_ascii=False, separators=(",", ":"))
    print(f"{data['name']}: {len(data['roads'])} routes, {len(data['buildings'])} immeubles, {len(data['pois'])} POI -> {a.out}")

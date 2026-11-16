#!/usr/bin/env python3
"""
Calcule la disposition du monde de Taxi Kin : position (compressée) de chaque zone dans un repère global et corridors qui les relient.
Écrit Assets/Resources/Zones/world.json. À relancer après tout changement de zones.json ou des fichiers de zone.

Principe : chaque zone garde sa direction réelle par rapport à sa voisine, mais l'espace entre elles est remplacé par un corridor
de longueur fixe. Le corridor relie l'extrémité d'une route principale de la zone A (vers B) à l'extrémité d'une route principale
de la zone B (vers A) : la circulation est continue. Repère global : +x = est, +z = nord, origine = centre de la première zone.

Usage : python3 Tools/build_world.py [--report]
"""
import argparse, json, math, os, sys

HERE = os.path.dirname(os.path.abspath(__file__))
ZONES_DIR = os.path.join(HERE, "..", "Assets", "Resources", "Zones")
MAX_KIND_MAJOR = 3          # 0 motorway, 1 trunk, 2 primary, 3 secondary
SQUARE_MARGIN = 100.0       # le sol d'une zone dépasse son rayon de 100 m (voir ZoneLoader)

# Trajets des missions (zone, type de repère ; « » = centre de la zone), pour le rapport de durées
MISSIONS = [
    (2, "Évite les embouteillages", "wewa", ("gare_centrale", ""), ("kintambo", "")),
    (4, "Le client pressé", "wewa", ("matonge", ""), ("ndjili", "aeroport")),
    (5, "Débloque le taxi", "taxi", ("gare_centrale", "station"), ("kintambo", "")),
    (6, "Client VIP", "taxi", ("gare_centrale", ""), ("ndjili", "aeroport")),
    (7, "Le roi du 207", "bus", ("gare_centrale", "station"), ("limete", "")),
    (9, "Course de wewa", "wewa", ("gare_centrale", "station"), ("matonge", "")),
    (10, "La grande course", "taxi", ("gare_centrale", "station"), ("ndjili", "aeroport")),
]
VMAX = {"wewa": 25.0, "taxi": 20.8, "bus": 13.9}   # m/s : 90, 75 et 50 km/h


def load_zone(zid):
    return json.load(open(os.path.join(ZONES_DIR, zid + ".json"), encoding="utf-8"))


def real_direction(za, zb):
    kx = 111320.0 * math.cos(math.radians(za["lat"])); kz = 110574.0
    dx, dz = (zb["lon"] - za["lon"]) * kx, (zb["lat"] - za["lat"]) * kz
    n = math.hypot(dx, dz)
    return dx / n, dz / n, n


def pick_gate(zone, u):
    """Sommet d'une route principale le plus avancé vers la direction u (puis meilleure classe, puis plus proche de l'axe)."""
    half = zone["radius"] + SQUARE_MARGIN - 20.0
    for max_kind in (MAX_KIND_MAJOR, 4):
        cands = []
        for r in zone["roads"]:
            if r["kind"] > max_kind: continue
            p = r["pts"]
            for i in range(0, len(p), 2):
                x, z = p[i], p[i + 1]
                if abs(x) > half or abs(z) > half: continue
                cands.append((x * u[0] + z * u[1], r["kind"], abs(-x * u[1] + z * u[0]), x, z))
        if cands: break
    if not cands: sys.exit("Aucune route principale dans la zone " + zone["id"])
    best = max(c[0] for c in cands)
    top = [c for c in cands if c[0] >= best - 60.0]
    top.sort(key=lambda c: (c[1], c[2]))
    return top[0][3], top[0][4]


def exit_distance(center, half, origin, u):
    """Distance le long de u, depuis 'origin', jusqu'à la sortie du carré (centre, demi-côté)."""
    ts = []
    for axis in (0, 1):
        if abs(u[axis]) < 1e-9: continue
        for side in (-1, 1):
            t = (center[axis] + side * half - origin[axis]) / u[axis]
            if t > 0: ts.append(t)
    return min(ts) if ts else 0.0


def overlaps(a, b):
    return abs(a["ox"] - b["ox"]) < a["half"] + b["half"] + 40 and abs(a["oz"] - b["oz"]) < a["half"] + b["half"] + 40


def build(zones_file, report):
    cfg = json.load(open(zones_file, encoding="utf-8"))
    zinfo = {z["id"]: z for z in cfg["zones"]}
    placed, order = {}, [cfg["zones"][0]["id"]]
    first = zinfo[order[0]]
    placed[first["id"]] = {"id": first["id"], "ox": 0.0, "oz": 0.0, "half": first["radius"] + SQUARE_MARGIN}
    corridors, links = [], list(cfg["links"])
    data = {zid: load_zone(zid) for zid in zinfo}

    progress = True
    while links and progress:
        progress = False
        for link in list(links):
            a, b = link["a"], link["b"]
            if (a in placed) == (b in placed): continue          # arbre : un seul bout est déjà placé
            if b in placed: a, b = b, a
            u = real_direction(zinfo[a], zinfo[b])
            ux = (u[0], u[1])
            gate_a = gate_local = pick_gate(data[a], ux)
            gate_b = pick_gate(data[b], (-ux[0], -ux[1]))
            pa = placed[a]
            ga = (pa["ox"] + gate_a[0], pa["oz"] + gate_a[1])
            length = float(link["length"])
            for _ in range(40):                                  # on allonge si deux zones se chevauchent
                gb = (ga[0] + ux[0] * length, ga[1] + ux[1] * length)
                cand = {"id": b, "ox": gb[0] - gate_b[0], "oz": gb[1] - gate_b[1], "half": zinfo[b]["radius"] + SQUARE_MARGIN}
                if not any(overlaps(cand, q) for q in placed.values()): break
                length += 200.0
            else:
                sys.exit(f"Chevauchement impossible à résoudre entre {a} et {b}")
            placed[b] = cand
            half_a, half_b = pa["half"], cand["half"]
            t_a = exit_distance((pa["ox"], pa["oz"]), half_a, ga, ux)
            t_b = exit_distance((cand["ox"], cand["oz"]), half_b, gb, (-ux[0], -ux[1]))
            corridors.append({
                "a": a, "b": b, "ax": round(ga[0], 1), "az": round(ga[1], 1), "bx": round(gb[0], 1), "bz": round(gb[1], 1),
                "length": round(length, 1), "width": cfg["corridor"]["roadWidth"], "stripHalfWidth": cfg["corridor"]["stripHalfWidth"],
                "wallStart": round(t_a, 1), "wallEnd": round(length - t_b, 1),
            })
            links.remove(link); progress = True
    if links: sys.exit("Liens non reliés (graphe non connexe) : " + str(links))

    world = {
        "attribution": "© contributeurs OpenStreetMap (ODbL)",
        "zones": [{"id": zid, "name": zinfo[zid]["name"], "originX": round(placed[zid]["ox"], 1), "originZ": round(placed[zid]["oz"], 1),
                   "radius": zinfo[zid]["radius"], "half": placed[zid]["half"], "pois": data[zid]["pois"]} for zid in placed],
        "corridors": corridors,
    }
    graph, moved = snap_to_roads(world, data)
    print(f"graphe routier : {len(graph.pos)} noeuds, {len(graph.sizes)} composantes (principale : {graph.sizes[graph.main] / len(graph.pos):.0%}) ; {moved} repères recalés sur la route")
    out = os.path.join(ZONES_DIR, "world.json")
    json.dump(world, open(out, "w", encoding="utf-8"), ensure_ascii=False, separators=(",", ":"))
    print(f"world.json : {len(world['zones'])} zones, {len(corridors)} corridors -> {out}")
    for c in corridors: print(f"  {c['a']} <-> {c['b']} : {c['length']:.0f} m (murs de {c['wallStart']:.0f} à {c['wallEnd']:.0f} m)")
    if report: print_report(world, data, graph)
    return world


# ---------------- Rapport : longueur des trajets de mission et durées conseillées ----------------
def zone_by_id(world): return {z["id"]: z for z in world["zones"]}


# ---------------- Graphe routier : composantes connexes, recalage des repères, plus courts chemins ----------------
import heapq

SNAP_MAX_KIND = 6          # on recale sur des routes « résidentielles » ou mieux (pas les ruelles de service)
SNAP_MAX_DIST = 150.0


class RoadGraph:
    def __init__(self, world, data):
        self.keys, self.pos, self.zone, self.kind, self.adj = {}, [], [], [], []
        for z in world["zones"]:
            for r in data[z["id"]]["roads"]:
                p = r["pts"]
                for i in range(0, len(p) - 2, 2):
                    a = self._node(z["originX"] + p[i], z["originZ"] + p[i + 1], z["id"], r["kind"])
                    b = self._node(z["originX"] + p[i + 2], z["originZ"] + p[i + 3], z["id"], r["kind"])
                    if a != b: self._link(a, b, r["kind"])
        for c in world["corridors"]:
            a, b = self.nearest(c["ax"], c["az"])[0], self.nearest(c["bx"], c["bz"])[0]
            self._link(a, b, -1, c["length"])
        self._components()

    def _node(self, x, z, zid, kind):
        k = (round(x * 10), round(z * 10))
        if k not in self.keys:
            self.keys[k] = len(self.pos); self.pos.append((x, z)); self.zone.append(zid); self.kind.append(kind); self.adj.append([])
        else:
            i = self.keys[k]; self.kind[i] = min(self.kind[i], kind)    # un carrefour prend la meilleure classe
        return self.keys[k]

    def _link(self, a, b, kind, length=None):
        L = length if length else math.dist(self.pos[a], self.pos[b])
        self.adj[a].append((b, L)); self.adj[b].append((a, L))

    def _components(self):
        self.comp = [-1] * len(self.pos); sizes = []
        for s in range(len(self.pos)):
            if self.comp[s] != -1: continue
            q, n = [s], 0; self.comp[s] = len(sizes)
            while q:
                u = q.pop(); n += 1
                for v, _ in self.adj[u]:
                    if self.comp[v] == -1: self.comp[v] = self.comp[s]; q.append(v)
            sizes.append(n)
        self.main = max(range(len(sizes)), key=lambda i: sizes[i]); self.sizes = sizes

    def nearest(self, x, z, main_only=False, max_kind=99):
        best, bd = None, 1e18
        for i, (px, pz) in enumerate(self.pos):
            if main_only and self.comp[i] != self.main: continue
            if self.kind[i] > max_kind: continue
            d = (px - x) ** 2 + (pz - z) ** 2
            if d < bd: bd, best = d, i
        return best, math.sqrt(bd) if best is not None else 1e18

    def path_length(self, s, t):
        dist, pq = {s: 0.0}, [(0.0, s)]
        while pq:
            d, u = heapq.heappop(pq)
            if u == t: return d
            if d > dist[u]: continue
            for v, L in self.adj[u]:
                nd = d + L
                if nd < dist.get(v, 1e18): dist[v] = nd; heapq.heappush(pq, (nd, v))
        return None


def snap_to_roads(world, data):
    """Recale les repères et le centre de chaque zone sur la route la plus proche de la composante principale
    (un repère qu'on ne peut pas atteindre en voiture ne sert à rien comme départ ou destination de mission)."""
    graph = RoadGraph(world, data)
    moved = 0
    for z in world["zones"]:
        def snap(local_x, local_z):
            i, d = graph.nearest(z["originX"] + local_x, z["originZ"] + local_z, True, SNAP_MAX_KIND)
            if i is None or d > SNAP_MAX_DIST:
                i, d = graph.nearest(z["originX"] + local_x, z["originZ"] + local_z, True)
            return round(graph.pos[i][0] - z["originX"], 1), round(graph.pos[i][1] - z["originZ"], 1), d
        z["centerX"], z["centerZ"], _ = snap(0.0, 0.0)
        for p in z["pois"]:
            nx, nz, d = snap(p["x"], p["z"])
            if d <= SNAP_MAX_DIST: p["x"], p["z"] = nx, nz; moved += 1
    return RoadGraph(world, data), moved


def poi_pos(world, zid, kind):
    z = zone_by_id(world)[zid]
    if kind:
        for p in z["pois"]:
            if p["kind"] == kind: return (z["originX"] + p["x"], z["originZ"] + p["z"])
    return (z["originX"] + z.get("centerX", 0.0), z["originZ"] + z.get("centerZ", 0.0))


def zone_path(world, za, zb):
    adj = {}
    for c in world["corridors"]:
        adj.setdefault(c["a"], []).append((c["b"], c)); adj.setdefault(c["b"], []).append((c["a"], c))
    prev, queue = {za: None}, [za]
    while queue:
        cur = queue.pop(0)
        for nxt, c in adj.get(cur, []):
            if nxt not in prev: prev[nxt] = (cur, c); queue.append(nxt)
    path, cur = [], zb
    while prev.get(cur): path.append(prev[cur]); cur = prev[cur][0]
    return list(reversed(path))   # [(zone précédente, corridor)]


def route_length(world, start, goal):
    """Longueur du trajet zone -> corridor -> zone ... (droites à l'intérieur de chaque zone)."""
    p0, p1 = poi_pos(world, *start), poi_pos(world, *goal)
    total, cur, zone = 0.0, p0, start[0]
    for prev_zone, c in zone_path(world, start[0], goal[0]):
        gate_here = (c["ax"], c["az"]) if c["a"] == prev_zone else (c["bx"], c["bz"])
        gate_there = (c["bx"], c["bz"]) if c["a"] == prev_zone else (c["ax"], c["az"])
        total += math.dist(cur, gate_here) + c["length"]
        cur = gate_there
    return total + math.dist(cur, p1)


def print_report(world, data, graph):
    print("\nTrajets de mission, DISTANCE ROUTIÈRE réelle (plus court chemin sur le réseau OSM) ; durée conseillée = longueur / (60 % de la vitesse max), arrondie à 30 s :")
    for num, title, veh, a, b in MISSIONS:
        pa, pb = poi_pos(world, *a), poi_pos(world, *b)
        L = graph.path_length(graph.nearest(*pa, True)[0], graph.nearest(*pb, True)[0])
        sec = L / (0.6 * VMAX[veh])
        print(f"  Mission {num:2d} {title:26s} {veh:5s} {L/1000:5.2f} km  -> {int(math.ceil(sec/30.0)*30):4d} s conseillées")


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--zones", default=os.path.join(HERE, "zones.json"))
    ap.add_argument("--report", action="store_true")
    a = ap.parse_args()
    build(a.zones, a.report)

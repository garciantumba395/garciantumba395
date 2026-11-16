#!/usr/bin/env python3
"""
Simulation HORS UNITY de la loi de pilotage des véhicules d'IA (VehicleAI.cs) sur les vraies routes de Kinshasa.
Le véhicule suit le même modèle que VehicleController : accélération et freinage plafonnés, cap tournant à
steer * turnRate * min(|v|/4, 1) degrés par seconde. On mesure l'écart à la route (cross-track) et la vitesse moyenne.
Les constantes DOIVENT rester identiques à celles de VehicleAI.cs.

Usage : python3 Tools/verify/sim_ai.py
"""
import heapq, json, math, os, sys

HERE = os.path.dirname(os.path.abspath(__file__))
ZONES = os.path.join(HERE, "..", "..", "Assets", "Resources", "Zones")

# --- constantes de la loi de pilotage (identiques à VehicleAI.cs) ---
LOOK_MIN, LOOK_MAX, LOOK_BASE, LOOK_GAIN = 5.0, 22.0, 4.0, 0.7
STEER_DEG = 30.0                 # écart de cap qui donne la pleine direction
CORNER_AHEAD_BASE, CORNER_AHEAD_GAIN = 25.0, 1.2
CORNER_MIN_FACTOR, CORNER_FROM_DEG, CORNER_TO_DEG = 0.30, 15.0, 100.0
THROTTLE_RANGE, BRAKE_RANGE = 2.0, 3.0
LANE_OFFSET = 2.2

# vitesse limite par classe de route (m/s) pour le trafic
LIMIT = {0: 14.0, 1: 14.0, 2: 11.0, 3: 9.0, 4: 8.0, 5: 7.0, 6: 6.0, 7: 5.0, 8: 4.0, -1: 14.0}
VEH = {"wewa": dict(vmax=25.0, acc=11.0, brk=18.0, turn=130.0), "taxi": dict(vmax=20.8, acc=8.0, brk=15.0, turn=90.0),
       "bus": dict(vmax=13.9, acc=4.0, brk=10.0, turn=55.0)}


def load_graph():
    w = json.load(open(os.path.join(ZONES, "world.json"), encoding="utf-8"))
    keys, pos, adj = {}, [], []
    def node(x, z):
        k = (round(x * 10), round(z * 10))
        if k not in keys: keys[k] = len(pos); pos.append((x, z)); adj.append([])
        return keys[k]
    for z in w["zones"]:
        d = json.load(open(os.path.join(ZONES, z["id"] + ".json"), encoding="utf-8"))
        for r in d["roads"]:
            p = r["pts"]
            for i in range(0, len(p) - 2, 2):
                a, b = node(z["originX"] + p[i], z["originZ"] + p[i + 1]), node(z["originX"] + p[i + 2], z["originZ"] + p[i + 3])
                if a != b:
                    L = math.dist(pos[a], pos[b]); adj[a].append((b, L, r["kind"], r["width"])); adj[b].append((a, L, r["kind"], r["width"]))
    def nearest(x, z):
        return min(range(len(pos)), key=lambda i: (pos[i][0] - x) ** 2 + (pos[i][1] - z) ** 2)
    for c in w["corridors"]:
        a, b = nearest(c["ax"], c["az"]), nearest(c["bx"], c["bz"])
        adj[a].append((b, c["length"], -1, 16)); adj[b].append((a, c["length"], -1, 16))
    return w, pos, adj, nearest


def astar(pos, adj, s, t):
    g, prev, pq = {s: 0.0}, {}, [(math.dist(pos[s], pos[t]), s)]
    while pq:
        _, u = heapq.heappop(pq)
        if u == t: break
        for v, L, k, wd in adj[u]:
            ng = g[u] + L
            if ng < g.get(v, 1e18): g[v] = ng; prev[v] = (u, k, wd); heapq.heappush(pq, (ng + math.dist(pos[v], pos[t]), v))
    path, kinds, widths, n = [pos[t]], [], [], t
    while n != s:
        u, k, wd = prev[n]; path.append(pos[u]); kinds.append(k); widths.append(wd); n = u
    return path[::-1], kinds[::-1], widths[::-1]


def offset_polyline(pts, off):
    """Décale la polyligne vers la DROITE (conduite à droite) : même convention que le jeu (droite = (dz, -dx))."""
    out = []
    for i, p in enumerate(pts):
        a = pts[max(0, i - 1)]; b = pts[min(len(pts) - 1, i + 1)]
        dx, dz = b[0] - a[0], b[1] - a[1]; n = math.hypot(dx, dz) or 1.0
        out.append((p[0] + dz / n * off, p[1] - dx / n * off))
    return out


class Path:
    def __init__(self, pts, kinds):
        self.p, self.k = pts, kinds
        self.cum = [0.0]
        for i in range(1, len(pts)): self.cum.append(self.cum[-1] + math.dist(pts[i - 1], pts[i]))
        self.total = self.cum[-1]
    def project(self, pos, hint):
        best, bi, bs = 1e18, hint, 0.0
        for i in range(max(0, hint - 3), min(len(self.p) - 1, hint + 40)):
            a, b = self.p[i], self.p[i + 1]; dx, dz = b[0] - a[0], b[1] - a[1]; L2 = dx * dx + dz * dz or 1e-9
            t = max(0.0, min(1.0, ((pos[0] - a[0]) * dx + (pos[1] - a[1]) * dz) / L2))
            q = (a[0] + dx * t, a[1] + dz * t); d = math.dist(pos, q)
            if d < best: best, bi, bs = d, i, self.cum[i] + t * math.sqrt(L2)
        return best, bi, bs
    def point_at(self, s):
        s = min(max(s, 0.0), self.total)
        lo, hi = 0, len(self.cum) - 1
        while hi - lo > 1:
            m = (lo + hi) // 2
            if self.cum[m] <= s: lo = m
            else: hi = m
        a, b = self.p[lo], self.p[lo + 1]; L = self.cum[lo + 1] - self.cum[lo] or 1e-9; t = (s - self.cum[lo]) / L
        return (a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t), lo
    def heading_at(self, s):
        _, i = self.point_at(s); a, b = self.p[i], self.p[i + 1]
        return math.degrees(math.atan2(b[0] - a[0], b[1] - a[1]))


def angle_diff(a, b): return (b - a + 180.0) % 360.0 - 180.0


# Adhérence : wet = 0 (sec), 0,5 (pluie légère), 1 (pluie forte). Mêmes formules que VehicleController.cs / WeatherManager.cs.
def road_grip(wet): return 1.0 - 0.4 * wet                     # 1,0 / 0,8 / 0,6


def simulate(path, veh, limit_factor, use_limits, wet=0.0, dt=0.02, max_t=3000.0):
    v = VEH[veh]; pos = path.p[0]; heading = path.heading_at(0.0)
    grip_road = road_grip(wet)
    lat_grip = 30.0 * grip_road * grip_road                    # m/s² d'adhérence latérale
    fwd_speed, lat_speed, t, idx = 0.0, 0.0, 0.0, 0
    vx_w = vz_w = 0.0
    max_err, sum_v, n, off_road = 0.0, 0.0, 0, 0
    rain_factor = 0.5 + 0.5 * grip_road                        # l'IA ralentit sous la pluie
    while t < max_t:
        err, idx, s = path.project(pos, idx)
        if s >= path.total - 8.0: break
        speed = abs(fwd_speed)
        L = min(max(LOOK_BASE + LOOK_GAIN * speed, LOOK_MIN), LOOK_MAX)
        tgt, _ = path.point_at(s + L)
        dx, dz = tgt[0] - pos[0], tgt[1] - pos[1]
        desired = math.degrees(math.atan2(dx, dz)); steer = max(-1.0, min(1.0, angle_diff(heading, desired) / STEER_DEG))
        ahead = CORNER_AHEAD_BASE + CORNER_AHEAD_GAIN * speed
        turn = abs(angle_diff(path.heading_at(s + 3.0), path.heading_at(s + ahead)))
        f = 1.0 - (1.0 - CORNER_MIN_FACTOR) * max(0.0, min(1.0, (turn - CORNER_FROM_DEG) / (CORNER_TO_DEG - CORNER_FROM_DEG)))
        vlimit = v["vmax"] * limit_factor * rain_factor
        if use_limits:
            _, i = path.point_at(s); k = path.k[min(i, len(path.k) - 1)]; vlimit = min(vlimit, LIMIT.get(k, 6.0) * limit_factor * rain_factor)
        e = vlimit * f - speed
        throttle = min(e / THROTTLE_RANGE, 1.0) if e > 0 else 0.0
        brake = min(-e / BRAKE_RANGE, 1.0) if e < 0 else 0.0
        # --- VehicleController : vitesse portée, adhérence latérale limitée ---
        hr = math.radians(heading)
        fx, fz = math.sin(hr), math.cos(hr); rx, rz = math.cos(hr), -math.sin(hr)
        # composantes actuelles de la vitesse monde par rapport au cap
        fwd_speed = fwd_speed * 0 + (vx_w * fx + vz_w * fz) if t > 0 else fwd_speed
        lat_speed = (vx_w * rx + vz_w * rz) if t > 0 else 0.0
        fwd_speed += throttle * v["acc"] * (0.5 + 0.5 * grip_road) * dt
        if brake > 0: fwd_speed = math.copysign(max(0.0, abs(fwd_speed) - brake * v["brk"] * grip_road * dt), fwd_speed)
        if throttle <= 0 and brake <= 0: fwd_speed = math.copysign(max(0.0, abs(fwd_speed) - 1.5 * dt), fwd_speed)
        fwd_speed = max(-v["vmax"] * 0.4, min(v["vmax"], fwd_speed))
        lat_speed = math.copysign(max(0.0, abs(lat_speed) - lat_grip * dt), lat_speed)
        vx_w, vz_w = fx * fwd_speed + rx * lat_speed, fz * fwd_speed + rz * lat_speed
        yaw_rate = steer * v["turn"] * (0.7 + 0.3 * grip_road) * min(abs(fwd_speed) / 4.0, 1.0)
        heading += yaw_rate * dt
        pos = (pos[0] + vx_w * dt, pos[1] + vz_w * dt)
        max_err = max(max_err, err); sum_v += abs(fwd_speed); n += 1; t += dt
        if err > 4.0: off_road += 1
    return dict(time=t, dist=path.total, mean=sum_v / max(n, 1), max_err=max_err, off_ratio=off_road / max(n, 1), finished=t < max_t)


if __name__ == "__main__":
    w, pos, adj, nearest = load_graph()
    zones = {z["id"]: z for z in w["zones"]}
    def poi(zid, kind):
        z = zones[zid]
        for p in z["pois"]:
            if p["kind"] == kind: return (z["originX"] + p["x"], z["originZ"] + p["z"])
        return (z["originX"] + z.get("centerX", 0), z["originZ"] + z.get("centerZ", 0))
    routes = [("Mission 9 : Gare → Matonge", poi("gare_centrale", "station"), poi("matonge", "")),
              ("Mission 2 : Gare → Kintambo", poi("gare_centrale", ""), poi("kintambo", ""))]
    bad = False
    print(f"{'trajet':28} {'véhicule':12} {'météo':13} {'durée':>6} {'v. moy.':>9} {'écart max':>10} {'hors route':>10}")
    for name, a, b in routes:
        pts, kinds, widths = astar(pos, adj, nearest(*a), nearest(*b))
        racing = Path(pts, kinds)                                   # course : on suit l'axe de la route
        traffic = Path(offset_polyline(pts, LANE_OFFSET), kinds)    # trafic : voie de droite
        for label, path, veh, skill, lim in [("course", racing, "wewa", 0.90, False), ("course", racing, "taxi", 0.90, False),
                                              ("trafic", traffic, "taxi", 0.90, True)]:
            for wname, wet in (("sec", 0.0), ("pluie légère", 0.5), ("pluie forte", 1.0)):
                r = simulate(path, veh, skill, lim, wet)
                ok = r["finished"] and r["max_err"] < 9.0 and r["off_ratio"] < 0.03
                bad |= not ok
                print(f"{name:28} {veh+'/'+label:12} {wname:13} {r['time']:5.0f}s {r['mean']*3.6:6.1f}km/h {r['max_err']:8.1f} m {r['off_ratio']*100:9.1f}% {'OK' if ok else 'À VÉRIFIER'}")
    sys.exit(1 if bad else 0)

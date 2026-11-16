using System.Collections.Generic;
using UnityEngine;

namespace TaxiKin.Map
{
    /// <summary>
    /// Réseau routier de TOUTES les zones (même non chargées), construit depuis les fichiers de zone et les corridors.
    /// Sert au trafic (promenade aléatoire, apparition autour du joueur) et à l'IA de course (plus court chemin A*).
    /// Un carrefour = deux routes qui partagent exactement le même point (arrondi à 10 cm, comme dans les données OSM).
    /// </summary>
    public class RoadGraph
    {
        public struct Edge { public int to; public float length; public sbyte kind; }

        private const float Cell = 100f;

        private readonly List<Vector3> pos = new List<Vector3>();
        private readonly List<List<Edge>> adj = new List<List<Edge>>();
        private readonly Dictionary<long, int> keys = new Dictionary<long, int>();
        private readonly Dictionary<long, List<int>> grid = new Dictionary<long, List<int>>();
        private int[] component;
        private int mainComponent;

        // Vitesse limite par classe de route (m/s) : motorway, trunk, primary, secondary, tertiary, unclassified, residential, living_street, service
        private static readonly float[] Limits = { 14f, 14f, 11f, 9f, 8f, 7f, 6f, 5f, 4f };

        public int Count => pos.Count;
        public Vector3 PositionOf(int i) => pos[i];
        public IReadOnlyList<Edge> EdgesOf(int i) => adj[i];
        public bool IsMain(int i) => component != null && component[i] == mainComponent;

        /// <summary>Vitesse limite (m/s) d'une classe de route ; -1 = corridor.</summary>
        public static float SpeedLimit(sbyte kind) => kind < 0 ? 14f : kind < Limits.Length ? Limits[kind] : 4f;

        // ---------- Construction ----------

        private static long Key(float x, float z) => (Mathf.RoundToInt(x * 10f) + 400000L) * 1000000L + (Mathf.RoundToInt(z * 10f) + 400000L);

        private int Node(float x, float z)
        {
            long k = Key(x, z);
            if (keys.TryGetValue(k, out int i)) return i;
            i = pos.Count;
            keys[k] = i;
            pos.Add(new Vector3(x, 0f, z));
            adj.Add(new List<Edge>());
            return i;
        }

        private void Link(int a, int b, sbyte kind, float length)
        {
            adj[a].Add(new Edge { to = b, length = length, kind = kind });
            adj[b].Add(new Edge { to = a, length = length, kind = kind });
        }

        /// <summary>Ajoute les routes d'une zone (coordonnées locales + position de la zone dans le monde).</summary>
        public void AddZone(WorldZone zone, ZoneData data)
        {
            if (data.roads == null) return;
            foreach (var r in data.roads)
                for (int i = 0; i + 3 < r.pts.Length; i += 2)
                {
                    int a = Node(zone.originX + r.pts[i], zone.originZ + r.pts[i + 1]);
                    int b = Node(zone.originX + r.pts[i + 2], zone.originZ + r.pts[i + 3]);
                    if (a != b) Link(a, b, (sbyte)r.kind, Vector3.Distance(pos[a], pos[b]));
                }
        }

        /// <summary>Relie les deux extrémités d'un corridor aux routes les plus proches (elles coïncident avec des sommets de route).</summary>
        public void AddCorridor(WorldCorridor c)
        {
            int a = NearestLinear(new Vector3(c.ax, 0f, c.az)), b = NearestLinear(new Vector3(c.bx, 0f, c.bz));
            if (a >= 0 && b >= 0 && a != b) Link(a, b, -1, c.length);
        }

        private int NearestLinear(Vector3 p)
        {
            int best = -1; float bd = float.MaxValue;
            for (int i = 0; i < pos.Count; i++)
            {
                float d = (pos[i].x - p.x) * (pos[i].x - p.x) + (pos[i].z - p.z) * (pos[i].z - p.z);
                if (d < bd) { bd = d; best = i; }
            }
            return best;
        }

        /// <summary>À appeler une fois toutes les zones ajoutées : composantes connexes et grille de recherche.</summary>
        public void Finish()
        {
            component = new int[pos.Count];
            for (int i = 0; i < component.Length; i++) component[i] = -1;
            var sizes = new List<int>();
            var stack = new Stack<int>();
            for (int s = 0; s < pos.Count; s++)
            {
                if (component[s] != -1) continue;
                int id = sizes.Count, n = 0;
                component[s] = id; stack.Push(s);
                while (stack.Count > 0)
                {
                    int u = stack.Pop(); n++;
                    foreach (var e in adj[u]) if (component[e.to] == -1) { component[e.to] = id; stack.Push(e.to); }
                }
                sizes.Add(n);
            }
            int best = 0;
            for (int i = 1; i < sizes.Count; i++) if (sizes[i] > sizes[best]) best = i;
            mainComponent = best;

            for (int i = 0; i < pos.Count; i++)
            {
                long k = CellKey(pos[i].x, pos[i].z);
                if (!grid.TryGetValue(k, out var list)) grid[k] = list = new List<int>();
                list.Add(i);
            }
        }

        // ---------- Recherches ----------

        private static long CellKey(float x, float z) => (Mathf.FloorToInt(x / Cell) + 4000L) * 100000L + (Mathf.FloorToInt(z / Cell) + 4000L);

        /// <summary>Nœud le plus proche (−1 si aucun dans la portée). 'mainOnly' ignore les petits réseaux isolés.</summary>
        public int Nearest(Vector3 p, bool mainOnly, float maxDistance)
        {
            int best = -1; float bd = maxDistance * maxDistance;
            int cx = Mathf.FloorToInt(p.x / Cell), cz = Mathf.FloorToInt(p.z / Cell);
            int rings = Mathf.CeilToInt(maxDistance / Cell) + 1;
            for (int r = 0; r <= rings; r++)
            {
                for (int dx = -r; dx <= r; dx++)
                    for (int dz = -r; dz <= r; dz++)
                    {
                        if (Mathf.Max(Mathf.Abs(dx), Mathf.Abs(dz)) != r) continue;       // seulement l'anneau courant
                        if (!grid.TryGetValue((cx + dx + 4000L) * 100000L + (cz + dz + 4000L), out var list)) continue;
                        foreach (int i in list)
                        {
                            if (mainOnly && !IsMain(i)) continue;
                            float d = (pos[i].x - p.x) * (pos[i].x - p.x) + (pos[i].z - p.z) * (pos[i].z - p.z);
                            if (d < bd) { bd = d; best = i; }
                        }
                    }
                if (best >= 0 && Mathf.Sqrt(bd) <= r * Cell) break;     // l'anneau suivant ne peut pas être plus proche
            }
            return best;
        }

        /// <summary>Nœuds du réseau principal dont la distance à 'center' est entre minR et maxR (pour faire apparaître le trafic).</summary>
        public void NodesInRing(Vector3 center, float minR, float maxR, List<int> result)
        {
            result.Clear();
            int cx = Mathf.FloorToInt(center.x / Cell), cz = Mathf.FloorToInt(center.z / Cell);
            int rings = Mathf.CeilToInt(maxR / Cell);
            float min2 = minR * minR, max2 = maxR * maxR;
            for (int dx = -rings; dx <= rings; dx++)
                for (int dz = -rings; dz <= rings; dz++)
                {
                    if (!grid.TryGetValue((cx + dx + 4000L) * 100000L + (cz + dz + 4000L), out var list)) continue;
                    foreach (int i in list)
                    {
                        if (!IsMain(i)) continue;
                        float d = (pos[i].x - center.x) * (pos[i].x - center.x) + (pos[i].z - center.z) * (pos[i].z - center.z);
                        if (d >= min2 && d <= max2) result.Add(i);
                    }
                }
        }

        /// <summary>Voisin au hasard (évite le demi-tour sauf en cul-de-sac ; préfère les grandes routes).</summary>
        public int RandomNeighbor(int node, int cameFrom, System.Random rnd, out sbyte kind)
        {
            var edges = adj[node];
            float total = 0f;
            foreach (var e in edges) if (e.to != cameFrom) total += Weight(e.kind);
            kind = 8;
            if (total <= 0f)
            {
                if (edges.Count == 0) return -1;
                var back = edges[0]; kind = back.kind; return back.to;       // cul-de-sac : demi-tour
            }
            float pick = (float)rnd.NextDouble() * total;
            foreach (var e in edges)
            {
                if (e.to == cameFrom) continue;
                pick -= Weight(e.kind);
                if (pick <= 0f) { kind = e.kind; return e.to; }
            }
            var last = edges[edges.Count - 1]; kind = last.kind; return last.to;
        }

        private static float Weight(sbyte kind) => kind < 0 ? 3f : kind <= 3 ? 3f : kind <= 5 ? 2f : 1f;

        // ---------- Plus court chemin (A*) ----------

        /// <summary>Plus court chemin routier. Remplit 'points' (positions) et 'kinds' (classe de la route entre deux points consécutifs).</summary>
        public bool FindPath(int from, int to, List<Vector3> points, List<sbyte> kinds)
        {
            points.Clear(); kinds.Clear();
            if (from < 0 || to < 0) return false;
            int n = pos.Count;
            var g = new float[n]; var prev = new int[n]; var prevKind = new sbyte[n]; var closed = new bool[n];
            for (int i = 0; i < n; i++) { g[i] = float.MaxValue; prev[i] = -1; }
            g[from] = 0f;

            var heap = new List<KeyValuePair<float, int>>();
            Push(heap, Heuristic(from, to), from);
            while (heap.Count > 0)
            {
                int u = Pop(heap);
                if (closed[u]) continue;
                closed[u] = true;
                if (u == to) break;
                foreach (var e in adj[u])
                {
                    float ng = g[u] + e.length;
                    if (ng < g[e.to]) { g[e.to] = ng; prev[e.to] = u; prevKind[e.to] = e.kind; Push(heap, ng + Heuristic(e.to, to), e.to); }
                }
            }
            if (g[to] == float.MaxValue) return false;

            for (int node = to; node != -1; node = prev[node])
            {
                points.Add(pos[node]);
                if (node != from) kinds.Add(prevKind[node]);
            }
            points.Reverse(); kinds.Reverse();
            return true;
        }

        private float Heuristic(int a, int b) => Vector3.Distance(pos[a], pos[b]);

        // Tas binaire minimal (Unity 2022 n'a pas System.Collections.Generic.PriorityQueue)
        private static void Push(List<KeyValuePair<float, int>> h, float priority, int node)
        {
            h.Add(new KeyValuePair<float, int>(priority, node));
            int i = h.Count - 1;
            while (i > 0)
            {
                int parent = (i - 1) / 2;
                if (h[parent].Key <= h[i].Key) break;
                var tmp = h[parent]; h[parent] = h[i]; h[i] = tmp;
                i = parent;
            }
        }

        private static int Pop(List<KeyValuePair<float, int>> h)
        {
            int top = h[0].Value;
            int last = h.Count - 1;
            h[0] = h[last]; h.RemoveAt(last);
            int i = 0;
            while (true)
            {
                int l = 2 * i + 1, r = l + 1, m = i;
                if (l < h.Count && h[l].Key < h[m].Key) m = l;
                if (r < h.Count && h[r].Key < h[m].Key) m = r;
                if (m == i) break;
                var tmp = h[m]; h[m] = h[i]; h[i] = tmp;
                i = m;
            }
            return top;
        }
    }
}

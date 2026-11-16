using System.Collections.Generic;
using UnityEngine;
using UnityEngine.Rendering;

namespace TaxiKin.Map
{
    /// <summary>
    /// Fabrique deux maillages combinés par zone (routes, immeubles) : 2 draw calls au lieu de milliers,
    /// idéal pour un Android modeste. Les routes et les murs portent des coordonnées de texture (asphalte avec marquages, façades à fenêtres) ; la couleur du matériau sert de teinte.
    /// </summary>
    public static class ZoneMeshBuilder
    {
        private const float RoadY = 0.03f;

        /// <summary>Un tronçon de route (une route est découpée en tronçons pour pouvoir être répartie par blocs).</summary>
        public struct RoadSegment { public Vector2 a, b; public float width; public int kind; }

        /// <summary>Clé de bloc (grille carrée) pour un point en mètres.</summary>
        public static long CellKey(float x, float z, float cellSize)
        {
            long cx = Mathf.FloorToInt(x / cellSize) + 32768;
            long cz = Mathf.FloorToInt(z / cellSize) + 32768;
            return (cx << 16) | cz;
        }

        public static Mesh BuildRoads(List<RoadSegment> segments)
        {
            var v = new List<Vector3>(); var t = new List<int>(); var uv = new List<Vector2>();
            if (segments != null)
                foreach (var seg in segments)
                {
                    float y = RoadY + seg.kind * 0.002f;   // les grandes routes passent au-dessus (évite le scintillement)
                    float half = seg.width * 0.5f;
                    Vector2 d = seg.b - seg.a;
                    float len = d.magnitude;
                    if (len < 0.01f) continue;
                    Vector2 n = new Vector2(-d.y, d.x) / len * half;   // côté gauche
                    int b = v.Count;
                    v.Add(new Vector3(seg.a.x + n.x, y, seg.a.y + n.y));   // a
                    v.Add(new Vector3(seg.b.x + n.x, y, seg.b.y + n.y));   // d
                    v.Add(new Vector3(seg.b.x - n.x, y, seg.b.y - n.y));   // c
                    v.Add(new Vector3(seg.a.x - n.x, y, seg.a.y - n.y));   // b
                    t.Add(b); t.Add(b + 1); t.Add(b + 2);
                    t.Add(b); t.Add(b + 2); t.Add(b + 3);
                    // Texture d'asphalte : u en travers (lignes de rive aux bords), v le long de la route (motif de 12 m).
                    // Les petites rues (< 6 m) n'affichent que la partie centrale : pas de lignes de rive.
                    float u0 = seg.width >= 6f ? 0f : 0.1f, u1 = seg.width >= 6f ? 1f : 0.9f, vEnd = len / 12f;
                    uv.Add(new Vector2(u0, 0f)); uv.Add(new Vector2(u0, vEnd)); uv.Add(new Vector2(u1, vEnd)); uv.Add(new Vector2(u1, 0f));
                }
            return v.Count == 0 ? null : Finish("Routes", v, t, uv);
        }

        // Sous-maillages des immeubles : 0 étages (façade), 1 et 2 rez-de-chaussée commerçant (deux variantes), 3 toits (palette de couleurs)
        public const int BuildingSubMeshes = 4;
        private const float FloorHeight = 3.2f;
        private const int RoofColors = 8;

        /// <summary>Hachage stable d'une position : le même bâtiment a toujours le même aspect.</summary>
        private static int Hash(float x, float z) =>
            (Mathf.RoundToInt(x * 10f) * 73856093 ^ Mathf.RoundToInt(z * 10f) * 19349663) & 0x7fffffff;

        public static Mesh BuildBuildings(List<ZoneBuilding> buildings)
        {
            var v = new List<Vector3>(); var uv = new List<Vector2>();
            var subs = new List<int>[BuildingSubMeshes];
            for (int i = 0; i < subs.Length; i++) subs[i] = new List<int>();
            if (buildings != null)
                foreach (var bd in buildings) AddBuilding(bd, v, uv, subs);
            if (v.Count == 0) return null;
            var m = new Mesh { name = "Immeubles", indexFormat = IndexFormat.UInt32 };
            m.SetVertices(v);
            m.SetUVs(0, uv);
            m.subMeshCount = BuildingSubMeshes;
            for (int i = 0; i < subs.Length; i++) m.SetTriangles(subs[i], i);
            m.RecalculateNormals();
            m.RecalculateBounds();
            return m;
        }

        private static void AddBuilding(ZoneBuilding bd, List<Vector3> v, List<Vector2> uv, List<int>[] subs)
        {
            int n = bd.pts.Length / 2;
            if (n < 3) return;
            var p = new List<Vector2>(n);
            float cx = 0f, cz = 0f;
            for (int i = 0; i < n; i++) { p.Add(new Vector2(bd.pts[i * 2], bd.pts[i * 2 + 1])); cx += p[i].x; cz += p[i].y; }
            int hash = Hash(cx / n, cz / n);

            // Murs (l'emprise est anti-horaire : l'extérieur est à droite de chaque arête)
            for (int i = 0; i < n; i++)
            {
                Vector2 a = p[i], b = p[(i + 1) % n];
                float edge = (b - a).magnitude;
                float uEnd = edge / 6f;      // un motif = 6 m de large
                // Une façade sur trois (au moins 4 m de large) porte une devanture commerçante au rez-de-chaussée
                bool shop = edge >= 4f && bd.h >= 2.5f && ((hash >> 3) + i) % 3 != 0;
                if (!shop)
                {
                    AddWall(v, uv, subs[0], a, b, 0f, bd.h, 0f, bd.h / FloorHeight, uEnd);
                    continue;
                }
                float g = Mathf.Min(FloorHeight, bd.h);
                AddWall(v, uv, subs[1 + (hash >> 5) % 2], a, b, 0f, g, 0f, g / FloorHeight, uEnd);     // devanture
                if (bd.h - g > 0.3f) AddWall(v, uv, subs[0], a, b, g, bd.h, 0f, (bd.h - g) / FloorHeight, uEnd);   // étages
            }

            // Toit : couleur tirée d'une palette (un point de la texture par bâtiment), triangulation « ear clipping »
            int roofBase = v.Count;
            var roofUv = new Vector2(((hash >> 7) % RoofColors + 0.5f) / RoofColors, 0.5f);
            for (int i = 0; i < n; i++) { v.Add(new Vector3(p[i].x, bd.h, p[i].y)); uv.Add(roofUv); }
            var tris = new List<int>();
            Triangulate(p, tris);
            for (int i = 0; i + 2 < tris.Count; i += 3)
            {   // inversion de l'ordre : face visible depuis le dessus
                subs[3].Add(roofBase + tris[i]); subs[3].Add(roofBase + tris[i + 2]); subs[3].Add(roofBase + tris[i + 1]);
            }
        }

        private static void AddWall(List<Vector3> v, List<Vector2> uv, List<int> t, Vector2 a, Vector2 b, float y0, float y1, float v0, float v1, float uEnd)
        {
            int k = v.Count;
            v.Add(new Vector3(a.x, y0, a.y)); v.Add(new Vector3(a.x, y1, a.y));
            v.Add(new Vector3(b.x, y1, b.y)); v.Add(new Vector3(b.x, y0, b.y));
            uv.Add(new Vector2(0f, v0)); uv.Add(new Vector2(0f, v1)); uv.Add(new Vector2(uEnd, v1)); uv.Add(new Vector2(uEnd, v0));
            t.Add(k); t.Add(k + 1); t.Add(k + 2);
            t.Add(k); t.Add(k + 2); t.Add(k + 3);
        }

        // ---------- Décor : arbres et lampadaires le long des grandes routes ----------

        /// <summary>Deux sous-maillages : 0 = troncs et poteaux, 1 = feuillages. Positions tirées des coordonnées (stables d'un chargement à l'autre).</summary>
        public static Mesh BuildDecor(List<RoadSegment> segments)
        {
            if (segments == null) return null;
            var v = new List<Vector3>(); var uv = new List<Vector2>();
            var posts = new List<int>(); var leaves = new List<int>();
            foreach (var seg in segments)
            {
                if (seg.width < 7f) continue;                 // seulement les grandes voies (trottoirs)
                Vector2 d = seg.b - seg.a;
                float len = d.magnitude;
                if (len < 12f) continue;
                Vector2 dir = d / len;
                Vector2 right = new Vector2(dir.y, -dir.x);
                int h = Hash(seg.a.x, seg.a.y);
                float half = seg.width * 0.5f;
                if (h % 3 == 0)    // lampadaire
                {
                    Vector2 pos = seg.a + dir * (len * 0.5f) + right * (half + 1.2f) * ((h >> 4) % 2 == 0 ? 1f : -1f);
                    AddBox(v, uv, posts, pos, 0f, 6f, new Vector2(0.18f, 0.18f), 0f);
                    AddBox(v, uv, posts, pos - dir * 0.0f, 5.8f, 0.2f, new Vector2(0.5f, 1.4f), Mathf.Atan2(dir.x, dir.y) * Mathf.Rad2Deg);
                }
                if (h % 4 == 1)    // arbre
                {
                    Vector2 pos = seg.a + dir * (len * 0.3f) + right * (half + 3.0f) * ((h >> 5) % 2 == 0 ? 1f : -1f);
                    float sc = 0.8f + ((h >> 9) % 5) * 0.12f;
                    AddBox(v, uv, posts, pos, 0f, 2.6f * sc, new Vector2(0.35f, 0.35f), 0f);
                    AddBox(v, uv, leaves, pos, 2.2f * sc, 2.4f * sc, new Vector2(3.4f, 3.4f) * sc, 0f);
                    AddBox(v, uv, leaves, pos, 4.1f * sc, 1.8f * sc, new Vector2(2.4f, 2.4f) * sc, 45f);
                }
            }
            if (v.Count == 0) return null;
            var m = new Mesh { name = "Decor", indexFormat = IndexFormat.UInt32 };
            m.SetVertices(v); m.SetUVs(0, uv);
            m.subMeshCount = 2;
            m.SetTriangles(posts, 0); m.SetTriangles(leaves, 1);
            m.RecalculateNormals(); m.RecalculateBounds();
            return m;
        }

        /// <summary>Boîte verticale (base à y0, hauteur h, emprise size, rotation yaw en degrés).</summary>
        private static void AddBox(List<Vector3> v, List<Vector2> uv, List<int> t, Vector2 c, float y0, float h, Vector2 size, float yawDeg)
        {
            float r = yawDeg * Mathf.Deg2Rad, cs = Mathf.Cos(r), sn = Mathf.Sin(r);
            Vector2 ex = new Vector2(cs, -sn) * (size.x * 0.5f), ez = new Vector2(sn, cs) * (size.y * 0.5f);
            Vector2[] q = { c - ex - ez, c + ex - ez, c + ex + ez, c - ex + ez };     // sens anti-horaire vu du dessus, comme les emprises : murs tournés vers l'extérieur
            for (int i = 0; i < 4; i++)
            {
                Vector2 a = q[i], b = q[(i + 1) % 4];
                int k = v.Count;
                v.Add(new Vector3(a.x, y0, a.y)); v.Add(new Vector3(a.x, y0 + h, a.y));
                v.Add(new Vector3(b.x, y0 + h, b.y)); v.Add(new Vector3(b.x, y0, b.y));
                for (int j = 0; j < 4; j++) uv.Add(Vector2.zero);
                t.Add(k); t.Add(k + 1); t.Add(k + 2);
                t.Add(k); t.Add(k + 2); t.Add(k + 3);
            }
            int top = v.Count;      // dessus
            for (int i = 0; i < 4; i++) { v.Add(new Vector3(q[i].x, y0 + h, q[i].y)); uv.Add(Vector2.zero); }
            t.Add(top); t.Add(top + 2); t.Add(top + 1);      // ordre horaire vu du dessus : face visible depuis le haut
            t.Add(top); t.Add(top + 3); t.Add(top + 2);
        }

        /// <summary>Triangulation d'un polygone simple anti-horaire. Si le polygone est dégénéré, le toit est ignoré.</summary>
        public static void Triangulate(List<Vector2> p, List<int> output)
        {
            var idx = new List<int>(p.Count);
            for (int i = 0; i < p.Count; i++) idx.Add(i);
            int guard = 0;
            while (idx.Count > 3 && guard++ < 2000)
            {
                bool clipped = false;
                for (int i = 0; i < idx.Count; i++)
                {
                    int a = idx[(i + idx.Count - 1) % idx.Count], b = idx[i], c = idx[(i + 1) % idx.Count];
                    Vector2 ab = p[b] - p[a], bc = p[c] - p[b];
                    if (ab.x * bc.y - ab.y * bc.x <= 1e-6f) continue;       // sommet rentrant ou plat
                    bool inside = false;
                    for (int j = 0; j < idx.Count && !inside; j++)
                    {
                        int k = idx[j];
                        if (k == a || k == b || k == c) continue;
                        inside = PointInTriangle(p[k], p[a], p[b], p[c]);
                    }
                    if (inside) continue;
                    output.Add(a); output.Add(b); output.Add(c);
                    idx.RemoveAt(i);
                    clipped = true;
                    break;
                }
                if (!clipped) return;
            }
            if (idx.Count == 3) { output.Add(idx[0]); output.Add(idx[1]); output.Add(idx[2]); }
        }

        private static bool PointInTriangle(Vector2 pt, Vector2 a, Vector2 b, Vector2 c)
        {
            float d1 = Cross(pt, a, b), d2 = Cross(pt, b, c), d3 = Cross(pt, c, a);
            return d1 >= -1e-5f && d2 >= -1e-5f && d3 >= -1e-5f;   // triangle anti-horaire
        }

        private static float Cross(Vector2 p, Vector2 a, Vector2 b) =>
            (b.x - a.x) * (p.y - a.y) - (b.y - a.y) * (p.x - a.x);

        private static Mesh Finish(string name, List<Vector3> v, List<int> t, List<Vector2> uv)
        {
            var m = new Mesh { name = name, indexFormat = IndexFormat.UInt32 };
            m.SetVertices(v);
            m.SetUVs(0, uv);
            m.SetTriangles(t, 0);
            m.RecalculateNormals();
            m.RecalculateBounds();
            return m;
        }
    }
}

using System.Collections.Generic;
using UnityEngine;
using UnityEngine.Rendering;

namespace TaxiKin.Map
{
    /// <summary>
    /// Fabrique deux maillages combinés par zone (routes, immeubles) : 2 draw calls au lieu de milliers,
    /// idéal pour un Android modeste. Pas de textures ; les couleurs viennent des matériaux.
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
            var v = new List<Vector3>(); var t = new List<int>();
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
                }
            return v.Count == 0 ? null : Finish("Routes", v, t);
        }

        public static Mesh BuildBuildings(List<ZoneBuilding> buildings)
        {
            var v = new List<Vector3>(); var t = new List<int>();
            if (buildings != null)
                foreach (var bd in buildings) AddBuilding(bd, v, t);
            return v.Count == 0 ? null : Finish("Immeubles", v, t);
        }

        private static void AddBuilding(ZoneBuilding bd, List<Vector3> v, List<int> t)
        {
            int n = bd.pts.Length / 2;
            if (n < 3) return;
            var p = new List<Vector2>(n);
            for (int i = 0; i < n; i++) p.Add(new Vector2(bd.pts[i * 2], bd.pts[i * 2 + 1]));

            // Murs (l'emprise est anti-horaire : l'extérieur est à droite de chaque arête)
            for (int i = 0; i < n; i++)
            {
                Vector2 a = p[i], b = p[(i + 1) % n];
                int k = v.Count;
                v.Add(new Vector3(a.x, 0f, a.y)); v.Add(new Vector3(a.x, bd.h, a.y));
                v.Add(new Vector3(b.x, bd.h, b.y)); v.Add(new Vector3(b.x, 0f, b.y));
                t.Add(k); t.Add(k + 1); t.Add(k + 2);
                t.Add(k); t.Add(k + 2); t.Add(k + 3);
            }

            // Toit : triangulation par « ear clipping », sommets propres pour un éclairage plat
            int roofBase = v.Count;
            for (int i = 0; i < n; i++) v.Add(new Vector3(p[i].x, bd.h, p[i].y));
            var tris = new List<int>();
            Triangulate(p, tris);
            for (int i = 0; i + 2 < tris.Count; i += 3)
            {   // inversion de l'ordre : face visible depuis le dessus
                t.Add(roofBase + tris[i]); t.Add(roofBase + tris[i + 2]); t.Add(roofBase + tris[i + 1]);
            }
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

        private static Mesh Finish(string name, List<Vector3> v, List<int> t)
        {
            var m = new Mesh { name = name, indexFormat = IndexFormat.UInt32 };
            m.SetVertices(v);
            m.SetTriangles(t, 0);
            m.RecalculateNormals();
            m.RecalculateBounds();
            return m;
        }
    }
}

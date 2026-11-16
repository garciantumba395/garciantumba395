using System.Collections.Generic;
using UnityEngine;
using UnityEngine.Rendering;

namespace TaxiKin.Map
{
    /// <summary>
    /// Fabrique un corridor : sol, route, pointillés centraux, poteaux et glissières (un seul maillage « décor »),
    /// plus des collisions simples (sol et deux murs invisibles). Très léger : 3 maillages et 3 collisions par corridor.
    /// </summary>
    public static class CorridorBuilder
    {
        private const float Overlap = 20f;        // le corridor dépasse de 20 m dans chaque zone pour la continuité
        private const float WallHeight = 4f;

        public static GameObject Build(WorldCorridor c, Material groundMat, Material roadMat, Material decorMat, Transform parent, List<Mesh> meshes)
        {
            var root = new GameObject($"Corridor_{c.a}_{c.b}");
            root.transform.SetParent(parent, false);

            Vector3 start = new Vector3(c.ax, 0f, c.az), end = new Vector3(c.bx, 0f, c.bz);
            Vector3 dir = (end - start).normalized;
            Vector3 left = new Vector3(-dir.z, 0f, dir.x);        // même convention que ZoneMeshBuilder
            float s0 = -Overlap, s1 = c.length + Overlap;

            // --- Sol (légèrement sous le sol des zones pour éviter le scintillement) et route ---
            meshes.Add(AddRibbon(root.transform, "Sol", groundMat, start, dir, left, s0, s1, c.stripHalfWidth, -0.03f));
            meshes.Add(AddRibbon(root.transform, "Route", roadMat, start, dir, left, s0, s1, c.width * 0.5f, 0.03f));

            // --- Décor : pointillés, poteaux, glissières ---
            var v = new List<Vector3>(); var t = new List<int>();
            for (float s = 10f; s < c.length - 10f; s += 14f)
                AddBox(v, t, start + dir * (s + 3f), dir, 3f, 0.2f, 0.04f, 0.06f);                      // pointillé central
            for (float s = c.wallStart + 20f; s < c.wallEnd - 10f; s += 60f)
                foreach (int side in new[] { -1, 1 })
                    AddBox(v, t, start + dir * s + left * (side * (c.width * 0.5f + 3f)), dir, 0.15f, 0.15f, 0f, 4f);   // poteau
            float wallLen = Mathf.Max(0f, c.wallEnd - c.wallStart);
            if (wallLen > 1f)
                foreach (int side in new[] { -1, 1 })
                    AddBox(v, t, start + dir * (c.wallStart + wallLen * 0.5f) + left * (side * (c.stripHalfWidth - 4f)), dir, wallLen * 0.5f, 0.3f, 0f, 0.9f);   // glissière
            var decor = Finish("Decor", v, t);
            meshes.Add(decor);
            AddMeshObject(root.transform, "Decor", decor, decorMat);

            // --- Collisions : sol et deux murs invisibles ---
            var col = new GameObject("Collisions");
            col.transform.SetParent(root.transform, false);
            Quaternion rot = Quaternion.LookRotation(dir, Vector3.up);
            Vector3 mid = start + dir * (c.length * 0.5f);

            var ground = new GameObject("SolCollision");
            ground.transform.SetParent(col.transform, false);
            ground.transform.SetPositionAndRotation(mid + Vector3.down * 0.5f, rot);
            ground.AddComponent<BoxCollider>().size = new Vector3(c.stripHalfWidth * 2f, 1f, c.length + Overlap * 2f);

            if (wallLen > 1f)
            {
                Vector3 wallMid = start + dir * (c.wallStart + wallLen * 0.5f);
                foreach (int side in new[] { -1, 1 })
                {
                    var wall = new GameObject(side < 0 ? "MurDroit" : "MurGauche");
                    wall.transform.SetParent(col.transform, false);
                    wall.transform.SetPositionAndRotation(wallMid + left * (side * (c.stripHalfWidth - 4f)) + Vector3.up * (WallHeight * 0.5f), rot);
                    wall.AddComponent<BoxCollider>().size = new Vector3(1f, WallHeight, wallLen);
                }
            }
            return root;
        }

        // ---------- Maillages ----------

        private static Mesh AddRibbon(Transform parent, string name, Material mat, Vector3 start, Vector3 dir, Vector3 left, float s0, float s1, float halfWidth, float y)
        {
            Vector3 a = start + dir * s0, b = start + dir * s1;
            var v = new List<Vector3>
            {
                a + left * halfWidth + Vector3.up * y, b + left * halfWidth + Vector3.up * y,
                b - left * halfWidth + Vector3.up * y, a - left * halfWidth + Vector3.up * y,
            };
            var t = new List<int> { 0, 1, 2, 0, 2, 3 };     // même ordre que les routes de zone (face visible vers le haut)
            var mesh = Finish(name, v, t);
            AddMeshObject(parent, name, mesh, mat);
            return mesh;
        }

        /// <summary>Boîte alignée sur la direction : demi-longueur (le long de dir), demi-largeur, de y0 à y1. Faces orientées vers l'extérieur.</summary>
        private static void AddBox(List<Vector3> v, List<int> t, Vector3 center, Vector3 dir, float halfLen, float halfWidth, float y0, float y1)
        {
            Vector3 left = new Vector3(-dir.z, 0f, dir.x);
            Vector3 c = new Vector3(center.x, 0f, center.z);
            Vector3[] p = new Vector3[8];
            int i = 0;
            foreach (float y in new[] { y0, y1 })
                foreach (float l in new[] { -halfLen, halfLen })
                    foreach (float w in new[] { -halfWidth, halfWidth })
                        p[i++] = c + dir * l + left * w + Vector3.up * y;
            // indices : bit0 = largeur, bit1 = longueur, bit2 = hauteur
            Quad(v, t, p[0], p[1], p[3], p[2], Vector3.down);
            Quad(v, t, p[4], p[5], p[7], p[6], Vector3.up);
            Quad(v, t, p[0], p[1], p[5], p[4], -dir);
            Quad(v, t, p[2], p[3], p[7], p[6], dir);
            Quad(v, t, p[0], p[2], p[6], p[4], -left);
            Quad(v, t, p[1], p[3], p[7], p[5], left);
        }

        /// <summary>Quadrilatère dont la face visible regarde vers 'outward' (l'ordre des sommets est corrigé au besoin).</summary>
        private static void Quad(List<Vector3> v, List<int> t, Vector3 a, Vector3 b, Vector3 c, Vector3 d, Vector3 outward)
        {
            bool flip = Vector3.Dot(Vector3.Cross(b - a, c - a), outward) < 0f;
            int k = v.Count;
            v.Add(a); v.Add(b); v.Add(c); v.Add(d);
            if (!flip) { t.Add(k); t.Add(k + 1); t.Add(k + 2); t.Add(k); t.Add(k + 2); t.Add(k + 3); }
            else { t.Add(k); t.Add(k + 2); t.Add(k + 1); t.Add(k); t.Add(k + 3); t.Add(k + 2); }
        }

        private static void AddMeshObject(Transform parent, string name, Mesh mesh, Material mat)
        {
            var go = new GameObject(name, typeof(MeshFilter), typeof(MeshRenderer));
            go.transform.SetParent(parent, false);
            go.GetComponent<MeshFilter>().sharedMesh = mesh;
            var r = go.GetComponent<MeshRenderer>();
            r.sharedMaterial = mat;
            r.shadowCastingMode = ShadowCastingMode.Off;
            r.receiveShadows = false;
        }

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

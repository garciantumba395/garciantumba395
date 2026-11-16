using System.Collections.Generic;
using UnityEditor;
using UnityEngine;
using TaxiKin.Vehicles;
using Object = UnityEngine.Object;

namespace TaxiKin.EditorTools
{
    /// <summary>
    /// Prépare le taxi du « Car Kit » de Kenney (CC0) pour Taxi Kin, SANS modifier les fichiers du fournisseur
    /// (Assets/ThirdParty/Kenney/CarKit). Les adaptations sont générées dans Assets/TaxiKin/Vehicles/Taxi :
    ///  - la carrosserie est séparée en « peinture » (repeinte par la personnalisation) et « détails » (vitres, phares, plastique) ;
    ///  - chaque roue est séparée en « pneu » et « jante » (jante repeinte par la personnalisation) ;
    ///  - chaque roue reçoit un pivot de braquage et un noeud de rotation (script WheelVisuals) ;
    ///  - l'orientation est détectée (roues avant/arrière), l'échelle et la position sont calées sur le sol.
    /// La séparation peinture/détails repose sur la palette de couleurs du kit : la couleur qui couvre la plus grande surface
    /// de la carrosserie est considérée comme la peinture. À VÉRIFIER dans Unity (voir Docs/ASSETS_3D.md).
    /// </summary>
    public static class TaxiKinKenneyTaxi
    {
        public const string FbxPath = "Assets/ThirdParty/Kenney/CarKit/Models/taxi.fbx";
        private const string ColormapPath = "Assets/ThirdParty/Kenney/CarKit/Models/Textures/colormap.png";
        private const string OutDir = "Assets/TaxiKin/Vehicles/Taxi";
        private const string PrefabPath = OutDir + "/TaxiVisual_Kenney.prefab";
        private const string PrefKey = "TaxiKin.UseKenneyTaxi";
        private const float TargetLength = 3.7f;      // longueur visuelle voulue en mètres (le collider d'origine fait 4,2 m)

        public static bool Available => AssetDatabase.LoadAssetAtPath<GameObject>(FbxPath) != null;
        public static bool Enabled => EditorPrefs.GetBool(PrefKey, true);

        public class Result
        {
            public GameObject visual;
            public Renderer paint;
            public Renderer[] rims;
            public Vector3 size;          // largeur, hauteur, longueur du modèle affiché
        }

        [MenuItem("Taxi Kin/Véhicules/Taxi : modèle 3D Kenney (activer / désactiver)")]
        public static void Toggle()
        {
            EditorPrefs.SetBool(PrefKey, !Enabled);
            EditorUtility.DisplayDialog("Taxi Kin",
                Enabled ? "Taxi : modèle 3D Kenney ACTIVÉ.\nRecréez la scène (Taxi Kin → Créer la scène de test Main)."
                        : "Taxi : retour au modèle provisoire.\nRecréez la scène (Taxi Kin → Créer la scène de test Main).", "OK");
        }

        [MenuItem("Taxi Kin/Véhicules/Taxi : régénérer le modèle 3D Kenney")]
        public static void Regenerate()
        {
            if (!Available) { Debug.LogWarning("[Taxi Kin] taxi.fbx introuvable : " + FbxPath); return; }
            Debug.Log(Build() != null ? "[Taxi Kin] Modèle taxi Kenney régénéré : " + PrefabPath : "[Taxi Kin] Échec de la génération du taxi Kenney (voir la Console).");
        }

        /// <summary>Instancie le modèle sous la racine du véhicule (bas du modèle au bas du collider). Null si indisponible.</summary>
        public static Result TryAttach(Transform vehicleRoot, Vector3 colliderSize)
        {
            if (!Available || !Enabled) return null;
            var prefab = Build();
            if (prefab == null) return null;
            var inst = PrefabUtility.InstantiatePrefab(prefab) as GameObject;
            if (inst == null) return null;
            inst.name = "VisuelKenney";
            inst.transform.SetParent(vehicleRoot, false);
            inst.transform.localPosition = new Vector3(0f, -colliderSize.y * 0.5f, 0f);   // les roues touchent le bas du collider
            inst.transform.localRotation = Quaternion.identity;

            var r = new Result { visual = inst };
            var rims = new List<Renderer>();
            foreach (var t in inst.GetComponentsInChildren<Transform>(true))
            {
                if (t.GetComponent<Renderer>() == null) continue;
                if (t.name == "Peinture") r.paint = t.GetComponent<Renderer>();
                else if (t.name == "Jante") rims.Add(t.GetComponent<Renderer>());
            }
            r.rims = rims.ToArray();
            var b = Bounds(inst);
            r.size = new Vector3(b.size.x, b.size.y, b.size.z);
            return r;
        }

        // ---------- Fabrication du prefab ----------

        private static GameObject Build()
        {
            EnsureFolders();
            PrepareColormap();

            var fbx = AssetDatabase.LoadAssetAtPath<GameObject>(FbxPath);
            var inst = Object.Instantiate(fbx);
            inst.transform.position = Vector3.zero; inst.transform.rotation = Quaternion.identity; inst.transform.localScale = Vector3.one;

            // Repérage des noeuds
            Transform body = null; var wheels = new List<Transform>();
            foreach (var mf in inst.GetComponentsInChildren<MeshFilter>(true))
            {
                string n = mf.name.ToLowerInvariant();
                if (n.Contains("wheel")) wheels.Add(mf.transform);
                else if (n.Contains("body")) body = mf.transform;
            }
            if (body == null || wheels.Count != 4) { Debug.LogError("[Taxi Kin] Structure inattendue du taxi (carrosserie ou 4 roues introuvables)."); Object.DestroyImmediate(inst); return null; }

            // Orientation : l'avant est du côté des roues « front »
            Vector3 front = Vector3.zero, back = Vector3.zero; int nf = 0, nb = 0;
            foreach (var w in wheels)
            {
                Vector3 c = w.GetComponent<MeshRenderer>().bounds.center;
                if (w.name.ToLowerInvariant().Contains("front")) { front += c; nf++; } else { back += c; nb++; }
            }
            Vector3 dir = (front / Mathf.Max(1, nf)) - (back / Mathf.Max(1, nb)); dir.y = 0f;
            if (dir.sqrMagnitude > 1e-6f) inst.transform.rotation = Quaternion.FromToRotation(dir.normalized, Vector3.forward);

            var colormap = AssetDatabase.LoadAssetAtPath<Texture2D>(ColormapPath);
            var detailMat = MakeMaterial("Mat_Kenney_Details", Color.white, colormap, 0.1f);
            var paintMat = MakeMaterial("Mat_Kenney_Peinture", VehicleColorManager.DefaultTaxiColor, null, 0.35f);   // jaune Taxi Kin #FFD400
            var rimMat = MakeMaterial("Mat_Kenney_Jante", new Color(0.35f, 0.35f, 0.38f), null, 0.5f);

            // Racine du prefab et noeud « Modele » (porte l'échelle)
            var root = new GameObject("TaxiVisual_Kenney");
            var model = new GameObject("Modele").transform;
            model.SetParent(root.transform, false);

            // Carrosserie : détails + peinture
            // la couleur dominante (la peinture) devient un enfant à matériau uni ; le reste (vitres, phares, plastique) garde la palette
            SplitAndAssign(body, "body", detailMat, paintMat, "Peinture", true);
            body.SetParent(model, true);

            // Roues : pivot (braquage) > rotation > pneu (+ jante)
            var steer = new List<Transform>(); var spin = new List<Transform>();
            foreach (var w in wheels)
            {
                Vector3 center = w.GetComponent<MeshRenderer>().bounds.center;
                bool isFront = w.name.ToLowerInvariant().Contains("front");
                var pivot = new GameObject("Pivot_" + w.name).transform;
                pivot.SetParent(model, false); pivot.position = center; pivot.rotation = Quaternion.identity;
                var rot = new GameObject("Rotation").transform;
                rot.SetParent(pivot, false); rot.localPosition = Vector3.zero; rot.localRotation = Quaternion.identity;
                // le pneu (couleur dominante) garde la palette du kit ; la jante (le reste) devient un enfant repeint par la personnalisation
                SplitAndAssign(w, w.name, detailMat, rimMat, "Jante", false);
                w.SetParent(rot, true);
                if (isFront) steer.Add(pivot);
                spin.Add(rot);
            }
            Object.DestroyImmediate(inst);      // il ne reste que l'enveloppe vide du FBX

            // Échelle et calage : longueur voulue, centré, bas du modèle à y = 0
            var b0 = Bounds(root);
            float scale = TargetLength / Mathf.Max(0.01f, b0.size.z);
            model.localScale = Vector3.one * scale;
            var b1 = Bounds(root);
            model.localPosition = new Vector3(-b1.center.x, -b1.min.y, -b1.center.z);

            float radius = wheels.Count > 0 ? wheels[0].GetComponent<MeshRenderer>().bounds.extents.y : 0.35f;
            var wv = root.AddComponent<WheelVisuals>();
            wv.Configure(steer.ToArray(), spin.ToArray(), radius);

            var prefab = PrefabUtility.SaveAsPrefabAsset(root, PrefabPath);
            Object.DestroyImmediate(root);
            AssetDatabase.SaveAssets();
            return prefab;
        }

        /// <summary>
        /// Sépare le maillage d'un noeud en deux : la couleur de palette dominante (en surface) devient l'enfant « childName »
        /// (matériau uni, repeint par la personnalisation), le reste garde la palette du kit.
        /// </summary>
        private static void SplitAndAssign(Transform node, string assetName, Material baseMat, Material childMat, string childName, bool dominantGoesToChild)
        {
            var mf = node.GetComponent<MeshFilter>();
            var src = mf.sharedMesh;
            var verts = src.vertices; var uvs = src.uv;
            var tris = new List<int>();
            for (int i = 0; i < src.subMeshCount; i++) tris.AddRange(src.GetTriangles(i));

            // Surface couverte par chaque case de la palette (8 x 8 cases de 64 px)
            var area = new Dictionary<int, float>();
            var cellOf = new int[tris.Count / 3];
            for (int t = 0; t + 2 < tris.Count; t += 3)
            {
                int a = tris[t], b = tris[t + 1], c = tris[t + 2];
                Vector2 uv = (uvs[a] + uvs[b] + uvs[c]) / 3f;
                int cell = Mathf.Clamp(Mathf.FloorToInt(uv.x * 8f), 0, 7) * 8 + Mathf.Clamp(Mathf.FloorToInt(uv.y * 8f), 0, 7);
                cellOf[t / 3] = cell;
                float ar = Vector3.Cross(verts[b] - verts[a], verts[c] - verts[a]).magnitude * 0.5f;
                area.TryGetValue(cell, out float cur); area[cell] = cur + ar;
            }
            int dominantCell = -1; float best = -1f;
            foreach (var kv in area) if (kv.Value > best) { best = kv.Value; dominantCell = kv.Key; }

            var rest = new List<int>(); var dom = new List<int>();
            for (int t = 0; t + 2 < tris.Count; t += 3)
            {
                var list = cellOf[t / 3] == dominantCell ? dom : rest;
                list.Add(tris[t]); list.Add(tris[t + 1]); list.Add(tris[t + 2]);
            }

            var onNode = dominantGoesToChild ? rest : dom;      // maillage qui reste sur le noeud (matériau de base)
            var onChild = dominantGoesToChild ? dom : rest;     // maillage de l'enfant (matériau uni repeint)
            mf.sharedMesh = MakeMesh(src, onNode, assetName + "_base");
            node.GetComponent<MeshRenderer>().sharedMaterials = new[] { baseMat };

            var child = new GameObject(childName, typeof(MeshFilter), typeof(MeshRenderer));
            child.transform.SetParent(node, false);          // même espace que le maillage d'origine
            child.GetComponent<MeshFilter>().sharedMesh = MakeMesh(src, onChild, assetName + "_" + childName.ToLowerInvariant());
            child.GetComponent<MeshRenderer>().sharedMaterial = childMat;
        }

        private static Mesh MakeMesh(Mesh src, List<int> triangles, string name)
        {
            var m = new Mesh { name = name, indexFormat = src.indexFormat };
            m.vertices = src.vertices; m.normals = src.normals; m.uv = src.uv;
            m.SetTriangles(triangles, 0);
            m.RecalculateBounds();
            string path = $"{OutDir}/{name}.asset";
            AssetDatabase.DeleteAsset(path);
            AssetDatabase.CreateAsset(m, path);
            return m;
        }

        private static Material MakeMaterial(string name, Color color, Texture2D tex, float smoothness)
        {
            var mat = TaxiKinSceneBuilder.GetMaterial(name, color);
            mat.color = color;
            mat.mainTexture = tex;
            if (mat.HasProperty("_Glossiness")) mat.SetFloat("_Glossiness", smoothness);
            if (mat.HasProperty("_Smoothness")) mat.SetFloat("_Smoothness", smoothness);
            EditorUtility.SetDirty(mat);
            return mat;
        }

        /// <summary>Palette sans mipmaps ni répétition : les couleurs ne se mélangent pas au loin.</summary>
        private static void PrepareColormap()
        {
            var imp = AssetImporter.GetAtPath(ColormapPath) as TextureImporter;
            if (imp != null && (imp.mipmapEnabled || imp.wrapMode != TextureWrapMode.Clamp))
            {
                imp.mipmapEnabled = false; imp.wrapMode = TextureWrapMode.Clamp; imp.filterMode = FilterMode.Bilinear;
                imp.SaveAndReimport();
            }
        }

        private static void EnsureFolders()
        {
            foreach (var f in new[] { "Assets/TaxiKin", "Assets/TaxiKin/Vehicles", OutDir })
                if (!AssetDatabase.IsValidFolder(f)) AssetDatabase.CreateFolder(f.Substring(0, f.LastIndexOf('/')), f.Substring(f.LastIndexOf('/') + 1));
        }

        private static Bounds Bounds(GameObject go)
        {
            var rs = go.GetComponentsInChildren<Renderer>(true);
            var b = new Bounds(go.transform.position, Vector3.zero); bool first = true;
            foreach (var r in rs) { if (first) { b = r.bounds; first = false; } else b.Encapsulate(r.bounds); }
            return b;
        }
    }
}

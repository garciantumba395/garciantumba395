using UnityEditor;
using UnityEngine;
using TaxiKin.Vehicles;
using Object = UnityEngine.Object;

namespace TaxiKin.EditorTools
{
    /// <summary>
    /// Fabrique un véhicule de test : racine NON déformée (physique) + carrosserie en cube + toit, 4 jantes,
    /// autocollants et plaque (TextMesh), le tout piloté par VehicleCustomizer.
    /// La racine n'est jamais mise à l'échelle : les enfants gardent des proportions correctes.
    /// </summary>
    public static class TaxiKinVehicleBuilder
    {
        /// <summary>Véhicule jouable : physique (collision + Rigidbody) et contrôleur.</summary>
        public static GameObject Make<T>(string name, Vector3 pos, Vector3 size, Material bodyMat, bool withRoof) where T : VehicleController
        {
            var root = Build(name, pos, size, bodyMat, withRoof, VehicleType.Taxi, true, out var cust);
            var controller = root.AddComponent<T>();   // ajoute aussi le Rigidbody (RequireComponent)
            var so = new SerializedObject(cust);
            so.FindProperty("vehicle").objectReferenceValue = controller;
            so.ApplyModifiedPropertiesWithoutUndo();
            return root;
        }

        /// <summary>Véhicule d'IA léger (trafic, coureurs) : collision + contrôleur + pilote, sans jantes ni personnalisation.</summary>
        public static GameObject MakeAi<T>(string name, Vector3 size, Material bodyMat, bool withRoof, float obstacleRange) where T : VehicleController
        {
            var root = new GameObject(name);
            root.AddComponent<BoxCollider>().size = size;
            root.AddComponent<T>();                                  // ajoute aussi le Rigidbody
            Prim(PrimitiveType.Cube, "Carrosserie", root.transform, Vector3.zero, size, bodyMat);
            if (withRoof)
                Prim(PrimitiveType.Cube, "Toit", root.transform, new Vector3(0f, size.y * 0.5f + 0.02f, 0f),
                     new Vector3(size.x * 0.9f, 0.24f, size.z * 0.5f), bodyMat);
            var ai = root.AddComponent<TaxiKin.Traffic.VehicleAI>();
            var so = new SerializedObject(ai);
            so.FindProperty("groundOffset").floatValue = size.y * 0.5f + 0.05f;
            so.FindProperty("obstacleRange").floatValue = obstacleRange;
            so.ApplyModifiedPropertiesWithoutUndo();
            return root;
        }

        /// <summary>Copie visuelle d'un véhicule (aperçu du garage) : aucune physique, aucun contrôleur.</summary>
        public static GameObject MakePreview(string name, VehicleType kind, Vector3 size, Material bodyMat, bool withRoof) =>
            Build(name, Vector3.zero, size, bodyMat, withRoof, kind, false, out _);

        private static GameObject Build(string name, Vector3 pos, Vector3 size, Material bodyMat, bool withRoof,
                                        VehicleType previewKind, bool physics, out VehicleCustomizer customizer)
        {
            var root = new GameObject(name);
            root.transform.position = pos;
            if (physics) root.AddComponent<BoxCollider>().size = size;

            var body = Prim(PrimitiveType.Cube, "Carrosserie", root.transform, Vector3.zero, size, bodyMat);

            Renderer roofRenderer = null;
            if (withRoof)
                roofRenderer = Prim(PrimitiveType.Cube, "Toit", root.transform,
                                    new Vector3(0f, size.y * 0.5f + 0.02f, 0f),
                                    new Vector3(size.x * 0.9f, 0.24f, size.z * 0.5f), bodyMat).GetComponent<Renderer>();

            // Jantes : 4 pour les voitures, 2 pour le Wewa (étroit)
            var rimMat = TaxiKinSceneBuilder.GetMaterial("Mat_Rim", new Color(0.35f, 0.35f, 0.38f));
            float r = Mathf.Min(0.38f, size.y * 0.35f);
            float xs = size.x > 1f ? size.x * 0.5f + 0.02f : 0f;
            var rims = new System.Collections.Generic.List<Renderer>();
            foreach (float z in new[] { size.z * 0.32f, -size.z * 0.32f })
                foreach (float x in (xs > 0f ? new[] { -xs, xs } : new[] { 0f }))
                {
                    var rim = Prim(PrimitiveType.Cylinder, "Jante", root.transform,
                                   new Vector3(x, -size.y * 0.5f + r, z), new Vector3(r * 1.5f, 0.12f, r * 1.5f), rimMat);
                    rim.transform.localRotation = Quaternion.Euler(0f, 0f, 90f);   // axe horizontal
                    rims.Add(rim.GetComponent<Renderer>());
                }

            // Habillage par type de véhicule (cabine, vitres, pneus, phares…) : la carrosserie, le toit et les jantes
            // restent ceux que la personnalisation repeint
            var dm = TaxiKinAiPrefabBuilder.CreateMats();
            if (size.x < 1f) TaxiKinAiPrefabBuilder.DressMoto(root, size, dm, false, true);
            else if (size.z >= 8f) TaxiKinAiPrefabBuilder.DressMinibus(root, size, dm, true);
            else TaxiKinAiPrefabBuilder.DressCar(root, size, dm, true, true);

            // Tout ce qui existe jusqu'ici est le modèle PROVISOIRE : on le range dans un groupe pour pouvoir le masquer / le rétablir
            var provisional = new GameObject("VisuelProvisoire").transform;
            provisional.SetParent(root.transform, false);
            var provisionalParts = new System.Collections.Generic.List<Transform>();
            foreach (Transform child in root.transform) if (child != provisional) provisionalParts.Add(child);
            foreach (var part in provisionalParts) part.SetParent(provisional, true);

            // Autocollants (quads sur les deux flancs ; un seul est visible à la fois)
            var stickerSet = new (string id, Color color)[]
            {
                ("sticker_kin", new Color(0.05f, 0.05f, 0.05f)),
                ("sticker_stripes", Color.white),
                ("sticker_kin_exclusive", new Color(0.95f, 0.45f, 0f)),
            };
            var stickerVisuals = new GameObject[stickerSet.Length];
            for (int i = 0; i < stickerSet.Length; i++)
            {
                var holder = new GameObject(stickerSet[i].id);
                holder.transform.SetParent(root.transform, false);
                var m = TaxiKinSceneBuilder.GetMaterial("Mat_" + stickerSet[i].id, stickerSet[i].color);
                Vector2 qs = i == 1 ? new Vector2(size.z * 0.8f, 0.14f) : new Vector2(size.z * (i == 0 ? 0.4f : 0.3f), size.y * 0.25f);
                float y = i == 1 ? 0.1f : 0f;
                foreach (int side in new[] { -1, 1 })
                {
                    var q = Prim(PrimitiveType.Quad, "Flanc", holder.transform, new Vector3(side * (size.x * 0.5f + 0.012f), y, 0f), new Vector3(qs.x, qs.y, 1f), m);
                    q.transform.localRotation = Quaternion.Euler(0f, side > 0 ? -90f : 90f, 0f);   // la face visible regarde vers l'extérieur
                }
                holder.SetActive(false);
                stickerVisuals[i] = holder;
            }

            // Plaque d'immatriculation personnalisable
            var plateMat = TaxiKinSceneBuilder.GetMaterial("Mat_Plate", Color.white);
            var plate = Prim(PrimitiveType.Quad, "Plaque", root.transform, new Vector3(0f, -size.y * 0.25f, -(size.z * 0.5f + 0.012f)), new Vector3(0.5f, 0.13f, 1f), plateMat);
            var textGo = new GameObject("TextePlaque");
            textGo.transform.SetParent(plate.transform, false);
            textGo.transform.localPosition = new Vector3(0f, 0f, -0.01f);
            textGo.transform.localScale = new Vector3(1f / 0.5f, 1f / 0.13f, 1f);   // annule l'étirement du quad
            var tm = textGo.AddComponent<TextMesh>();
            var font = Resources.GetBuiltinResource<Font>("LegacyRuntime.ttf");
            tm.font = font;
            tm.anchor = TextAnchor.MiddleCenter;
            tm.alignment = TextAlignment.Center;
            tm.fontSize = 100;
            tm.characterSize = 0.009f;   // ≈ 9 cm de haut sur une plaque de 13 cm
            tm.color = Color.black;
            tm.text = "KIN 001";
            textGo.GetComponent<MeshRenderer>().sharedMaterial = font.material;

            // Câblage de VehicleCustomizer
            var cust = root.AddComponent<VehicleCustomizer>();
            customizer = cust;
            var so = new SerializedObject(cust);
            if (!physics) so.FindProperty("previewKind").enumValueIndex = (int)previewKind;
            so.FindProperty("bodyRenderer").objectReferenceValue = body.GetComponent<Renderer>();
            so.FindProperty("roofRenderer").objectReferenceValue = roofRenderer;
            so.FindProperty("plateText").objectReferenceValue = tm;

            var rimProp = so.FindProperty("rimRenderers");
            rimProp.arraySize = rims.Count;
            for (int i = 0; i < rims.Count; i++) rimProp.GetArrayElementAtIndex(i).objectReferenceValue = rims[i];

            var stProp = so.FindProperty("stickers");
            stProp.arraySize = stickerSet.Length;
            for (int i = 0; i < stickerSet.Length; i++)
            {
                var el = stProp.GetArrayElementAtIndex(i);
                el.FindPropertyRelative("id").stringValue = stickerSet[i].id;
                el.FindPropertyRelative("visual").objectReferenceValue = stickerVisuals[i];
            }
            so.ApplyModifiedPropertiesWithoutUndo();

            // Nouveau modèle 3D (taxi-voiture seulement pour l'instant) : enfant visuel, la racine physique reste inchangée
            TaxiKinKenneyTaxi.Result kenney = null;
            bool isCar = physics && size.x >= 1f && size.z < 8f;
            if (isCar) kenney = TaxiKinKenneyTaxi.TryAttach(root.transform, size);
            if (isCar || physics)
            {
                var sw = root.AddComponent<TaxiKin.Vehicles.VehicleVisualSwitch>();
                var sws = new SerializedObject(sw);
                sws.FindProperty("provisional").objectReferenceValue = provisional.gameObject;
                sws.FindProperty("provisionalBody").objectReferenceValue = body.GetComponent<Renderer>();
                sws.FindProperty("provisionalRoof").objectReferenceValue = roofRenderer;
                var pr = sws.FindProperty("provisionalRims");
                pr.arraySize = rims.Count;
                for (int i = 0; i < rims.Count; i++) pr.GetArrayElementAtIndex(i).objectReferenceValue = rims[i];
                sws.FindProperty("provisionalSize").vector3Value = size;
                sws.FindProperty("customizer").objectReferenceValue = cust;
                sws.FindProperty("plate").objectReferenceValue = plate.transform;
                var sh = sws.FindProperty("stickerHolders");
                sh.arraySize = stickerVisuals.Length;
                for (int i = 0; i < stickerVisuals.Length; i++) sh.GetArrayElementAtIndex(i).objectReferenceValue = stickerVisuals[i].transform;
                if (kenney != null)
                {
                    sws.FindProperty("newModel").objectReferenceValue = kenney.visual;
                    sws.FindProperty("newBody").objectReferenceValue = kenney.paint;
                    var nr = sws.FindProperty("newRims");
                    nr.arraySize = kenney.rims.Length;
                    for (int i = 0; i < kenney.rims.Length; i++) nr.GetArrayElementAtIndex(i).objectReferenceValue = kenney.rims[i];
                    sws.FindProperty("newSize").vector3Value = new Vector3(kenney.size.x, size.y, kenney.size.z);
                    sws.FindProperty("useNewModel").boolValue = true;
                }
                sws.ApplyModifiedPropertiesWithoutUndo();

                if (kenney != null)
                {
                    // Le nouveau modèle est actif : la personnalisation repeint la peinture et les jantes du nouveau modèle seulement
                    provisional.gameObject.SetActive(false);
                    var cso = new SerializedObject(cust);
                    cso.FindProperty("bodyRenderer").objectReferenceValue = kenney.paint;
                    cso.FindProperty("roofRenderer").objectReferenceValue = null;
                    var rp = cso.FindProperty("rimRenderers");
                    rp.arraySize = kenney.rims.Length;
                    for (int i = 0; i < kenney.rims.Length; i++) rp.GetArrayElementAtIndex(i).objectReferenceValue = kenney.rims[i];
                    cso.ApplyModifiedPropertiesWithoutUndo();
                    // plaque et autocollants replacés sur le nouveau gabarit
                    var nsize = new Vector3(kenney.size.x, size.y, kenney.size.z);
                    var p = plate.transform.localPosition; plate.transform.localPosition = new Vector3(p.x, p.y, -(nsize.z * 0.5f + 0.012f));
                    foreach (var h in stickerVisuals)
                        foreach (Transform q in h.transform)
                        { var qp = q.localPosition; q.localPosition = new Vector3(Mathf.Sign(qp.x) * (nsize.x * 0.5f + 0.012f), qp.y, qp.z); }
                }
            }
            return root;
        }

        /// <summary>Crée les 5 prefabs d'aperçu du garage (Resources/Vehicles/Preview). Régénérés à chaque exécution.</summary>
        public static void BuildPreviewPrefabs(Material wewaMat, Material taxiMat, Material busMat)
        {
            foreach (var f in new[] { "Assets/Resources", "Assets/Resources/Vehicles", "Assets/Resources/Vehicles/Preview" })
                if (!AssetDatabase.IsValidFolder(f)) AssetDatabase.CreateFolder(f.Substring(0, f.LastIndexOf('/')), f.Substring(f.LastIndexOf('/') + 1));

            SavePreview("wewa_base", VehicleType.Wewa, new Vector3(0.5f, 0.9f, 1.8f), wewaMat, false);
            SavePreview("wewa_upgraded", VehicleType.Wewa, new Vector3(0.5f, 0.9f, 1.8f), wewaMat, false);
            SavePreview("taxi", VehicleType.Taxi, new Vector3(1.8f, 1.2f, 4f), taxiMat, true);
            SavePreview("taxi_premium", VehicleType.Taxi, new Vector3(1.8f, 1.2f, 4f), taxiMat, true);
            SavePreview("bus207", VehicleType.Bus207, new Vector3(2.5f, 2.8f, 9f), busMat, false);
        }

        private static void SavePreview(string id, VehicleType kind, Vector3 size, Material mat, bool roof)
        {
            string path = $"Assets/Resources/Vehicles/Preview/{id}.prefab";
            var go = MakePreview(id, kind, size, mat, roof);
            foreach (Transform c in go.transform) c.localPosition += Vector3.up * (size.y * 0.5f);   // posé sur le plateau (bas à y = 0)
            PrefabUtility.SaveAsPrefabAsset(go, path);
            Object.DestroyImmediate(go);
        }

        private static GameObject Prim(PrimitiveType t, string name, Transform parent, Vector3 pos, Vector3 scale, Material m)
        {
            var g = GameObject.CreatePrimitive(t);
            g.name = name;
            Object.DestroyImmediate(g.GetComponent<Collider>());   // seule la racine porte la collision
            g.transform.SetParent(parent, false);
            g.transform.localPosition = pos;
            g.transform.localScale = scale;
            g.GetComponent<Renderer>().sharedMaterial = m;
            return g;
        }
    }
}

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
        public static GameObject Make<T>(string name, Vector3 pos, Vector3 size, Material bodyMat, bool withRoof) where T : VehicleController
        {
            var root = new GameObject(name);
            root.transform.position = pos;
            root.AddComponent<BoxCollider>().size = size;
            var controller = root.AddComponent<T>();   // ajoute aussi le Rigidbody (RequireComponent)

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
                                   new Vector3(x, -size.y * 0.5f + r, z), new Vector3(r * 2f, 0.1f, r * 2f), rimMat);
                    rim.transform.localRotation = Quaternion.Euler(0f, 0f, 90f);   // axe horizontal
                    rims.Add(rim.GetComponent<Renderer>());
                }

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
            var so = new SerializedObject(cust);
            so.FindProperty("vehicle").objectReferenceValue = controller;
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
            return root;
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

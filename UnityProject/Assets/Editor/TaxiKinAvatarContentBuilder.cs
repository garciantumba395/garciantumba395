using System;
using UnityEditor;
using UnityEngine;
using TaxiKin.Player;
using Object = UnityEngine.Object;

namespace TaxiKin.EditorTools
{
    /// <summary>
    /// Génère le contenu de TEST de l'avatar avec des formes simples (cubes, capsules, sphères) :
    /// fiches de vêtements (Resources/Clothing/Items), prefabs légers (Resources/Clothing/Prefabs)
    /// et le prefab de personnage (Assets/Prefabs/Avatar.prefab). Rien n'est écrasé si le fichier existe.
    /// Les vraies tenues 3D remplaceront ces prefabs sans changer le code.
    /// </summary>
    public static class TaxiKinAvatarContentBuilder
    {
        private const string ItemsFolder = "Assets/Resources/Clothing/Items";
        private const string PrefabsFolder = "Assets/Resources/Clothing/Prefabs";
        private const string AvatarPath = "Assets/Prefabs/Avatar.prefab";

        private static Material white, hair, shoe, dark, brown, yellow, red, skin;

        [MenuItem("Taxi Kin/Avatar/Générer le contenu de test")]
        public static GameObject Generate()
        {
            EnsureFolder("Assets/Resources"); EnsureFolder("Assets/Resources/Clothing");
            EnsureFolder(ItemsFolder); EnsureFolder(PrefabsFolder); EnsureFolder("Assets/Prefabs");

            white = TaxiKinSceneBuilder.GetMaterial("Mat_ClothWhite", Color.white);
            hair = TaxiKinSceneBuilder.GetMaterial("Mat_Hair", new Color(0.1f, 0.1f, 0.1f));
            shoe = TaxiKinSceneBuilder.GetMaterial("Mat_Shoe", new Color(0.17f, 0.17f, 0.17f));
            dark = TaxiKinSceneBuilder.GetMaterial("Mat_Dark", new Color(0.04f, 0.04f, 0.04f));
            brown = TaxiKinSceneBuilder.GetMaterial("Mat_Brown", new Color(0.36f, 0.25f, 0.22f));
            yellow = TaxiKinSceneBuilder.GetMaterial("Mat_TaxiYellow", new Color(1f, 0.831f, 0f));
            red = TaxiKinSceneBuilder.GetMaterial("Mat_ClothRed", new Color(0.78f, 0.16f, 0.16f));
            skin = TaxiKinSceneBuilder.GetMaterial("Mat_Skin", new Color(0.55f, 0.37f, 0.25f));

            // ----- Visage -----
            Item("face_default", "Naturel", ClothingSlot.Face, false, true, "", r => { });
            Item("face_beard", "Barbe", ClothingSlot.Face, false, true, "", r => P(PrimitiveType.Cube, r, new Vector3(0f, -0.12f, 0.1f), new Vector3(0.22f, 0.12f, 0.1f), brown));
            Item("face_moustache", "Moustache", ClothingSlot.Face, false, true, "", r => P(PrimitiveType.Cube, r, new Vector3(0f, -0.04f, 0.17f), new Vector3(0.16f, 0.03f, 0.05f), hair));

            // ----- Cheveux -----
            Item("hair_short", "Courts", ClothingSlot.Hair, false, true, "", r => P(PrimitiveType.Cube, r, Vector3.zero, new Vector3(0.36f, 0.08f, 0.38f), hair));
            Item("hair_afro", "Afro", ClothingSlot.Hair, false, true, "", r => P(PrimitiveType.Sphere, r, new Vector3(0f, 0.05f, 0f), new Vector3(0.5f, 0.42f, 0.5f), hair));
            Item("hair_braids", "Tresses", ClothingSlot.Hair, false, true, "", r =>
            {
                P(PrimitiveType.Cube, r, Vector3.zero, new Vector3(0.36f, 0.08f, 0.38f), hair);
                P(PrimitiveType.Cube, r, new Vector3(-0.1f, -0.2f, -0.17f), new Vector3(0.05f, 0.4f, 0.05f), hair);
                P(PrimitiveType.Cube, r, new Vector3(0f, -0.2f, -0.19f), new Vector3(0.05f, 0.4f, 0.05f), hair);
                P(PrimitiveType.Cube, r, new Vector3(0.1f, -0.2f, -0.17f), new Vector3(0.05f, 0.4f, 0.05f), hair);
            });

            // ----- Haut (couleur modifiable) -----
            Item("top_tshirt", "T-shirt", ClothingSlot.Top, true, true, "", r => P(PrimitiveType.Cube, r, Vector3.zero, new Vector3(0.62f, 0.5f, 0.4f), white));
            Item("top_shirt", "Chemise", ClothingSlot.Top, true, true, "", r =>
            {
                P(PrimitiveType.Cube, r, Vector3.zero, new Vector3(0.64f, 0.55f, 0.42f), white);
                P(PrimitiveType.Cube, r, new Vector3(0f, 0.28f, 0.1f), new Vector3(0.2f, 0.05f, 0.2f), white);
            });
            Item("top_jacket", "Veste", ClothingSlot.Top, true, true, "", r =>
            {
                P(PrimitiveType.Cube, r, Vector3.zero, new Vector3(0.7f, 0.55f, 0.46f), white);
                P(PrimitiveType.Cube, r, new Vector3(-0.4f, -0.02f, 0f), new Vector3(0.14f, 0.5f, 0.14f), white);
                P(PrimitiveType.Cube, r, new Vector3(0.4f, -0.02f, 0f), new Vector3(0.14f, 0.5f, 0.14f), white);
            });
            Item("top_yellow_start", "Maillot jaune de départ", ClothingSlot.Top, false, false, "yellow_start", r =>
            {
                P(PrimitiveType.Cube, r, Vector3.zero, new Vector3(0.64f, 0.52f, 0.42f), yellow);
                P(PrimitiveType.Cube, r, new Vector3(0f, 0f, 0.215f), new Vector3(0.3f, 0.08f, 0.01f), dark);   // bande
            });

            // ----- Bas (couleur modifiable) -----
            Item("bottom_jeans", "Jean", ClothingSlot.Bottom, true, true, "", r => Legs(r, 0.7f, 0.22f, white));
            Item("bottom_trousers", "Pantalon", ClothingSlot.Bottom, true, true, "", r => Legs(r, 0.7f, 0.24f, white));
            Item("bottom_shorts", "Short", ClothingSlot.Bottom, true, true, "", r =>
            {
                P(PrimitiveType.Cube, r, new Vector3(-0.13f, 0.17f, 0f), new Vector3(0.22f, 0.35f, 0.26f), white);
                P(PrimitiveType.Cube, r, new Vector3(0.13f, 0.17f, 0f), new Vector3(0.22f, 0.35f, 0.26f), white);
            });
            Item("bottom_yellow_start", "Pantalon jaune de départ", ClothingSlot.Bottom, false, false, "yellow_start", r => Legs(r, 0.7f, 0.22f, yellow));

            // ----- Chaussures -----
            Item("shoes_sneakers", "Baskets", ClothingSlot.Shoes, false, true, "", r => Feet(r, 0.12f, white));
            Item("shoes_sandals", "Sandales", ClothingSlot.Shoes, false, true, "", r => Feet(r, 0.05f, brown));
            Item("shoes_boots", "Bottines", ClothingSlot.Shoes, false, true, "", r => Feet(r, 0.24f, shoe));

            // ----- Accessoires -----
            Item("acc_glasses", "Lunettes", ClothingSlot.Accessory, false, true, "", r => P(PrimitiveType.Cube, r, new Vector3(0f, 0.03f, 0.17f), new Vector3(0.3f, 0.06f, 0.04f), dark));
            Item("acc_cap", "Casquette", ClothingSlot.Accessory, false, true, "", r =>
            {
                P(PrimitiveType.Cube, r, new Vector3(0f, 0.2f, 0f), new Vector3(0.38f, 0.1f, 0.4f), red);
                P(PrimitiveType.Cube, r, new Vector3(0f, 0.17f, 0.26f), new Vector3(0.3f, 0.02f, 0.16f), red);
            });
            Item("acc_scarf", "Foulard", ClothingSlot.Accessory, false, true, "", r => P(PrimitiveType.Cube, r, new Vector3(0f, -0.28f, 0f), new Vector3(0.42f, 0.1f, 0.32f), yellow));

            AssetDatabase.SaveAssets();
            return BuildAvatarPrefab();
        }

        // ---------- Fabrication des articles ----------

        private static void Item(string id, string display, ClothingSlot slot, bool tintable, bool byDefault, string group, Action<Transform> build)
        {
            string prefabFile = $"{PrefabsFolder}/{id}.prefab";
            if (AssetDatabase.LoadAssetAtPath<GameObject>(prefabFile) == null)
            {
                var root = new GameObject(id);
                build(root.transform);
                PrefabUtility.SaveAsPrefabAsset(root, prefabFile);
                Object.DestroyImmediate(root);
            }

            string itemFile = $"{ItemsFolder}/{id}.asset";
            if (AssetDatabase.LoadAssetAtPath<ClothingItem>(itemFile) != null) return;   // ne jamais écraser
            var item = ScriptableObject.CreateInstance<ClothingItem>();
            item.id = id; item.displayName = display; item.slot = slot;
            item.prefabPath = "Clothing/Prefabs/" + id;      // chemin Resources : chargé seulement à l'équipement
            item.tintable = tintable; item.unlockedByDefault = byDefault; item.unlockGroup = group;
            AssetDatabase.CreateAsset(item, itemFile);
        }

        private static GameObject P(PrimitiveType t, Transform parent, Vector3 pos, Vector3 scale, Material m)
        {
            var g = GameObject.CreatePrimitive(t);
            Object.DestroyImmediate(g.GetComponent<Collider>());   // l'habillage n'a pas de collision
            g.transform.SetParent(parent, false);
            g.transform.localPosition = pos;
            g.transform.localScale = scale;
            g.GetComponent<Renderer>().sharedMaterial = m;
            return g;
        }

        private static void Legs(Transform r, float h, float w, Material m)
        {
            P(PrimitiveType.Cube, r, new Vector3(-0.13f, 0f, 0f), new Vector3(w, h, 0.26f), m);
            P(PrimitiveType.Cube, r, new Vector3(0.13f, 0f, 0f), new Vector3(w, h, 0.26f), m);
        }

        private static void Feet(Transform r, float h, Material m)
        {
            P(PrimitiveType.Cube, r, new Vector3(-0.13f, h * 0.5f - 0.06f, 0.05f), new Vector3(0.2f, h, 0.34f), m);
            P(PrimitiveType.Cube, r, new Vector3(0.13f, h * 0.5f - 0.06f, 0.05f), new Vector3(0.2f, h, 0.34f), m);
        }

        // ---------- Personnage ----------

        private static GameObject BuildAvatarPrefab()
        {
            var existing = AssetDatabase.LoadAssetAtPath<GameObject>(AvatarPath);
            if (existing != null) return existing;

            var root = new GameObject("Avatar");
            var model = new GameObject("Modele").transform;     // contient corps + ancrages : le style les met à l'échelle ensemble
            model.SetParent(root.transform, false);

            var parts = new[]
            {
                P(PrimitiveType.Capsule, model, new Vector3(0f, 1.05f, 0f), new Vector3(0.5f, 0.5f, 0.35f), skin),     // torse
                P(PrimitiveType.Sphere, model, new Vector3(0f, 1.72f, 0f), new Vector3(0.34f, 0.36f, 0.34f), skin),    // tête
                P(PrimitiveType.Capsule, model, new Vector3(-0.13f, 0.4f, 0f), new Vector3(0.2f, 0.4f, 0.2f), skin),    // jambes
                P(PrimitiveType.Capsule, model, new Vector3(0.13f, 0.4f, 0f), new Vector3(0.2f, 0.4f, 0.2f), skin),
                P(PrimitiveType.Capsule, model, new Vector3(-0.36f, 1.15f, 0f), new Vector3(0.14f, 0.35f, 0.14f), skin), // bras
                P(PrimitiveType.Capsule, model, new Vector3(0.36f, 1.15f, 0f), new Vector3(0.14f, 0.35f, 0.14f), skin),
            };

            var anchorSpec = new (ClothingSlot slot, string name, Vector3 pos)[]
            {
                (ClothingSlot.Face, "Ancre_Visage", new Vector3(0f, 1.72f, 0f)),
                (ClothingSlot.Hair, "Ancre_Cheveux", new Vector3(0f, 1.85f, 0f)),
                (ClothingSlot.Top, "Ancre_Haut", new Vector3(0f, 1.2f, 0f)),
                (ClothingSlot.Bottom, "Ancre_Bas", new Vector3(0f, 0.7f, 0f)),
                (ClothingSlot.Shoes, "Ancre_Chaussures", new Vector3(0f, 0.06f, 0f)),
                (ClothingSlot.Accessory, "Ancre_Accessoire", new Vector3(0f, 1.72f, 0f)),
            };

            var customizer = root.AddComponent<AvatarCustomizer>();
            var so = new SerializedObject(customizer);

            var skinProp = so.FindProperty("skinRenderers");
            skinProp.arraySize = parts.Length;
            for (int i = 0; i < parts.Length; i++) skinProp.GetArrayElementAtIndex(i).objectReferenceValue = parts[i].GetComponent<Renderer>();
            so.FindProperty("bodyRoot").objectReferenceValue = model;

            var anchorsProp = so.FindProperty("anchors");
            anchorsProp.arraySize = anchorSpec.Length;
            for (int i = 0; i < anchorSpec.Length; i++)
            {
                var a = new GameObject(anchorSpec[i].name).transform;
                a.SetParent(model, false);
                a.localPosition = anchorSpec[i].pos;
                var el = anchorsProp.GetArrayElementAtIndex(i);
                el.FindPropertyRelative("slot").enumValueIndex = (int)anchorSpec[i].slot;
                el.FindPropertyRelative("anchor").objectReferenceValue = a;
            }
            so.ApplyModifiedPropertiesWithoutUndo();

            var prefab = PrefabUtility.SaveAsPrefabAsset(root, AvatarPath);
            Object.DestroyImmediate(root);
            return prefab;
        }

        private static void EnsureFolder(string path)
        {
            if (AssetDatabase.IsValidFolder(path)) return;
            int i = path.LastIndexOf('/');
            AssetDatabase.CreateFolder(path.Substring(0, i), path.Substring(i + 1));
        }
    }
}

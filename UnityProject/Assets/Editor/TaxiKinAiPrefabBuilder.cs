using UnityEditor;
using UnityEngine;
using TaxiKin.Vehicles;
using Object = UnityEngine.Object;

namespace TaxiKin.EditorTools
{
    /// <summary>
    /// Génère les modèles des véhicules d'IA, chargés à la demande depuis Resources :
    /// trafic (voiture/taxi, moto avec pilote, camion chargé, minibus bleu et jaune) et chauffeurs de course.
    /// Style « low-poly » : formes simples mais soignées (cabine, vitres, roues, chargement). Les modèles du trafic sont
    /// régénérés à chaque exécution (ce sont des fichiers produits, pas du travail à la main) ; de vrais modèles 3D les remplaceront
    /// sans changer le code.
    /// </summary>
    public static class TaxiKinAiPrefabBuilder
    {
        [MenuItem("Taxi Kin/Trafic/Générer les modèles d'IA")]
        public static void Generate()
        {
            foreach (var f in new[] { "Assets/Resources", "Assets/Resources/Traffic", "Assets/Resources/AI" })
                if (!AssetDatabase.IsValidFolder(f)) AssetDatabase.CreateFolder(f.Substring(0, f.LastIndexOf('/')), f.Substring(f.LastIndexOf('/') + 1));

            var white = TaxiKinSceneBuilder.GetMaterial("Mat_AiBody", Color.white);        // teinté à l'apparition
            var moto = TaxiKinSceneBuilder.GetMaterial("Mat_AiMoto", new Color(0.8f, 0.35f, 0.1f));
            var truck = TaxiKinSceneBuilder.GetMaterial("Mat_AiTruckBlue", new Color(0.12f, 0.33f, 0.66f));
            var mats = CreateMats();

            // Trafic : les voitures voient jusqu'à 28 m devant, les camions un peu plus
            var carSize = new Vector3(1.8f, 1.3f, 4.2f);
            var car = TaxiKinVehicleBuilder.MakeAi<TaxiController>("TrafficCar", carSize, white, true, 28f);
            DressCar(car, carSize, mats);
            Save("Assets/Resources/Traffic/TrafficCar.prefab", car, true);

            var motoSize = new Vector3(0.5f, 0.9f, 1.8f);
            var m = TaxiKinVehicleBuilder.MakeAi<WewaController>("TrafficMoto", motoSize, moto, false, 22f);
            DressMoto(m, motoSize, mats);
            Save("Assets/Resources/Traffic/TrafficMoto.prefab", m, true);

            var truckSize = new Vector3(2.4f, 2.6f, 8f);
            var t = TaxiKinVehicleBuilder.MakeAi<Bus207Controller>("TrafficTruck", truckSize, truck, false, 34f);
            DressTruck(t, truckSize, mats);
            Save("Assets/Resources/Traffic/TrafficTruck.prefab", t, true);

            var miniSize = new Vector3(2f, 2.3f, 5.4f);
            var mini = TaxiKinVehicleBuilder.MakeAi<Bus207Controller>("TrafficMinibus", miniSize, truck, false, 30f);
            DressMinibus(mini, miniSize, mats);
            Save("Assets/Resources/Traffic/TrafficMinibus.prefab", mini, true);

            // Coureurs : portée d'obstacle courte (ils se frôlent et se dépassent plutôt que de s'attendre)
            Save("Assets/Resources/AI/RaceWewa.prefab", TaxiKinVehicleBuilder.MakeAi<WewaController>("RaceWewa", motoSize, white, false, 10f), false);
            Save("Assets/Resources/AI/RaceTaxi.prefab", TaxiKinVehicleBuilder.MakeAi<TaxiController>("RaceTaxi", new Vector3(1.8f, 1.2f, 4f), white, true, 10f), false);
            AssetDatabase.SaveAssets();
        }

        internal struct Mats { public Material glass, tire, dark, yellow, orange, skin, shirt, cargoA, cargoB, cargoC; }

        internal static Mats CreateMats() => new Mats
        {
            glass = TaxiKinSceneBuilder.GetMaterial("Mat_Glass", new Color(0.12f, 0.17f, 0.24f)),
            tire = TaxiKinSceneBuilder.GetMaterial("Mat_Tire", new Color(0.08f, 0.08f, 0.09f)),
            dark = TaxiKinSceneBuilder.GetMaterial("Mat_Dark", new Color(0.18f, 0.18f, 0.2f)),
            yellow = TaxiKinSceneBuilder.GetMaterial("Mat_AiYellow", new Color(1f, 0.83f, 0f)),
            orange = TaxiKinSceneBuilder.GetMaterial("Mat_AiOrange", new Color(0.85f, 0.35f, 0.12f)),
            skin = TaxiKinSceneBuilder.GetMaterial("Mat_AiSkin", new Color(0.45f, 0.3f, 0.2f)),
            shirt = TaxiKinSceneBuilder.GetMaterial("Mat_AiShirt", new Color(0.9f, 0.9f, 0.88f)),
            cargoA = TaxiKinSceneBuilder.GetMaterial("Mat_CargoA", new Color(0.62f, 0.62f, 0.58f)),
            cargoB = TaxiKinSceneBuilder.GetMaterial("Mat_CargoB", new Color(0.36f, 0.45f, 0.33f)),
            cargoC = TaxiKinSceneBuilder.GetMaterial("Mat_CargoC", new Color(0.72f, 0.6f, 0.42f)),
        };

        // ---------- Habillage (le collider de la racine garde la taille d'origine) ----------

        /// <summary>Voiture/taxi : caisse basse, cabine vitrée avec pare-brise et lunette inclinés, phares, feux, roues.
        /// player = véhicule du joueur : le « Toit » (peint par la personnalisation) est conservé, les roues viennent d'ailleurs, lanterne TAXI.</summary>
        internal static void DressCar(GameObject root, Vector3 s, Mats m, bool player = false, bool taxiLantern = false)
        {
            var body = root.transform.Find("Carrosserie");
            body.localScale = new Vector3(s.x, s.y * 0.5f, s.z);
            body.localPosition = new Vector3(0f, -s.y * 0.5f + s.y * 0.5f * 0.5f + 0.2f, 0f);
            float cabH = s.y * 0.4f, cabY = body.localPosition.y + s.y * 0.25f + s.y * 0.2f;
            float cabLen = s.z * 0.4f;
            var roof = root.transform.Find("Toit");
            if (roof != null)
            {
                if (player) { roof.localPosition = new Vector3(0f, cabY + cabH * 0.5f + 0.03f, -s.z * 0.04f); roof.localScale = new Vector3(s.x * 0.84f, 0.07f, cabLen + 0.1f); }
                else Object.DestroyImmediate(roof.gameObject);
            }
            Prim(PrimitiveType.Cube, "Cabine", root.transform, new Vector3(0f, cabY, -s.z * 0.04f), new Vector3(s.x * 0.86f, cabH, cabLen), m.glass);
            if (!player) Prim(PrimitiveType.Cube, "Pavillon", root.transform, new Vector3(0f, cabY + cabH * 0.5f + 0.02f, -s.z * 0.04f), new Vector3(s.x * 0.88f, 0.06f, cabLen), m.dark);
            // pare-brise et lunette arrière inclinés : la silhouette n'est plus une boîte
            var ws = Prim(PrimitiveType.Cube, "PareBrise", root.transform, new Vector3(0f, cabY - 0.02f, -s.z * 0.04f + cabLen * 0.5f + 0.1f), new Vector3(s.x * 0.84f, cabH * 1.15f, 0.05f), m.glass);
            ws.transform.localRotation = Quaternion.Euler(-32f, 0f, 0f);
            var rw = Prim(PrimitiveType.Cube, "Lunette", root.transform, new Vector3(0f, cabY - 0.02f, -s.z * 0.04f - cabLen * 0.5f - 0.1f), new Vector3(s.x * 0.84f, cabH * 1.15f, 0.05f), m.glass);
            rw.transform.localRotation = Quaternion.Euler(32f, 0f, 0f);
            Wheels(root.transform, s, player ? Mathf.Min(0.38f, s.y * 0.35f) : 0.36f, s.x * 0.5f, new[] { s.z * 0.32f, -s.z * 0.32f }, m.tire, player);
            // phares et feux, pare-chocs
            Prim(PrimitiveType.Cube, "Phares", root.transform, new Vector3(0f, body.localPosition.y + 0.05f, s.z * 0.5f + 0.01f), new Vector3(s.x * 0.8f, 0.14f, 0.04f), m.shirt);
            Prim(PrimitiveType.Cube, "Feux", root.transform, new Vector3(0f, body.localPosition.y + 0.05f, -s.z * 0.5f - 0.01f), new Vector3(s.x * 0.8f, 0.14f, 0.04f), m.orange);
            Prim(PrimitiveType.Cube, "PareChocs", root.transform, new Vector3(0f, body.localPosition.y - 0.18f, s.z * 0.5f + 0.03f), new Vector3(s.x * 0.95f, 0.14f, 0.1f), m.dark);
            if (taxiLantern)
            {   // lanterne « TAXI » sur le toit
                float ly = cabY + cabH * 0.5f + 0.2f;
                var box = Prim(PrimitiveType.Cube, "Lanterne", root.transform, new Vector3(0f, ly, -s.z * 0.04f), new Vector3(0.55f, 0.16f, 0.22f), m.yellow);
                var font = Resources.GetBuiltinResource<Font>("LegacyRuntime.ttf");
                foreach (int side in new[] { -1, 1 })
                {
                    var tg = new GameObject("TexteTaxi");
                    tg.transform.SetParent(box.transform, false);
                    tg.transform.localPosition = new Vector3(0f, 0f, side * -0.52f);
                    tg.transform.localScale = new Vector3(1f / 0.55f, 1f / 0.16f, 1f / 0.22f);
                    tg.transform.localRotation = Quaternion.Euler(0f, side > 0 ? 0f : 180f, 0f);
                    var tm = tg.AddComponent<TextMesh>();
                    tm.font = font; tm.anchor = TextAnchor.MiddleCenter; tm.alignment = TextAlignment.Center;
                    tm.fontSize = 100; tm.characterSize = 0.008f; tm.color = Color.black; tm.text = "TAXI";
                    tg.GetComponent<MeshRenderer>().sharedMaterial = font.material;
                }
            }
        }

        /// <summary>Moto avec pilote : cadre fin, roues, pilote (gilet clair, casque jaune).</summary>
        internal static void DressMoto(GameObject root, Vector3 s, Mats m, bool withRider = true, bool player = false)
        {
            var body = root.transform.Find("Carrosserie");
            body.localScale = new Vector3(0.3f, 0.35f, 1.4f);
            body.localPosition = new Vector3(0f, -s.y * 0.5f + 0.55f, 0f);
            Wheels(root.transform, s, player ? Mathf.Min(0.38f, s.y * 0.35f) : 0.34f, 0f, new[] { 0.62f, -0.62f }, m.tire, player);
            Prim(PrimitiveType.Cube, "Reservoir", root.transform, new Vector3(0f, 0.05f, 0.2f), new Vector3(0.28f, 0.22f, 0.5f), m.yellow);
            Prim(PrimitiveType.Sphere, "Phare", root.transform, new Vector3(0f, 0.28f, 0.72f), new Vector3(0.2f, 0.2f, 0.16f), m.shirt);
            Prim(PrimitiveType.Cube, "Garde", root.transform, new Vector3(0f, -0.1f, -0.78f), new Vector3(0.1f, 0.08f, 0.5f), m.dark);
            Prim(PrimitiveType.Cube, "Guidon", root.transform, new Vector3(0f, 0.3f, 0.5f), new Vector3(0.55f, 0.05f, 0.05f), m.dark);
            if (!withRider) return;
            Prim(PrimitiveType.Cube, "Torse", root.transform, new Vector3(0f, 0.3f, -0.1f), new Vector3(0.42f, 0.55f, 0.3f), m.shirt);
            Prim(PrimitiveType.Sphere, "Tete", root.transform, new Vector3(0f, 0.72f, -0.05f), new Vector3(0.24f, 0.26f, 0.26f), m.skin);
            Prim(PrimitiveType.Sphere, "Casque", root.transform, new Vector3(0f, 0.78f, -0.05f), new Vector3(0.29f, 0.24f, 0.3f), m.yellow);
        }

        /// <summary>Camion chargé : cabine orange, plateau bleu, pile de sacs/caisses aux couleurs variées.</summary>
        private static void DressTruck(GameObject root, Vector3 s, Mats m)
        {
            var body = root.transform.Find("Carrosserie");                       // châssis bas
            body.localScale = new Vector3(s.x * 0.92f, 0.5f, s.z);
            body.localPosition = new Vector3(0f, -s.y * 0.5f + 0.75f, 0f);
            float baseY = body.localPosition.y + 0.25f;
            Prim(PrimitiveType.Cube, "Cabine", root.transform, new Vector3(0f, baseY + 0.8f, s.z * 0.5f - 1.1f), new Vector3(s.x * 0.95f, 1.6f, 2.2f), m.orange);
            Prim(PrimitiveType.Cube, "Pare-brise", root.transform, new Vector3(0f, baseY + 1.1f, s.z * 0.5f + 0.01f), new Vector3(s.x * 0.8f, 0.7f, 0.05f), m.glass);
            Prim(PrimitiveType.Cube, "Plateau", root.transform, new Vector3(0f, baseY + 0.3f, -1.1f), new Vector3(s.x, 0.6f, s.z - 3.0f), m.dark);
            // chargement : trois piles de blocs de couleurs différentes
            Material[] cargo = { m.cargoA, m.cargoB, m.cargoC };
            for (int i = 0; i < 3; i++)
                Prim(PrimitiveType.Cube, "Charge" + i, root.transform,
                     new Vector3((i - 1) * 0.1f, baseY + 0.6f + 0.55f + (i == 1 ? 0.45f : 0f), -0.4f - i * 1.5f),
                     new Vector3(s.x * 0.86f, 1.1f + (i == 1 ? 0.9f : 0f), 1.3f), cargo[i]);
            Wheels(root.transform, s, 0.5f, s.x * 0.5f, new[] { s.z * 0.38f, -s.z * 0.18f, -s.z * 0.34f }, m.tire);
        }

        /// <summary>Minibus bleu et jaune : bas bleu, bande de vitres, haut jaune, pare-brise.</summary>
        internal static void DressMinibus(GameObject root, Vector3 s, Mats m, bool player = false)
        {
            var body = root.transform.Find("Carrosserie");                       // bas bleu
            body.localScale = new Vector3(s.x, s.y * 0.45f, s.z);
            body.localPosition = new Vector3(0f, -s.y * 0.5f + s.y * 0.225f + 0.25f, 0f);
            float topY = body.localPosition.y + s.y * 0.225f;
            Prim(PrimitiveType.Cube, "Vitres", root.transform, new Vector3(0f, topY + s.y * 0.15f, 0f), new Vector3(s.x * 1.01f, s.y * 0.3f, s.z * 0.86f), m.glass);
            Prim(PrimitiveType.Cube, "Haut", root.transform, new Vector3(0f, topY + s.y * 0.3f + 0.1f, 0f), new Vector3(s.x * 0.98f, 0.2f, s.z * 0.98f), m.yellow);
            Prim(PrimitiveType.Cube, "Bandeau", root.transform, new Vector3(0f, topY + 0.04f, 0f), new Vector3(s.x * 1.02f, 0.1f, s.z * 0.99f), m.yellow);
            Prim(PrimitiveType.Cube, "PareBrise", root.transform, new Vector3(0f, topY + s.y * 0.15f, s.z * 0.5f + 0.01f), new Vector3(s.x * 0.86f, s.y * 0.3f, 0.05f), m.glass);
            Wheels(root.transform, s, player ? 0.38f : 0.4f, s.x * 0.5f, new[] { s.z * 0.32f, -s.z * 0.3f }, m.tire, player);
        }

        private static void Wheels(Transform parent, Vector3 s, float radius, float x, float[] zs, Material tire, bool thin = false)
        {
            foreach (float z in zs)
                foreach (float sx in (x > 0.01f ? new[] { -x, x } : new[] { 0f }))
                {
                    var w = Prim(PrimitiveType.Cylinder, "Roue", parent, new Vector3(sx, -s.y * 0.5f + radius, z), new Vector3(radius * 2f, thin ? 0.08f : (x > 0.01f ? 0.14f : 0.1f), radius * 2f), tire);
                    w.transform.localRotation = Quaternion.Euler(0f, 0f, 90f);   // axe horizontal
                }
        }

        internal static GameObject Prim(PrimitiveType t, string name, Transform parent, Vector3 pos, Vector3 scale, Material mat)
        {
            var g = GameObject.CreatePrimitive(t);
            g.name = name;
            Object.DestroyImmediate(g.GetComponent<Collider>());   // seule la racine porte la collision
            g.transform.SetParent(parent, false);
            g.transform.localPosition = pos;
            g.transform.localScale = scale;
            g.GetComponent<Renderer>().sharedMaterial = mat;
            return g;
        }

        private static void Save(string path, GameObject go, bool overwrite)
        {
            if (overwrite || AssetDatabase.LoadAssetAtPath<GameObject>(path) == null) PrefabUtility.SaveAsPrefabAsset(go, path);
            Object.DestroyImmediate(go);
        }
    }
}

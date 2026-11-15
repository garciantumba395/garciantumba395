using UnityEditor;
using UnityEditor.SceneManagement;
using UnityEngine;
using UnityEngine.Rendering;
using TaxiKin.Core;
using TaxiKin.Player;
using TaxiKin.Vehicles;
using TaxiKin.Missions;
using TaxiKin.Economy;
using TaxiKin.UI;
using TaxiKin.Traffic;
using TaxiKin.Weather;
using TaxiKin.SaveSystem;
using TaxiKin.Ads;
using TaxiKin.Purchases;

namespace TaxiKin.EditorTools
{
    /// <summary>
    /// Menu « Taxi Kin → Créer la scène de test Main » : génère Assets/Scenes/Main.unity
    /// avec des formes simples (cubes, capsules, plans) et tous les scripts déjà câblés.
    /// </summary>
    public static class TaxiKinSceneBuilder
    {
        [MenuItem("Taxi Kin/Créer la scène de test Main")]
        public static void BuildMainScene()
        {
            var scene = EditorSceneManager.NewScene(NewSceneSetup.DefaultGameObjects, NewSceneMode.Single);

            // --- Matériaux de test (légers, partagés) ---
            Material yellow = GetMaterial("Mat_TaxiYellow", VehicleColorManager.DefaultTaxiColor);
            Material road = GetMaterial("Mat_Road", new Color(0.2f, 0.2f, 0.22f));
            Material ground = GetMaterial("Mat_Ground", new Color(0.35f, 0.45f, 0.3f));
            Material wewaMat = GetMaterial("Mat_Wewa", new Color(0.85f, 0.2f, 0.15f));
            Material busMat = GetMaterial("Mat_Bus207", new Color(0.2f, 0.4f, 0.8f));
            Material skin = GetMaterial("Mat_Skin", new Color(0.55f, 0.37f, 0.25f));

            // --- Managers (objet racine unique) ---
            var managers = new GameObject("Managers");
            managers.AddComponent<GameManager>();
            managers.AddComponent<SaveManager>();
            managers.AddComponent<EconomyManager>();
            managers.AddComponent<ProfileManager>();
            managers.AddComponent<MissionManager>();
            managers.AddComponent<PassengerManager>();
            managers.AddComponent<TrafficManager>();
            managers.AddComponent<WeatherManager>();
            managers.AddComponent<AdManager>();
            managers.AddComponent<PurchaseManager>();
            managers.AddComponent<UIManager>();
            managers.AddComponent<VehicleColorManager>();
            managers.AddComponent<GarageManager>();
            managers.AddComponent<GarageUIManager>();
            managers.AddComponent<CharacterCreatorManager>();

            // --- Décor : sol et route (plans) ---
            var groundGo = GameObject.CreatePrimitive(PrimitiveType.Plane);
            groundGo.name = "Sol";
            groundGo.transform.localScale = new Vector3(10f, 1f, 10f);
            groundGo.GetComponent<Renderer>().sharedMaterial = ground;

            var roadGo = GameObject.CreatePrimitive(PrimitiveType.Plane);
            roadGo.name = "Route";
            roadGo.transform.position = new Vector3(0f, 0.01f, 0f);
            roadGo.transform.localScale = new Vector3(2f, 1f, 10f);
            roadGo.GetComponent<Renderer>().sharedMaterial = road;
            Object.DestroyImmediate(roadGo.GetComponent<Collider>()); // Un seul collider de sol suffit

            // --- Véhicules (cubes) ---
            var wewa = MakeVehicle<WewaController>("Wewa", new Vector3(-6f, 0.6f, 0f), new Vector3(0.5f, 0.9f, 1.8f), wewaMat);
            var taxi = MakeVehicle<TaxiController>("Taxi", new Vector3(0f, 0.7f, 0f), new Vector3(1.8f, 1.2f, 4f), yellow);
            var bus = MakeVehicle<Bus207Controller>("Bus207", new Vector3(6f, 1.5f, 0f), new Vector3(2.5f, 2.8f, 9f), busMat);

            // Toit du taxi (petit cube) pour tester la couleur du toit
            var roof = GameObject.CreatePrimitive(PrimitiveType.Cube);
            roof.name = "Toit";
            roof.transform.SetParent(taxi.transform, false);
            roof.transform.localPosition = new Vector3(0f, 0.65f, 0f);
            roof.transform.localScale = new Vector3(0.9f, 0.2f, 0.5f);
            Object.DestroyImmediate(roof.GetComponent<Collider>());
            roof.GetComponent<Renderer>().sharedMaterial = yellow;

            var customizer = taxi.AddComponent<VehicleCustomizer>();
            Assign(customizer, "bodyRenderer", taxi.GetComponent<Renderer>());
            Assign(customizer, "roofRenderer", roof.GetComponent<Renderer>());

            // --- Joueur : contrôle le taxi par défaut ---
            var player = new GameObject("Player");
            var pc = player.AddComponent<PlayerController>();
            Assign(pc, "vehicle", taxi.GetComponent<TaxiController>());

            // --- Avatar de test (capsule) ---
            var avatar = GameObject.CreatePrimitive(PrimitiveType.Capsule);
            avatar.name = "Avatar";
            avatar.transform.position = new Vector3(-3f, 1f, 4f);
            avatar.GetComponent<Renderer>().sharedMaterial = skin;
            var ac = avatar.AddComponent<AvatarCustomizer>();
            Assign(ac, "skinRenderer", avatar.GetComponent<Renderer>());
            Assign(managers.GetComponent<CharacterCreatorManager>(), "previewAvatar", ac);

            // --- Caméra ---
            var cam = Camera.main;
            if (cam != null)
            {
                cam.transform.position = new Vector3(0f, 6f, -14f);
                cam.transform.LookAt(new Vector3(0f, 1f, 0f));
            }

            // --- Sauvegarde de la scène ---
            const string path = "Assets/Scenes/Main.unity";
            EditorSceneManager.SaveScene(scene, path);
            EditorBuildSettings.scenes = new[] { new EditorBuildSettingsScene(path, true) };
            AssetDatabase.SaveAssets();
            Debug.Log("[Taxi Kin] Scène Main créée : " + path);
        }

        private static GameObject MakeVehicle<T>(string name, Vector3 pos, Vector3 scale, Material mat) where T : VehicleController
        {
            var go = GameObject.CreatePrimitive(PrimitiveType.Cube);
            go.name = name;
            go.transform.position = pos;
            go.transform.localScale = scale;
            go.GetComponent<Renderer>().sharedMaterial = mat;
            go.AddComponent<T>(); // Ajoute aussi le Rigidbody (RequireComponent)
            return go;
        }

        private static Material GetMaterial(string name, Color color)
        {
            string path = $"Assets/Materials/{name}.mat";
            var mat = AssetDatabase.LoadAssetAtPath<Material>(path);
            if (mat != null) return mat;

            // URP si le projet l'utilise, sinon Standard
            Shader shader = GraphicsSettings.currentRenderPipeline != null
                ? Shader.Find("Universal Render Pipeline/Lit")
                : Shader.Find("Standard");
            mat = new Material(shader) { color = color };
            AssetDatabase.CreateAsset(mat, path);
            return mat;
        }

        /// <summary>Renseigne un champ [SerializeField] privé par son nom.</summary>
        private static void Assign(Object target, string field, Object value)
        {
            var so = new SerializedObject(target);
            var prop = so.FindProperty(field);
            if (prop == null) { Debug.LogWarning($"Champ introuvable : {field}"); return; }
            prop.objectReferenceValue = value;
            so.ApplyModifiedPropertiesWithoutUndo();
        }
    }
}

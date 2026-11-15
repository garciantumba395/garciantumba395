using System.IO;
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
using TaxiKin.Map;

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

            // --- Missions (assets ScriptableObject) : générées avant la scène pour être chargées au Play ---
            TaxiKinMissionBuilder.Generate();

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
            managers.AddComponent<ShopManager>();
            managers.AddComponent<WorldManager>();
            managers.AddComponent<OutOfBoundsGuard>();
            managers.AddComponent<RaceManager>();
            managers.AddComponent<QualityManager>();
            managers.AddComponent<PerformanceMonitor>();
            managers.AddComponent<TaxiKin.Audio.AudioManager>();
            managers.AddComponent<TaxiKin.Audio.HapticsManager>();
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

            // --- Monde de Kinshasa : si world.json existe, le sol de test est inutile (les zones et corridors fournissent le sol) ---
            bool hasWorld = File.Exists("Assets/Resources/Zones/world.json");
            if (hasWorld)
            {
                Object.DestroyImmediate(groundGo);
                Object.DestroyImmediate(roadGo);
            }

            // --- Véhicules : racine physique non déformée + carrosserie, jantes, autocollants, plaque ---
            var wewa = TaxiKinVehicleBuilder.Make<WewaController>("Wewa", new Vector3(-6f, 0.6f, 0f), new Vector3(0.5f, 0.9f, 1.8f), wewaMat, false);
            var taxi = TaxiKinVehicleBuilder.Make<TaxiController>("Taxi", new Vector3(0f, 0.7f, 0f), new Vector3(1.8f, 1.2f, 4f), yellow, true);
            var bus = TaxiKinVehicleBuilder.Make<Bus207Controller>("Bus207", new Vector3(6f, 1.5f, 0f), new Vector3(2.5f, 2.8f, 9f), busMat, false);

            // --- Prefabs d'aperçu du garage (chargés à la demande depuis Resources) ---
            TaxiKinVehicleBuilder.BuildPreviewPrefabs(wewaMat, yellow, busMat);

            // --- Joueur : commence avec le Wewa, peut passer au taxi et au bus ---
            var player = new GameObject("Player");
            var pc = player.AddComponent<PlayerController>();
            Assign(pc, "vehicle", wewa.GetComponent<WewaController>());
            var list = new VehicleController[]
                { wewa.GetComponent<WewaController>(), taxi.GetComponent<TaxiController>(), bus.GetComponent<Bus207Controller>() };
            var so = new SerializedObject(pc);
            var arr = so.FindProperty("availableVehicles");
            arr.arraySize = list.Length;
            for (int i = 0; i < list.Length; i++) arr.GetArrayElementAtIndex(i).objectReferenceValue = list[i];
            so.ApplyModifiedPropertiesWithoutUndo();

            // --- Avatar (prefab généré) affiché dans le monde : suit le profil du joueur ---
            var avatarPrefab = TaxiKinAvatarContentBuilder.Generate();
            var avatar = (GameObject)PrefabUtility.InstantiatePrefab(avatarPrefab);
            avatar.name = "Avatar";
            avatar.transform.position = new Vector3(-3f, 0f, 4f);
            avatar.AddComponent<AvatarDisplay>();

            // --- Aperçu 3D de « Mon chauffeur » : petite scène loin du monde, filmée vers une texture ---
            var stageGo = new GameObject("ScenePreviewAvatar");
            stageGo.transform.position = new Vector3(1000f, 0f, 1000f);
            var turntable = new GameObject("Plateau");
            turntable.transform.SetParent(stageGo.transform, false);
            var previewAvatar = (GameObject)PrefabUtility.InstantiatePrefab(avatarPrefab);
            previewAvatar.name = "AvatarApercu";
            previewAvatar.transform.SetParent(turntable.transform, false);
            var previewCamGo = new GameObject("CameraApercu");
            previewCamGo.transform.SetParent(stageGo.transform, false);
            previewCamGo.transform.localPosition = new Vector3(0f, 1.05f, 3.4f);
            previewCamGo.transform.LookAt(stageGo.transform.position + new Vector3(0f, 0.95f, 0f));
            var previewCam = previewCamGo.AddComponent<Camera>();
            previewCam.fieldOfView = 28f;
            previewCam.clearFlags = CameraClearFlags.SolidColor;
            previewCam.backgroundColor = new Color(0.12f, 0.13f, 0.16f);
            var stage = stageGo.AddComponent<AvatarPreviewStage>();
            Assign(stage, "previewCamera", previewCam);
            Assign(stage, "turntable", turntable.transform);
            Assign(stage, "avatar", previewAvatar.GetComponent<AvatarCustomizer>());
            Assign(managers.GetComponent<CharacterCreatorManager>(), "stage", stage);

            // --- Profil, quêtes de personnalisation ---
            Assign(managers.GetComponent<ProfileManager>(), "missions", managers.GetComponent<MissionManager>());
            var quests = managers.AddComponent<CustomizationQuests>();
            Assign(quests, "missions", managers.GetComponent<MissionManager>());
            Assign(quests, "taxiCustomizer", taxi.GetComponent<VehicleCustomizer>());

            // --- Garage : aperçu 3D du véhicule (petite scène loin du monde), liste des véhicules, boutique ---
            var vStageGo = new GameObject("ScenePreviewVehicule");
            vStageGo.transform.position = new Vector3(2000f, 0f, 2000f);
            var vTurn = new GameObject("Plateau");
            vTurn.transform.SetParent(vStageGo.transform, false);
            var vAnchor = new GameObject("Ancrage");
            vAnchor.transform.SetParent(vTurn.transform, false);
            var vCamGo = new GameObject("CameraApercuVehicule");
            vCamGo.transform.SetParent(vStageGo.transform, false);
            vCamGo.transform.localPosition = new Vector3(6f, 2.6f, 9f);
            vCamGo.transform.LookAt(vStageGo.transform.position + new Vector3(0f, 1.1f, 0f));
            var vCam = vCamGo.AddComponent<Camera>();
            vCam.fieldOfView = 30f;
            vCam.clearFlags = CameraClearFlags.SolidColor;
            vCam.backgroundColor = new Color(0.12f, 0.13f, 0.16f);
            var vStage = vStageGo.AddComponent<VehiclePreviewStage>();
            Assign(vStage, "previewCamera", vCam);
            Assign(vStage, "turntable", vTurn.transform);

            var garageMgr = managers.GetComponent<GarageManager>();
            Assign(garageMgr, "previewAnchor", vAnchor.transform);
            Assign(garageMgr, "stage", vStage);
            Assign(garageMgr, "player", pc);
            var garageEntries = new (string id, string title, VehicleType type, float scale, int bonus, bool free)[]
            {
                ("wewa_base", "Wewa", VehicleType.Wewa, 2.0f, 0, true),
                ("wewa_upgraded", "Wewa amélioré", VehicleType.Wewa, 2.0f, 1, false),
                ("taxi", "Taxi-voiture", VehicleType.Taxi, 1.1f, 0, false),
                ("taxi_premium", "Taxi premium", VehicleType.Taxi, 1.1f, 1, false),
                ("bus207", "Bus 207", VehicleType.Bus207, 0.6f, 0, false),
            };
            var gso = new SerializedObject(garageMgr);
            var gArr = gso.FindProperty("vehicles");
            gArr.arraySize = garageEntries.Length;
            for (int i = 0; i < garageEntries.Length; i++)
            {
                var el = gArr.GetArrayElementAtIndex(i);
                el.FindPropertyRelative("id").stringValue = garageEntries[i].id;
                el.FindPropertyRelative("title").stringValue = garageEntries[i].title;
                el.FindPropertyRelative("type").enumValueIndex = (int)garageEntries[i].type;
                el.FindPropertyRelative("previewResourcePath").stringValue = "Vehicles/Preview/" + garageEntries[i].id;
                el.FindPropertyRelative("previewScale").floatValue = garageEntries[i].scale;
                el.FindPropertyRelative("bonusLevel").intValue = garageEntries[i].bonus;
                el.FindPropertyRelative("unlockedByDefault").boolValue = garageEntries[i].free;
            }
            gso.ApplyModifiedPropertiesWithoutUndo();

            var shopMgr = managers.GetComponent<ShopManager>();
            Assign(shopMgr, "purchases", managers.GetComponent<PurchaseManager>());
            Assign(shopMgr, "missions", managers.GetComponent<MissionManager>());
            Assign(shopMgr, "player", pc);
            Assign(shopMgr, "ads", managers.GetComponent<AdManager>());
            Assign(managers.GetComponent<GarageUIManager>(), "player", pc);

            // --- Destination de test (cylindre) pour la flèche de direction et la distance ---
            var dest = GameObject.CreatePrimitive(PrimitiveType.Cylinder);
            dest.name = "Destination";
            dest.transform.position = new Vector3(0f, 1f, 80f);
            dest.transform.localScale = new Vector3(3f, 1f, 3f);
            Object.DestroyImmediate(dest.GetComponent<Collider>());
            dest.GetComponent<Renderer>().sharedMaterial = wewaMat;

            // --- Point de départ de repli des missions (tant que la vraie carte n'est pas chargée) ---
            var startPoint = new GameObject("DepartMission");
            startPoint.transform.position = new Vector3(0f, 0.6f, 0f);

            // --- Caméra principale + 4 points de vue ---
            var cam = Camera.main;
            CameraRig rig = null;
            if (cam != null)
            {
                rig = cam.gameObject.AddComponent<CameraRig>();
                Assign(rig, "cam", cam);
                Assign(rig, "player", pc);
            }

            // --- Trafic et chauffeurs de course : modèles d'IA (Resources) + câblage ---
            TaxiKinAiPrefabBuilder.Generate();
            var trafficMgr = managers.GetComponent<TrafficManager>();
            var raceMgr = managers.GetComponent<RaceManager>();

            // --- Monde : zones chargées par proximité, corridors, guidage ---
            var worldMgr = managers.GetComponent<WorldManager>();
            Assign(worldMgr, "player", pc);
            Assign(worldMgr, "roadMaterial", road);
            Assign(worldMgr, "buildingMaterial", GetMaterial("Mat_Building", new Color(0.78f, 0.7f, 0.58f)));
            Assign(worldMgr, "groundMaterial", ground);
            Assign(worldMgr, "corridorGroundMaterial", GetMaterial("Mat_CorridorGround", new Color(0.42f, 0.5f, 0.34f)));
            Assign(worldMgr, "decorMaterial", GetMaterial("Mat_Barrier", new Color(0.9f, 0.9f, 0.88f)));
            Assign(managers.GetComponent<MissionManager>(), "world", worldMgr);
            Assign(managers.GetComponent<MissionManager>(), "race", raceMgr);
            Assign(trafficMgr, "player", pc);
            Assign(trafficMgr, "world", worldMgr);
            Assign(trafficMgr, "missions", managers.GetComponent<MissionManager>());
            Assign(raceMgr, "player", pc);
            Assign(raceMgr, "world", worldMgr);
            Assign(managers.GetComponent<OutOfBoundsGuard>(), "player", pc);

            // --- Qualité graphique, son, vibrations ---
            var sunLight = Object.FindObjectOfType<Light>();
            var quality = managers.GetComponent<QualityManager>();
            Assign(quality, "sun", sunLight);
            if (cam != null) Assign(quality, "mainCamera", cam);
            Assign(quality, "traffic", managers.GetComponent<TrafficManager>());

            var audio = managers.GetComponent<TaxiKin.Audio.AudioManager>();
            var musicGo = new GameObject("Musique", typeof(AudioSource));
            var sfxGo = new GameObject("Effets", typeof(AudioSource));
            musicGo.transform.SetParent(managers.transform, false);
            sfxGo.transform.SetParent(managers.transform, false);
            musicGo.GetComponent<AudioSource>().playOnAwake = false;
            sfxGo.GetComponent<AudioSource>().playOnAwake = false;
            Assign(audio, "musicSource", musicGo.GetComponent<AudioSource>());
            Assign(audio, "sfxSource", sfxGo.GetComponent<AudioSource>());
            Assign(audio, "player", pc);
            var haptics = managers.GetComponent<TaxiKin.Audio.HapticsManager>();
            Assign(haptics, "player", pc);
            Assign(haptics, "missions", managers.GetComponent<MissionManager>());

            // --- Minimap (caméra du dessus, texture 256x256) ---
            var miniGo = new GameObject("MinimapCamera");
            var miniCam = miniGo.AddComponent<Camera>();
            miniCam.orthographic = true;
            miniCam.orthographicSize = 40f;
            miniCam.clearFlags = CameraClearFlags.SolidColor;
            miniCam.backgroundColor = new Color(0.1f, 0.12f, 0.1f);
            var mini = miniGo.AddComponent<MinimapCamera>();
            Assign(mini, "player", pc);

            // --- Interface tactile (Canvas, volant, pédales, HUD) ---
            TaxiKinUIBuilder.Build(managers, pc, managers.GetComponent<PassengerManager>(),
                managers.GetComponent<MissionManager>(), rig, miniCam, dest.transform);
            TaxiKinCustomizationUIBuilder.Build(GameObject.Find("Canvas/ZoneSure").transform, managers, pc, stage);
            TaxiKinGarageShopUIBuilder.Build(GameObject.Find("Canvas/ZoneSure").transform, managers, pc, vStage);
            TaxiKinSettingsUIBuilder.Build(GameObject.Find("Canvas/ZoneSure").transform, managers);
            Assign(managers.GetComponent<ControlActions>(), "world", worldMgr);
            TaxiKinMenuUIBuilder.Build(GameObject.Find("Canvas/ZoneSure").transform, managers, stage);   // en dernier : place le menu sous les autres écrans

            // --- Sauvegarde de la scène ---
            const string path = "Assets/Scenes/Main.unity";
            Assign(managers.GetComponent<MissionManager>(), "fallbackStart", startPoint.transform);
            EditorSceneManager.SaveScene(scene, path);
            EditorBuildSettings.scenes = new[] { new EditorBuildSettingsScene(path, true) };
            AssetDatabase.SaveAssets();
            Debug.Log("[Taxi Kin] Scène Main créée : " + path);
        }

        internal static Material GetMaterial(string name, Color color)
        {
            string path = $"Assets/Materials/{name}.mat";
            var mat = AssetDatabase.LoadAssetAtPath<Material>(path);
            if (mat != null) return mat;

            // URP si le projet l'utilise, sinon Standard
            // Sur Android modeste : « Simple Lit » (URP) est nettement moins coûteux que « Lit » ; sinon Standard
            Shader shader = null;
            if (GraphicsSettings.currentRenderPipeline != null)
            {
                shader = Shader.Find("Universal Render Pipeline/Simple Lit");
                if (shader == null) shader = Shader.Find("Universal Render Pipeline/Lit");
            }
            if (shader == null) shader = Shader.Find("Standard");
            mat = new Material(shader) { color = color, enableInstancing = true };
            AssetDatabase.CreateAsset(mat, path);
            return mat;
        }

        /// <summary>Renseigne un tableau [SerializeField] privé par son nom.</summary>
        internal static void AssignArray(Object target, string field, Object[] values)
        {
            var so = new SerializedObject(target);
            var prop = so.FindProperty(field);
            if (prop == null) { Debug.LogWarning($"Champ introuvable : {field}"); return; }
            prop.arraySize = values.Length;
            for (int i = 0; i < values.Length; i++) prop.GetArrayElementAtIndex(i).objectReferenceValue = values[i];
            so.ApplyModifiedPropertiesWithoutUndo();
        }

        /// <summary>Renseigne un champ [SerializeField] privé par son nom.</summary>
        internal static void Assign(Object target, string field, Object value)
        {
            var so = new SerializedObject(target);
            var prop = so.FindProperty(field);
            if (prop == null) { Debug.LogWarning($"Champ introuvable : {field}"); return; }
            prop.objectReferenceValue = value;
            so.ApplyModifiedPropertiesWithoutUndo();
        }
    }
}

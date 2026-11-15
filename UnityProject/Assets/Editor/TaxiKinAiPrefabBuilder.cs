using UnityEditor;
using UnityEngine;
using TaxiKin.Vehicles;
using Object = UnityEngine.Object;

namespace TaxiKin.EditorTools
{
    /// <summary>
    /// Génère les modèles des véhicules d'IA, chargés à la demande depuis Resources :
    /// trafic (voiture, moto, camion) et chauffeurs de course (Wewa, taxi). Un modèle existant n'est jamais écrasé.
    /// Formes simples (cubes) : de vrais modèles les remplaceront sans changer le code.
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
            var truck = TaxiKinSceneBuilder.GetMaterial("Mat_AiTruck", new Color(0.35f, 0.4f, 0.5f));

            // Trafic : les voitures voient jusqu'à 28 m devant, les camions un peu plus
            Save("Assets/Resources/Traffic/TrafficCar.prefab", TaxiKinVehicleBuilder.MakeAi<TaxiController>("TrafficCar", new Vector3(1.8f, 1.3f, 4.2f), white, true, 28f));
            Save("Assets/Resources/Traffic/TrafficMoto.prefab", TaxiKinVehicleBuilder.MakeAi<WewaController>("TrafficMoto", new Vector3(0.5f, 0.9f, 1.8f), moto, false, 22f));
            Save("Assets/Resources/Traffic/TrafficTruck.prefab", TaxiKinVehicleBuilder.MakeAi<Bus207Controller>("TrafficTruck", new Vector3(2.4f, 2.6f, 8f), truck, false, 34f));

            // Coureurs : portée d'obstacle courte (ils se frôlent et se dépassent plutôt que de s'attendre)
            Save("Assets/Resources/AI/RaceWewa.prefab", TaxiKinVehicleBuilder.MakeAi<WewaController>("RaceWewa", new Vector3(0.5f, 0.9f, 1.8f), white, false, 10f));
            Save("Assets/Resources/AI/RaceTaxi.prefab", TaxiKinVehicleBuilder.MakeAi<TaxiController>("RaceTaxi", new Vector3(1.8f, 1.2f, 4f), white, true, 10f));
            AssetDatabase.SaveAssets();
        }

        private static void Save(string path, GameObject go)
        {
            if (AssetDatabase.LoadAssetAtPath<GameObject>(path) == null) PrefabUtility.SaveAsPrefabAsset(go, path);
            Object.DestroyImmediate(go);
        }
    }
}

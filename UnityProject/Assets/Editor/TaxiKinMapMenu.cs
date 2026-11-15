using UnityEditor;
using UnityEngine;
using TaxiKin.Map;

namespace TaxiKin.EditorTools
{
    /// <summary>Menu « Taxi Kin → Carte → Ajouter une zone à la scène » : crée un ZoneLoader avec des matériaux de test.</summary>
    public static class TaxiKinMapMenu
    {
        [MenuItem("Taxi Kin/Carte/Ajouter une zone à la scène")]
        public static void AddZoneLoader()
        {
            var go = new GameObject("Zone");
            var loader = go.AddComponent<ZoneLoader>();
            TaxiKinSceneBuilder.Assign(loader, "roadMaterial", TaxiKinSceneBuilder.GetMaterial("Mat_Road", new Color(0.2f, 0.2f, 0.22f)));
            TaxiKinSceneBuilder.Assign(loader, "buildingMaterial", TaxiKinSceneBuilder.GetMaterial("Mat_Building", new Color(0.78f, 0.7f, 0.58f)));
            TaxiKinSceneBuilder.Assign(loader, "groundMaterial", TaxiKinSceneBuilder.GetMaterial("Mat_Ground", new Color(0.35f, 0.45f, 0.3f)));
            Selection.activeGameObject = go;
            Debug.Log("[Taxi Kin] ZoneLoader ajouté. Lancez Play : la zone « gare_centrale » se génère depuis Resources/Zones.");
        }
    }
}

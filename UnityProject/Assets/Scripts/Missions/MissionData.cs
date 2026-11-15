using UnityEngine;
using TaxiKin.Vehicles;

namespace TaxiKin.Missions
{
    /// <summary>
    /// Donnée d'une mission (ScriptableObject). Pour ajouter une mission : clic droit dans Project →
    /// Create → Taxi Kin → Mission. Aucun code à modifier.
    /// Structure de base ; les conditions de réussite détaillées seront ajoutées à l'Étape 3.
    /// </summary>
    [CreateAssetMenu(menuName = "Taxi Kin/Mission", fileName = "Mission_")]
    public class MissionData : ScriptableObject
    {
        [Header("Identité")]
        public string id;
        public string title;
        [TextArea] public string description;

        [Header("Véhicule")]
        public bool anyVehicle;               // true = au choix du joueur
        public VehicleType requiredVehicle;   // utilisé si anyVehicle == false

        [Header("Trajet")]
        public string startPlace;
        public string destination;
        public float timeLimitSeconds;        // 0 = pas de chronomètre

        [Header("Récompenses")]
        public int rewardKinCoins;
        public int rewardXp;
    }
}

using System;
using UnityEngine;
using TaxiKin.Core;
using TaxiKin.Vehicles;
using TaxiKin.Weather;

namespace TaxiKin.Missions
{
    public enum MissionType { SimpleRide, Urgent, Vip, Delivery, Night, Bus207, Daily, Race, Weather, Parking, Customization }

    /// <summary>
    /// Systèmes dont une mission dépend. Une mission n'est jouable que si tous ses systèmes existent
    /// (voir MissionManager.availableFeatures) : on ne simule jamais une règle impossible à respecter.
    /// </summary>
    [Flags]
    public enum MissionFeature
    {
        None = 0, RaceAI = 1, TrafficAvoidance = 2, Rain = 4, DenseTraffic = 8, CorridorMap = 16, Parking = 32
    }

    /// <summary>Lieu d'une mission : nom affiché + zone de la carte + type de point d'intérêt (optionnel).</summary>
    [Serializable]
    public class MissionPlace
    {
        public string label;      // ex. "Matonge"
        public string zoneId;     // ex. "matonge" (voir Tools/zones.json)
        public string poiKind;    // ex. "station", "aeroport" ; vide = centre de la zone
        public bool IsEmpty => string.IsNullOrEmpty(label);
    }

    /// <summary>Conditions de réussite. -1 = pas de limite ; 0 / false = non exigé.</summary>
    [Serializable]
    public class MissionConditions
    {
        [Header("Contraintes (à ne pas dépasser)")]
        public int maxCollisions = -1;
        public float maxDamage = -1f;          // % de dommages subis pendant la mission
        [Header("Objectifs")]
        public int passengersToDeliver;        // clients à déposer à destination
        public int minAvoidedVehicles;
        public int minStops;                   // arrêts de bus respectés
        public int roundTrips;                 // allers-retours de ligne
        public bool requireReverse;
        public bool requireRearCamera;
        public bool requireDoorsCycle;         // bus : ouvrir, faire monter, repartir
        public bool requireParked;
        public bool requireUTurn;              // faire demi-tour (cap inversé d'au moins 150°)
        public bool requireReachDestination;   // atteindre le point d'arrivée sans passagers (sortie de garage…)
        public bool requireFirstPlace;
        [Header("Environnement")]
        public int raceOpponents;
        [Range(0.5f, 1f)] public float raceSkill = 0.8f;   // niveau moyen des chauffeurs virtuels (part de la vitesse max)
        public WeatherType weather = WeatherType.Clear;
        public bool denseTraffic;
    }

    [Serializable]
    public class MissionRewards
    {
        public int kinCoins;
        public int xp;
        public string trophyId;
        public string rareVehicleId;
        public string stickerId;
        public string clothingId;
        public string unlocksVehicle;          // "Taxi", "Bus207" : débloqué en jouant
    }

    /// <summary>
    /// Une mission (ScriptableObject). Pour en ajouter : Create → Taxi Kin → Mission, aucun code à modifier.
    /// Les assets générés sont dans Assets/Resources/Missions.
    /// </summary>
    [CreateAssetMenu(menuName = "Taxi Kin/Mission", fileName = "Mission_")]
    public class MissionData : ScriptableObject
    {
        [Header("Identité")]
        public string id;
        public int order;
        public MissionType type;
        public string titleFr, titleLn;
        [TextArea] public string descriptionFr;
        [TextArea] public string descriptionLn;

        [Header("Véhicule")]
        public bool anyVehicle;                // true = au choix du joueur
        public VehicleType requiredVehicle;

        [Header("Trajet")]
        public MissionPlace start;
        public MissionPlace destination;
        public float timeLimitSeconds;         // 0 = pas de chronomètre

        [Header("Règles et récompenses")]
        public MissionConditions conditions = new MissionConditions();
        public MissionRewards rewards = new MissionRewards();

        [Header("Disponibilité")]
        public MissionFeature requiredFeatures;
        public bool availableInV1 = true;
        public string nextMissionId;

        // Texte lingala si présent, sinon français
        public string Title => Loc.Current == Language.Ln && !string.IsNullOrEmpty(titleLn) ? titleLn : titleFr;
        public string Description => Loc.Current == Language.Ln && !string.IsNullOrEmpty(descriptionLn) ? descriptionLn : descriptionFr;
    }
}

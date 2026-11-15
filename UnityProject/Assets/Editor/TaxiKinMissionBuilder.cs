using UnityEditor;
using UnityEngine;
using TaxiKin.Missions;
using TaxiKin.Vehicles;
using TaxiKin.Weather;

namespace TaxiKin.EditorTools
{
    /// <summary>
    /// Génère les 16 missions (14 de conduite + 2 de personnalisation) en assets (Assets/Resources/Missions). Un asset existant n'est JAMAIS écrasé :
    /// les réglages faits à la main sont conservés. Pour régénérer une mission, supprimer son asset.
    /// Arbitrages de carte (zones V1) : Gombe → zone Gare centrale ; Ngaliema → Kintambo ; Masina → Limete.
    /// </summary>
    public static class TaxiKinMissionBuilder
    {
        private const string Folder = "Assets/Resources/Missions";

        [MenuItem("Taxi Kin/Missions/Générer les missions")]
        public static void Generate()
        {
            if (!AssetDatabase.IsValidFolder("Assets/Resources")) AssetDatabase.CreateFolder("Assets", "Resources");
            if (!AssetDatabase.IsValidFolder(Folder)) AssetDatabase.CreateFolder("Assets/Resources", "Missions");

            const MissionFeature Cor = MissionFeature.CorridorMap;

            // ---------- 10 missions de la V1 ----------
            Make(1, "premier_client", MissionType.SimpleRide, "Premier client",
                "Un client t'attend à la gare. Dépose-le à Matonge sans accident grave.",
                Wewa(), P("Gare centrale", "gare_centrale", "station"), P("Matonge", "matonge"), 0,
                new MissionConditions { passengersToDeliver = 1, maxDamage = 60f }, Rw(200, 50), Cor, "evite_embouteillages");

            Make(2, "evite_embouteillages", MissionType.Urgent, "Évite les embouteillages",
                "Faufile-toi dans la circulation : arrive à Kintambo avant 2 min 30 en évitant au moins 10 véhicules.",
                Wewa(), P("Boulevard du 30 Juin", "gare_centrale"), P("Kintambo", "kintambo"), 150,
                new MissionConditions { passengersToDeliver = 1, minAvoidedVehicles = 10 }, Rw(350, 80),
                Cor | MissionFeature.TrafficAvoidance | MissionFeature.DenseTraffic, "course_pluie");

            Make(3, "course_pluie", MissionType.Weather, "Course sous la pluie",
                "Pluie forte, route glissante : amène ton client de Limete à Gombe sans tomber.",
                Wewa(), P("Limete", "limete"), P("Gombe", "gare_centrale"), 0,
                new MissionConditions { passengersToDeliver = 1, maxDamage = 40f, weather = WeatherType.HeavyRain }, Rw(500, 120),
                Cor | MissionFeature.Rain, "client_presse");

            Make(4, "client_presse", MissionType.Urgent, "Le client pressé",
                "Ton client a un avion à prendre ! Matonge → aéroport de N'djili en moins de 4 minutes, 3 collisions maximum.",
                Wewa(), P("Matonge", "matonge"), P("Aéroport de N'djili", "ndjili", "aeroport"), 240,
                new MissionConditions { passengersToDeliver = 1, maxCollisions = 3 }, Rw(800, 150), Cor, "debloque_taxi");

            Make(5, "debloque_taxi", MissionType.SimpleRide, "Débloque le taxi",
                "On te prête un taxi : transporte 2 passagers jusqu'à Ngaliema pour le gagner pour de bon.",
                Taxi(), P("Gare centrale", "gare_centrale", "station"), P("Ngaliema", "kintambo"), 0,
                new MissionConditions { passengersToDeliver = 2 }, Rw(1000, 200, unlocks: "Taxi"), Cor, "client_vip");

            Make(6, "client_vip", MissionType.Vip, "Client VIP",
                "Un client important de Gombe va à l'aéroport. Conduis sans accroc et arrive à l'heure : aucune collision.",
                Taxi(), P("Gombe", "gare_centrale"), P("Aéroport de N'djili", "ndjili", "aeroport"), 480,
                new MissionConditions { passengersToDeliver = 1, maxCollisions = 0 }, Rw(2000, 300), Cor, "roi_207");

            Make(7, "roi_207", MissionType.Bus207, "Le roi du 207",
                "Remplis ton bus : transporte au moins 12 passagers jusqu'à Masina et respecte au moins 4 arrêts.",
                Bus(), P("Gare centrale", "gare_centrale", "station"), P("Masina", "limete"), 900,
                new MissionConditions { passengersToDeliver = 12, minStops = 4 }, Rw(3000, 400, unlocks: "Bus207"), Cor, "ligne_207");

            Make(8, "ligne_207", MissionType.Bus207, "Ligne de 207",
                "Fais 3 allers-retours sur la ligne Gare centrale ↔ Matonge et ramasse des passagers à chaque arrêt.",
                Bus(), P("Gare centrale", "gare_centrale", "station"), P("Matonge", "matonge"), 0,
                new MissionConditions { roundTrips = 3 }, Rw(5000, 600), Cor, "course_wewa");

            Make(9, "course_wewa", MissionType.Race, "Course de wewa",
                "Arrive premier contre 4 chauffeurs virtuels, avec au moins un passager à bord.",
                Wewa(), P(), P(), 0,
                new MissionConditions { raceOpponents = 4, requireFirstPlace = true, passengersToDeliver = 1 },
                Rw(2500, 0, trophy: "trophy_wewa_race"), MissionFeature.RaceAI, "grande_course");

            Make(10, "grande_course", MissionType.Race, "La grande course",
                "Gare centrale → aéroport de N'djili sous la pluie, dans un trafic dense, chrono serré : arrive premier avec un client.",
                Any(), P("Gare centrale", "gare_centrale", "station"), P("Aéroport de N'djili", "ndjili", "aeroport"), 600,
                new MissionConditions { raceOpponents = 4, requireFirstPlace = true, passengersToDeliver = 1,
                                        weather = WeatherType.HeavyRain, denseTraffic = true },
                Rw(10000, 0, rare: "rare_vehicle_01"),
                Cor | MissionFeature.RaceAI | MissionFeature.Rain | MissionFeature.DenseTraffic, "");

            // ---------- 4 missions de stationnement (après la V1) ----------
            Make(11, "gare_le_taxi", MissionType.Parking, "Gare le taxi",
                "Gare le taxi dans la place marquée devant l'hôtel sans toucher les voitures ni les trottoirs.",
                Taxi(), P("Hôtel", "gare_centrale", "hotel"), P("Place de l'hôtel", "gare_centrale", "hotel"), 0,
                new MissionConditions { requireParked = true, maxCollisions = 0 }, Rw(1500, 0), MissionFeature.Parking, "demi_tour", v1: false);

            Make(12, "demi_tour", MissionType.Parking, "Demi-tour difficile",
                "Fais demi-tour dans une rue étroite en utilisant la marche arrière au moins une fois.",
                Taxi(), P("Rue étroite", "gare_centrale"), P(), 0,
                new MissionConditions { requireUTurn = true, requireReverse = true }, Rw(1200, 0), MissionFeature.Parking, "arret_parfait", v1: false);

            Make(13, "arret_parfait", MissionType.Parking, "Arrêt parfait",
                "Gare le 207 à l'arrêt : ouvre les portes, laisse monter les passagers, puis repars.",
                Bus(), P("Arrêt de bus", "gare_centrale", "arret"), P("Arrêt de bus", "gare_centrale", "arret"), 0,
                new MissionConditions { requireParked = true, requireDoorsCycle = true }, Rw(2500, 0), MissionFeature.Parking, "sortie_garage", v1: false);

            Make(14, "sortie_garage", MissionType.Parking, "Sortie de garage",
                "Sors du garage sans toucher les murs, en utilisant la caméra arrière.",
                Any(), P("Garage", "gare_centrale"), P("Sortie du garage", "gare_centrale"), 0,
                new MissionConditions { requireReachDestination = true, requireRearCamera = true, maxCollisions = 0 }, Rw(800, 0), MissionFeature.Parking, "", v1: false);

            // ---------- 2 missions de personnalisation (terminées automatiquement, sans conduite) ----------
            Make(15, "cree_ton_identite", MissionType.Customization, "Crée ton identité",
                "Crée ton avatar, choisis ta tenue et enregistre ton profil.",
                Any(), P(), P(), 0, new MissionConditions(),
                new MissionRewards { kinCoins = 500, clothingId = "yellow_start" }, MissionFeature.None, "le_jaune_de_kin");

            Make(16, "le_jaune_de_kin", MissionType.Customization, "Le jaune de Kin",
                "Personnalise ton taxi-voiture en jaune dans le garage, puis termine une course.",
                Taxi(), P(), P(), 0, new MissionConditions(),
                new MissionRewards { kinCoins = 1000, stickerId = "sticker_kin_exclusive" }, MissionFeature.None, "");

            AssetDatabase.SaveAssets();
            AssetDatabase.Refresh();
            Debug.Log("[Taxi Kin] Missions générées dans " + Folder);
        }

        // ---------- Aides ----------
        private static MissionPlace P(string label = "", string zone = "", string poi = "") =>
            new MissionPlace { label = label, zoneId = zone, poiKind = poi };

        private static MissionRewards Rw(int coins, int xp, string unlocks = "", string trophy = "", string rare = "") =>
            new MissionRewards { kinCoins = coins, xp = xp, unlocksVehicle = unlocks, trophyId = trophy, rareVehicleId = rare };

        private static (bool any, VehicleType v) Wewa() => (false, VehicleType.Wewa);
        private static (bool any, VehicleType v) Taxi() => (false, VehicleType.Taxi);
        private static (bool any, VehicleType v) Bus() => (false, VehicleType.Bus207);
        private static (bool any, VehicleType v) Any() => (true, VehicleType.Taxi);

        private static void Make(int order, string id, MissionType type, string title, string description,
            (bool any, VehicleType v) vehicle, MissionPlace start, MissionPlace dest, float time,
            MissionConditions cond, MissionRewards rewards, MissionFeature features, string next, bool v1 = true)
        {
            string path = $"{Folder}/Mission_{order:00}_{id}.asset";
            if (AssetDatabase.LoadAssetAtPath<MissionData>(path) != null) return;   // ne jamais écraser

            var m = ScriptableObject.CreateInstance<MissionData>();
            m.id = id; m.order = order; m.type = type;
            m.titleFr = title; m.descriptionFr = description;
            m.anyVehicle = vehicle.any; m.requiredVehicle = vehicle.v;
            m.start = start; m.destination = dest; m.timeLimitSeconds = time;
            m.conditions = cond; m.rewards = rewards;
            m.requiredFeatures = features; m.availableInV1 = v1; m.nextMissionId = next;
            AssetDatabase.CreateAsset(m, path);
        }
    }
}

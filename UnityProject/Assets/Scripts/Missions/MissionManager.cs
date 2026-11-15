using System;
using System.Collections.Generic;
using UnityEngine;
using TaxiKin.Core;
using TaxiKin.Economy;
using TaxiKin.Map;
using TaxiKin.Player;
using TaxiKin.SaveSystem;
using TaxiKin.Traffic;
using TaxiKin.UI;
using TaxiKin.Vehicles;
using TaxiKin.Weather;

namespace TaxiKin.Missions
{
    public enum MissionState { Idle, Briefing, Running, Ended }

    /// <summary>Compteurs de la mission en cours.</summary>
    public class MissionProgress
    {
        public int collisions, delivered, boarded, boardedWhileOpen, avoided, stops, legs, racePlace;
        public bool reverseUsed, rearCameraUsed, doorsOpened, doorsCycle, parked, uTurn, reached;
        public float peakDamage, startDamage, elapsed;
        public Vector3 startForward;
    }

    public struct ObjectiveResult
    {
        public string label;
        public bool ok;
        public bool isConstraint;   // true = limite à ne pas dépasser, false = objectif à atteindre
    }

    public class MissionResult
    {
        public MissionData mission;
        public bool success;
        public float time;
        public int coins, xp;
        public bool firstCompletion;
        public List<ObjectiveResult> objectives = new List<ObjectiveResult>();
        public List<string> unlocks = new List<string>();
    }

    /// <summary>
    /// Déroulement des missions : briefing → départ → suivi des objectifs → fin (récompenses).
    /// Première réussite : récompense complète ; rejouer : 25 % (évite le « farming » sans bloquer le joueur).
    /// </summary>
    public class MissionManager : MonoBehaviour
    {
        [Header("Références")]
        [SerializeField] private PlayerController player;
        [SerializeField] private PassengerManager passengers;
        [SerializeField] private WeatherManager weather;
        [SerializeField] private TrafficManager traffic;
        [SerializeField] private ZoneLoader zone;
        [SerializeField] private CameraRig cameraRig;
        [SerializeField] private HudManager hud;
        [SerializeField] private DirectionArrow directionArrow;

        [Header("Repli (tant que la carte réelle n'est pas chargée)")]
        [SerializeField] private Transform fallbackStart;
        [SerializeField] private Transform fallbackDestination;

        [Header("Disponibilité")]
        [Tooltip("Systèmes déjà développés. Les missions qui en exigent d'autres sont verrouillées (« Bientôt »).")]
        [SerializeField] private MissionFeature availableFeatures = MissionFeature.None;
        [Tooltip("Pour tester : ignore le verrouillage par fonctionnalités et utilise les points de repli.")]
        [SerializeField] private bool ignoreFeatureGating = true;
        [SerializeField] private float deliveryRadius = 25f;

        private readonly List<MissionData> missions = new List<MissionData>();
        private readonly HashSet<string> completed = new HashSet<string>();
        private readonly HashSet<string> unlocked = new HashSet<string>();
        private VehicleController watched;
        private bool completedLoaded;
        private bool headingToDestination = true;
        private float lastReverseCheck;

        public IReadOnlyList<MissionData> Missions => missions;
        public MissionData Current { get; private set; }
        public MissionState State { get; private set; } = MissionState.Idle;
        public MissionProgress Progress { get; private set; } = new MissionProgress();
        public Transform Destination { get; private set; }
        public bool IsRunning => State == MissionState.Running;
        public float ElapsedSeconds => Progress.elapsed;

        public event Action<MissionData> OnBriefing;
        public event Action<MissionData> OnMissionStarted;
        public event Action<MissionResult> OnMissionEnded;
        public event Action<MissionData> OnQuestCompleted;   // Quête de personnalisation terminée (bandeau à l'écran)
        public event Action<string> OnUnlock;     // identifiant débloqué (véhicule, trophée…) → Garage / sauvegarde

        // ---------- Initialisation ----------

        private void Awake()
        {
            var all = Resources.LoadAll<MissionData>("Missions");
            missions.AddRange(all);
            missions.Sort((a, b) => a.order.CompareTo(b.order));
        }

        private void OnEnable()
        {
            if (player != null) player.OnVehicleChanged += OnVehicleChanged;
            SaveManager.ProgressReset += OnProgressReset;
        }

        private void OnDisable()
        {
            SaveManager.ProgressReset -= OnProgressReset;
            if (player != null) player.OnVehicleChanged -= OnVehicleChanged;
            if (watched != null) watched.OnImpact -= OnImpact;
        }

        private void OnVehicleChanged(VehicleController v)
        {
            if (watched != null) watched.OnImpact -= OnImpact;
            watched = v;
            if (watched != null) watched.OnImpact += OnImpact;
        }

        // ---------- Disponibilité ----------

        // ---------- Missions terminées (sauvegardées) ----------

        /// <summary>Charge la liste des missions terminées dès que SaveManager existe (chargement paresseux).</summary>
        private void LoadCompleted()
        {
            if (completedLoaded) return;
            var sm = SaveManager.Instance;
            if (sm == null) return;
            completedLoaded = true;
            foreach (var id in sm.Data.completedMissions) completed.Add(id);
        }

        /// <summary>Marque la mission comme terminée et sauvegarde. Retourne vrai s'il s'agit de la PREMIÈRE réussite.</summary>
        private bool MarkCompleted(string id)
        {
            LoadCompleted();
            if (!completed.Add(id)) return false;
            var sm = SaveManager.Instance;
            if (sm != null && !sm.Data.completedMissions.Contains(id))
            {
                sm.Data.completedMissions.Add(id);
                sm.Save();   // sauvegarde automatique après chaque mission
            }
            return true;
        }

        /// <summary>Remise à zéro de la progression : on oublie missions terminées et déblocages, on arrête la mission en cours.</summary>
        private void OnProgressReset()
        {
            completed.Clear();
            unlocked.Clear();
            completedLoaded = false;
            State = MissionState.Idle;
            Current = null;
            Progress = new MissionProgress();
            SetDestination(null);
        }

        public bool IsCompleted(string id) { LoadCompleted(); return completed.Contains(id); }

        public bool IsPlayable(MissionData m)
        {
            if (m == null || !m.availableInV1) return false;
            if (m.type == MissionType.Customization) return false;   // Quêtes automatiques : jamais proposées comme course
            return ignoreFeatureGating || (m.requiredFeatures & ~availableFeatures) == MissionFeature.None;
        }

        /// <summary>Première mission jouable non terminée (ou la première jouable si tout est fini).</summary>
        public MissionData NextSuggested()
        {
            foreach (var m in missions) if (IsPlayable(m) && !IsCompleted(m.id)) return m;
            foreach (var m in missions) if (IsPlayable(m)) return m;
            return null;
        }

        public MissionData FindNext(MissionData m)
        {
            if (m == null) return NextSuggested();
            foreach (var x in missions) if (x.id == m.nextMissionId && IsPlayable(x)) return x;
            int i = missions.IndexOf(m);
            for (int k = i + 1; k < missions.Count; k++) if (IsPlayable(missions[k])) return missions[k];
            return null;
        }

        // ---------- Déroulement ----------

        /// <summary>Affiche l'écran de début de mission (jeu en pause).</summary>
        public void Brief(MissionData m)
        {
            if (m == null || !IsPlayable(m)) return;
            Current = m;
            State = MissionState.Briefing;
            GameManager.Instance?.SetState(GameState.Paused);
            OnBriefing?.Invoke(m);
        }

        public void BriefNext() => Brief(NextSuggested());
        public void Retry() => Brief(Current);
        public void BriefFollowing() => Brief(FindNext(Current));

        /// <summary>Bouton « Commencer » : prépare le véhicule, le lieu et la météo, puis lance le chrono.</summary>
        public void Begin()
        {
            if (State != MissionState.Briefing || Current == null) return;
            var m = Current;
            var c = m.conditions;

            if (!m.anyVehicle && player != null) player.SelectVehicle((int)m.requiredVehicle);

            Transform start = ResolvePlace(m.start, fallbackStart);
            Transform dest = ResolvePlace(m.destination, fallbackDestination);
            headingToDestination = true;
            SetDestination(dest);

            var v = player != null ? player.Vehicle : null;
            if (v != null && start != null)
            {
                Vector3 flat = dest != null ? dest.position - start.position : start.forward;
                flat.y = 0f;
                Quaternion rot = flat.sqrMagnitude > 0.01f ? Quaternion.LookRotation(flat) : start.rotation;
                v.ResetMotion(start.position + Vector3.up * 0.1f, rot);
                v.Gear = GearPosition.D;
            }

            passengers?.Reset();
            weather?.SetWeather(c.weather);
            traffic?.SetMaxVehicles(c.denseTraffic ? 30 : 12);

            Progress = new MissionProgress
            {
                startDamage = v != null ? v.Damage : 0f,
                startForward = v != null ? v.transform.forward : Vector3.forward
            };
            State = MissionState.Running;
            GameManager.Instance?.SetState(GameState.Playing);
            OnMissionStarted?.Invoke(m);
        }

        private void Update()
        {
            if (State != MissionState.Running) return;
            Progress.elapsed += Time.deltaTime;

            var v = player != null ? player.Vehicle : null;
            if (v != null)
            {
                if (v.Gear == GearPosition.R && v.SpeedKmh > 1f) Progress.reverseUsed = true;
                Progress.peakDamage = Mathf.Max(Progress.peakDamage, v.Damage - Progress.startDamage);
                if (Current.conditions.requireUTurn && Vector3.Angle(v.transform.forward, Progress.startForward) >= 150f) Progress.uTurn = true;
                if (Current.conditions.requireReachDestination && Destination != null)
                {
                    Vector3 d = Destination.position - v.transform.position; d.y = 0f;
                    if (d.magnitude <= deliveryRadius) Progress.reached = true;
                }
            }
            if (cameraRig != null && cameraRig.Current == CameraView.Rear) Progress.rearCameraUsed = true;

            CheckCompletion();
        }

        // ---------- Événements signalés par le jeu ----------

        private void OnImpact(float impact)
        {
            if (State != MissionState.Running) return;
            Progress.collisions++;
            CheckCompletion();
        }

        public void ReportAvoidedVehicle() { if (IsRunning) { Progress.avoided++; CheckCompletion(); } }
        public void ReportStop() { if (IsRunning) { Progress.stops++; CheckCompletion(); } }
        public void ReportParked() { if (IsRunning && !Progress.parked) { Progress.parked = true; CheckCompletion(); } }

        public void ReportRaceFinish(int place)
        {
            if (!IsRunning) return;
            Progress.racePlace = place;
            if (Current.conditions.requireFirstPlace && place != 1) Finish(false); else CheckCompletion();
        }

        public void ReportBoarded(int count, bool doorsOpen)
        {
            if (!IsRunning || count <= 0) return;
            Progress.boarded += count;
            if (doorsOpen) Progress.boardedWhileOpen += count;
        }

        public void ReportDoors(bool open)
        {
            if (!IsRunning) return;
            if (open) Progress.doorsOpened = true;
            else if (Progress.doorsOpened && Progress.boardedWhileOpen > 0) Progress.doorsCycle = true;
            CheckCompletion();
        }

        /// <summary>Dépose les passagers : valide la livraison seulement près de la destination.</summary>
        public bool TryDeliver()
        {
            if (!IsRunning || player == null || player.Vehicle == null || passengers == null) return false;
            if (Destination != null)
            {
                Vector3 d = Destination.position - player.Vehicle.transform.position;
                d.y = 0f;
                if (d.magnitude > deliveryRadius) return false;
            }
            int n = passengers.DropAll();
            if (n <= 0) return false;
            Progress.delivered += n;

            if (Current.conditions.roundTrips > 0)
            {   // Ligne : on alterne entre les deux terminus
                Progress.legs++;
                headingToDestination = !headingToDestination;
                SetDestination(headingToDestination ? ResolvePlace(Current.destination, fallbackDestination)
                                                    : ResolvePlace(Current.start, fallbackStart));
            }
            CheckCompletion();
            return true;
        }

        // ---------- Évaluation ----------

        /// <summary>Liste complète des objectifs et contraintes avec leur état actuel.</summary>
        public List<ObjectiveResult> Evaluate()
        {
            var list = new List<ObjectiveResult>();
            if (Current == null) return list;
            var c = Current.conditions;
            var p = Progress;

            if (c.passengersToDeliver > 0)
                Add(list, $"Déposer {c.passengersToDeliver} client(s) à destination ({p.delivered}/{c.passengersToDeliver})", p.delivered >= c.passengersToDeliver, false);
            if (c.minAvoidedVehicles > 0)
                Add(list, $"Éviter {c.minAvoidedVehicles} véhicules ({p.avoided}/{c.minAvoidedVehicles})", p.avoided >= c.minAvoidedVehicles, false);
            if (c.minStops > 0)
                Add(list, $"Respecter {c.minStops} arrêts ({p.stops}/{c.minStops})", p.stops >= c.minStops, false);
            if (c.roundTrips > 0)
                Add(list, $"{c.roundTrips} allers-retours ({p.legs / 2}/{c.roundTrips})", p.legs >= c.roundTrips * 2, false);
            if (c.requireReverse) Add(list, "Utiliser la marche arrière", p.reverseUsed, false);
            if (c.requireUTurn) Add(list, "Faire demi-tour", p.uTurn, false);
            if (c.requireReachDestination) Add(list, "Atteindre la sortie", p.reached, false);
            if (c.requireRearCamera) Add(list, "Utiliser la caméra arrière", p.rearCameraUsed, false);
            if (c.requireDoorsCycle) Add(list, "Ouvrir les portes, faire monter les passagers, puis repartir", p.doorsCycle, false);
            if (c.requireParked) Add(list, "Se garer dans la place marquée", p.parked, false);
            if (c.requireFirstPlace) Add(list, "Arriver premier", p.racePlace == 1, false);

            if (Current.timeLimitSeconds > 0f)
                Add(list, $"Arriver avant {FormatTime(Current.timeLimitSeconds)}", p.elapsed <= Current.timeLimitSeconds, true);
            if (c.maxCollisions == 0) Add(list, "Aucune collision", p.collisions == 0, true);
            else if (c.maxCollisions > 0) Add(list, $"Maximum {c.maxCollisions} collisions ({p.collisions})", p.collisions <= c.maxCollisions, true);
            if (c.maxDamage >= 0f)
                Add(list, $"Dommages sous {Mathf.RoundToInt(c.maxDamage)} % ({Mathf.RoundToInt(p.peakDamage)} %)", p.peakDamage <= c.maxDamage, true);
            return list;
        }

        private static void Add(List<ObjectiveResult> l, string label, bool ok, bool constraint) =>
            l.Add(new ObjectiveResult { label = label, ok = ok, isConstraint = constraint });

        /// <summary>Contrôle chaque image SANS allocation (pas de chaînes ni de listes) : économise le ramasse-miettes sur mobile.</summary>
        private void CheckCompletion()
        {
            if (State != MissionState.Running) return;
            var c = Current.conditions;
            var p = Progress;

            // Limite dépassée : échec immédiat
            if (Current.timeLimitSeconds > 0f && p.elapsed > Current.timeLimitSeconds) { Finish(false); return; }
            if (c.maxCollisions >= 0 && p.collisions > c.maxCollisions) { Finish(false); return; }
            if (c.maxDamage >= 0f && p.peakDamage > c.maxDamage) { Finish(false); return; }

            // Tous les objectifs exigés sont-ils atteints ?
            int needed = 0, done = 0;
            if (c.passengersToDeliver > 0) { needed++; if (p.delivered >= c.passengersToDeliver) done++; }
            if (c.minAvoidedVehicles > 0) { needed++; if (p.avoided >= c.minAvoidedVehicles) done++; }
            if (c.minStops > 0) { needed++; if (p.stops >= c.minStops) done++; }
            if (c.roundTrips > 0) { needed++; if (p.legs >= c.roundTrips * 2) done++; }
            if (c.requireReverse) { needed++; if (p.reverseUsed) done++; }
            if (c.requireUTurn) { needed++; if (p.uTurn) done++; }
            if (c.requireReachDestination) { needed++; if (p.reached) done++; }
            if (c.requireRearCamera) { needed++; if (p.rearCameraUsed) done++; }
            if (c.requireDoorsCycle) { needed++; if (p.doorsCycle) done++; }
            if (c.requireParked) { needed++; if (p.parked) done++; }
            if (c.requireFirstPlace) { needed++; if (p.racePlace == 1) done++; }

            if (needed > 0 && done == needed) Finish(true);
        }

        private void Finish(bool success)
        {
            if (State != MissionState.Running) return;
            State = MissionState.Ended;
            var m = Current;
            var res = new MissionResult { mission = m, success = success, time = Progress.elapsed, objectives = Evaluate() };

            if (success)
            {
                res.firstCompletion = MarkCompleted(m.id);
                float mult = res.firstCompletion ? 1f : 0.25f;
                // Bonus « pièces x2 » : consommé seulement si la mission rapporte des pièces
                int bonus = (m.rewards.kinCoins > 0 && EconomyManager.Instance != null) ? EconomyManager.Instance.ConsumeDoubleCoins() : 1;
                res.coins = Mathf.RoundToInt(m.rewards.kinCoins * mult) * bonus;
                res.xp = Mathf.RoundToInt(m.rewards.xp * mult);
                EconomyManager.Instance?.AddCoins(res.coins);
                ProfileManager.Instance?.AddXp(res.xp);

                if (res.firstCompletion)
                    foreach (var u in new[] { m.rewards.unlocksVehicle, m.rewards.trophyId, m.rewards.rareVehicleId, m.rewards.stickerId, m.rewards.clothingId })
                        if (!string.IsNullOrEmpty(u) && unlocked.Add(u)) { res.unlocks.Add(u); OnUnlock?.Invoke(u); }
            }

            SetDestination(null);
            VehicleCustomizer.SaveAllConditions();   // carburant et dommages sauvegardés après chaque mission
            GameManager.Instance?.SetState(GameState.MissionEnd);
            OnMissionEnded?.Invoke(res);
        }

        /// <summary>
        /// Termine une quête sans conduite (missions de personnalisation) : récompense complète, une seule fois.
        /// </summary>
        public bool GrantQuest(string missionId)
        {
            MissionData m = null;
            foreach (var x in missions) if (x.id == missionId) { m = x; break; }
            if (m == null || !MarkCompleted(m.id)) return false;

            EconomyManager.Instance?.AddCoins(m.rewards.kinCoins);
            ProfileManager.Instance?.AddXp(m.rewards.xp);
            foreach (var u in new[] { m.rewards.unlocksVehicle, m.rewards.trophyId, m.rewards.rareVehicleId, m.rewards.stickerId, m.rewards.clothingId })
                if (!string.IsNullOrEmpty(u) && unlocked.Add(u)) OnUnlock?.Invoke(u);
            OnQuestCompleted?.Invoke(m);
            return true;
        }

        // ---------- Utilitaires ----------

        private Transform ResolvePlace(MissionPlace place, Transform fallback)
        {
            if (place != null && !place.IsEmpty && zone != null && zone.IsLoaded && zone.Data.id == place.zoneId)
            {
                var poi = string.IsNullOrEmpty(place.poiKind) ? null : zone.FindPoi(place.poiKind);
                if (poi != null) return poi;
            }
            return fallback;   // zone non chargée ou point introuvable : repli
        }

        private void SetDestination(Transform t)
        {
            Destination = t;
            if (hud != null) hud.Destination = t;
            directionArrow?.SetDestination(t);
        }

        public static string FormatTime(float seconds) => $"{(int)(seconds / 60f)}:{(int)seconds % 60:00}";
    }
}

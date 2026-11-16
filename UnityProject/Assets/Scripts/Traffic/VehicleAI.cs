using System.Collections.Generic;
using UnityEngine;
using TaxiKin.Map;
using TaxiKin.Vehicles;
using TaxiKin.Weather;

namespace TaxiKin.Traffic
{
    /// <summary>
    /// Pilote automatique d'un véhicule (trafic ou chauffeur de course). Pas de physique propre : il envoie à VehicleController
    /// les mêmes entrées qu'un joueur (direction, gaz, frein).
    ///  - Mode Wander (trafic) : promenade aléatoire sur le réseau routier, voie de droite, vitesse limitée par la classe de route.
    ///  - Mode Route (course) : suit un trajet calculé (A*), à une part « skill » de la vitesse maximale.
    /// Loi de pilotage : cap vers un point d'anticipation, ralentissement avant les virages, ralentissement derrière un obstacle.
    /// Les constantes sont IDENTIQUES à celles de Tools/verify/sim_ai.py (validées sur les vraies routes : écart max 4 m).
    /// </summary>
    [RequireComponent(typeof(Rigidbody))]
    public class VehicleAI : MonoBehaviour
    {
        public enum Mode { Idle, Wander, Route }

        // --- constantes de pilotage (voir sim_ai.py) ---
        private const float LookMin = 5f, LookMax = 22f, LookBase = 4f, LookGain = 0.7f;
        private const float SteerDegrees = 30f;
        private const float CornerAheadBase = 25f, CornerAheadGain = 1.2f;
        private const float CornerMinFactor = 0.30f, CornerFromDeg = 15f, CornerToDeg = 100f;
        private const float ThrottleRange = 2f, BrakeRange = 3f;
        private const float WanderAhead = 140f;       // longueur de trajet maintenue devant le véhicule en promenade

        private static readonly List<VehicleAI> Active = new List<VehicleAI>();
        /// <summary>Le véhicule du joueur est aussi un obstacle pour l'IA (renseigné par TrafficManager).</summary>
        public static Transform PlayerTransform;

        [SerializeField] private float laneOffset = 2.2f;          // conduite à droite
        [SerializeField] private float groundOffset = 0.7f;        // hauteur du centre au-dessus du sol (demi-hauteur du véhicule + marge)
        [SerializeField] private float obstacleRange = 28f;        // portée de détection devant (m) ; faible pour les coureurs

        public float GroundOffset => groundOffset;
        public Mode CurrentMode { get; private set; } = Mode.Idle;
        public bool Hold { get; set; }                              // à l'arrêt tant que vrai (compte à rebours)
        public bool IsStuck { get; private set; }
        public bool Finished { get; private set; }
        public float Progress { get; private set; }                // abscisse sur le trajet (m)
        public float PathLength => path.Length;
        public RoutePath Path => path;

        private readonly RoutePath path = new RoutePath();
        private VehicleController vehicle;
        private RoadGraph graph;
        private System.Random rnd;
        private int segment, lastNode, currentNode;
        private float skill = 1f, stuckTime, lane;
        private bool useLimits;

        private void Awake() => vehicle = GetComponent<VehicleController>();
        private void OnEnable() => Active.Add(this);
        private void OnDisable() => Active.Remove(this);

        // ---------- Démarrage ----------

        /// <summary>Promenade aléatoire à partir d'un nœud du réseau.</summary>
        public void BeginWander(RoadGraph roads, int startNode, System.Random random, float speedFactor)
        {
            graph = roads; rnd = random; skill = speedFactor; lane = laneOffset; useLimits = true;
            path.Clear(); segment = 0; Progress = 0f; stuckTime = 0f; IsStuck = false; Finished = false; Hold = false;
            currentNode = startNode; lastNode = -1;
            path.Add(graph.PositionOf(startNode), 0);
            ExtendWander();
            CurrentMode = Mode.Wander;
            vehicle.Gear = GearPosition.D;
        }

        /// <summary>Suit un trajet précalculé ; 'startProgress' permet de reprendre en cours de route.</summary>
        public void BeginRoute(List<Vector3> points, List<sbyte> kinds, float skillFactor, float startProgress)
        {
            path.Set(points, kinds);
            skill = skillFactor; lane = 0f; useLimits = false;
            segment = 0; stuckTime = 0f; IsStuck = false; Finished = false;
            path.PointAt(startProgress, out segment);
            Progress = startProgress;
            CurrentMode = Mode.Route;
            vehicle.Gear = GearPosition.D;
        }

        public void Stop()
        {
            CurrentMode = Mode.Idle;
            if (vehicle != null) vehicle.SetInputs(0f, 0f, 1f, false);
        }

        private void ExtendWander()
        {
            int guard = 0;
            while (path.Length - Progress < WanderAhead && guard++ < 40)
            {
                int next = graph.RandomNeighbor(currentNode, lastNode, rnd, out sbyte kind);
                if (next < 0) break;
                path.Add(graph.PositionOf(next), kind);
                lastNode = currentNode; currentNode = next;
            }
        }

        // ---------- Pilotage ----------

        private void Update()
        {
            if (CurrentMode == Mode.Idle || vehicle == null) return;
            if (Hold) { vehicle.SetInputs(0f, 0f, 1f, false); return; }
            if (path.Count < 2) return;

            float dt = Time.deltaTime;
            Vector3 pos = transform.position;
            float speed = vehicle.SpeedKmh / 3.6f;

            Progress = path.Project(pos, ref segment, out float error);
            if (CurrentMode == Mode.Wander) ExtendWander();
            else if (Progress >= path.Length - 8f) { Finished = true; vehicle.SetInputs(0f, 0f, 0.6f, false); return; }

            // Point d'anticipation (décalé vers la droite pour le trafic)
            float look = Mathf.Clamp(LookBase + LookGain * speed, LookMin, LookMax);
            Vector3 target = path.PointAt(Progress + look, out int targetSeg);
            if (lane > 0f)
            {
                float h = path.HeadingAt(Progress + look) * Mathf.Deg2Rad;
                target += new Vector3(Mathf.Cos(h), 0f, -Mathf.Sin(h)) * lane;      // droite = (dz, -dx)
            }

            Vector3 to = target - pos;
            float desired = Mathf.Atan2(to.x, to.z) * Mathf.Rad2Deg;
            float steer = Mathf.Clamp(Mathf.DeltaAngle(transform.eulerAngles.y, desired) / SteerDegrees, -1f, 1f);

            // Vitesse visée : limite de la route (trafic) ou vitesse max (course), réduite avant un virage et derrière un obstacle
            float ahead = CornerAheadBase + CornerAheadGain * speed;
            float turn = Mathf.Abs(Mathf.DeltaAngle(path.HeadingAt(Progress + 3f), path.HeadingAt(Progress + ahead)));
            float cornerFactor = 1f - (1f - CornerMinFactor) * Mathf.Clamp01((turn - CornerFromDeg) / (CornerToDeg - CornerFromDeg));

            float rain = WeatherManager.Instance != null ? WeatherManager.Instance.AiSpeedFactor : 1f;     // sous la pluie : -10 % à -20 %
            float limit = vehicle.Stats.maxSpeedKmh / 3.6f * skill * rain;
            if (useLimits) limit = Mathf.Min(limit, RoadGraph.SpeedLimit(path.KindAt(segment)) * skill * rain);
            float targetSpeed = limit * cornerFactor * ObstacleFactor(pos);

            float e = targetSpeed - speed;
            float throttle = e > 0f ? Mathf.Clamp01(e / ThrottleRange) : 0f;
            float brake = e < 0f ? Mathf.Clamp01(-e / BrakeRange) : 0f;
            vehicle.SetInputs(steer, throttle, brake, false);

            // Bloqué (mur, autre véhicule) : le gestionnaire recyclera ou replacera ce véhicule
            if (speed < 0.5f && targetSpeed > 2f) stuckTime += dt; else stuckTime = 0f;
            IsStuck = stuckTime > 5f;
        }

        /// <summary>1 = route libre ; tend vers 0 quand un véhicule (ou le joueur) est juste devant.</summary>
        private float ObstacleFactor(Vector3 pos)
        {
            if (obstacleRange <= 0f) return 1f;
            Vector3 fwd = transform.forward; fwd.y = 0f; fwd.Normalize();
            float nearest = float.MaxValue;
            for (int i = 0; i < Active.Count; i++)
            {
                var other = Active[i];
                if (other == this || !other.isActiveAndEnabled) continue;
                nearest = Mathf.Min(nearest, DistanceAhead(pos, fwd, other.transform.position));
            }
            if (PlayerTransform != null) nearest = Mathf.Min(nearest, DistanceAhead(pos, fwd, PlayerTransform.position));
            return nearest >= obstacleRange ? 1f : Mathf.Clamp01((nearest - 5f) / (obstacleRange - 5f));
        }

        private static float DistanceAhead(Vector3 pos, Vector3 fwd, Vector3 other)
        {
            Vector3 rel = other - pos; rel.y = 0f;
            float along = Vector3.Dot(rel, fwd);
            if (along <= 0f || along > 60f) return float.MaxValue;
            float lateral = Mathf.Abs(rel.x * fwd.z - rel.z * fwd.x);
            return lateral < 2.4f ? along - 2f : float.MaxValue;      // dans mon couloir, moins la moitié d'un véhicule
        }
    }
}

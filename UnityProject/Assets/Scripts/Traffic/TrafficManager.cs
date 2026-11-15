using System.Collections.Generic;
using UnityEngine;
using TaxiKin.Map;
using TaxiKin.Missions;
using TaxiKin.Player;
using TaxiKin.Vehicles;

namespace TaxiKin.Traffic
{
    /// <summary>
    /// Trafic : voitures, motos et camions qui roulent sur le vrai réseau routier autour du joueur.
    ///  - Réserve d'objets (pas d'Instantiate/Destroy en jeu) ; plafond selon la qualité graphique (6 / 12 / 20), densité demandée par la mission.
    ///  - Apparition sur une route à 100-250 m du joueur, hors de sa vue directe, dans une zone dont le sol existe ;
    ///    disparition à plus de 300 m, en cas de chute ou de blocage.
    ///  - Détection des véhicules « évités » (mission « Évite les embouteillages ») : passer à moins de 6 m à plus de 12 km/h
    ///    sans choc compte pour un véhicule évité.
    /// Les modèles sont chargés depuis Resources/Traffic (voir TaxiKinAiPrefabBuilder).
    /// </summary>
    public class TrafficManager : MonoBehaviour
    {
        [SerializeField] private PlayerController player;
        [SerializeField] private WorldManager world;
        [SerializeField] private MissionManager missions;
        [SerializeField, Range(0, 40)] private int maxActiveVehicles = 12;
        [SerializeField] private float spawnMinRadius = 100f, spawnMaxRadius = 250f, despawnRadius = 300f;

        private enum VehicleKindIndex { Car = 0, Moto = 1, Truck = 2 }
        private static readonly string[] PrefabNames = { "Traffic/TrafficCar", "Traffic/TrafficMoto", "Traffic/TrafficTruck" };
        // Part de chaque type et vitesse relative : voitures 60 %, motos 25 %, camions 15 %
        private static readonly float[] Weights = { 0.60f, 0.25f, 0.15f };
        private static readonly float[] SpeedMin = { 0.85f, 1.00f, 0.65f };
        private static readonly float[] SpeedMax = { 1.00f, 1.15f, 0.80f };
        private static readonly Color[] CarColors =
        {
            new Color(1f, 0.831f, 0f), new Color(1f, 0.831f, 0f), new Color(1f, 0.765f, 0f),   // beaucoup de taxis jaunes
            Color.white, new Color(0.15f, 0.15f, 0.17f), new Color(0.75f, 0.12f, 0.12f), new Color(0.12f, 0.33f, 0.66f), new Color(0.5f, 0.52f, 0.56f),
        };
        private static readonly int BaseColorId = Shader.PropertyToID("_BaseColor");
        private static readonly int ColorId = Shader.PropertyToID("_Color");

        private class Entry
        {
            public GameObject go; public VehicleAI ai; public VehicleController vehicle; public int type;
            public bool near, hit, counted;
        }

        private readonly Queue<Entry>[] pools = { new Queue<Entry>(), new Queue<Entry>(), new Queue<Entry>() };
        private readonly GameObject[] prefabs = new GameObject[3];
        private readonly List<Entry> active = new List<Entry>();
        private readonly List<int> ring = new List<int>();
        private readonly System.Random rnd = new System.Random();
        private int qualityCap = 12;
        private float nextSpawn, nextAvoid, nextHousekeeping;
        private MaterialPropertyBlock block;
        private VehicleController watched;
        private bool prefabsLoaded;

        public int ActiveCount => active.Count;
        private int EffectiveMax => Mathf.Min(maxActiveVehicles, qualityCap);

        /// <summary>Densité demandée (30 pour une mission « trafic dense »). Le plafond de qualité l'emporte toujours.</summary>
        public void SetMaxVehicles(int max) => maxActiveVehicles = Mathf.Max(0, max);

        /// <summary>Plafond imposé par la qualité graphique (6 / 12 / 20).</summary>
        public void SetQualityCap(int cap) => qualityCap = Mathf.Max(0, cap);

        private void OnEnable() { if (player != null) player.OnVehicleChanged += OnVehicleChanged; }

        private void OnDisable()
        {
            if (player != null) player.OnVehicleChanged -= OnVehicleChanged;
            if (watched != null) watched.OnImpact -= OnPlayerImpact;
        }

        private void OnVehicleChanged(VehicleController v)
        {
            if (watched != null) watched.OnImpact -= OnPlayerImpact;
            watched = v;
            if (watched != null) watched.OnImpact += OnPlayerImpact;
            VehicleAI.PlayerTransform = v != null ? v.transform : null;
        }

        // Un choc avec le joueur annule le « évité » de tous les véhicules proches
        private void OnPlayerImpact(float impact) { foreach (var e in active) if (e.near) e.hit = true; }

        private void LoadPrefabs()
        {
            prefabsLoaded = true;
            for (int i = 0; i < prefabs.Length; i++) prefabs[i] = Resources.Load<GameObject>(PrefabNames[i]);
        }

        private void Update()
        {
            var pv = player != null ? player.Vehicle : null;
            if (pv == null || world == null || !world.GraphReady) return;
            if (!prefabsLoaded) LoadPrefabs();

            if (Time.unscaledTime >= nextAvoid) { nextAvoid = Time.unscaledTime + 0.1f; DetectAvoided(pv); }
            if (Time.unscaledTime >= nextHousekeeping) { nextHousekeeping = Time.unscaledTime + 0.5f; Housekeeping(pv); }
            if (Time.unscaledTime >= nextSpawn && active.Count < EffectiveMax) { nextSpawn = Time.unscaledTime + 0.5f; TrySpawn(pv); }
        }

        // ---------- Disparition ----------

        private void Housekeeping(VehicleController pv)
        {
            Vector3 p = pv.transform.position;
            for (int i = active.Count - 1; i >= 0; i--)
            {
                var e = active[i];
                Vector3 d = e.go.transform.position - p; d.y = 0f;
                bool far = d.sqrMagnitude > despawnRadius * despawnRadius;
                bool fell = e.go.transform.position.y < -3f;
                bool excess = active.Count > EffectiveMax && d.sqrMagnitude > 120f * 120f;     // qualité baissée : on retire les plus éloignés
                if (far || fell || excess || e.ai.IsStuck) Despawn(i);
            }
        }

        private void Despawn(int index)
        {
            var e = active[index];
            active.RemoveAt(index);
            e.ai.Stop();
            e.go.SetActive(false);
            pools[e.type].Enqueue(e);
        }

        // ---------- Apparition ----------

        private void TrySpawn(VehicleController pv)
        {
            var graph = world.Graph;
            Vector3 p = pv.transform.position, fwd = pv.transform.forward; fwd.y = 0f; fwd.Normalize();
            graph.NodesInRing(p, spawnMinRadius, spawnMaxRadius, ring);
            if (ring.Count == 0) return;

            for (int attempt = 0; attempt < 8; attempt++)       // quelques essais : tous les nœuds ne conviennent pas
            {
                int node = ring[rnd.Next(ring.Count)];
                Vector3 pos = graph.PositionOf(node);
                if (!world.IsGroundReadyAt(pos)) continue;

                Vector3 rel = pos - p; rel.y = 0f;
                float dist = rel.magnitude;
                if (dist < 150f && Vector3.Angle(fwd, rel) < 60f) continue;      // jamais d'apparition à vue juste devant
                if (TooCloseToOthers(pos)) continue;

                Spawn(graph, node, pos);
                return;
            }
        }

        private bool TooCloseToOthers(Vector3 pos)
        {
            foreach (var e in active)
            {
                Vector3 d = e.go.transform.position - pos; d.y = 0f;
                if (d.sqrMagnitude < 30f * 30f) return true;
            }
            return false;
        }

        private void Spawn(RoadGraph graph, int node, Vector3 pos)
        {
            int type = PickType();
            var prefab = prefabs[type];
            if (prefab == null) return;

            Entry e = pools[type].Count > 0 ? pools[type].Dequeue() : Create(type, prefab);
            e.near = e.hit = e.counted = false;

            int next = graph.RandomNeighbor(node, -1, rnd, out _);
            Vector3 dir = next >= 0 ? graph.PositionOf(next) - pos : Vector3.forward;
            dir.y = 0f;
            Quaternion rot = dir.sqrMagnitude > 0.01f ? Quaternion.LookRotation(dir) : Quaternion.identity;

            e.go.SetActive(true);
            e.vehicle.ResetMotion(pos + Vector3.up * e.ai.GroundOffset, rot);
            e.vehicle.Repair(100f);
            e.vehicle.Refuel(1000f);
            if (type == (int)VehicleKindIndex.Car) Tint(e.go, CarColors[rnd.Next(CarColors.Length)]);
            float factor = Mathf.Lerp(SpeedMin[type], SpeedMax[type], (float)rnd.NextDouble());
            e.ai.BeginWander(graph, node, rnd, factor);
            active.Add(e);
        }

        private Entry Create(int type, GameObject prefab)
        {
            var go = Instantiate(prefab, transform);
            return new Entry { go = go, ai = go.GetComponent<VehicleAI>(), vehicle = go.GetComponent<VehicleController>(), type = type };
        }

        private int PickType()
        {
            float r = (float)rnd.NextDouble(), acc = 0f;
            for (int i = 0; i < Weights.Length; i++) { acc += Weights[i]; if (r <= acc) return i; }
            return 0;
        }

        private void Tint(GameObject go, Color c)
        {
            var r = go.GetComponentInChildren<Renderer>();
            if (r == null) return;
            block ??= new MaterialPropertyBlock();
            r.GetPropertyBlock(block);
            block.SetColor(BaseColorId, c); block.SetColor(ColorId, c);
            r.SetPropertyBlock(block);
        }

        // ---------- Véhicules « évités » ----------

        private void DetectAvoided(VehicleController pv)
        {
            if (pv.SpeedKmh < 12f) return;
            Vector3 p = pv.transform.position;
            foreach (var e in active)
            {
                Vector3 d = e.go.transform.position - p; d.y = 0f;
                float dist = d.magnitude;
                if (!e.near && dist < 6f) { e.near = true; e.hit = false; }
                else if (e.near && dist > 11f)
                {
                    e.near = false;
                    if (!e.hit && !e.counted) { e.counted = true; missions?.ReportAvoidedVehicle(); }     // frôlé sans choc
                }
            }
        }
    }
}

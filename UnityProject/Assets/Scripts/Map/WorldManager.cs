using System.Collections;
using System.Collections.Generic;
using UnityEngine;
using TaxiKin.Player;

namespace TaxiKin.Map
{
    /// <summary>
    /// Le monde de Kinshasa : 5 zones à l'échelle réelle posées dans un repère global « compressé », reliées par des corridors courts
    /// (disposition calculée par Tools/build_world.py → Resources/Zones/world.json).
    ///  - Les zones se chargent quand le véhicule s'en approche et se déchargent quand il s'éloigne : une, parfois deux en mémoire.
    ///  - Les corridors sont construits une fois (très légers) et toujours visibles.
    ///  - Les repères (gare, aéroport, hôtels, arrêts…) existent pour TOUTES les zones, même non chargées : les missions peuvent
    ///    donc viser une destination lointaine.
    ///  - Guidage : chemin zone → corridor → zone, avec distance restante réelle (et non à vol d'oiseau).
    /// </summary>
    public class WorldManager : MonoBehaviour
    {
        public static WorldManager Instance { get; private set; }

        [SerializeField] private PlayerController player;
        [SerializeField] private Material roadMaterial, buildingMaterial, groundMaterial, corridorGroundMaterial, decorMaterial;
        [SerializeField] private float loadDistance = 450f;       // charge une zone quand le véhicule est à moins de 450 m de son sol
        [SerializeField] private float unloadDistance = 700f;     // la décharge au-delà de 700 m (hystérésis : pas de va-et-vient)
        [SerializeField] private bool buildingColliders = true;

        private class Slot
        {
            public WorldZone data;
            public ZoneLoader loader;
            public Transform center;
            public readonly List<Transform> pois = new List<Transform>();
            public Vector3 Origin => new Vector3(data.originX, 0f, data.originZ);
        }

        private struct Edge { public string to; public WorldCorridor corridor; public bool fromA; }

        private readonly Dictionary<string, Slot> slots = new Dictionary<string, Slot>();
        private readonly List<WorldCorridor> corridors = new List<WorldCorridor>();
        private readonly Dictionary<string, List<Edge>> adjacency = new Dictionary<string, List<Edge>>();
        private readonly HashSet<string> pinned = new HashSet<string>();
        private readonly List<Mesh> meshes = new List<Mesh>();
        private float nextStream;

        public bool IsReady { get; private set; }
        /// <summary>Réseau routier de toutes les zones (trafic et course). Prêt quelques images après le démarrage.</summary>
        public RoadGraph Graph { get; private set; }
        public bool GraphReady { get; private set; }
        public string Attribution { get; private set; } = "";

        private void Awake()
        {
            if (Instance != null && Instance != this) { Destroy(this); return; }
            Instance = this;

            var asset = Resources.Load<TextAsset>("Zones/world");
            if (asset == null) { Debug.LogWarning("[World] Zones/world.json introuvable : lancer Tools/build_world.py"); return; }
            var world = JsonUtility.FromJson<WorldData>(asset.text);
            Resources.UnloadAsset(asset);
            Attribution = world.attribution;

            var markers = new GameObject("Reperes").transform;
            markers.SetParent(transform, false);
            var corridorRoot = new GameObject("Corridors").transform;
            corridorRoot.SetParent(transform, false);

            foreach (var z in world.zones)
            {
                var slot = new Slot { data = z };
                var holder = new GameObject("Zone_" + z.id);
                holder.transform.SetParent(transform, false);
                slot.loader = holder.AddComponent<ZoneLoader>();
                slot.loader.Configure(roadMaterial, buildingMaterial, groundMaterial, player, buildingColliders);   // avant Start : pas de chargement automatique

                slot.center = Marker(markers, "Centre_" + z.id, slot.Origin + new Vector3(z.centerX, 0f, z.centerZ));   // centre recalé sur la route
                if (z.pois != null)
                    foreach (var p in z.pois)
                        slot.pois.Add(Marker(markers, $"POI_{p.kind}_{z.id}", slot.Origin + new Vector3(p.x, 0f, p.z)));
                slots[z.id] = slot;
                adjacency[z.id] = new List<Edge>();
            }

            foreach (var c in world.corridors)
            {
                corridors.Add(c);
                adjacency[c.a].Add(new Edge { to = c.b, corridor = c, fromA = true });
                adjacency[c.b].Add(new Edge { to = c.a, corridor = c, fromA = false });
                CorridorBuilder.Build(c, corridorGroundMaterial, roadMaterial, decorMaterial, corridorRoot, meshes);
            }
            IsReady = true;
            worldData = world;
        }

        private WorldData worldData;

        private void Start()
        {
            if (IsReady) StartCoroutine(BuildGraphRoutine());
        }

        /// <summary>Construit le réseau routier en étalant le travail : une zone par image.</summary>
        private IEnumerator BuildGraphRoutine()
        {
            var g = new RoadGraph();
            foreach (var z in worldData.zones)
            {
                var asset = Resources.Load<TextAsset>("Zones/" + z.id);
                if (asset != null)
                {
                    g.AddZone(z, JsonUtility.FromJson<ZoneData>(asset.text));
                    Resources.UnloadAsset(asset);
                }
                yield return null;
            }
            foreach (var c in worldData.corridors) g.AddCorridor(c);
            g.Finish();
            Graph = g;
            GraphReady = true;
        }

        private static Transform Marker(Transform parent, string name, Vector3 position)
        {
            var t = new GameObject(name).transform;
            t.SetParent(parent, false);
            t.position = position;
            return t;
        }

        private void OnDestroy() { foreach (var m in meshes) if (m != null) Destroy(m); }

        // ---------- Chargement par proximité ----------

        private void Update()
        {
            if (!IsReady || Time.unscaledTime < nextStream) return;
            nextStream = Time.unscaledTime + 0.25f;

            var v = player != null ? player.Vehicle : null;
            if (v == null) return;
            Vector3 p = v.transform.position;

            foreach (var kv in slots)
            {
                var s = kv.Value;
                float d = DistanceToZone(s, p);
                bool active = s.loader.IsLoaded || s.loader.IsLoading;
                if (!active && d <= loadDistance) s.loader.Load(s.data.id, s.Origin);
                else if (active && d > unloadDistance && !pinned.Contains(s.data.id)) s.loader.Unload();
            }
        }

        private static float DistanceToZone(Slot s, Vector3 p)
        {
            float dx = Mathf.Max(Mathf.Abs(p.x - s.data.originX) - s.data.half, 0f);
            float dz = Mathf.Max(Mathf.Abs(p.z - s.data.originZ) - s.data.half, 0f);
            return Mathf.Sqrt(dx * dx + dz * dz);
        }

        /// <summary>Force le chargement d'une zone (départ de mission) et la garde en mémoire tant qu'elle est « épinglée ».</summary>
        public void RequestZone(string zoneId)
        {
            if (!slots.TryGetValue(zoneId, out var s)) return;
            if (!s.loader.IsLoaded && !s.loader.IsLoading) s.loader.Load(zoneId, s.Origin);
        }

        public void Pin(string zoneId) => pinned.Add(zoneId);
        public void Unpin(string zoneId) => pinned.Remove(zoneId);
        public bool HasZone(string zoneId) => !string.IsNullOrEmpty(zoneId) && slots.ContainsKey(zoneId);
        public bool IsGroundReady(string zoneId) => slots.TryGetValue(zoneId, out var s) && s.loader.GroundReady;

        /// <summary>Le sol existe sous ce point (zone chargée ou corridor) : on peut y poser un véhicule.</summary>
        public bool IsGroundReadyAt(Vector3 p)
        {
            string zone = ZoneAt(p);
            return zone != null ? IsGroundReady(zone) : IsInCorridor(p);
        }

        private bool IsInCorridor(Vector3 p)
        {
            foreach (var c in corridors)
            {
                Vector3 a = new Vector3(c.ax, 0f, c.az), b = new Vector3(c.bx, 0f, c.bz);
                Vector3 dir = (b - a).normalized;
                Vector3 rel = new Vector3(p.x - a.x, 0f, p.z - a.z);
                float s = Vector3.Dot(rel, dir);
                float lateral = Mathf.Abs(rel.x * dir.z - rel.z * dir.x);
                if (s >= -20f && s <= c.length + 20f && lateral <= c.stripHalfWidth) return true;
            }
            return false;
        }



        /// <summary>Zones actuellement en mémoire (pour les mesures et le débogage).</summary>
        public int LoadedZoneCount { get { int n = 0; foreach (var s in slots.Values) if (s.loader.IsLoaded || s.loader.IsLoading) n++; return n; } }

        // ---------- Repères ----------

        public Transform ZoneCenter(string zoneId) => slots.TryGetValue(zoneId, out var s) ? s.center : null;

        public Transform GetPoi(string zoneId, string kind)
        {
            if (!slots.TryGetValue(zoneId, out var s)) return null;
            string prefix = "POI_" + kind + "_";
            foreach (var t in s.pois) if (t.name.StartsWith(prefix)) return t;
            return null;
        }

        /// <summary>Repère d'un type donné le plus proche d'une position, toutes zones confondues (arrêts de bus, par exemple).</summary>
        public Transform NearestPoi(string kind, Vector3 position, float maxDistance)
        {
            Transform best = null;
            float bestSqr = maxDistance * maxDistance;
            foreach (var s in slots.Values)
            {
                string prefix = "POI_" + kind + "_";
                foreach (var t in s.pois)
                {
                    if (!t.name.StartsWith(prefix)) continue;
                    float d = (t.position - position).sqrMagnitude;
                    if (d <= bestSqr) { bestSqr = d; best = t; }
                }
            }
            return best;
        }

        /// <summary>Identifiant de la zone dont le sol contient ce point (null dans un corridor ou dans le vide).</summary>
        public string ZoneAt(Vector3 p)
        {
            foreach (var s in slots.Values)
                if (Mathf.Abs(p.x - s.data.originX) <= s.data.half && Mathf.Abs(p.z - s.data.originZ) <= s.data.half) return s.data.id;
            return null;
        }

        // ---------- Guidage : zone → corridor → zone ----------

        /// <summary>
        /// Prochain point à rejoindre et distance restante RÉELLE (le long des corridors) vers une destination.
        /// Retourne faux si la position n'est ni dans une zone ni dans un corridor (le guidage se fait alors à vol d'oiseau).
        /// </summary>
        public bool GetGuidance(Vector3 playerPos, string destZone, Vector3 destPos, out Vector3 target, out float remaining)
        {
            target = destPos; remaining = Vector3.Distance(Flat(playerPos), Flat(destPos));
            if (!IsReady || !HasZone(destZone)) return false;

            string zone = ZoneAt(playerPos);
            if (zone != null)
            {
                remaining = PathLength(zone, playerPos, destZone, destPos, out target);
                return true;
            }

            // Dans un corridor : on peut le finir par l'un ou l'autre bout, on prend le plus court
            foreach (var c in corridors)
            {
                Vector3 a = new Vector3(c.ax, 0f, c.az), b = new Vector3(c.bx, 0f, c.bz);
                Vector3 dir = (b - a).normalized;
                Vector3 rel = Flat(playerPos) - a;
                float s = Vector3.Dot(rel, dir);
                float lateral = Mathf.Abs(rel.x * dir.z - rel.z * dir.x);
                if (s < -30f || s > c.length + 30f || lateral > c.stripHalfWidth + 20f) continue;

                float viaA = Mathf.Max(0f, s) + PathLength(c.a, a, destZone, destPos, out var tA);
                float viaB = Mathf.Max(0f, c.length - s) + PathLength(c.b, b, destZone, destPos, out var tB);
                bool useB = viaB <= viaA;
                target = useB ? b : a;
                remaining = useB ? viaB : viaA;
                return true;
            }
            return false;
        }

        /// <summary>Longueur du chemin depuis un point situé dans 'zone' jusqu'à la destination ; 'firstTarget' = premier point à viser.</summary>
        private float PathLength(string zone, Vector3 entry, string destZone, Vector3 destPos, out Vector3 firstTarget)
        {
            var edges = FindPath(zone, destZone);
            Vector3 cur = Flat(entry);
            firstTarget = destPos;
            float total = 0f;
            bool first = true;
            foreach (var e in edges)
            {
                var c = e.corridor;
                Vector3 gateHere = e.fromA ? new Vector3(c.ax, 0f, c.az) : new Vector3(c.bx, 0f, c.bz);
                Vector3 gateThere = e.fromA ? new Vector3(c.bx, 0f, c.bz) : new Vector3(c.ax, 0f, c.az);
                total += Vector3.Distance(cur, gateHere) + c.length;
                if (first) { firstTarget = gateHere; first = false; }
                cur = gateThere;
            }
            return total + Vector3.Distance(cur, Flat(destPos));
        }

        /// <summary>Plus court chemin (en nombre de corridors) entre deux zones : parcours en largeur.</summary>
        private List<Edge> FindPath(string from, string to)
        {
            var result = new List<Edge>();
            if (from == to || !adjacency.ContainsKey(from) || !adjacency.ContainsKey(to)) return result;

            var previous = new Dictionary<string, KeyValuePair<string, Edge>>();
            var queue = new Queue<string>();
            queue.Enqueue(from);
            previous[from] = default;
            while (queue.Count > 0)
            {
                string cur = queue.Dequeue();
                foreach (var e in adjacency[cur])
                {
                    if (previous.ContainsKey(e.to)) continue;
                    previous[e.to] = new KeyValuePair<string, Edge>(cur, e);
                    if (e.to == to) { queue.Clear(); break; }
                    queue.Enqueue(e.to);
                }
            }
            if (!previous.ContainsKey(to)) return result;

            string node = to;
            while (node != from)
            {
                var step = previous[node];
                result.Add(step.Value);
                node = step.Key;
            }
            result.Reverse();
            return result;
        }

        private static Vector3 Flat(Vector3 v) => new Vector3(v.x, 0f, v.z);
    }
}

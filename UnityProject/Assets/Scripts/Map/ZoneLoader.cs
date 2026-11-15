using System;
using System.Collections;
using System.Collections.Generic;
using System.Diagnostics;
using UnityEngine;
using TaxiKin.Player;
using Debug = UnityEngine.Debug;

namespace TaxiKin.Map
{
    /// <summary>
    /// Charge et décharge UNE zone de Kinshasa à la fois (fichier JSON dans Resources/Zones).
    ///  - Chargement étalé sur plusieurs images (budget en ms par image) : pas de gel de l'écran.
    ///  - Zone découpée en blocs de 250 m : les blocs lointains ne sont pas dessinés (distance fixée par la qualité),
    ///    la collision des immeubles n'existe que près du joueur.
    /// Les points d'intérêt servent de départs et de destinations pour les missions.
    /// </summary>
    public class ZoneLoader : MonoBehaviour
    {
        /// <summary>Distance d'affichage en mètres (fixée par QualityManager selon la qualité).</summary>
        public static float DrawDistance = 350f;

        private const float CellSize = 250f;
        private const float ColliderDistance = 160f;     // collision des immeubles seulement en dessous de cette distance

        [SerializeField] private Material roadMaterial;
        [SerializeField] private Material buildingMaterial;
        [SerializeField] private Material groundMaterial;
        [SerializeField] private PlayerController player;
        [SerializeField] private string autoLoadZoneId = "gare_centrale";
        [Tooltip("Collision sur les immeubles (créée à la demande près du joueur). À couper si le framerate chute.")]
        [SerializeField] private bool buildingColliders = true;
        [Tooltip("Temps maximal passé à fabriquer des blocs par image (ms).")]
        [SerializeField] private float loadBudgetMs = 6f;

        public ZoneData Data { get; private set; }
        public bool IsLoaded { get; private set; }
        public bool IsLoading { get; private set; }
        public string Attribution => Data != null ? Data.attribution : string.Empty;
        public event Action OnLoaded;

        private GameObject root;
        private readonly List<Mesh> meshes = new List<Mesh>();
        private readonly List<Transform> pois = new List<Transform>();
        private readonly List<ZoneChunk> chunks = new List<ZoneChunk>();
        private Coroutine loading;
        private float nextCull;

        private void Start()
        {
            if (!string.IsNullOrEmpty(autoLoadZoneId)) Load(autoLoadZoneId);
        }

        /// <summary>Lance le chargement (asynchrone). Termine par OnLoaded.</summary>
        public void Load(string zoneId)
        {
            Unload();
            loading = StartCoroutine(LoadRoutine(zoneId));
        }

        public void Unload()
        {
            if (loading != null) { StopCoroutine(loading); loading = null; }
            if (root != null) Destroy(root);
            foreach (var m in meshes) if (m != null) Destroy(m);
            bool hadZone = IsLoaded || IsLoading;
            meshes.Clear(); pois.Clear(); chunks.Clear();
            root = null; Data = null;
            IsLoaded = false; IsLoading = false;
            if (hadZone) Resources.UnloadUnusedAssets();    // libère la mémoire de la zone quittée
        }

        /// <summary>Premier point d'intérêt d'un type donné (ex. "station", "marche", "aeroport").</summary>
        public Transform FindPoi(string kind)
        {
            foreach (var p in pois) if (p != null && p.name.StartsWith("POI_" + kind)) return p;
            return null;
        }

        /// <summary>Point d'intérêt d'un type donné le plus proche d'une position (null si aucun dans la portée).</summary>
        public Transform NearestPoi(string kind, Vector3 position, float maxDistance)
        {
            Transform best = null;
            float bestSqr = maxDistance * maxDistance;
            foreach (var p in pois)
            {
                if (p == null || !p.name.StartsWith("POI_" + kind)) continue;
                float d = (p.position - position).sqrMagnitude;
                if (d <= bestSqr) { bestSqr = d; best = p; }
            }
            return best;
        }

        // ---------- Chargement étalé ----------

        private IEnumerator LoadRoutine(string zoneId)
        {
            IsLoading = true;
            var asset = Resources.Load<TextAsset>("Zones/" + zoneId);
            if (asset == null)
            {
                Debug.LogError($"[ZoneLoader] Zone introuvable : Resources/Zones/{zoneId}.json");
                IsLoading = false; loading = null;
                yield break;
            }
            Data = JsonUtility.FromJson<ZoneData>(asset.text);
            Resources.UnloadAsset(asset);   // le texte brut n'est plus nécessaire
            yield return null;

            root = new GameObject("Zone_" + Data.id);
            root.transform.SetParent(transform, false);

            var ground = GameObject.CreatePrimitive(PrimitiveType.Plane);
            ground.name = "Sol";
            ground.transform.SetParent(root.transform, false);
            float size = (Data.radius * 2f + 200f) / 10f;   // un Plane Unity fait 10 m
            ground.transform.localScale = new Vector3(size, 1f, size);
            if (groundMaterial != null) ground.GetComponent<Renderer>().sharedMaterial = groundMaterial;

            // Répartition des routes (par tronçon) et des immeubles (par centre) dans la grille de blocs
            var roadCells = new Dictionary<long, List<ZoneMeshBuilder.RoadSegment>>();
            var buildingCells = new Dictionary<long, List<ZoneBuilding>>();
            var keys = new HashSet<long>();

            if (Data.roads != null)
                foreach (var r in Data.roads)
                    for (int i = 0; i + 3 < r.pts.Length; i += 2)
                    {
                        var seg = new ZoneMeshBuilder.RoadSegment
                        { a = new Vector2(r.pts[i], r.pts[i + 1]), b = new Vector2(r.pts[i + 2], r.pts[i + 3]), width = r.width, kind = r.kind };
                        long k = ZoneMeshBuilder.CellKey((seg.a.x + seg.b.x) * 0.5f, (seg.a.y + seg.b.y) * 0.5f, CellSize);
                        if (!roadCells.TryGetValue(k, out var list)) roadCells[k] = list = new List<ZoneMeshBuilder.RoadSegment>();
                        list.Add(seg);
                        keys.Add(k);
                    }

            if (Data.buildings != null)
                foreach (var b in Data.buildings)
                {
                    if (b.pts == null || b.pts.Length < 6) continue;
                    float cx = 0f, cz = 0f; int n = b.pts.Length / 2;
                    for (int i = 0; i < n; i++) { cx += b.pts[i * 2]; cz += b.pts[i * 2 + 1]; }
                    long k = ZoneMeshBuilder.CellKey(cx / n, cz / n, CellSize);
                    if (!buildingCells.TryGetValue(k, out var list)) buildingCells[k] = list = new List<ZoneBuilding>();
                    list.Add(b);
                    keys.Add(k);
                }
            yield return null;

            // Fabrication des blocs : quelques millisecondes par image, puis on rend la main
            var watch = Stopwatch.StartNew();
            foreach (long key in keys)
            {
                roadCells.TryGetValue(key, out var rs);
                buildingCells.TryGetValue(key, out var bs);
                BuildChunk(key, rs, bs);
                if (watch.Elapsed.TotalMilliseconds > loadBudgetMs) { yield return null; watch.Restart(); }
            }

            if (Data.pois != null)
                foreach (var p in Data.pois)
                {
                    var go = new GameObject($"POI_{p.kind}_{p.name}");
                    go.transform.SetParent(root.transform, false);
                    go.transform.localPosition = new Vector3(p.x, 0f, p.z);
                    pois.Add(go.transform);
                }

            IsLoading = false;
            IsLoaded = true;
            loading = null;
            nextCull = 0f;
            OnLoaded?.Invoke();
        }

        private void BuildChunk(long key, List<ZoneMeshBuilder.RoadSegment> roads, List<ZoneBuilding> buildings)
        {
            var roadMesh = ZoneMeshBuilder.BuildRoads(roads);
            var buildingMesh = ZoneMeshBuilder.BuildBuildings(buildings);
            if (roadMesh == null && buildingMesh == null) return;

            var chunkGo = new GameObject("Bloc_" + key);
            chunkGo.transform.SetParent(root.transform, false);
            var renderers = new List<MeshRenderer>();
            MeshFilter buildingFilter = null;
            Bounds bounds = default; bool first = true;

            if (roadMesh != null) { meshes.Add(roadMesh); renderers.Add(AddMesh(chunkGo.transform, "Routes", roadMesh, roadMaterial, out _)); Grow(ref bounds, ref first, roadMesh.bounds); }
            if (buildingMesh != null)
            {
                meshes.Add(buildingMesh);
                renderers.Add(AddMesh(chunkGo.transform, "Immeubles", buildingMesh, buildingMaterial, out buildingFilter));
                Grow(ref bounds, ref first, buildingMesh.bounds);
            }

            var chunk = chunkGo.AddComponent<ZoneChunk>();
            chunk.Init(renderers.ToArray(), buildingColliders ? buildingFilter : null,
                       root.transform.TransformPoint(bounds.center), bounds.extents.magnitude);
            chunks.Add(chunk);
        }

        private static void Grow(ref Bounds total, ref bool first, Bounds b)
        {
            if (first) { total = b; first = false; } else total.Encapsulate(b);
        }

        private static MeshRenderer AddMesh(Transform parent, string name, Mesh mesh, Material mat, out MeshFilter filter)
        {
            var go = new GameObject(name, typeof(MeshFilter), typeof(MeshRenderer));
            go.transform.SetParent(parent, false);
            filter = go.GetComponent<MeshFilter>();
            filter.sharedMesh = mesh;
            var r = go.GetComponent<MeshRenderer>();
            r.sharedMaterial = mat;
            r.shadowCastingMode = UnityEngine.Rendering.ShadowCastingMode.Off;   // ombres des immeubles : trop coûteuses sur mobile
            r.receiveShadows = false;
            return r;
        }

        // ---------- Affichage et collisions selon la distance au joueur (4 fois par seconde) ----------

        private void Update()
        {
            if (!IsLoaded || Time.unscaledTime < nextCull) return;
            nextCull = Time.unscaledTime + 0.25f;

            var v = player != null ? player.Vehicle : null;
            if (v == null) return;
            Vector3 p = v.transform.position;
            bool cookedOne = false;

            foreach (var c in chunks)
            {
                Vector3 d = c.Center - p; d.y = 0f;
                float dist = d.magnitude - c.Radius;
                c.SetVisible(dist <= DrawDistance);

                bool wantCollider = c.HasBuildings && dist <= ColliderDistance;
                if (wantCollider && !c.HasCollider)
                {
                    if (cookedOne) continue;          // une seule création de collision par passage : pas de saccade
                    cookedOne = true;
                }
                if (c.HasBuildings && (wantCollider || c.HasCollider)) c.SetCollider(wantCollider);
            }
        }
    }
}

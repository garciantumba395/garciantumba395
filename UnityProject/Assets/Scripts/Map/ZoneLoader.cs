using System.Collections.Generic;
using UnityEngine;

namespace TaxiKin.Map
{
    /// <summary>
    /// Charge et décharge UNE zone de Kinshasa à la fois (fichier JSON dans Resources/Zones).
    /// Un seul chargement actif = faible mémoire et chargement rapide. Les points d'intérêt servent
    /// de départs et de destinations pour les missions.
    /// À placer sur un objet de la scène avec trois matériaux simples.
    /// </summary>
    public class ZoneLoader : MonoBehaviour
    {
        [SerializeField] private Material roadMaterial;
        [SerializeField] private Material buildingMaterial;
        [SerializeField] private Material groundMaterial;
        [SerializeField] private string autoLoadZoneId = "gare_centrale";
        [Tooltip("Collision sur les immeubles (maillage statique). À couper si le framerate chute.")]
        [SerializeField] private bool buildingColliders = true;

        public ZoneData Data { get; private set; }
        public bool IsLoaded => root != null;
        public string Attribution => Data != null ? Data.attribution : string.Empty;

        private GameObject root;
        private readonly List<Mesh> meshes = new List<Mesh>();
        private readonly List<Transform> pois = new List<Transform>();

        private void Start()
        {
            if (!string.IsNullOrEmpty(autoLoadZoneId)) Load(autoLoadZoneId);
        }

        public bool Load(string zoneId)
        {
            Unload();
            var asset = Resources.Load<TextAsset>("Zones/" + zoneId);
            if (asset == null) { Debug.LogError($"[ZoneLoader] Zone introuvable : Resources/Zones/{zoneId}.json"); return false; }
            Data = JsonUtility.FromJson<ZoneData>(asset.text);
            Resources.UnloadAsset(asset);   // le texte brut n'est plus nécessaire
            Build();
            return true;
        }

        public void Unload()
        {
            if (root != null) Destroy(root);
            foreach (var m in meshes) if (m != null) Destroy(m);
            meshes.Clear();
            pois.Clear();
            root = null;
            Data = null;
        }

        /// <summary>Premier point d'intérêt d'un type donné (ex. "station", "marche", "aeroport").</summary>
        public Transform FindPoi(string kind)
        {
            foreach (var p in pois) if (p != null && p.name.StartsWith("POI_" + kind)) return p;
            return null;
        }

        private void Build()
        {
            root = new GameObject("Zone_" + Data.id);
            root.transform.SetParent(transform, false);

            // Sol (un seul plan avec collision)
            var ground = GameObject.CreatePrimitive(PrimitiveType.Plane);
            ground.name = "Sol";
            ground.transform.SetParent(root.transform, false);
            float size = (Data.radius * 2f + 200f) / 10f;   // un Plane Unity fait 10 m
            ground.transform.localScale = new Vector3(size, 1f, size);
            if (groundMaterial != null) ground.GetComponent<Renderer>().sharedMaterial = groundMaterial;

            AddMesh("Routes", ZoneMeshBuilder.BuildRoads(Data.roads), roadMaterial, false);
            AddMesh("Immeubles", ZoneMeshBuilder.BuildBuildings(Data.buildings), buildingMaterial, buildingColliders);

            if (Data.pois != null)
                foreach (var p in Data.pois)
                {
                    var go = new GameObject($"POI_{p.kind}_{p.name}");
                    go.transform.SetParent(root.transform, false);
                    go.transform.localPosition = new Vector3(p.x, 0f, p.z);
                    pois.Add(go.transform);
                }
        }

        private void AddMesh(string name, Mesh mesh, Material mat, bool collider)
        {
            meshes.Add(mesh);
            var go = new GameObject(name, typeof(MeshFilter), typeof(MeshRenderer));
            go.transform.SetParent(root.transform, false);
            go.GetComponent<MeshFilter>().sharedMesh = mesh;
            var r = go.GetComponent<MeshRenderer>();
            r.sharedMaterial = mat;
            r.shadowCastingMode = UnityEngine.Rendering.ShadowCastingMode.Off;   // ombres coûteuses : à réactiver selon la qualité
            if (collider) go.AddComponent<MeshCollider>().sharedMesh = mesh;
        }
    }
}

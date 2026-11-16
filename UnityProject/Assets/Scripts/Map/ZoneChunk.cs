using UnityEngine;

namespace TaxiKin.Map
{
    /// <summary>
    /// Un bloc de 250 m de la zone. Permet de masquer les blocs lointains (moins de dessin) et de ne créer la collision
    /// des immeubles (coûteuse à calculer pour un maillage non convexe) que pour les blocs proches du joueur.
    /// </summary>
    public class ZoneChunk : MonoBehaviour
    {
        public Vector3 Center { get; private set; }
        public float Radius { get; private set; }

        private MeshRenderer[] renderers;
        private MeshFilter buildingsFilter;
        private MeshCollider meshCollider;
        private bool visible = true;

        public void Init(MeshRenderer[] meshRenderers, MeshFilter buildings, Vector3 worldCenter, float radius)
        {
            renderers = meshRenderers;
            buildingsFilter = buildings;
            Center = worldCenter;
            Radius = radius;
        }

        public bool HasBuildings => buildingsFilter != null;
        public bool HasCollider => meshCollider != null;

        public void SetVisible(bool on)
        {
            if (visible == on) return;
            visible = on;
            foreach (var r in renderers) r.enabled = on;
        }

        /// <summary>Crée (ou réactive) la collision des immeubles. La création coûte quelques millisecondes : à étaler.</summary>
        public void SetCollider(bool on)
        {
            if (buildingsFilter == null) return;
            if (on && meshCollider == null)
            {
                meshCollider = buildingsFilter.gameObject.AddComponent<MeshCollider>();
                meshCollider.sharedMesh = buildingsFilter.sharedMesh;      // c'est ici que le maillage est « cuit » (quelques ms)
            }
            if (meshCollider != null) meshCollider.enabled = on;
        }
    }
}

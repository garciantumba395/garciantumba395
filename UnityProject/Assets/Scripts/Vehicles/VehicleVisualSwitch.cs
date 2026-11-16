using UnityEngine;

namespace TaxiKin.Vehicles
{
    /// <summary>
    /// Permet de basculer un véhicule entre l'ancien modèle provisoire (formes simples) et le nouveau modèle 3D.
    /// Le système de conduite (racine, collider, Rigidbody, contrôleur) n'est jamais touché : seul le visuel change,
    /// ainsi que les rendus que la personnalisation repeint.
    /// Dans l'Inspector : menu « ⋮ » du composant → « Utiliser le modèle 3D » / « Revenir au modèle provisoire ».
    /// </summary>
    public class VehicleVisualSwitch : MonoBehaviour
    {
        [SerializeField] private VehicleCustomizer customizer;
        [SerializeField] private bool useNewModel;

        [Header("Modèle provisoire")]
        [SerializeField] private GameObject provisional;
        [SerializeField] private Renderer provisionalBody;
        [SerializeField] private Renderer provisionalRoof;
        [SerializeField] private Renderer[] provisionalRims;
        [SerializeField] private Vector3 provisionalSize = new Vector3(1.8f, 1.2f, 4.2f);

        [Header("Nouveau modèle 3D")]
        [SerializeField] private GameObject newModel;
        [SerializeField] private Renderer newBody;                 // uniquement la peinture : ni vitres, ni pneus, ni phares
        [SerializeField] private Renderer[] newRims;
        [SerializeField] private Vector3 newSize = new Vector3(1.9f, 1.2f, 3.7f);

        [Header("Éléments communs (plaque, autocollants)")]
        [SerializeField] private Transform plate;
        [SerializeField] private Transform[] stickerHolders;

        public bool UsesNewModel => useNewModel;

        [ContextMenu("Utiliser le modèle 3D")]
        public void UseNewModel() => Apply(true);

        [ContextMenu("Revenir au modèle provisoire")]
        public void UseProvisionalModel() => Apply(false);

        public void Apply(bool newOne)
        {
            useNewModel = newOne && newModel != null;
            if (provisional != null) provisional.SetActive(!useNewModel);
            if (newModel != null) newModel.SetActive(useNewModel);
            Place(useNewModel ? newSize : provisionalSize);
            if (customizer != null)
            {
                if (useNewModel) customizer.UseVisuals(newBody, null, newRims);
                else customizer.UseVisuals(provisionalBody, provisionalRoof, provisionalRims);
            }
        }

        /// <summary>Replace la plaque et les autocollants sur les flancs / l'arrière du modèle affiché.</summary>
        private void Place(Vector3 size)
        {
            if (plate != null)
            {
                var p = plate.localPosition;
                plate.localPosition = new Vector3(p.x, p.y, -(size.z * 0.5f + 0.012f));
            }
            if (stickerHolders == null) return;
            foreach (var holder in stickerHolders)
            {
                if (holder == null) continue;
                foreach (Transform quad in holder)
                {
                    var p = quad.localPosition;
                    quad.localPosition = new Vector3(Mathf.Sign(p.x) * (size.x * 0.5f + 0.012f), p.y, p.z);
                }
            }
        }
    }
}

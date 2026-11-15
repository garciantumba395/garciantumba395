using UnityEngine;

namespace TaxiKin.Vehicles
{
    /// <summary>
    /// Applique la personnalisation visuelle au véhicule : carrosserie, toit, jantes, autocollants, plaque.
    /// Étape 1 : couleurs uniquement ; le reste sera ajouté à l'Étape 4.
    /// Utilise un MaterialPropertyBlock pour ne pas dupliquer les matériaux (économie mémoire).
    /// </summary>
    public class VehicleCustomizer : MonoBehaviour
    {
        [SerializeField] private Renderer bodyRenderer;
        [SerializeField] private Renderer roofRenderer;

        private static readonly int BaseColorId = Shader.PropertyToID("_BaseColor"); // URP
        private static readonly int ColorId = Shader.PropertyToID("_Color");         // Built-in
        private MaterialPropertyBlock block;

        private void Awake()
        {
            // Jaune Taxi Kin par défaut
            SetBodyColor(VehicleColorManager.DefaultTaxiColor);
        }

        public void SetBodyColor(Color color) => Tint(bodyRenderer, color);
        public void SetRoofColor(Color color) => Tint(roofRenderer, color);

        private void Tint(Renderer target, Color color)
        {
            if (target == null) return;
            block ??= new MaterialPropertyBlock();
            target.GetPropertyBlock(block);
            block.SetColor(BaseColorId, color);
            block.SetColor(ColorId, color);
            target.SetPropertyBlock(block);
        }
    }
}

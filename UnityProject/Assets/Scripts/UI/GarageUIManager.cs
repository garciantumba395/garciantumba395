using UnityEngine;
using TaxiKin.Vehicles;

namespace TaxiKin.UI
{
    /// <summary>
    /// Interface du garage : boutons suivant/précédent, couleur, réparation, carburant…
    /// Les boutons UI appelleront ces méthodes (OnClick). Détails à l'Étape 5.
    /// </summary>
    public class GarageUIManager : MonoBehaviour
    {
        [SerializeField] private GarageManager garage;
        [SerializeField] private VehicleCustomizer customizer;
        [SerializeField] private VehicleColorManager colors;

        public void OnNextVehicle() => garage?.Next();
        public void OnPreviousVehicle() => garage?.Previous();

        /// <summary>Applique une couleur de la palette à la carrosserie.</summary>
        public void OnBodyColorSelected(int paletteIndex)
        {
            if (customizer == null || colors == null) return;
            customizer.SetBodyColor(colors.GetColor(paletteIndex));
        }
    }
}

using UnityEngine;
using TaxiKin.Vehicles;

namespace TaxiKin.Player
{
    /// <summary>
    /// Relie le joueur (entrées) au véhicule actif.
    /// Étape 1 : simple relais. Étape 2 : branché sur les commandes tactiles (volant, pédales, flèches, inclinaison).
    /// </summary>
    public class PlayerController : MonoBehaviour
    {
        [SerializeField] private VehicleController vehicle;

        public VehicleController Vehicle => vehicle;

        public void SetVehicle(VehicleController newVehicle) => vehicle = newVehicle;

        /// <summary>Point d'entrée unique pour toutes les méthodes de contrôle.</summary>
        public void Drive(float steer, float throttle, float brake, bool handBrake)
        {
            if (vehicle != null) vehicle.SetInputs(steer, throttle, brake, handBrake);
        }
    }
}

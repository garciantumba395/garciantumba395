using System;
using UnityEngine;
using TaxiKin.Vehicles;

namespace TaxiKin.Player
{
    /// <summary>
    /// Relie le joueur au véhicule actif. Un seul véhicule est actif à la fois
    /// (les autres sont désactivés : économie de CPU et de mémoire).
    /// </summary>
    public class PlayerController : MonoBehaviour
    {
        [SerializeField] private VehicleController vehicle;
        [SerializeField] private VehicleController[] availableVehicles;

        public VehicleController Vehicle => vehicle;

        /// <summary>Déclenché quand le véhicule actif change (caméra, boutons et HUD s'y abonnent).</summary>
        public event Action<VehicleController> OnVehicleChanged;

        private void Start()
        {
            // Au démarrage, seul le véhicule choisi reste actif
            if (availableVehicles != null)
                foreach (var v in availableVehicles) if (v != null) v.gameObject.SetActive(v == vehicle);
            if (vehicle != null) OnVehicleChanged?.Invoke(vehicle);
        }

        public void SetVehicle(VehicleController newVehicle)
        {
            vehicle = newVehicle;
            OnVehicleChanged?.Invoke(vehicle);
        }

        /// <summary>Change de véhicule en le posant là où se trouvait le précédent (test et garage).</summary>
        public void SelectVehicle(int index)
        {
            if (availableVehicles == null || index < 0 || index >= availableVehicles.Length) return;
            var next = availableVehicles[index];
            if (next == null || next == vehicle) return;

            if (vehicle != null)
            {
                next.transform.SetPositionAndRotation(vehicle.transform.position + Vector3.up * 0.3f, vehicle.transform.rotation);
                vehicle.gameObject.SetActive(false);
            }
            next.gameObject.SetActive(true);
            SetVehicle(next);
        }

        /// <summary>Point d'entrée unique pour toutes les méthodes de contrôle.</summary>
        public void Drive(float steer, float throttle, float brake, bool handBrake)
        {
            if (vehicle != null) vehicle.SetInputs(steer, throttle, brake, handBrake);
        }
    }
}

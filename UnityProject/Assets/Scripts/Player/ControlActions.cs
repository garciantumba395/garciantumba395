using UnityEngine;
using TaxiKin.Missions;
using TaxiKin.Vehicles;

namespace TaxiKin.Player
{
    /// <summary>
    /// Actions déclenchées par les boutons (appelées via les OnClick de l'interface).
    /// Passagers : 1 par appui pour le Wewa/taxi, 5 par appui pour le bus (placeholder avant l'Étape 3).
    /// </summary>
    public class ControlActions : MonoBehaviour
    {
        [SerializeField] private PlayerController player;
        [SerializeField] private PassengerManager passengers;
        [SerializeField] private CameraRig cameraRig;

        public bool StopRequested { get; private set; }

        private VehicleController V => player != null ? player.Vehicle : null;

        private void OnEnable() { if (player != null) player.OnVehicleChanged += OnVehicleChanged; }
        private void OnDisable() { if (player != null) player.OnVehicleChanged -= OnVehicleChanged; }

        private void OnVehicleChanged(VehicleController v)
        {
            StopRequested = false;
            if (passengers != null) passengers.SetCapacity(v.Stats.passengerCapacity);
        }

        public void Horn() => V?.Honk();
        public void ToggleHeadlights() => V?.ToggleHeadlights();
        public void ToggleWipers() => V?.ToggleWipers();
        public void NextCamera() => cameraRig?.Next();

        /// <summary>0 = D, 1 = N, 2 = R.</summary>
        public void SetGear(int gear)
        {
            if (V != null) V.Gear = (GearPosition)Mathf.Clamp(gear, 0, 2);
        }

        public void PickupPassengers()
        {
            if (V == null || passengers == null) return;
            passengers.Pickup(V.Kind == VehicleType.Bus207 ? 5 : 1);
        }

        public void DropPassengers() => passengers?.DropAll();

        /// <summary>Portes du bus : uniquement à l'arrêt complet.</summary>
        public void ToggleDoors()
        {
            if (V is Bus207Controller bus && bus.SpeedKmh < 2f) bus.SetDoors(!bus.DoorsOpen);
        }

        public void RequestStop() => StopRequested = !StopRequested;

        /// <summary>Passe au mode de contrôle suivant (volant → flèches → inclinaison). Bouton de test avant le menu Paramètres.</summary>
        public void CycleControlMode() => ControlSettings.Mode = (ControlMode)(((int)ControlSettings.Mode + 1) % 3);
    }
}

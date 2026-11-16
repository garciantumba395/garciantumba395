using UnityEngine;
using TaxiKin.Core;
using TaxiKin.Missions;
using TaxiKin.Player;
using TaxiKin.Vehicles;

namespace TaxiKin.Audio
{
    /// <summary>
    /// Vibrations (réglage « Vibrations ») : gros chocs et fin de mission.
    /// Handheld.Vibrate a une durée fixe ; on espace les vibrations d'au moins 1,5 s pour ne pas fatiguer la batterie.
    /// </summary>
    public class HapticsManager : MonoBehaviour
    {
        [SerializeField] private PlayerController player;
        [SerializeField] private MissionManager missions;
        [SerializeField] private float minImpact = 8f;        // m/s : seuls les gros chocs vibrent

        private VehicleController watched;
        private float lastVibration;

        private void OnEnable()
        {
            if (player != null) player.OnVehicleChanged += OnVehicleChanged;
            if (missions != null) missions.OnMissionEnded += OnMissionEnded;
        }

        private void OnDisable()
        {
            if (player != null) player.OnVehicleChanged -= OnVehicleChanged;
            if (missions != null) missions.OnMissionEnded -= OnMissionEnded;
            if (watched != null) watched.OnImpact -= OnImpact;
        }

        private void OnVehicleChanged(VehicleController v)
        {
            if (watched != null) watched.OnImpact -= OnImpact;
            watched = v;
            if (watched != null) watched.OnImpact += OnImpact;
        }

        private void OnImpact(float impact) { if (impact >= minImpact) Vibrate(); }
        private void OnMissionEnded(MissionResult r) { if (r.success) Vibrate(); }

        public void Vibrate()
        {
            if (!GameSettings.Vibration || Time.unscaledTime - lastVibration < 1.5f) return;
            lastVibration = Time.unscaledTime;
#if UNITY_ANDROID || UNITY_IOS
            Handheld.Vibrate();
#endif
        }
    }
}

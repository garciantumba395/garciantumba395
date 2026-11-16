using UnityEngine;
using TaxiKin.Vehicles;

namespace TaxiKin.Missions
{
    /// <summary>
    /// Place de stationnement marquée (BoxCollider en « Is Trigger »). Valide la mission quand le véhicule
    /// est dedans, à l'arrêt, et bien orienté pendant une seconde. Les collisions sont comptées par MissionManager.
    /// </summary>
    [RequireComponent(typeof(BoxCollider))]
    public class ParkingSpot : MonoBehaviour
    {
        [SerializeField] private MissionManager missions;
        [SerializeField] private float maxAngle = 15f;
        [SerializeField] private float maxSpeedKmh = 2f;
        [SerializeField] private float holdSeconds = 1f;

        private float inside;

        private void Reset() => GetComponent<BoxCollider>().isTrigger = true;

        private void OnTriggerStay(Collider other)
        {
            var v = other.GetComponentInParent<VehicleController>();
            if (v == null || missions == null) return;

            // Parallèle à la place (dans les deux sens)
            float angle = Vector3.Angle(v.transform.forward, transform.forward);
            bool aligned = angle <= maxAngle || angle >= 180f - maxAngle;

            if (aligned && v.SpeedKmh <= maxSpeedKmh) inside += Time.deltaTime; else inside = 0f;
            if (inside >= holdSeconds) missions.ReportParked();
        }

        private void OnTriggerExit(Collider other) => inside = 0f;
    }
}

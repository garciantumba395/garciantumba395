using UnityEngine;
using TaxiKin.Vehicles;

namespace TaxiKin.Player
{
    public enum CameraView { Behind, Cockpit, Hood, Rear }

    /// <summary>
    /// Une seule caméra, quatre points de vue : derrière, cockpit, capot, arrière.
    /// Les positions se calculent à partir de la taille du véhicule (Wewa, taxi et bus fonctionnent sans réglage).
    /// </summary>
    public class CameraRig : MonoBehaviour
    {
        [SerializeField] private Camera cam;
        [SerializeField] private PlayerController player;
        [SerializeField] private float followSharpness = 8f;

        public CameraView Current { get; private set; } = CameraView.Behind;

        private Transform target;
        private Vector3 size = new Vector3(1.8f, 1.2f, 4f);

        private void OnEnable() { if (player != null) player.OnVehicleChanged += OnVehicleChanged; }
        private void OnDisable() { if (player != null) player.OnVehicleChanged -= OnVehicleChanged; }

        private void OnVehicleChanged(VehicleController v)
        {
            target = v.transform;
            var r = v.GetComponentInChildren<Renderer>();
            if (r != null) size = Vector3.Scale(r.localBounds.size, r.transform.lossyScale);
            Place(true);
        }

        public void Next() => Set((CameraView)(((int)Current + 1) % 4));
        public void Set(CameraView view) { Current = view; Place(true); }

        private void LateUpdate() => Place(false);

        private void Place(bool snap)
        {
            if (target == null || cam == null) return;

            Vector3 pos;
            Quaternion rot;
            bool smooth = false;

            switch (Current)
            {
                case CameraView.Cockpit:
                    pos = target.TransformPoint(new Vector3(0f, size.y * 0.35f, size.z * 0.1f));
                    rot = target.rotation;
                    break;
                case CameraView.Hood:
                    pos = target.TransformPoint(new Vector3(0f, size.y * 0.5f + 0.3f, size.z * 0.5f - 0.2f));
                    rot = target.rotation;
                    break;
                case CameraView.Rear:   // Caméra de recul : au pare-chocs arrière, regarde vers l'arrière
                    pos = target.TransformPoint(new Vector3(0f, size.y * 0.5f + 0.5f, -size.z * 0.5f));
                    rot = target.rotation * Quaternion.Euler(10f, 180f, 0f);
                    break;
                default:                // Derrière le véhicule
                    pos = target.TransformPoint(new Vector3(0f, size.y * 0.5f + 2.5f, -(size.z * 0.5f + 6f)));
                    Vector3 look = target.position + target.forward * 3f + Vector3.up * (size.y * 0.3f);
                    rot = Quaternion.LookRotation(look - pos);
                    smooth = true;
                    break;
            }

            if (smooth && !snap)
            {
                float t = 1f - Mathf.Exp(-followSharpness * Time.deltaTime);
                cam.transform.SetPositionAndRotation(Vector3.Lerp(cam.transform.position, pos, t),
                                                     Quaternion.Slerp(cam.transform.rotation, rot, t));
            }
            else cam.transform.SetPositionAndRotation(pos, rot);
        }
    }
}

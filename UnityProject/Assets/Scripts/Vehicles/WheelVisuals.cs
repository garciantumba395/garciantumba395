using UnityEngine;

namespace TaxiKin.Vehicles
{
    /// <summary>
    /// Fait tourner les roues VISUELLES d'un véhicule (rotation selon la vitesse réelle, braquage des roues avant selon la direction).
    /// Purement visuel : aucun lien avec la physique ni avec les colliders, qui restent sur la racine du véhicule.
    /// La vitesse est lue sur le déplacement réel de la racine : fonctionne aussi pour un véhicule d'IA.
    /// </summary>
    public class WheelVisuals : MonoBehaviour
    {
        [Tooltip("Pivots des roues avant (braquage autour de l'axe vertical)")]
        [SerializeField] private Transform[] steerPivots;
        [Tooltip("Noeuds de rotation de TOUTES les roues (rotation autour de l'axe de l'essieu = X local)")]
        [SerializeField] private Transform[] spinNodes;
        [SerializeField] private float radius = 0.35f;         // rayon réel de la roue en mètres (après mise à l'échelle)
        [SerializeField] private float maxSteerAngle = 28f;    // degrés

        private Transform root;
        private VehicleController vehicle;
        private Vector3 lastPos;
        private float spinAngle, steerAngle, smoothSpeed;
        private bool hasLast;

        public void Configure(Transform[] steer, Transform[] spin, float wheelRadius)
        {
            steerPivots = steer; spinNodes = spin; radius = Mathf.Max(0.05f, wheelRadius);
        }

        private void Awake()
        {
            vehicle = GetComponentInParent<VehicleController>();
            root = vehicle != null ? vehicle.transform : transform;
        }

        private void OnEnable() => hasLast = false;      // pas de « saut » de vitesse après une réactivation

        private void LateUpdate()
        {
            float dt = Time.deltaTime;
            if (dt <= 0f || root == null) return;

            Vector3 pos = root.position;
            float speed = 0f;
            if (hasLast)
            {
                Vector3 d = pos - lastPos;
                if (d.sqrMagnitude < 25f)                        // plus de 5 m en une image = téléportation : on ignore
                    speed = Vector3.Dot(d / dt, root.forward);
            }
            lastPos = pos; hasLast = true;

            smoothSpeed = Mathf.Lerp(smoothSpeed, speed, 1f - Mathf.Exp(-20f * dt));
            spinAngle = Mathf.Repeat(spinAngle + smoothSpeed / radius * Mathf.Rad2Deg * dt, 360f);   // rotation positive = roule vers l'avant
            var spin = Quaternion.Euler(spinAngle, 0f, 0f);
            if (spinNodes != null) foreach (var s in spinNodes) if (s != null) s.localRotation = spin;

            float target = vehicle != null ? vehicle.Steer * maxSteerAngle : 0f;
            steerAngle = Mathf.Lerp(steerAngle, target, 1f - Mathf.Exp(-14f * dt));
            var steer = Quaternion.Euler(0f, steerAngle, 0f);
            if (steerPivots != null) foreach (var p in steerPivots) if (p != null) p.localRotation = steer;
        }
    }
}

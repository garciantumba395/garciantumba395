using UnityEngine;
using TaxiKin.Player;

namespace TaxiKin.Map
{
    /// <summary>
    /// Caméra de minimap vue du dessus. Optimisée mobile : texture 256x256 et rendu un frame sur N
    /// (la caméra est désactivée et rendue à la main).
    /// </summary>
    [RequireComponent(typeof(Camera))]
    public class MinimapCamera : MonoBehaviour
    {
        [SerializeField] private PlayerController player;
        [SerializeField] private float height = 60f;
        [SerializeField, Range(1, 10)] private int renderEveryNFrames = 3;
        [SerializeField] private bool rotateWithVehicle = true;

        private Camera cam;
        private int frame;

        private void Awake()
        {
            cam = GetComponent<Camera>();
            cam.enabled = false;   // Rendu manuel
        }

        private void LateUpdate()
        {
            if (player == null || player.Vehicle == null) return;
            Transform t = player.Vehicle.transform;
            transform.position = t.position + Vector3.up * height;
            transform.rotation = Quaternion.Euler(90f, rotateWithVehicle ? t.eulerAngles.y : 0f, 0f);
            if (++frame % renderEveryNFrames == 0) cam.Render();
        }
    }
}

using UnityEngine;
using UnityEngine.UI;

namespace TaxiKin.Vehicles
{
    /// <summary>
    /// Aperçu 3D du véhicule dans le garage : petite scène loin du monde filmée vers une texture (768x480).
    /// La caméra n'est active que lorsque le garage est ouvert (batterie).
    /// </summary>
    public class VehiclePreviewStage : MonoBehaviour
    {
        [SerializeField] private Camera previewCamera;
        [SerializeField] private Transform turntable;     // Plateau tournant qui porte le véhicule affiché
        [SerializeField] private RawImage target;

        private RenderTexture texture;

        private void Awake()
        {
            texture = new RenderTexture(768, 480, 16) { name = "VehiclePreviewRT" };
            previewCamera.targetTexture = texture;
            previewCamera.enabled = false;
            if (target != null) target.texture = texture;
        }

        private void OnDestroy() { if (texture != null) texture.Release(); }

        public void SetActive(bool on) { if (previewCamera != null) previewCamera.enabled = on; }
        public void Rotate(float degrees) { if (turntable != null) turntable.Rotate(0f, -degrees, 0f); }
        public void ResetRotation() { if (turntable != null) turntable.rotation = Quaternion.Euler(0f, 30f, 0f); }
    }
}

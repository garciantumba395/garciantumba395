using UnityEngine;
using UnityEngine.UI;

namespace TaxiKin.Player
{
    /// <summary>
    /// Aperçu 3D de l'avatar : une petite scène loin du monde, filmée par une caméra qui écrit dans une texture
    /// (512x768). La caméra n'est active que lorsque l'écran « Mon chauffeur » est ouvert (batterie).
    /// </summary>
    public class AvatarPreviewStage : MonoBehaviour
    {
        [SerializeField] private Camera previewCamera;
        [SerializeField] private Transform turntable;          // Plateau tournant qui porte l'avatar
        [SerializeField] private AvatarCustomizer avatar;
        [SerializeField] private RawImage target;               // Image de l'interface qui affiche l'aperçu

        public AvatarCustomizer Avatar => avatar;
        private RenderTexture texture;

        private void Awake()
        {
            texture = new RenderTexture(512, 768, 16) { name = "AvatarPreviewRT" };
            previewCamera.targetTexture = texture;
            previewCamera.enabled = false;
            if (target != null) target.texture = texture;
        }

        private void OnDestroy() { if (texture != null) texture.Release(); }

        public void SetActive(bool on) { if (previewCamera != null) previewCamera.enabled = on; }

        /// <summary>Fait tourner l'avatar (degrés).</summary>
        public void Rotate(float degrees) { if (turntable != null) turntable.Rotate(0f, -degrees, 0f); }
        public void ResetRotation() { if (turntable != null) turntable.rotation = Quaternion.identity; }
    }
}

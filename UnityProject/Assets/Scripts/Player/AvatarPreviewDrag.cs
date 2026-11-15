using UnityEngine;
using UnityEngine.EventSystems;

namespace TaxiKin.Player
{
    /// <summary>À placer sur l'image d'aperçu : un glissement du doigt fait tourner l'avatar.</summary>
    public class AvatarPreviewDrag : MonoBehaviour, IDragHandler
    {
        [SerializeField] private AvatarPreviewStage stage;
        [SerializeField] private float sensitivity = 0.5f;

        public void OnDrag(PointerEventData e) => stage.Rotate(e.delta.x * sensitivity);
    }
}

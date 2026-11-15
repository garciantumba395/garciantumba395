using UnityEngine;
using UnityEngine.EventSystems;

namespace TaxiKin.Vehicles
{
    /// <summary>À placer sur l'image d'aperçu du garage : un glissement du doigt fait tourner le véhicule.</summary>
    public class VehiclePreviewDrag : MonoBehaviour, IDragHandler
    {
        [SerializeField] private VehiclePreviewStage stage;
        [SerializeField] private float sensitivity = 0.5f;

        public void OnDrag(PointerEventData e) => stage.Rotate(e.delta.x * sensitivity);
    }
}

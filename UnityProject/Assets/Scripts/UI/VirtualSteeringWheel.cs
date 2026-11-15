using UnityEngine;
using UnityEngine.EventSystems;
using TaxiKin.Player;

namespace TaxiKin.UI
{
    /// <summary>
    /// Volant virtuel : on le tourne avec un doigt (angle du doigt autour du centre).
    /// Rotation maximale ±maxAngle ; revient au centre au relâchement. La sensibilité vient des réglages.
    /// </summary>
    public class VirtualSteeringWheel : MonoBehaviour, IPointerDownHandler, IDragHandler, IPointerUpHandler
    {
        [SerializeField] private float maxAngle = 135f;
        [SerializeField] private float returnSpeed = 360f;   // degrés/seconde

        private RectTransform rect;
        private float angle;          // angle visuel (positif = antihoraire)
        private float lastPointerAngle;
        private bool dragging;

        /// <summary>Valeur de direction : -1 (gauche) .. 1 (droite).</summary>
        public float Value => Mathf.Clamp(-angle / maxAngle, -1f, 1f);

        private void Awake() => rect = (RectTransform)transform;

        public void OnPointerDown(PointerEventData e)
        {
            dragging = true;
            lastPointerAngle = PointerAngle(e);
        }

        public void OnDrag(PointerEventData e)
        {
            float a = PointerAngle(e);
            float delta = Mathf.DeltaAngle(lastPointerAngle, a);
            lastPointerAngle = a;
            angle = Mathf.Clamp(angle + delta * ControlSettings.WheelSensitivity, -maxAngle, maxAngle);
        }

        public void OnPointerUp(PointerEventData e) => dragging = false;

        private void OnDisable() { dragging = false; angle = 0f; }

        private void Update()
        {
            if (!dragging) angle = Mathf.MoveTowards(angle, 0f, returnSpeed * Time.unscaledDeltaTime);
            rect.localEulerAngles = new Vector3(0f, 0f, angle);
        }

        private float PointerAngle(PointerEventData e)
        {
            // Le pivot du volant doit être au centre (0,5 ; 0,5) : localPosition = centre dans le repère du parent
            RectTransformUtility.ScreenPointToLocalPointInRectangle(rect.parent as RectTransform, e.position, e.pressEventCamera, out var local);
            Vector2 d = local - (Vector2)rect.localPosition;
            return Mathf.Atan2(d.y, d.x) * Mathf.Rad2Deg;
        }
    }
}

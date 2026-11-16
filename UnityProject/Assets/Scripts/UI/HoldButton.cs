using UnityEngine;
using UnityEngine.Events;
using UnityEngine.EventSystems;
using UnityEngine.UI;

namespace TaxiKin.UI
{
    /// <summary>
    /// Bouton tactile : « maintenu » (IsHeld, pour accélérateur/frein/flèches) et « appuyé » (onPressed, pour les actions).
    /// Multi-touch : chaque doigt est géré indépendamment par l'EventSystem.
    /// </summary>
    [RequireComponent(typeof(Image))]
    public class HoldButton : MonoBehaviour, IPointerDownHandler, IPointerUpHandler, IPointerExitHandler
    {
        public UnityEvent onPressed = new UnityEvent();

        [SerializeField] private Color pressedTint = new Color(0.75f, 0.75f, 0.75f, 1f);

        public bool IsHeld { get; private set; }

        private Image image;
        private Color baseColor;

        private void Awake()
        {
            image = GetComponent<Image>();
            baseColor = image.color;
        }

        private void OnDisable() => Release();   // Un bouton masqué ne reste jamais « coincé »

        public void OnPointerDown(PointerEventData eventData)
        {
            IsHeld = true;
            if (image != null) image.color = baseColor * pressedTint;
            onPressed.Invoke();
        }

        public void OnPointerUp(PointerEventData eventData) => Release();
        public void OnPointerExit(PointerEventData eventData) => Release();

        private void Release()
        {
            IsHeld = false;
            if (image != null) image.color = baseColor;
        }
    }
}

using UnityEngine;
using UnityEngine.UI;

namespace TaxiKin.UI
{
    /// <summary>Bandeau de message court en haut de l'écran (quêtes terminées, erreurs). Disparaît tout seul.</summary>
    public class ToastManager : MonoBehaviour
    {
        private static ToastManager instance;

        [SerializeField] private Text label;
        [SerializeField] private float duration = 3f;

        private float hideAt;

        private void Awake()
        {
            instance = this;
            if (label != null) label.gameObject.SetActive(false);
        }

        private void OnDestroy() { if (instance == this) instance = null; }

        public static void Show(string message) => instance?.Display(message);

        private void Display(string message)
        {
            if (label == null) return;
            label.text = message;
            label.gameObject.SetActive(true);
            hideAt = Time.unscaledTime + duration;
        }

        private void Update()
        {
            if (label != null && label.gameObject.activeSelf && Time.unscaledTime >= hideAt) label.gameObject.SetActive(false);
        }
    }
}

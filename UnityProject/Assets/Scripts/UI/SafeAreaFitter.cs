using UnityEngine;

namespace TaxiKin.UI
{
    /// <summary>
    /// Garde l'interface dans la « zone sûre » de l'écran (hors encoche, caméra et barre de navigation).
    /// À placer sur un RectTransform plein écran, parent de tout le HUD.
    /// </summary>
    [RequireComponent(typeof(RectTransform))]
    public class SafeAreaFitter : MonoBehaviour
    {
        private RectTransform rect;
        private Rect last;
        private Vector2Int lastScreen;

        private void Awake() { rect = (RectTransform)transform; Apply(); }

        private void Update()
        {
            // Comparaison très légère : réagit à la rotation de l'écran
            if (Screen.safeArea != last || Screen.width != lastScreen.x || Screen.height != lastScreen.y) Apply();
        }

        private void Apply()
        {
            last = Screen.safeArea;
            lastScreen = new Vector2Int(Screen.width, Screen.height);
            if (Screen.width <= 0 || Screen.height <= 0) return;
            Vector2 min = last.position, max = last.position + last.size;
            min.x /= Screen.width; min.y /= Screen.height;
            max.x /= Screen.width; max.y /= Screen.height;
            rect.anchorMin = min; rect.anchorMax = max;
            rect.offsetMin = rect.offsetMax = Vector2.zero;
        }
    }
}

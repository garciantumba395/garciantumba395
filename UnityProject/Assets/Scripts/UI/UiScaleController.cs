using UnityEngine;
using UnityEngine.UI;
using TaxiKin.Core;

namespace TaxiKin.UI
{
    /// <summary>
    /// Échelle de l'interface pour qu'elle reste lisible sur petits écrans : automatique selon la diagonale physique
    /// de l'écran, ou choisie dans les réglages (« Taille de l'interface »).
    /// Principe : on réduit la résolution de référence du CanvasScaler, donc tout paraît plus grand.
    /// </summary>
    [RequireComponent(typeof(CanvasScaler))]
    public class UiScaleController : MonoBehaviour
    {
        private static readonly Vector2 BaseReference = new Vector2(1920f, 1080f);
        private CanvasScaler scaler;

        private void Awake() => scaler = GetComponent<CanvasScaler>();
        private void OnEnable() => GameSettings.Changed += Apply;
        private void OnDisable() => GameSettings.Changed -= Apply;
        private void Start() => Apply();

        /// <summary>Échelle effective (choix du joueur, sinon automatique).</summary>
        public static float EffectiveScale()
        {
            float chosen = GameSettings.UiScale;
            return chosen > 0f ? chosen : AutoScale();
        }

        /// <summary>Plus l'écran est petit, plus l'interface est agrandie (diagonale en pouces).</summary>
        public static float AutoScale()
        {
            if (Screen.dpi <= 0f) return 1f;
            float diagonal = Mathf.Sqrt(Screen.width * Screen.width + Screen.height * Screen.height) / Screen.dpi;
            if (diagonal < 5.2f) return 1.25f;
            if (diagonal < 5.8f) return 1.15f;
            if (diagonal < 6.4f) return 1.08f;
            return 1f;
        }

        private void Apply()
        {
            if (scaler == null) return;
            scaler.referenceResolution = BaseReference / EffectiveScale();
        }
    }
}

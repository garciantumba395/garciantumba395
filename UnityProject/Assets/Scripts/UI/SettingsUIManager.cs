using UnityEngine;
using UnityEngine.UI;
using TaxiKin.Core;
using TaxiKin.Player;
using TaxiKin.SaveSystem;

namespace TaxiKin.UI
{
    /// <summary>
    /// Réglages : mode de contrôle, taille des boutons, sensibilité du volant, inversion, langue, et
    /// « Réinitialiser ma progression » avec une confirmation claire en deux étapes.
    /// (L'Étape 7 y ajoutera qualité graphique, son, musique, vibrations, ombres.)
    /// Le jeu est mis en pause pendant que les réglages sont ouverts.
    /// </summary>
    public class SettingsUIManager : MonoBehaviour
    {
        [SerializeField] private GameObject panel;
        [SerializeField] private Image[] modeButtons;           // Volant, Flèches, Inclinaison
        [SerializeField] private Image[] languageButtons;       // Français, Lingala
        [SerializeField] private Slider scaleSlider, sensitivitySlider;
        [SerializeField] private Text scaleValue, sensitivityValue, invertLabel;

        [Header("Confirmation de réinitialisation")]
        [SerializeField] private GameObject confirmPanel;
        [SerializeField] private Button confirmButton;
        [SerializeField] private Text confirmLabel;
        [SerializeField] private float confirmDelay = 3f;       // le bouton rouge reste bloqué quelques secondes

        private static readonly Color Normal = new Color(0.2f, 0.2f, 0.24f, 0.95f);
        private static readonly Color Selected = new Color(0.15f, 0.55f, 0.25f, 0.95f);

        private GameState stateBefore = GameState.Playing;
        private float confirmReadyAt;

        private void OnEnable() => SaveManager.ProgressReset += AfterReset;
        private void OnDisable() => SaveManager.ProgressReset -= AfterReset;

        // ---------- Ouverture / fermeture ----------

        public void Open()
        {
            var gm = GameManager.Instance;
            if (gm != null) { stateBefore = gm.State; gm.SetState(GameState.Paused); }
            panel.SetActive(true);
            confirmPanel.SetActive(false);
            RefreshAll();
        }

        public void Close()
        {
            panel.SetActive(false);
            confirmPanel.SetActive(false);
            GameManager.Instance?.SetState(stateBefore);   // on restaure exactement l'état d'avant (ex. briefing de mission en pause)
            SaveManager.Instance?.Save();     // les réglages sont enregistrés à la fermeture
        }

        // ---------- Commandes ----------

        public void OnMode(int mode) { ControlSettings.Mode = (ControlMode)Mathf.Clamp(mode, 0, 2); RefreshAll(); }
        public void OnScale(float value) { ControlSettings.ButtonScale = value; scaleValue.text = $"{ControlSettings.ButtonScale:0.00}x"; }
        public void OnSensitivity(float value) { ControlSettings.WheelSensitivity = value; sensitivityValue.text = $"{ControlSettings.WheelSensitivity:0.00}x"; }
        public void OnInvert() { ControlSettings.InvertSteering = !ControlSettings.InvertSteering; RefreshAll(); }

        // ---------- Langue ----------

        public void OnLanguage(int language) { Loc.Current = (Language)Mathf.Clamp(language, 0, 1); RefreshAll(); }

        // ---------- Réinitialisation (deux étapes) ----------

        public void OnAskReset()
        {
            confirmReadyAt = Time.unscaledTime + confirmDelay;
            confirmPanel.SetActive(true);
        }

        public void OnCancelReset() => confirmPanel.SetActive(false);

        public void OnConfirmReset()
        {
            if (Time.unscaledTime < confirmReadyAt) return;     // sécurité : jamais avant la fin du délai
            if (SaveManager.Instance != null) SaveManager.Instance.ResetProgress();
        }

        /// <summary>Après la remise à zéro : on rafraîchit les textes et les commandes, puis on referme.</summary>
        private void AfterReset()
        {
            ControlSettings.NotifyChanged();
            Loc.NotifyChanged();
            Close();
        }

        private void Update()
        {
            if (confirmPanel == null || !confirmPanel.activeSelf) return;
            float remaining = confirmReadyAt - Time.unscaledTime;
            bool ready = remaining <= 0f;
            confirmButton.interactable = ready;
            confirmLabel.text = ready ? "Oui, tout effacer" : $"Oui, tout effacer ({Mathf.CeilToInt(remaining)})";
        }

        // ---------- Affichage ----------

        private void RefreshAll()
        {
            for (int i = 0; i < modeButtons.Length; i++) modeButtons[i].color = (int)ControlSettings.Mode == i ? Selected : Normal;
            for (int i = 0; i < languageButtons.Length; i++) languageButtons[i].color = (int)Loc.Current == i ? Selected : Normal;

            // SetValueWithoutNotify : on met le curseur à jour sans déclencher une sauvegarde
            scaleSlider.SetValueWithoutNotify(ControlSettings.ButtonScale);
            sensitivitySlider.SetValueWithoutNotify(ControlSettings.WheelSensitivity);
            scaleValue.text = $"{ControlSettings.ButtonScale:0.00}x";
            sensitivityValue.text = $"{ControlSettings.WheelSensitivity:0.00}x";
            invertLabel.text = "Inversion des commandes : " + (ControlSettings.InvertSteering ? "Oui" : "Non");
        }
    }
}

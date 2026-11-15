using System;
using UnityEngine;
using TaxiKin.SaveSystem;

namespace TaxiKin.Core
{
    public enum QualityLevel { Low = 0, Medium = 1, High = 2 }
    public enum ShadowSetting { Off = 0, Low = 1, High = 2 }

    /// <summary>
    /// Réglages graphiques, audio et d'interface, stockés dans la sauvegarde (avec ControlSettings pour les commandes).
    /// Sans SaveManager, les valeurs par défaut s'appliquent et les modifications sont ignorées.
    /// </summary>
    public static class GameSettings
    {
        /// <summary>Un réglage a changé (son, vibrations, interface…).</summary>
        public static event Action Changed;
        /// <summary>Un réglage graphique a changé : QualityManager doit réappliquer les préréglages.</summary>
        public static event Action QualityChanged;

        private static SettingsData Data => SaveManager.Instance != null ? SaveManager.Instance.Data.settings : null;

        /// <summary>-1 = à détecter ; 0 basse, 1 moyenne, 2 haute.</summary>
        public static int Quality
        {
            get { var d = Data; return d == null ? -1 : Mathf.Clamp(d.quality, -1, 2); }
            set { var d = Data; if (d == null) return; d.quality = Mathf.Clamp(value, -1, 2); Touch(true); }
        }

        /// <summary>Si vrai, le jeu peut baisser la qualité tout seul quand l'appareil n'arrive pas à suivre.</summary>
        public static bool QualityAuto
        {
            get { var d = Data; return d == null || d.qualityAuto; }
            set { var d = Data; if (d == null) return; d.qualityAuto = value; Touch(true); }
        }

        /// <summary>-1 = selon la qualité ; 0 aucune, 1 basses, 2 hautes.</summary>
        public static int Shadows
        {
            get { var d = Data; return d == null ? -1 : Mathf.Clamp(d.shadows, -1, 2); }
            set { var d = Data; if (d == null) return; d.shadows = Mathf.Clamp(value, -1, 2); Touch(true); }
        }

        public static float SoundVolume
        {
            get { var d = Data; return d == null ? 1f : Mathf.Clamp01(d.soundVolume); }
            set { var d = Data; if (d == null) return; d.soundVolume = Mathf.Clamp01(value); Touch(false); }
        }

        public static float MusicVolume
        {
            get { var d = Data; return d == null ? 0.7f : Mathf.Clamp01(d.musicVolume); }
            set { var d = Data; if (d == null) return; d.musicVolume = Mathf.Clamp01(value); Touch(false); }
        }

        public static bool Vibration
        {
            get { var d = Data; return d == null || d.vibration; }
            set { var d = Data; if (d == null) return; d.vibration = value; Touch(false); }
        }

        /// <summary>Échelle de l'interface (0,85 à 1,4). 0 = automatique selon la taille physique de l'écran.</summary>
        public static float UiScale
        {
            get { var d = Data; return d == null || d.uiScale <= 0f ? 0f : Mathf.Clamp(d.uiScale, 0.85f, 1.4f); }
            set { var d = Data; if (d == null) return; d.uiScale = value <= 0f ? 0f : Mathf.Clamp(value, 0.85f, 1.4f); Touch(false); }
        }

        private static void Touch(bool graphics)
        {
            SaveManager.Instance.SaveSoon();
            if (graphics) QualityChanged?.Invoke();
            Changed?.Invoke();
        }

        /// <summary>À appeler après un chargement ou une réinitialisation pour tout réappliquer.</summary>
        public static void NotifyChanged()
        {
            QualityChanged?.Invoke();
            Changed?.Invoke();
        }
    }
}

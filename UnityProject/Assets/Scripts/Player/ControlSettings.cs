using System;
using UnityEngine;
using TaxiKin.SaveSystem;

namespace TaxiKin.Player
{
    public enum ControlMode { Wheel = 0, Arrows = 1, Tilt = 2 }

    /// <summary>
    /// Réglages des commandes (mode, taille des boutons, sensibilité, inversion), stockés dans la sauvegarde.
    /// Sans SaveManager (ex. test isolé) les valeurs par défaut s'appliquent et les modifications sont ignorées.
    /// </summary>
    public static class ControlSettings
    {
        public static event Action Changed;

        private static SettingsData Data => SaveManager.Instance != null ? SaveManager.Instance.Data.settings : null;

        public static ControlMode Mode
        {
            get { var d = Data; return d == null ? ControlMode.Wheel : (ControlMode)Mathf.Clamp(d.controlMode, 0, 2); }
            set { var d = Data; if (d == null) return; d.controlMode = (int)value; Touch(); }
        }

        /// <summary>Taille des boutons : 0,7 à 1,5.</summary>
        public static float ButtonScale
        {
            get { var d = Data; return d == null ? 1f : Mathf.Clamp(d.buttonScale, 0.7f, 1.5f); }
            set { var d = Data; if (d == null) return; d.buttonScale = Mathf.Clamp(value, 0.7f, 1.5f); Touch(); }
        }

        /// <summary>Sensibilité du volant : 0,5 à 2.</summary>
        public static float WheelSensitivity
        {
            get { var d = Data; return d == null ? 1f : Mathf.Clamp(d.wheelSensitivity, 0.5f, 2f); }
            set { var d = Data; if (d == null) return; d.wheelSensitivity = Mathf.Clamp(value, 0.5f, 2f); Touch(); }
        }

        /// <summary>Inverse la direction (gauche/droite) pour les trois modes.</summary>
        public static bool InvertSteering
        {
            get { var d = Data; return d != null && d.invertSteering; }
            set { var d = Data; if (d == null) return; d.invertSteering = value; Touch(); }
        }

        private static void Touch()
        {
            SaveManager.Instance.SaveSoon();   // sauvegarde groupée : un curseur qu'on glisse n'écrit pas 60 fois par seconde
            Changed?.Invoke();
        }

        /// <summary>À appeler après un chargement ou une réinitialisation pour rafraîchir l'interface.</summary>
        public static void NotifyChanged() => Changed?.Invoke();
    }
}

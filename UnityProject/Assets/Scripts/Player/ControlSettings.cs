using System;
using UnityEngine;

namespace TaxiKin.Player
{
    public enum ControlMode { Wheel = 0, Arrows = 1, Tilt = 2 }

    /// <summary>
    /// Réglages des commandes (mode, taille des boutons, sensibilité, inversion).
    /// Valeurs en cache + PlayerPrefs ; l'Étape 6 les rattachera à SaveManager.
    /// </summary>
    public static class ControlSettings
    {
        private const string KMode = "ctl_mode", KScale = "ctl_scale", KSens = "ctl_sens", KInvert = "ctl_invert";

        private static bool loaded;
        private static ControlMode mode = ControlMode.Wheel;   // Volant virtuel par défaut
        private static float buttonScale = 1f;
        private static float wheelSensitivity = 1f;
        private static bool invertSteering;

        public static event Action Changed;

        private static void EnsureLoaded()
        {
            if (loaded) return;
            loaded = true;
            mode = (ControlMode)Mathf.Clamp(PlayerPrefs.GetInt(KMode, 0), 0, 2);
            buttonScale = Mathf.Clamp(PlayerPrefs.GetFloat(KScale, 1f), 0.7f, 1.5f);
            wheelSensitivity = Mathf.Clamp(PlayerPrefs.GetFloat(KSens, 1f), 0.5f, 2f);
            invertSteering = PlayerPrefs.GetInt(KInvert, 0) == 1;
        }

        public static ControlMode Mode
        {
            get { EnsureLoaded(); return mode; }
            set { EnsureLoaded(); mode = value; PlayerPrefs.SetInt(KMode, (int)value); Changed?.Invoke(); }
        }

        /// <summary>Taille des boutons : 0,7 à 1,5.</summary>
        public static float ButtonScale
        {
            get { EnsureLoaded(); return buttonScale; }
            set { EnsureLoaded(); buttonScale = Mathf.Clamp(value, 0.7f, 1.5f); PlayerPrefs.SetFloat(KScale, buttonScale); Changed?.Invoke(); }
        }

        /// <summary>Sensibilité du volant : 0,5 à 2.</summary>
        public static float WheelSensitivity
        {
            get { EnsureLoaded(); return wheelSensitivity; }
            set { EnsureLoaded(); wheelSensitivity = Mathf.Clamp(value, 0.5f, 2f); PlayerPrefs.SetFloat(KSens, wheelSensitivity); Changed?.Invoke(); }
        }

        /// <summary>Inverse la direction (gauche/droite) pour les trois modes.</summary>
        public static bool InvertSteering
        {
            get { EnsureLoaded(); return invertSteering; }
            set { EnsureLoaded(); invertSteering = value; PlayerPrefs.SetInt(KInvert, value ? 1 : 0); Changed?.Invoke(); }
        }
    }
}

using System;
using UnityEngine;

namespace TaxiKin.Core
{
    /// <summary>États globaux du jeu.</summary>
    public enum GameState { Boot, MainMenu, CharacterCreation, Garage, Playing, Paused, MissionEnd }

    /// <summary>
    /// Chef d'orchestre du jeu (singleton).
    /// À placer sur l'objet RACINE "Managers" de la scène (DontDestroyOnLoad ne fonctionne que sur un objet racine).
    /// Applique aussi les réglages mobiles de base (FPS cible, écran toujours allumé).
    /// </summary>
    [DisallowMultipleComponent]
    public class GameManager : MonoBehaviour
    {
        public static GameManager Instance { get; private set; }

        public GameState State { get; private set; } = GameState.Boot;

        /// <summary>Déclenché à chaque changement d'état.</summary>
        public event Action<GameState> OnStateChanged;

        private void Awake()
        {
            if (Instance != null && Instance != this) { Destroy(gameObject); return; }
            Instance = this;
            DontDestroyOnLoad(gameObject);
            ApplyMobileSettings();
        }

        private void Start() => SetState(GameState.MainMenu);

        /// <summary>Change l'état du jeu et gère la pause.</summary>
        public void SetState(GameState newState)
        {
            if (State == newState) return;
            State = newState;
            // L'écran ne reste allumé qu'en conduite (économie de batterie dans les menus)
            // (MainMenu inclus tant que la conduite libre sert de menu : à retirer quand un vrai menu existera)
            bool awake = newState == GameState.Playing || newState == GameState.MainMenu;
            Screen.sleepTimeout = awake ? SleepTimeout.NeverSleep : SleepTimeout.SystemSetting;
            Time.timeScale = (newState == GameState.Paused || newState == GameState.MissionEnd || newState == GameState.CharacterCreation) ? 0f : 1f;
            OnStateChanged?.Invoke(newState);
        }

        private void ApplyMobileSettings()
        {
            // La cadence (30/60 FPS) est pilotée par QualityManager
            QualitySettings.vSyncCount = 0;
            Screen.sleepTimeout = SleepTimeout.SystemSetting;
        }
    }
}

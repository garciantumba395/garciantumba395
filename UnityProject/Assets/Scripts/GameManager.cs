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

        [Header("Performance mobile")]
        [Tooltip("30 pour économiser la batterie, 60 si l'appareil le permet.")]
        [SerializeField] private int targetFrameRate = 60;

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
            Time.timeScale = newState == GameState.Paused ? 0f : 1f;
            OnStateChanged?.Invoke(newState);
        }

        private void ApplyMobileSettings()
        {
            QualitySettings.vSyncCount = 0;              // On pilote le FPS nous-mêmes
            Application.targetFrameRate = targetFrameRate;
            Screen.sleepTimeout = SleepTimeout.NeverSleep; // L'écran reste allumé en conduite
        }
    }
}

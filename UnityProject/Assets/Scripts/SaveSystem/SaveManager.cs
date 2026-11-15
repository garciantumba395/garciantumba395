using System;
using System.Collections.Generic;
using System.IO;
using UnityEngine;
using TaxiKin.Player;
using TaxiKin.Vehicles;

namespace TaxiKin.SaveSystem
{
    /// <summary>Réglages du joueur (commandes, langue). L'Étape 7 y ajoutera graphismes, son, vibrations.</summary>
    [Serializable]
    public class SettingsData
    {
        public int controlMode;                 // 0 volant, 1 flèches, 2 inclinaison
        public float buttonScale = 1f;
        public float wheelSensitivity = 1f;
        public bool invertSteering;
        public int language;                    // 0 français, 1 lingala
        public int quality = -1;                // -1 = à détecter au premier lancement, 0 basse, 1 moyenne, 2 haute
        public bool qualityAuto = true;         // autorise la baisse automatique de qualité si le téléphone n'arrive pas à suivre
        public int shadows = -1;                // -1 = selon la qualité, 0 aucune, 1 basses, 2 hautes
        public float soundVolume = 1f;
        public float musicVolume = 0.7f;
        public bool vibration = true;
        public float uiScale;                   // 0 = automatique selon la taille de l'écran
    }

    /// <summary>Contenu de la sauvegarde locale (JSON).</summary>
    [Serializable]
    public class SaveData
    {
        public int version = 1;
        public string playerName;
        public int level = 1;
        public int xp;
        public int kinCoins;
        public int kinGold;
        public List<string> unlockedVehicles = new List<string>();
        public string selectedVehicle;
        public List<string> completedMissions = new List<string>();
        public List<string> unlockedItems = new List<string>();      // vêtements, autocollants, trophées…
        public List<VehicleCustomizationEntry> vehicleCustomizations = new List<VehicleCustomizationEntry>();
        public AvatarData avatar = new AvatarData();
        public SettingsData settings = new SettingsData();
        public bool adsRemoved;
        public int bonusDoubleCoins;     // missions restantes avec récompense en pièces doublée
        public int fuelTokens;           // pleins gratuits
    }

    /// <summary>
    /// Sauvegarde locale automatique dans Application.persistentDataPath.
    ///  - Chargement paresseux : l'ordre d'initialisation des managers n'a pas d'importance.
    ///  - Écriture atomique (fichier temporaire puis remplacement) : une coupure en pleine écriture ne détruit pas la sauvegarde.
    ///  - SaveSoon() regroupe les écritures (max. 1 par seconde) pour les changements fréquents (curseurs de réglages).
    ///  - Sauvegarde aussi à la mise en pause et à la fermeture (Android peut tuer l'appli sans prévenir).
    /// </summary>
    public class SaveManager : MonoBehaviour
    {
        public static SaveManager Instance { get; private set; }

        /// <summary>Déclenché APRÈS la remise à zéro : chaque manager doit alors se réinitialiser en mémoire.</summary>
        public static event Action ProgressReset;

        private const string FileName = "taxikin_save.json";
        private string FilePath => Path.Combine(Application.persistentDataPath, FileName);
        private string TempPath => FilePath + ".tmp";
        private string CorruptPath => FilePath + ".corrupt";

        private SaveData data = new SaveData();
        private bool loaded;
        private bool dirty;
        private float flushAt;

        public SaveData Data { get { EnsureLoaded(); return data; } }

        private void Awake()
        {
            if (Instance != null && Instance != this) { Destroy(this); return; }
            Instance = this;
        }

        public bool HasSave() => File.Exists(FilePath) || File.Exists(TempPath);

        private void EnsureLoaded()
        {
            if (loaded) return;
            loaded = true;
            // Si l'appli a été coupée entre l'écriture et le remplacement, le fichier temporaire est complet : on le reprend
            string source = File.Exists(FilePath) ? FilePath : (File.Exists(TempPath) ? TempPath : null);
            if (source == null) return;
            try
            {
                data = JsonUtility.FromJson<SaveData>(File.ReadAllText(source)) ?? new SaveData();
                if (data.settings == null) data.settings = new SettingsData();
                if (data.avatar == null) data.avatar = new AvatarData();
            }
            catch (Exception e)
            {
                // On garde une copie du fichier illisible au lieu de l'écraser en silence
                Debug.LogError($"[SaveManager] Sauvegarde illisible, copie conservée en .corrupt : {e.Message}");
                try { File.Copy(source, CorruptPath, true); } catch { /* sans importance */ }
                data = new SaveData();
            }
        }

        /// <summary>Écrit immédiatement la sauvegarde sur le disque.</summary>
        public void Save()
        {
            EnsureLoaded();
            dirty = false;
            try
            {
                File.WriteAllText(TempPath, JsonUtility.ToJson(data));
                if (File.Exists(FilePath)) File.Delete(FilePath);
                File.Move(TempPath, FilePath);
            }
            catch (Exception e) { Debug.LogError($"[SaveManager] Échec de la sauvegarde : {e.Message}"); }
        }

        /// <summary>Demande une sauvegarde groupée (au plus une par seconde).</summary>
        public void SaveSoon()
        {
            EnsureLoaded();
            if (!dirty) flushAt = Time.unscaledTime + 1f;
            dirty = true;
        }

        private void Update() { if (dirty && Time.unscaledTime >= flushAt) Save(); }

        /// <summary>Efface toute la progression, puis prévient les managers pour qu'ils se réinitialisent.</summary>
        public void ResetProgress()
        {
            data = new SaveData();
            loaded = true;
            dirty = false;
            try
            {
                if (File.Exists(FilePath)) File.Delete(FilePath);
                if (File.Exists(TempPath)) File.Delete(TempPath);
            }
            catch (Exception e) { Debug.LogError($"[SaveManager] Suppression impossible : {e.Message}"); }
            ProgressReset?.Invoke();
            Save();
        }

        private void OnApplicationPause(bool paused) { if (paused && loaded) Save(); }
        private void OnApplicationQuit() { if (loaded) Save(); }
    }
}

using System;
using System.Collections.Generic;
using System.IO;
using UnityEngine;
using TaxiKin.Player;
using TaxiKin.Vehicles;

namespace TaxiKin.SaveSystem
{
    /// <summary>Contenu de la sauvegarde locale (JSON). L'Étape 6 la complétera (paramètres, langue, véhicules…).</summary>
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
        public string language = "fr";
    }

    /// <summary>
    /// Lecture/écriture de la sauvegarde dans Application.persistentDataPath.
    /// Chargement paresseux (au premier accès) : l'ordre d'initialisation des managers n'a pas d'importance.
    /// Sauvegarde aussi à la mise en pause et à la fermeture (Android peut tuer l'appli sans prévenir).
    /// </summary>
    public class SaveManager : MonoBehaviour
    {
        public static SaveManager Instance { get; private set; }

        private const string FileName = "taxikin_save.json";
        private string FilePath => Path.Combine(Application.persistentDataPath, FileName);

        private SaveData data = new SaveData();
        private bool loaded;

        public SaveData Data { get { EnsureLoaded(); return data; } }

        private void Awake()
        {
            if (Instance != null && Instance != this) { Destroy(this); return; }
            Instance = this;
        }

        public bool HasSave() => File.Exists(FilePath);

        private void EnsureLoaded()
        {
            if (loaded) return;
            loaded = true;
            if (!HasSave()) return;
            try { data = JsonUtility.FromJson<SaveData>(File.ReadAllText(FilePath)) ?? new SaveData(); }
            catch (Exception e) { Debug.LogError($"[SaveManager] Sauvegarde illisible : {e.Message}"); data = new SaveData(); }
        }

        public void Save()
        {
            EnsureLoaded();
            try { File.WriteAllText(FilePath, JsonUtility.ToJson(data)); }
            catch (Exception e) { Debug.LogError($"[SaveManager] Échec de la sauvegarde : {e.Message}"); }
        }

        public void ResetProgress()
        {
            data = new SaveData();
            loaded = true;
            if (File.Exists(FilePath)) File.Delete(FilePath);
        }

        private void OnApplicationPause(bool paused) { if (paused && loaded) Save(); }
        private void OnApplicationQuit() { if (loaded) Save(); }
    }
}

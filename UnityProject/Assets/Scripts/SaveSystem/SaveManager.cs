using System;
using System.Collections.Generic;
using System.IO;
using UnityEngine;
using TaxiKin.Player;

namespace TaxiKin.SaveSystem
{
    /// <summary>Contenu de la sauvegarde locale (JSON). Complété à l'Étape 6.</summary>
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
        public AvatarData avatar = new AvatarData();
        public string language = "fr";
    }

    /// <summary>
    /// Lecture/écriture de la sauvegarde dans Application.persistentDataPath.
    /// Sauvegarde automatique à la mise en pause et à la fermeture (Android peut tuer l'appli sans prévenir).
    /// </summary>
    public class SaveManager : MonoBehaviour
    {
        private const string FileName = "taxikin_save.json";
        private string FilePath => Path.Combine(Application.persistentDataPath, FileName);

        public SaveData Data { get; private set; } = new SaveData();

        public bool HasSave() => File.Exists(FilePath);

        public void Save()
        {
            try { File.WriteAllText(FilePath, JsonUtility.ToJson(Data)); }
            catch (Exception e) { Debug.LogError($"[SaveManager] Échec de la sauvegarde : {e.Message}"); }
        }

        public void Load()
        {
            if (!HasSave()) return;
            try { Data = JsonUtility.FromJson<SaveData>(File.ReadAllText(FilePath)) ?? new SaveData(); }
            catch (Exception e) { Debug.LogError($"[SaveManager] Sauvegarde illisible : {e.Message}"); Data = new SaveData(); }
        }

        public void ResetProgress()
        {
            Data = new SaveData();
            if (File.Exists(FilePath)) File.Delete(FilePath);
        }

        private void OnApplicationPause(bool paused) { if (paused) Save(); }
        private void OnApplicationQuit() => Save();
    }
}

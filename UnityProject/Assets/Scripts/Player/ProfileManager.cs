using System;
using UnityEngine;
using TaxiKin.Missions;
using TaxiKin.SaveSystem;

namespace TaxiKin.Player
{
    /// <summary>
    /// Profil du chauffeur : nom, niveau, XP, avatar et objets débloqués. Persisté via SaveManager.
    /// HasProfile = false au premier lancement → l'écran « Mon chauffeur » s'ouvre automatiquement.
    /// </summary>
    public class ProfileManager : MonoBehaviour
    {
        public static ProfileManager Instance { get; private set; }

        [SerializeField] private MissionManager missions;   // Pour enregistrer les déblocages de missions

        private AvatarData avatar = new AvatarData();
        private int level = 1, xp;
        private bool hasProfile, loaded;

        public AvatarData Avatar { get { EnsureLoaded(); return avatar; } }
        public int Level { get { EnsureLoaded(); return level; } }
        public int Xp { get { EnsureLoaded(); return xp; } }
        public bool HasProfile { get { EnsureLoaded(); return hasProfile; } }

        public event Action OnProfileChanged;
        /// <summary>Déclenché quand le joueur ENREGISTRE son avatar (utilisé par la mission « Crée ton identité »).</summary>
        public event Action OnAvatarSaved;

        private void Awake()
        {
            if (Instance != null && Instance != this) { Destroy(this); return; }
            Instance = this;
        }

        private void Start() { if (missions != null) missions.OnUnlock += Unlock; }
        private void OnDestroy() { if (missions != null) missions.OnUnlock -= Unlock; }

        /// <summary>Charge depuis la sauvegarde dès que SaveManager existe.</summary>
        private void EnsureLoaded()
        {
            if (loaded) return;
            var sm = SaveManager.Instance;
            if (sm == null) return;
            loaded = true;
            var d = sm.Data;
            level = Mathf.Max(1, d.level);
            xp = d.xp;
            if (!string.IsNullOrEmpty(d.playerName) && d.avatar != null)
            {
                avatar = d.avatar;
                hasProfile = true;
            }
        }

        public void SetAvatar(AvatarData data)
        {
            EnsureLoaded();
            avatar = data;
            hasProfile = true;
            Persist();
            OnProfileChanged?.Invoke();
            OnAvatarSaved?.Invoke();
        }

        public void AddXp(int amount)
        {
            if (amount <= 0) return;
            EnsureLoaded();
            xp += amount;
            while (xp >= XpForNextLevel(level)) { xp -= XpForNextLevel(level); level++; }
            Persist();
            OnProfileChanged?.Invoke();
        }

        /// <summary>Courbe d'XP simple : 100 XP au niveau 1, +50 par niveau.</summary>
        public static int XpForNextLevel(int levelValue) => 100 + (levelValue - 1) * 50;

        // ---------- Objets débloqués (vêtements, autocollants, trophées…) ----------

        public bool Owns(string id)
        {
            var sm = SaveManager.Instance;
            return !string.IsNullOrEmpty(id) && sm != null && sm.Data.unlockedItems.Contains(id);
        }

        public void Unlock(string id)
        {
            var sm = SaveManager.Instance;
            if (sm == null || string.IsNullOrEmpty(id) || sm.Data.unlockedItems.Contains(id)) return;
            sm.Data.unlockedItems.Add(id);
            sm.Save();
        }

        private void Persist()
        {
            var sm = SaveManager.Instance;
            if (sm == null) return;
            var d = sm.Data;
            d.playerName = avatar.driverName;
            d.avatar = avatar;
            d.level = level;
            d.xp = xp;
            sm.Save();   // Sauvegarde automatique après chaque changement
        }
    }
}

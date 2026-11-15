using System;
using UnityEngine;

namespace TaxiKin.Player
{
    /// <summary>Profil du chauffeur : nom, niveau, XP et avatar. Source de vérité pour SaveManager.</summary>
    public class ProfileManager : MonoBehaviour
    {
        public static ProfileManager Instance { get; private set; }

        public AvatarData Avatar { get; private set; } = new AvatarData();
        public int Level { get; private set; } = 1;
        public int Xp { get; private set; }
        public bool HasProfile { get; private set; }   // false au premier lancement → création d'avatar

        public event Action OnProfileChanged;

        private void Awake()
        {
            if (Instance != null && Instance != this) { Destroy(this); return; }
            Instance = this;
        }

        public void SetAvatar(AvatarData data)
        {
            Avatar = data;
            HasProfile = true;
            OnProfileChanged?.Invoke();
        }

        public void AddXp(int amount)
        {
            if (amount <= 0) return;
            Xp += amount;
            while (Xp >= XpForNextLevel(Level)) { Xp -= XpForNextLevel(Level); Level++; }
            OnProfileChanged?.Invoke();
        }

        /// <summary>Courbe d'XP simple : 100 XP au niveau 1, +50 par niveau.</summary>
        public static int XpForNextLevel(int level) => 100 + (level - 1) * 50;
    }
}

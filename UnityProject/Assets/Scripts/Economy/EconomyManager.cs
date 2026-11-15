using System;
using UnityEngine;
using TaxiKin.SaveSystem;

namespace TaxiKin.Economy
{
    /// <summary>
    /// Deux monnaies : Kin Coins (gratuite, gagnée en jouant) et Kin Gold (premium, achetée ou gagnée en récompense rare).
    /// Toute dépense passe par TrySpend* (jamais de solde négatif). Sauvegarde automatique à chaque changement.
    /// </summary>
    public class EconomyManager : MonoBehaviour
    {
        public static EconomyManager Instance { get; private set; }

        private int coins, gold, doubleCharges;
        private bool loaded;

        public int KinCoins { get { Ensure(); return coins; } }
        public int KinGold { get { Ensure(); return gold; } }
        public int DoubleCoinsCharges { get { Ensure(); return doubleCharges; } }

        public event Action OnBalanceChanged;

        private void Awake()
        {
            if (Instance != null && Instance != this) { Destroy(this); return; }
            Instance = this;
        }

        /// <summary>Chargement paresseux : l'ordre d'initialisation des managers n'a pas d'importance.</summary>
        private void Ensure()
        {
            if (loaded) return;
            var sm = SaveManager.Instance;
            if (sm == null) return;
            loaded = true;
            coins = Mathf.Max(0, sm.Data.kinCoins);
            gold = Mathf.Max(0, sm.Data.kinGold);
            doubleCharges = Mathf.Max(0, sm.Data.bonusDoubleCoins);
        }

        public void AddCoins(int amount) { if (amount <= 0) return; Ensure(); coins += amount; Changed(); }
        public void AddGold(int amount) { if (amount <= 0) return; Ensure(); gold += amount; Changed(); }
        public void AddDoubleCoinsBonus(int missions) { if (missions <= 0) return; Ensure(); doubleCharges += missions; Changed(); }

        public bool TrySpendCoins(int amount)
        {
            Ensure();
            if (amount < 0 || coins < amount) return false;
            coins -= amount;
            Changed();
            return true;
        }

        public bool TrySpendGold(int amount)
        {
            Ensure();
            if (amount < 0 || gold < amount) return false;
            gold -= amount;
            Changed();
            return true;
        }

        /// <summary>Multiplicateur de la récompense en pièces d'une mission réussie (2 si un bonus est actif, consommé ici).</summary>
        public int ConsumeDoubleCoins()
        {
            Ensure();
            if (doubleCharges <= 0) return 1;
            doubleCharges--;
            Changed();
            return 2;
        }

        private void Changed()
        {
            var sm = SaveManager.Instance;
            if (sm != null)
            {
                sm.Data.kinCoins = coins;
                sm.Data.kinGold = gold;
                sm.Data.bonusDoubleCoins = doubleCharges;
                sm.Save();   // Sauvegarde automatique
            }
            OnBalanceChanged?.Invoke();
        }
    }
}

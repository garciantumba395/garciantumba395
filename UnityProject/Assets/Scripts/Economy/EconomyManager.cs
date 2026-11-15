using System;
using UnityEngine;

namespace TaxiKin.Economy
{
    /// <summary>
    /// Deux monnaies : Kin Coins (gratuite, gagnée en jouant) et Kin Gold (premium).
    /// Toute dépense passe par TrySpend* pour empêcher un solde négatif.
    /// </summary>
    public class EconomyManager : MonoBehaviour
    {
        public static EconomyManager Instance { get; private set; }

        public int KinCoins { get; private set; }
        public int KinGold { get; private set; }

        public event Action OnBalanceChanged;

        private void Awake()
        {
            if (Instance != null && Instance != this) { Destroy(this); return; }
            Instance = this;
        }

        /// <summary>Utilisé par SaveManager au chargement.</summary>
        public void SetBalances(int coins, int gold)
        {
            KinCoins = Mathf.Max(0, coins);
            KinGold = Mathf.Max(0, gold);
            OnBalanceChanged?.Invoke();
        }

        public void AddCoins(int amount) { if (amount > 0) { KinCoins += amount; OnBalanceChanged?.Invoke(); } }
        public void AddGold(int amount) { if (amount > 0) { KinGold += amount; OnBalanceChanged?.Invoke(); } }

        public bool TrySpendCoins(int amount)
        {
            if (amount < 0 || KinCoins < amount) return false;
            KinCoins -= amount;
            OnBalanceChanged?.Invoke();
            return true;
        }

        public bool TrySpendGold(int amount)
        {
            if (amount < 0 || KinGold < amount) return false;
            KinGold -= amount;
            OnBalanceChanged?.Invoke();
            return true;
        }
    }
}

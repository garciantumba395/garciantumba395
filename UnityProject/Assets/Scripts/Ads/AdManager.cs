using UnityEngine;

namespace TaxiKin.Ads
{
    /// <summary>
    /// Point d'entrée unique des publicités. Aucun SDK pour l'instant : les appels sont des stubs.
    /// Si « Supprimer les publicités » est acheté, aucune pub n'est jamais affichée.
    /// </summary>
    public class AdManager : MonoBehaviour
    {
        public bool AdsRemoved { get; private set; }

        public void SetAdsRemoved(bool removed) => AdsRemoved = removed;

        /// <summary>Pub interstitielle (à appeler uniquement entre deux missions, jamais en conduite).</summary>
        public void ShowInterstitial()
        {
            if (AdsRemoved) return;
            Debug.Log("[AdManager] Interstitiel (stub) — SDK publicitaire à intégrer.");
        }

        /// <summary>Pub récompensée facultative (ex. doubler la récompense).</summary>
        public void ShowRewarded(System.Action onRewarded)
        {
            Debug.Log("[AdManager] Pub récompensée (stub).");
            onRewarded?.Invoke();
        }
    }
}

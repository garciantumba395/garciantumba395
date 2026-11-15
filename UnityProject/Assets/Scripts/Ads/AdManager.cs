using System;
using UnityEngine;
using TaxiKin.SaveSystem;

namespace TaxiKin.Ads
{
    /// <summary>
    /// Publicités : UNIQUEMENT des publicités récompensées, facultatives, déclenchées par le joueur
    /// (ex. « doubler la récompense ») et jamais pendant la conduite. Aucun SDK pour l'instant : stub.
    /// « Supprimer les publicités » masque les propositions de publicité ; l'état est sauvegardé.
    /// </summary>
    public class AdManager : MonoBehaviour
    {
        public bool AdsRemoved => SaveManager.Instance != null && SaveManager.Instance.Data.adsRemoved;

        public event Action OnAdsRemovedChanged;

        public void SetAdsRemoved(bool removed)
        {
            var sm = SaveManager.Instance;
            if (sm == null || sm.Data.adsRemoved == removed) return;
            sm.Data.adsRemoved = removed;
            sm.Save();
            OnAdsRemovedChanged?.Invoke();
        }

        /// <summary>Faut-il proposer le bouton « regarder une pub pour doubler la récompense » ?</summary>
        public bool CanOfferRewarded => !AdsRemoved;

        /// <summary>Publicité récompensée facultative. À brancher sur un SDK ; l'appelant donne la récompense.</summary>
        public void ShowRewarded(Action onRewarded)
        {
            Debug.Log("[AdManager] Publicité récompensée (stub, SDK à intégrer).");
            onRewarded?.Invoke();
        }
    }
}

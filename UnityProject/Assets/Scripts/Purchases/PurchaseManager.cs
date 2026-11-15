using System;
using UnityEngine;

namespace TaxiKin.Purchases
{
    /// <summary>
    /// Achats intégrés. Étape 1 : identifiants de produits et API uniquement (Unity IAP sera branché à l'Étape 5).
    /// Non consommables : véhicules, suppression des pubs. Consommables : carburant, bonus, Kin Gold.
    /// Les achats sont toujours facultatifs : le jeu reste entièrement jouable sans payer.
    /// </summary>
    public class PurchaseManager : MonoBehaviour
    {
        public const string ProductRemoveAds = "taxikin.removeads";        // non consommable
        public const string ProductWewaUpgraded = "taxikin.wewa.upgraded"; // non consommable
        public const string ProductTaxi = "taxikin.taxi";                  // non consommable
        public const string ProductTaxiPremium = "taxikin.taxi.premium";   // non consommable
        public const string ProductBus207 = "taxikin.bus207";              // non consommable
        public const string ProductFullPack = "taxikin.fullpack";          // non consommable
        public const string ProductFuelPack = "taxikin.fuel.pack";         // consommable

        public event Action<string> OnPurchaseCompleted;

        public void Purchase(string productId)
        {
            Debug.Log($"[PurchaseManager] Achat de {productId} (stub — Unity IAP à intégrer).");
            // Étape 5 : appel à Unity IAP, puis OnPurchaseCompleted?.Invoke(productId);
        }
    }
}

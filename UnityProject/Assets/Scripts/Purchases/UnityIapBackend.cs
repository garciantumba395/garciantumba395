#if UNITY_PURCHASING
using System;
using System.Collections.Generic;
using UnityEngine;
using UnityEngine.Purchasing;
using TaxiKin.Economy;

namespace TaxiKin.Purchases
{
    /// <summary>
    /// Implémentation Unity IAP (API classique 4.x : IStoreListener). NON TESTÉE : écrite sans accès à l'éditeur.
    /// Si le projet installe Unity IAP 5.x (nouvelle API StoreController), seul CE fichier est à adapter.
    /// À faire avant la sortie : validation des reçus côté serveur, bouton « Restaurer les achats » sur iOS.
    /// </summary>
    public class UnityIapBackend : IDetailedStoreListener, IStoreBackend
    {
        private IStoreController controller;
        private Action<string> completed;
        private Action<string, string> failed;

        public bool IsReady => controller != null;
        public string Name => "Unity IAP";

        public string GetLocalizedPrice(string productId)
        {
            var p = controller?.products.WithID(productId);
            return p != null && p.availableToPurchase ? p.metadata.localizedPriceString : null;
        }

        public void Initialize(IList<ShopItem> products, Action<string> onCompleted, Action<string, string> onFailed)
        {
            completed = onCompleted;
            failed = onFailed;
            var builder = ConfigurationBuilder.Instance(StandardPurchasingModule.Instance());
            foreach (var p in products)
                builder.AddProduct(p.iapId, p.iapKind == ProductKind.Consumable ? ProductType.Consumable : ProductType.NonConsumable);
            UnityPurchasing.Initialize(this, builder);
        }

        public void Buy(string productId) => controller?.InitiatePurchase(productId);

        public void OnInitialized(IStoreController c, IExtensionProvider e) => controller = c;
        public void OnInitializeFailed(InitializationFailureReason error) => Debug.LogWarning($"[IAP] Initialisation impossible : {error}");
        public void OnInitializeFailed(InitializationFailureReason error, string message) => Debug.LogWarning($"[IAP] Initialisation impossible : {error} {message}");

        public PurchaseProcessingResult ProcessPurchase(PurchaseEventArgs args)
        {
            completed?.Invoke(args.purchasedProduct.definition.id);
            return PurchaseProcessingResult.Complete;   // l'achat est accordé puis confirmé au magasin
        }

        public void OnPurchaseFailed(Product product, PurchaseFailureReason reason) =>
            failed?.Invoke(product.definition.id, reason == PurchaseFailureReason.UserCancelled ? "Achat annulé." : "Achat impossible : " + reason);

        public void OnPurchaseFailed(Product product, PurchaseFailureDescription description) =>
            failed?.Invoke(product.definition.id, description.reason == PurchaseFailureReason.UserCancelled ? "Achat annulé." : "Achat impossible : " + description.message);
    }
}
#endif

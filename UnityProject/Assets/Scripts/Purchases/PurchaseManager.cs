using System;
using System.Collections.Generic;
using System.Globalization;
using UnityEngine;
using TaxiKin.Economy;

namespace TaxiKin.Purchases
{
    /// <summary>Magasin d'applications (Google Play / App Store) vu par le jeu.</summary>
    public interface IStoreBackend
    {
        bool IsReady { get; }
        string Name { get; }
        /// <summary>Prix localisé fourni par le magasin (null si inconnu).</summary>
        string GetLocalizedPrice(string productId);
        void Initialize(IList<ShopItem> products, Action<string> onCompleted, Action<string, string> onFailed);
        void Buy(string productId);
    }

    /// <summary>
    /// Achats intégrés (argent réel). Les achats sont TOUJOURS facultatifs.
    ///  - Non consommables : véhicules, suppression des publicités, pack complet.
    ///  - Consommables : Kin Gold, pack de carburant.
    /// Unity IAP est utilisé si le paquet « com.unity.purchasing » est installé (symbole UNITY_PURCHASING) ;
    /// sinon une boutique SIMULÉE n'existe que dans l'éditeur et les versions de développement.
    /// Une version de production sans Unity IAP refuse les achats au lieu de les accorder gratuitement.
    /// </summary>
    public class PurchaseManager : MonoBehaviour
    {
        public event Action<string> OnPurchaseCompleted;            // identifiant de produit
        public event Action<string, string> OnPurchaseFailed;       // identifiant, raison

        private IStoreBackend backend;

        private void Start()
        {
            var products = new List<ShopItem>();
            foreach (var i in ShopCatalog.All) if (!string.IsNullOrEmpty(i.iapId)) products.Add(i);

#if UNITY_PURCHASING
            backend = new UnityIapBackend();
#else
            if (Application.isEditor || Debug.isDebugBuild) backend = new SimulatedStoreBackend();
#endif
            backend?.Initialize(products, id => OnPurchaseCompleted?.Invoke(id), (id, why) => OnPurchaseFailed?.Invoke(id, why));
        }

        public bool IsAvailable => backend != null && backend.IsReady;
        public string BackendName => backend != null ? backend.Name : "aucun";

        /// <summary>Lance l'achat ; le résultat arrive par OnPurchaseCompleted / OnPurchaseFailed.</summary>
        public void Purchase(string productId)
        {
            if (!IsAvailable) { OnPurchaseFailed?.Invoke(productId, "Les achats ne sont pas disponibles pour le moment."); return; }
            backend.Buy(productId);
        }

        /// <summary>Prix à afficher : prix du magasin si connu, sinon prix indicatif en USD.</summary>
        public string PriceLabel(ShopItem item)
        {
            string local = backend?.GetLocalizedPrice(item.iapId);
            return !string.IsNullOrEmpty(local) ? local : string.Format(new CultureInfo("fr-FR"), "{0:0.00} US$", item.usd);
        }
    }

    /// <summary>Boutique simulée pour tester dans l'éditeur : accorde l'achat immédiatement. Jamais en production.</summary>
    public class SimulatedStoreBackend : IStoreBackend
    {
        private Action<string> completed;
        public bool IsReady => true;
        public string Name => "Boutique simulée (test)";
        public string GetLocalizedPrice(string productId) => null;

        public void Initialize(IList<ShopItem> products, Action<string> onCompleted, Action<string, string> onFailed) => completed = onCompleted;

        public void Buy(string productId)
        {
            Debug.Log($"[Achat simulé] {productId}");
            completed?.Invoke(productId);
        }
    }
}

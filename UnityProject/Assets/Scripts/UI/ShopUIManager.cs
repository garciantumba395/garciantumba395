using System;
using System.Collections.Generic;
using UnityEngine;
using UnityEngine.UI;
using TaxiKin.Economy;
using TaxiKin.Purchases;

namespace TaxiKin.UI
{
    /// <summary>
    /// Boutique : onglets par catégorie, prix affichés clairement dans les trois monnaies (Kin Coins, Kin Gold, argent réel).
    /// Les achats sont facultatifs ; chaque achat demande une confirmation (deuxième touche dans les 4 s).
    /// </summary>
    public class ShopUIManager : MonoBehaviour
    {
        [SerializeField] private GameObject panel;
        [SerializeField] private ShopManager shop;
        [SerializeField] private PurchaseManager purchases;
        [SerializeField] private RectTransform rowsContainer;
        [SerializeField] private ShopRow rowTemplate;
        [SerializeField] private Text coinsText, goldText, messageText, noteText;
        [SerializeField] private GameObject openGarageButton;
        [SerializeField] private GarageUIManager garageUi;

        // Ordre des onglets = ordre de l'énumération ShopCategory
        private ShopCategory current = ShopCategory.Vehicles;
        private readonly List<GameObject> rows = new List<GameObject>();
        private string pendingKey;
        private float pendingUntil;
        private Text pendingLabel;
        private string pendingNormalText;

        private void OnEnable()
        {
            if (shop != null) { shop.OnMessage += Say; shop.OnChanged += OnShopChanged; }
        }

        private void OnDisable()
        {
            if (shop != null) { shop.OnMessage -= Say; shop.OnChanged -= OnShopChanged; }
        }

        public void Open() { panel.SetActive(true); Say(""); Rebuild(); }
        public void Close() => panel.SetActive(false);
        public void OnCategory(int index) { current = (ShopCategory)Mathf.Clamp(index, 0, Enum.GetValues(typeof(ShopCategory)).Length - 1); Rebuild(); }
        public void OnOpenGarage() { Close(); garageUi?.Open(); }

        private void OnShopChanged() { if (panel.activeSelf) Rebuild(); }

        private void Update()
        {
            if (!panel.activeSelf) return;
            var eco = EconomyManager.Instance;
            if (eco != null)
            {
                coinsText.text = $"Kin Coins : {eco.KinCoins:N0}";
                goldText.text = $"Kin Gold : {eco.KinGold:N0}";
            }
            if (pendingLabel != null && Time.unscaledTime >= pendingUntil) { pendingLabel.text = pendingNormalText; pendingLabel = null; pendingKey = null; }
        }

        private void Rebuild()
        {
            foreach (var r in rows) Destroy(r);
            rows.Clear();
            pendingLabel = null; pendingKey = null;

            bool service = current == ShopCategory.Plates || current == ShopCategory.Repairs || current == ShopCategory.Upgrades;
            openGarageButton.SetActive(service);
            noteText.gameObject.SetActive(service || current == ShopCategory.Fuel);
            noteText.text = service ? "Ce service s'achète directement dans le garage, avec des Kin Coins."
                                    : current == ShopCategory.Fuel ? "Le plein se fait au garage (au prix du carburant) ; les packs de pleins sont gratuits à l'usage."
                                    : "";

            foreach (var item in ItemsFor(current))
            {
                if (service) break;
                var row = Instantiate(rowTemplate, rowsContainer);
                row.gameObject.SetActive(true);
                rows.Add(row.gameObject);
                Fill(row, item);
            }
        }

        /// <summary>Articles d'une catégorie. Les vêtements viennent des fiches d'avatar (prix en Kin Coins / Kin Gold).</summary>
        private static List<ShopItem> ItemsFor(ShopCategory c)
        {
            if (c != ShopCategory.Clothing) return ShopCatalog.Of(c);
            var list = new List<ShopItem>();
            foreach (var cl in TaxiKin.Player.AvatarCatalog.All)
            {
                if (cl == null || cl.priceKinCoins + cl.priceKinGold <= 0) continue;
                list.Add(new ShopItem { id = "cl_" + cl.id, category = ShopCategory.Clothing, title = cl.displayName,
                                        description = "Vêtement ou accessoire de ton chauffeur.", priceCoins = cl.priceKinCoins,
                                        priceGold = cl.priceKinGold, payload = cl.id });
            }
            return list;
        }

        private void Fill(ShopRow row, ShopItem item)
        {
            row.title.text = item.title;
            row.description.text = item.description;
            bool owned = shop.IsOwned(item);
            row.owned.gameObject.SetActive(owned);
            row.owned.text = "Possédé";

            SetupButton(row.coinsButton, row.coinsText, !owned && item.priceCoins > 0, $"{item.priceCoins:N0} Kin Coins",
                        item.id + "_c", () => shop.BuyWithCoins(item));
            SetupButton(row.goldButton, row.goldText, !owned && item.priceGold > 0, $"{item.priceGold:N0} Kin Gold",
                        item.id + "_g", () => shop.BuyWithGold(item));

            bool money = !owned && !string.IsNullOrEmpty(item.iapId);
            row.moneyButton.gameObject.SetActive(money);
            if (money)
            {
                string label = purchases != null ? purchases.PriceLabel(item) : $"{item.usd:0.00} US$";
                row.moneyText.text = label;
                row.moneyButton.onClick.RemoveAllListeners();
                // L'achat en argent réel passe par la fenêtre de confirmation du magasin (Google Play / App Store)
                row.moneyButton.onClick.AddListener(() => shop.BuyWithMoney(item));
            }
        }

        private void SetupButton(Button button, Text label, bool visible, string text, string key, Func<Outcome> buy)
        {
            button.gameObject.SetActive(visible);
            if (!visible) return;
            label.text = text;
            button.onClick.RemoveAllListeners();
            button.onClick.AddListener(() =>
            {
                if (pendingKey == key && Time.unscaledTime < pendingUntil)
                {
                    pendingKey = null; pendingLabel = null;
                    Say(buy().message);
                    return;
                }
                if (pendingLabel != null) pendingLabel.text = pendingNormalText;     // une seule confirmation à la fois
                pendingKey = key;
                pendingUntil = Time.unscaledTime + 4f;
                pendingLabel = label;
                pendingNormalText = text;
                label.text = "Confirmer ?";
            });
        }

        private void Say(string message) { if (messageText != null) messageText.text = message; }
    }
}

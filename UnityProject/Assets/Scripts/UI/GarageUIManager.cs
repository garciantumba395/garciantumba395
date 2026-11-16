using System.Text;
using UnityEngine;
using UnityEngine.UI;
using TaxiKin.Economy;
using TaxiKin.Player;
using TaxiKin.Vehicles;

namespace TaxiKin.UI
{
    /// <summary>
    /// Garage : aperçu 3D, choix du véhicule, réparation, carburant, améliorations, couleurs, jantes, autocollants, plaque.
    /// Tout achat en Kin Coins demande une CONFIRMATION (deuxième touche dans les 5 s) : pas d'achat accidentel.
    /// Les boutons appellent ces méthodes (OnClick).
    /// </summary>
    public class GarageUIManager : MonoBehaviour
    {
        [SerializeField] private GameObject panel;
        [SerializeField] private PlayerController player;
        [SerializeField] private GarageManager garage;
        [SerializeField] private VehicleColorManager colors;
        [SerializeField] private ShopUIManager shopUi;

        [Header("Textes")]
        [SerializeField] private Text vehicleName, vehicleStatus, conditionText, repairLabel, refuelLabel, messageText, plateLabel;
        [SerializeField] private InputField plateField;
        [SerializeField] private Text useLabel;
        [SerializeField] private Text[] bodyLabels, roofLabels, rimLabels, stickerLabels, upgradeLabels;

        private static readonly string[] RimNames = { "Standard", "Chrome", "Or" };
        private static readonly string[] StickerNames = { "Aucun", "Kin", "Rayures", "Exclusif" };
        private static readonly string[] StickerIds = { "", "sticker_kin", "sticker_stripes", "sticker_kin_exclusive" };
        private static readonly string[] UpgradeNames = { "Moteur", "Freins", "Pneus", "Carrosserie" };

        private string pendingKey;
        private float pendingUntil;
        private ShopManager Shop => ShopManager.Instance;
        private VehicleCustomizer Cust => garage != null ? garage.PreviewCustomizer : null;

        private VehicleController WorldVehicle =>
            player != null && garage != null && garage.Selected != null ? player.GetVehicle(garage.Selected.type) : null;

        // ---------- Ouverture / fermeture ----------

        public void Open()
        {
            panel.SetActive(true);
            garage.SetPreviewActive(true);
            garage.Refresh();
            Refresh();
            Say("");
        }

        public void Close()
        {
            garage.SetPreviewActive(false);
            panel.SetActive(false);
        }

        private void OnEnable() { if (garage != null) garage.OnVehicleChanged += OnVehicleChanged; }
        private void OnDisable() { if (garage != null) garage.OnVehicleChanged -= OnVehicleChanged; }
        private void OnVehicleChanged(GarageVehicleEntry e) { if (panel.activeSelf) { if (Cust != null && plateField != null) plateField.text = Cust.Current.plate; Refresh(); } }

        // ---------- Choix du véhicule ----------

        public void OnPrevious() => garage.Previous();
        public void OnNext() => garage.Next();

        public void OnUse()
        {
            if (garage.UseSelected()) { Say("Véhicule sélectionné : " + garage.Selected.title); Refresh(); }
            else Say("Ce véhicule est verrouillé. Gagne-le en jouant ou ouvre la boutique.");
        }

        public void OnOpenShop() { if (shopUi != null) shopUi.Open(); }

        // ---------- Réparation et carburant ----------

        public void OnRepair()
        {
            var v = WorldVehicle;
            if (v == null || Shop == null) return;
            int cost = Shop.RepairCost(v);
            if (cost > 0 && !Confirm("repair", $"Réparer : {cost} Kin Coins.")) return;
            Say(Shop.Repair(v).message);
            Refresh();
        }

        public void OnRefuel()
        {
            var v = WorldVehicle;
            if (v == null || Shop == null) return;
            int cost = Shop.FuelTokens > 0 ? 0 : Shop.FuelCost(v);
            if (cost > 0 && !Confirm("fuel", $"Faire le plein : {cost} Kin Coins.")) return;
            Say(Shop.Refuel(v).message);
            Refresh();
        }

        // ---------- Personnalisation ----------

        public void OnBodyColor(int i) { if (TryUnlockColor(i)) Cust?.SetBodyColor(colors.GetColor(i)); Refresh(); }
        public void OnRoofColor(int i) { if (TryUnlockColor(i)) Cust?.SetRoofColor(colors.GetColor(i)); Refresh(); }

        public void OnRims(int i)
        {
            if (Cust == null) return;
            if (i > 0 && !Owned("rim_" + i) && !Buy("rim_" + i)) { Refresh(); return; }
            Cust.SetRims(i);
            Refresh();
        }

        public void OnSticker(int i)
        {
            if (Cust == null || i < 0 || i >= StickerIds.Length) return;
            string id = StickerIds[i];
            if (id != "" && !Owned(id))
            {
                if (id == "sticker_kin_exclusive") { Say("Autocollant exclusif : termine la mission « Le jaune de Kin »."); return; }
                if (!Buy(id)) { Refresh(); return; }
            }
            Cust.SetSticker(id);
            Refresh();
        }

        public void OnApplyPlate()
        {
            var c = Cust;
            if (c == null || plateField == null || Shop == null) return;
            if (!PlateFilter.TryNormalize(plateField.text, out var plate, out var error)) { Say(error); return; }
            if (plate == c.Current.plate) { Say("C'est déjà ta plaque."); return; }
            if (!Confirm("plate", $"Changer la plaque en « {plate} » : {ShopCatalog.PlateChangeCoins} Kin Coins.")) return;
            var o = Shop.Spend(ShopCatalog.PlateChangeCoins, "Plaque modifiée");
            if (!o.ok) { Say(o.message); return; }
            c.TrySetPlate(plate, out _);
            Say(o.message);
            Refresh();
        }

        public void OnUpgrade(int type)
        {
            var c = Cust;
            if (c == null || Shop == null) return;
            var t = (UpgradeType)type;
            int cost = Shop.UpgradeCost(c.GetUpgrade(t));
            if (cost < 0) { Say("Niveau maximum atteint."); return; }
            if (!Confirm("up" + type, $"{UpgradeNames[type]} niveau {c.GetUpgrade(t) + 1} : {cost} Kin Coins.")) return;
            Say(Shop.BuyUpgrade(c, t).message);
            Refresh();
        }

        // ---------- Achats d'articles ----------

        private bool Owned(string payload) => Shop != null && Shop.IsPayloadOwned(payload);

        private bool TryUnlockColor(int index)
        {
            string payload = ShopCatalog.ColorPayload(colors.Palette[index].hex);
            return Owned(payload) || Buy(payload);
        }

        /// <summary>Achète un article en Kin Coins après confirmation. Retourne vrai s'il est possédé ensuite.</summary>
        private bool Buy(string payload)
        {
            var item = ShopCatalog.FindByPayload(payload);
            if (item == null || item.priceCoins <= 0 || Shop == null) { Say("Cet article n'est pas en vente ici."); return false; }
            if (!Confirm(payload, $"{item.title} : {item.priceCoins} Kin Coins.")) return false;
            var o = Shop.BuyWithCoins(item);
            Say(o.message);
            return o.ok;
        }

        // ---------- Confirmation en deux touches ----------

        private bool Confirm(string key, string prompt)
        {
            if (pendingKey == key && Time.unscaledTime < pendingUntil) { pendingKey = null; return true; }
            pendingKey = key;
            pendingUntil = Time.unscaledTime + 5f;
            Say(prompt + "  Touche encore pour confirmer.");
            return false;
        }

        private void Say(string m) { if (messageText != null) messageText.text = m; }

        // ---------- Affichage ----------

        private void Refresh()
        {
            var e = garage.Selected;
            if (e == null) return;
            bool owned = garage.IsOwned(e);
            vehicleName.text = e.title;
            vehicleStatus.text = owned ? "Possédé" : "Verrouillé : gagne-le en jouant ou achète-le dans la boutique.";
            if (useLabel != null) useLabel.text = owned ? "Utiliser" : "Verrouillé";

            var v = WorldVehicle;
            if (v != null && Shop != null)
            {
                int fuelPct = Mathf.RoundToInt(v.Fuel / Mathf.Max(0.01f, v.Stats.fuelCapacity) * 100f);
                conditionText.text = $"Dommages {Mathf.RoundToInt(v.Damage)} %   ·   Carburant {fuelPct} %";
                int rc = Shop.RepairCost(v);
                repairLabel.text = rc > 0 ? $"Réparer ({rc})" : "Réparé";
                refuelLabel.text = Shop.FuelTokens > 0 ? $"Plein gratuit (x{Shop.FuelTokens})" : (Shop.FuelCost(v) > 0 ? $"Plein ({Shop.FuelCost(v)})" : "Plein");
            }

            var c = Cust;
            if (c == null) return;
            for (int i = 0; i < upgradeLabels.Length && i < UpgradeNames.Length; i++)
            {
                int lvl = c.GetUpgrade((UpgradeType)i), cost = Shop != null ? Shop.UpgradeCost(lvl) : -1;
                upgradeLabels[i].text = cost < 0 ? $"{UpgradeNames[i]} {lvl}/3  MAX" : $"{UpgradeNames[i]} {lvl}/3  ({cost})";
            }
            for (int i = 0; i < bodyLabels.Length && i < colors.Palette.Count; i++)
            {
                string price = ColorPriceText(i);
                bodyLabels[i].text = price; roofLabels[i].text = price;
            }
            for (int i = 0; i < rimLabels.Length; i++) rimLabels[i].text = RimNames[i] + PriceSuffix(i == 0 ? "" : "rim_" + i);
            for (int i = 0; i < stickerLabels.Length; i++) stickerLabels[i].text = StickerNames[i] + (i == 3 && !Owned(StickerIds[3]) ? " (mission)" : PriceSuffix(StickerIds[i]));
            if (plateLabel != null) plateLabel.text = $"OK ({ShopCatalog.PlateChangeCoins})";
        }

        private string ColorPriceText(int index)
        {
            string payload = ShopCatalog.ColorPayload(colors.Palette[index].hex);
            if (Owned(payload)) return "";
            var item = ShopCatalog.FindByPayload(payload);
            return item != null ? item.priceCoins.ToString() : "";
        }

        private string PriceSuffix(string payload)
        {
            if (string.IsNullOrEmpty(payload) || Owned(payload)) return "";
            var item = ShopCatalog.FindByPayload(payload);
            return item != null && item.priceCoins > 0 ? $" ({item.priceCoins})" : "";
        }
    }
}

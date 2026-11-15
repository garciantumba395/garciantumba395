using System.Collections.Generic;
using UnityEngine;
using TaxiKin.Vehicles;

namespace TaxiKin.Economy
{
    public enum ShopCategory { Vehicles, Skins, Colors, Rims, Stickers, Plates, Fuel, Repairs, Upgrades, NoAds, Packs, Gold, Clothing }
    public enum ProductKind { None, NonConsumable, Consumable }

    /// <summary>Un article de la boutique. Un prix à 0 = ce mode de paiement n'est pas proposé.</summary>
    public class ShopItem
    {
        public string id;
        public ShopCategory category;
        public string title, description;
        public int priceCoins;        // Kin Coins (monnaie gratuite)
        public int priceGold;         // Kin Gold (monnaie premium)
        public string iapId;          // identifiant de produit du magasin (achat en argent réel)
        public ProductKind iapKind;
        public float usd;             // prix indicatif affiché si le magasin ne répond pas
        public string payload;        // identifiant débloqué (véhicule, couleur, jante…)
        public int amount;            // quantité (Kin Gold, jetons de carburant…)
    }

    /// <summary>Apparence complète vendue en Kin Gold.</summary>
    public struct SkinPreset
    {
        public string id; public VehicleType kind; public string body, roof; public int rim; public string sticker;
    }

    /// <summary>
    /// Catalogue unique de la boutique (prix compris). Les prix en Kin Coins et en USD viennent du cahier des charges ;
    /// TOUS les prix en Kin Gold, les paquets de Kin Gold, les skins et les bonus sont PROVISOIRES (à équilibrer).
    /// </summary>
    public static class ShopCatalog
    {
        // Prix de services (réparation, carburant, plaque, améliorations) : voir ShopManager
        public const int PlateChangeCoins = 200;
        public static readonly int[] UpgradeCoins = { 1000, 2500, 5000 };

        public static readonly SkinPreset[] Skins =
        {
            new SkinPreset { id = "skin_taxi_gold",    kind = VehicleType.Taxi,   body = "#E6A700", roof = "#FFE600", rim = 2, sticker = "sticker_stripes" },
            new SkinPreset { id = "skin_wewa_neon",    kind = VehicleType.Wewa,   body = "#FFE600", roof = "#FFE600", rim = 1, sticker = "sticker_stripes" },
            new SkinPreset { id = "skin_bus_carnaval", kind = VehicleType.Bus207, body = "#EF6C00", roof = "#F2F2F2", rim = 1, sticker = "sticker_kin" },
        };

        private static List<ShopItem> all;

        public static IReadOnlyList<ShopItem> All { get { if (all == null) Build(); return all; } }

        public static ShopItem Find(string id) { foreach (var i in All) if (i.id == id) return i; return null; }
        public static ShopItem FindByIap(string iapId) { foreach (var i in All) if (i.iapId == iapId) return i; return null; }
        public static ShopItem FindByPayload(string payload) { foreach (var i in All) if (i.payload == payload) return i; return null; }
        public static List<ShopItem> Of(ShopCategory c) { var l = new List<ShopItem>(); foreach (var i in All) if (i.category == c) l.Add(i); return l; }

        public static string ColorPayload(string hex) => "color_" + hex.ToUpperInvariant();

        private static void Build()
        {
            all = new List<ShopItem>();

            // ----- Véhicules : le Wewa de base est gratuit ; taxi et 207 se débloquent aussi EN JOUANT (missions 5 et 7) -----
            Add("veh_wewa_upgraded", ShopCategory.Vehicles, "Wewa amélioré", "Moteur, freins, pneus et carrosserie +1 niveau.", 5000, 40, "taxikin.wewa.upgraded", 1.99f, "wewa_upgraded");
            Add("veh_taxi", ShopCategory.Vehicles, "Taxi-voiture", "Équilibré, jusqu'à 4 passagers. Aussi gagnable en jouant (mission « Débloque le taxi »).", 15000, 120, "taxikin.taxi", 2.99f, "taxi");
            Add("veh_taxi_premium", ShopCategory.Vehicles, "Taxi premium", "Taxi confortable : améliorations +1 niveau.", 35000, 250, "taxikin.taxi.premium", 4.99f, "taxi_premium");
            Add("veh_bus207", ShopCategory.Vehicles, "Bus 207", "Transport collectif, très rentable. Aussi gagnable en jouant (mission « Le roi du 207 »).", 50000, 350, "taxikin.bus207", 5.99f, "bus207");

            // ----- Skins premium (Kin Gold) -----
            Add("skin_taxi_gold", ShopCategory.Skins, "Taxi Or", "Carrosserie jaune-or, toit néon, jantes or, rayures.", 0, 150, null, 0f, "skin_taxi_gold");
            Add("skin_wewa_neon", ShopCategory.Skins, "Wewa Néon", "Jaune néon avec jantes chrome et rayures.", 0, 80, null, 0f, "skin_wewa_neon");
            Add("skin_bus_carnaval", ShopCategory.Skins, "207 Carnaval", "Orange et blanc, jantes chrome.", 0, 120, null, 0f, "skin_bus_carnaval");

            // ----- Couleurs de base (les 5 jaunes sont gratuits) -----
            for (int i = VehicleColorManager.YellowCount; i < VehicleColorManager.Defaults.Length; i++)
            {
                var c = VehicleColorManager.Defaults[i];
                Add("col_" + i, ShopCategory.Colors, c.name, "Couleur de carrosserie et de toit.", 500, 0, null, 0f, ColorPayload(c.hex));
            }

            // ----- Jantes, autocollants -----
            Add("rim_1", ShopCategory.Rims, "Jantes chrome", "", 800, 0, null, 0f, "rim_1");
            Add("rim_2", ShopCategory.Rims, "Jantes or", "", 1500, 0, null, 0f, "rim_2");
            Add("stk_kin", ShopCategory.Stickers, "Autocollant Kin", "", 300, 0, null, 0f, "sticker_kin");
            Add("stk_stripes", ShopCategory.Stickers, "Autocollant rayures", "", 500, 0, null, 0f, "sticker_stripes");

            // ----- Services (achat dans le garage) : plaque, réparations, améliorations -----
            Add("svc_plate", ShopCategory.Plates, "Plaque personnalisée", $"{PlateChangeCoins} Kin Coins par changement, au garage.", PlateChangeCoins, 0, null, 0f, null);
            Add("svc_repair", ShopCategory.Repairs, "Réparation", "Prix selon les dommages du véhicule, au garage.", 0, 0, null, 0f, null);
            Add("svc_upgrade", ShopCategory.Upgrades, "Améliorations", "Moteur, freins, pneus, carrosserie : 1 000 / 2 500 / 5 000 Kin Coins par niveau, au garage.", 0, 0, null, 0f, null);

            // ----- Carburant -----
            var fuel = Add("fuel_pack", ShopCategory.Fuel, "Pack de 3 pleins", "3 pleins gratuits, utilisables au garage.", 0, 30, "taxikin.fuel.pack", 0.99f, null);
            fuel.iapKind = ProductKind.Consumable; fuel.amount = 3;

            // ----- Suppression des publicités -----
            Add("no_ads", ShopCategory.NoAds, "Supprimer les publicités", "Plus aucune publicité. Achat unique.", 0, 300, "taxikin.removeads", 2.99f, "no_ads");

            // ----- Packs premium -----
            Add("pack_full", ShopCategory.Packs, "Pack complet", "Tous les véhicules, sans publicités, autocollant exclusif.", 0, 0, "taxikin.fullpack", 9.99f, "pack_full");
            var bonus = Add("bonus_double", ShopCategory.Packs, "Bonus pièces x2", "Double les Kin Coins de ta prochaine mission réussie.", 0, 40, null, 0f, null);
            bonus.amount = 1;

            // ----- Kin Gold (consommables) -----
            AddGold("gold_100", "100 Kin Gold", 100, "taxikin.gold.100", 0.99f);
            AddGold("gold_550", "550 Kin Gold", 550, "taxikin.gold.550", 4.99f);
            AddGold("gold_1200", "1 200 Kin Gold", 1200, "taxikin.gold.1200", 9.99f);
        }

        private static ShopItem Add(string id, ShopCategory cat, string title, string desc, int coins, int gold, string iap, float usd, string payload)
        {
            var item = new ShopItem
            {
                id = id, category = cat, title = title, description = desc, priceCoins = coins, priceGold = gold,
                iapId = iap, usd = usd, payload = payload,
                iapKind = string.IsNullOrEmpty(iap) ? ProductKind.None : ProductKind.NonConsumable,
            };
            all.Add(item);
            return item;
        }

        private static void AddGold(string id, string title, int amount, string iap, float usd)
        {
            var item = Add(id, ShopCategory.Gold, title, "Monnaie premium. Facultatif : tout se gagne en jouant.", 0, 0, iap, usd, null);
            item.iapKind = ProductKind.Consumable;
            item.amount = amount;
        }
    }
}

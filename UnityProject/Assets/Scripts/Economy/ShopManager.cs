using System;
using UnityEngine;
using TaxiKin.Ads;
using TaxiKin.Missions;
using TaxiKin.Player;
using TaxiKin.Purchases;
using TaxiKin.SaveSystem;
using TaxiKin.Vehicles;

namespace TaxiKin.Economy
{
    /// <summary>Résultat d'une action d'achat, avec un message prêt à afficher.</summary>
    public struct Outcome
    {
        public bool ok;
        public string message;
        public static Outcome Ok(string m) => new Outcome { ok = true, message = m };
        public static Outcome No(string m) => new Outcome { ok = false, message = m };
    }

    /// <summary>
    /// Règles d'achat : Kin Coins, Kin Gold, argent réel, déblocage de véhicules, réparation, carburant, améliorations.
    /// Principe : le joueur n'est JAMAIS obligé de payer. Quand les fonds manquent, on le dit et on propose de jouer.
    /// </summary>
    public class ShopManager : MonoBehaviour
    {
        public static ShopManager Instance { get; private set; }

        [SerializeField] private PurchaseManager purchases;
        [SerializeField] private MissionManager missions;
        [SerializeField] private PlayerController player;
        [SerializeField] private AdManager ads;

        [Header("Prix des services (Kin Coins)")]
        [SerializeField] private float fuelPricePerLiter = 20f;
        [SerializeField] private float repairPricePerPercent = 15f;

        private static readonly string[] AllVehicles = { "wewa_upgraded", "taxi", "taxi_premium", "bus207" };

        public event Action OnChanged;
        public event Action<string> OnMessage;

        private void Awake()
        {
            if (Instance != null && Instance != this) { Destroy(this); return; }
            Instance = this;
        }

        private void OnEnable() => SaveManager.ProgressReset += RaiseChanged;
        private void OnDisable() => SaveManager.ProgressReset -= RaiseChanged;
        private void RaiseChanged() => OnChanged?.Invoke();

        private void Start()
        {
            if (purchases != null) { purchases.OnPurchaseCompleted += OnMoneyPurchase; purchases.OnPurchaseFailed += OnMoneyFailed; }
            if (missions != null) missions.OnUnlock += OnMissionUnlock;
        }

        private void OnDestroy()
        {
            if (purchases != null) { purchases.OnPurchaseCompleted -= OnMoneyPurchase; purchases.OnPurchaseFailed -= OnMoneyFailed; }
            if (missions != null) missions.OnUnlock -= OnMissionUnlock;
        }

        // ---------- Propriété ----------

        public bool IsVehicleOwned(string id)
        {
            var sm = SaveManager.Instance;
            return id == "wewa_base" || (sm != null && sm.Data.unlockedVehicles.Contains(id));
        }

        public void UnlockVehicle(string id)
        {
            var sm = SaveManager.Instance;
            if (sm == null || sm.Data.unlockedVehicles.Contains(id)) return;
            sm.Data.unlockedVehicles.Add(id);
            sm.Save();
            OnChanged?.Invoke();
        }

        public bool IsPayloadOwned(string payload)
        {
            if (string.IsNullOrEmpty(payload)) return true;
            if (payload.StartsWith("color_")) return IsYellowPayload(payload) || (ProfileManager.Instance != null && ProfileManager.Instance.Owns(payload));
            return ProfileManager.Instance != null && ProfileManager.Instance.Owns(payload);
        }

        public bool IsOwned(ShopItem item)
        {
            if (item.category == ShopCategory.Vehicles) return IsVehicleOwned(item.payload);
            if (item.category == ShopCategory.NoAds) return ads != null && ads.AdsRemoved;
            if (item.id == "pack_full") return IsVehicleOwned("bus207") && IsVehicleOwned("taxi_premium") && IsVehicleOwned("taxi")
                                               && IsVehicleOwned("wewa_upgraded") && ads != null && ads.AdsRemoved;
            if (item.iapKind == ProductKind.Consumable || string.IsNullOrEmpty(item.payload)) return false;   // consommables : rachetables à volonté
            return IsPayloadOwned(item.payload);
        }

        private static bool IsYellowPayload(string payload)
        {
            for (int i = 0; i < VehicleColorManager.YellowCount; i++)
                if (payload == ShopCatalog.ColorPayload(VehicleColorManager.Defaults[i].hex)) return true;
            return false;
        }

        // ---------- Achats d'articles ----------

        public Outcome BuyWithCoins(ShopItem item)
        {
            if (item == null || item.priceCoins <= 0) return Outcome.No("Cet article ne s'achète pas avec des Kin Coins.");
            if (IsOwned(item)) return Outcome.No("Tu possèdes déjà cet article.");
            var eco = EconomyManager.Instance;
            if (eco == null) return Outcome.No("Économie indisponible.");
            if (!eco.TrySpendCoins(item.priceCoins))
                return Outcome.No($"Il te manque {item.priceCoins - eco.KinCoins} Kin Coins. Gagne-en en jouant des missions !");
            return Say(Outcome.Ok(Grant(item)));
        }

        public Outcome BuyWithGold(ShopItem item)
        {
            if (item == null || item.priceGold <= 0) return Outcome.No("Cet article ne s'achète pas avec du Kin Gold.");
            if (IsOwned(item)) return Outcome.No("Tu possèdes déjà cet article.");
            var eco = EconomyManager.Instance;
            if (eco == null) return Outcome.No("Économie indisponible.");
            if (!eco.TrySpendGold(item.priceGold))
                return Outcome.No($"Il te manque {item.priceGold - eco.KinGold} Kin Gold. Le Kin Gold est facultatif : tout se gagne aussi en jouant.");
            return Say(Outcome.Ok(Grant(item)));
        }

        /// <summary>Lance l'achat en argent réel ; le résultat arrive par OnMessage.</summary>
        public void BuyWithMoney(ShopItem item)
        {
            if (item == null || string.IsNullOrEmpty(item.iapId) || purchases == null) { Say(Outcome.No("Achat indisponible.")); return; }
            if (IsOwned(item)) { Say(Outcome.No("Tu possèdes déjà cet article.")); return; }
            purchases.Purchase(item.iapId);
        }

        private void OnMoneyPurchase(string productId)
        {
            var item = ShopCatalog.FindByIap(productId);
            if (item != null) Say(Outcome.Ok(Grant(item)));
        }

        private void OnMoneyFailed(string productId, string reason) => Say(Outcome.No(reason));

        /// <summary>Accorde l'article (idempotent pour les non consommables). Retourne un message de confirmation.</summary>
        private string Grant(ShopItem item)
        {
            var eco = EconomyManager.Instance;
            var profile = ProfileManager.Instance;

            switch (item.category)
            {
                case ShopCategory.Vehicles:
                    UnlockVehicle(item.payload);
                    break;
                case ShopCategory.Skins:
                    profile?.Unlock(item.payload);
                    ApplySkin(item.payload);
                    break;
                case ShopCategory.NoAds:
                    ads?.SetAdsRemoved(true);
                    break;
                case ShopCategory.Gold:
                    eco?.AddGold(item.amount);
                    break;
                case ShopCategory.Fuel:
                    if (SaveManager.Instance != null) { SaveManager.Instance.Data.fuelTokens += item.amount; SaveManager.Instance.Save(); }
                    break;
                case ShopCategory.Packs:
                    if (item.id == "pack_full")
                    {
                        foreach (var v in AllVehicles) UnlockVehicle(v);
                        ads?.SetAdsRemoved(true);
                        profile?.Unlock("sticker_kin_exclusive");
                    }
                    else if (item.id == "bonus_double") eco?.AddDoubleCoinsBonus(item.amount);
                    break;
                default:   // couleurs, jantes, autocollants
                    profile?.Unlock(item.payload);
                    break;
            }
            OnChanged?.Invoke();
            return "Merci ! " + item.title + " : ajouté.";
        }

        private void OnMissionUnlock(string id)
        {
            // Déblocages en jouant (récompenses de missions)
            if (id == "Taxi") UnlockVehicle("taxi");
            else if (id == "Bus207") UnlockVehicle("bus207");
        }

        private void ApplySkin(string skinId)
        {
            if (player == null) return;
            foreach (var s in ShopCatalog.Skins)
            {
                if (s.id != skinId) continue;
                var v = player.GetVehicle(s.kind);
                var c = v != null ? v.GetComponent<VehicleCustomizer>() : null;
                if (c == null) return;
                ColorUtility.TryParseHtmlString(s.body, out var body);
                ColorUtility.TryParseHtmlString(s.roof, out var roof);
                c.SetBodyColor(body); c.SetRoofColor(roof); c.SetRims(s.rim); c.SetSticker(s.sticker);
                return;
            }
        }

        // ---------- Services du garage (Kin Coins) ----------

        private static float RepairMultiplier(VehicleType k) => k == VehicleType.Wewa ? 0.6f : k == VehicleType.Bus207 ? 1.8f : 1f;

        public int RepairCost(VehicleController v) => v == null ? 0 : Mathf.CeilToInt(v.Damage * repairPricePerPercent * RepairMultiplier(v.Kind));
        public int FuelCost(VehicleController v) => v == null ? 0 : Mathf.CeilToInt((v.Stats.fuelCapacity - v.Fuel) * fuelPricePerLiter);
        public int FuelTokens => SaveManager.Instance != null ? SaveManager.Instance.Data.fuelTokens : 0;

        public Outcome Repair(VehicleController v)
        {
            if (v == null) return Outcome.No("Aucun véhicule.");
            int cost = RepairCost(v);
            if (cost <= 0) return Say(Outcome.No("Aucune réparation nécessaire."));
            if (!Pay(cost, out var failMsg)) return Say(Outcome.No(failMsg));
            v.Repair(100f);
            v.GetComponent<VehicleCustomizer>()?.SaveCondition();
            return Say(Outcome.Ok($"Véhicule réparé ({cost} Kin Coins)."));
        }

        public Outcome Refuel(VehicleController v)
        {
            if (v == null) return Outcome.No("Aucun véhicule.");
            if (v.Fuel >= v.Stats.fuelCapacity - 0.05f) return Say(Outcome.No("Le réservoir est déjà plein."));
            var sm = SaveManager.Instance;
            if (sm != null && sm.Data.fuelTokens > 0)
            {
                sm.Data.fuelTokens--; sm.Save();
                v.Refuel(v.Stats.fuelCapacity);
                v.GetComponent<VehicleCustomizer>()?.SaveCondition();
                return Say(Outcome.Ok("Plein gratuit utilisé."));
            }
            int cost = FuelCost(v);
            if (!Pay(cost, out var failMsg)) return Say(Outcome.No(failMsg));
            v.Refuel(v.Stats.fuelCapacity);
            v.GetComponent<VehicleCustomizer>()?.SaveCondition();
            return Say(Outcome.Ok($"Plein effectué ({cost} Kin Coins)."));
        }

        public int UpgradeCost(int currentLevel) =>
            currentLevel >= 0 && currentLevel < ShopCatalog.UpgradeCoins.Length ? ShopCatalog.UpgradeCoins[currentLevel] : -1;

        public Outcome BuyUpgrade(VehicleCustomizer c, UpgradeType type)
        {
            if (c == null) return Outcome.No("Aucun véhicule.");
            int level = c.GetUpgrade(type);
            int cost = UpgradeCost(level);
            if (cost < 0) return Say(Outcome.No("Niveau maximum atteint."));
            if (!Pay(cost, out var failMsg)) return Say(Outcome.No(failMsg));
            c.SetUpgrade(type, level + 1);
            return Say(Outcome.Ok($"Amélioration achetée : niveau {level + 1}/3 ({cost} Kin Coins)."));
        }

        /// <summary>Dépense libre (ex. changement de plaque).</summary>
        public Outcome Spend(int coins, string what)
        {
            if (!Pay(coins, out var failMsg)) return Say(Outcome.No(failMsg));
            return Say(Outcome.Ok($"{what} ({coins} Kin Coins)."));
        }

        private bool Pay(int coins, out string failMessage)
        {
            failMessage = null;
            var eco = EconomyManager.Instance;
            if (eco == null) { failMessage = "Économie indisponible."; return false; }
            if (eco.TrySpendCoins(coins)) return true;
            failMessage = $"Il te manque {coins - eco.KinCoins} Kin Coins. Gagne-en en jouant des missions !";
            return false;
        }

        private Outcome Say(Outcome o) { OnMessage?.Invoke(o.message); return o; }
    }
}

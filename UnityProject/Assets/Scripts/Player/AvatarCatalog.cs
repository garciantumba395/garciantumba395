using System.Collections.Generic;
using UnityEngine;

namespace TaxiKin.Player
{
    /// <summary>
    /// Catalogue des vêtements. Ne charge que les FICHES (quelques octets chacune) depuis Resources/Clothing/Items ;
    /// les prefabs 3D sont chargés individuellement par AvatarCustomizer, jamais tous en même temps.
    /// </summary>
    public static class AvatarCatalog
    {
        private static List<ClothingItem> all;
        private static Dictionary<string, ClothingItem> byId;

        /// <summary>Tenue prédéfinie (bouton « Tenues »).</summary>
        public struct Outfit { public string name, top, bottom, shoes; public int colorIndex; }

        public static readonly Color[] SkinTones =
        {
            Hex("#F1C8A5"), Hex("#D9A072"), Hex("#A8693F"), Hex("#8A5230"), Hex("#6B3E22"), Hex("#4A2A17"), Hex("#2F1B0F"),
        };

        // La couleur 0 est le jaune Taxi Kin #FFD400
        public static readonly Color[] ClothingColors =
        {
            Hex("#FFD400"), Hex("#F2F2F2"), Hex("#1A1A1A"), Hex("#C62828"), Hex("#1E5AA8"),
            Hex("#2E7D32"), Hex("#EF6C00"), Hex("#8A8F98"), Hex("#6A1B9A"), Hex("#5D4037"),
        };

        public static readonly Outfit[] Outfits =
        {
            new Outfit { name = "Décontracté",   top = "top_tshirt", bottom = "bottom_jeans",     shoes = "shoes_sneakers", colorIndex = 1 },
            new Outfit { name = "Chauffeur",     top = "top_shirt",  bottom = "bottom_trousers",  shoes = "shoes_boots",    colorIndex = 2 },
            new Outfit { name = "Taxi Kin",      top = "top_jacket", bottom = "bottom_trousers",  shoes = "shoes_sneakers", colorIndex = 0 },
            new Outfit { name = "Été",           top = "top_tshirt", bottom = "bottom_shorts",    shoes = "shoes_sandals",  colorIndex = 6 },
            new Outfit { name = "Jaune de départ", top = "top_yellow_start", bottom = "bottom_yellow_start", shoes = "shoes_sneakers", colorIndex = 0 },
        };

        private static void Ensure()
        {
            if (all != null) return;
            all = new List<ClothingItem>(Resources.LoadAll<ClothingItem>("Clothing/Items"));
            byId = new Dictionary<string, ClothingItem>();
            foreach (var i in all) if (i != null && !string.IsNullOrEmpty(i.id)) byId[i.id] = i;
        }

        public static IReadOnlyList<ClothingItem> All { get { Ensure(); return all; } }

        public static ClothingItem Get(string id)
        {
            Ensure();
            return !string.IsNullOrEmpty(id) && byId.TryGetValue(id, out var item) ? item : null;
        }

        public static List<ClothingItem> ForSlot(ClothingSlot slot)
        {
            Ensure();
            var list = new List<ClothingItem>();
            foreach (var i in all) if (i != null && i.slot == slot) list.Add(i);
            return list;
        }

        private static Color Hex(string hex) { ColorUtility.TryParseHtmlString(hex, out var c); return c; }
    }
}

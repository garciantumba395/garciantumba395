using System.Collections.Generic;
using UnityEngine;

namespace TaxiKin.Vehicles
{
    /// <summary>Une couleur nommée de la palette.</summary>
    [System.Serializable]
    public struct NamedColor
    {
        public string name;
        public string hex;
        public Color ToColor() => ColorUtility.TryParseHtmlString(hex, out var c) ? c : Color.magenta;
    }

    /// <summary>
    /// Palette de couleurs des véhicules. Le jaune Taxi Kin #FFD400 reste la couleur principale des taxis.
    /// </summary>
    public class VehicleColorManager : MonoBehaviour
    {
        public const string DefaultTaxiHex = "#FFD400";
        public static Color DefaultTaxiColor
        {
            get { ColorUtility.TryParseHtmlString(DefaultTaxiHex, out var c); return c; }
        }

        /// <summary>Palette d'origine (partagée avec ShopCatalog pour les prix).</summary>
        public static readonly NamedColor[] Defaults =
        {
            new NamedColor { name = "Jaune Taxi Kin", hex = "#FFD400" },
            new NamedColor { name = "Jaune vif",      hex = "#FFC300" },
            new NamedColor { name = "Jaune-or",       hex = "#E6A700" },
            new NamedColor { name = "Jaune néon",     hex = "#FFE600" },
            new NamedColor { name = "Jaune sale",     hex = "#D9B300" },
            // Autres couleurs au choix : le jaune reste la couleur principale des taxis (les 5 premières entrées)
            new NamedColor { name = "Blanc",  hex = "#F2F2F2" },
            new NamedColor { name = "Noir",   hex = "#1A1A1A" },
            new NamedColor { name = "Rouge",  hex = "#C62828" },
            new NamedColor { name = "Bleu",   hex = "#1E5AA8" },
            new NamedColor { name = "Vert",   hex = "#2E7D32" },
            new NamedColor { name = "Gris",   hex = "#8A8F98" },
            new NamedColor { name = "Orange", hex = "#EF6C00" },
        };

        [SerializeField] private List<NamedColor> palette = new List<NamedColor>(Defaults);

        /// <summary>Nombre de jaunes en tête de palette.</summary>
        public const int YellowCount = 5;

        public IReadOnlyList<NamedColor> Palette => palette;

        public Color GetColor(int index) =>
            index >= 0 && index < palette.Count ? palette[index].ToColor() : DefaultTaxiColor;
    }
}

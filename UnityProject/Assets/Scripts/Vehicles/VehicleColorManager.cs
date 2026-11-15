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

        [SerializeField] private List<NamedColor> palette = new List<NamedColor>
        {
            new NamedColor { name = "Jaune Taxi Kin", hex = "#FFD400" },
            new NamedColor { name = "Jaune vif",      hex = "#FFC300" },
            new NamedColor { name = "Jaune-or",       hex = "#E6A700" },
            new NamedColor { name = "Jaune néon",     hex = "#FFE600" },
            new NamedColor { name = "Jaune sale",     hex = "#D9B300" },
        };

        public IReadOnlyList<NamedColor> Palette => palette;

        public Color GetColor(int index) =>
            index >= 0 && index < palette.Count ? palette[index].ToColor() : DefaultTaxiColor;
    }
}

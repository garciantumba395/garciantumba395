using UnityEngine;
using UnityEngine.UI;
using TaxiKin.Player;
using TaxiKin.Vehicles;

namespace TaxiKin.UI
{
    /// <summary>
    /// Panneau de personnalisation du véhicule actif : couleur de carrosserie et de toit, jantes,
    /// autocollants, plaque et améliorations. Les boutons appellent ces méthodes (OnClick).
    /// Étape 4 : les changements sont gratuits (test) ; les prix et la boutique arrivent à l'Étape 5.
    /// </summary>
    public class GarageUIManager : MonoBehaviour
    {
        [SerializeField] private GameObject panel;
        [SerializeField] private PlayerController player;
        [SerializeField] private VehicleColorManager colors;
        [SerializeField] private InputField plateField;
        [SerializeField] private Text plateMessage;
        [SerializeField] private Text[] upgradeLabels = new Text[4];   // Moteur, Freins, Pneus, Carrosserie

        // Identifiants des autocollants (le dernier est exclusif : récompense de mission)
        private static readonly string[] StickerIds = { "", "sticker_kin", "sticker_stripes", "sticker_kin_exclusive" };
        private static readonly string[] UpgradeNames = { "Moteur", "Freins", "Pneus", "Carrosserie" };

        private VehicleCustomizer Cust => player != null && player.Vehicle != null ? player.Vehicle.GetComponent<VehicleCustomizer>() : null;

        public void Open()
        {
            panel.SetActive(true);
            var c = Cust;
            if (plateField != null && c != null) plateField.text = c.Current.plate;
            RefreshUpgrades();
        }

        public void Close() => panel.SetActive(false);

        public void OnBodyColor(int paletteIndex) { var c = Cust; if (c != null && colors != null) c.SetBodyColor(colors.GetColor(paletteIndex)); }
        public void OnRoofColor(int paletteIndex) { var c = Cust; if (c != null && colors != null) c.SetRoofColor(colors.GetColor(paletteIndex)); }
        public void OnRims(int index) => Cust?.SetRims(index);

        public void OnSticker(int index)
        {
            var c = Cust;
            if (c == null || index < 0 || index >= StickerIds.Length) return;
            string id = StickerIds[index];
            // L'autocollant exclusif n'est disponible qu'une fois gagné
            if (id == "sticker_kin_exclusive" && !(ProfileManager.Instance != null && ProfileManager.Instance.Owns(id)))
            {
                if (plateMessage != null) plateMessage.text = "Autocollant exclusif : termine la mission « Le jaune de Kin ».";
                return;
            }
            c.SetSticker(id);
        }

        public void OnApplyPlate()
        {
            var c = Cust;
            if (c == null || plateField == null) return;
            bool ok = c.TrySetPlate(plateField.text, out var error);
            if (plateMessage != null) plateMessage.text = ok ? "Plaque enregistrée." : error;
            if (ok) plateField.text = c.Current.plate;
        }

        public void OnUpgradeUp(int type) => ChangeUpgrade(type, +1);
        public void OnUpgradeDown(int type) => ChangeUpgrade(type, -1);

        private void ChangeUpgrade(int type, int delta)
        {
            var c = Cust;
            if (c == null) return;
            var t = (UpgradeType)type;
            c.SetUpgrade(t, c.GetUpgrade(t) + delta);
            RefreshUpgrades();
        }

        private void RefreshUpgrades()
        {
            var c = Cust;
            if (c == null || upgradeLabels == null) return;
            for (int i = 0; i < upgradeLabels.Length && i < UpgradeNames.Length; i++)
                if (upgradeLabels[i] != null) upgradeLabels[i].text = $"{UpgradeNames[i]}  {c.GetUpgrade((UpgradeType)i)}/3";
        }
    }
}

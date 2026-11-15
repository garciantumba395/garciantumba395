using System;
using System.Collections.Generic;
using UnityEngine;
using TaxiKin.Player;
using TaxiKin.SaveSystem;

namespace TaxiKin.Vehicles
{
    /// <summary>Filtre des plaques personnalisées : 3 à 10 caractères A-Z, 0-9, espace, tiret.</summary>
    public static class PlateFilter
    {
        public static bool TryNormalize(string raw, out string plate, out string error)
        {
            plate = (raw ?? string.Empty).Trim().ToUpperInvariant();
            error = null;
            if (plate.Length < 3 || plate.Length > 10) { error = "La plaque doit faire 3 à 10 caractères."; return false; }
            foreach (char c in plate)
                if (!((c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9') || c == ' ' || c == '-')) { error = "Caractères autorisés : A-Z, 0-9, espace, tiret."; return false; }
            if (NameFilter.ContainsBanned(plate)) { error = "Cette plaque n'est pas autorisée."; return false; }
            return true;
        }
    }

    public enum UpgradeType { Engine, Brakes, Tires, Armor }

    /// <summary>
    /// Personnalisation visuelle et mécanique d'un véhicule : carrosserie, toit, jantes, autocollants,
    /// plaque, améliorations. Sauvegardée automatiquement par véhicule (clé = type de véhicule).
    /// Matériaux partagés + MaterialPropertyBlock : aucune copie de matériau en mémoire.
    /// </summary>
    public class VehicleCustomizer : MonoBehaviour
    {
        [Serializable] public struct StickerEntry { public string id; public GameObject visual; }

        [SerializeField] private VehicleController vehicle;
        [Tooltip("Type du véhicule quand il n'y a pas de contrôleur (copie d'aperçu du garage)")]
        [SerializeField] private VehicleType previewKind;
        [SerializeField] private Renderer bodyRenderer;
        [SerializeField] private Renderer roofRenderer;
        [SerializeField] private Renderer[] rimRenderers;
        [SerializeField] private List<StickerEntry> stickers = new List<StickerEntry>();
        [SerializeField] private TextMesh plateText;

        private static readonly int BaseColorId = Shader.PropertyToID("_BaseColor");   // URP
        private static readonly int ColorId = Shader.PropertyToID("_Color");           // Built-in
        // Jantes : standard (gris), chrome (clair), or (jaune-or)
        private static readonly Color[] RimColors =
            { new Color(0.35f, 0.35f, 0.38f), new Color(0.85f, 0.87f, 0.9f), new Color(0.9f, 0.65f, 0f) };

        private static readonly List<VehicleCustomizer> Active = new List<VehicleCustomizer>();

        private MaterialPropertyBlock block;
        private bool conditionLoaded;

        public VehicleCustomization Current { get; private set; }
        public VehicleType Kind => vehicle != null ? vehicle.Kind : previewKind;
        public string Key => Kind.ToString();
        /// <summary>Niveaux gratuits ajoutés par la variante (Wewa amélioré, Taxi premium : +1).</summary>
        public int VariantBonus { get; private set; }
        public bool HasYellowBody => IsYellow(Current.bodyColor);

        /// <summary>Déclenché après chaque changement (sauvegarde, missions de personnalisation).</summary>
        public event Action<VehicleCustomization> OnChanged;

        private void Awake()
        {
            Current = DefaultFor(Kind);
            SaveManager.ProgressReset += OnProgressReset;   // abonné même quand le véhicule est désactivé
        }

        private void OnDestroy() => SaveManager.ProgressReset -= OnProgressReset;

        /// <summary>Réinitialisation : couleurs d'origine, aucune amélioration, plein et véhicule neuf.</summary>
        private void OnProgressReset()
        {
            Current = DefaultFor(Kind);
            VariantBonus = 0;
            conditionLoaded = false;
            if (vehicle != null) vehicle.SetCondition(vehicle.Stats.fuelCapacity, 0f);
            ApplyAll();
        }

        private void OnEnable()
        {
            Active.Add(this);
            LoadFromSave();      // se remet à jour à chaque activation (les copies d'aperçu et les véhicules désactivés)
        }

        private void OnDisable() => Active.Remove(this);

        private void Start() => LoadFromSave();

        private void LoadFromSave()
        {
            var sm = SaveManager.Instance;
            bool found = false;
            if (sm != null)
                foreach (var e in sm.Data.vehicleCustomizations)
                {
                    if (e.vehicleKey != Key || e.data == null) continue;
                    found = true;
                    Current = e.data;
                    // L'état (carburant, dommages) n'est restauré qu'une fois, pour le vrai véhicule
                    if (!conditionLoaded && vehicle != null && e.fuel >= 0f) { vehicle.SetCondition(e.fuel, e.damage); }
                    break;
                }
            if (sm != null && !found) Current = DefaultFor(Kind);
            if (vehicle != null) conditionLoaded = true;
            ApplyAll();
        }

        public void SetVariantBonus(int bonus) { VariantBonus = Mathf.Clamp(bonus, 0, 3); ApplyAll(); }

        private void OnApplicationPause(bool paused) { if (paused) SaveCondition(); }
        private void OnApplicationQuit() => SaveCondition();

        /// <summary>Enregistre carburant et dommages du véhicule (garage, fin de mission, mise en pause).</summary>
        public void SaveCondition()
        {
            var sm = SaveManager.Instance;
            if (sm == null || vehicle == null) return;
            VehicleCustomizationEntry entry = null;
            foreach (var e in sm.Data.vehicleCustomizations) if (e.vehicleKey == Key) { entry = e; break; }
            if (entry == null) { entry = new VehicleCustomizationEntry { vehicleKey = Key, data = Current }; sm.Data.vehicleCustomizations.Add(entry); }
            entry.fuel = vehicle.Fuel;
            entry.damage = vehicle.Damage;
            sm.Save();
        }

        /// <summary>Enregistre l'état de tous les véhicules actifs (appelé en fin de mission).</summary>
        public static void SaveAllConditions() { foreach (var c in Active) c.SaveCondition(); }

        /// <summary>Couleurs d'origine : taxi jaune Taxi Kin, Wewa rouge, bus bleu.</summary>
        private static VehicleCustomization DefaultFor(VehicleType kind)
        {
            var c = new VehicleCustomization();
            if (kind == VehicleType.Wewa) { c.bodyColor = c.roofColor = new Color(0.85f, 0.23f, 0.15f); c.plate = "WEWA 01"; }
            else if (kind == VehicleType.Bus207) { c.bodyColor = c.roofColor = new Color(0.2f, 0.4f, 0.8f); c.plate = "207 KIN"; }
            return c;
        }

        // ---------- Modifications (chaque appel applique, sauvegarde et notifie) ----------

        public void SetBodyColor(Color color) { Current.bodyColor = color; Changed(); }
        public void SetRoofColor(Color color) { Current.roofColor = color; Changed(); }
        public void SetRims(int index) { Current.rimIndex = Mathf.Clamp(index, 0, RimColors.Length - 1); Changed(); }
        public void SetSticker(string id) { Current.stickerId = id ?? ""; Changed(); }

        public bool TrySetPlate(string raw, out string error)
        {
            if (!PlateFilter.TryNormalize(raw, out var plate, out error)) return false;
            Current.plate = plate;
            Changed();
            return true;
        }

        public int GetUpgrade(UpgradeType t)
        {
            switch (t) { case UpgradeType.Engine: return Current.engine; case UpgradeType.Brakes: return Current.brakes; case UpgradeType.Tires: return Current.tires; default: return Current.armor; }
        }

        public void SetUpgrade(UpgradeType t, int level)
        {
            level = Mathf.Clamp(level, 0, 3);
            switch (t) { case UpgradeType.Engine: Current.engine = level; break; case UpgradeType.Brakes: Current.brakes = level; break; case UpgradeType.Tires: Current.tires = level; break; default: Current.armor = level; break; }
            Changed();
        }

        private void Changed()
        {
            ApplyAll();
            foreach (var other in Active)      // l'aperçu du garage et le véhicule du monde restent identiques
                if (other != this && other.Key == Key) { other.Current = Current.Clone(); other.ApplyAll(); }
            Persist();
            OnChanged?.Invoke(Current);
        }

        // ---------- Application visuelle ----------

        private void ApplyAll()
        {
            Tint(bodyRenderer, Current.bodyColor);
            Tint(roofRenderer, Current.roofColor);
            if (rimRenderers != null) foreach (var r in rimRenderers) Tint(r, RimColors[Mathf.Clamp(Current.rimIndex, 0, RimColors.Length - 1)]);
            foreach (var s in stickers) if (s.visual != null) s.visual.SetActive(s.id == Current.stickerId);
            if (plateText != null) plateText.text = Current.plate;
            if (vehicle != null)
                vehicle.SetUpgrades(Mathf.Min(3, Current.engine + VariantBonus), Mathf.Min(3, Current.brakes + VariantBonus),
                                    Mathf.Min(3, Current.tires + VariantBonus), Mathf.Min(3, Current.armor + VariantBonus));
        }

        private void Tint(Renderer target, Color color)
        {
            if (target == null) return;
            block ??= new MaterialPropertyBlock();
            target.GetPropertyBlock(block);
            block.SetColor(BaseColorId, color);
            block.SetColor(ColorId, color);
            target.SetPropertyBlock(block);
        }

        private void Persist()
        {
            var sm = SaveManager.Instance;
            if (sm == null) return;
            var list = sm.Data.vehicleCustomizations;
            foreach (var e in list) if (e.vehicleKey == Key) { e.data = Current; sm.Save(); return; }
            list.Add(new VehicleCustomizationEntry { vehicleKey = Key, data = Current, fuel = vehicle != null ? vehicle.Fuel : -1f, damage = vehicle != null ? vehicle.Damage : 0f });
            sm.Save();   // Sauvegarde automatique après chaque changement de personnalisation
        }

        /// <summary>Vrai si la couleur est l'un des 5 jaunes de la palette Taxi Kin.</summary>
        public static bool IsYellow(Color c)
        {
            string[] yellows = { "#FFD400", "#FFC300", "#E6A700", "#FFE600", "#D9B300" };
            foreach (var h in yellows)
            {
                ColorUtility.TryParseHtmlString(h, out var y);
                if (Mathf.Abs(c.r - y.r) < 0.02f && Mathf.Abs(c.g - y.g) < 0.02f && Mathf.Abs(c.b - y.b) < 0.02f) return true;
            }
            return false;
        }
    }
}

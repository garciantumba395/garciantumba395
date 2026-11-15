using System;
using System.Collections.Generic;
using UnityEngine;
using TaxiKin.Economy;
using TaxiKin.Player;
using TaxiKin.SaveSystem;

namespace TaxiKin.Vehicles
{
    /// <summary>Un véhicule proposé dans le garage (variantes comprises : Wewa amélioré, Taxi premium…).</summary>
    [Serializable]
    public class GarageVehicleEntry
    {
        public string id;                     // wewa_base, wewa_upgraded, taxi, taxi_premium, bus207
        public string title;
        public VehicleType type;
        [Tooltip("Chemin Resources du prefab d'aperçu (chargé seulement quand ce véhicule est affiché)")]
        public string previewResourcePath;
        public float previewScale = 1f;       // normalise la taille à l'écran (Wewa petit, bus grand)
        public int bonusLevel;                // niveaux d'amélioration offerts par la variante
        public bool unlockedByDefault;
    }

    /// <summary>
    /// Logique du garage : choix du véhicule et aperçu 3D. N'instancie QUE le véhicule affiché
    /// (jamais tous en même temps) pour économiser la mémoire.
    /// </summary>
    public class GarageManager : MonoBehaviour
    {
        [SerializeField] private List<GarageVehicleEntry> vehicles = new List<GarageVehicleEntry>();
        [SerializeField] private Transform previewAnchor;
        [SerializeField] private VehiclePreviewStage stage;
        [SerializeField] private PlayerController player;

        public int SelectedIndex { get; private set; }
        public GarageVehicleEntry Selected => SelectedIndex >= 0 && SelectedIndex < vehicles.Count ? vehicles[SelectedIndex] : null;
        public VehicleCustomizer PreviewCustomizer { get; private set; }

        public event Action<GarageVehicleEntry> OnVehicleChanged;

        private GameObject currentPreview;

        private void OnEnable() => SaveManager.ProgressReset += OnProgressReset;
        private void OnDisable() => SaveManager.ProgressReset -= OnProgressReset;

        /// <summary>Réinitialisation : retour au Wewa de base.</summary>
        private void OnProgressReset()
        {
            SelectedIndex = 0;
            if (vehicles.Count > 0) ApplyToWorld(vehicles[0]);
        }

        private void Start()
        {
            // Retrouve le véhicule utilisé à la dernière session
            var sm = SaveManager.Instance;
            string saved = sm != null ? sm.Data.selectedVehicle : null;
            for (int i = 0; i < vehicles.Count; i++)
                if (vehicles[i].id == saved && IsOwned(vehicles[i])) { SelectedIndex = i; ApplyToWorld(vehicles[i]); break; }
        }

        public bool IsOwned(GarageVehicleEntry e) =>
            e != null && (e.unlockedByDefault || (ShopManager.Instance != null && ShopManager.Instance.IsVehicleOwned(e.id)));

        public void Select(int index)
        {
            if (vehicles.Count == 0) return;
            SelectedIndex = (index % vehicles.Count + vehicles.Count) % vehicles.Count;
            ShowPreview(Selected);
            OnVehicleChanged?.Invoke(Selected);
        }

        public void Refresh() => Select(SelectedIndex);
        public void Next() => Select(SelectedIndex + 1);
        public void Previous() => Select(SelectedIndex - 1);

        public void SetPreviewActive(bool on) => stage?.SetActive(on);

        /// <summary>Utilise le véhicule affiché (seulement s'il est possédé).</summary>
        public bool UseSelected()
        {
            var e = Selected;
            if (!IsOwned(e)) return false;
            ApplyToWorld(e);
            var sm = SaveManager.Instance;
            if (sm != null) { sm.Data.selectedVehicle = e.id; sm.Save(); }
            return true;
        }

        private void ApplyToWorld(GarageVehicleEntry e)
        {
            if (player == null) return;
            player.SelectVehicle((int)e.type);
            var v = player.GetVehicle(e.type);
            var c = v != null ? v.GetComponent<VehicleCustomizer>() : null;
            c?.SetVariantBonus(e.bonusLevel);
        }

        private void ShowPreview(GarageVehicleEntry e)
        {
            if (currentPreview != null) Destroy(currentPreview);
            PreviewCustomizer = null;
            if (e == null || previewAnchor == null) return;
            var prefab = Resources.Load<GameObject>(e.previewResourcePath);   // chargé à la demande
            if (prefab == null) { Debug.LogWarning($"[Garage] Aperçu introuvable : {e.previewResourcePath}"); return; }
            currentPreview = Instantiate(prefab, previewAnchor);
            currentPreview.transform.localPosition = Vector3.zero;
            currentPreview.transform.localRotation = Quaternion.identity;
            currentPreview.transform.localScale = Vector3.one * e.previewScale;
            PreviewCustomizer = currentPreview.GetComponentInChildren<VehicleCustomizer>();
            stage?.ResetRotation();
        }

    }
}

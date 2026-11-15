using System;
using System.Collections.Generic;
using UnityEngine;

namespace TaxiKin.Vehicles
{
    /// <summary>Un véhicule proposé dans le garage.</summary>
    [Serializable]
    public class GarageVehicleEntry
    {
        public string id;                 // ex. "wewa_base"
        public VehicleType type;
        public GameObject previewPrefab;  // Prefab affiché dans l'aperçu 3D
        public bool unlockedByDefault;
    }

    /// <summary>
    /// Logique du garage : choix du véhicule et aperçu 3D.
    /// N'instancie QUE le véhicule affiché (jamais tous en même temps) pour économiser la mémoire.
    /// </summary>
    public class GarageManager : MonoBehaviour
    {
        [SerializeField] private List<GarageVehicleEntry> vehicles = new List<GarageVehicleEntry>();
        [SerializeField] private Transform previewAnchor;

        public int SelectedIndex { get; private set; }
        public GarageVehicleEntry Selected =>
            SelectedIndex >= 0 && SelectedIndex < vehicles.Count ? vehicles[SelectedIndex] : null;

        public event Action<GarageVehicleEntry> OnVehicleChanged;

        private GameObject currentPreview;

        public void Select(int index)
        {
            if (index < 0 || index >= vehicles.Count) return;
            SelectedIndex = index;
            ShowPreview(vehicles[index]);
            OnVehicleChanged?.Invoke(vehicles[index]);
        }

        public void Next() => Select((SelectedIndex + 1) % Mathf.Max(1, vehicles.Count));
        public void Previous() => Select((SelectedIndex - 1 + vehicles.Count) % Mathf.Max(1, vehicles.Count));

        private void ShowPreview(GarageVehicleEntry entry)
        {
            if (currentPreview != null) Destroy(currentPreview);
            if (entry.previewPrefab == null || previewAnchor == null) return;
            currentPreview = Instantiate(entry.previewPrefab, previewAnchor);
        }
    }
}

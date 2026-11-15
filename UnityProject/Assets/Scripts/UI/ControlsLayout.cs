using System;
using System.Collections.Generic;
using UnityEngine;
using TaxiKin.Player;
using TaxiKin.Vehicles;

namespace TaxiKin.UI
{
    /// <summary>Identifiant de chaque élément de commande à l'écran.</summary>
    public enum ControlId
    {
        Wheel, ArrowLeft, ArrowRight, Gas, Brake, HandBrake, Horn, Headlights, Wipers, Gear, Camera,
        PassengerAction,        // Wewa : un seul bouton « passager »
        Pickup, Dropoff,        // Taxi : prendre / déposer le client
        PickupAll, DropAll,     // Bus : prendre / déposer les passagers
        Doors, Stop             // Bus : portes et arrêt
    }

    /// <summary>
    /// Affiche uniquement les commandes utiles au véhicule actif et applique la taille des boutons.
    ///  - Wewa : simple. - Taxi : complet. - Bus 207 : complet + portes, arrêt, passagers.
    /// Le volant / les flèches dépendent aussi du mode de contrôle choisi (en inclinaison, aucun des deux).
    /// </summary>
    public class ControlsLayout : MonoBehaviour
    {
        [Serializable] private struct Entry { public ControlId id; public RectTransform rect; }

        [SerializeField] private PlayerController player;
        [SerializeField] private List<Entry> entries = new List<Entry>();

        private static readonly ControlId[] Common =
            { ControlId.Gas, ControlId.Brake, ControlId.HandBrake, ControlId.Horn, ControlId.Camera };

        private static readonly Dictionary<VehicleType, HashSet<ControlId>> PerVehicle =
            new Dictionary<VehicleType, HashSet<ControlId>>
            {
                [VehicleType.Wewa] = new HashSet<ControlId>(Common) { ControlId.PassengerAction },
                [VehicleType.Taxi] = new HashSet<ControlId>(Common)
                    { ControlId.Headlights, ControlId.Wipers, ControlId.Gear, ControlId.Pickup, ControlId.Dropoff },
                [VehicleType.Bus207] = new HashSet<ControlId>(Common)
                    { ControlId.Headlights, ControlId.Wipers, ControlId.Gear, ControlId.PickupAll, ControlId.DropAll,
                      ControlId.Doors, ControlId.Stop },
            };

        private VehicleType current = VehicleType.Wewa;

        /// <summary>Utilisé par l'outil de construction de l'interface (Editor).</summary>
        public void Register(ControlId id, RectTransform rect) => entries.Add(new Entry { id = id, rect = rect });

        private void OnEnable()
        {
            ControlSettings.Changed += Apply;
            if (player != null) player.OnVehicleChanged += OnVehicleChanged;
        }

        private void OnDisable()
        {
            ControlSettings.Changed -= Apply;
            if (player != null) player.OnVehicleChanged -= OnVehicleChanged;
        }

        private void OnVehicleChanged(VehicleController v) { current = v.Kind; Apply(); }

        public void Apply()
        {
            var allowed = PerVehicle[current];
            var mode = ControlSettings.Mode;
            float scale = ControlSettings.ButtonScale;

            foreach (var e in entries)
            {
                if (e.rect == null) continue;
                bool visible;
                switch (e.id)
                {
                    case ControlId.Wheel: visible = mode == ControlMode.Wheel; break;
                    case ControlId.ArrowLeft:
                    case ControlId.ArrowRight: visible = mode == ControlMode.Arrows; break;
                    default: visible = allowed.Contains(e.id); break;
                }
                e.rect.gameObject.SetActive(visible);
                e.rect.localScale = Vector3.one * scale;
            }
        }
    }
}

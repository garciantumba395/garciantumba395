using UnityEngine;
using UnityEngine.UI;
using TaxiKin.Core;
using TaxiKin.Missions;

namespace TaxiKin.UI
{
    /// <summary>Libellé du bouton « Passager » du Wewa : « Prendre le client » sans passager, « Déposer le client » avec.</summary>
    public class PassengerButtonLabel : MonoBehaviour
    {
        [SerializeField] private Text label;
        [SerializeField] private PassengerManager passengers;

        private void OnEnable()
        {
            if (passengers != null) passengers.OnPassengersChanged += OnChanged;
            Loc.Changed += Refresh;
            Refresh();
        }

        private void OnDisable()
        {
            if (passengers != null) passengers.OnPassengersChanged -= OnChanged;
            Loc.Changed -= Refresh;
        }

        private void Start() => Refresh();
        private void OnChanged(int _) => Refresh();

        private void Refresh()
        {
            if (label == null) return;
            label.text = Loc.T(passengers != null && passengers.OnBoard > 0 ? "dropoff" : "pickup");
        }
    }
}

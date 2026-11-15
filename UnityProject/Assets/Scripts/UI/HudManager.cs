using System.Text;
using UnityEngine;
using UnityEngine.UI;
using TaxiKin.Economy;
using TaxiKin.Missions;
using TaxiKin.Player;

namespace TaxiKin.UI
{
    /// <summary>
    /// Affichage du haut de l'écran : argent, passagers, carburant, dommages, chrono, distance, vitesse.
    /// Rafraîchi 5 fois par seconde seulement (et sans allocation inutile) pour ménager le CPU et la batterie.
    /// </summary>
    public class HudManager : MonoBehaviour
    {
        [SerializeField] private PlayerController player;
        [SerializeField] private PassengerManager passengers;
        [SerializeField] private MissionManager missions;
        [SerializeField] private Text money, passengersText, fuel, damage, timer, distance, speed;
        [SerializeField] private float refreshInterval = 0.2f;

        [SerializeField] private Transform destination;
        public Transform Destination { get => destination; set => destination = value; }

        private float next;
        private readonly StringBuilder sb = new StringBuilder(32);

        private void Update()
        {
            if (Time.unscaledTime < next) return;
            next = Time.unscaledTime + refreshInterval;
            Refresh();
        }

        private void Refresh()
        {
            var v = player != null ? player.Vehicle : null;
            if (v == null) return;

            if (money != null) money.text = (EconomyManager.Instance != null ? EconomyManager.Instance.KinCoins : 0) + " KC";
            if (passengersText != null && passengers != null) passengersText.text = passengers.OnBoard + "/" + passengers.Capacity;
            if (fuel != null) fuel.text = Mathf.RoundToInt(v.Fuel / Mathf.Max(0.01f, v.Stats.fuelCapacity) * 100f) + "%";
            if (damage != null) damage.text = Mathf.RoundToInt(v.Damage) + "%";
            if (speed != null) speed.text = Mathf.RoundToInt(v.SpeedKmh) + " km/h";

            if (distance != null)
                distance.text = Destination != null
                    ? Mathf.RoundToInt(Vector3.Distance(v.transform.position, Destination.position)) + " m" : "--";

            if (timer != null)
            {
                float t = 0f;
                if (missions != null && missions.IsRunning)
                    t = missions.Current.timeLimitSeconds > 0f ? missions.Current.timeLimitSeconds - missions.ElapsedSeconds : missions.ElapsedSeconds;
                t = Mathf.Max(0f, t);
                sb.Clear();
                sb.Append((int)(t / 60f)).Append(':').Append(((int)t % 60).ToString("00"));
                timer.text = sb.ToString();
            }
        }
    }
}

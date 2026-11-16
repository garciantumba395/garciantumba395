using UnityEngine;
using UnityEngine.UI;
using TaxiKin.Player;
using TaxiKin.Vehicles;

namespace TaxiKin.UI
{
    /// <summary>Met en évidence (jaune, plus grand) la vitesse engagée sur le levier P R N D.</summary>
    public class GearIndicator : MonoBehaviour
    {
        [SerializeField] private PlayerController player;
        [SerializeField] private Text[] labels;     // indexés par GearPosition : D, N, R, P

        private static readonly Color Active = new Color(1f, 0.83f, 0f);

        private void Update()
        {
            if (player == null || player.Vehicle == null || labels == null) return;
            int gear = (int)player.Vehicle.Gear;
            for (int i = 0; i < labels.Length; i++)
            {
                if (labels[i] == null) continue;
                bool on = i == gear;
                labels[i].color = on ? Active : Color.white;
                labels[i].fontStyle = on ? FontStyle.Bold : FontStyle.Normal;
            }
        }
    }
}

using UnityEngine;
using TaxiKin.Player;

namespace TaxiKin.UI
{
    /// <summary>Flèche à l'écran qui pointe vers la destination, relativement à l'orientation du véhicule.</summary>
    public class DirectionArrow : MonoBehaviour
    {
        [SerializeField] private PlayerController player;
        [SerializeField] private RectTransform arrow;
        [SerializeField] private Transform destination;

        public void SetDestination(Transform t) { destination = t; if (arrow != null) arrow.gameObject.SetActive(t != null); }

        private void Update()
        {
            if (arrow == null || destination == null || player == null || player.Vehicle == null) return;
            Transform v = player.Vehicle.transform;
            Vector3 to = destination.position - v.position; to.y = 0f;
            Vector3 fwd = v.forward; fwd.y = 0f;
            if (to.sqrMagnitude < 0.01f) return;
            float angle = Vector3.SignedAngle(fwd, to, Vector3.up);   // + = à droite
            arrow.localEulerAngles = new Vector3(0f, 0f, -angle);
        }
    }
}

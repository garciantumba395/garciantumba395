using UnityEngine;
using TaxiKin.Player;
using TaxiKin.UI;

namespace TaxiKin.Map
{
    /// <summary>
    /// Hors de la carte (le monde n'a de sol que sous les zones et les corridors) : si le véhicule tombe, il est remis
    /// sur la dernière position sûre, sans vitesse. Vérifié 5 fois par seconde.
    /// </summary>
    public class OutOfBoundsGuard : MonoBehaviour
    {
        [SerializeField] private PlayerController player;
        [SerializeField] private float fallLimit = -8f;

        private Vector3 lastSafe;
        private Quaternion lastRotation = Quaternion.identity;
        private bool hasSafe;
        private float nextCheck, nextRecord;

        private void Update()
        {
            if (Time.unscaledTime < nextCheck) return;
            nextCheck = Time.unscaledTime + 0.2f;

            var v = player != null ? player.Vehicle : null;
            if (v == null) return;
            Vector3 p = v.transform.position;

            if (p.y < fallLimit)
            {
                if (!hasSafe) return;
                v.ResetMotion(lastSafe + Vector3.up * 0.5f, lastRotation);
                ToastManager.Show("Retour sur la route.");
                return;
            }

            // Position sûre : on touche le sol (rayon vers le bas), enregistrée au plus une fois par seconde
            if (Time.unscaledTime >= nextRecord && p.y > -1f && Physics.Raycast(p + Vector3.up, Vector3.down, 3f))
            {
                lastSafe = p; lastRotation = v.transform.rotation; hasSafe = true;
                nextRecord = Time.unscaledTime + 1f;
            }
        }
    }
}

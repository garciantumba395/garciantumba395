using UnityEngine;

namespace TaxiKin.UI
{
    /// <summary>Outils de test (changer de véhicule, de mode de contrôle…) : désactivés dans les versions de production.</summary>
    public class DevOnly : MonoBehaviour
    {
        private void Awake()
        {
            if (!Debug.isDebugBuild && !Application.isEditor) gameObject.SetActive(false);
        }
    }
}

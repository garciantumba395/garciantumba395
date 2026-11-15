using UnityEngine;
using UnityEngine.UI;
using TaxiKin.Core;

namespace TaxiKin.UI
{
    /// <summary>Met à jour automatiquement un Text selon la langue choisie.</summary>
    [RequireComponent(typeof(Text))]
    public class LocalizedText : MonoBehaviour
    {
        [SerializeField] private string key;

        public string Key { get => key; set { key = value; Refresh(); } }

        private void OnEnable() { Loc.Changed += Refresh; Refresh(); }
        private void OnDisable() => Loc.Changed -= Refresh;

        private void Refresh()
        {
            var t = GetComponent<Text>();
            if (t != null && !string.IsNullOrEmpty(key)) t.text = Loc.T(key);
        }
    }
}

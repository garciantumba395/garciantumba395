using System;
using System.Collections.Generic;
using UnityEngine;

namespace TaxiKin.UI
{
    /// <summary>
    /// Affiche un seul panneau d'interface à la fois (menu, garage, HUD, paramètres…).
    /// Les panneaux sont enregistrés dans l'Inspector avec un identifiant texte.
    /// </summary>
    public class UIManager : MonoBehaviour
    {
        [Serializable]
        public struct PanelEntry { public string id; public GameObject panel; }

        [SerializeField] private List<PanelEntry> panels = new List<PanelEntry>();
        private string currentId;

        public void Show(string id)
        {
            foreach (var p in panels)
                if (p.panel != null) p.panel.SetActive(p.id == id);
            currentId = id;
        }

        public void HideAll() => Show(null);
        public string Current => currentId;
    }
}

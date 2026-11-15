using System.Collections.Generic;
using UnityEngine;
using UnityEngine.UI;
using TaxiKin.Core;
using TaxiKin.Player;

namespace TaxiKin.UI
{
    /// <summary>
    /// Écran « Mon chauffeur » : aperçu 3D (rotation au doigt), 6 onglets (visage, cheveux, haut, bas, chaussures,
    /// accessoires), boutons Couleur / Tenues / Style, couleur de peau, nom, Enregistrer / Annuler.
    /// Au premier lancement l'écran s'ouvre automatiquement et ne peut pas être annulé.
    /// </summary>
    public class CharacterCreatorUI : MonoBehaviour
    {
        [SerializeField] private CharacterCreatorManager creator;
        [SerializeField] private AvatarPreviewStage stage;
        [SerializeField] private GameObject panel;
        [SerializeField] private RectTransform itemsContainer;
        [SerializeField] private Button itemTemplate;             // Modèle désactivé, cloné pour chaque article
        [SerializeField] private InputField nameField;
        [SerializeField] private Text messageText;
        [SerializeField] private GameObject cancelButton;

        private static readonly Color Normal = new Color(0.2f, 0.2f, 0.24f, 0.95f);
        private static readonly Color Selected = new Color(0.15f, 0.55f, 0.25f, 0.95f);
        private static readonly Color Locked = new Color(0.12f, 0.12f, 0.12f, 0.8f);

        private readonly List<GameObject> spawned = new List<GameObject>();
        private ClothingSlot tab = ClothingSlot.Face;
        private bool firstLaunch;
        private GameState stateBefore = GameState.Playing;

        private void Start()
        {
            // Premier lancement : création obligatoire
            var p = ProfileManager.Instance;
            if (p != null && !p.HasProfile) Open(true);
        }

        public void OpenFromMenu() => Open(false);

        public void Open(bool first)
        {
            firstLaunch = first;
            var gm = GameManager.Instance;
            if (gm != null && gm.State != GameState.CharacterCreation) { stateBefore = gm.State; gm.SetState(GameState.CharacterCreation); }

            creator.Begin();
            stage.ResetRotation();
            stage.SetActive(true);
            panel.SetActive(true);
            if (cancelButton != null) cancelButton.SetActive(!first);
            nameField.text = creator.Draft.driverName;
            messageText.text = first ? "Bienvenue à Kinshasa ! Crée ton chauffeur." : "";
            RebuildItems();
        }

        private void Close()
        {
            stage.SetActive(false);
            panel.SetActive(false);
            GameManager.Instance?.SetState(stateBefore == GameState.Paused || stateBefore == GameState.CharacterCreation ? GameState.Playing : stateBefore);
        }

        // ---------- Boutons ----------
        /// <summary>0 = visage, 1 = cheveux, 2 = haut, 3 = bas, 4 = chaussures, 5 = accessoires.</summary>
        public void OnTab(int index) { tab = (ClothingSlot)Mathf.Clamp(index, 0, 5); RebuildItems(); }
        public void OnSkin(int index) => creator.SetSkin(index);
        public void OnColor() => creator.NextClothingColor();
        public void OnOutfit() { creator.NextOutfit(); RebuildItems(); }
        public void OnStyle() => creator.NextStyle();

        public void OnSave()
        {
            if (!creator.TrySetName(nameField.text, out var error)) { messageText.text = error; return; }
            if (!creator.Save(out error)) { messageText.text = error; return; }
            Close();
        }

        public void OnCancel()
        {
            if (firstLaunch) return;      // Impossible d'annuler la première création
            creator.Cancel();
            Close();
        }

        // ---------- Liste des articles de l'onglet ----------
        private void RebuildItems()
        {
            foreach (var go in spawned) Destroy(go);
            spawned.Clear();

            // Option « Aucun » pour les cheveux et les accessoires
            if (tab == ClothingSlot.Hair || tab == ClothingSlot.Accessory) AddButton(null, "Aucun", true);

            foreach (var item in AvatarCatalog.ForSlot(tab))
                AddButton(item, item.displayName, creator.IsOwned(item));
        }

        private void AddButton(ClothingItem item, string label, bool owned)
        {
            var btn = Instantiate(itemTemplate, itemsContainer);
            btn.gameObject.SetActive(true);
            string id = item != null ? item.id : "";
            bool isSelected = creator.GetItemId(tab) == id || (item == null && string.IsNullOrEmpty(creator.GetItemId(tab)));

            btn.GetComponentInChildren<Text>().text = owned ? label : label + " (verrouillé)";
            btn.GetComponent<Image>().color = !owned ? Locked : isSelected ? Selected : Normal;
            btn.interactable = owned;
            var slot = tab;
            btn.onClick.AddListener(() => { creator.SetItem(slot, id); RebuildItems(); });
            spawned.Add(btn.gameObject);
        }
    }
}

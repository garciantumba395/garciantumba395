using UnityEngine;
using UnityEngine.UI;

namespace TaxiKin.UI
{
    /// <summary>Une ligne de la boutique : titre, description, trois boutons de prix (Kin Coins, Kin Gold, argent réel) ou « Possédé ».</summary>
    public class ShopRow : MonoBehaviour
    {
        public Text title, description, owned;
        public Button coinsButton, goldButton, moneyButton;
        public Text coinsText, goldText, moneyText;
    }
}

using System;
using UnityEngine;

namespace TaxiKin.Missions
{
    /// <summary>Compte les passagers à bord et respecte la capacité du véhicule.</summary>
    public class PassengerManager : MonoBehaviour
    {
        public int OnBoard { get; private set; }
        public int Capacity { get; private set; } = 1;
        public int TotalTransported { get; private set; }

        public event Action<int> OnPassengersChanged;

        public void SetCapacity(int capacity) => Capacity = Mathf.Max(0, capacity);

        /// <summary>Embarque jusqu'à `count` passagers ; renvoie le nombre réellement monté.</summary>
        public int Pickup(int count)
        {
            int accepted = Mathf.Clamp(count, 0, Capacity - OnBoard);
            OnBoard += accepted;
            if (accepted > 0) OnPassengersChanged?.Invoke(OnBoard);
            return accepted;
        }

        /// <summary>Vide le véhicule sans compter les passagers (début de mission).</summary>
        public void Reset()
        {
            if (OnBoard == 0) return;
            OnBoard = 0;
            OnPassengersChanged?.Invoke(OnBoard);
        }

        /// <summary>Dépose tous les passagers ; renvoie leur nombre.</summary>
        public int DropAll()
        {
            int dropped = OnBoard;
            TotalTransported += dropped;
            OnBoard = 0;
            if (dropped > 0) OnPassengersChanged?.Invoke(OnBoard);
            return dropped;
        }
    }
}

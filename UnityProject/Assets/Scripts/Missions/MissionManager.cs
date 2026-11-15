using System;
using System.Collections.Generic;
using UnityEngine;

namespace TaxiKin.Missions
{
    /// <summary>Gère la mission en cours : démarrage, chronomètre, fin (succès/échec).</summary>
    public class MissionManager : MonoBehaviour
    {
        [SerializeField] private List<MissionData> missions = new List<MissionData>();

        public IReadOnlyList<MissionData> Missions => missions;
        public MissionData Current { get; private set; }
        public float ElapsedSeconds { get; private set; }
        public bool IsRunning { get; private set; }

        public event Action<MissionData> OnMissionStarted;
        public event Action<MissionData, bool> OnMissionEnded;   // (mission, succès)

        public void StartMission(MissionData mission)
        {
            if (mission == null) return;
            Current = mission;
            ElapsedSeconds = 0f;
            IsRunning = true;
            OnMissionStarted?.Invoke(mission);
        }

        public void EndMission(bool success)
        {
            if (!IsRunning) return;
            IsRunning = false;
            OnMissionEnded?.Invoke(Current, success);
        }

        private void Update()
        {
            if (!IsRunning) return;
            ElapsedSeconds += Time.deltaTime;
            if (Current.timeLimitSeconds > 0f && ElapsedSeconds >= Current.timeLimitSeconds)
                EndMission(false);   // Temps écoulé
        }
    }
}

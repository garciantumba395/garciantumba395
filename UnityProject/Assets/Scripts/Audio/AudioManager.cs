using UnityEngine;
using TaxiKin.Core;
using TaxiKin.Player;
using TaxiKin.Vehicles;

namespace TaxiKin.Audio
{
    /// <summary>
    /// Son et musique : deux curseurs de volume (réglages) appliqués à deux AudioSource.
    /// Aucun fichier audio n'est encore fourni : les clips (klaxon, musique) se glissent dans l'Inspector.
    /// Conseil Android : clips courts en « Decompress On Load » + Vorbis, musique en « Streaming », mono pour les bruitages.
    /// </summary>
    public class AudioManager : MonoBehaviour
    {
        public static AudioManager Instance { get; private set; }

        [SerializeField] private AudioSource musicSource;
        [SerializeField] private AudioSource sfxSource;
        [SerializeField] private PlayerController player;
        [SerializeField] private AudioClip hornClip;
        [SerializeField] private AudioClip musicClip;

        private VehicleController watched;

        private void Awake()
        {
            if (Instance != null && Instance != this) { Destroy(this); return; }
            Instance = this;
        }

        private void OnEnable()
        {
            GameSettings.Changed += ApplyVolumes;
            if (player != null) player.OnVehicleChanged += OnVehicleChanged;
        }

        private void OnDisable()
        {
            GameSettings.Changed -= ApplyVolumes;
            if (player != null) player.OnVehicleChanged -= OnVehicleChanged;
            if (watched != null) watched.OnHorn -= PlayHorn;
        }

        private void Start()
        {
            ApplyVolumes();
            if (musicSource != null && musicClip != null) { musicSource.clip = musicClip; musicSource.loop = true; musicSource.Play(); }
        }

        public void ApplyVolumes()
        {
            if (musicSource != null) musicSource.volume = GameSettings.MusicVolume;
            if (sfxSource != null) sfxSource.volume = GameSettings.SoundVolume;
        }

        public void PlaySfx(AudioClip clip, float volume = 1f)
        {
            if (clip != null && sfxSource != null) sfxSource.PlayOneShot(clip, volume);
        }

        private void OnVehicleChanged(VehicleController v)
        {
            if (watched != null) watched.OnHorn -= PlayHorn;
            watched = v;
            if (watched != null) watched.OnHorn += PlayHorn;
        }

        private void PlayHorn() => PlaySfx(hornClip);
    }
}

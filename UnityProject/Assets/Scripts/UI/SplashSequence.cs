using System.Collections;
using UnityEngine;
using UnityEngine.UI;

namespace TaxiKin.UI
{
    /// <summary>
    /// Écran de démarrage : logo des partenaires (s'il existe), puis logo Taxi Kin, avec fondu.
    /// Se met en place tout seul au lancement (aucun câblage dans la scène) et se supprime à la fin.
    /// Images attendues dans Assets/Resources/Branding/ : « logo_partenaires » (facultatif) et « logo_taxikin ».
    /// Touche / toucher : passe l'écran en cours.
    /// </summary>
    public class SplashSequence : MonoBehaviour
    {
        private const float ShowSeconds = 2.2f;
        private const float FadeSeconds = 0.5f;

        [RuntimeInitializeOnLoadMethod(RuntimeInitializeLoadType.AfterSceneLoad)]
        private static void AutoStart()
        {
            var partners = Resources.Load<Texture2D>("Branding/logo_partenaires");
            var game = Resources.Load<Texture2D>("Branding/logo_taxikin");
            if (partners == null && game == null) return;
            var go = new GameObject("SplashSequence");
            DontDestroyOnLoad(go);
            go.AddComponent<SplashSequence>().Begin(partners, game);
        }

        private CanvasGroup group;
        private RawImage image;
        private AspectRatioFitter fitter;

        private void Begin(Texture2D partners, Texture2D game) => StartCoroutine(Run(partners, game));

        private IEnumerator Run(Texture2D partners, Texture2D game)
        {
            BuildOverlay();
            if (partners != null) yield return Show(partners, Color.white);
            if (game != null) yield return Show(game, new Color(0.02f, 0.1f, 0.19f));
            Destroy(gameObject);
        }

        private void BuildOverlay()
        {
            var canvasGo = new GameObject("Canvas", typeof(Canvas), typeof(CanvasScaler), typeof(CanvasGroup));
            canvasGo.transform.SetParent(transform, false);
            var canvas = canvasGo.GetComponent<Canvas>();
            canvas.renderMode = RenderMode.ScreenSpaceOverlay;
            canvas.sortingOrder = 32000;                      // au-dessus de toute l'interface
            var scaler = canvasGo.GetComponent<CanvasScaler>();
            scaler.uiScaleMode = CanvasScaler.ScaleMode.ScaleWithScreenSize;
            scaler.referenceResolution = new Vector2(1920f, 1080f);
            scaler.matchWidthOrHeight = 0.5f;
            group = canvasGo.GetComponent<CanvasGroup>();

            var bg = new GameObject("Fond", typeof(RectTransform), typeof(Image));
            bg.transform.SetParent(canvasGo.transform, false);
            Stretch((RectTransform)bg.transform);
            bgImage = bg.GetComponent<Image>();

            var img = new GameObject("Logo", typeof(RectTransform), typeof(RawImage), typeof(AspectRatioFitter));
            img.transform.SetParent(canvasGo.transform, false);
            var rt = (RectTransform)img.transform;
            rt.anchorMin = new Vector2(0.15f, 0.15f); rt.anchorMax = new Vector2(0.85f, 0.85f);
            rt.offsetMin = rt.offsetMax = Vector2.zero;
            image = img.GetComponent<RawImage>();
            image.raycastTarget = false;
            fitter = img.GetComponent<AspectRatioFitter>();
            fitter.aspectMode = AspectRatioFitter.AspectMode.FitInParent;
        }

        private Image bgImage;

        private static void Stretch(RectTransform rt)
        {
            rt.anchorMin = Vector2.zero; rt.anchorMax = Vector2.one;
            rt.offsetMin = rt.offsetMax = Vector2.zero;
        }

        private IEnumerator Show(Texture2D tex, Color background)
        {
            image.texture = tex;
            fitter.aspectRatio = (float)tex.width / tex.height;
            bgImage.color = background;
            yield return Fade(0f, 1f);
            float t = 0f;
            while (t < ShowSeconds && !Input.anyKeyDown) { t += Time.unscaledDeltaTime; yield return null; }
            yield return Fade(1f, 0f);
        }

        private IEnumerator Fade(float from, float to)
        {
            for (float t = 0f; t < FadeSeconds; t += Time.unscaledDeltaTime)
            {
                group.alpha = Mathf.Lerp(from, to, t / FadeSeconds);
                yield return null;
            }
            group.alpha = to;
        }
    }
}

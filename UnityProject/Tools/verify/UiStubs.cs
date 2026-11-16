// SUBSTITUTS DE VÉRIFICATION UNIQUEMENT (hors dépôt) : reproduisent les noms de membres publics d'Unity UI 1.0
using UnityEngine.Events;
namespace UnityEngine.UI
{
    public class Graphic : MonoBehaviour { public Color color; public bool raycastTarget; }
    public class Text : Graphic
    {
        public string text; public Font font; public int fontSize; public TextAnchor alignment; public FontStyle fontStyle;
        public bool resizeTextForBestFit; public int resizeTextMinSize, resizeTextMaxSize; public HorizontalWrapMode horizontalOverflow; public bool supportRichText;
    }
    public class Image : Graphic { public Sprite sprite; }
    public class RawImage : Graphic { public Texture texture; }
    public class Shadow : MonoBehaviour { }
    public class GraphicRaycaster : MonoBehaviour { }
    public class Selectable : MonoBehaviour { public bool interactable; public Graphic targetGraphic; }
    public class Button : Selectable { public UnityEvent onClick = new UnityEvent(); }
    public class Slider : Selectable
    {
        public enum Direction { LeftToRight, RightToLeft, BottomToTop, TopToBottom }
        public float value, minValue, maxValue; public RectTransform fillRect, handleRect; public Direction direction;
        public UnityEvent<float> onValueChanged = new UnityEvent<float>();
        public void SetValueWithoutNotify(float v) { value = v; }
    }
    public class InputField : Selectable { public string text; public Text textComponent; public Graphic placeholder; public int characterLimit; }
    public class CanvasScaler : MonoBehaviour
    {
        public enum ScaleMode { ConstantPixelSize, ScaleWithScreenSize, ConstantPhysicalSize }
        public enum ScreenMatchMode { MatchWidthOrHeight, Expand, Shrink }
        public ScaleMode uiScaleMode; public Vector2 referenceResolution; public ScreenMatchMode screenMatchMode; public float matchWidthOrHeight;
    }
    public class LayoutGroup : MonoBehaviour { }
    public class GridLayoutGroup : LayoutGroup
    {
        public enum Constraint { Flexible, FixedColumnCount, FixedRowCount }
        public Vector2 cellSize, spacing; public Constraint constraint; public int constraintCount;
    }
    public class VerticalLayoutGroup : LayoutGroup { public float spacing; public bool childControlHeight, childControlWidth, childForceExpandHeight, childForceExpandWidth; }
    public class LayoutElement : MonoBehaviour { public float preferredHeight; }
}
namespace UnityEngine.EventSystems
{
    public class PointerEventData { public Vector2 position, delta; public Camera pressEventCamera; }
    public interface IPointerDownHandler { void OnPointerDown(PointerEventData e); }
    public interface IPointerUpHandler { void OnPointerUp(PointerEventData e); }
    public interface IPointerExitHandler { void OnPointerExit(PointerEventData e); }
    public interface IDragHandler { void OnDrag(PointerEventData e); }
    public class EventSystem : MonoBehaviour { }
    public class StandaloneInputModule : MonoBehaviour { }
}
namespace UnityEngine.UI
{
    public class AspectRatioFitter : MonoBehaviour
    {
        public enum AspectMode { None, WidthControlsHeight, HeightControlsWidth, FitInParent, EnvelopeParent }
        public AspectMode aspectMode; public float aspectRatio;
    }
}

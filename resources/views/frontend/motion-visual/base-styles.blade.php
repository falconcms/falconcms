{{--
    What every Motion Visual shares. Repeated per visual on purpose (it is short): a
    stylesheet linked from the footer would arrive after the layer and redraw it, and the
    builder renders its content twice per request, so "print once" flags cannot be trusted.

    Each item is three boxes, so the three kinds of movement never fight over one property:
      .falcon-mv__item   where it sits (left/top/width) and the pointer's parallax (transform)
      .falcon-mv__anim   its animation (translate / rotate / scale, or transform for orbit).
                         --mv-dist is the distance in px (float, drift, bounce, orbit);
                         --mv-d is the same number without a unit, for what is not a length:
                         pulse grows by --mv-d %, swing sweeps --mv-d degrees (pivot: top center)
      .falcon-mv__shape  how it looks (rotate, color, opacity, blur)
--}}
<style>
.falcon-mv-host:has(>.falcon-mv){position:relative}
.falcon-mv-host:has(>.falcon-mv)>:not(.falcon-mv):not(style):not(script){position:relative;z-index:1}
.falcon-mv{position:absolute;inset:0;overflow:hidden;pointer-events:none;border-radius:inherit;z-index:0;container-type:size}
.falcon-mv__item--text{white-space:pre;line-height:1.05;text-align:center}
.falcon-mv__item--text .falcon-mv__anim,.falcon-mv__item--text .falcon-mv__shape{width:auto}
.falcon-mv__text{display:block;font-style:normal;color:currentColor}
.falcon-mv__text--outline{color:transparent;-webkit-text-stroke:max(1px,.022em) var(--mv-c1)}
.falcon-mv__text--gradient{color:transparent;background:linear-gradient(100deg,var(--mv-c1),var(--mv-c2));-webkit-background-clip:text;background-clip:text}
.falcon-mv--front{z-index:2}
.falcon-mv__dots{position:absolute;inset:0;width:100%;height:100%;display:block}
.falcon-mv__item{position:absolute;translate:-50% -50%;will-change:transform}
.falcon-mv__anim{width:100%;animation-duration:var(--mv-dur,8s);animation-timing-function:ease-in-out;animation-iteration-count:infinite}
.falcon-mv__shape{width:100%}
.falcon-mv__shape svg{display:block;width:100%;height:auto;aspect-ratio:1;overflow:visible}
.falcon-mv__shape img{display:block;width:100%;height:auto;max-width:none;user-select:none;-webkit-user-drag:none}
.falcon-mv__anim--float{animation-name:fmv-float}
.falcon-mv__anim--drift{animation-name:fmv-drift}
.falcon-mv__anim--rotate{animation-name:fmv-rotate;animation-timing-function:linear}
.falcon-mv__anim--pulse{animation-name:fmv-pulse}
.falcon-mv__anim--swing{animation-name:fmv-swing;transform-origin:50% 0}
.falcon-mv__anim--orbit{animation-name:fmv-orbit;animation-timing-function:linear}
.falcon-mv__anim--bounce{animation-name:fmv-bounce;animation-timing-function:cubic-bezier(.3,0,.7,1)}
@keyframes fmv-float{0%,100%{translate:0 0}50%{translate:0 calc(var(--mv-dist) * -1)}}
@keyframes fmv-drift{0%,100%{translate:0 0}25%{translate:var(--mv-dist) calc(var(--mv-dist) * -.6)}50%{translate:calc(var(--mv-dist) * .4) calc(var(--mv-dist) * .5)}75%{translate:calc(var(--mv-dist) * -.7) calc(var(--mv-dist) * -.2)}}
@keyframes fmv-rotate{to{rotate:360deg}}
@keyframes fmv-pulse{0%,100%{scale:1}50%{scale:calc(1 + var(--mv-d) / 100)}}
@keyframes fmv-swing{0%,100%{rotate:calc(var(--mv-d) * -.5deg)}50%{rotate:calc(var(--mv-d) * .5deg)}}
@keyframes fmv-orbit{from{transform:rotate(0deg) translateX(var(--mv-dist)) rotate(0deg)}to{transform:rotate(360deg) translateX(var(--mv-dist)) rotate(-360deg)}}
@keyframes fmv-bounce{0%,100%{translate:0 0}45%{translate:0 calc(var(--mv-dist) * -1)}55%{translate:0 calc(var(--mv-dist) * -1)}}
@property --mv-rc{syntax:'<color>';inherits:true;initial-value:#3b82f6}
@property --mv-rc2{syntax:'<color>';inherits:true;initial-value:#f59e0b}
.falcon-mv__shape--random{animation-name:fmv-hue;animation-timing-function:linear;animation-iteration-count:infinite}
@keyframes fmv-hue{0%{--mv-rc:hsl(0 80% 58%);--mv-rc2:hsl(150 80% 58%)}8.33%{--mv-rc:hsl(30 80% 58%);--mv-rc2:hsl(180 80% 58%)}16.67%{--mv-rc:hsl(60 80% 58%);--mv-rc2:hsl(210 80% 58%)}25%{--mv-rc:hsl(90 80% 58%);--mv-rc2:hsl(240 80% 58%)}33.33%{--mv-rc:hsl(120 80% 58%);--mv-rc2:hsl(270 80% 58%)}41.67%{--mv-rc:hsl(150 80% 58%);--mv-rc2:hsl(300 80% 58%)}50%{--mv-rc:hsl(180 80% 58%);--mv-rc2:hsl(330 80% 58%)}58.33%{--mv-rc:hsl(210 80% 58%);--mv-rc2:hsl(0 80% 58%)}66.67%{--mv-rc:hsl(240 80% 58%);--mv-rc2:hsl(30 80% 58%)}75%{--mv-rc:hsl(270 80% 58%);--mv-rc2:hsl(60 80% 58%)}83.33%{--mv-rc:hsl(300 80% 58%);--mv-rc2:hsl(90 80% 58%)}91.67%{--mv-rc:hsl(330 80% 58%);--mv-rc2:hsl(120 80% 58%)}100%{--mv-rc:hsl(0 80% 58%);--mv-rc2:hsl(150 80% 58%)}}
.falcon-mv.is-paused .falcon-mv__anim,.falcon-mv.is-paused .falcon-mv__shape--random{animation-play-state:paused}
.falcon-mv.is-static .falcon-mv__anim,.falcon-mv.is-static .falcon-mv__shape--random{animation:none}
@media (prefers-reduced-motion:reduce){.falcon-mv .falcon-mv__anim,.falcon-mv .falcon-mv__shape--random{animation:none}}
</style>

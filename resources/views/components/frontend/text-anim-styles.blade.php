{{-- Text Animation — the Extra tab's looping motion, shared by every element that
     offers it (Title, Text Block, Button, Callout).

     One stylesheet, included by both renderers (the Vue canvas through
     admin/falcon-builder/partials/styles.blade.php and the page through
     frontend/builder/render.blade.php), so what an author previews is literally the
     rules that ship.

     The mode table lives in FalconCms\Core\Support\TextAnimations; every class below
     is one of its keys. Timing arrives as custom properties on the animated element's
     own style attribute, so nothing here is per-element.

     Deliberately NOT @once-guarded. On the front end this is emitted by
     frontend/builder/render.blade.php, and a page renders that more than once --
     _lazy_layout_post_context() pre-renders the post's content to build
     $postContent/$postExcerpt, and that throwaway pass would consume a @once guard and
     leave the visible element carrying the classes with no keyframes behind them.
     render.blade.php only includes this when the layout actually animates something,
     which is what keeps the repetition down.

     Colour modes paint the glyphs with a clipped background, so they carry !important --
     the element's inline `color` would otherwise win. TextAnimations::ELEMENTS keeps
     them away from anything with a background of its own (a Button, a Callout), where
     clipping the background to the letters would erase it.

     Colour Cycle is the exception among the colour modes: it animates `color` and
     `-webkit-text-fill-color` directly and needs no !important, because an animated
     declaration already outranks an inline one (animation origin beats author-normal).
     It does NOT use hue-rotate(), which was the first attempt and a dead end --
     hue-rotate turns a hue, and white, black and greys have none, which is exactly what
     most text is coloured. --}}
@php
    // Blade reads a bare `@media (` as a directive, so the at-rule is interpolated.
    $mqReduce = '@media (prefers-reduced-motion: reduce)';
@endphp
<style>
.fa-tanim {
    animation-name: var(--fa-tanim-name, none);
    animation-duration: var(--fa-tanim-dur, 2000ms);
    animation-delay: var(--fa-tanim-delay, 0ms);
    animation-iteration-count: var(--fa-tanim-iter, infinite);
    animation-timing-function: var(--fa-tanim-ease, ease-in-out);
    animation-fill-mode: both;
    will-change: transform, filter, opacity, background-position;
}
/* Shrink-to-fit so a transform pivots around the text, not the whole column.
   Left off whenever the heading is clipping to an ellipsis. */
.fa-tanim-ib { display: inline-block; max-width: 100%; }

/* Hover trigger: parked on frame 0 until pointed at. Two selectors because some
   elements animate their own wrapper (a Text Block) and some animate a child of it
   (a Title's heading, a Button's anchor), where hovering the steady wrapper is far
   easier than chasing a moving target. */
.fa-tanim-hover { animation-play-state: paused; }
.fa-tanim-hover:hover,
.fa-tanim-host:hover .fa-tanim-hover { animation-play-state: running; }

/* ── Motion ─────────────────────────────────────────────────────────────── */
.fa-tanim-swing { --fa-tanim-name: fa-tanim-swing; transform-origin: top center; }
@keyframes fa-tanim-swing {
    0%, 100% { transform: rotate(0deg); }
    20%      { transform: rotate(7deg); }
    40%      { transform: rotate(-5deg); }
    60%      { transform: rotate(3deg); }
    80%      { transform: rotate(-2deg); }
}

.fa-tanim-float { --fa-tanim-name: fa-tanim-float; }
@keyframes fa-tanim-float {
    0%, 100% { transform: translateY(0); }
    50%      { transform: translateY(-10px); }
}

.fa-tanim-bounce { --fa-tanim-name: fa-tanim-bounce; transform-origin: center bottom; }
@keyframes fa-tanim-bounce {
    0%, 20%, 53%, 80%, 100% { transform: translateY(0); animation-timing-function: cubic-bezier(.215,.61,.355,1); }
    40%, 43%                { transform: translateY(-20px); animation-timing-function: cubic-bezier(.755,.05,.855,.06); }
    70%                     { transform: translateY(-10px); animation-timing-function: cubic-bezier(.755,.05,.855,.06); }
    90%                     { transform: translateY(-4px); }
}

.fa-tanim-flip { --fa-tanim-name: fa-tanim-flip; backface-visibility: visible; transform-style: preserve-3d; }
@keyframes fa-tanim-flip {
    0%, 25%   { transform: perspective(900px) rotateX(0deg); }
    75%, 100% { transform: perspective(900px) rotateX(360deg); }
}

.fa-tanim-slide-reveal { --fa-tanim-name: fa-tanim-slide-reveal; }
@keyframes fa-tanim-slide-reveal {
    0%        { opacity: 0; transform: translateX(-28px); }
    22%, 78%  { opacity: 1; transform: translateX(0); }
    100%      { opacity: 0; transform: translateX(28px); }
}

/* ── Emphasis ───────────────────────────────────────────────────────────── */
.fa-tanim-pulse { --fa-tanim-name: fa-tanim-pulse; }
@keyframes fa-tanim-pulse {
    0%, 100% { transform: scale(1); }
    50%      { transform: scale(1.06); }
}

.fa-tanim-heartbeat { --fa-tanim-name: fa-tanim-heartbeat; }
@keyframes fa-tanim-heartbeat {
    0%, 60%, 100% { transform: scale(1); }
    14%, 42%      { transform: scale(1.1); }
    28%           { transform: scale(1); }
}

.fa-tanim-tada { --fa-tanim-name: fa-tanim-tada; }
@keyframes fa-tanim-tada {
    0%, 100%            { transform: scale(1) rotate(0deg); }
    10%, 20%            { transform: scale(.93) rotate(-3deg); }
    30%, 50%, 70%, 90%  { transform: scale(1.08) rotate(3deg); }
    40%, 60%, 80%       { transform: scale(1.08) rotate(-3deg); }
}

.fa-tanim-jello { --fa-tanim-name: fa-tanim-jello; transform-origin: center; }
@keyframes fa-tanim-jello {
    0%, 11.1%, 100% { transform: skewX(0deg) skewY(0deg); }
    22.2%           { transform: skewX(-11deg) skewY(-11deg); }
    33.3%           { transform: skewX(6deg) skewY(6deg); }
    44.4%           { transform: skewX(-3deg) skewY(-3deg); }
    55.5%           { transform: skewX(1.6deg) skewY(1.6deg); }
    66.6%           { transform: skewX(-.8deg) skewY(-.8deg); }
    77.7%           { transform: skewX(.4deg) skewY(.4deg); }
}

.fa-tanim-wobble { --fa-tanim-name: fa-tanim-wobble; }
@keyframes fa-tanim-wobble {
    0%, 100% { transform: translateX(0) rotate(0deg); }
    15%      { transform: translateX(-6%) rotate(-4deg); }
    30%      { transform: translateX(5%) rotate(3deg); }
    45%      { transform: translateX(-3%) rotate(-2deg); }
    60%      { transform: translateX(2%) rotate(1.5deg); }
    75%      { transform: translateX(-1%) rotate(-1deg); }
}

.fa-tanim-shake { --fa-tanim-name: fa-tanim-shake; }
@keyframes fa-tanim-shake {
    0%, 100%                { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
    20%, 40%, 60%, 80%      { transform: translateX(5px); }
}

/* ── Light & Color ──────────────────────────────────────────────────────── */
/* The three sweeping modes below all work the same way: an over-wide gradient clipped
   to the glyphs, slid sideways. Two things about that are easy to get wrong, and both
   did go wrong here once, so they are written down.

   1. The gradient MUST tile (background-repeat: repeat). With no-repeat, any offset
      that carries the image off the box leaves the glyphs with no background at all
      and, since they are painted transparent, the text simply disappears.

   2. A percentage background-position resolves against (box width - image width), not
      against the box. With an image wider than the box that difference is negative, so
      the offset runs backwards and is scaled by (S - 1) rather than by S. To travel
      exactly one tile — which is what makes the loop seamless — the end position must
      be S / (S - 1) x 100%: 150% at 3x, 200% at 2x, 300% at 1.5x. Only ratios that
      terminate are used; 2.2x, the size falconcms.com's hero happens to carry, wants
      183.333...% and leaves a few pixels of rounding at the seam. A negative value
      sends the sheen left-to-right across the text.

   The wider the tile, the longer the text rests between passes: Shine at 1.5x sweeps
   almost continuously, Shimmer at 3x shows the highlight once and then waits, which is
   the unhurried rhythm the falconcms.com hero has. */
.fa-tanim-glow { --fa-tanim-name: fa-tanim-glow; }
@keyframes fa-tanim-glow {
    0%, 100% { filter: drop-shadow(0 0 2px var(--fa-tanim-accent, #0091ea)) drop-shadow(0 0 6px var(--fa-tanim-accent, #0091ea)); }
    50%      { filter: drop-shadow(0 0 8px var(--fa-tanim-accent, #0091ea)) drop-shadow(0 0 22px var(--fa-tanim-accent, #0091ea)); }
}

.fa-tanim-shine {
    --fa-tanim-name: fa-tanim-shine;
    background-image: linear-gradient(100deg,
        var(--fa-tanim-base, #222222) 30%,
        var(--fa-tanim-accent, #ffffff) 45%,
        var(--fa-tanim-accent, #ffffff) 55%,
        var(--fa-tanim-base, #222222) 70%) !important;
    background-size: 150% 100% !important;
    background-repeat: repeat !important;
    -webkit-background-clip: text !important;
    background-clip: text !important;
    color: transparent !important;
    -webkit-text-fill-color: transparent !important;
}
@keyframes fa-tanim-shine {
    0%   { background-position: 0 0; }
    100% { background-position: -300% 0; }
}

/* Shimmer is the sheen on the hero word at falconcms.com, same geometry: a three-tone
   band inside a 220%-wide background, so the highlight crosses the text and then stays
   away for most of the cycle instead of sweeping continuously like Shine. */
.fa-tanim-shimmer {
    --fa-tanim-name: fa-tanim-shimmer;
    background-image: linear-gradient(100deg,
        var(--fa-tanim-base, #b9720f) 20%,
        var(--fa-tanim-mid, #e8912b) 45%,
        var(--fa-tanim-accent, #ffd9a0) 50%,
        var(--fa-tanim-mid, #e8912b) 55%,
        var(--fa-tanim-base, #b9720f) 80%) !important;
    background-size: 300% 100% !important;
    background-repeat: repeat !important;
    -webkit-background-clip: text !important;
    background-clip: text !important;
    color: transparent !important;
    -webkit-text-fill-color: transparent !important;
}
@keyframes fa-tanim-shimmer {
    0%   { background-position: 0 0; }
    100% { background-position: -150% 0; }
}

.fa-tanim-gradient-flow {
    --fa-tanim-name: fa-tanim-gradient-flow;
    background-image: linear-gradient(90deg,
        var(--fa-tanim-g1, #222222),
        var(--fa-tanim-g2, #0091ea),
        var(--fa-tanim-g3, #222222),
        var(--fa-tanim-g2, #0091ea),
        var(--fa-tanim-g1, #222222)) !important;
    background-size: 200% 100% !important;
    -webkit-background-clip: text !important;
    background-clip: text !important;
    color: transparent !important;
    -webkit-text-fill-color: transparent !important;
}
@keyframes fa-tanim-gradient-flow {
    0%   { background-position: 0% 50%; }
    100% { background-position: 200% 50%; }
}

/* Colour Cycle paints the glyphs; see the note at the top of this file. */
.fa-tanim-color-cycle { --fa-tanim-name: fa-tanim-color-cycle; }
@keyframes fa-tanim-color-cycle {
    0%, 100% { color: hsl(0, 85%, 60%);   -webkit-text-fill-color: hsl(0, 85%, 60%); }
    16.6%    { color: hsl(45, 90%, 55%);  -webkit-text-fill-color: hsl(45, 90%, 55%); }
    33.3%    { color: hsl(140, 70%, 45%); -webkit-text-fill-color: hsl(140, 70%, 45%); }
    50%      { color: hsl(190, 85%, 50%); -webkit-text-fill-color: hsl(190, 85%, 50%); }
    66.6%    { color: hsl(255, 75%, 62%); -webkit-text-fill-color: hsl(255, 75%, 62%); }
    83.3%    { color: hsl(320, 80%, 58%); -webkit-text-fill-color: hsl(320, 80%, 58%); }
}

{!! $mqReduce !!} {
    .fa-tanim { animation: none !important; }
}
</style>

{{-- Padding (or any four-sided spacing): one text box per side, like the Font Size field.
     Each box takes any CSS size — "20", "20px", "2rem", "5%", "10vh", "calc(1rem + 2vw)" —
     and a bare number is px. See spDisplay/spInput in scripts.blade.php for how it is stored.

     Variables:
       $model        JS expression of the settings object   (default editingElement.settings)
       $key          key prefix; the side is appended      (default 'padding' → paddingTop …)
       $sides        side => suffix                         (default Top/Right/Bottom/Left)
       $responsive   true: the box edits the current device's value (default true)
       $unit         unit key suffix: true = "Unit" (paddingTopUnit), a string such as "_unit",
                     or false when the field has no unit key        (default true)
       $defUnit      unit of a number with no stored unit           (default px)
       $placeholders side => hint on desktop, e.g. ['Top' => '12', 'Left' => '30'] (default '0')
       $legacy       for a padding that was one or two boxes before: the old key (or side => key);
                     an empty side shows that old value as its hint, as padSides() falls back to it
       $hintJs       side => JS expression for the hint, when it is worked out in the builder
       $class        extra classes for the grid
--}}
@php
    $spModel = $model ?? 'editingElement.settings';
    $spKey = $key ?? 'padding';
    $spSides = $sides ?? ['Top' => 'Top', 'Right' => 'Right', 'Bottom' => 'Bottom', 'Left' => 'Left'];
    $spDevice = ($responsive ?? true) ? 'device' : "'desktop'";
    $spUnitArg = $unit ?? true;
    $spUnit = $spUnitArg === true ? 'true' : ($spUnitArg === false ? 'false' : "'".$spUnitArg."'");
    $spDef = $defUnit ?? 'px';
    $spHints = $placeholders ?? [];
    $spLegacy = $legacy ?? null;
@endphp
<div class="grid grid-cols-4 gap-2 {{ $class ?? '' }}">
    @foreach($spSides as $spLabel => $spSuffix)
        @php
            $spOld = is_array($spLegacy) ? ($spLegacy[$spLabel] ?? null) : $spLegacy;
            $spDefHint = $spHints[$spLabel] ?? '0';
            $spHintJs = isset($hintJs[$spLabel]) ? $hintJs[$spLabel] : ($spOld
                ? "padHint({$spModel}, '{$spOld}', '{$spDefHint}')"
                : "spHint({$spModel}, '{$spKey}{$spSuffix}', {$spDevice}, {$spUnit}, '{$spDefHint}', '{$spDef}')");
        @endphp
        <div>
            <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block text-center">{{ $spLabel }}</label>
            <input type="text"
                   :value="spDisplay({{ $spModel }}, '{{ $spKey.$spSuffix }}', {{ $spDevice }}, {{ $spUnit }}, '{{ $spDef }}')"
                   @input="spInput({{ $spModel }}, '{{ $spKey.$spSuffix }}', {{ $spDevice }}, $event.target.value, {{ $spUnit }})"
                   :placeholder="{{ $spHintJs }}"
                   title="Any CSS size: 20, 20px, 2rem, 5%, 10vh"
                   class="w-full min-w-0 border border-slate-200 rounded px-1 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
        </div>
    @endforeach
</div>

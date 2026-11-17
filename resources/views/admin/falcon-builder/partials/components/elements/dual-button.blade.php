{{-- Dual Button — live canvas preview.
     Mirrors resources/views/frontend/builder/elements/dual-button.blade.php.

     The markup and class names are the page's, and so is the stylesheet (styles.blade.php
     includes the same one), so hover, the joined corners and the connector are the real
     thing here rather than a copy. The fcDb* helpers only hand over values: which buttons
     exist, the wrapper's classes, and its custom properties for the current device.

     Keep comments OUT of the bindings below — a quote inside one closes the attribute and
     Vue then fails to compile the whole canvas. --}}
<div v-if="el.type === 'dual_button'"
     class="w-full"
     :style="getCanvasVisibilityStyle(el.settings)">

    <div v-if="fcDbButtons(el).length"
         :class="[fcDbClasses(el), el.settings.cssClass || '']"
         :id="el.settings.cssId || undefined"
         :style="fcDbStyle(el)">
        <div class="fdb-group">
            <span v-for="(btn, bi) in fcDbButtons(el)" :key="btn.side"
                  class="fdb-cell"
                  :class="{ 'fdb-cell--first': bi === 0, 'fdb-cell--last': bi === fcDbButtons(el).length - 1 }">
                <a href="javascript:void(0)" class="fdb-btn" :class="'fdb-btn--' + btn.side" @click.prevent>
                    <i v-if="btn.icon && btn.iconPosition !== 'right'" :class="btn.icon"></i>
                    <span v-if="btn.text !== ''" class="fdb-label" v-text="btn.text"></span>
                    <i v-if="btn.icon && btn.iconPosition === 'right'" :class="btn.icon"></i>
                </a>
                <span v-if="bi === 0 && fcDbConnector(el) !== ''" class="fdb-or" v-text="fcDbConnector(el)"></span>
            </span>
        </div>
    </div>

    {{-- Both buttons cleared: the page renders nothing, and without a word here the canvas
         would show an element that cannot be seen or clicked. --}}
    <div v-else
         style="padding:18px;border:1px dashed #c9d0d8;border-radius:8px;color:#79838F;font-size:13px;text-align:center;line-height:1.5;">
        Both buttons are empty.<br>
        <span style="font-size:12px;opacity:.85;">Give one a label or an icon in the Content tab.</span>
    </div>
</div>

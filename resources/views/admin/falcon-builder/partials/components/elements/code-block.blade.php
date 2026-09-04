{{-- Code Block — live canvas preview.
     Mirrors resources/views/frontend/builder/elements/code-block.blade.php.
     The highlighting comes from the fcCode* helpers in partials/scripts.blade.php,
     which run the very same language rules that CodeHighlighter uses on the server —
     handed over as JSON — so the canvas and the front end cannot colour differently.

     The typing reveal is deliberately NOT played here: an editor needs to see the
     whole snippet while writing it, not watch it type out on every keystroke. The
     chosen mode is shown as a small badge instead. --}}
<div v-if="el.type === 'code_block'"
     class="w-full"
     :style="[getCanvasVisibilityStyle(el.settings), fcCodeOuterStyle(el)]">

    <div :style="fcCodeShellStyle(el)">

        {{-- window bar --}}
        <div v-if="el.settings.showChrome" :style="fcCodeChromeStyle(el)">
            <span v-if="el.settings.chromeDots !== false"
                  style="display:flex; gap:6px; flex:none;" aria-hidden="true">
                <i style="width:10px;height:10px;border-radius:50%;display:block;background:#FF5F57"></i>
                <i style="width:10px;height:10px;border-radius:50%;display:block;background:#FEBC2E"></i>
                <i style="width:10px;height:10px;border-radius:50%;display:block;background:#28C840"></i>
            </span>
            <span style="flex:1 1 auto; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
                  v-text="el.settings.filename"></span>
            <span v-if="el.settings.showLangTag !== false"
                  style="flex:none; text-transform:uppercase; letter-spacing:.08em; font-size:10.5px; opacity:.75;"
                  v-text="fcCodeLangName(el)"></span>
        </div>

        {{-- copy button: shown so the editor can position and colour it, but it does
             not copy on the canvas — clicking inside the canvas selects the element --}}
        <span v-if="el.settings.showCopy !== false" :style="fcCodeCopyStyle(el)">
            @{{ el.settings.copyLabel || 'Copy' }}
        </span>

        <div :style="fcCodeScrollStyle(el)">
            <pre :style="fcCodePreStyle(el)"><code><span
                v-for="(line, i) in fcCodeCanvasLines(el)" :key="i"
                :style="fcCodeLineStyle(el, i)"><span
                    v-if="el.settings.showLineNumbers !== false"
                    :style="fcCodeNoStyle(el)"
                    v-text="fcCodeLineNo(el, i)"></span><span
                    style="flex:1 1 auto; min-width:0;"
                    v-html="line.html || '&nbsp;'"></span></span></code></pre>
        </div>

        <span v-if="el.settings.typeMode && el.settings.typeMode !== 'none'"
              :style="fcCodeBadgeStyle(el)"
              :title="'Plays on the live page, not in the editor'">
            @{{ el.settings.typeMode === 'lines' ? 'line reveal' : 'typewriter' }}
        </span>
    </div>
</div>

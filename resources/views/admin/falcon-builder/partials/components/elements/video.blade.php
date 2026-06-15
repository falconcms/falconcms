<div v-if="el.type === 'video'"
     class="w-full"
     :class="[el.settings.cssClass || '']"
     :id="el.settings.cssId || undefined"
     :style="[getCanvasVisibilityStyle(el.settings), {
         marginTop:    (el.settings.marginTop    || 0) + (el.settings.marginTopUnit    || 'px'),
         marginBottom: (el.settings.marginBottom || 0) + (el.settings.marginBottomUnit || 'px'),
     }]">

    <div style="position:relative;width:100%;overflow:hidden;border-radius:4px;"
         :style="{
             paddingBottom: ({'16-9':'56.25%','4-3':'75%','1-1':'100%','9-16':'177.78%'})[el.settings.aspectRatio || '16-9'] || '56.25%'
         }">

        <!-- Self Host / MP4 mode -->
        <template v-if="(el.settings.videoSource || 'youtube') === 'selfhost'">
            <div style="position:absolute;inset:0;background:linear-gradient(135deg,#1a1a2e,#16213e);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;">
                <div style="width:56px;height:56px;background:rgba(255,255,255,0.08);border:2px solid rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa fa-film" style="color:rgba(255,255,255,0.6);font-size:18px;"></i>
                </div>
                <div v-if="el.settings.url" style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                    <span style="color:rgba(255,255,255,0.3);font-size:8px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;">Video File</span>
                    <span style="color:rgba(255,255,255,0.6);font-size:10px;font-family:monospace;word-break:break-all;text-align:center;max-width:85%;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:4px;padding:3px 8px;">@{{ el.settings.url.split('/').pop() }}</span>
                </div>
                <span v-else style="color:rgba(255,255,255,0.22);font-size:10px;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;">No Video File</span>
            </div>
        </template>

        <!-- YouTube: CSS background thumbnail (no broken-image icons) -->
        <template v-else-if="el.settings.url && el.settings.url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/)">
            <div :style="{
                     position:'absolute', top:0, left:0, width:'100%', height:'100%',
                     backgroundImage: 'url(https://img.youtube.com/vi/' + el.settings.url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/)[1] + '/hqdefault.jpg)',
                     backgroundSize: 'cover', backgroundPosition: 'center', backgroundColor: '#000'
                 }">
            </div>
            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);pointer-events:none;">
                <div style="width:64px;height:45px;background:#ff0000;border-radius:10px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 10px rgba(0,0,0,0.5);">
                    <svg viewBox="0 0 68 48" width="40" height="28"><path fill="#fff" d="M26.5 16l17 8-17 8z"/></svg>
                </div>
            </div>
        </template>

        <!-- Vimeo: branded placeholder -->
        <template v-else-if="el.settings.url && el.settings.url.match(/vimeo\.com\/(\d+)/)">
            <div style="position:absolute;inset:0;background:linear-gradient(135deg,#1ab7ea,#0f8ab0);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;">
                <div style="width:64px;height:45px;background:rgba(255,255,255,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <svg viewBox="0 0 68 48" width="40" height="28"><path fill="#fff" d="M26.5 16l17 8-17 8z"/></svg>
                </div>
                <span style="color:rgba(255,255,255,0.85);font-size:10px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;">Vimeo</span>
            </div>
        </template>

        <!-- No URL / unrecognised URL in YouTube mode -->
        <template v-else>
            <div style="position:absolute;inset:0;background:#0d1117;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;">
                <div style="width:52px;height:52px;background:rgba(255,255,255,0.07);border:2px solid rgba(255,255,255,0.12);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa fa-play" style="color:rgba(255,255,255,0.55);font-size:16px;margin-left:4px;"></i>
                </div>
                <span v-if="el.settings.url"
                      style="color:rgba(255,255,255,0.4);font-size:10px;font-family:monospace;word-break:break-all;text-align:center;max-width:80%;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:4px;padding:3px 8px;">@{{ el.settings.url }}</span>
                <span v-else style="color:rgba(255,255,255,0.22);font-size:10px;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;">No Video URL</span>
            </div>
        </template>

        <!-- Aspect ratio badge -->
        <span style="position:absolute;bottom:6px;right:8px;background:rgba(0,0,0,0.55);color:rgba(255,255,255,0.8);font-size:8px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;padding:2px 6px;border-radius:3px;pointer-events:none;">
            @{{ (el.settings.aspectRatio || '16-9').replace('-', ':') }}
        </span>

    </div>

</div>

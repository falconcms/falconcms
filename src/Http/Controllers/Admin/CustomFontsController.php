<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use FalconCms\Core\Support\CustomFonts\CustomFonts;
use FalconCms\Core\Support\CustomFonts\FontFile;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Customizer → Typography → Custom Font: upload, rename, re-weigh and delete fonts.
 *
 * Every answer carries the whole list and the @font-face CSS, so the screen redraws from it
 * and its previews use the fonts at once. Same permission as the rest of the Customizer.
 */
class CustomFontsController extends Controller
{
    public function upload(Request $request)
    {
        $this->allowed();
        $request->validate([
            'fonts' => 'required|array|max:40',
            // The extension is checked here, the content by FontFile::format() — a file has to
            // be a real font, whatever its name says.
            'fonts.*' => 'file|max:'.(FontFile::MAX_BYTES / 1024),
            'into' => 'nullable|string|max:24',
        ]);
        $files = [];
        $rejected = [];
        foreach ((array) $request->file('fonts', []) as $file) {
            $ext = strtolower((string) $file->getClientOriginalExtension());
            if (in_array($ext, FontFile::FORMATS, true)) {
                $files[] = $file;
            } else {
                $rejected[] = $file->getClientOriginalName().': only WOFF2, WOFF, TTF and OTF fonts can be uploaded';
            }
        }
        $result = CustomFonts::upload($files, $request->input('into') ?: null);

        return $this->answer(array_merge($rejected, $result['errors']), $result['added']);
    }

    public function update(Request $request, string $font)
    {
        $this->allowed();
        $data = $request->validate([
            'family' => 'nullable|string|max:80',
            'fallback' => 'nullable|string|in:'.implode(',', CustomFonts::FALLBACKS),
        ]);
        $error = CustomFonts::update($font, $data['family'] ?? null, $data['fallback'] ?? null);

        return $this->answer($error ? [$error] : []);
    }

    public function updateVariant(Request $request, string $font, string $variant)
    {
        $this->allowed();
        $data = $request->validate([
            'weight' => 'required|integer|min:100|max:900',
            'style' => 'required|in:normal,italic',
        ]);
        $error = CustomFonts::updateVariant($font, $variant, (int) $data['weight'], $data['style']);

        return $this->answer($error ? [$error] : []);
    }

    public function destroy(string $font, ?string $variant = null)
    {
        $this->allowed();
        CustomFonts::delete($font, $variant);

        return $this->answer();
    }

    private function answer(array $errors = [], array $added = [])
    {
        return response()->json([
            'success' => !$errors,
            'errors' => array_values($errors),
            'added' => array_values($added),
            'fonts' => CustomFonts::forEditor(),
            'css' => CustomFonts::css(),
        ]);
    }

    private function allowed(): void
    {
        if (!auth()->user() || !auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }
    }
}

<?php

/**
 * Languages, language switchers and translation.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Models\Language;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (!function_exists('falcon_default_language')) {
    /**
     * The site's default language code, memoized for the request. Called from
     * get_falcon_permalink() which runs many times per page, so this avoids a
     * repeated `cms_languages where is_default` query on every link.
     */
    function falcon_default_language(): string
    {
        static $code = null;
        if ($code !== null) {
            return $code;
        }
        $code = 'en';
        try {
            $dbDefault = DB::table('cms_languages')->where('is_default', true)->value('code');
            if ($dbDefault) {
                $code = $dbDefault;
            }
        } catch (Throwable $e) {
        }

        return $code;
    }
}

if (!function_exists('falcon_lang_switcher')) {
    function falcon_lang_switcher($showFlags = true)
    {
        try {
            if (!Schema::hasTable('cms_languages')) {
                return '';
            }
            $languages = Language::where('status', true)->get();
            if ($languages->count() <= 1) {
                return '';
            }

            $currentLocale = app()->getLocale();
            $output = '<div class="lazy-lang-switcher flex items-center space-x-3">';

            // Check if we are on a single post/page to find equivalents
            $currentPost = null;
            if (request()->route('typeOrSlug')) {
                $viewData = view()->getShared();
                if (isset($viewData['post'])) {
                    $currentPost = $viewData['post'];
                }
            }

            foreach ($languages as $lang) {
                $isActive = ($currentLocale == $lang->code);
                $url = url($lang->code);

                if ($currentPost) {
                    $equivalent = $currentPost->getTranslation($lang->code);
                    if ($equivalent) {
                        $url = get_falcon_permalink($equivalent);
                    }
                }

                $output .= '<a href="'.$url.'" class="flex items-center text-[13px] '.($isActive ? 'font-bold text-blue-600' : 'text-gray-600 hover:text-black').'">';
                if ($showFlags) {
                    $output .= '<span class="mr-1">'.$lang->flag.'</span> ';
                }
                $output .= strtoupper($lang->code);
                $output .= '</a>';
            }
            $output .= '</div>';

            return $output;
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('falcon_lang_dropdown')) {
    function falcon_lang_dropdown()
    {
        try {
            if (!Schema::hasTable('cms_languages')) {
                return '';
            }
            $activeLangs = Language::where('status', true)->get();
            if ($activeLangs->count() <= 1) {
                return '';
            }

            $currentLang = $activeLangs->where('code', app()->getLocale())->first() ?? $activeLangs->first();

            // Find current post to check for translations
            $currentPost = view()->getShared()['current_post'] ?? null;

            // Filter languages to only those that have a translation for the current post
            if ($currentPost) {
                $activeLangs = $activeLangs->filter(function ($lang) use ($currentPost) {
                    if ($currentPost->lang_code == $lang->code) {
                        return true;
                    }

                    return (bool) $currentPost->getTranslation($lang->code);
                });
            }

            if ($activeLangs->count() <= 1) {
                return '';
            }

            $displayMode = get_cms_option('lang_switcher_display', 'both');

            $output = '<div class="relative group inline-block language-switcher-dropdown">';
            $output .= '<button class="flex items-center gap-1.5 text-slate-700 hover:text-primary transition-colors text-[13px] font-bold cursor-pointer" onclick="this.nextElementSibling.classList.toggle(\'hidden\')">';

            $currentLangCode = strtolower($currentLang->code);
            $countryMap = [
                'en' => 'us', 'bn' => 'bd', 'zh' => 'cn', 'ar' => 'sa', 'uk' => 'gb',
                'ja' => 'jp', 'ko' => 'kr', 'pt' => 'br', 'hi' => 'in', 'ru' => 'ru',
                'tr' => 'tr', 'it' => 'it', 'es' => 'es', 'fr' => 'fr', 'de' => 'de',
                'gb' => 'gb', 'cn' => 'cn', 'sa' => 'sa', 'kr' => 'kr', 'jp' => 'jp',
                'br' => 'br', 'in' => 'in',
            ];
            $currentFlagCode = $countryMap[$currentLangCode] ?? $currentLangCode;

            if (in_array($displayMode, ['both', 'flag_only'])) {
                $output .= '<span class="flex items-center justify-center w-5 h-4 overflow-hidden rounded-sm border border-slate-100 shadow-sm">';
                $output .= '<img src="'.url('/assets/flags/'.$currentFlagCode.'.png').'" class="w-full h-full object-cover" alt="'.$currentLang->name.'">';
                $output .= '</span>';
            }

            if (in_array($displayMode, ['both', 'text_only'])) {
                $output .= '<span class="uppercase">'.$currentLang->name.'</span>';
            } elseif ($displayMode === 'code_only') {
                $output .= '<span class="uppercase">'.$currentLang->code.'</span>';
            }

            $output .= '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>';
            $output .= '</button>';
            $output .= '<div class="absolute top-full right-0 mt-2 w-32 bg-white border border-slate-100 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 rounded-md overflow-hidden">';
            $output .= '<ul class="py-1 m-0 list-none">';

            foreach ($activeLangs as $lang) {
                $isActive = (app()->getLocale() == $lang->code);
                $url = route('frontend.set-locale', $lang->code);

                if ($currentPost) {
                    $equivalent = $currentPost->getTranslation($lang->code);
                    if ($equivalent) {
                        $url = get_falcon_permalink($equivalent);
                    } elseif ($currentPost->lang_code == $lang->code) {
                        $url = get_falcon_permalink($currentPost);
                    }
                }

                $output .= '<li>';
                $output .= '<a href="'.$url.'" class="flex items-center justify-between gap-2 px-4 py-2 text-[13px] font-medium text-slate-600 hover:text-primary hover:bg-slate-50 transition-all '.($isActive ? 'bg-slate-50 text-primary font-bold' : '').'">';
                $output .= '<div class="flex items-center gap-2">';

                $langCode = strtolower($lang->code);
                $countryMap = [
                    'en' => 'us', 'bn' => 'bd', 'zh' => 'cn', 'ar' => 'sa', 'uk' => 'gb',
                    'ja' => 'jp', 'ko' => 'kr', 'pt' => 'br', 'hi' => 'in', 'ru' => 'ru',
                    'tr' => 'tr', 'it' => 'it', 'es' => 'es', 'fr' => 'fr', 'de' => 'de',
                    'gb' => 'gb', 'cn' => 'cn', 'sa' => 'sa', 'kr' => 'kr', 'jp' => 'jp',
                    'br' => 'br', 'in' => 'in',
                ];
                $flagCode = $countryMap[$langCode] ?? $langCode;

                if (in_array($displayMode, ['both', 'flag_only'])) {
                    $output .= '<span class="flex items-center justify-center w-5 h-4 overflow-hidden rounded-sm border border-slate-100 shadow-sm">';
                    $output .= '<img src="'.url('/assets/flags/'.$flagCode.'.png').'" class="w-full h-full object-cover" alt="'.$lang->name.'">';
                    $output .= '</span>';
                }

                if (in_array($displayMode, ['both', 'text_only'])) {
                    $output .= '<span>'.$lang->name.'</span>';
                } elseif ($displayMode === 'code_only') {
                    $output .= '<span class="uppercase">'.$lang->code.'</span>';
                }

                $output .= '</div>';
                if ($isActive) {
                    $output .= '<svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                }
                $output .= '</a></li>';
            }

            $output .= '</ul></div></div>';

            return $output;
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('falcon_mobile_lang_switcher')) {
    function falcon_mobile_lang_switcher()
    {
        try {
            if (!Schema::hasTable('cms_languages')) {
                return '';
            }
            $activeLangs = Language::where('status', true)->get();
            if ($activeLangs->count() <= 1) {
                return '';
            }

            // Find current post to check for translations
            $currentPost = view()->getShared()['current_post'] ?? null;

            // Filter languages to only those that have a translation for the current post
            if ($currentPost) {
                $activeLangs = $activeLangs->filter(function ($lang) use ($currentPost) {
                    if ($currentPost->lang_code == $lang->code) {
                        return true;
                    }

                    return (bool) $currentPost->getTranslation($lang->code);
                });
            }

            if ($activeLangs->count() <= 1) {
                return '';
            }

            $displayMode = get_cms_option('lang_switcher_display', 'both');
            $output = '<div class="grid grid-cols-2 gap-2">';
            foreach ($activeLangs as $lang) {
                $isActive = (app()->getLocale() == $lang->code);
                $url = route('frontend.set-locale', $lang->code);

                if ($currentPost) {
                    $equivalent = $currentPost->getTranslation($lang->code);
                    if ($equivalent) {
                        $url = get_falcon_permalink($equivalent);
                    } elseif ($currentPost->lang_code == $lang->code) {
                        $url = get_falcon_permalink($currentPost);
                    }
                }

                $output .= '<a href="'.$url.'" class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg border '.($isActive ? 'border-primary bg-primary/5 text-primary' : 'border-slate-100 text-slate-600').' transition-all">';
                $output .= '<div class="flex items-center gap-2">';

                $langCode = strtolower($lang->code);
                $countryMap = [
                    'en' => 'us', 'bn' => 'bd', 'zh' => 'cn', 'ar' => 'sa', 'uk' => 'gb',
                    'ja' => 'jp', 'ko' => 'kr', 'pt' => 'br', 'hi' => 'in', 'ru' => 'ru',
                    'tr' => 'tr', 'it' => 'it', 'es' => 'es', 'fr' => 'fr', 'de' => 'de',
                    'gb' => 'gb', 'cn' => 'cn', 'sa' => 'sa', 'kr' => 'kr', 'jp' => 'jp',
                    'br' => 'br', 'in' => 'in',
                ];
                $flagCode = $countryMap[$langCode] ?? $langCode;

                if (in_array($displayMode, ['both', 'flag_only'])) {
                    $output .= '<span class="w-6 h-4 overflow-hidden rounded-sm flex items-center justify-center shrink-0 border border-slate-100 shadow-sm">';
                    $output .= '<img src="'.url('/assets/flags/'.$flagCode.'.png').'" class="w-full h-full object-cover" alt="'.$lang->name.'">';
                    $output .= '</span>';
                }

                if (in_array($displayMode, ['both', 'text_only'])) {
                    $output .= '<span class="text-[13px] font-semibold">'.$lang->name.'</span>';
                } elseif ($displayMode === 'code_only') {
                    $output .= '<span class="text-[13px] font-semibold uppercase">'.$lang->code.'</span>';
                }

                $output .= '</div>';
                if ($isActive) {
                    $output .= '<svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                }
                $output .= '</a>';
            }
            $output .= '</div>';

            return $output;
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('the_falcon_lang_dropdown')) {
    function the_falcon_lang_dropdown()
    {
        echo falcon_lang_dropdown();
    }
}

if (!function_exists('falcon_translate')) {
    function falcon_translate($text, $targetLang = 'en', $sourceLang = 'auto')
    {
        if (empty($text)) {
            return $text;
        }

        // Map common CMS codes to Google Translate codes
        $map = [
            'jp' => 'ja', 'gb' => 'en', 'in' => 'hi', 'cn' => 'zh-CN', 'kr' => 'ko',
            'ua' => 'uk', 'br' => 'pt', 'sa' => 'ar', 'bd' => 'bn', 'zh' => 'zh-CN',
            'ja' => 'ja', 'ko' => 'ko', 'pt' => 'pt', 'hi' => 'hi',
        ];

        $targetLang = $map[strtolower($targetLang)] ?? $targetLang;
        $sourceLang = $map[strtolower($sourceLang)] ?? $sourceLang;

        try {
            $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl='.$sourceLang.'&tl='.$targetLang.'&dt=t&q='.urlencode($text);

            $options = [
                'http' => [
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n",
                ],
            ];
            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);

            if ($response) {
                $data = json_decode($response, true);
                $translated = '';
                if (isset($data[0])) {
                    foreach ($data[0] as $line) {
                        $translated .= $line[0];
                    }

                    return $translated;
                }
            }
        } catch (Exception $e) {
        }

        return $text;
    }
}

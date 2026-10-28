<?php

namespace FalconAi\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Falcon AI's options (AI → Settings), kept as CMS options.
 *
 * API keys are stored encrypted with the app key and never leave the server: the settings
 * screen shows only their first and last characters. Saving a field still holding that masked
 * text keeps the saved key; an empty field keeps it too; "Remove" clears it.
 */
final class Settings
{
    /** The permission for the AI settings and usage screens. */
    public const PERMISSION = 'manage_ai';

    public const PROVIDER = 'ai_provider';

    public const LANGUAGE = 'ai_language';

    public const TONE = 'ai_tone';

    /** Role slugs (besides administrators) that may use AI writing; JSON list. */
    public const WRITER_ROLES = 'ai_writer_roles';

    /** Tokens a month across all requests; 0 = no limit. */
    public const MONTHLY_LIMIT = 'ai_monthly_token_limit';

    /** provider id => [key option, model option, default model, the shape a real key has, help] */
    public const KEYS = [
        'claude' => ['ai_claude_key', 'ai_claude_model', 'claude-opus-5-5', '/^sk-ant-[A-Za-z0-9_\-]{20,}$/',
            'A Claude API key starts with "sk-ant-". Copy it again from console.anthropic.com → API keys.'],
        'openai' => ['ai_openai_key', 'ai_openai_model', '', '/^sk-[A-Za-z0-9_\-]{20,}$/',
            'An OpenAI API key starts with "sk-". Copy it again from platform.openai.com → API keys.'],
        // Google AI Studio now issues "AQ." keys; older keys start with "AIza". Both work.
        'gemini' => ['ai_gemini_key', 'ai_gemini_model', '', '/^(AIza[0-9A-Za-z_\-]{35}|AQ\.[0-9A-Za-z_\-.]{20,500})$/',
            'A Gemini API key starts with "AQ." (or "AIza" for older keys). Copy it again from Google AI Studio → Get API key.'],
    ];

    /** provider id => its name in messages, where to get a key, what a key looks like. */
    public const KEY_INFO = [
        'claude' => ['Claude', 'https://console.anthropic.com/settings/keys', 'sk-ant-…'],
        'openai' => ['OpenAI', 'https://platform.openai.com/api-keys', 'sk-…'],
        'gemini' => ['Gemini', 'https://aistudio.google.com/apikey', 'AQ.… (or AIza…)'],
    ];

    /** What hides the middle of a saved key; a field still holding it is unchanged. */
    public const MASK = '•';

    /**
     * The languages articles can be written in: code => [name in English, its own name]. Claude
     * and OpenAI write all of these well; "auto" follows the language the topic is written in.
     */
    public const LANGUAGES = [
        'auto' => ['The language of the topic', ''],
        'en' => ['English', 'English'], 'bn' => ['Bangla', 'বাংলা'], 'hi' => ['Hindi', 'हिन्दी'], 'ur' => ['Urdu', 'اردو'],
        'ar' => ['Arabic', 'العربية'], 'fa' => ['Persian', 'فارسی'], 'tr' => ['Turkish', 'Türkçe'], 'ps' => ['Pashto', 'پښتو'],
        'es' => ['Spanish', 'Español'], 'pt' => ['Portuguese', 'Português'], 'fr' => ['French', 'Français'], 'de' => ['German', 'Deutsch'],
        'it' => ['Italian', 'Italiano'], 'nl' => ['Dutch', 'Nederlands'], 'ca' => ['Catalan', 'Català'], 'gl' => ['Galician', 'Galego'],
        'eu' => ['Basque', 'Euskara'], 'sv' => ['Swedish', 'Svenska'], 'no' => ['Norwegian', 'Norsk'], 'da' => ['Danish', 'Dansk'],
        'fi' => ['Finnish', 'Suomi'], 'is' => ['Icelandic', 'Íslenska'], 'ga' => ['Irish', 'Gaeilge'], 'cy' => ['Welsh', 'Cymraeg'],
        'pl' => ['Polish', 'Polski'], 'cs' => ['Czech', 'Čeština'], 'sk' => ['Slovak', 'Slovenčina'], 'hu' => ['Hungarian', 'Magyar'],
        'ro' => ['Romanian', 'Română'], 'bg' => ['Bulgarian', 'Български'], 'el' => ['Greek', 'Ελληνικά'], 'ru' => ['Russian', 'Русский'],
        'uk' => ['Ukrainian', 'Українська'], 'sr' => ['Serbian', 'Српски'], 'hr' => ['Croatian', 'Hrvatski'], 'bs' => ['Bosnian', 'Bosanski'],
        'sl' => ['Slovenian', 'Slovenščina'], 'mk' => ['Macedonian', 'Македонски'], 'sq' => ['Albanian', 'Shqip'], 'lt' => ['Lithuanian', 'Lietuvių'],
        'lv' => ['Latvian', 'Latviešu'], 'et' => ['Estonian', 'Eesti'], 'he' => ['Hebrew', 'עברית'], 'ka' => ['Georgian', 'ქართული'],
        'hy' => ['Armenian', 'Հայերեն'], 'az' => ['Azerbaijani', 'Azərbaycanca'], 'kk' => ['Kazakh', 'Қазақша'], 'uz' => ['Uzbek', 'Oʻzbekcha'],
        'mn' => ['Mongolian', 'Монгол'], 'zh-Hans' => ['Chinese (Simplified)', '简体中文'], 'zh-Hant' => ['Chinese (Traditional)', '繁體中文'],
        'ja' => ['Japanese', '日本語'], 'ko' => ['Korean', '한국어'], 'th' => ['Thai', 'ไทย'], 'vi' => ['Vietnamese', 'Tiếng Việt'],
        'id' => ['Indonesian', 'Bahasa Indonesia'], 'ms' => ['Malay', 'Bahasa Melayu'], 'tl' => ['Filipino', 'Filipino'], 'my' => ['Burmese', 'မြန်မာ'],
        'km' => ['Khmer', 'ខ្មែរ'], 'lo' => ['Lao', 'ລາວ'], 'ne' => ['Nepali', 'नेपाली'], 'si' => ['Sinhala', 'සිංහල'],
        'ta' => ['Tamil', 'தமிழ்'], 'te' => ['Telugu', 'తెలుగు'], 'kn' => ['Kannada', 'ಕನ್ನಡ'], 'ml' => ['Malayalam', 'മലയാളം'],
        'mr' => ['Marathi', 'मराठी'], 'gu' => ['Gujarati', 'ગુજરાતી'], 'pa' => ['Punjabi', 'ਪੰਜਾਬੀ'], 'or' => ['Odia', 'ଓଡ଼ିଆ'],
        'as' => ['Assamese', 'অসমীয়া'], 'sw' => ['Swahili', 'Kiswahili'], 'am' => ['Amharic', 'አማርኛ'], 'so' => ['Somali', 'Soomaali'],
        'ha' => ['Hausa', 'Hausa'], 'yo' => ['Yoruba', 'Yorùbá'], 'ig' => ['Igbo', 'Igbo'], 'zu' => ['Zulu', 'isiZulu'], 'af' => ['Afrikaans', 'Afrikaans'],
    ];

    public const TONES = [
        'friendly' => 'Friendly and clear',
        'professional' => 'Professional',
        'conversational' => 'Conversational',
        'persuasive' => 'Persuasive',
        'academic' => 'Academic',
    ];

    public static function get(string $key, string $default = ''): string
    {
        return (string) get_cms_option($key, $default);
    }

    /** The provider chosen in the settings. */
    public static function provider(): string
    {
        $id = self::get(self::PROVIDER, 'claude');

        return $id !== '' ? $id : 'claude';
    }

    public static function language(): string
    {
        $language = self::get(self::LANGUAGE, 'auto');

        return isset(self::LANGUAGES[$language]) ? $language : 'auto';
    }

    /**
     * The language list for a dropdown: code => "Bangla — বাংলা", "auto" first, then by name.
     *
     * @return array<string, string>
     */
    public static function languageOptions(): array
    {
        $options = [];
        foreach (self::LANGUAGES as $code => [$name, $native]) {
            $options[$code] = $native !== '' && $native !== $name ? $name.' — '.$native : $name;
        }
        $auto = ['auto' => $options['auto']];
        unset($options['auto']);
        asort($options);

        return $auto + $options;
    }

    /** "Bangla (বাংলা)", as a prompt names it; null for "auto". */
    public static function languageName(string $code): ?string
    {
        if ($code === 'auto' || !isset(self::LANGUAGES[$code])) {
            return null;
        }
        [$name, $native] = self::LANGUAGES[$code];

        return $native !== '' && $native !== $name ? $name.' ('.$native.')' : $name;
    }

    public static function tone(): string
    {
        $tone = self::get(self::TONE, 'friendly');

        return isset(self::TONES[$tone]) ? $tone : 'friendly';
    }

    /** @return list<string> */
    public static function writerRoles(): array
    {
        $roles = json_decode(self::get(self::WRITER_ROLES, '["editor"]'), true);

        return is_array($roles) ? array_values(array_filter($roles, 'is_string')) : ['editor'];
    }

    public static function monthlyLimit(): int
    {
        return max(0, (int) self::get(self::MONTHLY_LIMIT, '0'));
    }

    /** A provider's saved key, decrypted; '' when there is none (or it cannot be read). */
    public static function key(string $provider): string
    {
        $option = self::KEYS[$provider][0] ?? null;
        $stored = $option ? self::get($option) : '';
        if ($stored === '') {
            return '';
        }
        try {
            return Crypt::decryptString($stored);
        } catch (Throwable $e) {
            return '';
        }
    }

    /** A provider's chosen model (or its default). */
    public static function model(string $provider): string
    {
        [, $option, $default] = self::KEYS[$provider] ?? [null, null, ''];

        return $option ? (self::get($option) ?: $default) : '';
    }

    /** The saved key as its field shows it: "sk-ant-api03-a••••••••••••VwAA", or null. */
    public static function masked(string $provider): ?string
    {
        $key = self::key($provider);
        if ($key === '') {
            return null;
        }
        [$start, $end] = strlen($key) >= 32 ? [12, 4] : [3, 2];

        return substr($key, 0, $start).str_repeat(self::MASK, 12).substr($key, -$end);
    }

    /**
     * Save the settings screen. Keys are encrypted; a value that cannot be the service's key is
     * refused (the rest still saves) — a browser filling the admin password into the first
     * password field it finds would otherwise replace a good key.
     *
     * @param  array<string, mixed>  $input  Validated input
     * @param  array<string, string>  $models  Models each provider offers (to refuse anything else)
     * @return list<string> Problems to show
     */
    public static function save(array $input, array $models): array
    {
        $problems = [];
        $values = [
            self::PROVIDER => (string) ($input['provider'] ?? 'claude'),
            self::LANGUAGE => isset(self::LANGUAGES[$input['language'] ?? '']) ? (string) $input['language'] : 'auto',
            self::TONE => isset(self::TONES[$input['tone'] ?? '']) ? (string) $input['tone'] : 'friendly',
            self::WRITER_ROLES => json_encode(array_values(array_unique(array_filter((array) ($input['writer_roles'] ?? []), 'is_string')))),
            self::MONTHLY_LIMIT => (string) max(0, (int) ($input['monthly_limit'] ?? 0)),
        ];

        foreach (self::KEYS as $provider => [$keyOption, $modelOption, , $pattern, $help]) {
            $key = trim((string) ($input[$provider.'_key'] ?? ''));
            if (!empty($input[$provider.'_key_remove'])) {
                $values[$keyOption] = '';
            } elseif ($key !== '' && !str_contains($key, self::MASK)) {
                if (preg_match($pattern, $key)) {
                    $values[$keyOption] = Crypt::encryptString($key);
                } else {
                    $problems[] = 'The '.(self::KEY_INFO[$provider][0] ?? $provider).' key was not saved: '.$help;
                }
            }

            $model = trim((string) ($input[$provider.'_model'] ?? ''));
            if ($model !== '' && (isset($models[$provider][$model]) || preg_match('/^[A-Za-z0-9._:\-]{2,100}$/', $model))) {
                $values[$modelOption] = $model;
            }
        }

        foreach ($values as $key => $value) {
            DB::table('cms_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now()]);
        }
        forget_cms_options_cache();

        return $problems;
    }

    /** Every option the plugin keeps (removed with its data on uninstall). */
    public static function forget(): void
    {
        $keys = [self::PROVIDER, self::LANGUAGE, self::TONE, self::WRITER_ROLES, self::MONTHLY_LIMIT];
        foreach (self::KEYS as [$keyOption, $modelOption]) {
            $keys[] = $keyOption;
            $keys[] = $modelOption;
        }
        DB::table('cms_settings')->whereIn('key', $keys)->delete();
        forget_cms_options_cache();
    }
}

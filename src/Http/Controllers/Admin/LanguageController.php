<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use FalconCms\Core\Models\Language;

class LanguageController extends Controller
{
    public function index()
    {
        if (!auth()->user()->hasPermission('manage_settings') && !auth()->user()->hasPermission('access_languages')) {
            abort(403);
        }

        $topCountries = [
            ['name' => 'United States', 'code' => 'en', 'flag' => '🇺🇸'],
            ['name' => 'United Kingdom', 'code' => 'gb', 'flag' => '🇬🇧'],
            ['name' => 'Bangladesh', 'code' => 'bn', 'flag' => '🇧🇩'],
            ['name' => 'France', 'code' => 'fr', 'flag' => '🇫🇷'],
            ['name' => 'Spain', 'code' => 'es', 'flag' => '🇪🇸'],
            ['name' => 'Germany', 'code' => 'de', 'flag' => '🇩🇪'],
            ['name' => 'China', 'code' => 'cn', 'flag' => '🇨🇳'],
            ['name' => 'Saudi Arabia', 'code' => 'sa', 'flag' => '🇸🇦'],
            ['name' => 'India', 'code' => 'in', 'flag' => '🇮🇳'],
            ['name' => 'Japan', 'code' => 'jp', 'flag' => '🇯🇵'],
            ['name' => 'Portugal', 'code' => 'pt', 'flag' => '🇵🇹'],
            ['name' => 'Italy', 'code' => 'it', 'flag' => '🇮🇹'],
            ['name' => 'Russia', 'code' => 'ru', 'flag' => '🇷🇺'],
            ['name' => 'Turkey', 'code' => 'tr', 'flag' => '🇹🇷'],
            ['name' => 'Netherlands', 'code' => 'nl', 'flag' => '🇳🇱'],
            ['name' => 'Vietnam', 'code' => 'vi', 'flag' => '🇻🇳'],
            ['name' => 'Thailand', 'code' => 'th', 'flag' => '🇹🇭'],
            ['name' => 'Korea', 'code' => 'kr', 'flag' => '🇰🇷'],
            ['name' => 'Brazil', 'code' => 'br', 'flag' => '🇧🇷'],
        ];

        $languages = Language::all();
        $displayMode = get_cms_option('lang_switcher_display', 'both');
        
        return view('falcon-cms::admin.languages.index', compact('languages', 'topCountries', 'displayMode'));
    }

    public function updateSettings(Request $request)
    {
        if (!auth()->user()->hasPermission('manage_settings') && !auth()->user()->hasPermission('access_languages')) {
            abort(403);
        }

        $request->validate([
            'lang_switcher_display' => 'required|string|in:both,text_only,flag_only,code_only'
        ]);

        update_cms_option('lang_switcher_display', $request->lang_switcher_display);

        return redirect()->back()->with('success', 'Switcher settings updated successfully!');
    }

    public function store(Request $request)
    {
        if ($request->has('sync_mode')) {
            $submittedCodes = [];
            foreach ($request->input('countries', []) as $countryJson) {
                $country = json_decode($countryJson, true);
                if ($country) {
                    Language::updateOrCreate(
                        ['code' => $country['code']],
                        ['name' => $country['name'], 'flag' => $country['flag'], 'status' => true]
                    );
                    $submittedCodes[] = $country['code'];
                }
            }
            return redirect()->back()->with('success', 'Languages updated successfully!');
        }

        if ($request->has('country_data')) {
            $country = json_decode($request->input('country_data'), true);
            if (!$country) return redirect()->back()->with('error', 'Invalid country data!');

            Language::updateOrCreate(
                ['code' => $country['code']],
                ['name' => $country['name'], 'flag' => $country['flag'], 'status' => true]
            );

            return redirect()->back()->with('success', 'Language added successfully!');
        }

        // Fallback for manual form if needed
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:5|unique:cms_languages,code',
        ]);

        Language::create($request->all());
        return redirect()->back()->with('success', 'Language added successfully!');
    }

    public function setDefault($id)
    {
        Language::where('is_default', true)->update(['is_default' => false]);
        Language::where('id', $id)->update(['is_default' => true]);
        return redirect()->back()->with('success', 'Default language updated!');
    }

    public function destroy($id)
    {
        $lang = Language::findOrFail($id);
        if ($lang->is_default) {
            return redirect()->back()->with('error', 'Cannot delete default language!');
        }
        $lang->delete();
        return redirect()->back()->with('success', 'Language deleted!');
    }
}

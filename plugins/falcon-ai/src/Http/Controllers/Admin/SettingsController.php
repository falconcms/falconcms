<?php

namespace FalconAi\Http\Controllers\Admin;

use FalconAi\Http\Requests\SaveSettingsRequest;
use FalconAi\Support\Access;
use FalconAi\Support\AiManager;
use FalconAi\Support\Settings;
use FalconAi\Support\Usage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** AI → Settings: the provider, the keys, the defaults, who may write with AI, and this month's use. */
class SettingsController extends Controller
{
    public function __construct(private AiManager $ai) {}

    public function index(Request $request)
    {
        abort_unless(Access::canManage($request->user()), 403);

        $providers = $this->ai->providers();

        return view('falcon-ai::admin.settings', [
            'providers' => $providers,
            'chosen' => Settings::provider(),
            'masked' => array_map(fn ($p) => Settings::masked($p->id()), $providers),
            'models' => array_map(fn ($p) => $p->models(), $providers),
            'model' => array_map(fn ($p) => $p->model(), $providers),
            'language' => Settings::language(),
            'tone' => Settings::tone(),
            'roles' => DB::table('roles')->whereNotIn('slug', ['administrator', 'super-admin'])->orderBy('name')->pluck('name', 'slug')->all(),
            'writerRoles' => Settings::writerRoles(),
            'limit' => Settings::monthlyLimit(),
            'usage' => Usage::thisMonth(),
            'breakdown' => Usage::breakdown(),
        ]);
    }

    public function save(SaveSettingsRequest $request)
    {
        $models = array_map(fn ($p) => $p->models(), $this->ai->providers());
        $problems = Settings::save($request->validated(), $models);

        return redirect()->route('admin.ai.settings')
            ->with($problems ? 'error' : 'success', $problems ? implode(' ', $problems) : 'AI settings saved.');
    }

    /** "Test" beside a key: a call that costs nothing. */
    public function test(Request $request): JsonResponse
    {
        abort_unless(Access::canManage($request->user()), 403);
        $data = $request->validate(['provider' => ['required', Rule::in(array_keys($this->ai->providers()))]]);

        return response()->json($this->ai->provider($data['provider'])->test());
    }
}

<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use FalconCms\Core\Pro\LicenseGateway;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Admin License page — paste a Pro license key, activate or deactivate it.
 *
 * The key is stored as a CMS option ('falcon_license_key'); the Pro package's
 * gateway reads it (see FalconProServiceProvider) and unlocks features when it
 * resolves to a valid plan. Activation only unlocks features when the
 * `falconcms/pro` package is actually installed — otherwise the key is saved but
 * the gateway stays Null and the page reports that Pro still needs installing.
 */
class LicenseController extends Controller
{
    public function index()
    {
        $this->authorizeAccess();

        $gateway = app(LicenseGateway::class);

        return view('falcon-cms::admin.license.index', [
            'licensed'    => $gateway->licensed(),
            'plan'        => $gateway->plan(),
            'features'    => $gateway->features(),
            'proInstalled'=> $this->proInstalled(),
            'maskedKey'   => $this->maskKey((string) get_cms_option('falcon_license_key', '')),
            'hasKey'      => (string) get_cms_option('falcon_license_key', '') !== '',
        ]);
    }

    public function activate(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'license_key' => ['required', 'string', 'max:191'],
        ]);

        update_cms_option('falcon_license_key', trim($validated['license_key']));

        // The saved key takes effect on the next request (the redirect below),
        // where the gateway resolves it. index() then reports the real status.
        falcon_log_activity('license_activated', 'Saved a Pro license key');

        return redirect()->route('admin.license.index')
            ->with('success', 'License key saved — see the status below.');
    }

    public function deactivate()
    {
        $this->authorizeAccess();

        update_cms_option('falcon_license_key', '');
        falcon_log_activity('license_deactivated', 'Removed the Pro license key');

        return redirect()->route('admin.license.index')
            ->with('warning', 'License deactivated. Pro features are now locked.');
    }

    private function authorizeAccess(): void
    {
        if (! auth()->check() || ! auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }
    }

    /** Whether the falconcms/pro package is physically installed. */
    private function proInstalled(): bool
    {
        return class_exists(\FalconCms\Pro\FalconProServiceProvider::class);
    }

    /** Show only the first/last few chars of the key, never the whole thing. */
    private function maskKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }
        if (strlen($key) <= 8) {
            return str_repeat('•', max(0, strlen($key) - 2)) . substr($key, -2);
        }
        return substr($key, 0, 4) . str_repeat('•', 8) . substr($key, -4);
    }
}

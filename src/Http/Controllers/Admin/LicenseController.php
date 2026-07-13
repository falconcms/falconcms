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
            'hasToken'    => $this->hasAccessToken(),
            'licenseError'=> $this->currentLicenseError(),
        ]);
    }

    /** The human-readable reason the stored key isn't active right now, or null. */
    private function currentLicenseError(): ?string
    {
        $state = json_decode((string) get_cms_option('falcon_license_state', ''), true);
        $error = is_array($state) ? ($state['error'] ?? null) : null;

        return $error ? $this->explainError($error) : null;
    }

    public function activate(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'license_key' => ['required', 'string', 'max:191'],
        ]);

        update_cms_option('falcon_license_key', trim($validated['license_key']));
        // Drop any cached validation result so the new key is re-checked with the
        // provider immediately, instead of serving a stale result for up to 12h.
        update_cms_option('falcon_license_state', '');
        falcon_log_activity('license_activated', 'Saved a Pro license key');

        // Without the Pro package there is nothing to validate against yet.
        if (! $this->proInstalled()) {
            return redirect()->route('admin.license.index')
                ->with('success', 'License key saved. Now install the Pro package below to activate it.');
        }

        // Pro is installed → resolve the new key right now so we can tell the admin
        // whether it actually worked, instead of a vague "saved". Forget the cached
        // gateway so it rebuilds with the key we just stored.
        app()->forgetInstance(\FalconCms\Pro\License\LicenseManager::class);
        app()->forgetInstance(LicenseGateway::class);
        $gateway = app(LicenseGateway::class);

        if ($gateway->licensed()) {
            return redirect()->route('admin.license.index')
                ->with('success', 'License activated — Pro (' . $gateway->plan() . ') is now active on this site.');
        }

        $state = json_decode((string) get_cms_option('falcon_license_state', ''), true);
        return redirect()->route('admin.license.index')
            ->with('error', $this->explainError(is_array($state) ? ($state['error'] ?? null) : null));
    }

    /** Turn a validator error code into a message the admin can act on. */
    private function explainError(?string $error): string
    {
        $error = strtolower((string) $error);

        return match (true) {
            str_contains($error, 'activation limit') => 'This license key has reached its activation limit — it is already active on another site. Deactivate it there first, or buy another license.',
            str_contains($error, 'instance')         => 'This site\'s activation was removed. Click Deactivate, then activate the key again.',
            str_contains($error, 'expired')           => 'This license key has expired. Renew it to continue using Pro.',
            str_contains($error, 'disabled')          => 'This license key has been disabled. Contact support with your order reference.',
            str_contains($error, 'offline'), str_contains($error, 'transient') => 'Could not reach the license server. Check your connection and try again.',
            default => 'This license key is not valid. Double-check that you pasted it correctly and try again.',
        };
    }

    public function deactivate()
    {
        $this->authorizeAccess();

        // Release the activation slot with the provider first (so the key can be
        // re-activated here or moved to another site), then clear the local key.
        // Guarded: older Pro gateways may not implement deactivate().
        $gateway = app(LicenseGateway::class);
        if (method_exists($gateway, 'deactivate')) {
            $gateway->deactivate();
        }
        update_cms_option('falcon_license_key', '');
        update_cms_option('falcon_license_state', '');
        falcon_log_activity('license_deactivated', 'Removed the Pro license key');

        return redirect()->route('admin.license.index')
            ->with('warning', 'License deactivated. Pro features are now locked.');
    }

    /**
     * Force an immediate re-validation with the provider — for when a license was
     * just deactivated/moved at the provider and the admin doesn't want to wait for
     * the cached result to expire.
     */
    public function recheck()
    {
        $this->authorizeAccess();

        // Drop the cached result and the resolved gateway so the next resolve hits
        // the provider fresh.
        update_cms_option('falcon_license_state', '');
        app()->forgetInstance(\FalconCms\Pro\License\LicenseManager::class);
        app()->forgetInstance(LicenseGateway::class);

        if (! $this->proInstalled()) {
            return redirect()->route('admin.license.index')
                ->with('warning', 'Re-checked, but the Pro package is not installed on this site.');
        }

        $gateway = app(LicenseGateway::class);
        if ($gateway->licensed()) {
            return redirect()->route('admin.license.index')
                ->with('success', 'Re-checked — Pro (' . $gateway->plan() . ') is active.');
        }

        return redirect()->route('admin.license.index')
            ->with('warning', 'Re-checked — ' . ($this->currentLicenseError() ?? 'the license is not active.'));
    }

    /**
     * Save the GitHub access token into the project's auth.json so Composer can
     * download the private falconcms/pro package — the customer pastes it here
     * instead of hand-editing the file or fighting shell escaping.
     */
    public function saveToken(Request $request)
    {
        $this->authorizeAccess();

        $data  = $request->validate([
            'access_token' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-]+$/'],
        ]);
        $token = trim($data['access_token']);

        $path = base_path('auth.json');
        $auth = [];
        if (is_file($path)) {
            $existing = json_decode((string) @file_get_contents($path), true);
            if (is_array($existing)) {
                $auth = $existing;
            }
        }
        $auth['github-oauth'] = array_merge($auth['github-oauth'] ?? [], ['github.com' => $token]);

        $ok = @file_put_contents(
            $path,
            json_encode($auth, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        if ($ok === false) {
            return redirect()->route('admin.license.index')->with(
                'warning',
                'Could not write auth.json — the web server has no write permission on the project root. '
                . 'Create the file manually using the instructions below.'
            );
        }

        $this->gitignore('auth.json');
        falcon_log_activity('license_token_saved', 'Saved the Pro access token to auth.json');

        // Pro already installed → nothing more to do.
        if ($this->proInstalled()) {
            return redirect()->route('admin.license.index')
                ->with('success', 'Access token updated.');
        }

        // Try to install Pro automatically with the token we just saved, so the
        // customer never has to touch a terminal. Falls back to the manual
        // commands (shown on the page) if Composer isn't runnable here.
        $install = $this->installProPackage();

        if ($install['ok']) {
            return redirect()->route('admin.license.index')->with(
                'success',
                'Access token saved and the Pro package was installed. Reload in a moment — if your license key is set, Pro is now active.'
            );
        }

        return redirect()->route('admin.license.index')->with(
            'warning',
            'Access token saved, but the Pro package could not be installed automatically (' . $install['reason'] . '). Run the two commands below manually.'
        );
    }

    /**
     * Add the private repository and `composer require falconcms/pro`, using the
     * token already written to auth.json. Best effort — many shared hosts disable
     * exec() or ship without a CLI Composer, hence the manual fallback.
     *
     * @return array{ok:bool,reason?:string,output?:string}
     */
    private function installProPackage(): array
    {
        @set_time_limit(300);

        $composer = $this->findComposerBinary();
        if (! $composer) {
            return ['ok' => false, 'reason' => 'Composer was not found on the server'];
        }
        if (! is_writable(base_path('composer.json')) || ! is_writable(base_path('vendor'))) {
            return ['ok' => false, 'reason' => 'the project files are not writable by the web server'];
        }

        $base = escapeshellarg(base_path());
        $repo = 'https://github.com/falconcms/falconcms-pro.git';

        exec('cd ' . $base . ' && ' . $composer . ' config repositories.falconcms-pro vcs ' . escapeshellarg($repo) . ' 2>&1', $o1, $e1);
        exec('cd ' . $base . ' && ' . $composer . ' require falconcms/pro --no-interaction --prefer-dist --no-progress 2>&1', $o2, $e2);

        if ($e2 !== 0) {
            $output = implode("\n", array_merge((array) $o1, (array) $o2));
            $reason = preg_match('/authenticat|denied|\b40[34]\b|could not find package|not authorized/i', $output)
                ? 'the access token may be wrong or lacks access to the repository'
                : 'see the manual commands below';

            return ['ok' => false, 'reason' => $reason, 'output' => $output];
        }

        // Refresh the php-fpm OPcache so the just-installed provider is picked up
        // on the redirect instead of serving stale bytecode.
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        cache()->forget('falcon_cms_update_check');

        return ['ok' => true];
    }

    /** Locate a runnable Composer binary (mirrors the dashboard updater). */
    private function findComposerBinary(): ?string
    {
        foreach ([base_path('composer.phar'), '/usr/local/bin/composer', '/usr/bin/composer', '/usr/local/bin/composer.phar'] as $p) {
            if (is_file($p)) {
                return str_ends_with($p, '.phar') ? 'php ' . escapeshellarg($p) : escapeshellarg($p);
            }
        }
        $which = shell_exec('which composer 2>/dev/null');
        if ($which && trim($which)) {
            return 'composer';
        }
        $where = shell_exec('where composer 2>nul');

        return ($where && trim($where)) ? 'composer' : null;
    }

    /** Ensure a path is listed in the project's .gitignore (best effort). */
    private function gitignore(string $entry): void
    {
        $file = base_path('.gitignore');
        $body = is_file($file) ? (string) @file_get_contents($file) : '';
        if (! preg_match('/^\s*\/?' . preg_quote($entry, '/') . '\s*$/m', $body)) {
            @file_put_contents($file, rtrim($body) . "\n" . $entry . "\n");
        }
    }

    /** Whether a GitHub token is already stored in the project's auth.json. */
    private function hasAccessToken(): bool
    {
        $path = base_path('auth.json');
        if (! is_file($path)) {
            return false;
        }
        $auth = json_decode((string) @file_get_contents($path), true);

        return ! empty($auth['github-oauth']['github.com'] ?? null);
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

<?php

namespace FalconCms\Core\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Settings → Site Health.
 *
 * Two kinds of result, like the WordPress screen it is modelled on:
 *
 *  - checks(): fast tests run while the page renders — PHP, database, configuration,
 *    security settings, file permissions. Each returns a status (good / recommended /
 *    critical), what it found, why it matters and what to do about it.
 *  - asyncTest(): the slow ones, fetched by the page one at a time after it has loaded —
 *    the dependency vulnerability audit (Packagist's advisory database), the code scan of
 *    the site's own code, the error log, updates and disk usage. A slow network or a large
 *    log then delays one card, not the whole screen.
 *
 * info() is the plain inventory behind it all — server, PHP, database, Laravel, FalconCMS,
 * directories — and copies out as text for a support request.
 *
 * Nothing here changes anything. Every test only reads.
 */
class SiteHealth
{
    const GOOD = 'good';

    const RECOMMENDED = 'recommended';

    const CRITICAL = 'critical';

    /** The slow tests the page runs after loading, in the order it runs them. */
    const ASYNC = [
        'dependencies' => 'Dependency vulnerabilities',
        'code_scan' => 'Code security scan',
        'error_log' => 'Error log',
        'updates' => 'Updates',
        'disk' => 'Disk usage',
    ];

    /** PHP branches and the day each stops getting security fixes (php.net/supported-versions). */
    const PHP_EOL = [
        '8.1' => '2025-12-31',
        '8.2' => '2026-12-31',
        '8.3' => '2027-12-31',
        '8.4' => '2028-12-31',
        '8.5' => '2029-12-31',
    ];

    // ── Fast checks ────────────────────────────────────────────────────────

    public static function checks(): array
    {
        $out = [];
        foreach ([
            'phpVersion', 'phpExtensions', 'phpLimits', 'opcache',
            'database', 'pendingMigrations',
            'appDebug', 'appKey', 'https', 'sessionCookie', 'exposedFiles', 'publicPhpFiles',
            'writableDirs', 'storageLink', 'envPermissions',
            'cacheDriver', 'queueDriver', 'mailer', 'productionCaches', 'maintenance',
            'timezone', 'proLicense',
        ] as $method) {
            try {
                foreach ((array) self::$method() as $result) {
                    if ($result) {
                        $out[] = $result;
                    }
                }
            } catch (Throwable $e) {
                $out[] = self::result('check_'.$method, 'Could not run the '.Str::headline($method).' check', self::RECOMMENDED, 'Site',
                    'The check itself failed: '.$e->getMessage());
            }
        }

        // Plugins add their own checks: each an array of id, label, status (good, recommended,
        // critical), category, description, and optionally action and details.
        try {
            $added = apply_falcon_filters('falcon_site_health_checks', []);
            foreach (is_array($added) ? $added : [] as $result) {
                if (is_array($result) && isset($result['id'], $result['label'], $result['status'])) {
                    $out[] = $result + ['category' => 'Plugins', 'description' => '', 'action' => null, 'details' => []];
                }
            }
        } catch (Throwable $e) {
            $out[] = self::result('check_plugins', 'Could not run the plugins\' checks', self::RECOMMENDED, 'Plugins',
                'A plugin\'s check failed: '.$e->getMessage());
        }

        return $out;
    }

    private static function result(string $id, string $label, string $status, string $category, string $description, ?string $action = null, array $details = []): array
    {
        return compact('id', 'label', 'status', 'category', 'description', 'action', 'details');
    }

    private static function isProduction(): bool
    {
        return app()->environment('production');
    }

    /**
     * Is this a developer's own machine — not production AND reached on a local address
     * (localhost, 127.0.0.1, *.test, *.local, *.localhost)? Debug mode and plain HTTP are
     * how a site is built there, so they are not counted against it. Both halves are needed:
     * a public server left on APP_ENV=local is exactly the case that must NOT pass.
     */
    public static function isDevMachine(): bool
    {
        if (self::isProduction()) {
            return false;
        }
        $host = strtolower((string) request()->getHost());

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || (bool) preg_match('/\.(test|local|localhost)$/', $host);
    }

    private static function phpVersion(): array
    {
        $branch = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
        $eol = self::PHP_EOL[$branch] ?? null;
        $daysLeft = $eol ? (int) now()->startOfDay()->diffInDays($eol, false) : null;

        if (version_compare(PHP_VERSION, '8.2.0', '<') || ($daysLeft !== null && $daysLeft < 0)) {
            return [self::result('php_version', 'PHP '.PHP_VERSION.' no longer gets security fixes', self::CRITICAL, 'Security',
                'PHP '.$branch.' reached end of life'.($eol ? ' on '.$eol : '').'. Vulnerabilities found in it are no longer fixed.',
                'Ask your host to move the site to PHP 8.3 or newer.')];
        }
        if ($daysLeft !== null && $daysLeft < 120) {
            return [self::result('php_version', 'PHP '.PHP_VERSION.' stops getting security fixes soon', self::RECOMMENDED, 'Security',
                'PHP '.$branch.' gets security fixes until '.$eol.' ('.$daysLeft.' days).',
                'Plan the move to PHP 8.3 or newer before then.')];
        }

        return [self::result('php_version', 'PHP '.PHP_VERSION.' is supported', self::GOOD, 'Performance',
            'PHP '.$branch.' gets security fixes'.($eol ? ' until '.$eol : '').'.')];
    }

    private static function phpExtensions(): array
    {
        $required = ['pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'curl'];
        $recommended = ['gd' => 'resizing and optimising uploaded images', 'intl' => 'dates, numbers and slugs in other languages',
            'zip' => 'installing plugins and themes, backups', 'bcmath' => 'exact money arithmetic in the shop',
            'exif' => 'rotating phone photos the right way up'];

        $missing = array_values(array_filter($required, fn ($e) => !extension_loaded($e)));
        if (!extension_loaded('pdo_mysql') && !extension_loaded('pdo_sqlite') && !extension_loaded('pdo_pgsql')) {
            $missing[] = 'pdo_mysql';
        }
        if ($missing) {
            return [self::result('php_extensions', 'Required PHP extensions are missing', self::CRITICAL, 'Site',
                'Missing: '.implode(', ', $missing).'. Parts of the site will fail without them.',
                'Enable them in php.ini or ask your host.')];
        }

        $soft = [];
        foreach ($recommended as $ext => $why) {
            if (!extension_loaded($ext) && !($ext === 'gd' && extension_loaded('imagick'))) {
                $soft[] = $ext.' ('.$why.')';
            }
        }
        if ($soft) {
            return [self::result('php_extensions', 'Some recommended PHP extensions are missing', self::RECOMMENDED, 'Performance',
                'Missing: '.implode('; ', $soft).'.', 'Enable them in php.ini or ask your host.')];
        }

        return [self::result('php_extensions', 'All required and recommended PHP extensions are installed', self::GOOD, 'Site',
            count(get_loaded_extensions()).' extensions are loaded.')];
    }

    private static function bytes($value): int
    {
        $v = trim((string) $value);
        if ($v === '' || $v === '-1') {
            return -1;
        }
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n,
        };
    }

    private static function phpLimits(): array
    {
        $issues = [];
        $mem = self::bytes(ini_get('memory_limit'));
        if ($mem !== -1 && $mem < 256 * 1024 ** 2) {
            $issues[] = 'memory_limit is '.ini_get('memory_limit').' (256M recommended — the builder, imports and image work need it)';
        }
        $upload = self::bytes(ini_get('upload_max_filesize'));
        if ($upload !== -1 && $upload < 16 * 1024 ** 2) {
            $issues[] = 'upload_max_filesize is '.ini_get('upload_max_filesize').' (16M or more lets people upload phone photos and short videos)';
        }
        $post = self::bytes(ini_get('post_max_size'));
        if ($post !== -1 && $upload !== -1 && $post < $upload) {
            $issues[] = 'post_max_size ('.ini_get('post_max_size').') is smaller than upload_max_filesize — uploads above it fail silently';
        }
        $exec = (int) ini_get('max_execution_time');
        if ($exec > 0 && $exec < 60 && PHP_SAPI !== 'cli') {
            $issues[] = 'max_execution_time is '.$exec.'s (60s or more lets backups and imports finish)';
        }

        return [$issues
            ? self::result('php_limits', 'Some PHP limits are low', self::RECOMMENDED, 'Performance', implode('. ', $issues).'.', 'Raise them in php.ini or ask your host.')
            : self::result('php_limits', 'PHP limits are comfortable', self::GOOD, 'Performance',
                'memory_limit '.ini_get('memory_limit').', upload_max_filesize '.ini_get('upload_max_filesize').', post_max_size '.ini_get('post_max_size').'.')];
    }

    private static function opcache(): array
    {
        $on = function_exists('opcache_get_status') && ($s = @opcache_get_status(false)) && !empty($s['opcache_enabled']);
        if ($on) {
            return [self::result('opcache', 'OPcache is on', self::GOOD, 'Performance', 'Compiled PHP is kept in memory between requests.')];
        }

        return [self::result('opcache', 'OPcache is off', self::isProduction() ? self::RECOMMENDED : self::GOOD, 'Performance',
            'Without OPcache PHP recompiles every file on every request — pages are noticeably slower.'.(self::isProduction() ? '' : ' (Normal on a development machine.)'),
            self::isProduction() ? 'Enable opcache in php.ini (opcache.enable=1).' : null)];
    }

    private static function database(): array
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            return [self::result('database', 'Cannot connect to the database', self::CRITICAL, 'Database', $e->getMessage(), 'Check DB_HOST, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env.')];
        }

        $driver = DB::connection()->getDriverName();
        $version = self::dbVersion();
        $out = [];
        if (in_array($driver, ['mysql', 'mariadb'], true) && $version) {
            $isMaria = stripos($version, 'mariadb') !== false;
            preg_match('/^(\d+\.\d+)/', $version, $m);
            $num = $m[1] ?? '0';
            $old = $isMaria ? version_compare($num, '10.6', '<') : version_compare($num, '8.0', '<');
            $out[] = $old
                ? self::result('database', ($isMaria ? 'MariaDB ' : 'MySQL ').$num.' is out of support', self::RECOMMENDED, 'Database',
                    'This version no longer gets fixes. Newer versions are faster with JSON columns, which the builder uses.',
                    'Upgrade to '.($isMaria ? 'MariaDB 10.11' : 'MySQL 8.0').' or newer.')
                : self::result('database', 'Database connection works', self::GOOD, 'Database', ($isMaria ? 'MariaDB ' : 'MySQL ').$version.', database "'.DB::connection()->getDatabaseName().'".');
        } else {
            $out[] = self::result('database', 'Database connection works', self::GOOD, 'Database', ucfirst($driver).' '.$version.'.');
        }

        // Tables without a primary key or on MyISAM lose data on a crash and cannot use transactions.
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            try {
                $myisam = DB::select('SELECT TABLE_NAME AS t FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND ENGINE = ?', [DB::connection()->getDatabaseName(), 'MyISAM']);
                if ($myisam) {
                    $out[] = self::result('db_engine', count($myisam).' table(s) use the MyISAM engine', self::RECOMMENDED, 'Database',
                        'MyISAM has no transactions or crash recovery: '.implode(', ', array_map(fn ($r) => $r->t, array_slice($myisam, 0, 15))).'.',
                        'Convert them: ALTER TABLE name ENGINE=InnoDB;');
                }
            } catch (Throwable $e) {
            }
        }

        return $out;
    }

    public static function dbVersion(): ?string
    {
        try {
            return match (DB::connection()->getDriverName()) {
                'sqlite' => DB::selectOne('select sqlite_version() as v')->v ?? null,
                'pgsql' => DB::selectOne('show server_version')->server_version ?? null,
                'sqlsrv' => DB::selectOne('select @@version as v')->v ?? null,
                default => DB::selectOne('select version() as v')->v ?? null,
            };
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function pendingMigrations(): array
    {
        $migrator = app('migrator');
        if (!$migrator->repositoryExists()) {
            return [self::result('migrations', 'The migrations table is missing', self::CRITICAL, 'Database', 'The database has not been set up.', 'Run: php artisan migrate')];
        }
        $paths = array_merge([database_path('migrations')], $migrator->paths());
        $files = $migrator->getMigrationFiles($paths);
        $ran = $migrator->getRepository()->getRan();
        $pending = array_values(array_diff(array_keys($files), $ran));

        return [$pending
            ? self::result('migrations', count($pending).' database migration(s) have not run', self::CRITICAL, 'Database',
                'The code expects database changes that are not there yet, so some screens can fail: '.implode(', ', array_slice($pending, 0, 8)).(count($pending) > 8 ? '…' : '').'.',
                'Run: php artisan migrate --force (the dashboard Update button does this too).')
            : self::result('migrations', 'The database is up to date', self::GOOD, 'Database', count($ran).' migrations have run.')];
    }

    private static function appDebug(): array
    {
        if (!config('app.debug')) {
            return [self::result('app_debug', 'Debug mode is off', self::GOOD, 'Security', 'Errors show a plain error page, not the code behind it.')];
        }
        if (self::isDevMachine()) {
            return [self::result('app_debug', 'Debug mode is on — normal on this development machine', self::GOOD, 'Security',
                'APP_ENV='.app()->environment().' on '.request()->getHost().', so detailed errors only reach you.',
                'Before the site goes live: APP_ENV=production and APP_DEBUG=false.')];
        }

        // Debug on and reached on a public address: visitors see the stack traces, whatever
        // APP_ENV says.
        return [self::result('app_debug', 'Debug mode is ON on a live site', self::CRITICAL, 'Security',
            'Any error shows visitors the full stack trace — file paths, code, and often database credentials and keys from the environment.'
            .(self::isProduction() ? '' : ' APP_ENV is "'.app()->environment().'" but the site is reached at '.request()->getHost().', which is not a local address.'),
            'Set APP_ENV=production and APP_DEBUG=false in .env, then run php artisan config:clear.')];
    }

    private static function appKey(): array
    {
        $key = (string) config('app.key');

        return [$key === '' || strlen($key) < 32
            ? self::result('app_key', 'The application key is missing', self::CRITICAL, 'Security', 'Sessions, cookies and encrypted settings are not protected.', 'Run: php artisan key:generate')
            : self::result('app_key', 'The application key is set', self::GOOD, 'Security', 'Sessions and encrypted values are signed with it.')];
    }

    private static function https(): array
    {
        $appHttps = str_starts_with((string) config('app.url'), 'https://');
        $requestHttps = request()->isSecure();
        if ($appHttps || $requestHttps) {
            return [self::result('https', 'The site uses HTTPS', self::GOOD, 'Security', 'Traffic between visitors and the site is encrypted.'.(!$appHttps ? ' (APP_URL still says http:// — update it so links and emails use https.)' : ''))];
        }
        if (self::isDevMachine()) {
            return [self::result('https', 'Plain HTTP — normal on this development machine', self::GOOD, 'Security',
                'Traffic to '.request()->getHost().' never leaves this computer.',
                'When the site goes live, install a certificate (most hosts offer a free Let\'s Encrypt one) and set APP_URL to https://.')];
        }

        return [self::result('https', 'The site is not using HTTPS', self::CRITICAL, 'Security',
            'Logins, passwords and orders travel unencrypted.',
            'Install a certificate (most hosts offer a free Let\'s Encrypt one) and set APP_URL to https://.')];
    }

    private static function sessionCookie(): array
    {
        if (!request()->isSecure() && !str_starts_with((string) config('app.url'), 'https://')) {
            return [];
        }

        return [config('session.secure')
            ? self::result('session_secure', 'Session cookies are HTTPS-only', self::GOOD, 'Security', 'The login cookie is never sent over plain HTTP.')
            : self::result('session_secure', 'Session cookies can travel over plain HTTP', self::RECOMMENDED, 'Security',
                'On an HTTPS site the login cookie should be marked Secure so it can never leak over an http:// request.',
                'Set SESSION_SECURE_COOKIE=true in .env.')];
    }

    private static function exposedFiles(): array
    {
        $found = [];
        foreach (['.env', '.env.backup', '.env.production', '.git', 'composer.json', 'composer.lock', 'storage', '.htpasswd', 'database.sqlite', 'phpinfo.php', 'info.php', 'adminer.php'] as $name) {
            // public/storage is meant to be there when it is the storage:link — a symlink, or on
            // Windows a junction, which is_link() does not recognise; compare where it leads.
            if ($name === 'storage' && (is_link(public_path($name)) || realpath(public_path($name)) === realpath(storage_path('app/public')))) {
                continue;
            }
            if (file_exists(public_path($name))) {
                $found[] = $name;
            }
        }
        foreach ((array) glob(public_path('*.{sql,zip,tar,gz,bak,log}'), GLOB_BRACE) as $file) {
            $found[] = basename($file);
        }

        return [$found
            ? self::result('exposed_files', 'Sensitive files are inside the public folder', self::CRITICAL, 'Security',
                'Anyone can download these from the web: '.implode(', ', $found).'. A .env file there gives away the database password and app key.',
                'Move or delete them from '.public_path().'. Only index.php and assets belong there.', ['files' => $found])
            : self::result('exposed_files', 'No sensitive files in the public folder', self::GOOD, 'Security', 'No .env, .git, backups, dumps or info scripts are reachable from the web.')];
    }

    /** PHP files in public/ other than the front controller — the classic sign of an uploaded web shell. */
    private static function publicPhpFiles(): array
    {
        $found = [];
        $root = public_path();
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            $n = 0;
            foreach ($it as $file) {
                if (++$n > 20000) {
                    break;
                }
                if (!$file->isFile() || !preg_match('/\.(php\d?|phtml|phar)$/i', $file->getFilename())) {
                    continue;
                }
                $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
                if ($rel === 'index.php') {
                    continue;
                }
                $found[] = $rel;
            }
        } catch (Throwable $e) {
        }

        return [$found
            ? self::result('public_php', count($found).' unexpected PHP file(s) in the public folder', self::CRITICAL, 'Security',
                'Only index.php should run from the public folder. Extra PHP files there — especially inside uploads — are how attackers keep a back door: '.implode(', ', array_slice($found, 0, 12)).(count($found) > 12 ? '…' : '').'.',
                'Open each one. If you did not put it there, delete it and change your passwords.', ['files' => $found])
            : self::result('public_php', 'No stray PHP files in the public folder', self::GOOD, 'Security', 'Only index.php can run from the web root.')];
    }

    private static function writableDirs(): array
    {
        $bad = [];
        foreach ([storage_path(), storage_path('app'), storage_path('framework/cache'), storage_path('framework/sessions'),
            storage_path('framework/views'), storage_path('logs'), base_path('bootstrap/cache')] as $dir) {
            if (!is_dir($dir) || !is_writable($dir)) {
                $bad[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $dir);
            }
        }

        return [$bad
            ? self::result('writable', 'Some folders are not writable', self::CRITICAL, 'Site',
                'The site cannot write to: '.implode(', ', $bad).'. Caching, sessions, uploads or logs will fail.',
                'Give the web server write access (e.g. chmod -R 775 storage bootstrap/cache).')
            : self::result('writable', 'Storage folders are writable', self::GOOD, 'Site', 'Cache, sessions, logs and uploads can be saved.')];
    }

    private static function storageLink(): array
    {
        $link = public_path('storage');

        return [file_exists($link)
            ? self::result('storage_link', 'Uploaded files are reachable', self::GOOD, 'Site', 'public/storage points at storage/app/public.')
            : self::result('storage_link', 'public/storage is missing', self::CRITICAL, 'Site', 'Uploaded images and files will not load on the site.', 'Run: php artisan storage:link')];
    }

    private static function envPermissions(): array
    {
        $env = base_path('.env');
        if (!file_exists($env) || DIRECTORY_SEPARATOR === '\\') {
            return [];
        }
        $perms = fileperms($env) & 0777;

        return [($perms & 0004)
            ? self::result('env_perms', '.env is readable by every user on the server', self::RECOMMENDED, 'Security',
                'Permissions are '.decoct($perms).'. On shared hosting other accounts could read your database password.', 'Run: chmod 640 .env')
            : self::result('env_perms', '.env permissions are restricted', self::GOOD, 'Security', 'Permissions are '.decoct($perms).'.')];
    }

    private static function cacheDriver(): array
    {
        $store = config('cache.default');

        return [$store === 'array' && self::isProduction()
            ? self::result('cache', 'Nothing is cached between requests', self::RECOMMENDED, 'Performance', 'CACHE_STORE=array keeps the cache for one request only, so settings and menus are re-read from the database every time.', 'Use file, database or redis.')
            : self::result('cache', 'Cache: '.$store, self::GOOD, 'Performance', 'Settings, menus and update checks are cached with the "'.$store.'" store.')];
    }

    private static function queueDriver(): array
    {
        $q = config('queue.default');

        return [$q === 'sync' && self::isProduction()
            ? self::result('queue', 'Background jobs run during the request', self::RECOMMENDED, 'Performance', 'QUEUE_CONNECTION=sync makes visitors wait for emails and other jobs.', 'Use the database queue with a worker or cron (php artisan queue:work).')
            : self::result('queue', 'Queue: '.$q, self::GOOD, 'Performance', 'Jobs use the "'.$q.'" connection.')];
    }

    private static function mailer(): array
    {
        $m = config('mail.default');
        if (in_array($m, ['log', 'array'], true)) {
            return [self::result('mail', 'Emails are not actually sent', self::isProduction() ? self::CRITICAL : self::RECOMMENDED, 'Site',
                'MAIL_MAILER='.$m.' writes emails '.($m === 'log' ? 'to the log' : 'nowhere').' — password resets, order receipts and form notifications never arrive.',
                'Set up SMTP (or another mailer) in .env.')];
        }

        return [self::result('mail', 'Mail is sent with '.$m, self::GOOD, 'Site', 'From '.(config('mail.from.address') ?: '(no from address)').'.')];
    }

    private static function productionCaches(): array
    {
        if (!self::isProduction()) {
            return [];
        }
        $missing = [];
        if (!app()->configurationIsCached()) {
            $missing[] = 'config (php artisan config:cache)';
        }
        if (!app()->routesAreCached()) {
            $missing[] = 'routes (php artisan route:cache)';
        }

        return [$missing
            ? self::result('prod_cache', 'Configuration is not cached', self::RECOMMENDED, 'Performance', 'Laravel re-reads its configuration on every request. Not cached: '.implode(', ', $missing).'.', 'Run the commands after each deploy.')
            : self::result('prod_cache', 'Configuration and routes are cached', self::GOOD, 'Performance', 'Laravel boots from its compiled caches.')];
    }

    private static function maintenance(): array
    {
        return app()->isDownForMaintenance()
            ? [self::result('maintenance', 'The site is in maintenance mode', self::CRITICAL, 'Site', 'Visitors see the maintenance page.', 'Run: php artisan up')]
            : [];
    }

    private static function timezone(): array
    {
        // The site's own timezone (Settings → General) is what every date is shown in; the
        // database keeps UTC underneath, which is how it should be.
        $site = function_exists('cms_timezone') ? cms_timezone() : (string) config('app.timezone');
        $chosen = (string) get_cms_option('timezone', '') !== '';

        return [$chosen || $site !== 'UTC'
            ? self::result('timezone', 'Timezone: '.$site, self::GOOD, 'Site', 'Dates and times on the site and in the admin are shown in '.$site.' (stored as UTC underneath).')
            : self::result('timezone', 'No site timezone is set', self::RECOMMENDED, 'Site',
                'Scheduled posts and order times are shown in UTC, which is probably not your local time.',
                'Choose your timezone under Settings → General.')];
    }

    private static function proLicense(): array
    {
        if (!function_exists('falcon_pro_installed_version') || !falcon_pro_installed_version()) {
            return [];
        }
        if (function_exists('falcon_pro_expired') && falcon_pro_expired()) {
            return [self::result('pro', 'FalconCMS Pro updates have expired', self::RECOMMENDED, 'Site',
                'Pro keeps working, but no new Pro versions or security fixes will install.', 'Renew the licence under License.')];
        }

        return [function_exists('falcon_pro_editable') && falcon_pro_editable()
            ? self::result('pro', 'FalconCMS Pro is licensed', self::GOOD, 'Site', 'Pro features and updates are available.')
            : self::result('pro', 'FalconCMS Pro is installed but not licensed', self::RECOMMENDED, 'Site', 'Pro features are read-only.', 'Activate a key under License.')];
    }

    // ── Slow tests ─────────────────────────────────────────────────────────

    public static function asyncTest(string $test): array
    {
        try {
            return match ($test) {
                'dependencies' => self::dependencyAudit(),
                'code_scan' => self::codeScan(),
                'error_log' => self::errorLog(),
                'updates' => self::updates(),
                'disk' => self::disk(),
                default => [],
            };
        } catch (Throwable $e) {
            return [self::result($test, 'Could not run: '.(self::ASYNC[$test] ?? $test), self::RECOMMENDED, 'Site', $e->getMessage())];
        }
    }

    /** Installed Composer packages, name => version, from vendor/composer/installed.json. */
    public static function installedPackages(): array
    {
        $file = base_path('vendor/composer/installed.json');
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $list = $data['packages'] ?? (is_array($data) ? $data : []);
        $out = [];
        foreach ($list as $p) {
            if (!empty($p['name'])) {
                $out[$p['name']] = ltrim((string) ($p['version'] ?? ''), 'v');
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * Every installed package checked against Packagist's security advisory database — the
     * same source `composer audit` uses, asked directly so the check works on hosts with no
     * Composer binary.
     */
    private static function dependencyAudit(): array
    {
        $packages = self::installedPackages();
        if (!$packages) {
            return [self::result('dependencies', 'Could not read the installed packages', self::RECOMMENDED, 'Security', 'vendor/composer/installed.json was not found.')];
        }

        $advisories = [];
        $seen = [];
        foreach (array_chunk(array_keys($packages), 100) as $chunk) {
            $res = Http::timeout(15)->asForm()->acceptJson()
                ->post('https://packagist.org/api/security-advisories/', ['packages' => $chunk]);
            if (!$res->successful()) {
                return [self::result('dependencies', 'The vulnerability database could not be reached', self::RECOMMENDED, 'Security',
                    'Packagist answered HTTP '.$res->status().'. '.count($packages).' packages were not checked.', 'Try again later, or run composer audit on the server.')];
            }
            foreach ((array) $res->json('advisories') as $name => $list) {
                if (!in_array($name, $chunk, true)) {
                    continue; // only what this request asked about
                }
                foreach ((array) $list as $adv) {
                    $key = $name.'|'.($adv['advisoryId'] ?? $adv['cve'] ?? $adv['title'] ?? '').'|'.($adv['affectedVersions'] ?? '');
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    if (self::versionAffected($packages[$name] ?? '', (string) ($adv['affectedVersions'] ?? ''))) {
                        $advisories[] = [
                            'package' => $name, 'installed' => $packages[$name] ?? '',
                            'title' => $adv['title'] ?? '', 'cve' => $adv['cve'] ?? null,
                            'severity' => $adv['severity'] ?? null, 'link' => $adv['link'] ?? null,
                            'affected' => $adv['affectedVersions'] ?? '',
                        ];
                    }
                }
            }
        }

        if (!$advisories) {
            return [self::result('dependencies', 'No known vulnerabilities in '.count($packages).' packages', self::GOOD, 'Security',
                'Every installed Composer package was checked against the Packagist security advisory database.')];
        }

        $byPkg = collect($advisories)->groupBy('package');

        return [self::result('dependencies', count($advisories).' known vulnerabilit'.(count($advisories) === 1 ? 'y' : 'ies').' in '.$byPkg->count().' package(s)', self::CRITICAL, 'Security',
            'These installed versions have published security advisories. Updating the affected packages closes them.',
            'Run: composer update '.$byPkg->keys()->implode(' ').' (or update FalconCMS from the dashboard).',
            ['advisories' => $advisories])];
    }

    /** Does a version fall inside a Packagist "affectedVersions" constraint (">=1.0,<1.2|>=2.0,<2.1")? */
    public static function versionAffected(string $version, string $constraint): bool
    {
        $version = ltrim(trim($version), 'v');
        if ($version === '' || $constraint === '' || str_starts_with($version, 'dev-')) {
            return false;
        }
        foreach (explode('|', $constraint) as $range) {
            $ok = true;
            foreach (preg_split('/\s*,\s*|\s+(?=[<>=!])/', trim($range)) as $part) {
                if (!preg_match('/^(>=|<=|>|<|==|=|!=)?\s*v?([0-9][\w.\-]*)$/', trim($part), $m)) {
                    $ok = false;
                    break;
                }
                $op = ($m[1] ?? '') === '' || $m[1] === '=' ? '==' : $m[1];
                if (!version_compare($version, $m[2], $op)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return true;
            }
        }

        return false;
    }

    /**
     * Patterns the code scan looks for: [severity, title, why, regex]. Matched line by line
     * against the site's own code — themes, plugins, app/, routes/ — never vendor/. It is a
     * heuristic: each finding is a line worth a look, not a proof of a hole.
     */
    const SCAN_RULES = [
        ['critical', 'eval() on dynamic code', 'Runs whatever string it is given as PHP — the core of most web shells.', '/\beval\s*\(/i'],
        ['critical', 'Obfuscated payload', 'Decoded and executed data is how injected malware hides.', '/\b(eval|assert|create_function)\s*\(\s*(base64_decode|gzinflate|gzuncompress|str_rot13|hex2bin)\s*\(/i'],
        ['critical', 'Shell command from user input', 'Request data reaches a system command — remote command execution.', '/\b(exec|shell_exec|system|passthru|popen|proc_open)\s*\([^)]*(\$_(GET|POST|REQUEST|COOKIE)|request\(|\$request->)/i'],
        ['critical', 'File include from user input', 'Request data decides which file PHP loads — remote/local file inclusion.', '/\b(include|require)(_once)?\b[^;]*(\$_(GET|POST|REQUEST|COOKIE)|request\(|\$request->)/i'],
        ['critical', 'unserialize() on user input', 'Unserialising request data can run code through gadget chains.', '/\bunserialize\s*\([^)]*(\$_(GET|POST|REQUEST|COOKIE)|request\(|\$request->)/i'],
        ['high', 'SQL built from user input', 'Request data concatenated into SQL — SQL injection. Use bindings (?) instead.', '/(DB::(raw|select|statement|unprepared|insert|update|delete)|->(whereRaw|orderByRaw|selectRaw|havingRaw))\s*\(\s*["\'][^"\']*["\']\s*\.\s*(\$_(GET|POST|REQUEST)|request\(|\$request->)|(DB::(raw|select|statement|unprepared)|->(whereRaw|orderByRaw|selectRaw|havingRaw))\s*\(\s*"[^"]*\{?\$(request|_GET|_POST)/i'],
        ['high', 'Unescaped user input in a template', '{!! !!} prints request data as raw HTML — cross-site scripting.', '/\{!!\s*(\$_(GET|POST|REQUEST|COOKIE)|request\(|\$request->|old\()/i'],
        ['high', 'Raw superglobal echoed', 'Request data printed without escaping — cross-site scripting.', '/\becho\s+\$_(GET|POST|REQUEST|COOKIE)/i'],
        ['high', 'Shell command', 'Runs a system command. Fine when the command is fixed; dangerous if any part comes from outside.', '/(?<![\w>:$])(shell_exec|passthru|proc_open|popen)\s*\(/i'],
        ['medium', 'File written from a request path', 'A filename taken from the request can write outside the intended folder.', '/\b(file_put_contents|fopen|move_uploaded_file)\s*\([^)]*(\$_(GET|POST|REQUEST)|request\(|\$request->input)/i'],
        ['medium', 'Weak password hashing', 'md5/sha1 are not password hashes. Use Hash::make() / bcrypt.', '/\b(md5|sha1)\s*\(\s*\$(password|pass|pwd)\b/i'],
        ['medium', 'TLS verification turned off', 'Disabling certificate checks lets anyone in the middle read or change the traffic.', '/(CURLOPT_SSL_VERIFYPEER\s*,\s*(false|0)|[\'"]verify[\'"]\s*=>\s*false|withoutVerifying\s*\()/i'],
        ['low', 'Debug output left in code', 'dd()/dump()/var_dump()/print_r() can expose data or break a page for visitors.', '/(?<![\w>:$])(dd|dump|var_dump|print_r|var_export)\s*\(/'],
        ['low', 'phpinfo() call', 'phpinfo() lists the server configuration and environment to whoever calls it.', '/\bphpinfo\s*\(/i'],
        ['low', 'Hard-coded credential', 'A password or secret written into code ends up in backups and repositories.', '/[\'"]?(password|passwd|secret|api_key|apikey|token)[\'"]?\s*(=>|=)\s*[\'"][^\'"\s$]{6,}[\'"]/i'],
    ];

    /**
     * A "password" => '...' line that is not a secret: read from env/config, a model cast
     * ('hashed'), a validation rule ('required|min:8'), a translation or a form label.
     */
    private static function harmlessCredentialLine(string $line): bool
    {
        if (preg_match('/env\(|config\(|Hash::|bcrypt|__\(|trans\(|placeholder|label|old\(/i', $line)) {
            return true;
        }
        if (preg_match('/(=>|=)\s*[\'"]([^\'"]*)[\'"]/', $line, $m)) {
            $v = strtolower($m[2]);

            return in_array($v, ['hashed', 'encrypted', 'string', 'password', 'secret', 'text', 'hidden', 'required', 'confirmed'], true)
                || str_contains($v, '|') || preg_match('/^(required|nullable|min:|max:|sometimes)/', $v);
        }

        return false;
    }

    /** Folders of the site's own code: everything an owner or developer writes, nothing Composer installs. */
    public static function scanRoots(): array
    {
        return array_values(array_filter([
            base_path('app'), base_path('routes'), base_path('plugins'),
            resource_path('views'), base_path('config'), base_path('database'),
        ], 'is_dir'));
    }

    private static function codeScan(): array
    {
        $findings = [];
        $files = 0;
        $limit = 6000;
        foreach (self::scanRoots() as $root) {
            $it = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                fn ($f) => !($f->isDir() && in_array($f->getFilename(), ['vendor', 'node_modules', '.git', 'storage'], true))
            ));
            foreach ($it as $file) {
                if (!$file->isFile() || !preg_match('/\.php$/i', $file->getFilename()) || $file->getSize() > 1024 * 1024) {
                    continue;
                }
                if (++$files > $limit) {
                    break 2;
                }
                $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()))), '/');
                $isConfig = str_starts_with($rel, 'config/');
                foreach (file($file->getPathname(), FILE_IGNORE_NEW_LINES) ?: [] as $i => $line) {
                    $trim = ltrim($line);
                    if ($trim === '' || str_starts_with($trim, '//') || str_starts_with($trim, '*') || str_starts_with($trim, '#') || str_starts_with($trim, '{{--')) {
                        continue;
                    }
                    foreach (self::SCAN_RULES as [$sev, $title, $why, $re]) {
                        // config/ reads secrets from env() by design; only literal values matter there.
                        if ($isConfig && $title !== 'Hard-coded credential') {
                            continue;
                        }
                        if (preg_match($re, $line) && !($title === 'Hard-coded credential' && self::harmlessCredentialLine($line))) {
                            $findings[] = ['severity' => $sev, 'title' => $title, 'why' => $why, 'file' => $rel, 'line' => $i + 1, 'code' => Str::limit(trim($line), 180)];
                            break;
                        }
                    }
                }
            }
        }

        $counts = array_count_values(array_column($findings, 'severity'));
        $scope = 'Scanned '.min($files, $limit).' PHP/Blade files in '.implode(', ', array_map(fn ($r) => str_replace('\\', '/', ltrim(substr($r, strlen(base_path())), '\\/')), self::scanRoots())).' (vendor/ is covered by the dependency audit).';
        if (!$findings) {
            return [self::result('code_scan', 'No risky code patterns found', self::GOOD, 'Security', $scope)];
        }
        usort($findings, fn ($a, $b) => array_search($a['severity'], ['critical', 'high', 'medium', 'low']) <=> array_search($b['severity'], ['critical', 'high', 'medium', 'low']));
        $status = !empty($counts['critical']) ? self::CRITICAL : (!empty($counts['high']) ? self::CRITICAL : self::RECOMMENDED);
        $summary = implode(', ', array_map(fn ($s) => ($counts[$s] ?? 0).' '.$s, array_filter(['critical', 'high', 'medium', 'low'], fn ($s) => !empty($counts[$s]))));

        return [self::result('code_scan', count($findings).' line(s) of code worth a look ('.$summary.')', $status, 'Security',
            $scope.' Each finding is a pattern that is often a bug or a hole — check whether the value can come from a visitor.',
            'Open each file at the line shown. Escape output with {{ }}, use query bindings, validate input, remove debug calls.',
            ['findings' => array_slice($findings, 0, 300), 'truncated' => count($findings) > 300])];
    }

    /** Recent errors from storage/logs, grouped by message, newest last-seen first. */
    private static function errorLog(): array
    {
        $files = array_merge((array) glob(storage_path('logs/*.log')));
        if (!$files) {
            return [self::result('error_log', 'No log files', self::GOOD, 'Site', 'storage/logs is empty — nothing has gone wrong, or logging is off.')];
        }
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $totalSize = array_sum(array_map('filesize', $files));

        $groups = [];
        $since = now()->subDays(7);
        $count24 = 0;
        $count7 = 0;
        foreach (array_slice($files, 0, 8) as $file) {
            $size = filesize($file);
            $fh = fopen($file, 'r');
            if (!$fh) {
                continue;
            }
            // The newest 2 MB of each file is plenty for the last week and keeps a huge log fast.
            if ($size > 2 * 1024 * 1024) {
                fseek($fh, -2 * 1024 * 1024, SEEK_END);
                fgets($fh);
            }
            $buffer = stream_get_contents($fh);
            fclose($fh);
            preg_match_all('/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\]\s+\w+\.(ERROR|CRITICAL|ALERT|EMERGENCY|WARNING):\s+(.*)$/m', (string) $buffer, $m, PREG_SET_ORDER);
            foreach ($m as [, $at, $level, $message]) {
                try {
                    $time = Carbon::parse($at);
                } catch (Throwable $e) {
                    continue;
                }
                if ($time->lt($since)) {
                    continue;
                }
                $count7++;
                if ($time->gt(now()->subDay())) {
                    $count24++;
                }
                $msg = preg_replace('/\s*\{"(exception|userId)".*$/s', '', $message);
                $where = null;
                $loc = [];
                if (preg_match('/ at ([^\s(]+):(\d+)\)?/', $message, $loc) || preg_match('/\(View: ([^)]+)\)/', $message, $loc)) {
                    $where = str_replace([str_replace('\\', '/', base_path()).'/', base_path().DIRECTORY_SEPARATOR], '', str_replace('\\', '/', $loc[1])).(isset($loc[2]) ? ':'.$loc[2] : '');
                }
                // The file the error is really about: a Blade error names its template in
                // "(View: …)", which beats the compiled copy in storage/framework/views.
                $source = preg_match('/\(View: ([^)]+)\)/', $message, $v) ? $v[1] : ($loc[1] ?? null);
                $key = md5($level.Str::limit($msg, 160, ''));
                $g = $groups[$key] ?? ['level' => $level, 'message' => Str::limit($msg, 300), 'where' => $where, 'count' => 0, 'last' => $at, 'file' => basename($file), 'source' => $source];
                $g['count']++;
                if ($at > $g['last']) {
                    $g['last'] = $at;
                }
                $groups[$key] = $g;
            }
        }

        $out = [];
        if ($totalSize > 100 * 1024 * 1024) {
            $out[] = self::result('log_size', 'Log files take '.self::human($totalSize), self::RECOMMENDED, 'Performance',
                'Large logs fill the disk and slow backups.', 'Set LOG_STACK=daily (keeps 14 days) or delete old files in storage/logs.');
        }
        if (!$groups) {
            $out[] = self::result('error_log', 'No errors in the last 7 days', self::GOOD, 'Site', 'Checked '.count($files).' log file(s), '.self::human($totalSize).'.');

            return $out;
        }
        usort($groups, fn ($a, $b) => strcmp($b['last'], $a['last']));
        foreach ($groups as &$g) {
            $g['resolved'] = self::errorResolved($g);
            unset($g['source']);
        }
        unset($g);

        $open = array_values(array_filter($groups, fn ($g) => !$g['resolved']));
        $fixed = count($groups) - count($open);
        $openCount = array_sum(array_column($open, 'count'));
        $open24 = count(array_filter($open, fn ($g) => $g['last'] > now()->subDay()->format('Y-m-d H:i:s')));
        $fixedNote = $fixed ? ' '.$fixed.' older problem(s) look fixed — the file behind each was changed after it last happened, so they no longer count.' : '';

        if (!$open) {
            $out[] = self::result('error_log', 'No open errors — '.$fixed.' recent problem(s) have been fixed', self::GOOD, 'Site',
                'Every error logged in the last 7 days came from code that has been changed since, and none has happened again.',
                null, ['errors' => array_slice($groups, 0, 50)]);

            return $out;
        }

        $hard = array_filter($open, fn ($g) => $g['level'] !== 'WARNING');
        $out[] = self::result('error_log', $openCount.' error(s) logged in the last 7 days'.($open24 ? ' ('.$open24.' problem(s) in the last 24 hours)' : ''),
            $hard ? ($open24 > 0 ? self::CRITICAL : self::RECOMMENDED) : self::RECOMMENDED, 'Site',
            count($open).' distinct problem(s). Each one is a page or action that failed for someone — the most recent first.'.$fixedNote,
            'Open the file and line shown and fix the cause. Once the file is changed and the error stops happening, it is marked fixed here.',
            ['errors' => array_slice($groups, 0, 50)]);

        return $out;
    }

    /**
     * Has a logged error been dealt with since? Yes when the file it points at was changed
     * after it last happened (the template named in "(View: …)", or the file in " at …:line"),
     * when it named a view that now exists, or when it pointed into a compiled view that has
     * since been cleared. An error that happens again after the change shows up as open again,
     * because its last-seen time moves past the file's.
     */
    private static function errorResolved(array $g): bool
    {
        try {
            $last = Carbon::parse($g['last'])->getTimestamp();
        } catch (Throwable $e) {
            return false;
        }

        if (preg_match('/View \[([^\]]+)\] not found/', $g['message'], $m)) {
            $name = $m[1];

            return view()->exists($name) || view()->exists('falcon-cms::'.$name);
        }

        $path = $g['source'] ?? null;
        if (!$path) {
            return false;
        }
        $path = str_replace('\\', '/', $path);
        if (str_contains($path, 'storage/framework/views/')) {
            return !is_file($path); // compiled copy gone: views were rebuilt from fixed source
        }
        if (str_contains($path, '/vendor/laravel/') || str_contains($path, '/vendor/symfony/')) {
            return false; // the framework is where it surfaced, not where it was caused
        }

        return is_file($path) && filemtime($path) > $last;
    }

    private static function updates(): array
    {
        $out = [];
        if (function_exists('falcon_check_update')) {
            $u = falcon_check_update();
            $out[] = !empty($u['has_update'])
                ? self::result('cms_update', 'FalconCMS '.$u['latest'].' is available', self::RECOMMENDED, 'Security', 'You have '.$u['current'].'. Updates carry bug and security fixes.', 'Update from the Dashboard.')
                : self::result('cms_update', 'FalconCMS is up to date', self::GOOD, 'Security', 'Version '.($u['current'] ?? '?').($u['latest'] ? ' — latest is '.$u['latest'] : '').'.');
        }
        try {
            $plugins = app(PluginManager::class)->all();
            $stale = array_filter($plugins, fn ($p) => !empty($p['update_available']));
            $out[] = $stale
                ? self::result('plugin_updates', count($stale).' plugin update(s) waiting', self::RECOMMENDED, 'Security', implode(', ', array_map(fn ($p) => ($p['name'] ?? '?').' '.($p['installed_version'] ?? '').' → '.($p['version'] ?? ''), $stale)).'.', 'Update them under Plugins.')
                : self::result('plugin_updates', 'Plugins are up to date', self::GOOD, 'Security', count($plugins).' plugin(s) installed.');
        } catch (Throwable $e) {
        }

        return $out;
    }

    private static function disk(): array
    {
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());
        $out = [];
        if ($free !== false && $total) {
            $pct = $free / $total * 100;
            $out[] = $free < 1024 ** 3 || $pct < 5
                ? self::result('disk', 'Low disk space: '.self::human($free).' free', self::CRITICAL, 'Site', 'When the disk fills, uploads, sessions and the database stop working.', 'Free space or upgrade the hosting plan.')
                : self::result('disk', self::human($free).' of '.self::human($total).' free', self::GOOD, 'Site', round($pct).'% of the disk is free.');
        }
        $sizes = [];
        foreach (['Uploads (storage/app/public)' => storage_path('app/public'), 'Logs' => storage_path('logs'), 'Backups' => storage_path('app/backups'),
            'Framework cache' => storage_path('framework'), 'Plugins' => base_path('plugins'), 'Vendor' => base_path('vendor')] as $label => $dir) {
            if (is_dir($dir)) {
                $sizes[$label] = self::human(self::dirSize($dir));
            }
        }
        if ($sizes) {
            $out[] = self::result('disk_usage', 'Folder sizes', self::GOOD, 'Site', implode(' · ', array_map(fn ($k, $v) => $k.': '.$v, array_keys($sizes), $sizes)).'.');
        }

        return $out;
    }

    private static function dirSize(string $dir): int
    {
        $size = 0;
        $n = 0;
        try {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
                if (++$n > 200000) {
                    break;
                }
                if ($f->isFile()) {
                    $size += $f->getSize();
                }
            }
        } catch (Throwable $e) {
        }

        return $size;
    }

    public static function human($bytes): string
    {
        $bytes = (float) $bytes;
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($bytes < 1024 || $unit === 'TB') {
                return round($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return '';
    }

    // ── Info ───────────────────────────────────────────────────────────────

    /** Sections of label => value, for the Info tab and the copyable report. */
    public static function info(): array
    {
        $db = DB::connection();
        $dbSize = null;
        $tables = null;
        try {
            if (in_array($db->getDriverName(), ['mysql', 'mariadb'], true)) {
                $row = DB::selectOne('SELECT COUNT(*) AS n, SUM(data_length + index_length) AS s FROM information_schema.TABLES WHERE table_schema = ?', [$db->getDatabaseName()]);
                $tables = (int) $row->n;
                $dbSize = self::human($row->s ?? 0);
            } elseif ($db->getDriverName() === 'sqlite') {
                $tables = count(DB::select("select name from sqlite_master where type='table'"));
                $dbSize = is_file($db->getDatabaseName()) ? self::human(filesize($db->getDatabaseName())) : null;
            }
        } catch (Throwable $e) {
        }
        $charset = $collation = null;
        try {
            if (in_array($db->getDriverName(), ['mysql', 'mariadb'], true)) {
                $c = DB::selectOne('SELECT @@character_set_database AS cs, @@collation_database AS co, @@max_allowed_packet AS map, @@max_connections AS mc');
                $charset = $c->cs ?? null;
                $collation = $c->co ?? null;
                $maxPacket = self::human($c->map ?? 0);
                $maxConn = $c->mc ?? null;
            }
        } catch (Throwable $e) {
        }

        $plugins = [];
        try {
            foreach (app(PluginManager::class)->all() as $slug => $p) {
                $plugins[$p['name'] ?? $slug] = ($p['installed_version'] ?? $p['version'] ?? '?').($p['active'] ? ' (active)' : ' (inactive)');
            }
        } catch (Throwable $e) {
        }

        $userModel = config('auth.providers.users.model');
        $counts = [];
        try {
            $counts['Users'] = class_exists($userModel) ? $userModel::count() : null;
            $counts['Posts'] = DB::table('posts')->where('type', 'post')->count();
            $counts['Pages'] = DB::table('posts')->where('type', 'page')->count();
            $counts['Media files'] = DB::table('media')->count();
        } catch (Throwable $e) {
        }

        return [
            'FalconCMS' => array_filter([
                'Version' => function_exists('falcon_cms_installed_version') ? falcon_cms_installed_version() : (defined('FALCON_CMS_VERSION') ? FALCON_CMS_VERSION : null),
                'Pro' => function_exists('falcon_pro_installed_version') ? (falcon_pro_installed_version() ?: 'Not installed') : null,
                'Active theme' => get_cms_option('active_theme', 'falcon-theme'),
                'Site URL' => url('/'),
                'Site title' => get_cms_option('site_title', ''),
            ] + $counts, fn ($v) => $v !== null),
            'Server' => array_filter([
                'Operating system' => php_uname('s').' '.php_uname('r').' ('.php_uname('m').')',
                'Web server' => $_SERVER['SERVER_SOFTWARE'] ?? null,
                'Server name' => request()->getHost(),
                'Server IP' => $_SERVER['SERVER_ADDR'] ?? null,
                'HTTPS' => request()->isSecure() ? 'Yes' : 'No',
                'Server time' => now()->toDateTimeString().' ('.config('app.timezone').')',
                'Disk free' => ($f = @disk_free_space(base_path())) ? self::human($f) : null,
            ]),
            'PHP' => [
                'Version' => PHP_VERSION,
                'SAPI' => PHP_SAPI,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time').'s',
                'max_input_time' => ini_get('max_input_time').'s',
                'max_input_vars' => ini_get('max_input_vars'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'display_errors' => ini_get('display_errors') ? 'On' : 'Off',
                'OPcache' => function_exists('opcache_get_status') && ($s = @opcache_get_status(false)) && !empty($s['opcache_enabled']) ? 'Enabled' : 'Disabled',
                'Extensions' => implode(', ', array_map('strtolower', get_loaded_extensions())),
            ],
            'Database' => array_filter([
                'Driver' => $db->getDriverName(),
                'Server version' => self::dbVersion(),
                'Database name' => $db->getDatabaseName(),
                'Host' => config('database.connections.'.config('database.default').'.host'),
                'Table prefix' => $db->getTablePrefix() ?: '(none)',
                'Tables' => $tables,
                'Size' => $dbSize,
                'Charset' => $charset,
                'Collation' => $collation,
                'max_allowed_packet' => $maxPacket ?? null,
                'max_connections' => $maxConn ?? null,
            ], fn ($v) => $v !== null),
            'Laravel' => [
                'Version' => app()->version(),
                'Environment' => app()->environment(),
                'Debug mode' => config('app.debug') ? 'On' : 'Off',
                'Locale' => app()->getLocale(),
                'Cache store' => config('cache.default'),
                'Session driver' => config('session.driver').' ('.config('session.lifetime').' min)',
                'Queue connection' => config('queue.default'),
                'Mailer' => config('mail.default'),
                'Log channel' => config('logging.default'),
                'Config cached' => app()->configurationIsCached() ? 'Yes' : 'No',
                'Routes cached' => app()->routesAreCached() ? 'Yes' : 'No',
                'Maintenance mode' => app()->isDownForMaintenance() ? 'On' : 'Off',
            ],
            'Directories' => [
                'Base path' => base_path(),
                'Public path' => public_path(),
                'Storage path' => storage_path().(is_writable(storage_path()) ? ' (writable)' : ' (NOT writable)'),
                'Bootstrap cache' => base_path('bootstrap/cache').(is_writable(base_path('bootstrap/cache')) ? ' (writable)' : ' (NOT writable)'),
                'Storage link' => file_exists(public_path('storage')) ? 'Present' : 'Missing',
            ],
            'Plugins' => $plugins ?: ['—' => 'No plugins installed'],
            'Composer packages' => self::installedPackages(),
        ];
    }
}

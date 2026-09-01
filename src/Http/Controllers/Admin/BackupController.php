<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Carbon\Carbon;
use FalconCms\Core\Support\SvgSanitizer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackupController extends Controller
{
    private function checkAccess(): void
    {
        $u = auth()->user();
        if (!$u || (!$u->hasPermission('manage_settings')
            && !$u->hasPermission('access_backup_restore')
            && !$u->hasPermission('access_backups')
            && !$u->hasPermission('access_tools'))) {
            abort(403);
        }
    }

    /**
     * Whether this connection can be dumped and restored as SQL.
     *
     * buildSqlDump() is written in MySQL — SHOW TABLES, SHOW CREATE TABLE, backticks — and
     * runSqlDump() replays it the same way. The README lists SQLite as a supported database
     * for running the CMS, and it is; this one tool is the exception. Saying so plainly
     * beats letting a shop owner press Create Backup and receive a raw SQL syntax error
     * that reads like the CMS is broken.
     *
     * Media backups carry no SQL and work on every driver.
     */
    private function supportsSqlDump(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    private function sqlDumpUnsupportedMessage(): string
    {
        return 'Database backups need MySQL or MariaDB. This site runs on '
            .DB::connection()->getDriverName()
            .', so only media backups are available here.';
    }

    private function backupDir(): string
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    private function mediaDir(): string
    {
        return storage_path('app/public'); // Laravel "public" disk = uploaded media
    }

    // Convert php.ini size string (e.g. "64M", "1G") to bytes
    private function iniToBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1] ?? '');
        $num = (int) $val;

        return match ($last) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }

    private function maxUploadBytes(): int
    {
        $upload = $this->iniToBytes(ini_get('upload_max_filesize') ?: '8M');
        $post = $this->iniToBytes(ini_get('post_max_size') ?: '8M');

        return min($upload, $post);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / 1024 / 1024 / 1024, 1).' GB';
        }
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 0).' MB';
        }

        return round($bytes / 1024, 0).' KB';
    }

    /** Human label for what a backup file contains (best-effort, by name/extension). */
    private function backupType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['sql', 'gz'])) {
            return 'Database';
        }
        if ($ext === 'zip') {
            $lower = strtolower($filename);
            if (str_starts_with($lower, 'full-backup-')) {
                return 'Database + Media';
            }
            if (str_starts_with($lower, 'media-backup-')) {
                return 'Media';
            }

            return 'Archive';
        }

        return 'Backup';
    }

    public function index()
    {
        $this->checkAccess();

        $backups = [];
        $backupDir = storage_path('app/backups');

        if (is_dir($backupDir)) {
            foreach (array_diff(scandir($backupDir), ['.', '..']) as $file) {
                $filePath = $backupDir.'/'.$file;
                if (is_file($filePath)) {
                    $backups[] = [
                        'name' => $file,
                        'type' => $this->backupType($file),
                        'size' => round(filesize($filePath) / 1024 / 1024, 2).' MB',
                        'date' => Carbon::createFromTimestamp(filemtime($filePath))->format('Y-m-d H:i:s'),
                        'path' => $filePath,
                    ];
                }
            }
        }

        usort($backups, fn ($a, $b) => strtotime($b['date']) - strtotime($a['date']));

        $maxUploadBytes = $this->maxUploadBytes();
        $maxUploadHuman = $this->formatBytes($maxUploadBytes);

        return view('falcon-cms::admin.tools.backup', compact('backups', 'maxUploadBytes', 'maxUploadHuman'));
    }

    // ───────────────────────────── create ─────────────────────────────

    /**
     * One entry point for all three backup kinds, chosen by `backup_type`:
     *   database | media | both
     */
    public function create(Request $request)
    {
        $this->checkAccess();
        @set_time_limit(0);

        $type = $request->input('backup_type', 'database');

        try {
            return match ($type) {
                'media' => $this->doMediaBackup(),
                'both' => $this->doFullBackup(),
                default => $this->doDatabaseBackup(),
            };
        } catch (\Throwable $e) {
            return back()->with('error', 'Backup failed: '.$e->getMessage());
        }
    }

    /** Backward-compatible route target — now just a media backup. */
    public function createMedia()
    {
        $this->checkAccess();
        @set_time_limit(0);
        try {
            return $this->doMediaBackup();
        } catch (\Throwable $e) {
            return back()->with('error', 'Media backup failed: '.$e->getMessage());
        }
    }

    private function doDatabaseBackup()
    {
        if (!$this->supportsSqlDump()) {
            return back()->with('error', $this->sqlDumpUnsupportedMessage());
        }

        $filename = 'backup-'.Carbon::now()->format('Y-m-d-H-i-s').'.sql';
        file_put_contents($this->backupDir().'/'.$filename, $this->buildSqlDump());

        falcon_log_activity('created', "Created a database backup: {$filename}");

        return back()->with('success', 'Database backup created successfully.');
    }

    private function doMediaBackup()
    {
        if (!class_exists('\ZipArchive')) {
            return back()->with('error', 'Media backup needs the PHP "zip" extension, which is not enabled on this server.');
        }
        if (!is_dir($this->mediaDir())) {
            return back()->with('error', 'No media folder found to back up.');
        }

        $filename = 'media-backup-'.Carbon::now()->format('Y-m-d-H-i-s').'.zip';
        $path = $this->backupDir().'/'.$filename;

        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Could not create the zip archive.');
        }
        $count = $this->addMediaToZip($zip, '');

        // Bundle the Media Library records too, so restoring brings back the library
        // entries (not just the physical files — the library is database-driven).
        if (Schema::hasTable('media')) {
            $zip->addFromString('_media-records.json', DB::table('media')->get()->toJson());
        }
        $zip->close();

        if ($count === 0) {
            @unlink($path);

            return back()->with('error', 'No media files found to back up.');
        }

        falcon_log_activity('created', "Created a media files backup: {$filename} ({$count} files)");

        return back()->with('success', "Media backup created successfully ({$count} files).");
    }

    private function doFullBackup()
    {
        if (!$this->supportsSqlDump()) {
            return back()->with('error', $this->sqlDumpUnsupportedMessage());
        }

        if (!class_exists('\ZipArchive')) {
            return back()->with('error', 'A full backup needs the PHP "zip" extension, which is not enabled on this server.');
        }

        $filename = 'full-backup-'.Carbon::now()->format('Y-m-d-H-i-s').'.zip';
        $path = $this->backupDir().'/'.$filename;

        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Could not create the zip archive.');
        }

        // Database goes in as database.sql, media under a media/ folder. Restore
        // detects both by content, so the two always travel together cleanly.
        $zip->addFromString('database.sql', $this->buildSqlDump());
        $mediaCount = $this->addMediaToZip($zip, 'media/');
        $zip->close();

        falcon_log_activity('created', "Created a full backup: {$filename} (database + {$mediaCount} media files)");

        return back()->with('success', "Full backup created successfully (database + {$mediaCount} media files).");
    }

    /** Stream every file under the media dir into the zip, under $prefix. Returns file count. */
    private function addMediaToZip(\ZipArchive $zip, string $prefix): int
    {
        $mediaDir = $this->mediaDir();
        if (!is_dir($mediaDir)) {
            return 0;
        }

        $count = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($mediaDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }
            // getSubPathname() gives the path relative to $mediaDir directly — robust
            // across OS path separators / drive-letter casing (no manual stripping).
            $relative = str_replace('\\', '/', $files->getSubPathname());
            $zip->addFile($file->getPathname(), $prefix.$relative);
            $count++;
        }

        return $count;
    }

    private function buildSqlDump(): string
    {
        $tables = DB::select('SHOW TABLES');
        $dbName = config('database.connections.mysql.database');
        $sql = "-- Falcon CMS Backup\n-- Database: {$dbName}\n-- Date: ".now()."\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $tableName = current((array) $table);

            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`")[0];
            $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            $sql .= $createTable->{'Create Table'}.";\n\n";

            foreach (DB::table($tableName)->get() as $row) {
                $row = (array) $row;
                $columns = array_keys($row);
                $values = array_map(function ($value) {
                    if (is_null($value)) {
                        return 'NULL';
                    }

                    return "'".addslashes($value)."'";
                }, array_values($row));

                $sql .= "INSERT INTO `{$tableName}` (`".implode('`, `', $columns).'`) VALUES ('.implode(', ', $values).");\n";
            }
            $sql .= "\n";
        }
        $sql .= 'SET FOREIGN_KEY_CHECKS=1;';

        return $sql;
    }

    // ───────────────────────────── restore ─────────────────────────────

    /**
     * Smart restore: figures out from the file itself what it holds and restores
     * the right thing(s) — a .sql/.gz dump, a media zip, or a combined archive
     * that carries BOTH the database and the media (restored together).
     */
    public function restore($filename)
    {
        $this->checkAccess();
        @set_time_limit(0);

        $filename = basename($filename);
        $path = storage_path('app/backups/'.$filename);
        if (!file_exists($path)) {
            return back()->with('error', 'Backup file not found.');
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        try {
            if ($ext === 'zip') {
                return $this->restoreFromZip($path, $filename);
            }
            // .sql or .sql.gz → pure database dump.
            $sql = $ext === 'gz' ? gzdecode(file_get_contents($path)) : file_get_contents($path);
            if ($sql === false || trim($sql) === '') {
                throw new \Exception('Backup file is empty or could not be read.');
            }
            $executed = $this->runSqlDump($sql);

            falcon_log_activity('restored', "Restored database from snapshot: {$filename} ({$executed} statements)");

            return back()->with('success', "Database restored successfully from \"{$filename}\" ({$executed} statements executed).");
        } catch (\Throwable $e) {
            $this->toggleForeignKeys(true);

            return back()->with('error', 'Restoration failed: '.$e->getMessage());
        }
    }

    /**
     * If every entry sits under one shared top-level folder, return that "folder/"
     * prefix so it can be stripped; otherwise ''. Makes restore tolerant of backups
     * that were extracted and re-zipped inside a folder (e.g. by Windows Explorer).
     */
    private function commonWrapperPrefix(array $names): string
    {
        if (empty($names)) {
            return '';
        }
        $first = reset($names);
        $slash = strpos($first, '/');
        if ($slash === false) {
            return '';
        } // first entry is a root-level file → no wrapper
        $candidate = substr($first, 0, $slash + 1);
        foreach ($names as $n) {
            if (!str_starts_with($n, $candidate)) {
                return '';
            }
        }

        return $candidate;
    }

    /**
     * Inspect a zip and restore whatever it contains:
     *  - a *.sql entry  → run it as the database
     *  - any other files → extracted into the media folder (a leading "media/" is stripped)
     * Both can be present (a full backup) and both get restored.
     */
    private function restoreFromZip(string $path, string $filename)
    {
        if (!class_exists('\ZipArchive')) {
            return back()->with('error', 'Restoring a zip backup needs the PHP "zip" extension, which is not enabled on this server.');
        }

        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \Exception('Could not open the backup archive.');
        }

        // Gather file entries (skip directory entries).
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || str_ends_with($name, '/')) {
                continue;
            }
            $entries[$i] = $name;
        }

        // If the whole archive lives inside a single wrapper folder (common when a
        // downloaded backup is extracted then re-zipped by the OS), strip that
        // folder so the inner paths resolve correctly.
        $wrapper = $this->commonWrapperPrefix($entries);

        // Classify by logical (wrapper-stripped) name; read content by index.
        $sqlIndex = null;
        $recordsIndex = null;
        $mediaEntries = []; // index => logical path
        foreach ($entries as $i => $name) {
            $logical = $wrapper !== '' ? substr($name, strlen($wrapper)) : $name;
            if ($logical === '') {
                continue;
            }
            if (str_ends_with(strtolower($logical), '.sql')) {
                if ($sqlIndex === null || strtolower(basename($logical)) === 'database.sql') {
                    $sqlIndex = $i;
                }
            } elseif (basename($logical) === '_media-records.json') {
                $recordsIndex = $i; // Media Library rows bundled with a media-only backup
            } else {
                $mediaEntries[$i] = $logical;
            }
        }

        $done = [];

        // 1) Database
        if ($sqlIndex !== null) {
            $sql = $zip->getFromIndex($sqlIndex);
            if ($sql === false || trim($sql) === '') {
                $zip->close();
                throw new \Exception('The archive contains an empty database dump.');
            }
            $executed = $this->runSqlDump($sql);
            $done[] = "database ({$executed} statements)";
        }

        // 2) Media
        if (!empty($mediaEntries)) {
            $dest = $this->mediaDir();
            if (!is_dir($dest)) {
                mkdir($dest, 0755, true);
            }

            // A *full* backup (one that also carries database.sql) nests its media
            // under a "media/" folder, so we strip that one prefix to land files at
            // the public-disk root. A *media-only* backup already stores paths
            // relative to that root (which themselves can include a real "media/"
            // sub-folder), so those must be kept exactly as-is.
            $stripMediaPrefix = ($sqlIndex !== null);

            $count = 0;
            $skippedUnsafe = 0;
            foreach ($mediaEntries as $i => $logical) {
                $target = $stripMediaPrefix ? preg_replace('#^media/#', '', $logical) : $logical;
                if ($target === '' || str_contains($target, '..')) {
                    continue;
                } // safety

                // This is the third door into the media directory, after the upload screen
                // and the WordPress importer, and it writes to the public disk — which is
                // web-accessible. An archive is input like any other: it can be uploaded on
                // the Backup screen, and a site restoring a copy it was handed has no way to
                // know what is inside. So the same rule applies here as at the other two
                // doors; see falcon_blocked_upload_extensions().
                $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
                if ($ext === '' || in_array($ext, falcon_blocked_upload_extensions(), true)) {
                    $skippedUnsafe++;

                    continue;
                }

                $content = $zip->getFromIndex($i);
                if ($content === false) {
                    continue;
                }

                // Formats we keep only in rewritten form get rewritten here too. An archive
                // is untrusted input, so a backup cannot smuggle back a raw SVG that the
                // upload screen would have sanitised on the way in.
                if (in_array($ext, falcon_sanitized_upload_extensions(), true)) {
                    $content = SvgSanitizer::clean($content);
                    if ($content === '') {
                        $skippedUnsafe++;

                        continue;
                    }
                }

                $full = $dest.'/'.$target;
                $dir = dirname($full);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                if (file_put_contents($full, $content) !== false) {
                    $count++;
                } // count only real writes
            }
            $done[] = "{$count} media files";
            if ($skippedUnsafe > 0) {
                // Worth saying out loud: a backup that carries files the CMS will not store
                // is either damaged or was built by someone else.
                $done[] = "{$skippedUnsafe} unsafe file".($skippedUnsafe === 1 ? '' : 's').' skipped';
            }
        }

        // 3) Media Library records — only in a media-only backup. (A full backup
        // restores these through database.sql instead.) updateOrInsert brings back
        // deleted library entries without disturbing rows added since the backup.
        if ($recordsIndex !== null && Schema::hasTable('media')) {
            $records = json_decode((string) $zip->getFromIndex($recordsIndex), true);
            if (is_array($records)) {
                $cols = Schema::getColumnListing('media');
                $n = 0;
                foreach ($records as $rec) {
                    $row = array_intersect_key((array) $rec, array_flip($cols));
                    if (empty($row['id'])) {
                        continue;
                    }
                    DB::table('media')->updateOrInsert(['id' => $row['id']], $row);
                    $n++;
                }
                if ($n > 0) {
                    $done[] = "{$n} media library records";
                }
            }
        }

        $zip->close();

        if (empty($done)) {
            return back()->with('error', 'The archive did not contain a recognizable database dump or media files.');
        }

        falcon_log_activity('restored', "Restored from backup: {$filename} (".implode(', ', $done).')');

        return back()->with('success', "Restored from \"{$filename}\": ".implode(' + ', $done).'.');
    }

    /** Execute a full SQL dump string. Returns the number of statements run. */
    private function runSqlDump(string $sql): int
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql); // strip UTF-8 BOM
        $statements = $this->parseSqlStatements($sql);
        $executed = 0;

        $this->toggleForeignKeys(false);
        try {
            foreach ($statements as $stmt) {
                DB::unprepared($stmt);
                $executed++;
            }
        } finally {
            $this->toggleForeignKeys(true);
        }

        return $executed;
    }

    /**
     * Turn foreign-key enforcement off around a restore, in whatever dialect this
     * connection speaks. A dump drops and recreates tables in an arbitrary order, so the
     * constraints have to stand aside while it runs.
     *
     * Unrecognised drivers are left alone rather than guessed at: failing to relax the
     * constraints is recoverable, and throwing here would take down the restore — which is
     * what the unconditional MySQL statement used to do on every other database, including
     * from inside the catch block that was meant to report the error.
     */
    private function toggleForeignKeys(bool $on): void
    {
        try {
            match (DB::connection()->getDriverName()) {
                'mysql', 'mariadb' => DB::statement('SET FOREIGN_KEY_CHECKS='.($on ? '1' : '0')),
                'sqlite' => DB::statement('PRAGMA foreign_keys = '.($on ? 'ON' : 'OFF')),
                'pgsql' => DB::statement("SET session_replication_role = '".($on ? 'origin' : 'replica')."'"),
                default => null,
            };
        } catch (\Throwable $e) {
            // Best effort. A restore that cannot relax its constraints may still succeed,
            // and must not be aborted by the attempt.
        }
    }

    // Parse a multi-statement SQL dump into individual statements,
    // correctly handling quoted strings, line comments, and block comments.
    private function parseSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $len = strlen($sql);
        $inString = false;
        $strChar = '';
        $i = 0;

        while ($i < $len) {
            $ch = $sql[$i];

            if ($inString) {
                if ($ch === '\\') {
                    $current .= $ch.($sql[$i + 1] ?? '');
                    $i += 2;

                    continue;
                }
                if ($ch === $strChar) {
                    $inString = false;
                }
                $current .= $ch;
                $i++;

                continue;
            }

            if ($ch === '"' || $ch === "'") {
                $inString = true;
                $strChar = $ch;
                $current .= $ch;
                $i++;

                continue;
            }

            if ($ch === '-' && isset($sql[$i + 1]) && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }

                continue;
            }

            if ($ch === '/' && isset($sql[$i + 1]) && $sql[$i + 1] === '*') {
                $i += 2;
                while ($i < $len - 1 && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i += 2;

                continue;
            }

            if ($ch === ';') {
                $stmt = trim($current);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $current = '';
                $i++;

                continue;
            }

            $current .= $ch;
            $i++;
        }

        $stmt = trim($current);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }

        return $statements;
    }

    public function download($filename)
    {
        $this->checkAccess();

        $filename = basename($filename);
        $path = storage_path('app/backups/'.$filename);
        if (!file_exists($path)) {
            abort(404);
        }

        return response()->download($path);
    }

    public function upload(Request $request)
    {
        $this->checkAccess();

        $maxBytes = $this->maxUploadBytes();
        $maxKb = (int) ($maxBytes / 1024);

        $request->validate([
            'backup_file' => [
                'required',
                'file',
                'max:'.$maxKb,
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (!in_array($ext, ['sql', 'gz', 'zip'])) {
                        $fail('Only .sql, .sql.gz, or .zip backup files are allowed.');
                    }
                },
            ],
        ], [
            'backup_file.max' => 'The file exceeds the server upload limit of '.$this->formatBytes($maxBytes).'.',
        ]);

        try {
            $file = $request->file('backup_file');
            $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $ext = $file->getClientOriginalExtension();
            $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $original);
            $filename = $safe.'_uploaded_'.Carbon::now()->format('Y-m-d-H-i-s').'.'.$ext;

            $file->move($this->backupDir(), $filename);

            falcon_log_activity('uploaded', "Uploaded backup file: {$filename}");

            return back()->with('success', "Backup file \"{$filename}\" uploaded successfully. You can now restore it from the list below.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Upload failed: '.$e->getMessage());
        }
    }

    public function destroy($filename)
    {
        $this->checkAccess();

        $filename = basename($filename);
        $path = storage_path('app/backups/'.$filename);
        if (file_exists($path)) {
            unlink($path);

            return back()->with('success', 'Backup deleted successfully.');
        }

        return back()->with('error', 'Backup not found.');
    }
}

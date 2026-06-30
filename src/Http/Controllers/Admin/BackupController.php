<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
        $val  = trim($val);
        $last = strtolower($val[strlen($val) - 1] ?? '');
        $num  = (int) $val;
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
        $post   = $this->iniToBytes(ini_get('post_max_size')       ?: '8M');
        return min($upload, $post);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) return round($bytes / 1024 / 1024 / 1024, 1) . ' GB';
        if ($bytes >= 1024 * 1024)        return round($bytes / 1024 / 1024, 0) . ' MB';
        return round($bytes / 1024, 0) . ' KB';
    }

    /** Human label for what a backup file contains (best-effort, by name/extension). */
    private function backupType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['sql', 'gz'])) return 'Database';
        if ($ext === 'zip') {
            $lower = strtolower($filename);
            if (str_starts_with($lower, 'full-backup-'))  return 'Database + Media';
            if (str_starts_with($lower, 'media-backup-')) return 'Media';
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
                $filePath = $backupDir . '/' . $file;
                if (is_file($filePath)) {
                    $backups[] = [
                        'name' => $file,
                        'type' => $this->backupType($file),
                        'size' => round(filesize($filePath) / 1024 / 1024, 2) . ' MB',
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
                'both'  => $this->doFullBackup(),
                default => $this->doDatabaseBackup(),
            };
        } catch (\Throwable $e) {
            return back()->with('error', 'Backup failed: ' . $e->getMessage());
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
            return back()->with('error', 'Media backup failed: ' . $e->getMessage());
        }
    }

    private function doDatabaseBackup()
    {
        $filename = 'backup-' . Carbon::now()->format('Y-m-d-H-i-s') . '.sql';
        file_put_contents($this->backupDir() . '/' . $filename, $this->buildSqlDump());

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

        $filename = 'media-backup-' . Carbon::now()->format('Y-m-d-H-i-s') . '.zip';
        $path     = $this->backupDir() . '/' . $filename;

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Could not create the zip archive.');
        }
        $count = $this->addMediaToZip($zip, '');
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
        if (!class_exists('\ZipArchive')) {
            return back()->with('error', 'A full backup needs the PHP "zip" extension, which is not enabled on this server.');
        }

        $filename = 'full-backup-' . Carbon::now()->format('Y-m-d-H-i-s') . '.zip';
        $path     = $this->backupDir() . '/' . $filename;

        $zip = new \ZipArchive();
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
        if (!is_dir($mediaDir)) return 0;

        $count = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($mediaDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $file) {
            if ($file->isDir()) continue;
            // getSubPathname() gives the path relative to $mediaDir directly — robust
            // across OS path separators / drive-letter casing (no manual stripping).
            $relative = str_replace('\\', '/', $files->getSubPathname());
            $zip->addFile($file->getPathname(), $prefix . $relative);
            $count++;
        }
        return $count;
    }

    private function buildSqlDump(): string
    {
        $tables = DB::select('SHOW TABLES');
        $dbName = config('database.connections.mysql.database');
        $sql  = "-- Falcon CMS Backup\n-- Database: {$dbName}\n-- Date: " . now() . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $tableName = current((array) $table);

            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`")[0];
            $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            $sql .= $createTable->{'Create Table'} . ";\n\n";

            foreach (DB::table($tableName)->get() as $row) {
                $row     = (array) $row;
                $columns = array_keys($row);
                $values  = array_map(function ($value) {
                    if (is_null($value)) return 'NULL';
                    return "'" . addslashes($value) . "'";
                }, array_values($row));

                $sql .= "INSERT INTO `{$tableName}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
            }
            $sql .= "\n";
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;";
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
        $path = storage_path('app/backups/' . $filename);
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
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            return back()->with('error', 'Restoration failed: ' . $e->getMessage());
        }
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

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \Exception('Could not open the backup archive.');
        }

        // Classify entries.
        $sqlIndex     = null;
        $mediaEntries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || str_ends_with($name, '/')) continue; // skip dirs
            if (str_ends_with(strtolower($name), '.sql')) {
                // Prefer a file literally named database.sql when several exist.
                if ($sqlIndex === null || strtolower(basename($name)) === 'database.sql') {
                    $sqlIndex = $i;
                }
            } else {
                $mediaEntries[] = $name;
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
            $done[]   = "database ({$executed} statements)";
        }

        // 2) Media
        if (!empty($mediaEntries)) {
            $dest = $this->mediaDir();
            if (!is_dir($dest)) mkdir($dest, 0755, true);

            $count = 0;
            foreach ($mediaEntries as $name) {
                // Combined backups nest media under "media/"; strip that so files
                // land directly in storage/app/public. Old root-level zips just work.
                $target = preg_replace('#^media/#', '', $name);
                if ($target === '' || str_contains($target, '..')) continue; // safety

                $content = $zip->getFromName($name);
                if ($content === false) continue;

                $full = $dest . '/' . $target;
                $dir  = dirname($full);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                file_put_contents($full, $content);
                $count++;
            }
            $done[] = "{$count} media files";
        }

        $zip->close();

        if (empty($done)) {
            return back()->with('error', 'The archive did not contain a recognizable database dump or media files.');
        }

        falcon_log_activity('restored', "Restored from backup: {$filename} (" . implode(', ', $done) . ')');
        return back()->with('success', "Restored from \"{$filename}\": " . implode(' + ', $done) . '.');
    }

    /** Execute a full SQL dump string. Returns the number of statements run. */
    private function runSqlDump(string $sql): int
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql); // strip UTF-8 BOM
        $statements = $this->parseSqlStatements($sql);
        $executed   = 0;

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($statements as $stmt) {
                DB::unprepared($stmt);
                $executed++;
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
        return $executed;
    }

    // Parse a multi-statement SQL dump into individual statements,
    // correctly handling quoted strings, line comments, and block comments.
    private function parseSqlStatements(string $sql): array
    {
        $statements = [];
        $current    = '';
        $len        = strlen($sql);
        $inString   = false;
        $strChar    = '';
        $i          = 0;

        while ($i < $len) {
            $ch = $sql[$i];

            if ($inString) {
                if ($ch === '\\') {
                    $current .= $ch . ($sql[$i + 1] ?? '');
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
                $strChar  = $ch;
                $current .= $ch;
                $i++;
                continue;
            }

            if ($ch === '-' && isset($sql[$i + 1]) && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") $i++;
                continue;
            }

            if ($ch === '/' && isset($sql[$i + 1]) && $sql[$i + 1] === '*') {
                $i += 2;
                while ($i < $len - 1 && !($sql[$i] === '*' && $sql[$i + 1] === '/')) $i++;
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
        $path = storage_path('app/backups/' . $filename);
        if (!file_exists($path)) {
            abort(404);
        }

        return response()->download($path);
    }

    public function upload(Request $request)
    {
        $this->checkAccess();

        $maxBytes = $this->maxUploadBytes();
        $maxKb    = (int) ($maxBytes / 1024);

        $request->validate([
            'backup_file' => [
                'required',
                'file',
                'max:' . $maxKb,
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (!in_array($ext, ['sql', 'gz', 'zip'])) {
                        $fail('Only .sql, .sql.gz, or .zip backup files are allowed.');
                    }
                },
            ],
        ], [
            'backup_file.max' => 'The file exceeds the server upload limit of ' . $this->formatBytes($maxBytes) . '.',
        ]);

        try {
            $file      = $request->file('backup_file');
            $original  = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $ext       = $file->getClientOriginalExtension();
            $safe      = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $original);
            $filename  = $safe . '_uploaded_' . Carbon::now()->format('Y-m-d-H-i-s') . '.' . $ext;

            $file->move($this->backupDir(), $filename);

            falcon_log_activity('uploaded', "Uploaded backup file: {$filename}");
            return back()->with('success', "Backup file \"{$filename}\" uploaded successfully. You can now restore it from the list below.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    public function destroy($filename)
    {
        $this->checkAccess();

        $filename = basename($filename);
        $path = storage_path('app/backups/' . $filename);
        if (file_exists($path)) {
            unlink($path);
            return back()->with('success', 'Backup deleted successfully.');
        }

        return back()->with('error', 'Backup not found.');
    }
}

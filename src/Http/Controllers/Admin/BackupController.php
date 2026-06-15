<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BackupController extends Controller
{
    // Convert php.ini size string (e.g. "64M", "1G") to bytes
    private function iniToBytes(string $val): int
    {
        $val  = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
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

    public function index()
    {
        if (!auth()->user()->hasPermission('manage_settings') && !auth()->user()->hasPermission('access_backup_restore')) {
            abort(403);
        }

        $backups = [];
        $backupDir = storage_path('app/backups');
        
        if (file_exists($backupDir)) {
            $files = array_diff(scandir($backupDir), array('.', '..'));
            
            foreach ($files as $file) {
                $filePath = $backupDir . '/' . $file;
                if (is_file($filePath)) {
                    $backups[] = [
                        'name' => $file,
                        'size' => round(filesize($filePath) / 1024 / 1024, 2) . ' MB',
                        'date' => Carbon::createFromTimestamp(filemtime($filePath))->format('Y-m-d H:i:s'),
                        'path' => $filePath
                    ];
                }
            }
        }

        // Sort by date descending
        usort($backups, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        $maxUploadBytes = $this->maxUploadBytes();
        $maxUploadHuman = $this->formatBytes($maxUploadBytes);

        return view('falcon-cms::admin.tools.backup', compact('backups', 'maxUploadBytes', 'maxUploadHuman'));
    }

    public function create()
    {
        if (!auth()->user()->hasPermission('manage_settings') && !auth()->user()->hasPermission('access_backups') && !auth()->user()->hasPermission('access_tools')) {
            abort(403);
        }

        try {
            $filename = 'backup-' . Carbon::now()->format('Y-m-d-H-i-s') . '.sql';
            $backupDir = storage_path('app/backups');

            if (!file_exists($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $path = $backupDir . '/' . $filename;

            // Simple Database Export Logic
            $tables = DB::select('SHOW TABLES');
            $dbName = config('database.connections.mysql.database');
            $sql = "-- Lazy CMS Backup\n-- Database: {$dbName}\n-- Date: " . now() . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($tables as $table) {
                $tableName = current((array)$table);
                
                // Structure
                $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`")[0];
                $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $sql .= $createTable->{'Create Table'} . ";\n\n";

                // Data
                $rows = DB::table($tableName)->get();
                foreach ($rows as $row) {
                    $row = (array)$row;
                    $columns = array_keys($row);
                    $values = array_map(function($value) {
                        if (is_null($value)) return 'NULL';
                        return "'" . addslashes($value) . "'";
                    }, array_values($row));
                    
                    $sql .= "INSERT INTO `{$tableName}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
            $sql .= "SET FOREIGN_KEY_CHECKS=1;";

            file_put_contents($path, $sql);

            lazy_log_activity('created', "Created a database backup: {$filename}");
            return redirect()->back()->with('success', 'Backup created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }

    public function restore($filename)
    {
        if (!auth()->user()->hasPermission('manage_settings') && !auth()->user()->hasPermission('access_backups') && !auth()->user()->hasPermission('access_tools')) {
            abort(403);
        }

        $filename = basename($filename);
        $path = storage_path('app/backups/' . $filename);
        if (!file_exists($path)) {
            return redirect()->back()->with('error', 'Backup file not found.');
        }

        try {
            // Decompress if needed
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($ext === 'gz') {
                $sql = gzdecode(file_get_contents($path));
            } elseif ($ext === 'zip') {
                $zip = new \ZipArchive();
                if ($zip->open($path) !== true) throw new \Exception('Could not open zip file.');
                $sql = $zip->getFromIndex(0);
                $zip->close();
            } else {
                $sql = file_get_contents($path);
            }

            if ($sql === false || trim($sql) === '') {
                throw new \Exception('Backup file is empty or could not be read.');
            }

            // Remove UTF-8 BOM if present
            $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);

            $statements = $this->parseSqlStatements($sql);
            $executed   = 0;

            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            foreach ($statements as $stmt) {
                DB::unprepared($stmt);
                $executed++;
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            lazy_log_activity('restored', "Restored database from snapshot: {$filename} ({$executed} statements)");
            return redirect()->back()->with('success', "Database restored successfully from \"{$filename}\" ({$executed} statements executed).");
        } catch (\Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            return redirect()->back()->with('error', 'Restoration failed: ' . $e->getMessage());
        }
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

            // Inside a quoted string
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

            // Start of a quoted string
            if ($ch === '"' || $ch === "'") {
                $inString = true;
                $strChar  = $ch;
                $current .= $ch;
                $i++;
                continue;
            }

            // Line comment: -- ...
            if ($ch === '-' && isset($sql[$i + 1]) && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") $i++;
                continue;
            }

            // Block comment: /* ... */
            if ($ch === '/' && isset($sql[$i + 1]) && $sql[$i + 1] === '*') {
                $i += 2;
                while ($i < $len - 1 && !($sql[$i] === '*' && $sql[$i + 1] === '/')) $i++;
                $i += 2;
                continue;
            }

            // Statement delimiter
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

        // Trailing statement without semicolon
        $stmt = trim($current);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }

        return $statements;
    }

    public function download($filename)
    {
        if (!auth()->user()->hasPermission('manage_settings') && !auth()->user()->hasPermission('access_backups') && !auth()->user()->hasPermission('access_tools')) {
            abort(403);
        }

        $filename = basename($filename);
        $path = storage_path('app/backups/' . $filename);
        if (!file_exists($path)) {
            abort(404);
        }

        return response()->download($path);
    }

    public function upload(Request $request)
    {
        if (!auth()->user()->hasPermission('manage_settings') && !auth()->user()->hasPermission('access_backups') && !auth()->user()->hasPermission('access_tools')) {
            abort(403);
        }

        $maxBytes = $this->maxUploadBytes();
        $maxKb    = (int) ($maxBytes / 1024);

        $request->validate([
            'backup_file' => [
                'required',
                'file',
                'max:' . $maxKb,
                function ($attribute, $value, $fail) {
                    $ext      = strtolower($value->getClientOriginalExtension());
                    $mime     = strtolower($value->getMimeType() ?? '');
                    $allowed  = ['sql', 'gz', 'zip'];
                    $allowedMime = ['text/plain', 'application/sql', 'application/octet-stream',
                                    'application/x-sql', 'application/gzip', 'application/zip',
                                    'application/x-gzip', 'application/x-zip-compressed'];
                    if (!in_array($ext, $allowed)) {
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
            $backupDir = storage_path('app/backups');

            if (!file_exists($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $file->move($backupDir, $filename);

            lazy_log_activity('uploaded', "Uploaded backup file: {$filename}");
            return redirect()->back()->with('success', "Backup file \"{$filename}\" uploaded successfully. You can now restore it from the list below.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    public function destroy($filename)
    {
        if (!auth()->user()->hasPermission('manage_settings') && !auth()->user()->hasPermission('access_backups') && !auth()->user()->hasPermission('access_tools')) {
            abort(403);
        }

        $filename = basename($filename);
        $path = storage_path('app/backups/' . $filename);
        if (file_exists($path)) {
            unlink($path);
            return redirect()->back()->with('success', 'Backup deleted successfully.');
        }

        return redirect()->back()->with('error', 'Backup not found.');
    }
}

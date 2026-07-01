<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use FalconCms\Core\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function index(Request $request)
    {
        $query = Media::query();

        // Search filter
        if ($request->filled('s')) {
            $search = $request->s;
            $query->where(function($q) use ($search) {
                $q->where('filename', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('alt_text', 'like', "%{$search}%");
            });
        }

        // Type filter
        if ($request->filled('type') && $request->type !== 'all') {
            if ($request->type === 'image') {
                $query->where('mime_type', 'like', 'image/%');
            } elseif ($request->type === 'video') {
                $query->where('mime_type', 'like', 'video/%');
            } elseif ($request->type === 'pdf') {
                $query->where('mime_type', 'application/pdf');
            }
        }

        // Date filter (Format: Ym, e.g., 202404)
        if ($request->filled('m')) {
            $year = substr($request->m, 0, 4);
            $month = substr($request->m, 4, 2);
            $query->whereYear('created_at', $year)
                  ->whereMonth('created_at', $month);
        }

        $perPage = ($request->ajax() || $request->expectsJson())
            ? (int)($request->input('per_page', 100))
            : 10;
        $media = $query->latest()->paginate($perPage)->appends($request->all());

        // Get unique months for filter dropdown
        $months = Media::selectRaw('DISTINCT DATE_FORMAT(created_at, "%Y%m") as month_val, DATE_FORMAT(created_at, "%M %Y") as month_label')
            ->orderBy('month_val', 'desc')
            ->get();

        // Get existing types for filter dropdown
        $hasImages = Media::where('mime_type', 'like', 'image/%')->exists();
        $hasVideos = Media::where('mime_type', 'like', 'video/%')->exists();
        $hasPdfs = Media::where('mime_type', 'application/pdf')->exists();

        $types = [];
        if ($hasImages) $types['image'] = 'Images';
        if ($hasVideos) $types['video'] = 'Video';
        if ($hasPdfs) $types['pdf'] = 'PDF';

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json($media);
        }
        return view('falcon-cms::admin.media.index', compact('media', 'months', 'types'));
    }

    public function create()
    {
        return view('falcon-cms::admin.media.create');
    }

    public function store(Request $request)
    {
        try {
            // Cap uploads at the real server limit (upload_max_filesize / post_max_size)
            // so the accepted size matches what the upload page shows.
            $maxKb = (int) (falcon_max_upload_bytes() / 1024);
            $request->validate([
                'file' => 'required|file' . ($maxKb > 0 ? '|max:' . $maxKb : ''),
            ]);

            $file = $request->file('file');
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = strtolower($file->getClientOriginalExtension());
            $mimeType = $file->getMimeType();

            // Block executable and server-side script extensions regardless of allowed list
            $blockedExtensions = ['php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
                                   'asp', 'aspx', 'jsp', 'js', 'cgi', 'pl', 'py', 'rb',
                                   'sh', 'bash', 'exe', 'bat', 'cmd', 'htaccess', 'htpasswd',
                                   'svg', 'svgz', 'xml', 'xsl', 'xslt'];
            if (in_array($extension, $blockedExtensions)) {
                return response()->json([
                    'success' => false,
                    'message' => "File format '.{$extension}' is not allowed for security reasons."
                ], 422);
            }

            // Validate allowed formats
            $allowedRaw = get_cms_option('performance_allowed_formats', '[]');
            $allowedFormats = is_array($allowedRaw) ? $allowedRaw : json_decode($allowedRaw, true);

            if (!empty($allowedFormats) && !in_array($extension, $allowedFormats)) {
                return response()->json([
                    'success' => false,
                    'message' => "File format '.{$extension}' is not allowed. Allowed: " . implode(', ', $allowedFormats)
                ], 422);
            }
            
            $slugName  = Str::slug($originalName) ?: 'upload';
            $isImage   = strpos($mimeType, 'image/') === 0;
            $yearMonth = now()->format('Y/m');
            $mediaDir  = 'media/' . $yearMonth;

            $width = null;
            $height = null;
            $path = null;

            if ($isImage) {
                // Get original dimensions
                $imgSize = @getimagesize($file->getRealPath());
                if ($imgSize) {
                    $width = $imgSize[0];
                    $height = $imgSize[1];
                }

                $quality  = (int)get_cms_option('performance_image_quality', 80);
                $maxWidth = (int)get_cms_option('performance_max_image_width', 1920);
                $autoWebp = get_cms_option('performance_webp_conversion', '1') == '1';

                $targetExt  = ($autoWebp && function_exists('imagewebp')) ? 'webp' : $extension;
                $targetMime = $targetExt === 'webp' ? 'image/webp' : $mimeType;
                $processed  = false;

                // Try processing with GD
                if (function_exists('imagecreatefromstring')) {
                    $img = @imagecreatefromstring(file_get_contents($file->getRealPath()));
                    if ($img) {
                        if ($width > $maxWidth) {
                            $newWidth  = $maxWidth;
                            $newHeight = (int)floor($height * ($maxWidth / $width));
                            $tmp = imagecreatetruecolor($newWidth, $newHeight);
                            imagealphablending($tmp, false);
                            imagesavealpha($tmp, true);
                            imagecopyresampled($tmp, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                            imagedestroy($img);
                            $img    = $tmp;
                            $width  = $newWidth;
                            $height = $newHeight;
                        }

                        ob_start();
                        $success = false;
                        if ($targetMime === 'image/webp' && function_exists('imagewebp')) {
                            imagepalettetotruecolor($img);
                            imagealphablending($img, true);
                            imagesavealpha($img, true);
                            $success = imagewebp($img, null, $quality);
                        } elseif (in_array($targetMime, ['image/jpeg','image/jpg']) && function_exists('imagejpeg')) {
                            $success = imagejpeg($img, null, $quality);
                        } elseif ($targetMime === 'image/png' && function_exists('imagepng')) {
                            $success = imagepng($img, null, (int)round(9 * (100 - $quality) / 100));
                        }
                        $imageData = ob_get_clean();

                        if ($success && $imageData) {
                            [$filename, $savePath] = $this->uniqueMediaPath($mediaDir, $slugName, $targetExt);
                            Storage::disk('public')->put($savePath, $imageData);
                            $path     = $savePath;
                            $mimeType = $targetMime;
                            $processed = true;
                        }
                        imagedestroy($img);
                    }
                }

                // Fallback: store original file as-is
                if (!$processed) {
                    [$filename, $savePath] = $this->uniqueMediaPath($mediaDir, $slugName, $extension);
                    $path = $file->storeAs($mediaDir, $filename, 'public');
                }
            } else {
                // Non-image files
                [$filename, $savePath] = $this->uniqueMediaPath($mediaDir, $slugName, $extension);
                $path = $file->storeAs($mediaDir, $filename, 'public');
            }

            // Ensure we have a path
            if (!$path) {
                throw new \Exception("Failed to store file.");
            }

            // Get file size
            $compressedSize = Storage::disk('public')->size($path);

            $media = Media::create([
                'title' => $originalName,
                'filename' => $filename,
                'path' => $path,
                'mime_type' => $mimeType,
                'width' => $width,
                'height' => $height,
                'original_size' => $file->getSize(),
                'compressed_size' => $compressedSize,
                'user_id' => auth()->id(),
            ]);

            falcon_log_activity('created', "Uploaded media: {$media->filename}", $media);

            return response()->json([
                'success' => true,
                'data' => $media
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No items selected'], 422);
        }

        $mediaItems = Media::whereIn('id', $ids)->get();
        foreach ($mediaItems as $item) {
            $name = $item->filename;
            $this->deleteMediaFiles($item->path);
            $item->delete();
            falcon_log_activity('deleted', "Deleted media: {$name}");
        }

        return response()->json(['success' => true]);
    }

    public function update(Request $request, Media $media)
    {
        $oldTitle = $media->title;
        $newTitle = $request->input('title');

        $media->alt_text = $request->input('alt_text');
        $media->title = $newTitle;
        $media->caption = $request->input('caption');
        $media->description = $request->input('description');

        // Rename file if title changed and it's not empty
        if ($newTitle && $newTitle !== $oldTitle) {
            $extension = pathinfo($media->path, PATHINFO_EXTENSION);
            $slug = \Illuminate\Support\Str::slug($newTitle);
            
            // Generate unique filename — preserve existing year/month directory
            $dir = dirname($media->path);
            $newFilename = $slug . '.' . $extension;
            $newPath = $dir . '/' . $newFilename;

            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($newPath)) {
                $newFilename = $slug . '-' . time() . '.' . $extension;
                $newPath = $dir . '/' . $newFilename;
            }

            // Move the file
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($media->path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->move($media->path, $newPath);
                $media->path = $newPath;
                $media->filename = $newFilename;
            }
        }

        $media->save();

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
            'success' => true,
            'data' => $media
        ]);
        }

        return redirect()->back()->with('success', 'Media updated successfully');
    }

    public function destroy(Media $media)
    {
        $name = $media->filename;
        $this->deleteMediaFiles($media->path);
        $media->delete();
        falcon_log_activity('deleted', "Deleted media: {$name}");
        return response()->json(['success' => true]);
    }

    /**
     * Delete a media file plus any generated size variants ("name-WIDTHxHEIGHT.ext")
     * sitting next to it — matching WordPress' behaviour of removing every size when
     * an attachment is deleted. Variant files that are themselves tracked as their
     * own media item are left alone.
     */
    private function deleteMediaFiles(string $path): void
    {
        $disk = Storage::disk('public');
        $disk->delete($path); // the original

        $info = pathinfo($path);
        if (empty($info['filename'])) return;

        $folder  = (!isset($info['dirname']) || $info['dirname'] === '.' || $info['dirname'] === '') ? '' : $info['dirname'];
        $ext     = $info['extension'] ?? '';
        $pattern = '/^' . preg_quote($info['filename'], '/') . '-\d+x\d+'
                 . ($ext !== '' ? '\.' . preg_quote($ext, '/') : '') . '$/i';

        foreach ($disk->files($folder) as $f) {
            if (preg_match($pattern, basename($f)) && !Media::where('path', $f)->exists()) {
                $disk->delete($f);
            }
        }
    }

    public function bulkOptimize()
    {
        try {
            $mediaItems = Media::where('mime_type', 'like', 'image/%')->get();
            $count = 0;
            $quality = (int)get_cms_option('performance_image_quality', 80);
            $maxWidth = (int)get_cms_option('performance_max_image_width', 1920);
            $autoWebp = get_cms_option('performance_webp_conversion', '1') == '1';

            if (!function_exists('imagecreatefromstring')) {
                throw new \Exception("GD extension with imagecreatefromstring is required.");
            }

            foreach ($mediaItems as $media) {
                $filePath = storage_path('app/public/' . $media->path);
                if (!file_exists($filePath)) continue;

                // Skip if already webp and we are targeting webp
                if ($autoWebp && $media->mime_type === 'image/webp') continue;

                $img = @imagecreatefromstring(file_get_contents($filePath));
                if (!$img) continue;

                $width = imagesx($img);
                $height = imagesy($img);

                // Resize if needed
                if ($width > $maxWidth) {
                    $newWidth = $maxWidth;
                    $newHeight = (int)floor($height * ($maxWidth / $width));
                    $tmp = imagecreatetruecolor($newWidth, $newHeight);
                    imagealphablending($tmp, false);
                    imagesavealpha($tmp, true);
                    imagecopyresampled($tmp, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    imagedestroy($img);
                    $img = $tmp;
                    $width = $newWidth;
                    $height = $newHeight;
                }

                $filename = pathinfo($media->filename, PATHINFO_FILENAME);
                $extension = $autoWebp ? 'webp' : pathinfo($media->path, PATHINFO_EXTENSION);
                $newFilename = $filename . '-' . time() . '.' . $extension;
                $dir = dirname($media->path);
                $newPath = $dir . '/' . $newFilename;

                ob_start();
                $success = false;
                if ($autoWebp && function_exists('imagewebp')) {
                    imagepalettetotruecolor($img);
                    imagealphablending($img, true);
                    imagesavealpha($img, true);
                    $success = imagewebp($img, null, $quality);
                } else {
                    $ext = strtolower($extension);
                    if (($ext === 'jpg' || $ext === 'jpeg') && function_exists('imagejpeg')) {
                        $success = imagejpeg($img, null, $quality);
                    } elseif ($ext === 'png' && function_exists('imagepng')) {
                        $success = imagepng($img, null, (int)round(9 * (100 - $quality) / 100));
                    }
                }

                $imageData = ob_get_clean();
                if ($success && $imageData) {
                    // Delete old file
                    Storage::disk('public')->delete($media->path);
                    
                    // Save new file
                    Storage::disk('public')->put($newPath, $imageData);

                    // Update Database
                    $media->update([
                        'filename' => $newFilename,
                        'path' => $newPath,
                        'mime_type' => $autoWebp ? 'image/webp' : $media->mime_type,
                        'width' => $width,
                        'height' => $height,
                        'compressed_size' => strlen($imageData)
                    ]);
                    $count++;
                }
                imagedestroy($img);
            }

            falcon_log_activity('settings_updated', "Bulk optimized {$count} media items");

            return response()->json([
                'success' => true, 
                'message' => "Successfully optimized {$count} images."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Return a [filename, storagePath] pair that doesn't already exist on the public disk.
     * Appends -1, -2, … only when needed.
     */
    private function uniqueMediaPath(string $dir, string $slug, string $ext): array
    {
        $filename = $slug . '.' . $ext;
        $path     = $dir . '/' . $filename;
        $n        = 1;
        while (Storage::disk('public')->exists($path)) {
            $filename = $slug . '-' . $n . '.' . $ext;
            $path     = $dir . '/' . $filename;
            $n++;
        }
        return [$filename, $path];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
}

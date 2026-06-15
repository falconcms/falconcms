<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use FalconCms\Core\Models\ProductData;
use FalconCms\Core\Models\ProductDownload;

class ProductDownloadController extends Controller
{
    public function store(Request $request, int $productDataId)
    {
        $productData = ProductData::findOrFail($productDataId);

        $request->validate([
            'media_path'     => 'required|string|max:1000',
            'name'           => 'nullable|string|max:255',
            'download_limit' => 'nullable|integer|min:1|max:9999',
        ]);

        $mediaPath = $request->input('media_path');
        $fileName  = $request->input('name') ?: basename($mediaPath);

        $download = ProductDownload::create([
            'product_id'     => $productData->id,
            'name'           => $fileName,
            'file_path'      => $mediaPath,
            'file_size'      => null,
            'download_limit' => $request->input('download_limit'),
            'sort_order'     => ProductDownload::where('product_id', $productData->id)->max('sort_order') + 1,
        ]);

        return response()->json([
            'success'  => true,
            'download' => [
                'id'             => $download->id,
                'name'           => $download->name,
                'file_size'      => $download->file_size,
                'download_limit' => $download->download_limit,
                'delete_url'     => route('admin.shop.products.downloads.destroy', $download->id),
            ],
        ]);
    }

    public function destroy(ProductDownload $download)
    {
        // Only delete from local disk if it was a direct upload (not a media library file).
        if (str_starts_with($download->file_path, 'downloads/')) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($download->file_path);
        }
        $download->delete();

        return response()->json(['success' => true]);
    }
}

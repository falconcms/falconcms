# Media Library

The Media Library handles all file uploads: images, videos, and documents.

![The media library](/screenshots/media-library.webp)

*Every upload in one grid, filterable by type and date, with bulk selection.*

## Uploading Files

**Admin → Media → Add New**

- Drag and drop files or click to browse
- Supports: JPEG, PNG, GIF, WebP, SVG, MP4, PDF, and more
- Multiple files can be uploaded at once

## Managing Media

**Admin → Media** shows all uploaded files in a grid or list view.

Click any file to:
- Edit **Alt text** (important for SEO and accessibility)
- Edit **Title** and **Caption**. The title is a label: changing it does not rename the file
  or change its address, so every page that shows the image keeps working.
- Get the **direct URL**
- **Delete** the file

## Folders

Keep the library tidy with folders, and folders inside folders. They are on the **Media** page,
the **upload** page and the media window that opens from pages, posts, the builder and settings.

- **All files** shows everything; **Uncategorized** shows files in no folder (every file starts
  there). Click a folder to see its files; the number beside it is how many it holds.
- **New folder**: give it a name and choose **Where** — at the top level (a main folder) or
  inside another folder. It is also beside **Upload to**, so a folder can be made right before
  uploading into it.
- **Upload to**: uploads go into the folder picked there (the open folder to begin with).
- **Move files**: drag them onto a folder, pick **Move to folder** under Bulk actions, or change
  **Folder** in a file's details.
- **Rename** a folder any time with its pencil, and **move** it with its Move button — inside
  another folder, or to the top level to make it a main folder again. With a mouse you can also
  drag a folder onto another one, or onto *Drop here to make it a main folder*. On a phone the
  buttons are always shown.
- **Delete** a folder with its bin: its files are not deleted — they go back to Uncategorized —
  and its folders move up one level.

Folders only group files. A file keeps its address whichever folder it is in, so moving it
never breaks a page that shows it. Making and changing folders needs **Media → Add New**
access; the library's read-only users see them without the buttons.

## Bulk Operations

Select multiple files and **Bulk Delete** them at once, or move them into a folder.

## Image optimisation

Images are optimised as they are uploaded. Set this up under **Customizer → Performance**:

- **WebP Conversion:** uploads are converted to WebP.
- **Image Quality** and **Max Image Width:** the compression level, and the widest an image is kept.

**Optimize Existing Images Now**, in the same section, applies these settings to images already in
the library. It replaces each original with an optimised copy under a new file name, and this
cannot be undone. Take a backup first, and check pages that link to an image file directly.

## AVIF copies

With **Serve AVIF Images** on (Customizer → Performance), every library image also gets an AVIF
copy. AVIF is usually 20–30% smaller than WebP. Browsers that support it get the AVIF, and the
rest keep the original. New uploads get their copy automatically.

For images already in the library, press **Generate AVIF copies** at the top of the Media Library.
It works through the library in small batches and shows its progress. It only adds copies: the
originals, their file names and their addresses are never changed. An image that would not get
smaller is skipped.

![Generate AVIF copies](/screenshots/media-avif.webp)

*The button appears while Serve AVIF Images is on and the server can write AVIF.*

AVIF needs PHP's GD extension with AVIF support. On a server without it, the switch is locked off
in the Customizer with the reason, and the button does not appear. Ask your host to enable AVIF
in GD.

## Using Media in Content

### In the Editor
Click the **Image** button in any content field or click **Featured Image** to open the media picker.

### In Builder Elements
Image, Gallery, and Video elements all have media picker buttons that open the library.

### In Code

```php
use Acme\CmsDashboard\Models\Media;

// Get all media
$images = Media::where('type', 'image')->latest()->get();

foreach ($images as $media) {
    echo $media->url;        // Full URL
    echo $media->filename;   // "photo.jpg"
    echo $media->alt;        // Alt text
    echo $media->size;       // File size in bytes
    echo $media->mime_type;  // "image/jpeg"
}

// Get the featured image of a post
$post = get_falcon_post('my-post');
echo $post->featured_image;  // URL string
```

## Storage

Files are stored in Laravel's `storage/app/public/` directory and served via the `storage` symlink.

If uploads aren't working, run:

```bash
php artisan storage:link
```

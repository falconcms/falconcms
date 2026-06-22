<?php
 
use Illuminate\Support\Facades\Route;
use FalconCms\Core\Http\Controllers\Admin\PostController;
use FalconCms\Core\Http\Controllers\Admin\PostTypeController;
use FalconCms\Core\Http\Controllers\Admin\MediaController;
use FalconCms\Core\Http\Controllers\Admin\DashboardController;
use FalconCms\Core\Http\Controllers\Admin\CustomFieldController;
use FalconCms\Core\Http\Controllers\Admin\UserController;
use FalconCms\Core\Http\Controllers\Admin\LoginController;
use FalconCms\Core\Http\Controllers\Admin\RegisterController;
use FalconCms\Core\Http\Controllers\Admin\RoleController;
use FalconCms\Core\Http\Controllers\Admin\AcptCptController;
use FalconCms\Core\Http\Controllers\Admin\AcptTaxonomyController;
use FalconCms\Core\Http\Controllers\Admin\AcptTermController;
use FalconCms\Core\Http\Controllers\Admin\WidgetController;
use FalconCms\Core\Http\Controllers\Admin\LanguageController;
use FalconCms\Core\Http\Controllers\Admin\ThemeController;
use FalconCms\Core\Http\Controllers\Admin\ShopController;
use FalconCms\Core\Http\Controllers\ShopFrontendController;
use FalconCms\Core\Http\Controllers\FrontendController;

// 1. Dynamic Login & Registration URLs (Highest Priority - Outside any group)
$login_slug = get_cms_option('login_url', 'super-lazy-admin');
$register_slug = get_cms_option('register_url', 'super-lazy-register');

Route::middleware(['web', \FalconCms\Core\Http\Middleware\SecurityHeadersMiddleware::class])->group(function() use ($login_slug, $register_slug) {
    Route::get($login_slug, [LoginController::class, 'showLoginForm'])->name('admin.login');
    Route::post($login_slug, [LoginController::class, 'login'])->middleware('throttle:10,1');

    Route::get($register_slug, [RegisterController::class, 'showRegistrationForm'])->name('admin.register');
    Route::post($register_slug, [RegisterController::class, 'register'])->middleware('throttle:10,1');

    // Email verification (registration) — time-limited signed link + notice/resend
    Route::get('verify-email-notice', [RegisterController::class, 'verifyNotice'])->name('admin.verify.notice');
    Route::get('verify-email/{id}/{hash}', [RegisterController::class, 'verifyEmail'])->name('admin.verify.email')->middleware('throttle:20,1');
    Route::post('resend-verification', [RegisterController::class, 'resendVerification'])->name('admin.verify.resend')->middleware('throttle:5,1');

    // Password Recovery
    Route::get('forgot-password', [LoginController::class, 'showForgotPasswordForm'])->name('admin.password.request');
    Route::post('forgot-password', [LoginController::class, 'sendResetLinkEmail'])->name('admin.password.email')->middleware('throttle:5,1');
    Route::get('reset-password/{token}', [LoginController::class, 'showResetForm'])->name('admin.password.reset');
    Route::post('reset-password', [LoginController::class, 'resetPassword'])->name('admin.password.update')->middleware('throttle:5,1');

    // Admin magic login (passwordless)
    Route::post('admin-magic-login', [LoginController::class, 'requestAdminMagicLink'])->name('admin.magic.request')->middleware('throttle:5,1');
    Route::get('admin-magic-login/verify/{token}', [LoginController::class, 'verifyAdminMagicLink'])->name('admin.magic.verify');

    // Frontend magic email check (AJAX, rate-limited)
    Route::post('magic-email-check', [ShopFrontendController::class, 'checkMagicEmail'])->name('shop.magic.email.check')->middleware('throttle:30,1');

    // Redirect standard admin/login and admin/register to custom slugs
    Route::get('admin/login', function() use ($login_slug) { return redirect($login_slug); });
    Route::get('admin/register', function() use ($register_slug) { return redirect($register_slug); });
});

// 2. Authenticated Admin Routes
Route::prefix('admin')->name('admin.')->middleware(['web', \FalconCms\Core\Http\Middleware\SecurityHeadersMiddleware::class, \FalconCms\Core\Http\Middleware\AdminMiddleware::class])->group(function () {
    // Media and posts
    Route::post('media/bulk-delete', [MediaController::class, 'bulkDestroy'])->name('media.bulk-delete');
    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::get('media/upload', [MediaController::class, 'create'])->name('media.create');
    Route::post('media/bulk-optimize', [MediaController::class, 'bulkOptimize'])->name('media.bulk-optimize');
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::put('media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
 
    Route::get('edit-post', [PostController::class, 'edit'])->name('edit-post');
    Route::post('posts/bulk', [PostController::class, 'bulk'])->name('posts.bulk');
    Route::post('categories/bulk', [\FalconCms\Core\Http\Controllers\Admin\CategoryController::class, 'bulk'])->name('categories.bulk');
    Route::post('tags/bulk', [\FalconCms\Core\Http\Controllers\Admin\TagController::class, 'bulk'])->name('tags.bulk');
    Route::post('posts/{post}/restore', [PostController::class, 'restore'])->name('posts.restore')->withTrashed();
    Route::delete('posts/{post}/force-delete', [PostController::class, 'forceDelete'])->name('posts.force-delete')->withTrashed();
    // Classic editor revisions + autosave (must precede the resource route)
    Route::post('posts/{id}/autosave', [PostController::class, 'autosaveClassic'])->name('posts.autosave');
    Route::get('posts/{id}/revisions', [PostController::class, 'revisionsPage'])->name('posts.revisions');
    Route::post('posts/{id}/revisions/{revision}/restore', [PostController::class, 'restoreRevisionClassic'])->name('posts.revisions.restore');
    Route::delete('posts/{id}/revisions/{revision}', [PostController::class, 'deleteRevision'])->name('posts.revisions.delete');
    Route::delete('posts/{id}/revisions', [PostController::class, 'clearRevisions'])->name('posts.revisions.clear');
    Route::resource('posts', PostController::class);
    Route::get('falcon-builder-library', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'page'])->name('falcon-builder.library');
    Route::post('falcon-builder-library/post-cards', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'savePostCard'])->name('falcon-builder.post-cards.save');
    Route::delete('falcon-builder-library/post-cards/{id}', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'deletePostCard'])->name('falcon-builder.post-cards.delete');
    Route::patch('falcon-builder-library/post-cards/{id}', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'updatePostCard'])->name('falcon-builder.post-cards.update');
    Route::get('falcon-builder-library/post-cards/{id}/builder', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'editPostCardBuilder'])->name('falcon-builder.post-cards.builder');
    Route::post('falcon-builder-library/post-cards/{id}/builder', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'savePostCardLayout'])->name('falcon-builder.post-cards.save-layout');
    Route::post('falcon-builder-library/mega-menus', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'saveMegaMenu'])->name('falcon-builder.mega-menus.save');
    Route::delete('falcon-builder-library/mega-menus/{id}', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'deleteMegaMenu'])->name('falcon-builder.mega-menus.delete');
    Route::patch('falcon-builder-library/mega-menus/{id}', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'updateMegaMenu'])->name('falcon-builder.mega-menus.update');
    Route::get('falcon-builder-library/mega-menus/{id}/builder', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'editMegaMenuBuilder'])->name('falcon-builder.mega-menus.builder');
    Route::post('falcon-builder-library/mega-menus/{id}/builder', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'saveMegaMenuLayout'])->name('falcon-builder.mega-menus.save-layout');
    Route::post('falcon-builder-library/mega-menus/{id}/settings', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'saveMegaMenuSettings'])->name('falcon-builder.mega-menus.save-settings');
    Route::get('falcon-builder/library', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'index'])->name('falcon-builder.library.index');
    Route::post('falcon-builder/library/save', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'save'])->name('falcon-builder.library.save');
    Route::delete('falcon-builder/library/{type}/{id}', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'delete'])->name('falcon-builder.library.delete');
    Route::get('falcon-builder/global-sections', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'listGlobalSections'])->name('falcon-builder.global-sections.list');
    Route::post('falcon-builder/global-sections', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'saveGlobalSection'])->name('falcon-builder.global-sections.save');
    Route::patch('falcon-builder/global-sections/{id}', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'updateGlobalSection'])->name('falcon-builder.global-sections.update');
    Route::delete('falcon-builder/global-sections/{id}', [\FalconCms\Core\Http\Controllers\Admin\BuilderLibraryController::class, 'deleteGlobalSection'])->name('falcon-builder.global-sections.delete');
    Route::post('falcon-builder/card-preview', function(\Illuminate\Http\Request $r) {
        $s = $r->input('settings', []);
        try {
            $html = view('falcon-cms::frontend.builder.elements.card', [
                'el' => ['settings' => $s],
                'previewDevice' => $r->input('device'),
            ])->render();
            return response()->json(['success' => true, 'html' => $html]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'html' => '']);
        }
    })->name('falcon-builder.card-preview');
    Route::get('falcon-builder/{id}', [PostController::class, 'builder'])->name('falcon-builder');
    Route::post('falcon-builder/{id}/save', [PostController::class, 'saveBuilder'])->name('falcon-builder.save');
    // Revisions + Autosave
    Route::post('falcon-builder/{id}/autosave', [PostController::class, 'autosaveBuilder'])->name('falcon-builder.autosave');
    Route::get('falcon-builder/{id}/revisions', [PostController::class, 'revisions'])->name('falcon-builder.revisions');
    Route::post('falcon-builder/{id}/revisions/{revision}/restore', [PostController::class, 'restoreRevision'])->name('falcon-builder.revisions.restore');
    Route::delete('falcon-builder/{id}/revisions/{revision}', [PostController::class, 'deleteRevisionBuilder'])->name('falcon-builder.revisions.delete');
    Route::post('posts/{id}/variations/ajax', [PostController::class, 'ajaxSaveVariations'])->name('posts.variations.ajax-save');
    Route::get('falcon-builder/{id}/preview', [PostController::class, 'previewBuilder'])->name('falcon-builder.preview');
 
    Route::post('pages/bulk', [\FalconCms\Core\Http\Controllers\Admin\PageController::class, 'bulk'])->name('pages.bulk');
    Route::post('pages/{page}/restore', [\FalconCms\Core\Http\Controllers\Admin\PageController::class, 'restore'])->name('pages.restore')->withTrashed();
    Route::delete('pages/{page}/force-delete', [\FalconCms\Core\Http\Controllers\Admin\PageController::class, 'forceDelete'])->name('pages.force-delete')->withTrashed();
    // Page revisions + autosave (must precede the resource route)
    Route::post('pages/{id}/autosave', [\FalconCms\Core\Http\Controllers\Admin\PageController::class, 'autosaveClassic'])->name('pages.autosave');
    Route::get('pages/{id}/revisions', [\FalconCms\Core\Http\Controllers\Admin\PageController::class, 'revisionsPage'])->name('pages.revisions');
    Route::post('pages/{id}/revisions/{revision}/restore', [\FalconCms\Core\Http\Controllers\Admin\PageController::class, 'restoreRevisionClassic'])->name('pages.revisions.restore');
    Route::delete('pages/{id}/revisions/{revision}', [\FalconCms\Core\Http\Controllers\Admin\PageController::class, 'deleteRevision'])->name('pages.revisions.delete');
    Route::delete('pages/{id}/revisions', [\FalconCms\Core\Http\Controllers\Admin\PageController::class, 'clearRevisions'])->name('pages.revisions.clear');
    Route::resource('pages', \FalconCms\Core\Http\Controllers\Admin\PageController::class);

    // Categories
    Route::get('categories', [\FalconCms\Core\Http\Controllers\Admin\CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [\FalconCms\Core\Http\Controllers\Admin\CategoryController::class, 'store'])->name('categories.store');
    Route::get('categories/edit/{category}', [\FalconCms\Core\Http\Controllers\Admin\CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('categories/{category}', [\FalconCms\Core\Http\Controllers\Admin\CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [\FalconCms\Core\Http\Controllers\Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

    // Tags
    Route::get('tags', [\FalconCms\Core\Http\Controllers\Admin\TagController::class, 'index'])->name('tags.index');
    Route::post('tags', [\FalconCms\Core\Http\Controllers\Admin\TagController::class, 'store'])->name('tags.store');
    Route::get('tags/edit/{tag}', [\FalconCms\Core\Http\Controllers\Admin\TagController::class, 'edit'])->name('tags.edit');
    Route::put('tags/{tag}', [\FalconCms\Core\Http\Controllers\Admin\TagController::class, 'update'])->name('tags.update');
    Route::delete('tags/{tag}', [\FalconCms\Core\Http\Controllers\Admin\TagController::class, 'destroy'])->name('tags.destroy');

    Route::resource('post-types', PostTypeController::class)->only(['index', 'store', 'destroy']);
    
    Route::post('categories/ajax', function(\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'name' => 'required|string',
            'parent_id' => 'nullable|exists:categories,id',
            'lang_code' => 'nullable|string'
        ]);
        
        $lang = $validated['lang_code'] ?? app()->getLocale();
        $category = \FalconCms\Core\Models\Category::create([
            'name' => $validated['name'],
            'parent_id' => !empty($validated['parent_id']) ? $validated['parent_id'] : null,
            'lang_code' => $lang,
            'slug' => \FalconCms\Core\Models\Category::generateUniqueSlug($validated['name'], 0, $lang)
        ]);
        
        return response()->json($category);
    })->name('categories.ajax');

    // Product Categories (dedicated, first-class — mirrors Categories)
    Route::get('product-categories', [\FalconCms\Core\Http\Controllers\Admin\ProductCategoryController::class, 'index'])->name('product-categories.index');
    Route::post('product-categories', [\FalconCms\Core\Http\Controllers\Admin\ProductCategoryController::class, 'store'])->name('product-categories.store');
    Route::post('product-categories/bulk', [\FalconCms\Core\Http\Controllers\Admin\ProductCategoryController::class, 'bulk'])->name('product-categories.bulk');
    Route::post('product-categories/ajax', [\FalconCms\Core\Http\Controllers\Admin\ProductCategoryController::class, 'ajax'])->name('product-categories.ajax');
    Route::get('product-categories/edit/{product_category}', [\FalconCms\Core\Http\Controllers\Admin\ProductCategoryController::class, 'edit'])->name('product-categories.edit');
    Route::put('product-categories/{product_category}', [\FalconCms\Core\Http\Controllers\Admin\ProductCategoryController::class, 'update'])->name('product-categories.update');
    Route::delete('product-categories/{product_category}', [\FalconCms\Core\Http\Controllers\Admin\ProductCategoryController::class, 'destroy'])->name('product-categories.destroy');

    // Product Tags (dedicated, first-class — mirrors Tags)
    Route::get('product-tags', [\FalconCms\Core\Http\Controllers\Admin\ProductTagController::class, 'index'])->name('product-tags.index');
    Route::post('product-tags', [\FalconCms\Core\Http\Controllers\Admin\ProductTagController::class, 'store'])->name('product-tags.store');
    Route::post('product-tags/bulk', [\FalconCms\Core\Http\Controllers\Admin\ProductTagController::class, 'bulk'])->name('product-tags.bulk');
    Route::post('product-tags/ajax', [\FalconCms\Core\Http\Controllers\Admin\ProductTagController::class, 'ajax'])->name('product-tags.ajax');
    Route::get('product-tags/edit/{product_tag}', [\FalconCms\Core\Http\Controllers\Admin\ProductTagController::class, 'edit'])->name('product-tags.edit');
    Route::put('product-tags/{product_tag}', [\FalconCms\Core\Http\Controllers\Admin\ProductTagController::class, 'update'])->name('product-tags.update');
    Route::delete('product-tags/{product_tag}', [\FalconCms\Core\Http\Controllers\Admin\ProductTagController::class, 'destroy'])->name('product-tags.destroy');

    // Navigation Menus
    Route::resource('menus', \FalconCms\Core\Http\Controllers\Admin\MenuManagementController::class);
    
    // Dynamic Taxonomy Terms
    Route::get('taxonomies/{slug}/terms', [\FalconCms\Core\Http\Controllers\Admin\TaxonomyTermController::class, 'index'])->name('old.terms.index');
    Route::post('taxonomies/{slug}/terms', [\FalconCms\Core\Http\Controllers\Admin\TaxonomyTermController::class, 'store'])->name('old.terms.store');
    Route::delete('taxonomies/{slug}/terms/{id}', [\FalconCms\Core\Http\Controllers\Admin\TaxonomyTermController::class, 'destroy'])->name('old.terms.destroy');
    Route::post('taxonomies/{slug}/terms/bulk', [\FalconCms\Core\Http\Controllers\Admin\TaxonomyTermController::class, 'bulk'])->name('old.terms.bulk');
 
    // Advanced Custom Post Types (ACPT) - Latest Version
    Route::prefix('acpt')->name('acpt.')->group(function() {
        Route::post('cpt/bulk', [AcptCptController::class, 'bulk'])->name('cpt.bulk');
        Route::post('cpt/{id}/toggle-status', [AcptCptController::class, 'toggleStatus'])->name('cpt.toggle-status');
        Route::post('cpt/{id}/duplicate', [AcptCptController::class, 'duplicate'])->name('cpt.duplicate');
        Route::resource('cpt', AcptCptController::class);
        
        Route::post('taxonomies/bulk', [AcptTaxonomyController::class, 'bulk'])->name('taxonomies.bulk');
        Route::resource('taxonomies', AcptTaxonomyController::class)->except(['show']);
        Route::post('tax-terms/ajax', [AcptTermController::class, 'ajax'])->name('terms.ajax');
        Route::get('tax-terms/{taxonomySlug}', [AcptTermController::class, 'index'])->name('terms.index');
        Route::post('tax-terms/{taxonomySlug}/bulk', [AcptTermController::class, 'bulk'])->name('terms.bulk');
        Route::post('tax-terms/{taxonomySlug}', [AcptTermController::class, 'store'])->name('terms.store');
        Route::get('tax-terms/{taxonomySlug}/edit/{id}', [AcptTermController::class, 'edit'])->name('terms.edit');
        Route::put('tax-terms/{taxonomySlug}/{id}', [AcptTermController::class, 'update'])->name('terms.update');
        Route::delete('tax-terms/{taxonomySlug}/{id}', [AcptTermController::class, 'destroy'])->name('terms.destroy');
        Route::delete('fields/delete-field/{field}', [CustomFieldController::class, 'deleteField'])->name('fields.delete-field');
        Route::post('fields/store-field', [CustomFieldController::class, 'storeField'])->name('fields.store-field');
        Route::resource('fields', CustomFieldController::class);
    });
 
    // Dashboard index
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('analytics', [DashboardController::class, 'analytics'])->name('analytics');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index');
 
    // Users
    Route::get('profile', function() {
        return redirect()->route('admin.users.edit', auth()->id());
    })->name('profile');
    Route::post('users/bulk', [UserController::class, 'bulk'])->name('users.bulk');
    Route::resource('users', UserController::class);
    Route::post('users/{user}/toggle-block', [UserController::class, 'toggleBlock'])->name('users.toggle-block');
    Route::get('blacklist', [\FalconCms\Core\Http\Controllers\Admin\BlacklistController::class, 'index'])->name('blacklist.index');
    Route::delete('blacklist/{id}', [\FalconCms\Core\Http\Controllers\Admin\BlacklistController::class, 'destroy'])->name('blacklist.destroy');
    Route::post('blacklist/bulk', [\FalconCms\Core\Http\Controllers\Admin\BlacklistController::class, 'bulk'])->name('blacklist.bulk');
    
    // Dynamic Options Pages
    Route::get('options/{slug}', [\FalconCms\Core\Http\Controllers\Admin\CustomOptionsController::class, 'index'])->name('options.index');
    Route::post('options/{slug}', [\FalconCms\Core\Http\Controllers\Admin\CustomOptionsController::class, 'update'])->name('options.update');

    Route::resource('roles', RoleController::class);
    
    // Languages
    Route::post('languages/settings', [LanguageController::class, 'updateSettings'])->name('languages.settings.update');
    Route::post('languages/{id}/default', [\FalconCms\Core\Http\Controllers\Admin\LanguageController::class, 'setDefault'])->name('languages.set-default');
    Route::resource('languages', \FalconCms\Core\Http\Controllers\Admin\LanguageController::class)->names('languages');
 
    // Settings
    Route::get('settings', [DashboardController::class, 'settings'])->name('settings.index');
    Route::post('settings', [DashboardController::class, 'updateSettings'])->name('settings.update');
    Route::get('settings/seo', [DashboardController::class, 'seoSettings'])->name('settings.seo');
    Route::post('settings/seo', [DashboardController::class, 'updateSeoSettings'])->name('settings.seo.update');
    Route::get('settings/activity-logs', [DashboardController::class, 'activityLogs'])->name('settings.activity-logs');
    Route::post('settings/activity-logs/bulk', [DashboardController::class, 'bulkDeleteLogs'])->name('settings.activity-logs.bulk');
    Route::get('settings/api', [DashboardController::class, 'apiSettings'])->name('settings.api');
    Route::post('settings/api/tokens', [DashboardController::class, 'generateApiToken'])->name('settings.api.tokens.store');
    Route::delete('settings/api/tokens/{id}', [DashboardController::class, 'revokeApiToken'])->name('settings.api.tokens.destroy');
    Route::get('settings/integrations', [DashboardController::class, 'integrationsSettings'])->name('settings.integrations');
    Route::post('settings/integrations', [DashboardController::class, 'updateIntegrationsSettings'])->name('settings.integrations.update');
    Route::get('settings/email-templates', [DashboardController::class, 'emailTemplates'])->name('settings.email-templates');
    Route::post('settings/email-templates', [DashboardController::class, 'updateEmailTemplate'])->name('settings.email-templates.update');
    Route::post('settings/email-templates/test', [DashboardController::class, 'testEmailTemplate'])->name('settings.email-templates.test');
    
    // Backups
    Route::get('tools/backup', [\FalconCms\Core\Http\Controllers\Admin\BackupController::class, 'index'])->name('backup.index');
    Route::post('tools/backup', [\FalconCms\Core\Http\Controllers\Admin\BackupController::class, 'create'])->name('backup.create');
    Route::post('tools/backup/upload', [\FalconCms\Core\Http\Controllers\Admin\BackupController::class, 'upload'])->name('backup.upload');
    Route::post('tools/backup/restore/{filename}', [\FalconCms\Core\Http\Controllers\Admin\BackupController::class, 'restore'])->name('backup.restore');
    Route::get('tools/backup/download/{filename}', [\FalconCms\Core\Http\Controllers\Admin\BackupController::class, 'download'])->name('backup.download');
    Route::delete('tools/backup/{filename}', [\FalconCms\Core\Http\Controllers\Admin\BackupController::class, 'destroy'])->name('backup.destroy');

    // WordPress Importer
    Route::get('tools/wp-import', [\FalconCms\Core\Http\Controllers\Admin\WordPressImportController::class, 'index'])->name('wp-import.index');
    Route::post('tools/wp-import', [\FalconCms\Core\Http\Controllers\Admin\WordPressImportController::class, 'import'])->name('wp-import.import');
    Route::post('tools/wp-import/media', [\FalconCms\Core\Http\Controllers\Admin\WordPressImportController::class, 'importMedia'])->name('wp-import.media');

    // Redirection Manager
    Route::get('seo/redirects', [\FalconCms\Core\Http\Controllers\Admin\RedirectController::class, 'index'])->name('redirects.index');
    Route::post('seo/redirects', [\FalconCms\Core\Http\Controllers\Admin\RedirectController::class, 'store'])->name('redirects.store');
    Route::delete('seo/redirects/{redirect}', [\FalconCms\Core\Http\Controllers\Admin\RedirectController::class, 'destroy'])->name('redirects.destroy');
    Route::post('seo/redirects/bulk', [\FalconCms\Core\Http\Controllers\Admin\RedirectController::class, 'bulk'])->name('redirects.bulk');
    Route::get('seo/related-posts', [DashboardController::class, 'getRelatedPosts'])->name('seo.related-posts');

    Route::get('documentation', [DashboardController::class, 'documentation'])->name('documentation');
    Route::get('update', [DashboardController::class, 'updateCheck'])->name('update');
    Route::post('update/run', [DashboardController::class, 'runUpdate'])->name('update.run');
 
    // Comments Management
    Route::get('comments', [\FalconCms\Core\Http\Controllers\Admin\CommentController::class, 'index'])->name('comments.index');
    Route::post('comments/{comment}/toggle-approve', [\FalconCms\Core\Http\Controllers\Admin\CommentController::class, 'toggleApprove'])->name('comments.toggle-approve');
    Route::delete('comments/{comment}', [\FalconCms\Core\Http\Controllers\Admin\CommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('comments/bulk', [\FalconCms\Core\Http\Controllers\Admin\CommentController::class, 'bulk'])->name('comments.bulk');

    Route::post('logout', [LoginController::class, 'logout'])->name('logout');
    Route::post('login/check', [LoginController::class, 'checkCredentials'])->name('login.check');
    Route::post('admin/login/check', [LoginController::class, 'checkCredentials'])->name('admin.login.check');
    Route::post('email/check', [RegisterController::class, 'checkEmail'])->name('email.check');
    Route::post('admin/email/check', [RegisterController::class, 'checkEmail'])->name('admin.email.check');

    // Widgets
    Route::get('/widgets', [WidgetController::class, 'index'])->name('widgets.index');
    Route::post('/widgets', [WidgetController::class, 'store'])->name('widgets.store');
    Route::put('/widgets/{widget}', [WidgetController::class, 'update'])->name('widgets.update');
    Route::delete('/widgets/{widget}', [WidgetController::class, 'destroy'])->name('widgets.destroy');
    Route::post('/widgets/order', [WidgetController::class, 'updateOrder'])->name('widgets.update-order');

    // Customizer (Appearance > Customizer)
    Route::get('/appearance/customizer', [\FalconCms\Core\Http\Controllers\Admin\CustomizerController::class, 'index'])->name('customizer.index');
    Route::post('/appearance/customizer', [\FalconCms\Core\Http\Controllers\Admin\CustomizerController::class, 'save'])->name('customizer.save');
    Route::post('/appearance/customizer/reset', [\FalconCms\Core\Http\Controllers\Admin\CustomizerController::class, 'resetSection'])->name('customizer.reset');
    Route::get('/appearance/customizer/export', [\FalconCms\Core\Http\Controllers\Admin\CustomizerController::class, 'export'])->name('customizer.export');
    Route::post('/appearance/customizer/import', [\FalconCms\Core\Http\Controllers\Admin\CustomizerController::class, 'import'])->name('customizer.import');
    Route::post('/appearance/customizer/action/{action}', [\FalconCms\Core\Http\Controllers\Admin\CustomizerController::class, 'runAction'])->name('customizer.action');

    // Themes
    Route::get('/themes', [ThemeController::class, 'index'])->name('themes.index');
    Route::post('/themes/upload', [ThemeController::class, 'upload'])->name('themes.upload');
    Route::post('/themes/{slug}/activate', [ThemeController::class, 'activate'])->name('themes.activate');
    Route::delete('/themes/{slug}', [ThemeController::class, 'destroy'])->name('themes.destroy');

    // Falcon Builder Sections
    Route::get('/falcon-builder-sections', [\FalconCms\Core\Http\Controllers\Admin\FalconBuilderController::class, 'index'])->name('falcon-builder.sections');
    Route::get('/falcon-builder-sections/header', [\FalconCms\Core\Http\Controllers\Admin\FalconBuilderController::class, 'editHeader'])->name('falcon-builder.header');
    Route::get('/falcon-builder-sections/footer', [\FalconCms\Core\Http\Controllers\Admin\FalconBuilderController::class, 'editFooter'])->name('falcon-builder.footer');
    Route::post('/falcon-builder-sections/toggle/{id}', [\FalconCms\Core\Http\Controllers\Admin\FalconBuilderController::class, 'toggleStatus'])->name('falcon-builder.toggle');

    // Form Builder
    Route::get('forms', [\FalconCms\Core\Http\Controllers\Admin\FormController::class, 'index'])->name('forms.index');
    Route::get('forms/create', [\FalconCms\Core\Http\Controllers\Admin\FormController::class, 'create'])->name('forms.create');
    Route::post('forms', [\FalconCms\Core\Http\Controllers\Admin\FormController::class, 'store'])->name('forms.store');
    Route::get('forms/{id}/builder', [\FalconCms\Core\Http\Controllers\Admin\FormController::class, 'builder'])->name('forms.builder');
    Route::post('forms/{id}/save', [\FalconCms\Core\Http\Controllers\Admin\FormController::class, 'saveBuilder'])->name('forms.save');
    Route::get('forms/{id}/submissions', [\FalconCms\Core\Http\Controllers\Admin\FormController::class, 'submissions'])->name('forms.submissions');
    Route::get('forms/all-submissions', [\FalconCms\Core\Http\Controllers\Admin\FormController::class, 'allSubmissions'])->name('forms.all-submissions');
    Route::delete('forms/submissions/{submission}', [\FalconCms\Core\Http\Controllers\Admin\FormController::class, 'destroySubmission'])->name('forms.submissions.destroy');
    Route::delete('forms/{form}', [\FalconCms\Core\Http\Controllers\Admin\FormController::class, 'destroy'])->name('forms.destroy');

    // Shop Management
    Route::prefix('shop')->name('shop.')->group(function() {
        Route::get('overview', [ShopController::class, 'overview'])->name('overview');
        Route::get('orders', [ShopController::class, 'orders'])->name('orders.index');
        Route::post('orders/bulk', [ShopController::class, 'ordersBulk'])->name('orders.bulk');
        Route::get('orders/{id}', [ShopController::class, 'orderShow'])->name('orders.show');
        Route::get('orders/{id}/invoice', [ShopController::class, 'orderInvoice'])->name('orders.invoice');
        Route::post('orders/{id}/status', [ShopController::class, 'orderUpdateStatus'])->name('orders.status');
        Route::post('orders/{id}/refund', [ShopController::class, 'orderRefund'])->name('orders.refund');
        Route::get('settings', [ShopController::class, 'settings'])->name('settings');
        Route::post('settings', [ShopController::class, 'saveSettings'])->name('settings.save');

        // Sales Reports
        Route::get('reports', [\FalconCms\Core\Http\Controllers\Admin\ShopReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [\FalconCms\Core\Http\Controllers\Admin\ShopReportController::class, 'export'])->name('reports.export');

        // Product download files (admin)
        Route::post('products/{productDataId}/downloads', [\FalconCms\Core\Http\Controllers\Admin\ProductDownloadController::class, 'store'])->name('products.downloads.store');
        Route::delete('products/downloads/{download}', [\FalconCms\Core\Http\Controllers\Admin\ProductDownloadController::class, 'destroy'])->name('products.downloads.destroy');

        // Reviews
        Route::get('reviews', [\FalconCms\Core\Http\Controllers\Admin\ReviewController::class, 'index'])->name('reviews.index');
        Route::post('reviews/{review}/toggle-approve', [\FalconCms\Core\Http\Controllers\Admin\ReviewController::class, 'toggleApprove'])->name('reviews.toggle-approve');
        Route::delete('reviews/{review}', [\FalconCms\Core\Http\Controllers\Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');
        Route::post('reviews/bulk', [\FalconCms\Core\Http\Controllers\Admin\ReviewController::class, 'bulk'])->name('reviews.bulk');
    });

});
 
// 3. Frontend Routes (Catch-all for posts/pages) - Outside Admin Group
Route::middleware(['web', \FalconCms\Core\Http\Middleware\SecurityHeadersMiddleware::class, \FalconCms\Core\Http\Middleware\MaintenanceModeMiddleware::class, \FalconCms\Core\Http\Middleware\PageCacheMiddleware::class])->group(function() {
    Route::get('/', [FrontendController::class, 'index'])->name('frontend.index');
    Route::get('lang/{locale}', [FrontendController::class, 'setLocale'])->name('frontend.set-locale');
    
    // Localization Logic
    $isMultiLang = get_cms_option('multi_language_enabled', 0);
    $supportedLocales = [];
    
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('cms_languages')) {
            $supportedLocales = \FalconCms\Core\Models\Language::where('status', true)->pluck('code')->toArray();
            // If we have more than 1 language, we consider it multi-lang for routing purposes
            if (count($supportedLocales) > 1) {
                $isMultiLang = 1;
            }
        }
    } catch (\Exception $e) {
        $supportedLocales = [];
    }
    
    $localePattern = implode('|', $supportedLocales);
    if ($isMultiLang && !empty($localePattern)) {
        Route::get('/{locale}', [FrontendController::class, 'index'])
            ->where('locale', $localePattern);
            
        Route::get('/{locale}/category/{slug}', [FrontendController::class, 'archive'])
            ->where('locale', $localePattern)->where('slug', '.*')->name('frontend.category.locale');

        Route::get('/{locale}/tag/{slug}', [FrontendController::class, 'archive'])
            ->where('locale', $localePattern)->where('slug', '.*')->name('frontend.tag.locale');

        Route::get('/{locale}/product-category/{slug}', [FrontendController::class, 'archive'])
            ->where('locale', $localePattern)->where('slug', '.*')->name('frontend.product_category.locale');
        Route::get('/{locale}/product-tag/{slug}', [FrontendController::class, 'archive'])
            ->where('locale', $localePattern)->where('slug', '.*')->name('frontend.product_tag.locale');

        Route::get('/{locale}/search', [FrontendController::class, 'search'])
            ->where('locale', $localePattern);
    }

    Route::get('/category/{slug}', [FrontendController::class, 'archive'])->name('frontend.category')->where('slug', '.*');
    Route::get('/tag/{slug}', [FrontendController::class, 'archive'])->name('frontend.tag')->where('slug', '.*');
    Route::get('/product-category/{slug}', [FrontendController::class, 'archive'])->name('frontend.product_category')->where('slug', '.*');
    Route::get('/product-tag/{slug}', [FrontendController::class, 'archive'])->name('frontend.product_tag')->where('slug', '.*');
    Route::get('/author/{id}', [FrontendController::class, 'authorArchive'])->name('frontend.author')->where('id', '[0-9]+');
    Route::get('/search', [FrontendController::class, 'search'])->name('frontend.search');
    Route::get('/search/live', [FrontendController::class, 'liveSearch'])->name('frontend.search.live');
    Route::post('/comment', [FrontendController::class, 'storeComment'])->name('frontend.comment.store')->middleware('throttle:10,1');
    Route::post('/form-submit', [FrontendController::class, 'submitForm'])->name('frontend.form.submit')->middleware('throttle:5,1');

    // Shop Frontend
    Route::prefix('cart')->name('shop.')->group(function() {
        Route::get('/',         [ShopFrontendController::class, 'cart'])->name('cart');
        Route::get('/fragment', [ShopFrontendController::class, 'miniCart'])->name('cart.fragment');
        Route::post('/add',             [ShopFrontendController::class, 'addToCart'])->name('cart.add')->middleware('throttle:30,1');
        Route::post('/update',          [ShopFrontendController::class, 'updateCart'])->name('cart.update')->middleware('throttle:30,1');
        Route::post('/remove/{key}',    [ShopFrontendController::class, 'removeFromCart'])->name('cart.remove')->middleware('throttle:30,1');
        Route::post('/apply-coupon',    [ShopFrontendController::class, 'applyCoupon'])->name('cart.coupon')->middleware('throttle:10,1');
        Route::get('/remove-coupon',    [ShopFrontendController::class, 'removeCoupon'])->name('cart.coupon.remove');
        Route::post('/update-shipping', [ShopFrontendController::class, 'updateShipping'])->name('cart.shipping.update')->middleware('throttle:20,1');
        Route::post('/review',          [ShopFrontendController::class, 'storeReview'])->name('review.store')->middleware('throttle:5,1');
    });
    Route::get('/checkout', [ShopFrontendController::class, 'checkout'])->name('shop.checkout');
    Route::post('/checkout', [ShopFrontendController::class, 'placeOrder'])->name('shop.place-order');
    Route::get('/order-confirmation/{id}', [ShopFrontendController::class, 'confirmation'])->name('shop.confirmation');

    // Order tracking
    Route::match(['get', 'post'], '/track-order', [ShopFrontendController::class, 'trackOrder'])->name('shop.track');

    // Account page login / logout / profile / password
    Route::post('/account-login', [ShopFrontendController::class, 'accountLogin'])->name('shop.account.login');
    Route::post('/account-logout', [ShopFrontendController::class, 'accountLogout'])->name('shop.account.logout');
    Route::post('/account-profile-update', [ShopFrontendController::class, 'updateProfile'])->name('shop.account.profile.update');
    Route::post('/account-password-update', [ShopFrontendController::class, 'updatePassword'])->name('shop.account.password.update');

    // Digital downloads (token-based, no auth required)
    Route::get('/download/{token}', [ShopFrontendController::class, 'downloadFile'])->name('shop.download')->middleware('throttle:30,1');

    // Magic login (passwordless)
    Route::post('/magic-login', [ShopFrontendController::class, 'requestMagicLink'])->name('shop.magic.request')->middleware('throttle:5,1');
    Route::get('/magic-login/{token}', [ShopFrontendController::class, 'verifyMagicLink'])->name('shop.magic.verify');

    // Wishlist
    Route::get('/wishlist', [\FalconCms\Core\Http\Controllers\WishlistController::class, 'index'])->name('shop.wishlist');
    Route::post('/wishlist/toggle', [\FalconCms\Core\Http\Controllers\WishlistController::class, 'toggle'])->name('shop.wishlist.toggle');
    Route::post('/wishlist/remove', [\FalconCms\Core\Http\Controllers\WishlistController::class, 'remove'])->name('shop.wishlist.remove');

    // Online payment gateway return / cancel — gateways (e.g. SSLCommerz) POST here without a CSRF token.
    Route::match(['get', 'post'], '/payment/return/{id}', [ShopFrontendController::class, 'paymentReturn'])
        ->name('shop.payment.return')
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    Route::match(['get', 'post'], '/payment/cancel/{id}', [ShopFrontendController::class, 'paymentCancel'])
        ->name('shop.payment.cancel')
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

    Route::get('/robots.txt', [FrontendController::class, 'robots'])->name('frontend.robots');
    Route::get('/sitemap.xml', [\FalconCms\Core\Http\Controllers\SitemapController::class, 'index'])->name('frontend.sitemap');
    Route::get('/{typeOrSlug}/{slug?}', [FrontendController::class, 'single'])->name('frontend.show')->where('slug', '.*');
});

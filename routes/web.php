<?php
 
use Illuminate\Support\Facades\Route;
use Acme\CmsDashboard\Http\Controllers\Admin\PostController;
use Acme\CmsDashboard\Http\Controllers\Admin\PostTypeController;
use Acme\CmsDashboard\Http\Controllers\Admin\MediaController;
use Acme\CmsDashboard\Http\Controllers\Admin\DashboardController;
use Acme\CmsDashboard\Http\Controllers\Admin\CustomFieldController;
use Acme\CmsDashboard\Http\Controllers\Admin\UserController;
use Acme\CmsDashboard\Http\Controllers\Admin\LoginController;
use Acme\CmsDashboard\Http\Controllers\Admin\RegisterController;
use Acme\CmsDashboard\Http\Controllers\Admin\RoleController;
use Acme\CmsDashboard\Http\Controllers\Admin\AcptCptController;
use Acme\CmsDashboard\Http\Controllers\Admin\AcptTaxonomyController;
use Acme\CmsDashboard\Http\Controllers\Admin\AcptTermController;
use Acme\CmsDashboard\Http\Controllers\Admin\WidgetController;
use Acme\CmsDashboard\Http\Controllers\Admin\LanguageController;
use Acme\CmsDashboard\Http\Controllers\Admin\ThemeController;
use Acme\CmsDashboard\Http\Controllers\Admin\ShopController;
use Acme\CmsDashboard\Http\Controllers\ShopFrontendController;
use Acme\CmsDashboard\Http\Controllers\FrontendController;

// 1. Dynamic Login & Registration URLs (Highest Priority - Outside any group)
$login_slug = get_cms_option('login_url', 'super-lazy-admin');
$register_slug = get_cms_option('register_url', 'super-lazy-register');

Route::middleware(['web'])->group(function() use ($login_slug, $register_slug) {
    Route::get($login_slug, [LoginController::class, 'showLoginForm'])->name('admin.login');
    Route::post($login_slug, [LoginController::class, 'login']);
    
    Route::get($register_slug, [RegisterController::class, 'showRegistrationForm'])->name('admin.register');
    Route::post($register_slug, [RegisterController::class, 'register']);

    // Password Recovery
    Route::get('forgot-password', [LoginController::class, 'showForgotPasswordForm'])->name('admin.password.request');
    Route::post('forgot-password', [LoginController::class, 'sendResetLinkEmail'])->name('admin.password.email');
    Route::get('reset-password/{token}', [LoginController::class, 'showResetForm'])->name('admin.password.reset');
    Route::post('reset-password', [LoginController::class, 'resetPassword'])->name('admin.password.update');

    // Admin magic login (passwordless)
    Route::post('admin-magic-login', [LoginController::class, 'requestAdminMagicLink'])->name('admin.magic.request');
    Route::get('admin-magic-login/verify/{token}', [LoginController::class, 'verifyAdminMagicLink'])->name('admin.magic.verify');

    // Frontend magic email check (AJAX, rate-limited)
    Route::post('magic-email-check', [ShopFrontendController::class, 'checkMagicEmail'])->name('shop.magic.email.check')->middleware('throttle:30,1');

    // Redirect standard admin/login and admin/register to custom slugs
    Route::get('admin/login', function() use ($login_slug) { return redirect($login_slug); });
    Route::get('admin/register', function() use ($register_slug) { return redirect($register_slug); });
});

// 2. Authenticated Admin Routes
Route::prefix('admin')->name('admin.')->middleware(['web', \Acme\CmsDashboard\Http\Middleware\AdminMiddleware::class])->group(function () {
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
    Route::post('categories/bulk', [\Acme\CmsDashboard\Http\Controllers\Admin\CategoryController::class, 'bulk'])->name('categories.bulk');
    Route::post('tags/bulk', [\Acme\CmsDashboard\Http\Controllers\Admin\TagController::class, 'bulk'])->name('tags.bulk');
    Route::post('posts/{post}/restore', [PostController::class, 'restore'])->name('posts.restore')->withTrashed();
    Route::delete('posts/{post}/force-delete', [PostController::class, 'forceDelete'])->name('posts.force-delete')->withTrashed();
    // Classic editor revisions + autosave (must precede the resource route)
    Route::post('posts/{id}/autosave', [PostController::class, 'autosaveClassic'])->name('posts.autosave');
    Route::get('posts/{id}/revisions', [PostController::class, 'revisionsPage'])->name('posts.revisions');
    Route::post('posts/{id}/revisions/{revision}/restore', [PostController::class, 'restoreRevisionClassic'])->name('posts.revisions.restore');
    Route::delete('posts/{id}/revisions/{revision}', [PostController::class, 'deleteRevision'])->name('posts.revisions.delete');
    Route::delete('posts/{id}/revisions', [PostController::class, 'clearRevisions'])->name('posts.revisions.clear');
    Route::resource('posts', PostController::class);
    Route::get('lazy-builder-library', [\Acme\CmsDashboard\Http\Controllers\Admin\BuilderLibraryController::class, 'page'])->name('lazy-builder.library');
    Route::post('lazy-builder-library/post-cards', [\Acme\CmsDashboard\Http\Controllers\Admin\BuilderLibraryController::class, 'savePostCard'])->name('lazy-builder.post-cards.save');
    Route::delete('lazy-builder-library/post-cards/{id}', [\Acme\CmsDashboard\Http\Controllers\Admin\BuilderLibraryController::class, 'deletePostCard'])->name('lazy-builder.post-cards.delete');
    Route::patch('lazy-builder-library/post-cards/{id}', [\Acme\CmsDashboard\Http\Controllers\Admin\BuilderLibraryController::class, 'updatePostCard'])->name('lazy-builder.post-cards.update');
    Route::get('lazy-builder-library/post-cards/{id}/builder', [\Acme\CmsDashboard\Http\Controllers\Admin\BuilderLibraryController::class, 'editPostCardBuilder'])->name('lazy-builder.post-cards.builder');
    Route::post('lazy-builder-library/post-cards/{id}/builder', [\Acme\CmsDashboard\Http\Controllers\Admin\BuilderLibraryController::class, 'savePostCardLayout'])->name('lazy-builder.post-cards.save-layout');
    Route::get('lazy-builder/library', [\Acme\CmsDashboard\Http\Controllers\Admin\BuilderLibraryController::class, 'index'])->name('lazy-builder.library.index');
    Route::post('lazy-builder/library/save', [\Acme\CmsDashboard\Http\Controllers\Admin\BuilderLibraryController::class, 'save'])->name('lazy-builder.library.save');
    Route::delete('lazy-builder/library/{type}/{id}', [\Acme\CmsDashboard\Http\Controllers\Admin\BuilderLibraryController::class, 'delete'])->name('lazy-builder.library.delete');
    Route::get('lazy-builder/global-sections', [\Acme\CmsDashboard\Http\Controllers\Admin\BuilderLibraryController::class, 'listGlobalSections'])->name('lazy-builder.global-sections.list');
    Route::post('lazy-builder/global-sections', [\Acme\CmsDashboard\Http\Controllers\Admin\BuilderLibraryController::class, 'saveGlobalSection'])->name('lazy-builder.global-sections.save');
    Route::patch('lazy-builder/global-sections/{id}', [\Acme\CmsDashboard\Http\Controllers\Admin\BuilderLibraryController::class, 'updateGlobalSection'])->name('lazy-builder.global-sections.update');
    Route::delete('lazy-builder/global-sections/{id}', [\Acme\CmsDashboard\Http\Controllers\Admin\BuilderLibraryController::class, 'deleteGlobalSection'])->name('lazy-builder.global-sections.delete');
    Route::post('lazy-builder/card-preview', function(\Illuminate\Http\Request $r) {
        $s = $r->input('settings', []);
        try {
            $html = view('cms-dashboard::frontend.builder.elements.card', [
                'el' => ['settings' => $s],
                'previewDevice' => $r->input('device'),
            ])->render();
            return response()->json(['success' => true, 'html' => $html]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'html' => '']);
        }
    })->name('lazy-builder.card-preview');
    Route::get('lazy-builder/{id}', [PostController::class, 'builder'])->name('lazy-builder');
    Route::post('lazy-builder/{id}/save', [PostController::class, 'saveBuilder'])->name('lazy-builder.save');
    // Revisions + Autosave
    Route::post('lazy-builder/{id}/autosave', [PostController::class, 'autosaveBuilder'])->name('lazy-builder.autosave');
    Route::get('lazy-builder/{id}/revisions', [PostController::class, 'revisions'])->name('lazy-builder.revisions');
    Route::post('lazy-builder/{id}/revisions/{revision}/restore', [PostController::class, 'restoreRevision'])->name('lazy-builder.revisions.restore');
    Route::delete('lazy-builder/{id}/revisions/{revision}', [PostController::class, 'deleteRevisionBuilder'])->name('lazy-builder.revisions.delete');
    Route::post('posts/{id}/variations/ajax', [PostController::class, 'ajaxSaveVariations'])->name('posts.variations.ajax-save');
    Route::get('lazy-builder/{id}/preview', [PostController::class, 'previewBuilder'])->name('lazy-builder.preview');
 
    Route::post('pages/bulk', [\Acme\CmsDashboard\Http\Controllers\Admin\PageController::class, 'bulk'])->name('pages.bulk');
    Route::post('pages/{page}/restore', [\Acme\CmsDashboard\Http\Controllers\Admin\PageController::class, 'restore'])->name('pages.restore')->withTrashed();
    Route::delete('pages/{page}/force-delete', [\Acme\CmsDashboard\Http\Controllers\Admin\PageController::class, 'forceDelete'])->name('pages.force-delete')->withTrashed();
    // Page revisions + autosave (must precede the resource route)
    Route::post('pages/{id}/autosave', [\Acme\CmsDashboard\Http\Controllers\Admin\PageController::class, 'autosaveClassic'])->name('pages.autosave');
    Route::get('pages/{id}/revisions', [\Acme\CmsDashboard\Http\Controllers\Admin\PageController::class, 'revisionsPage'])->name('pages.revisions');
    Route::post('pages/{id}/revisions/{revision}/restore', [\Acme\CmsDashboard\Http\Controllers\Admin\PageController::class, 'restoreRevisionClassic'])->name('pages.revisions.restore');
    Route::delete('pages/{id}/revisions/{revision}', [\Acme\CmsDashboard\Http\Controllers\Admin\PageController::class, 'deleteRevision'])->name('pages.revisions.delete');
    Route::delete('pages/{id}/revisions', [\Acme\CmsDashboard\Http\Controllers\Admin\PageController::class, 'clearRevisions'])->name('pages.revisions.clear');
    Route::resource('pages', \Acme\CmsDashboard\Http\Controllers\Admin\PageController::class);

    // Categories
    Route::get('categories', [\Acme\CmsDashboard\Http\Controllers\Admin\CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [\Acme\CmsDashboard\Http\Controllers\Admin\CategoryController::class, 'store'])->name('categories.store');
    Route::get('categories/edit/{category}', [\Acme\CmsDashboard\Http\Controllers\Admin\CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('categories/{category}', [\Acme\CmsDashboard\Http\Controllers\Admin\CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [\Acme\CmsDashboard\Http\Controllers\Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

    // Tags
    Route::get('tags', [\Acme\CmsDashboard\Http\Controllers\Admin\TagController::class, 'index'])->name('tags.index');
    Route::post('tags', [\Acme\CmsDashboard\Http\Controllers\Admin\TagController::class, 'store'])->name('tags.store');
    Route::get('tags/edit/{tag}', [\Acme\CmsDashboard\Http\Controllers\Admin\TagController::class, 'edit'])->name('tags.edit');
    Route::put('tags/{tag}', [\Acme\CmsDashboard\Http\Controllers\Admin\TagController::class, 'update'])->name('tags.update');
    Route::delete('tags/{tag}', [\Acme\CmsDashboard\Http\Controllers\Admin\TagController::class, 'destroy'])->name('tags.destroy');

    Route::resource('post-types', PostTypeController::class)->only(['index', 'store', 'destroy']);
    
    Route::post('categories/ajax', function(\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'name' => 'required|string',
            'parent_id' => 'nullable|exists:categories,id',
            'lang_code' => 'nullable|string'
        ]);
        
        $lang = $validated['lang_code'] ?? app()->getLocale();
        $category = \Acme\CmsDashboard\Models\Category::create([
            'name' => $validated['name'],
            'parent_id' => !empty($validated['parent_id']) ? $validated['parent_id'] : null,
            'lang_code' => $lang,
            'slug' => \Acme\CmsDashboard\Models\Category::generateUniqueSlug($validated['name'], 0, $lang)
        ]);
        
        return response()->json($category);
    })->name('categories.ajax');

    // Product Categories (dedicated, first-class — mirrors Categories)
    Route::get('product-categories', [\Acme\CmsDashboard\Http\Controllers\Admin\ProductCategoryController::class, 'index'])->name('product-categories.index');
    Route::post('product-categories', [\Acme\CmsDashboard\Http\Controllers\Admin\ProductCategoryController::class, 'store'])->name('product-categories.store');
    Route::post('product-categories/bulk', [\Acme\CmsDashboard\Http\Controllers\Admin\ProductCategoryController::class, 'bulk'])->name('product-categories.bulk');
    Route::post('product-categories/ajax', [\Acme\CmsDashboard\Http\Controllers\Admin\ProductCategoryController::class, 'ajax'])->name('product-categories.ajax');
    Route::get('product-categories/edit/{product_category}', [\Acme\CmsDashboard\Http\Controllers\Admin\ProductCategoryController::class, 'edit'])->name('product-categories.edit');
    Route::put('product-categories/{product_category}', [\Acme\CmsDashboard\Http\Controllers\Admin\ProductCategoryController::class, 'update'])->name('product-categories.update');
    Route::delete('product-categories/{product_category}', [\Acme\CmsDashboard\Http\Controllers\Admin\ProductCategoryController::class, 'destroy'])->name('product-categories.destroy');

    // Product Tags (dedicated, first-class — mirrors Tags)
    Route::get('product-tags', [\Acme\CmsDashboard\Http\Controllers\Admin\ProductTagController::class, 'index'])->name('product-tags.index');
    Route::post('product-tags', [\Acme\CmsDashboard\Http\Controllers\Admin\ProductTagController::class, 'store'])->name('product-tags.store');
    Route::post('product-tags/bulk', [\Acme\CmsDashboard\Http\Controllers\Admin\ProductTagController::class, 'bulk'])->name('product-tags.bulk');
    Route::post('product-tags/ajax', [\Acme\CmsDashboard\Http\Controllers\Admin\ProductTagController::class, 'ajax'])->name('product-tags.ajax');
    Route::get('product-tags/edit/{product_tag}', [\Acme\CmsDashboard\Http\Controllers\Admin\ProductTagController::class, 'edit'])->name('product-tags.edit');
    Route::put('product-tags/{product_tag}', [\Acme\CmsDashboard\Http\Controllers\Admin\ProductTagController::class, 'update'])->name('product-tags.update');
    Route::delete('product-tags/{product_tag}', [\Acme\CmsDashboard\Http\Controllers\Admin\ProductTagController::class, 'destroy'])->name('product-tags.destroy');

    // Navigation Menus
    Route::resource('menus', \Acme\CmsDashboard\Http\Controllers\Admin\MenuManagementController::class);
    
    // Dynamic Taxonomy Terms
    Route::get('taxonomies/{slug}/terms', [\Acme\CmsDashboard\Http\Controllers\Admin\TaxonomyTermController::class, 'index'])->name('old.terms.index');
    Route::post('taxonomies/{slug}/terms', [\Acme\CmsDashboard\Http\Controllers\Admin\TaxonomyTermController::class, 'store'])->name('old.terms.store');
    Route::delete('taxonomies/{slug}/terms/{id}', [\Acme\CmsDashboard\Http\Controllers\Admin\TaxonomyTermController::class, 'destroy'])->name('old.terms.destroy');
    Route::post('taxonomies/{slug}/terms/bulk', [\Acme\CmsDashboard\Http\Controllers\Admin\TaxonomyTermController::class, 'bulk'])->name('old.terms.bulk');
 
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
    Route::get('blacklist', [\Acme\CmsDashboard\Http\Controllers\Admin\BlacklistController::class, 'index'])->name('blacklist.index');
    Route::delete('blacklist/{id}', [\Acme\CmsDashboard\Http\Controllers\Admin\BlacklistController::class, 'destroy'])->name('blacklist.destroy');
    Route::post('blacklist/bulk', [\Acme\CmsDashboard\Http\Controllers\Admin\BlacklistController::class, 'bulk'])->name('blacklist.bulk');
    
    // Dynamic Options Pages
    Route::get('options/{slug}', [\Acme\CmsDashboard\Http\Controllers\Admin\CustomOptionsController::class, 'index'])->name('options.index');
    Route::post('options/{slug}', [\Acme\CmsDashboard\Http\Controllers\Admin\CustomOptionsController::class, 'update'])->name('options.update');

    Route::resource('roles', RoleController::class);
    
    // Languages
    Route::post('languages/settings', [LanguageController::class, 'updateSettings'])->name('languages.settings.update');
    Route::post('languages/{id}/default', [\Acme\CmsDashboard\Http\Controllers\Admin\LanguageController::class, 'setDefault'])->name('languages.set-default');
    Route::resource('languages', \Acme\CmsDashboard\Http\Controllers\Admin\LanguageController::class)->names('languages');
 
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
    Route::get('tools/backup', [\Acme\CmsDashboard\Http\Controllers\Admin\BackupController::class, 'index'])->name('backup.index');
    Route::post('tools/backup', [\Acme\CmsDashboard\Http\Controllers\Admin\BackupController::class, 'create'])->name('backup.create');
    Route::post('tools/backup/upload', [\Acme\CmsDashboard\Http\Controllers\Admin\BackupController::class, 'upload'])->name('backup.upload');
    Route::post('tools/backup/restore/{filename}', [\Acme\CmsDashboard\Http\Controllers\Admin\BackupController::class, 'restore'])->name('backup.restore');
    Route::get('tools/backup/download/{filename}', [\Acme\CmsDashboard\Http\Controllers\Admin\BackupController::class, 'download'])->name('backup.download');
    Route::delete('tools/backup/{filename}', [\Acme\CmsDashboard\Http\Controllers\Admin\BackupController::class, 'destroy'])->name('backup.destroy');

    // WordPress Importer
    Route::get('tools/wp-import', [\Acme\CmsDashboard\Http\Controllers\Admin\WordPressImportController::class, 'index'])->name('wp-import.index');
    Route::post('tools/wp-import', [\Acme\CmsDashboard\Http\Controllers\Admin\WordPressImportController::class, 'import'])->name('wp-import.import');
    Route::post('tools/wp-import/media', [\Acme\CmsDashboard\Http\Controllers\Admin\WordPressImportController::class, 'importMedia'])->name('wp-import.media');

    // Redirection Manager
    Route::get('seo/redirects', [\Acme\CmsDashboard\Http\Controllers\Admin\RedirectController::class, 'index'])->name('redirects.index');
    Route::post('seo/redirects', [\Acme\CmsDashboard\Http\Controllers\Admin\RedirectController::class, 'store'])->name('redirects.store');
    Route::delete('seo/redirects/{redirect}', [\Acme\CmsDashboard\Http\Controllers\Admin\RedirectController::class, 'destroy'])->name('redirects.destroy');
    Route::post('seo/redirects/bulk', [\Acme\CmsDashboard\Http\Controllers\Admin\RedirectController::class, 'bulk'])->name('redirects.bulk');
    Route::get('seo/related-posts', [DashboardController::class, 'getRelatedPosts'])->name('seo.related-posts');

    Route::get('documentation', [DashboardController::class, 'documentation'])->name('documentation');
    Route::get('update', [DashboardController::class, 'updateCheck'])->name('update');
    Route::post('update/run', [DashboardController::class, 'runUpdate'])->name('update.run');
 
    // Comments Management
    Route::get('comments', [\Acme\CmsDashboard\Http\Controllers\Admin\CommentController::class, 'index'])->name('comments.index');
    Route::post('comments/{comment}/toggle-approve', [\Acme\CmsDashboard\Http\Controllers\Admin\CommentController::class, 'toggleApprove'])->name('comments.toggle-approve');
    Route::delete('comments/{comment}', [\Acme\CmsDashboard\Http\Controllers\Admin\CommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('comments/bulk', [\Acme\CmsDashboard\Http\Controllers\Admin\CommentController::class, 'bulk'])->name('comments.bulk');

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
    Route::get('/appearance/customizer', [\Acme\CmsDashboard\Http\Controllers\Admin\CustomizerController::class, 'index'])->name('customizer.index');
    Route::post('/appearance/customizer', [\Acme\CmsDashboard\Http\Controllers\Admin\CustomizerController::class, 'save'])->name('customizer.save');
    Route::post('/appearance/customizer/reset', [\Acme\CmsDashboard\Http\Controllers\Admin\CustomizerController::class, 'resetSection'])->name('customizer.reset');
    Route::get('/appearance/customizer/export', [\Acme\CmsDashboard\Http\Controllers\Admin\CustomizerController::class, 'export'])->name('customizer.export');
    Route::post('/appearance/customizer/import', [\Acme\CmsDashboard\Http\Controllers\Admin\CustomizerController::class, 'import'])->name('customizer.import');
    Route::post('/appearance/customizer/action/{action}', [\Acme\CmsDashboard\Http\Controllers\Admin\CustomizerController::class, 'runAction'])->name('customizer.action');

    // Themes
    Route::get('/themes', [ThemeController::class, 'index'])->name('themes.index');
    Route::post('/themes/upload', [ThemeController::class, 'upload'])->name('themes.upload');
    Route::post('/themes/{slug}/activate', [ThemeController::class, 'activate'])->name('themes.activate');
    Route::delete('/themes/{slug}', [ThemeController::class, 'destroy'])->name('themes.destroy');

    // Lazy Builder Sections
    Route::get('/lazy-builder-sections', [\Acme\CmsDashboard\Http\Controllers\Admin\LazyBuilderController::class, 'index'])->name('lazy-builder.sections');
    Route::get('/lazy-builder-sections/header', [\Acme\CmsDashboard\Http\Controllers\Admin\LazyBuilderController::class, 'editHeader'])->name('lazy-builder.header');
    Route::get('/lazy-builder-sections/footer', [\Acme\CmsDashboard\Http\Controllers\Admin\LazyBuilderController::class, 'editFooter'])->name('lazy-builder.footer');
    Route::post('/lazy-builder-sections/toggle/{id}', [\Acme\CmsDashboard\Http\Controllers\Admin\LazyBuilderController::class, 'toggleStatus'])->name('lazy-builder.toggle');

    // Form Builder
    Route::get('forms', [\Acme\CmsDashboard\Http\Controllers\Admin\FormController::class, 'index'])->name('forms.index');
    Route::get('forms/create', [\Acme\CmsDashboard\Http\Controllers\Admin\FormController::class, 'create'])->name('forms.create');
    Route::post('forms', [\Acme\CmsDashboard\Http\Controllers\Admin\FormController::class, 'store'])->name('forms.store');
    Route::get('forms/{id}/builder', [\Acme\CmsDashboard\Http\Controllers\Admin\FormController::class, 'builder'])->name('forms.builder');
    Route::post('forms/{id}/save', [\Acme\CmsDashboard\Http\Controllers\Admin\FormController::class, 'saveBuilder'])->name('forms.save');
    Route::get('forms/{id}/submissions', [\Acme\CmsDashboard\Http\Controllers\Admin\FormController::class, 'submissions'])->name('forms.submissions');
    Route::get('forms/all-submissions', [\Acme\CmsDashboard\Http\Controllers\Admin\FormController::class, 'allSubmissions'])->name('forms.all-submissions');
    Route::delete('forms/submissions/{submission}', [\Acme\CmsDashboard\Http\Controllers\Admin\FormController::class, 'destroySubmission'])->name('forms.submissions.destroy');
    Route::delete('forms/{form}', [\Acme\CmsDashboard\Http\Controllers\Admin\FormController::class, 'destroy'])->name('forms.destroy');

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

        // Reviews
        Route::get('reviews', [\Acme\CmsDashboard\Http\Controllers\Admin\ReviewController::class, 'index'])->name('reviews.index');
        Route::post('reviews/{review}/toggle-approve', [\Acme\CmsDashboard\Http\Controllers\Admin\ReviewController::class, 'toggleApprove'])->name('reviews.toggle-approve');
        Route::delete('reviews/{review}', [\Acme\CmsDashboard\Http\Controllers\Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');
        Route::post('reviews/bulk', [\Acme\CmsDashboard\Http\Controllers\Admin\ReviewController::class, 'bulk'])->name('reviews.bulk');
    });

});
 
// 3. Frontend Routes (Catch-all for posts/pages) - Outside Admin Group
Route::middleware(['web', \Acme\CmsDashboard\Http\Middleware\PageCacheMiddleware::class])->group(function() {
    Route::get('/', [FrontendController::class, 'index'])->name('frontend.index');
    Route::get('lang/{locale}', [FrontendController::class, 'setLocale'])->name('frontend.set-locale');
    
    // Localization Logic
    $isMultiLang = get_cms_option('multi_language_enabled', 0);
    $supportedLocales = [];
    
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('cms_languages')) {
            $supportedLocales = \Acme\CmsDashboard\Models\Language::where('status', true)->pluck('code')->toArray();
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
    Route::post('/comment', [FrontendController::class, 'storeComment'])->name('frontend.comment.store');
    Route::post('/form-submit', [FrontendController::class, 'submitForm'])->name('frontend.form.submit');

    // Shop Frontend
    Route::prefix('cart')->name('shop.')->group(function() {
        Route::get('/', [ShopFrontendController::class, 'cart'])->name('cart');
        Route::get('/fragment', [ShopFrontendController::class, 'miniCart'])->name('cart.fragment');
        Route::post('/add', [ShopFrontendController::class, 'addToCart'])->name('cart.add');
        Route::post('/update', [ShopFrontendController::class, 'updateCart'])->name('cart.update');
        Route::get('/remove/{key}', [ShopFrontendController::class, 'removeFromCart'])->name('cart.remove');
        Route::post('/apply-coupon', [ShopFrontendController::class, 'applyCoupon'])->name('cart.coupon');
        Route::get('/remove-coupon', [ShopFrontendController::class, 'removeCoupon'])->name('cart.coupon.remove');
        Route::post('/update-shipping', [ShopFrontendController::class, 'updateShipping'])->name('cart.shipping.update');
        Route::post('/review', [ShopFrontendController::class, 'storeReview'])->name('review.store');
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

    // Magic login (passwordless)
    Route::post('/magic-login', [ShopFrontendController::class, 'requestMagicLink'])->name('shop.magic.request');
    Route::get('/magic-login/{token}', [ShopFrontendController::class, 'verifyMagicLink'])->name('shop.magic.verify');

    // Wishlist
    Route::get('/wishlist', [\Acme\CmsDashboard\Http\Controllers\WishlistController::class, 'index'])->name('shop.wishlist');
    Route::post('/wishlist/toggle', [\Acme\CmsDashboard\Http\Controllers\WishlistController::class, 'toggle'])->name('shop.wishlist.toggle');
    Route::post('/wishlist/remove', [\Acme\CmsDashboard\Http\Controllers\WishlistController::class, 'remove'])->name('shop.wishlist.remove');

    // Online payment gateway return / cancel — gateways (e.g. SSLCommerz) POST here without a CSRF token.
    Route::match(['get', 'post'], '/payment/return/{id}', [ShopFrontendController::class, 'paymentReturn'])
        ->name('shop.payment.return')
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    Route::match(['get', 'post'], '/payment/cancel/{id}', [ShopFrontendController::class, 'paymentCancel'])
        ->name('shop.payment.cancel')
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

    Route::get('/robots.txt', [FrontendController::class, 'robots'])->name('frontend.robots');
    Route::get('/sitemap.xml', [\Acme\CmsDashboard\Http\Controllers\SitemapController::class, 'index'])->name('frontend.sitemap');
    Route::get('/{typeOrSlug}/{slug?}', [FrontendController::class, 'single'])->name('frontend.show')->where('slug', '.*');
});

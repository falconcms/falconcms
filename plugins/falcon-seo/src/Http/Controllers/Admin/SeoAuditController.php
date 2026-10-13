<?php

namespace FalconSeo\Http\Controllers\Admin;

use FalconSeo\Support\SeoAudit;
use Illuminate\Routing\Controller;

/** Settings → SEO Audit: a site-wide check-up with a link to fix each finding. */
class SeoAuditController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()?->hasPermission('manage_settings'), 403);

        return view('falcon-seo::admin.seo-audit', ['audit' => SeoAudit::run()]);
    }
}

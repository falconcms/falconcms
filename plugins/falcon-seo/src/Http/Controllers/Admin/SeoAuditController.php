<?php

namespace FalconSeo\Http\Controllers\Admin;

use FalconSeo\Support\SeoAudit;
use Illuminate\Routing\Controller;

/** SEO → SEO Audit: a site-wide check-up with a link to fix each finding. */
class SeoAuditController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()?->hasPermission('access_seo_audit'), 403);

        return view('falcon-seo::admin.seo-audit', ['audit' => SeoAudit::run()]);
    }
}

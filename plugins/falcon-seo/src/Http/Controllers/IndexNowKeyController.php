<?php

namespace FalconSeo\Http\Controllers;

use FalconSeo\Support\IndexNow;
use Illuminate\Routing\Controller;

/** The IndexNow key file: proves to the engines that pings for this host come from its owner. */
class IndexNowKeyController extends Controller
{
    public function __invoke(string $indexnowKey)
    {
        abort_unless(IndexNow::enabled() && hash_equals(IndexNow::key(), $indexnowKey), 404);

        return response($indexnowKey, 200)->header('Content-Type', 'text/plain; charset=utf-8');
    }
}

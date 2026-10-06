<?php

namespace FalconCms\Core\Http\Controllers;

use Illuminate\Routing\Controller;

/**
 * Stands in for the controllers of a plugin that is switched off.
 *
 * A switched-off plugin's routes are not registered, but a route cache built while it was on
 * still lists them, naming controllers whose classes are no longer loadable. Every request to
 * one of those URLs would be a 500 ("Target class does not exist") until someone cleared the
 * cache. PluginManager points those class names here instead, so they answer 404, as if the
 * plugin had never been there.
 */
class DormantPluginController extends Controller
{
    public function callAction($method, $parameters)
    {
        abort(404);
    }

    public function __call($method, $parameters)
    {
        abort(404);
    }
}

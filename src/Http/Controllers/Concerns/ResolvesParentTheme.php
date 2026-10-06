<?php

namespace FalconCms\Core\Http\Controllers\Concerns;

trait ResolvesParentTheme
{
    /** The parent named in a theme's theme.json, if it declares one (see falcon_theme_parent()). */
    protected function parentThemeOf(string $theme): ?string
    {
        return falcon_theme_parent($theme);
    }
}

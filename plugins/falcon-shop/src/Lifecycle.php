<?php

namespace FalconShop;

/** Plugin lifecycle (named in plugin.json): activating the shop makes its pages. */
class Lifecycle
{
    public function activate(): void
    {
        StorefrontPages::ensure();
        update_cms_option(StorefrontPages::DONE, '1');
    }
}

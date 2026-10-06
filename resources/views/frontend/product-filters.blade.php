{{-- Moved to the shop plugin (falcon-shop::frontend.partials.product-filters). Kept so templates
     published before the move still find it; renders nothing while the shop is off. --}}
@includeWhen(falcon_plugin_active('falcon-shop'), 'falcon-shop::frontend.partials.product-filters')

{{--
    Taxonomy / post-type data every builder screen needs.

    Included by the page-section builder, the Post Card builder and the Mega Menu builder.
    It lives in one partial because all three include the same sidebar + scripts: when a
    screen forgot one of these globals, controls that read it (card query filters, the
    Taxonomy dynamic source) silently fell back to "post + category only" on that screen.
--}}
@php
    // Built-ins are declared outside the try so they survive any DB exception.
    $__bdTaxonomies = [
        ['slug' => 'category', 'name' => 'Category', 'type' => 'built_in'],
        ['slug' => 'tag',      'name' => 'Tag',      'type' => 'built_in'],
    ];
    $__bdTaxTerms      = [];
    $__bdCptList       = [];
    $__bdCptTaxonomies = ['post' => ['category', 'tag'], 'page' => [], 'product' => []];
    // Post types offered by the Taxonomy dynamic source. Built-ins first, then every CPT.
    $__bdPostTypes = [
        ['slug' => 'post',    'name' => 'Post'],
        ['slug' => 'page',    'name' => 'Page'],
        ['slug' => 'product', 'name' => 'Product'],
    ];

    try {
        $__bdTaxTerms['category'] = \FalconCms\Core\Models\Category::select('id', 'name', 'slug')->orderBy('name')->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug])->values()->toArray();
        $__bdTaxTerms['tag'] = \FalconCms\Core\Models\Tag::select('id', 'name', 'slug')->orderBy('name')->get()
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug])->values()->toArray();

        foreach (\FalconCms\Core\Models\CustomTaxonomy::where('is_active', true)->get() as $__bdTax) {
            $__bdTaxonomies[] = ['slug' => $__bdTax->slug, 'name' => $__bdTax->name, 'type' => 'custom'];
            $__bdTaxTerms[$__bdTax->slug] = \FalconCms\Core\Models\TaxonomyTerm::where('taxonomy_slug', $__bdTax->slug)
                ->select('id', 'name', 'slug')->orderBy('name')->get()
                ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug])->values()->toArray();
            foreach (($__bdTax->post_types ?? []) as $__bdPt) {
                if (!isset($__bdCptTaxonomies[$__bdPt])) $__bdCptTaxonomies[$__bdPt] = [];
                if (!in_array($__bdTax->slug, $__bdCptTaxonomies[$__bdPt], true)) $__bdCptTaxonomies[$__bdPt][] = $__bdTax->slug;
            }
        }

        $__bdCptList = \FalconCms\Core\Models\PostType::where('is_builtin', false)
            ->where('is_active', true)->whereNull('deleted_at')->orderBy('name')->get()
            ->map(fn ($c) => ['slug' => $c->slug, 'name' => $c->name])->values()->toArray();
        foreach ($__bdCptList as $__bdCpt) {
            $__bdPostTypes[] = ['slug' => $__bdCpt['slug'], 'name' => $__bdCpt['name']];
        }
    } catch (\Throwable $e) {
        // Built-ins above already give the builder a usable (if minimal) set.
    }
@endphp
<script>
    window.falconTaxonomies    = {!! json_encode($__bdTaxonomies, JSON_HEX_TAG) !!};
    window.falconTaxonomyTerms = {!! json_encode($__bdTaxTerms, JSON_HEX_TAG) !!};
    window.falconCptList       = {!! json_encode($__bdCptList, JSON_HEX_TAG) !!};
    window.falconCptTaxonomies = {!! json_encode($__bdCptTaxonomies, JSON_HEX_TAG) !!};
    window.falconPostTypeList  = {!! json_encode($__bdPostTypes, JSON_HEX_TAG) !!};
</script>

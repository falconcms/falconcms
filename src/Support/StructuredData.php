<?php

namespace FalconCms\Core\Support;

use App\Models\User;
use Throwable;

/**
 * The page's JSON-LD, as one @graph of linked entities:
 *
 *   Organization (or LocalBusiness) — who runs the site: name, logo, social profiles, contact,
 *       address. Set once in SEO settings; Google uses it for the knowledge panel and to know
 *       who stands behind the content.
 *   WebSite — the site, published by that organization.
 *   The page — a BlogPosting for posts (with its author as a Person: bio, photo, profiles,
 *       a link to their author page), a WebPage otherwise, a Product where the shop supplies
 *       one, a ProfilePage on an author's archive.
 *   Anything elements on the page add (a video, an event…), through add() or the
 *       `falcon_structured_data` filter.
 *
 * Entities refer to each other by @id instead of repeating themselves, which is how Google's
 * documentation shows linked data. Only Google-supported types are emitted.
 */
final class StructuredData
{
    /** @var list<array> nodes added by elements while the page renders */
    private static array $extra = [];

    public const BUSINESS_TYPES = [
        'Organization' => 'Organization (company, brand, project)',
        'LocalBusiness' => 'Local business',
        'Store' => 'Shop / store',
        'Restaurant' => 'Restaurant / café',
        'ProfessionalService' => 'Professional service (agency, consultant, lawyer…)',
        'MedicalBusiness' => 'Medical business / clinic',
        'EducationalOrganization' => 'School / education',
        'NGO' => 'Non-profit',
    ];

    /** Add a node for this page (an element's VideoObject, Event, …). */
    public static function add(array $node): void
    {
        self::$extra[] = $node;
    }

    /** Nodes added so far, and forget them (called once when the head is printed). */
    public static function takeExtra(): array
    {
        $extra = self::$extra;
        self::$extra = [];

        return $extra;
    }

    public static function organizationId(): string
    {
        return url('/').'#organization';
    }

    /** The site owner: Organization or a LocalBusiness subtype, from SEO settings. */
    public static function organization(string $siteTitle): array
    {
        $type = (string) get_cms_option('seo_org_type', 'Organization');
        $type = array_key_exists($type, self::BUSINESS_TYPES) ? $type : 'Organization';
        $name = trim((string) get_cms_option('seo_org_name', '')) ?: $siteTitle;

        $node = ['@type' => $type, '@id' => self::organizationId(), 'name' => $name, 'url' => url('/')];

        if ($logo = self::logoUrl()) {
            $node['logo'] = ['@type' => 'ImageObject', 'url' => $logo];
            if ($type !== 'Organization') {
                $node['image'] = $logo;
            }
        }
        $sameAs = array_values(array_filter(array_map('trim', preg_split('/\s+/', (string) get_cms_option('seo_org_same_as', '')) ?: []),
            fn ($u) => filter_var($u, FILTER_VALIDATE_URL)));
        if ($sameAs) {
            $node['sameAs'] = $sameAs;
        }
        foreach (['telephone' => 'seo_org_phone', 'email' => 'seo_org_email'] as $prop => $option) {
            if ($value = trim((string) get_cms_option($option, ''))) {
                $node[$prop] = $value;
            }
        }

        $address = array_filter([
            'streetAddress' => trim((string) get_cms_option('seo_org_street', '')),
            'addressLocality' => trim((string) get_cms_option('seo_org_city', '')),
            'addressRegion' => trim((string) get_cms_option('seo_org_region', '')),
            'postalCode' => trim((string) get_cms_option('seo_org_postal_code', '')),
            'addressCountry' => trim((string) get_cms_option('seo_org_country', '')),
        ]);
        if ($address) {
            $node['address'] = ['@type' => 'PostalAddress'] + $address;
        }
        if ($type !== 'Organization' && ($range = trim((string) get_cms_option('seo_org_price_range', '')))) {
            $node['priceRange'] = $range;
        }

        return $node;
    }

    /** SEO settings logo, then the theme logo, then nothing (a favicon is not a logo). */
    public static function logoUrl(): ?string
    {
        foreach (['seo_org_logo', 'theme_site_logo'] as $option) {
            $logo = trim((string) get_cms_option($option, ''));
            if ($logo !== '') {
                return str_starts_with($logo, 'http') ? $logo : url(str_starts_with($logo, '/storage') ? $logo : '/storage/'.ltrim($logo, '/'));
            }
        }

        return null;
    }

    /** A person who writes on the site: name, page, photo, bio and their own profiles. */
    public static function person($user): ?array
    {
        if (!$user || empty($user->id)) {
            return null;
        }
        $url = route('frontend.author', $user->id);
        $node = ['@type' => 'Person', '@id' => $url.'#person', 'name' => (string) $user->name, 'url' => $url];

        if (!empty($user->avatar)) {
            $node['image'] = str_starts_with((string) $user->avatar, 'http') ? $user->avatar : url('/storage/'.ltrim((string) $user->avatar, '/'));
        }
        if ($bio = trim(strip_tags((string) ($user->bio ?? '')))) {
            $node['description'] = mb_substr($bio, 0, 500);
        }
        $sameAs = array_values(array_filter([$user->facebook_url ?? null, $user->twitter_url ?? null, $user->linkedin_url ?? null],
            fn ($u) => $u && filter_var($u, FILTER_VALIDATE_URL)));
        if ($sameAs) {
            $node['sameAs'] = $sameAs;
        }

        return $node;
    }

    /**
     * The whole graph for a page.
     *
     * @param  array{site_title: string, title: string, description: string, image: ?string, type: string, post?: mixed}  $page
     */
    public static function graph(array $page): array
    {
        $graph = [];
        $orgId = self::organizationId();
        $graph[] = self::organization($page['site_title']);
        $graph[] = ['@type' => 'WebSite', '@id' => url('/').'#website', 'url' => url('/'), 'name' => $page['site_title'],
            'publisher' => ['@id' => $orgId], 'inLanguage' => app()->getLocale()];

        $post = $page['post'] ?? null;
        $current = url()->current();
        $node = null;

        // A product page describes a Product: that is what puts price and rating in a result.
        if ($post && function_exists('falcon_product_schema')) {
            try {
                $product = falcon_product_schema($post);
            } catch (Throwable $e) {
                $product = null;
            }
            if ($product) {
                unset($product['@context']);
                $node = $product;
            }
        }

        if (!$node && $post && $page['type'] === 'article') {
            $node = [
                '@type' => 'BlogPosting', '@id' => $current.'#article',
                'headline' => mb_substr($page['title'], 0, 110), 'description' => $page['description'],
                'url' => $current, 'mainEntityOfPage' => $current,
                'datePublished' => self::date($post->published_at ?? $post->created_at ?? null),
                'dateModified' => self::date($post->updated_at ?? null),
                'publisher' => ['@id' => $orgId], 'isPartOf' => ['@id' => url('/').'#website'],
            ];
            $user = $post->user ?? null;
            if ($person = self::person($user)) {
                $graph[] = $person;
                $node['author'] = ['@id' => $person['@id']];
            } else {
                $node['author'] = ['@id' => $orgId];
            }
        }

        // An author's archive: a ProfilePage about that person.
        $context = function_exists('falcon_layout_context') ? falcon_layout_context() : [];
        if (!$node && ($context['archive_type'] ?? null) === 'author' && !empty($context['author_id'])) {
            try {
                $author = User::find((int) $context['author_id']);
            } catch (Throwable $e) {
                $author = null;
            }
            if ($person = self::person($author)) {
                $node = ['@type' => 'ProfilePage', '@id' => $current.'#webpage', 'url' => $current, 'name' => $page['title'],
                    'mainEntity' => $person, 'isPartOf' => ['@id' => url('/').'#website']];
            }
        }

        $node ??= ['@type' => 'WebPage', '@id' => $current.'#webpage', 'url' => $current, 'name' => $page['title'],
            'description' => $page['description'], 'isPartOf' => ['@id' => url('/').'#website']];
        if (!empty($page['image']) && !isset($node['image'])) {
            $node['image'] = $page['image'];
        }
        $graph[] = array_filter($node, fn ($v) => $v !== null && $v !== '');

        foreach (self::takeExtra() as $extra) {
            unset($extra['@context']);
            $graph[] = $extra;
        }

        $graph = apply_falcon_filters('falcon_structured_data', $graph, $page);

        return ['@context' => 'https://schema.org', '@graph' => array_values($graph)];
    }

    private static function date($value): ?string
    {
        try {
            return $value ? \Illuminate\Support\Carbon::parse($value)->toIso8601String() : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

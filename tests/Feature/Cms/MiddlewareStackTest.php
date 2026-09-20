<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Language;
use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\App;

/**
 * The middleware every request passes through.
 *
 * None of these are ever called directly, none of them are visible on a page, and every one
 * of them is the sort of thing that stops working without anyone noticing: a header that
 * silently stops being sent, a cart cookie that silently stops being written, a locale that
 * silently stops being applied. The first report is months later and never mentions
 * middleware.
 *
 * They are exercised through real requests on real routes, because the wiring — which group
 * a middleware is actually in — is the half that breaks.
 */
class MiddlewareStackTest extends TestCase
{
    use MakesShopFixtures;

    /** A route in the web group, so the globally-pushed middleware run on it. */
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->post('/mw/echo-content', fn () => request('content', ''));
    }

    // ── SecurityHeadersMiddleware ────────────────────────────────────────────

    public function test_the_security_headers_are_on_a_public_page(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // The browser is told the site has no use for a camera, a microphone or a location,
        // so an injected script cannot ask for one in the site's name.
        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
    }

    public function test_the_server_does_not_announce_what_it_is_running(): void
    {
        $this->get('/')->assertHeaderMissing('X-Powered-By');
    }

    public function test_the_transport_header_is_only_sent_over_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        // Sending HSTS over plain http is ignored by browsers; sending it over https is what
        // stops the next visit being downgraded.
        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_the_admin_area_carries_the_same_headers(): void
    {
        // The admin sits in its own route group, so it has its own chance to lose them —
        // and it is the half of the site where clickjacking actually costs something.
        $this->get('/admin/login')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    // ── PersistCart ──────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function cartItem(string $title = 'Kept Between Visits'): array
    {
        $product = $this->makeProduct([], ['title' => $title]);

        return ['k'.$product->id => [
            'id' => $product->id,
            'name' => $title,
            'slug' => $product->slug,
            'price' => 1000.0,
            'sale_price' => null,
            'quantity' => 1,
            'thumbnail' => null,
            'variation_id' => null,
            'sku' => null,
            'meta' => [],
        ]];
    }

    /** Hand the request the cookie a returning browser would send (the harness encrypts it). */
    private function withCartCookie(array $cart): self
    {
        return $this->withCookie('falcon_cart_v1', json_encode($cart));
    }

    public function test_a_basket_is_written_to_a_long_lived_cookie(): void
    {
        $cart = $this->cartItem();
        session()->put('falcon_cart', $cart);

        // The session dies with the browser; the cookie is what makes a basket survive a
        // restart, which is most of the reason an abandoned cart ever gets bought.
        $this->get('/')->assertCookie('falcon_cart_v1', json_encode($cart));
    }

    public function test_a_returning_browser_gets_its_basket_back(): void
    {
        $cart = $this->cartItem('Left Here Last Week');

        $this->withCartCookie($cart)->get('/cart')
            ->assertOk()
            ->assertSee('Left Here Last Week', false);
    }

    public function test_a_session_basket_is_not_overwritten_by_an_older_cookie(): void
    {
        $fromCookie = $this->cartItem('Stale');
        $current = $this->cartItem('Current');
        session()->put('falcon_cart', $current);

        $this->withCartCookie($fromCookie)->get('/cart')
            ->assertSee('Current', false)
            ->assertDontSee('Stale', false);
    }

    public function test_emptying_the_basket_drops_the_cookie_too(): void
    {
        // A browser arriving with a stored basket that is now empty. The cookie has to be
        // expired, or the next page load hands the shopper back a basket they just emptied.
        $this->withCartCookie([])->get('/')->assertCookieExpired('falcon_cart_v1');
    }

    public function test_the_admin_area_does_not_get_a_cart_cookie(): void
    {
        session()->put('falcon_cart', $this->cartItem());

        $this->get('/admin/login')->assertCookieMissing('falcon_cart_v1');
    }

    // ── LocalizationMiddleware ───────────────────────────────────────────────

    public function test_an_active_language_in_the_url_sets_the_locale(): void
    {
        Language::create(['name' => 'English', 'code' => 'en', 'is_default' => true, 'status' => true]);
        Language::create(['name' => 'Bangla', 'code' => 'bn', 'is_default' => false, 'status' => true]);

        $this->get('/bn/anything-at-all');

        $this->assertSame('bn', App::getLocale());
    }

    public function test_a_language_that_is_switched_off_does_not_set_the_locale(): void
    {
        Language::create(['name' => 'English', 'code' => 'en', 'is_default' => true, 'status' => true]);
        Language::create(['name' => 'Bangla', 'code' => 'bn', 'is_default' => false, 'status' => false]);

        // Turning a language off in the admin has to actually turn it off; otherwise the
        // half-translated language a site is still working on is live on the internet.
        $this->get('/bn/anything-at-all');

        $this->assertSame('en', App::getLocale());
    }

    public function test_an_ordinary_url_falls_back_to_the_default_language(): void
    {
        Language::create(['name' => 'Bangla', 'code' => 'bn', 'is_default' => true, 'status' => true]);

        $this->get('/');

        $this->assertSame('bn', App::getLocale());
    }

    // ── BuilderShortcodeMiddleware ───────────────────────────────────────────

    public function test_shortcode_content_is_converted_before_the_controller_sees_it(): void
    {
        $shortcode = "[falcon_section]\n[falcon_title text=\"Hello\"]\n[/falcon_section]";

        $body = $this->post('/mw/echo-content', ['content' => $shortcode])->getContent();

        // The controller must never have to know the editor sent shortcodes.
        $this->assertTrue(BuilderShortcodeConverter::isBuilderJson($body),
            'the controller was handed shortcodes instead of builder JSON');
    }

    public function test_entity_encoded_brackets_are_still_recognised(): void
    {
        // Some editors write [ as &#91;. Without normalising, the content reached the
        // controller as literal text and the page saved as gibberish.
        $shortcode = '&#91;falcon_section&#93;&#91;falcon_title text="Hi"&#93;&#91;/falcon_section&#93;';

        $body = $this->post('/mw/echo-content', ['content' => $shortcode])->getContent();

        $this->assertTrue(BuilderShortcodeConverter::isBuilderJson($body));
    }

    public function test_ordinary_content_is_passed_through_untouched(): void
    {
        $html = '<p>Just an article. Nothing to convert.</p>';

        $this->assertSame($html, $this->post('/mw/echo-content', ['content' => $html])->getContent());
    }

    public function test_content_that_is_already_builder_json_is_left_alone(): void
    {
        $json = json_encode([['id' => 'sec-1', 'type' => 'section', 'children' => []]]);

        // Converting twice is how a saved layout gets mangled, so JSON is recognised first.
        $this->assertSame($json, $this->post('/mw/echo-content', ['content' => $json])->getContent());
    }

    // ── the write API is actually behind its gate ────────────────────────────

    public function test_the_write_api_refuses_a_request_with_no_token(): void
    {
        // The middleware itself is tested directly elsewhere; this is the wiring — that the
        // route group it is supposed to protect is the group it is on.
        $this->postJson('/api/v1/posts', ['title' => 'Snuck in'])
            ->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_the_read_api_stays_open(): void
    {
        $this->getJson('/api/v1/posts')->assertOk();
    }
}

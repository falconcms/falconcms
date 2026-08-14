<?php

namespace FalconCms\Core\Tests\Feature\Security;

use FalconCms\Core\Http\Controllers\ShopFrontendController;
use FalconCms\Core\Models\OrderDownload;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Paid file downloads.
 *
 * The token in the link is the whole of the authorisation — anyone holding it gets the
 * file, which is the point (the link is mailed and has to work from any device). So the
 * things that matter are that the token is unguessable, that it only ever reaches the
 * one file it was minted for, and that expiry and the download cap are actually
 * enforced rather than merely stored.
 */
class DigitalDownloadTest extends TestCase
{
    use MakesShopFixtures;

    private function makeDownload(array $overrides = [], string $contents = 'file contents'): OrderDownload
    {
        static $n = 0;
        $n++;

        $product = $this->makeProduct(['is_downloadable' => 1]);

        $relative = 'downloads/test-file-'.$n.'.txt';
        $absolute = storage_path('app/'.$relative);
        if (!is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0777, true);
        }
        file_put_contents($absolute, $contents);

        $fileId = DB::table('shop_product_downloads')->insertGetId([
            'product_id' => $product->shopData->id,
            'name' => 'Manual.txt',
            'file_path' => $relative,
            'download_limit' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('shop_orders')->insertGetId([
            'order_number' => 'ORD-'.strtoupper(Str::random(6)),
            'status' => 'completed',
            'subtotal' => 100, 'total' => 100,
            'customer_email' => 'buyer@example.test',
            'first_name' => 'Buyer', 'last_name' => 'One',
            'address_line_1' => '1 Road', 'city' => 'Dhaka', 'state' => 'Dhaka',
            'postcode' => '1207', 'country' => 'Bangladesh',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $orderItemId = DB::table('shop_order_items')->insertGetId([
            'order_id' => $orderId,
            'product_id' => $product->id,
            'product_name' => 'Downloadable product',
            'quantity' => 1,
            'price' => 100,
            'subtotal' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return OrderDownload::create(array_merge([
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'product_download_id' => $fileId,
            'token' => Str::random(48),
            'expires_at' => null,
            'download_limit' => null,
        ], $overrides));
    }

    /** @return int the HTTP status the customer would get */
    private function fetch(string $token): int
    {
        $request = Request::create('/download/'.$token, 'GET');
        $this->app->instance('request', $request);
        Facade::clearResolvedInstance('request');

        try {
            $response = (new ShopFrontendController)->downloadFile($request, $token);

            return $response->getStatusCode();
        } catch (HttpException $e) {
            return $e->getStatusCode();
        }
    }

    protected function tearDown(): void
    {
        foreach (glob(storage_path('app/downloads/test-file-*.txt')) ?: [] as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    // ---- the happy path --------------------------------------------------------

    public function test_a_valid_token_serves_the_file(): void
    {
        $download = $this->makeDownload();

        $this->assertSame(200, $this->fetch($download->token));
    }

    public function test_each_download_is_counted(): void
    {
        $download = $this->makeDownload();

        $this->fetch($download->token);
        $this->fetch($download->token);

        $this->assertSame(2, (int) $download->fresh()->download_count);
    }

    // ---- the token is the credential -------------------------------------------

    public function test_a_token_that_does_not_exist_is_a_404(): void
    {
        $this->assertSame(404, $this->fetch(Str::random(48)));
    }

    public function test_an_empty_token_is_refused(): void
    {
        $this->assertSame(404, $this->fetch(''));
    }

    /**
     * Tokens are the only thing standing between a paying customer's file and everyone
     * else, so they have to be long and random rather than sequential or derived.
     */
    public function test_tokens_are_long_and_unique(): void
    {
        $tokens = [];
        for ($i = 0; $i < 5; $i++) {
            $tokens[] = $this->makeDownload()->token;
        }

        $this->assertCount(5, array_unique($tokens));
        foreach ($tokens as $token) {
            $this->assertGreaterThanOrEqual(40, strlen($token));
        }
    }

    /** One order's link must not reach another order's file. */
    public function test_one_customers_token_does_not_open_another_customers_file(): void
    {
        $mine = $this->makeDownload([], 'my manual');
        $theirs = $this->makeDownload([], 'their manual');

        $this->assertSame(200, $this->fetch($mine->token));

        $this->assertNotSame($mine->product_download_id, $theirs->product_download_id);
        $this->assertNotSame($mine->token, $theirs->token);
    }

    // ---- expiry and caps -------------------------------------------------------

    public function test_an_expired_link_is_gone(): void
    {
        $download = $this->makeDownload(['expires_at' => now()->subMinute()]);

        $this->assertSame(410, $this->fetch($download->token));
    }

    public function test_a_link_expiring_in_the_future_still_works(): void
    {
        $download = $this->makeDownload(['expires_at' => now()->addDay()]);

        $this->assertSame(200, $this->fetch($download->token));
    }

    public function test_a_link_with_no_expiry_never_expires(): void
    {
        $download = $this->makeDownload(['expires_at' => null]);

        $this->assertFalse($download->isExpired());
        $this->assertSame(200, $this->fetch($download->token));
    }

    public function test_the_download_limit_is_enforced(): void
    {
        $download = $this->makeDownload(['download_limit' => 2]);

        $this->assertSame(200, $this->fetch($download->token), 'first');
        $this->assertSame(200, $this->fetch($download->token), 'second');
        $this->assertSame(410, $this->fetch($download->token), 'third is over the cap');
    }

    public function test_no_limit_means_no_limit(): void
    {
        $download = $this->makeDownload(['download_limit' => null]);

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(200, $this->fetch($download->token));
        }
    }

    public function test_accessibility_combines_expiry_and_the_cap(): void
    {
        $fine = $this->makeDownload(['download_limit' => 2]);
        $this->assertTrue($fine->isAccessible());

        $expired = $this->makeDownload(['expires_at' => now()->subDay()]);
        $this->assertFalse($expired->isAccessible());

        $exhausted = $this->makeDownload(['download_limit' => 1]);
        $exhausted->forceFill(['download_count' => 1])->save();
        $this->assertFalse($exhausted->fresh()->isAccessible());
    }

    // ---- the file behind the token ---------------------------------------------

    public function test_a_missing_file_on_disk_is_a_404_not_a_crash(): void
    {
        $download = $this->makeDownload();
        @unlink(storage_path('app/'.$download->productDownload->file_path));

        $this->assertSame(404, $this->fetch($download->token));
    }

    public function test_a_token_whose_file_record_is_gone_is_a_404(): void
    {
        $download = $this->makeDownload();
        DB::table('shop_product_downloads')
            ->where('id', $download->product_download_id)->delete();

        $this->assertSame(404, $this->fetch($download->token));
    }

    /**
     * file_path is set by the shop owner, not the customer, so this is defence in depth
     * rather than a live hole — but a path that climbs out of the storage directory must
     * not resolve to something on the server.
     */
    public function test_a_traversing_file_path_does_not_escape_storage(): void
    {
        // A real file outside the storage tree, so the test cannot pass merely because the
        // traversal target happens not to exist on this machine.
        $secret = dirname(storage_path()).'/traversal-target.txt';
        file_put_contents($secret, 'not for sale');

        try {
            $download = $this->makeDownload();
            DB::table('shop_product_downloads')
                ->where('id', $download->product_download_id)
                ->update(['file_path' => 'downloads/../../../traversal-target.txt']);

            $this->assertSame(404, $this->fetch($download->token),
                'a file_path climbing out of storage must not be served');
        } finally {
            @unlink($secret);
        }
    }
}

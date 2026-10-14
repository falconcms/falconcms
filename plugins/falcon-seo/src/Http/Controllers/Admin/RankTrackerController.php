<?php

namespace FalconSeo\Http\Controllers\Admin;

use FalconSeo\Support\Countries;
use FalconSeo\Support\DataForSeo;
use FalconSeo\Support\KeywordDemand;
use FalconSeo\Support\Pro;
use FalconSeo\Support\RankTracker;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Throwable;

/** SEO → Rank Tracker (Pro, with DataForSEO): tracked keywords, and a competitor's keyword gap. */
class RankTrackerController extends Controller
{
    /** Keywords checked by one "Check now" (each is a live lookup of a few seconds). */
    public const CHECK_BATCH = 10;

    /** New keywords checked straight away when added; the rest follow with the hourly run. */
    private const CHECK_ON_ADD = 5;

    public function index(Request $request)
    {
        $this->authorizeScreen();
        $tab = $request->query('tab') === 'gap' ? 'gap' : 'tracked';
        $rows = RankTracker::available() ? RankTracker::rows() : [];
        $country = $this->country($request->query('country'));
        $competitor = DataForSeo::domain((string) $request->query('competitor', ''));

        return view('falcon-seo::admin.rank-tracker', [
            'pro' => Pro::active(),
            'available' => RankTracker::available(),
            'tab' => $tab,
            'rows' => $rows,
            'summary' => RankTracker::summary($rows),
            'frequency' => RankTracker::frequency(),
            'country' => $country,
            'competitor' => $competitor,
            'gap' => $tab === 'gap' && $competitor !== '' ? DataForSeo::cachedGap($competitor, $country) : null,
            'ownDomain' => DataForSeo::ownDomain(),
            'tracked' => array_flip(array_map(fn ($r) => $r['keyword'].'|'.$r['country'], $rows)),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeScreen();
        abort_unless(RankTracker::available(), 403);
        $data = $request->validate([
            'keywords' => ['required', 'string', 'max:5000'],
            'country' => ['required', 'string', 'size:2', Rule::in(array_keys(Countries::ALL))],
            'device' => ['nullable', Rule::in(array_keys(RankTracker::DEVICES))],
        ]);
        $result = RankTracker::add($data['keywords'], $data['country'], $data['device'] ?? 'desktop');
        foreach (array_slice($result['added'], 0, self::CHECK_ON_ADD) as $id) {
            RankTracker::check($id);
        }

        $message = count($result['added']).' keyword(s) added.';
        if (count($result['added']) > self::CHECK_ON_ADD) {
            $message .= ' The first '.self::CHECK_ON_ADD.' are checked; the rest follow within the hour.';
        }
        if ($result['skipped']) {
            $message .= ' '.$result['skipped'].' already tracked.';
        }
        if ($result['full']) {
            $message .= ' The list holds '.RankTracker::MAX_KEYWORDS.' keywords (and '.RankTracker::MAX_ADD.' at a time); the rest were not added.';
        }

        return back()->with('success', $message);
    }

    public function destroy(int $id)
    {
        $this->authorizeScreen();
        RankTracker::remove($id);

        return back()->with('success', 'Keyword removed.');
    }

    /** "Check now": one keyword, or the next ones that are due. */
    public function check(Request $request)
    {
        $this->authorizeScreen();
        abort_unless(RankTracker::available(), 403);
        if ($id = (int) $request->input('id')) {
            $row = RankTracker::check($id);
            abort_unless($row, 404);

            return back()->with($row->error ? 'error' : 'success', $row->error ? 'The check failed: '.$row->error : 'Checked "'.$row->keyword.'".');
        }
        $checked = RankTracker::runDue(self::CHECK_BATCH);

        return back()->with('success', $checked ? "Checked {$checked} keyword(s)." : 'Every keyword is up to date; the next checks come when they are due.');
    }

    public function settings(Request $request)
    {
        $this->authorizeScreen();
        $data = $request->validate(['frequency' => ['required', Rule::in(array_keys(RankTracker::FREQUENCIES))]]);
        update_cms_option(RankTracker::FREQUENCY, $data['frequency']);

        return back()->with('success', 'Keywords are checked '.strtolower(RankTracker::FREQUENCIES[$data['frequency']]).'.');
    }

    /** Ask DataForSEO for a competitor's keywords that this site does not rank for (kept a week). */
    public function gap(Request $request)
    {
        $this->authorizeScreen();
        abort_unless(RankTracker::available(), 403);
        $data = $request->validate(['competitor' => ['required', 'string', 'max:255'], 'country' => ['required', 'string', 'size:2']]);
        $country = $this->country($data['country']);
        $competitor = DataForSeo::domain($data['competitor']);
        $back = redirect()->route('admin.seo.rank-tracker', ['tab' => 'gap', 'competitor' => $competitor ?: $data['competitor'], 'country' => $country]);

        if ($competitor === DataForSeo::ownDomain()) {
            return $back->with('error', 'That is this site. Enter a competitor\'s domain.');
        }
        try {
            DataForSeo::keywordGap($competitor, $country);
        } catch (Throwable $e) {
            return $back->with('error', $e->getMessage());
        }

        return $back;
    }

    private function authorizeScreen(): void
    {
        abort_unless(auth()->user()?->hasPermission('access_seo_rank_tracker'), 403);
    }

    private function country(mixed $code): string
    {
        $code = strtolower((string) $code);
        if (Countries::valid($code)) {
            return $code;
        }
        $default = strtolower((string) get_cms_option(KeywordDemand::COUNTRY, 'us'));

        return Countries::valid($default) ? $default : 'us';
    }
}

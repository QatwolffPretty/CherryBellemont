<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\NewsletterCampaignRequest;
use App\Http\Requests\ScheduleNewsletterCampaignRequest;
use App\Http\Requests\SendNewsletterCampaignRequest;
use App\Http\Requests\SendNewsletterCampaignTestRequest;
use App\Mail\NewsletterCampaignMail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Services\CampaignContentSanitizer;
use App\Services\NewsletterCampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class NewsletterCampaignController extends Controller
{
    public function index(Request $request): View
    {
        $campaigns = NewsletterCampaign::query()->with('creator:id,name')->latest();
        $status = $request->string('status')->value();

        if (in_array($status, $this->statuses(), true)) {
            $campaigns->where('status', $status);
        }

        if ($search = $request->string('search')->trim()->value()) {
            $campaigns->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        return view('admin.newsletter.campaigns.index', [
            'campaigns' => $campaigns->paginate(20)->withQueryString(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): View
    {
        return view('admin.newsletter.campaigns.form', [
            'campaign' => new NewsletterCampaign(['audience_type' => NewsletterCampaign::AUDIENCE_ALL_ACTIVE]),
            'audiences' => $this->audiences(),
        ]);
    }

    public function store(NewsletterCampaignRequest $request, CampaignContentSanitizer $sanitizer): RedirectResponse
    {
        $campaign = NewsletterCampaign::create($this->campaignData($request, $sanitizer) + [
            'status' => NewsletterCampaign::STATUS_DRAFT,
            'created_by' => $request->user()->id,
        ]);

        return to_route('admin.newsletter.campaigns.show', $campaign)->with('success', 'Campaign saved as a draft.');
    }

    public function show(NewsletterCampaign $campaign): View
    {
        $campaign->load('creator:id,name');
        $counts = $campaign->deliveries()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('admin.newsletter.campaigns.show', [
            'campaign' => $campaign,
            'counts' => $counts,
        ]);
    }

    public function edit(NewsletterCampaign $campaign): View|RedirectResponse
    {
        if (in_array($campaign->status, [NewsletterCampaign::STATUS_SENDING, NewsletterCampaign::STATUS_SENT, NewsletterCampaign::STATUS_ARCHIVED], true)) {
            return to_route('admin.newsletter.campaigns.show', $campaign)->withErrors(['campaign' => 'This campaign can no longer be edited. Duplicate it to make changes.']);
        }

        return view('admin.newsletter.campaigns.form', compact('campaign') + ['audiences' => $this->audiences()]);
    }

    public function update(NewsletterCampaignRequest $request, NewsletterCampaign $campaign, CampaignContentSanitizer $sanitizer): RedirectResponse
    {
        if (in_array($campaign->status, [NewsletterCampaign::STATUS_SENDING, NewsletterCampaign::STATUS_SENT, NewsletterCampaign::STATUS_ARCHIVED], true)) {
            return back()->withErrors(['campaign' => 'This campaign can no longer be edited. Duplicate it to make changes.']);
        }

        $campaign->update($this->campaignData($request, $sanitizer, $campaign));

        return to_route('admin.newsletter.campaigns.show', $campaign)->with('success', 'Campaign updated.');
    }

    public function preview(NewsletterCampaign $campaign): View
    {
        $subscriber = new NewsletterSubscriber([
            'name' => 'Preview Subscriber',
            'email' => 'preview@example.test',
            'verification_token' => Str::random(64),
        ]);

        return view('admin.newsletter.campaigns.preview', compact('campaign', 'subscriber'));
    }

    public function test(SendNewsletterCampaignTestRequest $request, NewsletterCampaign $campaign): RedirectResponse
    {
        $subscriber = new NewsletterSubscriber([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'verification_token' => Str::random(64),
            'status' => 'subscribed',
        ]);

        Mail::to($subscriber->email, $subscriber->name)
            ->queue((new NewsletterCampaignMail($campaign, $subscriber, true))->afterCommit());

        return back()->with('success', 'A test copy has been queued for '.$subscriber->email.'.');
    }

    public function schedule(ScheduleNewsletterCampaignRequest $request, NewsletterCampaign $campaign, NewsletterCampaignService $campaigns): RedirectResponse
    {
        try {
            $campaigns->schedule($campaign, $request->date('scheduled_at'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['campaign' => $exception->getMessage()]);
        }

        return to_route('admin.newsletter.campaigns.show', $campaign)->with('success', 'Campaign scheduled.');
    }

    public function send(SendNewsletterCampaignRequest $request, NewsletterCampaign $campaign, NewsletterCampaignService $campaigns): RedirectResponse
    {
        try {
            $campaign = $campaigns->start($campaign);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['campaign' => $exception->getMessage()]);
        }

        if ($campaign->status === NewsletterCampaign::STATUS_SENDING) {
            $campaigns->dispatchPendingDeliveries($campaign);
        }

        return to_route('admin.newsletter.campaigns.show', $campaign)->with('success', 'Campaign delivery has started in the queue.');
    }

    public function duplicate(NewsletterCampaign $campaign): RedirectResponse
    {
        $copy = $campaign->replicate([
            'status', 'scheduled_at', 'sending_started_at', 'sent_at', 'archived_at', 'recipient_count', 'sent_count', 'failed_count', 'created_by',
        ]);
        $copy->fill([
            'name' => Str::limit($campaign->name.' Copy', 160, ''),
            'status' => NewsletterCampaign::STATUS_DRAFT,
            'scheduled_at' => null,
            'sending_started_at' => null,
            'sent_at' => null,
            'archived_at' => null,
            'recipient_count' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'created_by' => request()->user()->id,
        ]);
        $copy->save();

        return to_route('admin.newsletter.campaigns.edit', $copy)->with('success', 'Campaign copied into a new draft.');
    }

    public function archive(NewsletterCampaign $campaign): RedirectResponse
    {
        if (! in_array($campaign->status, [NewsletterCampaign::STATUS_DRAFT, NewsletterCampaign::STATUS_SENT, NewsletterCampaign::STATUS_FAILED], true)) {
            return back()->withErrors(['campaign' => 'Only draft, sent, or failed campaigns can be archived.']);
        }

        $campaign->update(['status' => NewsletterCampaign::STATUS_ARCHIVED, 'archived_at' => now()]);

        return to_route('admin.newsletter.campaigns.show', $campaign)->with('success', 'Campaign archived.');
    }

    public function deliveries(NewsletterCampaign $campaign): View
    {
        return view('admin.newsletter.campaigns.deliveries', [
            'campaign' => $campaign,
            'deliveries' => $campaign->deliveries()->latest()->paginate(50)->withQueryString(),
        ]);
    }

    /** @return array<string, string> */
    private function audiences(): array
    {
        return [
            NewsletterCampaign::AUDIENCE_ALL_ACTIVE => 'All active subscribers',
            NewsletterCampaign::AUDIENCE_LAST_30_DAYS => 'Subscribed in the last 30 days',
            NewsletterCampaign::AUDIENCE_LAST_90_DAYS => 'Subscribed in the last 90 days',
        ];
    }

    /** @return array<int, string> */
    private function statuses(): array
    {
        return [
            NewsletterCampaign::STATUS_DRAFT,
            NewsletterCampaign::STATUS_SCHEDULED,
            NewsletterCampaign::STATUS_SENDING,
            NewsletterCampaign::STATUS_SENT,
            NewsletterCampaign::STATUS_FAILED,
            NewsletterCampaign::STATUS_ARCHIVED,
        ];
    }

    /** @return array<string, mixed> */
    private function campaignData(NewsletterCampaignRequest $request, CampaignContentSanitizer $sanitizer, ?NewsletterCampaign $campaign = null): array
    {
        $data = $request->safe()->except('hero_image');
        $data['content'] = $sanitizer->sanitize($data['content']);

        if ($request->hasFile('hero_image')) {
            if ($campaign?->hero_image_path) {
                Storage::disk('public')->delete($campaign->hero_image_path);
            }
            $data['hero_image_path'] = $request->file('hero_image')->store('newsletter-campaigns', 'public');
        }

        return $data;
    }
}

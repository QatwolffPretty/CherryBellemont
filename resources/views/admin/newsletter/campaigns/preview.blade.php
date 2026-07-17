<x-layouts.admin :title="$campaign->name.' preview | Cherry Bellemont'">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Client relationships" title="Campaign email preview" subtitle="This preview does not send email. The unsubscribe destination is shown as a safe placeholder.">
            <x-slot:actions><x-admin.button variant="outline" :href="route('admin.newsletter.campaigns.show', $campaign)">Back to campaign</x-admin.button></x-slot:actions>
        </x-admin.page-header>
        <x-admin.card class="mt-8 bg-cream p-0 text-wine">
            <iframe title="Campaign email preview" class="block min-h-[760px] w-full border-0" srcdoc="{!! e((new \App\Mail\NewsletterCampaignMail($campaign, $subscriber))->render()) !!}"></iframe>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>

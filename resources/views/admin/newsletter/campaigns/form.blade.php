<x-layouts.admin :title="($campaign->exists ? 'Edit' : 'Create').' newsletter campaign | Cherry Bellemont'">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Client relationships" :title="$campaign->exists ? 'Edit newsletter campaign' : 'Create newsletter campaign'" subtitle="Draft your message with safe, subscriber-ready content. Sending is always a separate confirmed step.">
            <x-slot:actions><x-admin.button variant="outline" :href="$campaign->exists ? route('admin.newsletter.campaigns.show', $campaign) : route('admin.newsletter.campaigns.index')">Back</x-admin.button></x-slot:actions>
        </x-admin.page-header>

        <x-admin.card class="mt-8">
            <form class="space-y-6" method="POST" enctype="multipart/form-data" action="{{ $campaign->exists ? route('admin.newsletter.campaigns.update', $campaign) : route('admin.newsletter.campaigns.store') }}">
                @csrf
                @if($campaign->exists)@method('PUT')@endif

                <div class="grid gap-6 md:grid-cols-2">
                    <x-admin.form-input name="name" label="Campaign Name" :value="$campaign->name" required />
                    <x-admin.form-input name="subject" label="Email Subject" :value="$campaign->subject" required />
                </div>
                <x-admin.form-input name="preview_text" label="Preview Text" :value="$campaign->preview_text" help="Optional inbox preview shown beside the subject line." />
                <x-admin.form-input name="hero_image" type="file" label="Hero Banner Image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" help="Optional JPG, JPEG, PNG, or WEBP image up to 5 MB." />
                @if($campaign->hero_image_path)
                    <img class="max-h-56 border border-cream/20 object-cover" src="{{ asset('storage/'.$campaign->hero_image_path) }}" alt="Current campaign banner">
                @endif
                <x-admin.textarea name="content" label="Campaign Content" :value="$campaign->content" rows="16" required help="Safe basic HTML is supported: headings, paragraphs, bold, italic, links, and bullet lists. Scripts, iframes, event handlers, and unsafe HTML are removed." />
                <div class="grid gap-6 md:grid-cols-2">
                    <x-admin.form-input name="cta_text" label="CTA Button Text" :value="$campaign->cta_text" />
                    <x-admin.form-input name="cta_url" label="CTA URL" type="url" :value="$campaign->cta_url" placeholder="https://example.com/collection" />
                </div>
                <div class="grid gap-6 md:grid-cols-2">
                    <x-admin.select name="audience_type" label="Audience" required>
                        @foreach($audiences as $value => $label)
                            <option value="{{ $value }}" @selected(old('audience_type', $campaign->audience_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-admin.select>
                    <x-admin.form-input name="scheduled_at" type="datetime-local" label="Suggested Schedule Date and Time" :value="$campaign->scheduled_at?->format('Y-m-d\TH:i')" help="Saving this date keeps the campaign as a draft. Use Schedule from the campaign page to confirm it." />
                </div>
                <x-admin.button type="submit" icon="bi-floppy">Save draft</x-admin.button>
            </form>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>

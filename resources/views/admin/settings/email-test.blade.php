<x-layouts.admin title="Email Test | Cherry Bellemont">
    <x-admin.section width="4xl">
        <x-admin.page-header eyebrow="Settings" title="Send Test Email" subtitle="In local development, Mailpit receives this test at 127.0.0.1:1025 and shows it at http://127.0.0.1:8025.">
            <x-slot:actions>
                <x-admin.button variant="outline" :href="route('admin.email-logs.index')" icon="bi bi-envelope-paper">Email Logs</x-admin.button>
                <x-admin.button variant="outline" :href="route('admin.settings.index')" icon="bi bi-arrow-left">Settings</x-admin.button>
            </x-slot:actions>
        </x-admin.page-header>

        @if(session('success'))<p class="mt-6 border border-gold/40 p-4 text-gold">{{ session('success') }}</p>@endif

        <x-admin.card class="mt-8" title="Mailpit-ready test">
            <p class="mt-4 text-sm leading-6 text-cream/70">This uses the configured Laravel mailer and the same Cherry Bellemont email layout. It does not send through a provider-specific API.</p>
            <form class="mt-6 space-y-5" method="POST" action="{{ route('admin.settings.email.test') }}">
                @csrf
                <x-admin.form-input name="recipient" type="email" label="Recipient Email" :value="old('recipient', auth()->user()->email)" required />
                <x-admin.form-input name="subject" label="Subject (optional)" :value="old('subject', 'Cherry Bellemont Mailpit Test')" />
                <x-admin.textarea name="message" label="Message (optional)" :value="old('message')" />
                <x-admin.button type="submit" icon="bi bi-send">Send Test Email</x-admin.button>
            </form>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>

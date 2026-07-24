<x-layouts.admin title="Settings">
    <div class="space-y-8 px-6 py-8 lg:px-10">
        <x-admin.page-header title="Store Settings" eyebrow="Configuration" subtitle="Manage ordinary public business settings. Credentials, API secrets, passwords, and application keys remain in the server environment.">
            <x-slot:actions>
                <a class="admin-button admin-button-secondary" href="{{ route('admin.settings.audit') }}"><i class="bi bi-clock-history"></i> Audit History</a>
                <form method="POST" action="{{ route('admin.settings.cache.clear') }}">@csrf <button class="admin-button admin-button-secondary" type="submit"><i class="bi bi-arrow-clockwise"></i> Clear Cache</button></form>
            </x-slot:actions>
        </x-admin.page-header>

        @if(session('success'))<div class="border border-gold/50 bg-cream/10 px-4 py-3 text-cream">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="border border-red-300/60 bg-red-950/20 px-4 py-3 text-cream">Please correct the highlighted settings.</div>@endif

        <p class="border-l-2 border-gold bg-wine-deep/30 px-4 py-3 text-sm text-cream/80">Stripe credentials and webhook secrets are managed securely through the server environment. They are intentionally unavailable here.</p>

        <nav class="flex flex-wrap gap-x-5 gap-y-3 border-y border-gold/30 py-4 text-sm" aria-label="Settings sections">
            @foreach($sections as $section)<a class="admin-link" href="#settings-{{ $section['id'] }}">{{ $section['label'] }}</a>@endforeach
        </nav>

        @foreach($sections as $section)
            <x-admin.card id="settings-{{ $section['id'] }}" :title="$section['label']" class="scroll-mt-8">
                @if(str_contains($section['groups'], 'payment'))
                    <p class="mb-6 text-sm text-cream/70">Public-facing labels and availability only. Do not disable both payment methods, and do not change methods for existing orders.</p>
                @elseif(str_contains($section['groups'], 'store'))
                    <p class="mb-6 text-sm text-cream/70">Currency remains MYR for current checkout. Historical order totals are never converted.</p>
                @elseif(str_contains($section['groups'], 'gift'))
                    <p class="mb-6 text-sm text-cream/70">The configured gift price applies only to future checkouts. Existing orders retain their stored wrapping fee.</p>
                @endif
                <form method="POST" action="{{ route('admin.settings.update') }}" class="grid gap-5 md:grid-cols-2">
                    @csrf @method('PATCH')
                    <input type="hidden" name="group" value="{{ $section['groups'] }}">
                    @foreach($section['settings'] as $setting)
                        @php($key = $setting['key'])
                        @php($definition = $setting['definition'])
                        @php($value = old('settings.'.$key, $setting['value']))
                        <div class="{{ $definition['type'] === 'text' ? 'md:col-span-2' : '' }}">
                            <label class="admin-label" for="setting-{{ str_replace('.', '-', $key) }}">{{ str($key)->after('.')->replace('_', ' ')->title() }}</label>
                            @if($definition['type'] === 'boolean')
                                <input type="hidden" name="settings[{{ $key }}]" value="0">
                                <label class="mt-3 inline-flex items-center gap-3 text-cream" for="setting-{{ str_replace('.', '-', $key) }}"><input id="setting-{{ str_replace('.', '-', $key) }}" type="checkbox" name="settings[{{ $key }}]" value="1" @checked((bool) $value)> Enabled</label>
                            @elseif($definition['type'] === 'text')
                                <textarea id="setting-{{ str_replace('.', '-', $key) }}" class="admin-field min-h-28" name="settings[{{ $key }}]">{{ $value }}</textarea>
                            @elseif($definition['type'] === 'image')
                                @if($value)<p class="mb-2 text-sm text-cream/70">Current file: {{ basename((string) $value) }}</p>@endif
                                <p class="admin-field flex min-h-11 items-center text-sm text-cream/60">Use the secure media uploader below to replace this file.</p>
                            @else
                                <input id="setting-{{ str_replace('.', '-', $key) }}" class="admin-field" name="settings[{{ $key }}]" type="{{ $definition['type'] === 'decimal' ? 'number' : ($definition['type'] === 'email' ? 'email' : ($definition['type'] === 'url' ? 'url' : ($definition['type'] === 'integer' ? 'number' : 'text'))) }}" @if($definition['type'] === 'decimal') step="0.01" @elseif($definition['type'] === 'integer') step="1" @endif value="{{ $value }}">
                            @endif
                            <p class="mt-2 text-xs text-cream/55">{{ $definition['description'] }}</p>
                            @error('settings.'.$key)<p class="mt-2 text-sm text-gold">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <div class="md:col-span-2"><button class="admin-button admin-button-primary" type="submit">Save {{ $section['label'] }}</button></div>
                </form>

                @if($section['settings']->contains(fn (array $setting) => $setting['definition']['type'] === 'image'))
                    <form method="POST" action="{{ route('admin.settings.media.upload') }}" enctype="multipart/form-data" class="mt-8 border-t border-gold/25 pt-6">
                        @csrf
                        <div class="grid gap-4 md:grid-cols-[1fr_2fr_auto] md:items-end">
                            <x-admin.select name="setting_key" label="Image setting">
                                @foreach($section['settings']->filter(fn (array $setting) => $setting['definition']['type'] === 'image') as $imageSetting)<option value="{{ $imageSetting['key'] }}">{{ str($imageSetting['key'])->after('.')->replace('_', ' ')->title() }}</option>@endforeach
                            </x-admin.select>
                            <div><label class="admin-label" for="settings-file-{{ $section['id'] }}">PNG, JPG, WEBP, or safe SVG (maximum 5 MB)</label><input id="settings-file-{{ $section['id'] }}" class="admin-field" type="file" name="file" accept=".png,.jpg,.jpeg,.webp,.svg" required>@error('file')<p class="mt-2 text-sm text-gold">{{ $message }}</p>@enderror</div>
                            <button class="admin-button admin-button-secondary" type="submit">Upload</button>
                        </div>
                    </form>
                @endif
            </x-admin.card>
        @endforeach
    </div>
</x-layouts.admin>

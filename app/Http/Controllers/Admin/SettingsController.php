<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Requests\UploadSettingMediaRequest;
use App\Models\SettingAuditLog;
use App\Services\SettingsService;
use App\Support\SettingsCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(SettingsService $settings, Request $request): View
    {
        $definitions = SettingsCatalog::definitions();
        $sections = collect(SettingsCatalog::groups())->map(function (string $label, string $group) use ($definitions, $settings): array {
            return [
                'group' => $group,
                'label' => $label,
                'settings' => collect($definitions)->filter(fn (array $_, string $key) => str_starts_with($key, $group.'.'))->map(fn (array $definition, string $key): array => ['key' => $key, 'definition' => $definition, 'value' => $settings->get($key)])->values(),
            ];
        })->groupBy('label')->map(fn ($items, $label) => [
            'label' => $label,
            'id' => $items->pluck('group')->implode('-'),
            'groups' => $items->pluck('group')->implode(','),
            'settings' => $items->pluck('settings')->flatten(1),
        ])->values();

        return view('admin.settings.index', compact('sections'));
    }

    public function update(UpdateSettingsRequest $request, SettingsService $settings): RedirectResponse
    {
        $values = $request->validated('settings');
        $projectedStripe = array_key_exists('payment.stripe_enabled', $values) ? (bool) $values['payment.stripe_enabled'] : (bool) $settings->get('payment.stripe_enabled');
        $projectedDuitNow = array_key_exists('payment.duitnow_enabled', $values) ? (bool) $values['payment.duitnow_enabled'] : (bool) $settings->get('payment.duitnow_enabled');
        if (! $projectedStripe && ! $projectedDuitNow) {
            return back()->withErrors(['settings' => 'At least one public payment method must remain enabled.'])->withInput();
        }

        foreach ($values as $key => $value) {
            $settings->set($key, $value, $request->user()->id, $request->ip());
        }

        return back()->with('success', 'Settings have been saved.');
    }

    public function uploadMedia(UploadSettingMediaRequest $request, SettingsService $settings): RedirectResponse
    {
        $file = $request->file('file');
        if ($file->getClientOriginalExtension() === 'svg' && ! $this->safeSvg((string) file_get_contents($file->getRealPath()))) {
            return back()->withErrors(['file' => 'The SVG contains unsupported executable content.']);
        }

        $path = $file->store('settings', 'public');
        $settings->set($request->string('setting_key')->toString(), $path, $request->user()->id, $request->ip());

        return back()->with('success', 'Branding media has been uploaded.');
    }

    public function audit(Request $request): View
    {
        $request->validate(['group' => ['nullable', 'string', 'max:60'], 'key' => ['nullable', 'string', 'max:100'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $logs = SettingAuditLog::query()->with('changer:id,name,email')
            ->when($request->filled('group'), fn ($query) => $query->where('group', $request->string('group')->toString()))
            ->when($request->filled('key'), fn ($query) => $query->where('key', 'like', '%'.$request->string('key')->toString().'%'))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('to')))
            ->latest('created_at')->paginate(30)->withQueryString();

        return view('admin.settings.audit', compact('logs'));
    }

    public function clearCache(SettingsService $settings): RedirectResponse
    {
        $settings->forgetCache();
        return back()->with('success', 'Settings cache cleared.');
    }

    private function safeSvg(string $contents): bool
    {
        return ! preg_match('/<(script|iframe|object|embed)|on[a-z]+\s*=|javascript:/i', $contents);
    }
}

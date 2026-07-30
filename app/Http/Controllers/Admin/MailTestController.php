<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMailTestRequest;
use App\Models\SettingAuditLog;
use App\Notifications\AdminMailTestNotification;
use App\Services\OrderEmailLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MailTestController extends Controller
{
    public function create()
    {
        return view('admin.settings.email-test');
    }

    public function store(SendMailTestRequest $request, OrderEmailLogService $logs): RedirectResponse
    {
        $data = $request->validated();
        $subject = trim((string) ($data['subject'] ?? 'Cherry Bellemont Mailpit Test')) ?: 'Cherry Bellemont Mailpit Test';
        $log = $logs->prepare(null, 'mail_test', $data['recipient'], ['source' => 'admin_settings'], true, $request->user()->id);

        try {
            Notification::route('mail', $data['recipient'])->notify(new AdminMailTestNotification($subject, $data['message'] ?? null, $log?->id));
        } catch (Throwable $exception) {
            $logs->markFailed($log?->id, $exception);
            Log::warning('Administrative mail test could not be queued.', ['exception_class' => $exception::class]);

            return back()->withErrors(['recipient' => 'The test email could not be queued. Check the email logs for a safe error summary.'])->withInput();
        }

        if (Schema::hasTable('settings_audit_logs')) {
            SettingAuditLog::query()->create([
                'group' => 'email',
                'key' => 'test_email_sent',
                'new_value' => json_encode(['recipient_hash' => hash('sha256', strtolower($data['recipient'])), 'subject' => $subject]),
                'changed_by' => $request->user()->id,
                'ip_hash' => hash('sha256', (string) $request->ip()),
                'created_at' => now(),
            ]);
        }

        return back()->with('success', 'Test email queued. In local development, open Mailpit at http://127.0.0.1:8025.');
    }
}

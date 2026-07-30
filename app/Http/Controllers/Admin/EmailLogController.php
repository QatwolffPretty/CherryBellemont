<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderNotificationLog;
use App\Services\OrderNotifier;
use App\Services\ReturnNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailLogController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'recipient' => ['nullable', 'string', 'max:254'],
            'notification_type' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'in:queued,sent,failed'],
            'order_number' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $logs = OrderNotificationLog::query()->with(['order:id,order_number,number', 'returnRequest:id,return_number', 'resentBy:id,name'])
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('to')))
            ->when($request->filled('recipient'), fn ($query) => $query->where('recipient', 'like', '%'.$request->string('recipient')->trim()->value().'%'))
            ->when($request->filled('notification_type'), fn ($query) => $query->where('notification_type', $request->string('notification_type')->trim()->value()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('order_number'), fn ($query) => $query->whereHas('order', fn ($orders) => $orders->where('order_number', 'like', '%'.$request->string('order_number')->trim()->value().'%')->orWhere('number', 'like', '%'.$request->string('order_number')->trim()->value().'%')))
            ->when($request->filled('search'), fn ($query) => $query->search($request->string('search')->trim()->value()))
            ->latest('created_at')->paginate(30)->withQueryString();

        return view('admin.email-logs.index', compact('logs'));
    }

    public function show(OrderNotificationLog $emailLog): View
    {
        return view('admin.email-logs.show', ['emailLog' => $emailLog->load(['order', 'returnRequest', 'resentBy'])]);
    }

    public function resend(OrderNotificationLog $emailLog, Request $request, OrderNotifier $orders, ReturnNotifier $returns): RedirectResponse
    {
        $emailLog->loadMissing(['order', 'returnRequest.refunds']);

        if ($emailLog->returnRequest && in_array($emailLog->notification_type, ['refund_processing', 'refund_succeeded', 'refund_failed'], true)) {
            $returns->customer($emailLog->returnRequest, $emailLog->notification_type, true, $request->user()->id);
        } elseif ($emailLog->order && in_array($emailLog->notification_type, ['order_placed', 'receipt_submitted', 'payment_approved', 'receipt_rejected', 'status_updated', 'shipment_updated'], true)) {
            $orders->send($emailLog->order, $emailLog->notification_type, $emailLog->metadata ?? [], true, $request->user()->id);
        } else {
            return back()->withErrors(['email' => 'This email log does not have a customer-safe resend action.']);
        }

        return back()->with('success', 'Email resend queued and recorded as a manual resend.');
    }
}

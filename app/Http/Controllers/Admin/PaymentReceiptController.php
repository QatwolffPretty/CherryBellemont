<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentReceipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use App\Services\OrderNotifier;
use App\Services\AdminNotificationService;

class PaymentReceiptController extends Controller
{
    public function index(Request $request): View { $q=PaymentReceipt::with('order.user')->whereHas('order', fn ($order) => $order->where('payment_method', 'duitnow'))->orderByRaw("status = 'pending' desc")->latest(); if(in_array($request->status,['pending','approved','rejected']))$q->where('status',$request->status); return view('admin.receipts.index', ['receipts'=>$q->paginate(20)]); }
    public function show(PaymentReceipt $paymentReceipt): View { return view('admin.receipts.show',['receipt'=>$paymentReceipt->load('order.items','reviewer')]); }
    public function approve(Request $request, PaymentReceipt $paymentReceipt, OrderNotifier $notifier, AdminNotificationService $adminNotifier): RedirectResponse { [$order, $receipt] = DB::transaction(function()use($paymentReceipt,$request){$r=PaymentReceipt::lockForUpdate()->findOrFail($paymentReceipt->id);$o=$r->order()->lockForUpdate()->firstOrFail();abort_unless($r->status==='pending' && $o->payment_status!=='paid',409);$r->update(['status'=>'approved','reviewed_by'=>$request->user()->id,'reviewed_at'=>now()]);$o->update(['payment_status'=>'paid']);return [$o, $r];});$notifier->send($order,'payment_approved');$adminNotifier->send('duitnow_payment_approved',['order'=>$order,'reviewerName'=>$request->user()->name,'approvedAt'=>$receipt->reviewed_at]);return back()->with('success','Receipt approved and payment marked paid.'); }
    public function reject(Request $request, PaymentReceipt $paymentReceipt, OrderNotifier $notifier): RedirectResponse { $data=$request->validate(['rejection_reason'=>['required','string','max:2000']]);$order=DB::transaction(function()use($paymentReceipt,$request,$data){$r=PaymentReceipt::lockForUpdate()->findOrFail($paymentReceipt->id);$o=$r->order()->lockForUpdate()->firstOrFail();abort_unless($r->status==='pending',409);$r->update(['status'=>'rejected','rejection_reason'=>$data['rejection_reason'],'reviewed_by'=>$request->user()->id,'reviewed_at'=>now()]);return $o;});$notifier->send($order,'receipt_rejected',['reason'=>$data['rejection_reason']]);return back()->with('success','Receipt rejected; the customer may submit a replacement.'); }
    public function download(PaymentReceipt $paymentReceipt) { abort_unless(Storage::disk('public')->exists($paymentReceipt->path),404); return Storage::disk('public')->download($paymentReceipt->path,$paymentReceipt->original_filename ?: 'receipt'); }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminReturnActionRequest;
use App\Jobs\ProcessStripeRefund;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestImage;
use App\Services\RefundCalculator;
use App\Services\RefundService;
use App\Services\ReturnWorkflowService;
use App\Services\ProductStockNotificationService;
use App\Services\ReturnNotifier;
use App\Services\OrderDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReturnRequestController extends Controller
{
    public function index(Request $request): View
    {
        $query = ReturnRequest::query()->with('order')->latest('requested_at');
        if ($search = $request->string('search')->trim()->value()) $query->where(fn ($q) => $q->where('return_number', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%")->orWhere('customer_email', 'like', "%{$search}%")->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%"))->orWhereHas('items', fn ($i) => $i->where('product_name', 'like', "%{$search}%")));
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('provider')) $query->whereHas('order', fn ($q) => $q->where('payment_provider', $request->provider));
        return view('admin.returns.index', ['returns' => $query->paginate(20)->withQueryString()]);
    }
    public function show(ReturnRequest $return): View { return view('admin.returns.show', ['returnRequest' => $return->load('order.items.product', 'items.product', 'images', 'refunds.processor', 'events', 'reviewer')]); }
    public function beginReview(ReturnRequest $return, ReturnWorkflowService $workflow): RedirectResponse { $workflow->transition($return, 'under_review', request()->user(), 'Review started.'); return back()->with('success', 'Return request is under review.'); }
    public function approve(AdminReturnActionRequest $request, ReturnRequest $return, ReturnWorkflowService $workflow, ReturnNotifier $notifier): RedirectResponse { $data = $request->validated(); foreach ($return->items as $item) { $quantity = (int) ($data['items'][$item->id]['approved_quantity'] ?? 0); if ($quantity < 1 || $quantity > $item->requested_quantity) throw ValidationException::withMessages(['items' => 'Approved quantities must not exceed requested quantities.']); $item->update(['approved_quantity' => $quantity]); } $return = $workflow->transition($return, 'approved', $request->user(), $data['reason'] ?? null, ['admin_decision_reason' => $data['reason'] ?? null]); $notifier->customer($return, 'approved'); return back()->with('success', 'Return request approved.'); }
    public function reject(AdminReturnActionRequest $request, ReturnRequest $return, ReturnWorkflowService $workflow, ReturnNotifier $notifier): RedirectResponse { $data=$request->validated(); if (blank($data['reason'] ?? null)) throw ValidationException::withMessages(['reason'=>'A rejection reason is required.']); $return = $workflow->transition($return,'rejected',$request->user(),$data['reason'],['admin_decision_reason'=>$data['reason']]); $notifier->customer($return, 'rejected'); return back()->with('success','Return request rejected.'); }
    public function instructions(AdminReturnActionRequest $request, ReturnRequest $return, ReturnWorkflowService $workflow, ReturnNotifier $notifier): RedirectResponse { $data=$request->validated(); if (blank($data['return_instructions'] ?? null)) throw ValidationException::withMessages(['return_instructions'=>'Return instructions are required.']); $return = $workflow->transition($return,'awaiting_return',$request->user(),$data['return_instructions'],['return_instructions'=>$data['return_instructions']]); $notifier->customer($return, 'instructions'); return back()->with('success','Return instructions issued.'); }
    public function received(ReturnRequest $return, ReturnWorkflowService $workflow, ReturnNotifier $notifier): RedirectResponse { $return = $workflow->transition($return,'item_received',request()->user(),'Returned item received.'); $notifier->customer($return, 'item_received'); return back()->with('success','Returned item receipt recorded.'); }
    public function inspect(ReturnRequest $return, ReturnWorkflowService $workflow): RedirectResponse { $workflow->transition($return,'inspecting',request()->user(),'Inspection started.'); return back()->with('success','Inspection started.'); }
    public function finishInspection(AdminReturnActionRequest $request, ReturnRequest $return, ReturnWorkflowService $workflow, ProductStockNotificationService $stockNotifications, ReturnNotifier $notifier): RedirectResponse { $data=$request->validated(); $passed=$request->boolean('passed'); [$return,$restocked]=$workflow->inspect($return,$request->user(),$data['items']??[],$passed,$data['reason']??'Inspection completed.'); foreach($restocked as [$id,$previous]) if($product=\App\Models\Product::find($id)) $stockNotifications->handleStockChange($product,$previous); if (! $passed) $notifier->customer($return, 'inspection_failed'); return back()->with('success',$passed?'Inspection passed; resolution is pending.':'Inspection failed.'); }
    public function refund(AdminReturnActionRequest $request, ReturnRequest $return, RefundCalculator $calculator, ReturnNotifier $notifier): RedirectResponse
    {
        abort_unless($return->status === 'resolution_pending', 422); $data=$request->validated(); $order=$return->order->load('items','refunds');
        $result=$calculator->calculate($return->load('items','order.items','order.refunds'),(int)round(((float)($data['shipping_refund_amount']??0))*100),(int)round(((float)($data['gift_wrap_refund_amount']??0))*100));
        $refund=DB::transaction(function()use($return,$order,$data,$result,$request){ if(Refund::query()->where('return_request_id',$return->id)->whereIn('status',['pending','processing'])->exists()) throw ValidationException::withMessages(['refund'=>'A refund is already being processed.']); $provider=$order->payment_provider?:$order->payment_method; $refund=Refund::create(['refund_number'=>$this->refundNumber(),'return_request_id'=>$return->id,'order_id'=>$order->id,'payment_provider'=>$provider,'refund_type'=>$data['refund_type']??($result['total_amount']===$result['remaining_amount']?'full':'partial'),'status'=>$provider==='stripe'?'processing':'pending','amount'=>number_format($result['total_amount']/100,2,'.',''),'shipping_refund_amount'=>number_format($result['shipping_amount']/100,2,'.',''),'gift_wrap_refund_amount'=>number_format($result['gift_wrap_amount']/100,2,'.',''),'reason'=>$data['reason']??'Approved return resolution.','stripe_payment_intent_id'=>$order->stripe_payment_intent_id,'requested_at'=>now(),'processed_by'=>$request->user()->id]); $order->update(['refund_status'=>$provider==='stripe'?'processing':'pending']); app(ReturnWorkflowService::class)->event($return,$request->user(),'refund_requested',null,null,$refund->reason,['refund_number'=>$refund->refund_number,'amount'=>$refund->amount]); return $refund; });
        if($refund->payment_provider==='stripe') ProcessStripeRefund::dispatch($refund->id); $notifier->customer($return->fresh(['refunds']), 'refund_processing'); return back()->with('success',$refund->payment_provider==='stripe'?'Stripe refund submitted for provider confirmation.':'Manual DuitNow refund is awaiting transfer confirmation.');
    }
    public function confirmManualRefund(AdminReturnActionRequest $request, Refund $refund, RefundService $refunds, ReturnNotifier $notifier): RedirectResponse { $data=$request->validated(); abort_unless($refund->payment_provider==='duitnow' && $refund->status==='pending',422); if(blank($data['manual_reference']??null)) throw ValidationException::withMessages(['manual_reference'=>'A bank transfer reference is required.']); if (! $request->hasFile('manual_proof')) throw ValidationException::withMessages(['manual_proof'=>'A private transfer proof is required before confirming a manual refund.']); $data['manual_proof_path']=$request->file('manual_proof')->store('refund-proofs','local'); $refund->update(['manual_reference'=>$data['manual_reference'],'manual_proof_path'=>$data['manual_proof_path'],'processed_at'=>now(),'processed_by'=>$request->user()->id]); $refund = $refunds->confirm($refund); if ($refund->returnRequest) $notifier->customer($refund->returnRequest, 'refund_succeeded'); return back()->with('success','Manual DuitNow refund confirmed.'); }
    public function exchange(AdminReturnActionRequest $request, ReturnRequest $return, ReturnWorkflowService $workflow, ReturnNotifier $notifier): RedirectResponse { $data=$request->validated(); abort_unless($return->status==='resolution_pending',422); if(blank($data['replacement_details']??null)) throw ValidationException::withMessages(['replacement_details'=>'Replacement details are required.']); $return = $workflow->transition($return,'completed',$request->user(),'Exchange approved.', ['exchange_details'=>['details'=>$data['replacement_details'],'approved_at'=>now()->toIso8601String()]]); $workflow->event($return,$request->user(),'exchange_approved',null,null,$data['replacement_details']); $notifier->customer($return, 'exchange_approved'); return back()->with('success','Exchange approved and recorded. Create any replacement fulfilment manually from the original order.'); }
    public function close(ReturnRequest $return, ReturnWorkflowService $workflow, ReturnNotifier $notifier): RedirectResponse { $return = $workflow->transition($return,'closed',request()->user(),'Request closed.'); $notifier->customer($return, 'closed'); return back()->with('success','Return request closed.'); }
    public function downloadImage(ReturnRequestImage $image) { $disk=Storage::disk('local'); abort_unless($disk->exists($image->image_path),404); return $disk->download($image->image_path,'return-evidence-'.$image->id.'.'.pathinfo($image->image_path,PATHINFO_EXTENSION)); }
    public function downloadProof(Refund $refund) { abort_unless($refund->manual_proof_path,404); $disk=Storage::disk('local'); abort_unless($disk->exists($refund->manual_proof_path),404); return $disk->download($refund->manual_proof_path,'manual-refund-proof-'.$refund->refund_number.'.'.pathinfo($refund->manual_proof_path,PATHINFO_EXTENSION)); }
    public function creditNote(Refund $refund, OrderDocumentService $documents) { abort_unless($refund->status === 'succeeded', 404); return $documents->creditNote($refund)->download('credit-note-'.$refund->refund_number.'.pdf'); }
    private function refundNumber(): string { do{$number='RFD-CB-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));}while(Refund::where('refund_number',$number)->exists());return $number; }
}

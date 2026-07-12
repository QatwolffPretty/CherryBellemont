<?php
namespace App\Http\Controllers;
use App\Services\ShippingCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class ShippingQuoteController extends Controller { public function __invoke(Request $request, ShippingCalculator $calculator): JsonResponse { $data=$request->validate(['state'=>['nullable','string','max:120'],'city'=>['nullable','string','max:120'],'postcode'=>['nullable','string','max:30'],'delivery_method_id'=>['required','integer']]); return response()->json($calculator->calculate($data['state']??null,$data['city']??null,$data['postcode']??null,(int)$data['delivery_method_id'])); } }

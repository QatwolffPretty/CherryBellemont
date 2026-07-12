<?php
namespace App\Services;
use App\Models\DeliveryMethod;
use App\Models\ShippingZone;
use Illuminate\Validation\ValidationException;

class ShippingCalculator
{
    public function calculate(?string $state, ?string $city, ?string $postcode, int $deliveryMethodId): array
    {
        $method=DeliveryMethod::query()->whereKey($deliveryMethodId)->where('is_active',true)->first();
        if (! $method) throw ValidationException::withMessages(['delivery_method_id'=>'This delivery method is unavailable.']);
        if ($method->is_pickup) return ['shipping_zone_id'=>null,'delivery_method_id'=>$method->id,'shipping_fee'=>'0.00','display_label'=>$method->name,'pickup_location'=>'Cherry Bellemont Atelier, Kuala Lumpur'];
        if (! $state || ! $city || ! $postcode) throw ValidationException::withMessages(['shipping'=>'Enter your state, city or area, and postcode to calculate delivery.']);
        $state=strtolower(trim($state)); $city=strtolower(trim($city)); $postcode=trim($postcode);
        $zone=ShippingZone::query()->where('is_active',true)->whereRaw('LOWER(state) = ?',[$state])->get()->filter(function($zone) use($city,$postcode){
            $cityMatch=!$zone->city_or_area || strtolower(trim($zone->city_or_area))===$city;
            $postcodeMatch=(!$zone->postcode_from && !$zone->postcode_to) || (($zone->postcode_from===null || $postcode >= $zone->postcode_from) && ($zone->postcode_to===null || $postcode <= $zone->postcode_to));
            return $cityMatch && $postcodeMatch;
        })->sortByDesc(fn($zone)=>(($zone->city_or_area?10:0)+(($zone->postcode_from||$zone->postcode_to)?5:0))*1000-$zone->sort_order)->first();
        if (! $zone) throw ValidationException::withMessages(['shipping'=>'Delivery is not currently available for this area.']);
        $fee=(float)$zone->base_fee + (float)$method->additional_fee;
        return ['shipping_zone_id'=>$zone->id,'delivery_method_id'=>$method->id,'shipping_fee'=>number_format($fee,2,'.',''),'display_label'=>$method->name.($method->estimated_days ? ' · '.$method->estimated_days.' day'.($method->estimated_days===1?'':'s') : ''),'pickup_location'=>null];
    }
}

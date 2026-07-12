<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ShippingZone extends Model { protected $fillable=['name','state','city_or_area','postcode_from','postcode_to','base_fee','is_active','sort_order']; protected $casts=['base_fee'=>'decimal:2','is_active'=>'boolean','sort_order'=>'integer']; }

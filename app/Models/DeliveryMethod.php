<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DeliveryMethod extends Model { protected $fillable=['name','code','description','additional_fee','estimated_days','is_pickup','is_active','sort_order']; protected $casts=['additional_fee'=>'decimal:2','estimated_days'=>'integer','is_pickup'=>'boolean','is_active'=>'boolean','sort_order'=>'integer']; }

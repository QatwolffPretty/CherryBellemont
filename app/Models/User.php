<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_admin' => 'boolean']; }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
    public function reviewedReturnRequests(): HasMany { return $this->hasMany(ReturnRequest::class, 'reviewed_by'); }
    public function processedRefunds(): HasMany { return $this->hasMany(Refund::class, 'processed_by'); }
}

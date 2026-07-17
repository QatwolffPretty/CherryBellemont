<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsletterCampaign extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ARCHIVED = 'archived';

    public const AUDIENCE_ALL_ACTIVE = 'all_active';
    public const AUDIENCE_LAST_30_DAYS = 'subscribed_last_30_days';
    public const AUDIENCE_LAST_90_DAYS = 'subscribed_last_90_days';

    protected $fillable = [
        'name', 'subject', 'preview_text', 'content', 'hero_image_path', 'cta_text', 'cta_url',
        'status', 'audience_type', 'scheduled_at', 'sending_started_at', 'sent_at', 'archived_at',
        'recipient_count', 'sent_count', 'failed_count', 'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sending_started_at' => 'datetime',
        'sent_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NewsletterCampaignDelivery::class);
    }

    public function scopeDrafts(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }
}

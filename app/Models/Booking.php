<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $scheduled_at
 * @property string $status
 * @property string $email
 */
class Booking extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'booking_page_id', 'contact_id', 'owner_id', 'name', 'email',
        'scheduled_at', 'status', 'source', 'notes', 'ics_token', 'answers', 'utm',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'answers' => 'array', 'utm' => 'array'];
    }

    /**
     * @return BelongsTo<BookingPage, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(BookingPage::class, 'booking_page_id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}

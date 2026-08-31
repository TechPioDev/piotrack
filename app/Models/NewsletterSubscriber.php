<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A product-homepage newsletter signup. Deliberately NOT tenant-scoped:
 * this list belongs to the platform, not to any organization.
 */
class NewsletterSubscriber extends Model
{
    protected $fillable = ['email', 'source'];
}

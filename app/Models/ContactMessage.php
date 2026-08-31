<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A message from the public contact page (MSITE). Platform-level — deliberately
 * NOT tenant-scoped: senders are prospects who have no organization yet.
 */
class ContactMessage extends Model
{
    protected $fillable = [
        'name',
        'email',
        'company',
        'message',
        'source',
    ];
}

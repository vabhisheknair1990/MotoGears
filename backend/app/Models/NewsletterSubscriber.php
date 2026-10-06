<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    protected $attributes = ['status' => 'subscribed'];

    protected $fillable = ['email', 'status', 'source'];
}

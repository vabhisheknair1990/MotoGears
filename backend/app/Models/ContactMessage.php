<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $attributes = ['status' => 'new'];

    protected $fillable = ['name', 'email', 'phone', 'subject', 'message', 'status', 'admin_notes', 'ip_address'];
}

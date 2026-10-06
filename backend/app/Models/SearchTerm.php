<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchTerm extends Model
{
    protected $fillable = ['term', 'hits', 'results'];
}

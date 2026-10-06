<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponses;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use ApiResponses, AuthorizesRequests;
}

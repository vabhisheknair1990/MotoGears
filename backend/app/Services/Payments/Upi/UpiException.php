<?php

namespace App\Services\Payments\Upi;

use RuntimeException;

/** The UPI provider refused the request or could not be reached. */
class UpiException extends RuntimeException {}

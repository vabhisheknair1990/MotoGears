<?php

namespace App\Services\Payments\Razorpay;

use RuntimeException;

/** Razorpay API could not be reached or rejected the request. Message is safe to log, not to show verbatim. */
class RazorpayException extends RuntimeException {}

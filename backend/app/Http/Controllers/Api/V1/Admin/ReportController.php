<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function sales(Request $request): JsonResponse
    {
        return $this->report($request, 'sales');
    }

    public function products(Request $request): JsonResponse
    {
        return $this->report($request, 'products');
    }

    public function customers(Request $request): JsonResponse
    {
        return $this->report($request, 'customers');
    }

    public function inventory(Request $request): JsonResponse
    {
        return $this->report($request, 'inventory');
    }

    private function report(Request $request, string $type): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', Rule::in(ReportService::PERIODS)],
            'from' => ['nullable', 'required_if:period,custom', 'date'],
            'to' => ['nullable', 'required_if:period,custom', 'date', 'after_or_equal:from'],
        ]);
        [$from, $to, $period] = $this->reports->range($request->period, $request->from, $request->to);
        if ($from->diffInDays($to) > 731) {
            return response()->json(['success' => false, 'message' => 'Please choose a range of two years or less.'], 422);
        }

        return $this->ok($this->reports->{$type}($from, $to), ucfirst($type).' report retrieved', [
            'period' => $period, 'from' => $from->toDateString(), 'to' => $to->toDateString(),
        ]);
    }
}

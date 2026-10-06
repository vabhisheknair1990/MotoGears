<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsletterController extends SimpleCrudController
{
    protected string $model = NewsletterSubscriber::class;

    protected string $label = 'Subscriber';

    protected array $searchable = ['email'];

    protected function rules(?Model $record): array
    {
        return [
            'email' => [$record ? 'sometimes' : 'required', 'email', 'max:190', Rule::unique('newsletter_subscribers', 'email')->ignore($record?->id)],
            'status' => ['sometimes', 'in:subscribed,unsubscribed'],
            'source' => ['nullable', 'string', 'max:40'],
        ];
    }

    protected function filter($query, Request $request): void
    {
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'status', 'source', 'subscribed_at']);
            NewsletterSubscriber::orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [$r->email, $r->status, $r->source, $r->created_at?->toDateTimeString()]);
                }
            });
            fclose($out);
        }, 'newsletter-subscribers-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }
}

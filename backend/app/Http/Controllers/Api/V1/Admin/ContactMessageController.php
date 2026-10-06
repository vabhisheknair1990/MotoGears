<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactMessageController extends SimpleCrudController
{
    protected string $model = ContactMessage::class;

    protected string $label = 'Enquiry';

    protected array $searchable = ['name', 'email', 'subject', 'message'];

    protected function rules(?Model $record): array
    {
        return [
            'status' => ['sometimes', 'in:new,read,replied,closed'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function filter($query, Request $request): void
    {
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
    }

    public function store(Request $request): JsonResponse
    {
        abort(405, 'Enquiries are created from the storefront contact form.');
    }

    public function show(int $id): JsonResponse
    {
        $msg = ContactMessage::findOrFail($id);
        if ($msg->status === 'new') {
            $msg->update(['status' => 'read']);
        }

        return parent::show($id);
    }
}

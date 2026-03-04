<?php

declare(strict_types=1);

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:65535'],
            'is_internal' => ['nullable', 'boolean'],
            'close_with_comment' => ['nullable', 'boolean'],
            'also_update_status' => ['nullable', 'string', 'in:new,open,pending,hold,solved,closed'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:51200'],
        ];
    }
}

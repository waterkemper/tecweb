<?php

declare(strict_types=1);

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:65535'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'due_at' => ['nullable', 'date', 'after_or_equal:today'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:51200'],
        ];
    }

    public function messages(): array
    {
        return [
            'due_at.after_or_equal' => 'O prazo de entrega não pode ser anterior à data de criação do ticket.',
        ];
    }
}

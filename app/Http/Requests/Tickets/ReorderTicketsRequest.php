<?php

declare(strict_types=1);

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class ReorderTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'colaborador'], true);
    }

    public function rules(): array
    {
        return [
            'ticket_ids' => ['required', 'array', 'min:1'],
            'ticket_ids.*' => ['integer'],
        ];
    }
}

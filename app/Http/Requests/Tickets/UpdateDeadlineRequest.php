<?php

declare(strict_types=1);

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeadlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'colaborador'], true);
    }

    public function rules(): array
    {
        $ticket = $this->route('ticket');
        $minDate = ($ticket?->zd_created_at ?? $ticket?->created_at)?->format('Y-m-d') ?? now()->format('Y-m-d');

        return [
            'due_at' => ['nullable', 'date', 'after_or_equal:' . $minDate],
            'clear' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'due_at.after_or_equal' => 'O prazo de entrega não pode ser anterior à data de criação do ticket.',
        ];
    }
}

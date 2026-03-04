<?php

declare(strict_types=1);

namespace App\Http\Requests\Tickets;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'colaborador'], true);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:new,open,pending,hold,solved,closed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function () use ($validator) {
            $ticket = $this->route('ticket');
            $newStatus = $this->input('status');
            $currentStatus = $ticket?->status ?? '';

            if ($newStatus === 'closed' && $currentStatus !== 'solved') {
                $validator->errors()->add(
                    'status',
                    'Para fechar o ticket, marque-o primeiro como "Resolvido". Tickets fechados não podem ser reabertos.'
                );
            }
        });
    }
}

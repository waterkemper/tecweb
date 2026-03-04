<?php

declare(strict_types=1);

namespace App\Http\Requests\Tickets;

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
}

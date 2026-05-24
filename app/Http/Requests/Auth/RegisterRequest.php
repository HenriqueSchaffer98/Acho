<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\Tenant;
use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->where('tenant_id', $tenant->id)->whereNull('deleted_at'),
            ],
            'password' => ['required', 'confirmed', new StrongPassword],
            'terms' => ['required', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'Já existe uma conta com este e-mail neste tenant.',
            'terms.accepted' => 'Você precisa aceitar os termos de uso para se cadastrar.',
        ];
    }
}

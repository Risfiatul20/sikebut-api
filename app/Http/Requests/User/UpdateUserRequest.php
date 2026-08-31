<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User|string|int $user */
        $user = $this->route('user');
        $userId = is_object($user) ? $user->id : $user;

        return [
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('pgsql.dev.users', 'username')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:6', 'max:255'],
            'role' => ['sometimes', 'required', 'string', 'max:50'],
            'kode_skpd' => ['nullable', 'string', 'max:50', Rule::exists('pgsql.dev.ref_skpd', 'kode_skpd')],
            'info' => ['nullable', 'array'],
            'info.nip' => ['nullable', 'string', 'max:50'],
            'info.pangkat' => ['nullable', 'string', 'max:100'],
            'info.golongan' => ['nullable', 'string', 'max:50'],
            'info.jabatan' => ['nullable', 'string', 'max:255'],
            'info.no_hp' => ['nullable', 'string', 'max:50'],
            'info.email_dinas' => ['nullable', 'email', 'max:255'],
            'sub_kegiatan_ids' => ['nullable', 'array'],
            'sub_kegiatan_ids.*' => ['string', Rule::exists('pgsql.dev.ref_sub_kegiatan', 'kode_sub_kegiatan')],
        ];
    }
}

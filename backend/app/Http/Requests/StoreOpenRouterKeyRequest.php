<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreOpenRouterKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'apiKey' => ['required', 'string', 'min:20', 'max:256', 'regex:/^sk-[a-zA-Z0-9\-_]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'apiKey.required' => 'API key is required.',
            'apiKey.min' => 'API key is too short.',
            'apiKey.max' => 'API key is too long.',
            'apiKey.regex' => 'API key format is invalid. Expected format: sk-...',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json(['errors' => $validator->errors()], 422)
        );
    }
}

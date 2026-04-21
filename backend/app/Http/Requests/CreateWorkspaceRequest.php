<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'initialPrompt' => [
                'required',
                'string',
                'min:1',
                'max:100000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'initialPrompt.required' => 'Initial prompt is required.',
            'initialPrompt.min' => 'Initial prompt cannot be empty.',
            'initialPrompt.max' => 'Initial prompt exceeds the maximum allowed length.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json(['errors' => $validator->errors()], 422)
        );
    }
}

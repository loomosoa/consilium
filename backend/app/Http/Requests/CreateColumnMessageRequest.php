<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateColumnMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => [
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
            'prompt.required' => 'Prompt is required.',
            'prompt.min' => 'Prompt cannot be empty.',
            'prompt.max' => 'Prompt exceeds the maximum allowed length.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json(['errors' => $validator->errors()], 422)
        );
    }
}

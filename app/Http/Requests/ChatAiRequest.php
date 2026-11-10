<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChatAiRequest extends FormRequest
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
        return [
            'prompt' => ['required', 'string', 'max:1000'],
            'session_id' => [
                'nullable',
                'integer',
                // Someone else's session fails exactly like a missing one, so its existence is not revealed.
                Rule::exists('chat_sessions', 'id')->where('user_id', $this->user()->id),
            ],
        ];
    }
}

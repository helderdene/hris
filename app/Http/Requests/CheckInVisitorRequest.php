<?php

namespace App\Http\Requests;

use App\Enums\VisitStatus;
use App\Models\VisitorVisit;
use Illuminate\Foundation\Http\FormRequest;

class CheckInVisitorRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'badge_number' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $visit = $this->route('visit');

            if ($visit instanceof VisitorVisit && ! in_array($visit->status, [VisitStatus::Approved, VisitStatus::PreRegistered])) {
                $validator->errors()->add('status', 'Only approved or pre-registered visits can be checked in.');
            }
        });
    }
}

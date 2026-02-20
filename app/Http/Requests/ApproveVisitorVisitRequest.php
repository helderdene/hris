<?php

namespace App\Http\Requests;

use App\Enums\VisitStatus;
use App\Models\VisitorVisit;
use Illuminate\Foundation\Http\FormRequest;

class ApproveVisitorVisitRequest extends FormRequest
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
        return [];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $visit = $this->route('visit');

            if ($visit instanceof VisitorVisit && $visit->status !== VisitStatus::PendingApproval) {
                $validator->errors()->add('status', 'Only visits with Pending Approval status can be approved.');
            }
        });
    }
}

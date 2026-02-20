<?php

namespace App\Http\Requests;

use App\Services\HtmlSanitizerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHelpArticleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $articleId = $this->route('article');
        $categoryId = $this->input('help_category_id');

        return [
            'help_category_id' => ['sometimes', 'required', 'exists:help_categories,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('help_articles')
                    ->where('help_category_id', $categoryId)
                    ->ignore($articleId),
            ],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['sometimes', 'required', 'string'],
            'related_article_ids' => ['nullable', 'array'],
            'related_article_ids.*' => ['exists:help_articles,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'help_category_id.required' => 'Please select a category for this article.',
            'help_category_id.exists' => 'The selected category does not exist.',
            'title.required' => 'The article title is required.',
            'title.max' => 'The article title must not exceed 255 characters.',
            'slug.required' => 'The article slug is required.',
            'slug.unique' => 'An article with this slug already exists in this category.',
            'content.required' => 'The article content is required.',
            'excerpt.max' => 'The excerpt must not exceed 500 characters.',
        ];
    }

    /**
     * Get the validated data with sanitized HTML content.
     *
     * @param  array|int|string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if ($key === null && is_array($validated) && isset($validated['content'])) {
            $validated['content'] = app(HtmlSanitizerService::class)->sanitize($validated['content']);
        }

        return $validated;
    }
}

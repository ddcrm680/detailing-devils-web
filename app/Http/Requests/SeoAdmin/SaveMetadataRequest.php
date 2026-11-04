<?php

namespace App\Http\Requests\SeoAdmin;

use App\Services\Seo\Admin\SeoAdminPages;
use App\Services\Seo\Admin\SeoMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class SaveMetadataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::guard('web')->check() && Gate::forUser(Auth::guard('web')->user())->allows('manage-seo');
    }

    public function rules(): array
    {
        app(SeoAdminPages::class)->page($this->route('page'));
        $plain = function ($attribute, $value, $fail) {
            if (
                ! mb_check_encoding($value, 'UTF-8') || strip_tags($value) !== $value
                || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)
            ) {
                $fail('Use plain text without HTML or control characters.');
            }
        };
        $canonicals = array_map(
            fn ($page) => $page->canonical,
            app(SeoAdminPages::class)->canonicals($this->route('page'))
        );

        return [
            'title' => ['required', 'string', 'max:200', $plain],
            'description' => ['required', 'string', 'max:1000', $plain],
            'og_title' => ['nullable', 'string', 'max:200', $plain],
            'og_description' => ['nullable', 'string', 'max:1000', $plain],
            'og_type' => ['required', Rule::in(['website', 'article'])],
            'twitter_title' => ['nullable', 'string', 'max:200', $plain],
            'twitter_description' => ['nullable', 'string', 'max:1000', $plain],
            'image' => ['nullable', Rule::in(array_keys(app(SeoMedia::class)->choices()))],
            'canonical' => ['required', 'string', Rule::in($canonicals)],
            'robots' => [
                'required',
                Rule::in(['index, follow', 'noindex, follow', 'noindex, nofollow', 'index, nofollow']),
            ],
            'sitemap' => ['required', 'boolean'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'policy_reason' => ['nullable', 'string', 'max:500', $plain],
        ];
    }

    public function fields(): array
    {
        $fields = $this->safe()->except(['expected_version', 'policy_reason']);
        $fields['sitemap'] = $this->boolean('sitemap');
        foreach (['og_title', 'og_description', 'twitter_title', 'twitter_description', 'image'] as $key) {
            $fields[$key] = $fields[$key] ?? null;
        }

        return $fields;
    }
}

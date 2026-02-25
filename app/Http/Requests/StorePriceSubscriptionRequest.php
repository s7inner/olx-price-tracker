<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\Url\Url;
use Throwable;

class StorePriceSubscriptionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $listingUrl = trim((string) $this->input('listing_url', ''));

        if ($listingUrl === '') {
            return;
        }

        try {
            $listingUrl = rtrim(
                (string) Url::fromString($listingUrl)
                    ->withoutQueryParameters()
                    ->withFragment(''),
                '/'
            );
        } catch (Throwable) {
            // ignore parse errors, let validation handle it
        }

        $this->merge(['listing_url' => $listingUrl]);
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'listing_url' => ['required', 'url:https', 'starts_with:'.config('olx.listing_url_prefix')],
            'subscriber_email' => ['required', 'email'],
        ];
    }
}

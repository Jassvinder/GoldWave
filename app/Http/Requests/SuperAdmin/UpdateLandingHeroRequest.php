<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/** T-115 (19-09-2026) — public landing page's editable hero copy. */
class UpdateLandingHeroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'headline' => ['required', 'string', 'max:255'],
            'subtext' => ['required', 'string', 'max:1000'],
            'cta_primary_label' => ['required', 'string', 'max:50'],
            'cta_secondary_label' => ['required', 'string', 'max:50'],
            // Contact details on the landing page footer (02-10-2026) — a blank one is simply hidden there.
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_whatsapp' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_address' => ['nullable', 'string', 'max:500'],
        ];
    }
}

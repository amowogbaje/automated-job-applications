<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Opportunity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function store(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $type = $request->input('type');

        $valueRules = ['required', 'string', 'max:500'];
        if ($type === 'email') {
            $valueRules[] = 'email';
        } elseif (in_array($type, ['whatsapp', 'phone'], true)) {
            $valueRules[] = 'regex:/^\+?[0-9][0-9\s().-]{5,}$/';
        } elseif (in_array($type, ['contact_form', 'careers_portal', 'professional_profile'], true)) {
            $valueRules[] = 'url';
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(config('opportunity.contact_types')))],
            'value' => array_merge($valueRules, [
                Rule::unique('contacts')->where(fn ($q) => $q
                    ->where('company_id', $opportunity->company_id)->where('type', $type)),
            ]),
            'label' => ['nullable', 'string', 'max:255'],
            'person_name' => ['nullable', 'string', 'max:255'],
            'person_role' => ['nullable', 'string', 'max:255'],
            // Required on purpose: every contact must show where it was publicly found.
            'source_url' => ['required', 'url', 'max:2048'],
            'restrictions' => ['nullable', 'string', 'max:2000'],
        ], [
            'value.unique' => 'This contact is already saved for the company.',
            'source_url.required' => 'Add the public page where you found this contact. Never save unsourced or guessed contacts.',
        ]);

        $contact = Contact::create($data + [
            'company_id' => $opportunity->company_id,
            'opportunity_id' => $opportunity->id,
            'is_public_business_channel' => $request->boolean('is_public_business_channel'),
            'is_preferred' => $request->boolean('is_preferred'),
            'verified_at' => now(),
        ]);

        if ($contact->is_preferred) {
            Contact::where('company_id', $contact->company_id)->where('id', '!=', $contact->id)
                ->update(['is_preferred' => false]);
        }
        ActivityLog::record($opportunity, 'contact_added', "Contact added ({$contact->type}).");

        return back()->with('status', 'Contact saved.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return back()->with('status', 'Contact removed.');
    }
}

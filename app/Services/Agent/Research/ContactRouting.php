<?php

namespace App\Services\Agent\Research;

/** Deterministic contact-channel rules, so this part of the score is explainable and never AI-guessed. */
class ContactRouting
{
    private const BASE = [
        'email' => 90, 'whatsapp' => 80, 'careers_portal' => 75, 'contact_form' => 65,
        'phone' => 40, 'professional_profile' => 30, 'other' => 20,
    ];

    /** @param array<int, array{type: string, is_public_business_channel?: bool}> $contacts */
    public static function score(array $contacts, bool $isEmployment = false): array
    {
        $best = 0;
        $why = 'No public contact route found yet.';
        foreach ($contacts as $c) {
            $type = $c['type'] ?? 'other';
            $s = self::BASE[$type] ?? 20;
            if ($type === 'careers_portal' && $isEmployment) {
                $s = 90;
            }
            if (empty($c['is_public_business_channel'])) {
                $s = (int) round($s * 0.6);
            }
            if ($s > $best) {
                $best = $s;
                $why = "Best route: {$type}".(empty($c['is_public_business_channel']) ? ' (not confirmed as a business channel)' : ' published by the company').'.';
            }
        }

        return ['score' => $best, 'reasoning' => $why];
    }

    /** @return array{channel: ?string, rationale: string} using the channel keys from config('opportunity.channels') */
    public static function recommend(array $contacts, ?string $engagement, ?string $oppType): array
    {
        $types = array_column($contacts, 'type');
        $has = fn (string $t) => in_array($t, $types, true);
        $job = $engagement === 'employment' || $oppType === 'job_posting';

        if ($job && $has('careers_portal')) {
            return ['channel' => 'application', 'rationale' => "This looks like a job opening, so use the company's own application process."];
        }
        if ($has('email')) {
            return ['channel' => 'email', 'rationale' => 'A public business email is available, which suits a concise, personalised introduction.'];
        }
        if ($has('whatsapp')) {
            return ['channel' => 'whatsapp', 'rationale' => 'The company publishes a WhatsApp contact; keep the message short.'];
        }
        if ($has('contact_form')) {
            return ['channel' => 'contact_form', 'rationale' => 'Only a contact form was found; send a short message that fits it.'];
        }
        if ($has('careers_portal')) {
            return ['channel' => 'application', 'rationale' => 'Only a careers or application portal was found.'];
        }
        if ($has('phone')) {
            return ['channel' => 'phone', 'rationale' => 'Only a phone number was found. It is not assumed to support WhatsApp; prepare a short call script.'];
        }

        return ['channel' => null, 'rationale' => 'No reliable public contact route found yet. Keep the opportunity and look again later.'];
    }
}

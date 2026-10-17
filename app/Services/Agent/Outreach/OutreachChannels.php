<?php

namespace App\Services\Agent\Outreach;

/** What each channel needs. Keys match config('opportunity.channels'). */
class OutreachChannels
{
    public const APPROACHES = ['direct', 'problem_solving', 'experience_led'];

    public const APPROACH_LABELS = [
        'direct' => 'Direct',
        'problem_solving' => 'Problem-solving',
        'experience_led' => 'Experience-led',
    ];

    private const SPECS = [
        'email' => [
            'label' => 'Email', 'subject' => true, 'max_words' => 170, 'signature' => 'email',
            'contact_types' => ['email'],
            'format' => 'A short first email. Greeting line, then 3 short paragraphs at most, ending with one low-pressure question.',
        ],
        'whatsapp' => [
            'label' => 'WhatsApp', 'subject' => false, 'max_words' => 90, 'signature' => 'whatsapp',
            'contact_types' => ['whatsapp'],
            'format' => 'A short, conversational WhatsApp message. No subject line, no headings, no bullet lists, at most 2 short paragraphs. Say who you are in the first line.',
        ],
        'contact_form' => [
            'label' => 'Contact form', 'subject' => false, 'max_words' => 130, 'signature' => 'form',
            'contact_types' => ['contact_form', 'professional_profile'],
            'format' => 'A message to paste into a website contact form or a professional-network message box. Plain text, 2 short paragraphs at most, self-explanatory without a subject line.',
        ],
        'application' => [
            'label' => 'Application', 'subject' => false, 'max_words' => 240, 'signature' => 'any',
            'contact_types' => ['careers_portal'],
            'format' => 'A cover note for a job or project application through the company\'s own portal. Refer to the role by its title. Plain text, 3 short paragraphs at most.',
        ],
        'phone' => [
            'label' => 'Call script', 'subject' => false, 'max_words' => 200, 'signature' => null,
            'contact_types' => ['phone'],
            'format' => 'A call script, not a message: (1) a 15-second opener that says who you are and why you are calling, (2) two open questions to ask, (3) one line to close or to leave a voicemail. Label the three parts.',
        ],
    ];

    private const APPROACH_GUIDE = [
        'direct' => 'Say plainly who you are and what you offer, in your own words, and ask one simple question. Make no claims about the company beyond what its own contact page or the evidence plainly shows.',
        'problem_solving' => 'Open with ONE specific, evidenced observation about the company (cite the evidence id in "claims"), then connect it to how the service could help. Never invent a problem. State it as an observation, not an accusation.',
        'experience_led' => 'Open with one relevant project from "MY PROJECTS" and what it delivered, using only the proof points given, then say why it may be relevant to this company. Name the project exactly as given and set "project_id".',
    ];

    public static function keys(): array
    {
        return array_keys(self::SPECS);
    }

    public static function exists(string $channel): bool
    {
        return isset(self::SPECS[$channel]);
    }

    public static function spec(string $channel): array
    {
        return self::SPECS[$channel];
    }

    public static function guide(string $approach): string
    {
        return self::APPROACH_GUIDE[$approach];
    }

    /** Which channel a saved contact type belongs to (null = no draft channel). */
    public static function forContactType(string $type): ?string
    {
        foreach (self::SPECS as $key => $spec) {
            if (in_array($type, $spec['contact_types'], true)) {
                return $key;
            }
        }

        return null;
    }
}

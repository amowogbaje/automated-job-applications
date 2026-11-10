<?php

return [
    // Phone numbers written without a country code (e.g. 0803...) are given this one.
    'default_country_code' => env('LEADS_COUNTRY_CODE', '234'),

    // The account whose mailbox the app's MAIL_* settings send from. Only this user can "Send now" through MAIL_*;
    // everyone else connects their own Google account. Can be changed on the Leads page.
    'smtp_user_email' => env('LEADS_SMTP_USER_EMAIL', 'amowogbajegideon@gmail.com'),

    // Email follow-up sequence: days to wait AFTER the previous step. Step 1 goes out straight away.
    'email_delays' => [0, 3, 4],
    // After the last email, wait this many days for a reply before moving to phone / closing.
    'grace_days' => 4,
    // Phone/WhatsApp (manual) steps: remind you up to this many times, then wait this long after you message.
    'manual_reminders' => 2,
    'manual_wait_days' => 3,
    'after_manual_days' => 5,

    // Safety rails for automatic email.
    'send_hours' => [8, 17],      // only send between these hours (app timezone), inclusive start, exclusive end
    'daily_cap' => 20,            // max automatic emails per day
    'attach_max_mb' => 5,

    'max_import_rows' => 200,
    'max_ai_chars' => 14000,

    'generic_email_domains' => ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'live.com', 'proton.me', 'protonmail.com', 'aol.com', 'yahoo.co.uk'],

    'default_templates' => [
        1 => [
            'subject' => 'Quick question for {company}',
            'body' => "Hi {name},\n\nI'm {sender}. I came across {company} and thought {service} could help you. I'd be glad to share what I can do and agree a price that fits you.\n\nWould you be open to a short chat?\n\n{documents}",
        ],
        2 => [
            'subject' => 'Re: Quick question for {company}',
            'body' => "Hi {name},\n\nJust following up on my earlier note about {service} for {company}. If now isn't the right time, no problem. Let me know and I'll stay out of your inbox.\n\n{documents}",
        ],
        3 => [
            'subject' => 'Last note from {sender}',
            'body' => "Hi {name},\n\nThis is my last follow-up so I don't clutter your inbox. If {service} is ever useful for {company}, just reply to this email and we can pick it up then.\n\nAll the best.",
        ],
    ],
];

<?php

return [
    'types' => [
        'inferred_need' => 'Inferred need',
        'job_posting' => 'Job posting',
        'rfp' => 'RFP / project brief',
        'growth_signal' => 'Growth signal',
        'referral' => 'Referral',
        'manual' => 'Manual',
    ],

    'statuses' => [
        'discovered' => 'Discovered',
        'researching' => 'Researching',
        'qualified' => 'Qualified',
        'drafted' => 'Drafted',
        'approved' => 'Approved',
        'contacted' => 'Contacted',
        'replied' => 'Replied',
        'won' => 'Won',
        'lost' => 'Lost',
        'rejected' => 'Rejected',
        'archived' => 'Archived',
    ],

    'engagements' => [
        'employment' => 'Employment',
        'contract' => 'Contract',
        'project' => 'Project',
        'consulting' => 'Consulting',
    ],

    'evidence_kinds' => [
        'website' => 'Company website',
        'job_listing' => 'Job listing',
        'news' => 'News / announcement',
        'funding' => 'Funding / growth',
        'directory' => 'Directory',
        'rfp' => 'RFP / brief',
        'other' => 'Other',
    ],

    'contact_types' => [
        'email' => 'Business email',
        'whatsapp' => 'WhatsApp business',
        'phone' => 'Phone',
        'contact_form' => 'Contact form',
        'careers_portal' => 'Careers / application portal',
        'professional_profile' => 'Professional profile',
        'other' => 'Other',
    ],

    'channels' => [
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'phone' => 'Phone call',
        'contact_form' => 'Contact form',
        'application' => 'Application portal',
    ],

    'event_types' => [
        'sent' => 'Message sent',
        'reply' => 'Reply received',
        'call' => 'Call',
        'meeting' => 'Meeting',
        'bounce' => 'Bounced',
        'no_response' => 'No response',
        'note' => 'Note',
    ],

    'criteria' => [
        'need_evidence' => 'Evidence of a relevant business need',
        'skills_match' => 'Match with my skills and services',
        'active_signal' => 'Evidence the opportunity is active',
        'commercial_value' => 'Potential commercial value',
        'contact_channel' => 'Relevant contact channel available',
        'portfolio_strength' => 'Strength of my portfolio evidence',
    ],

    'default_weights' => [
        'need_evidence' => 25,
        'skills_match' => 25,
        'active_signal' => 15,
        'commercial_value' => 15,
        'contact_channel' => 10,
        'portfolio_strength' => 10,
    ],
];

<?php

return [
    /*
     | The Opportunity Agent is a personal tool: it uses YOUR API keys and holds YOUR outreach,
     | while the rest of this site is multi-account. Only these accounts see /agent.
     | Comma-separated emails. If empty, only the first account ever registered (lowest id) gets in.
     */
    'owner_emails' => array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)),
        explode(',', (string) env('AGENT_OWNER_EMAILS', ''))
    ))),
    // Build a tailored resume from your Profile when research finishes (AGENT_AUTO_RESUME=false to turn off).
    'auto_resume' => (bool) env('AGENT_AUTO_RESUME', true),
];

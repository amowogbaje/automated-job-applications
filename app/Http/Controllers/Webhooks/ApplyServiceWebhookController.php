<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\ApplicationDraft;
use App\Models\JobListingUserState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives the apply service's outcome for an application we handed it
 * (submitted | needs_input | failed). POST /webhooks/apply-service
 *
 * Verification: HMAC-SHA256 of the RAW body with APPLY_SERVICE_WEBHOOK_SECRET,
 * compared to the header named by APPLY_SERVICE_SIGNATURE_HEADER. The page
 * we integrated against doesn't document the provider's exact scheme —
 * confirm header name/algorithm in their dashboard docs and adjust verify().
 * With no secret configured every request is rejected (fail closed).
 */
class ApplyServiceWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        if (! $this->verify($request)) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        $sessionId = $request->input('sessionId') ?? $request->input('session_id') ?? $request->input('data.sessionId');
        $status = $request->input('status') ?? $request->input('event') ?? $request->input('data.status');

        $draft = $sessionId ? ApplicationDraft::where('external_ref', $sessionId)->first() : null;

        if (! $draft) {
            Log::info('Apply webhook for unknown session', ['session' => $sessionId]);

            return response()->json(['ok' => true]); // 2xx so the provider doesn't retry forever
        }

        // Idempotent: a repeated "submitted" is a no-op.
        if ($draft->status === 'sent' && $status === 'submitted') {
            return response()->json(['ok' => true]);
        }

        match ($status) {
            'submitted' => $this->submitted($draft, $request),
            'needs_input' => $draft->update([
                'status' => 'needs_input',
                'pending_questions' => (array) ($request->input('questions') ?? $request->input('data.questions') ?? []),
            ]),
            'failed' => $draft->update([
                'status' => 'ready', // falls back to manual review
                'error' => mb_substr((string) ($request->input('error') ?? $request->input('details') ?? 'failed'), 0, 1000),
            ]),
            default => Log::info('Apply webhook with unhandled status', ['status' => $status]),
        };

        return response()->json(['ok' => true]);
    }

    private function submitted(ApplicationDraft $draft, Request $request): void
    {
        $draft->update([
            'status' => 'sent',
            'submitted_at' => now(),
            'receipt_url' => $request->input('receiptUrl') ?? $request->input('screenshotUrl') ?? $request->input('data.receiptUrl'),
        ]);

        JobListingUserState::updateOrCreate(
            ['job_listing_id' => $draft->job_listing_id, 'user_id' => $draft->user_id],
            ['is_applied' => true, 'applied_at' => now()]
        );
    }

    private function verify(Request $request): bool
    {
        $secret = config('services.apply_service.webhook_secret');
        if (! $secret) {
            return false;
        }

        $given = (string) $request->header(config('services.apply_service.signature_header', 'X-Signature'));
        $given = preg_replace('/^sha256=/i', '', $given);
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return $given !== '' && hash_equals($expected, $given);
    }
}

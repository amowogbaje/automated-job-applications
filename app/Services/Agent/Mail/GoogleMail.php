<?php

namespace App\Services\Agent\Mail;

use App\Models\GoogleAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** Google OAuth 2 (authorization-code flow) + Gmail API send, without any extra composer package. */
class GoogleMail
{
    public const SCOPES = 'openid email https://www.googleapis.com/auth/gmail.send';

    public static function configured(): bool
    {
        return filled(config('google.client_id')) && filled(config('google.client_secret'));
    }

    public static function redirectUri(): string
    {
        return config('google.redirect') ?: route('agent.google.callback');
    }

    public function authUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('google.client_id'), 'redirect_uri' => self::redirectUri(), 'response_type' => 'code',
            'scope' => self::SCOPES, 'access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'true', 'state' => $state,
        ]);
    }

    /** Swaps the code for tokens and stores them for $user. @throws \RuntimeException */
    public function connect(User $user, string $code): GoogleAccount
    {
        $r = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'code' => $code, 'client_id' => config('google.client_id'), 'client_secret' => config('google.client_secret'),
            'redirect_uri' => self::redirectUri(), 'grant_type' => 'authorization_code',
        ]);
        if (! $r->successful()) {
            throw new \RuntimeException('Google refused the sign-in: '.($r->json('error_description') ?: $r->json('error') ?: $r->status()));
        }
        $granted = (string) $r->json('scope');
        if (! str_contains($granted, 'gmail.send')) {
            throw new \RuntimeException('You did not allow "Send email on your behalf". Try again and tick that box.');
        }
        $existing = GoogleAccount::where('user_id', $user->id)->first();
        $refresh = $r->json('refresh_token') ?: $existing?->refresh_token;
        if (! $refresh) {
            throw new \RuntimeException('Google did not return a refresh token. Remove the app at myaccount.google.com/permissions and connect again.');
        }

        return GoogleAccount::updateOrCreate(['user_id' => $user->id], [
            'email' => self::emailFromIdToken((string) $r->json('id_token')) ?: $user->email,
            'refresh_token' => $refresh,
            'access_token' => $r->json('access_token'),
            'expires_at' => now()->addSeconds((int) $r->json('expires_in', 3600) - 60),
        ]);
    }

    public static function emailFromIdToken(string $jwt): ?string
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return null;
        }
        $claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($claims) ? ($claims['email'] ?? null) : null;
    }

    public function disconnect(GoogleAccount $acct): void
    {
        try {
            Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/revoke', ['token' => $acct->refresh_token]);
        } catch (\Throwable) {
        }
        $acct->delete();
    }

    private function accessToken(GoogleAccount $acct): string
    {
        if ($acct->access_token && $acct->expires_at && $acct->expires_at->isFuture()) {
            return $acct->access_token;
        }
        $r = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('google.client_id'), 'client_secret' => config('google.client_secret'),
            'refresh_token' => $acct->refresh_token, 'grant_type' => 'refresh_token',
        ]);
        if (! $r->successful()) {
            if ($r->json('error') === 'invalid_grant') {
                $acct->delete();
                throw new \RuntimeException('Google access was withdrawn or expired. Connect your Google account again.');
            }
            throw new \RuntimeException('Could not refresh Google access: '.($r->json('error_description') ?: $r->status()));
        }
        $acct->update(['access_token' => $r->json('access_token'), 'expires_at' => now()->addSeconds((int) $r->json('expires_in', 3600) - 60)]);

        return $acct->access_token;
    }

    /** @param array{subject:string, body:string, to:string, attachments:array} $mail */
    public static function mime(GoogleAccount $acct, string $fromName, array $mail): string
    {
        $email = (new Email())
            ->from(new Address($acct->email, $fromName))
            ->to($mail['to'])
            ->subject($mail['subject'])
            ->text($mail['body']);
        foreach ($mail['attachments'] ?? [] as $d) {
            $email->attachFromPath($d->absolutePath(), $d->original_name, $d->mime ?: 'application/octet-stream');
        }

        return $email->toString();
    }

    /** @throws \RuntimeException */
    public function send(GoogleAccount $acct, string $fromName, array $mail): void
    {
        $raw = rtrim(strtr(base64_encode(self::mime($acct, $fromName, $mail)), '+/', '-_'), '=');
        $r = Http::withToken($this->accessToken($acct))->timeout(30)->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', ['raw' => $raw]);
        if ($r->successful()) {
            return;
        }
        $msg = (string) ($r->json('error.message') ?: $r->status());
        if ($r->status() === 403 && str_contains(strtolower($msg), 'api')) {
            $msg = 'The Gmail API is not enabled in your Google Cloud project (APIs & Services → Library → Gmail API → Enable).';
        } elseif (in_array($r->status(), [401, 403], true)) {
            $msg = 'Google rejected the send: '.$msg.' Try disconnecting and connecting Google again.';
        }
        throw new \RuntimeException($msg);
    }
}

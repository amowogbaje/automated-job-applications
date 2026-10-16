<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $guarded = [];

    protected $casts = [
        'services_offered' => 'array',
        'last_inspected_at' => 'datetime',
        'do_not_contact' => 'boolean',
    ];

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    /** Normalize a URL or host to a bare domain for dedupe. */
    public static function normalizeDomain(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $host = parse_url(str_contains($url, '://') ? $url : "https://{$url}", PHP_URL_HOST);
        if (! $host) {
            return null;
        }

        return preg_replace('/^www\./i', '', strtolower($host));
    }

    protected static function booted(): void
    {
        static::saving(function (Company $company) {
            if ($company->website_url && ! $company->domain) {
                $company->domain = self::normalizeDomain($company->website_url);
            }
        });
    }
}

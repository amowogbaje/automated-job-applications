<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'company_name',
        'company_website',
        'company_size',
        'company_description',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isEmployer(): bool
    {
        return $this->role === 'employer';
    }

    public function isSeeker(): bool
    {
        return $this->role !== 'employer';
    }

    /** Where this account lands after login. */
    public function homeRoute(): string
    {
        return $this->isEmployer() ? 'employer.dashboard' : 'jobs.index';
    }

    public function postedJobs()
    {
        return $this->hasMany(JobListing::class, 'employer_id');
    }

    public function careerProfile()
    {
        return $this->hasOne(CareerProfile::class);
    }

    public function resumes()
    {
        return $this->hasMany(Resume::class);
    }
}

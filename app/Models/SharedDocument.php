<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class SharedDocument extends Model
{
    protected $guarded = [];

    protected $casts = ['attach_to_emails' => 'boolean'];

    /** Link you can paste into WhatsApp or an email. Signed, so it can't be guessed. */
    public function publicUrl(): string
    {
        return URL::signedRoute('documents.public', $this);
    }

    public function absolutePath(): string
    {
        return Storage::disk('local')->path($this->path);
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576 ? round($this->size / 1048576, 1).' MB' : max(1, (int) round($this->size / 1024)).' KB';
    }
}

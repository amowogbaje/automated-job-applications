<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name'];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function toBoardArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Models extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'url'
    ];
    public function generations()
    {
        return $this->hasMany(Generation::class, 'model_id');
    }
}

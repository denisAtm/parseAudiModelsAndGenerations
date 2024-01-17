<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Generation extends Model
{
    use HasFactory;
    protected $fillable = [
        'market',
        'modelName',
        'period',
        'generation',
        'imageSrc',
        'techSpecsLink'
    ];
    public function model()
    {
        return $this->belongsTo(Models::class, 'model_id');
    }
}

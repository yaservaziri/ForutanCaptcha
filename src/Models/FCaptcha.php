<?php

namespace Forutan\Captcha\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class FCaptcha extends Model
{
    use HasUuids;
    protected $table = 'fcaptchas';
    protected $fillable = [
        'image',
        'category',
    ];
}

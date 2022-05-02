<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rekey extends Model
{
    use HasFactory;

    protected $fillable = ['owner_id', 'requester_id', 'file_id', 'rekey'];
}

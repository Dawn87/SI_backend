<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refile extends Model
{
    use HasFactory;
    
    public function user(){
        return $this->belongsTo('App\Models\User');
    }
    protected $fillable = ['name', 'path', 'file_id', 'requester_id'];
}

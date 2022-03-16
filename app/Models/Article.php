<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    /**
     * The users that belong to the article.
     */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}

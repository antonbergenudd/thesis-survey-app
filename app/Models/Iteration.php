<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Iteration extends Model
{
    use HasFactory;

    /**
     * Retrieve all articles in iteration
     */
    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    /**
     * Retrieve all users in iteration
     */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Retrieve all users in iteration
     */
    public function answers($user)
    {
        return $this->belongsToMany(ArticleUser::class)->where('user_id', $user->id);
    }
}

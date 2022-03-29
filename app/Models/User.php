<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'token',
        'profile'
    ];

    /**
     * Retrieve all articles related to user
     */
    public function answers()
    {
        return $this->belongsToMany(Article::class)->withPivot('relevance', 'understandability', 'length');
    }

    /**
     * Retrieve specific iteration from user
     */
    public function iteration($iter)
    {
        return $this->belongsToMany(Article::class)->withPivot('relevance', 'understandability', 'length');
    }

    private function calculateUserProfile($articles, $update) {
        $num_topics = count(json_decode($articles->first()->label_dist));
        $user_profile = isset($user->profile) ? json_decode($user->profile) : array_fill(0, $num_topics, (100/$num_topics)/100);
        $total_num_answers = count($articles);

        // Loop all articles and compute average label dist for user profile
        foreach($articles as $i => $article) {
            // Get article distribution
            $article_dist = json_decode($article->label_dist);

            // Add each weighted label distribution over an article to collected array (rel score * single label dist)
            $total_diff = [];
            foreach($article_dist as $j => $single_topic_dist) {
                // Get abs. difference between profiles
                $diff = abs($user_profile[$j] - $single_topic_dist);
                $total_diff[] = $diff;

                // Decrease impact of articles the more you answer
                // Roughly every other iteration (30 articles)
                $gradient = $total_num_answers != 0 ? 1 / $total_num_answers % 30 : 1;

                // Add weighted difference to user profile. This weight decides the impact of a single article.
                $scale = [-0.75, -0.25, 0, 0.25, 0.75];
                $weighted_diff = ($diff != 0 ? $diff * $scale[$article->pivot->relevance - 1] * $gradient : 0);
                $user_profile[$j] += $weighted_diff;

                // Prevent values above 100%
                if($user_profile[$j] >= 1.0)
                    $user_profile[$j] = 1.0;

                // Normalize distribution on all other values
                if($weighted_diff != 0) {
                    $normalized_weighted_diff = $weighted_diff/($num_topics-1);
                    for($i = 0; $i < $num_topics; $i++) {
    
                        // Normalize all values except current
                        if($i != $j)
                            $user_profile[$i] -= $normalized_weighted_diff;
    
                            // Prevent values below 0%
                            if($user_profile[$i] < 0)
                                $user_profile[$i] = 0;
                    }
                }
            }

            if ($update) {
                // Save difference for each article for each iteration
                $article->pivot->difference = $total_diff;
                $article->pivot->save();
            }
        }

        if ($update) {
            // Update profile
            $user->profile = $user_profile;
            $user->save();

            return 1;
        }
        
        return $user_profile;
    }

    public function updateProfile($article_ids) {
        // Get all rated articles
        $user = User::find($this->id);
        $rated_articles = $user->answers()->whereIn('article_id', $article_ids)->get();

        $this->calculateUserProfile($rated_articles, 1);
    }

    public function getProfile($iteration) {
        // Get all rated articles
        $user = User::find($this->id);
        $articles = $iteration ? $user->answers()->get()->where('iteration_id', '<=', $iteration) : $user->answers;

        // Return profile
        return $this->calculateUserProfile($articles, 0);
    }
}

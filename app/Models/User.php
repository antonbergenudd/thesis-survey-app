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
        'profile',
        'last_accessed'
    ];

    /**
     * Retrieve all articles related to user
     */
    public function answers()
    {
        return $this->belongsToMany(Article::class)->withPivot('relevance', 'understandability', 'length', 'difference');
    }

    /**
     * Retrieve all articles related to user
     */
    public function iterationProfile($iteration)
    {
        return $this->hasMany(UserProfile::class)->where('iteration_id', $iteration)->first();
    }

     /**
     * Retrieve all articles related to user
     */
    public function profile()
    {
        return $this->hasMany(UserProfile::class);
    }

    /**
     * Retrieve all articles related to user
     */
    public function latestProfile()
    {
        return $this->hasMany(UserProfile::class)->get()->sortByDesc('iteration_id')->first();
    }

    public function updateProfile($answered_articles) {
        $num_topics = count(json_decode($answered_articles->first()->label_dist)); # Calc number of topics
        $iteration = $answered_articles->sortByDesc('iteration_id')->first()->iteration_id; # Set iteration number
        $num_answers = count($answered_articles);
        $user_profile = $this->latestProfile(); # Retrieve latest user profile
        
        # Init unit distributed user profile if not set before
        if (! isset($user_profile))
            $user_profile = array_fill(0, $num_topics, (100/$num_topics)/100);
        else
            $user_profile = json_decode($user_profile->profile);

        // Loop all articles and compute average label dist for user profile
        $sum_weighted_diff = array_fill(0, $num_topics, 0.0);
        foreach($answered_articles as $i => $article) {

            // Get article distribution
            $article_dist = json_decode($article->label_dist);

            // Add each weighted label distribution over an article to collected array (rel score * single label dist)
            $total_diff = [];
            foreach($article_dist as $j => $single_topic_dist) {

                // Get abs. difference between profiles
                $diff = $single_topic_dist - $user_profile[$j];
                $total_diff[] = $diff;

                // Decrease impact of articles the more you answer
                // Roughly every other iteration (30 articles)
                $gradient = $num_answers >= 30 ? (1 / ($num_answers/$num_topics)) : 1;

                // Add weighted difference to user profile. This weight decides the impact of a single article.
                $scale = [-0.75, -0.25, 0, 0.25, 0.75];
                    
                // Add weighted difference (Is deducted if negative)
                $sum_weighted_diff[$j] += ($diff != 0 ? $diff * $scale[$article->pivot->relevance - 1] * $gradient : 0);
            }

            // Save difference for each article for each iteration
            $article->pivot->difference = $total_diff;
            $article->pivot->save();
        }

        // Take average of all article differences
        foreach($sum_weighted_diff as $j => $avg_weighted_diff) {
            $user_profile[$j] += $avg_weighted_diff;
        }

        // Alter values to be between 0 and 1
        $max = max($user_profile); $min = min($user_profile);
        if($min != $max) {
            // normalized = (x-min(x))/(max(x)-min(x))
            foreach($user_profile as $j => $val) {
                $user_profile[$j] = ($val - $min) / ($max - $min);
            }

            // Normalize values
            $sum = array_sum($user_profile);
            foreach($user_profile as $j => $val) {
                $user_profile[$j] = $val / $sum;
            }
        }

        // Create new profile
        $userProfile = new UserProfile;
        $userProfile->user_id = $this->id;
        $userProfile->token = $this->token;
        $userProfile->profile = json_encode($user_profile);
        $userProfile->iteration_id = $iteration;
        $userProfile->save();

        return "Distribution summary: ".(array_sum($user_profile) * 100)."%";
    }

    public function getProfile($iteration) {
        return UserProfile::where('iteration_id', $iteration)->where('user_id', $this->id)->first();
    }
}

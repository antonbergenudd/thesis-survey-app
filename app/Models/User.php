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
    public function latestProfile()
    {
        return $this->hasMany(UserProfile::class)->get()->sortByDesc('iteration_id')->first();
    }

    public function updateProfile($articles) {
        $num_topics = count(json_decode($articles->first()->label_dist)); # Calc number of topics
        $iteration = $articles->sortByDesc('iteration_id')->first()->iteration_id; # Set iteration number
        $total_num_answers = count($articles);
        $user_profile = $this->latestProfile(); # Retrieve latest user profile
        
        # Init unit distributed user profile if not set before
        if (! isset($user_profile))
            $user_profile = array_fill(0, $num_topics, (100/$num_topics)/100);
        else
            $user_profile = json_decode($user_profile->profile);
        
        // Loop all articles and compute average label dist for user profile
        foreach($articles as $i => $article) {

            // Get article distribution
            $article_dist = json_decode($article->label_dist);

            // Add each weighted label distribution over an article to collected array (rel score * single label dist)
            $total_diff = [];
            foreach($article_dist as $j => $single_topic_dist) {
                $userArraySum = array_sum($user_profile);

                // Get abs. difference between profiles
                $diff = $single_topic_dist - $user_profile[$j];
                $total_diff[] = $diff;

                // Decrease impact of articles the more you answer
                // Roughly every other iteration (30 articles)
                $gradient = $total_num_answers >= 30 ? (1 / ($total_num_answers/$num_topics)) : 1;

                // Add weighted difference to user profile. This weight decides the impact of a single article.
                $scale = [-0.75, -0.25, 0, 0.25, 0.75];
                    
                $weighted_diff = ($diff != 0 ? $diff * $scale[$article->pivot->relevance - 1] * $gradient : 0);

                // Add weighted difference (Is deducted if negative)
                $user_profile[$j] += $weighted_diff;
            }

            // Alter values to be between 0 and 1
            $max = max($user_profile);
            $min = min($user_profile);
            foreach($user_profile as $j => $profile_val) {
                $user_profile[$j] = ($profile_val - $min) / ($max - $min);
            }

            // Normalize values
            $sum = array_sum($user_profile);
            foreach($user_profile as $j => $profile_val) {
                $user_profile[$j] = $profile_val / $sum;
            }

            // Save difference for each article
            $article->pivot->difference = $total_diff;
            $article->pivot->save();
        }

        // Add updated user profile log
        if (!UserProfile::where('user_id', $this->id)->where('iteration_id', $iteration)->exists()) {
            $userProfile = new UserProfile;
            $userProfile->user_id = $this->id;
            $userProfile->token = $this->token;
            $userProfile->profile = json_encode($user_profile);
            $userProfile->iteration_id = $iteration;
            $userProfile->save();
        }

        return "Distribution summary: ".(array_sum($user_profile) * 100)."%";
    }

    // public function updateProfile($articles) {
    //     $num_topics = count(json_decode($articles->first()->label_dist)); # Calc number of topics
    //     $iteration = $articles->sortByDesc('iteration_id')->first()->iteration_id; # Set iteration number
    //     $total_num_answers = count($articles);
    //     $user_profile = $this->latestProfile(); # Retrieve latest user profile
        
    //     # Init unit distributed user profile if not set before
    //     if (! isset($user_profile))
    //         $user_profile = array_fill(0, $num_topics, (100/$num_topics)/100);
    //     else
    //         $user_profile = json_decode($user_profile->profile);
        
    //     // Loop all articles and compute average label dist for user profile
    //     foreach($articles as $i => $article) {

    //         // Get article distribution
    //         $article_dist = json_decode($article->label_dist);

    //         // Add each weighted label distribution over an article to collected array (rel score * single label dist)
    //         $total_diff = [];
    //         foreach($article_dist as $j => $single_topic_dist) {
    //             $userArraySum = array_sum($user_profile);

    //             // Get abs. difference between profiles
    //             $diff = $single_topic_dist - $user_profile[$j];
    //             $total_diff[] = $diff;


    //             // 0.3 -> 0.6
    //             // 0.7
    //             // 0.4 
    //             // relevance 5
    //             // 0.4 * 0.75 = 0.3

    //             // 0.7 -> 0.4
    //             // 0.3
    //             // -0.4 
    //             // relevance 5
    //             // -0.4 * 0.75 = -0.3

    //             // Art: 0.7
    //             // Usr: 0.3
    //             // Diff: 0.4 
    //             // relevance 1
    //             // Tot: 0.4 * -0.75 = -0.3

    //             // Art: 0.3
    //             // Usr: 0.7
    //             // Diff: -0.4 
    //             // relevance 1
    //             // Tot: -0.4 * -0.75 = 0.3


    //             // Decrease impact of articles the more you answer
    //             // Roughly every other iteration (30 articles)
    //             $gradient = $total_num_answers >= 30 ? (1 / ($total_num_answers/$num_topics)) : 1;

    //             // Add weighted difference to user profile. This weight decides the impact of a single article.
    //             $scale = [-0.75, -0.25, 0, 0.25, 0.75];
                    
    //             $weighted_diff = ($diff != 0 ? $diff * $scale[$article->pivot->relevance - 1] * $gradient : 0);

    //             // Add weighted difference (Is deducted if negative)
    //             $user_profile[$j] += $weighted_diff;

    //             // END LOOP

    //             // min val topic, max val topic
    //             // new val = 3 - -5 / (10 - -5) 8/15

    //             // Iterate the user profile values
    //             // Still will add up to more than 1
    //             // Compute sum of all values and divide each value by that sum

    //             // Handle values below 0% and over 100%
    //             if($user_profile[$j] > 1.0) {
    //                 $weighted_diff -= ($user_profile[$j] - 1); // Remove value above 100% fr difference
    //                 $user_profile[$j] = 1.0;
    //             } else if($user_profile[$j] < 0) {
    //                 $weighted_diff += (0 - $user_profile[$j]); // Add value below 0% to difference
    //                 $user_profile[$j] = 0;
    //             }

    //             // normalized = (x-min(x))/(max(x)-min(x))

    //             // Calculate normalized difference
    //             $normalized_weighted_diff = $weighted_diff / ($num_topics - 1);

    //             // Distribute normalized difference over all other values
    //             foreach(array_keys($user_profile) as $key) {

    //                 // Ignore current value key
    //                 if($key == $j)
    //                     continue;

    //                 // Remove normalized difference (Is added if negative)
    //                 $user_profile[$key] -= $normalized_weighted_diff;

    //                 // Handle normalized values above 100% and below 0%
    //                 if($user_profile[$key] > 1.0) {
    //                     $surp = ($user_profile[$key] - 1);

    //                     // Retrieve first value which can add surplus from normalized value
    //                     $compatibleValues = array_filter($user_profile, function($value, $j) use($surp, $key) {
    //                         return $value + $surp < 1.0 && $key != $j;
    //                     }, ARRAY_FILTER_USE_BOTH);
    //                     $compatIndex = array_keys($compatibleValues, min($compatibleValues))[0];
    //                     $user_profile[$compatIndex] += $surp;

    //                     $user_profile[$key] = 1.0;
    //                 } else if($user_profile[$key] < 0) {
    //                     $surp = (0 - $user_profile[$key]);

    //                     // Retrieve first value which can remove surplus from normalized value
    //                     $compatibleValues = array_filter($user_profile, function($value, $j) use ($surp, $key) {
    //                         return $value - $surp >= 0 && $key != $j;
    //                     }, ARRAY_FILTER_USE_BOTH);
    //                     $compatIndex = array_keys($compatibleValues, min($compatibleValues))[0];
    //                     $user_profile[$compatIndex] -= $surp;

    //                     $user_profile[$key] = 0;
    //                 }
    //             }
    //         }

    //         // Save difference for each article
    //         $article->pivot->difference = $total_diff;
    //         $article->pivot->save();
    //     }

    //     // Add updated user profile log
    //     if (!UserProfile::where('user_id', $this->id)->where('iteration_id', $iteration)->exists()) {
    //         $userProfile = new UserProfile;
    //         $userProfile->user_id = $this->id;
    //         $userProfile->token = $this->token;
    //         $userProfile->profile = json_encode($user_profile);
    //         $userProfile->iteration_id = $iteration;
    //         $userProfile->save();
    //     }

    //     return "Distribution summary: ".(array_sum($user_profile) * 100)."%";
    // }

    public function getProfile($iteration) {
        return UserProfile::where('iteration_id', $iteration)->where('user_id', $this->id)->first();
    }
}

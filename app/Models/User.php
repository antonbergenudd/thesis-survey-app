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

    /**
    * Helper function to print summary of distribution
    */
    private function printBalance($user_profile, $b, $check) {
        $userArraySum = array_sum($user_profile);

        if($check) {
            if(bccomp($userArraySum, 1.0, 2) <> 0) {
                if($b) {
                    var_dump("Check before sum: ". $userArraySum);
                } else {
                    var_dump("Check after sum: ". $userArraySum);
                }
            }
        } else {
            if($b) {
                var_dump("Before sum: ". $userArraySum);
            } else {
                var_dump("After sum: ". $userArraySum);
            }
        }
    }

    /**
     * Helper function to calculate updated user profile
     */
    private function calculateUserProfile($user, $articles) {
        $num_topics = count(json_decode($articles->first()->label_dist)); # Calc number of topics
        $iteration = $articles->sortByDesc('iteration_id')->first()->iteration_id; # Set iteration number
        $total_num_answers = count($articles);
        $user_profile = $user->latestProfile(); # Retrieve latest user profile
        
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
                $diff = abs($user_profile[$j] - $single_topic_dist);
                $total_diff[] = $diff;

                // Decrease impact of articles the more you answer
                // Roughly every other iteration (30 articles)
                $gradient = $total_num_answers >= 30 ? (1 / ($total_num_answers/$num_topics)) : 1;

                // Add weighted difference to user profile. This weight decides the impact of a single article.
                $scale = [-0.75, -0.25, 0, 0.25, 0.75];
                $weighted_diff = ($diff != 0 ? $diff * $scale[$article->pivot->relevance - 1] * $gradient : 0);

                // Add weighted difference (Is deducted if negative)
                $user_profile[$j] += $weighted_diff;

                // Handle values below 0% and over 100%
                if($user_profile[$j] > 1.0) {
                    $weighted_diff -= ($user_profile[$j] - 1); // Remove value above 100% fr difference
                    $user_profile[$j] = 1.0;
                } else if($user_profile[$j] < 0) {
                    $weighted_diff += (0 - $user_profile[$j]); // Add value below 0% to difference
                    $user_profile[$j] = 0;
                }

                // Calculate normalized difference
                $normalized_weighted_diff = $weighted_diff / ($num_topics - 1);

                // Distribute normalized difference over all other values
                foreach(array_keys($user_profile) as $key) {

                    // Ignore current value key
                    if($key == $j)
                        continue;

                    // Remove normalized difference (Is added if negative)
                    $user_profile[$key] -= $normalized_weighted_diff;

                    // Handle normalized values above 100% and below 0%
                    if($user_profile[$key] > 1.0) {
                        $surp = ($user_profile[$key] - 1);

                        // Retrieve first value which can add surplus from normalized value
                        $compatibleValues = array_filter($user_profile, function($value, $key) use($surp, $j) {
                            return $value + $surp < 1.0 && $key != $j;
                        }, ARRAY_FILTER_USE_BOTH);
                        $compatIndex = array_keys($compatibleValues, min($compatibleValues))[0];
                        $user_profile[$compatIndex] += $surp;

                        $user_profile[$key] = 1.0;
                    } else if($user_profile[$key] < 0) {
                        $surp = (0 - $user_profile[$key]);

                        // Retrieve first value which can remove surplus from normalized value
                        $compatibleValues = array_filter($user_profile, function($value, $key) use ($surp, $j) {
                            return $value - $surp > 0 && $key != $j;
                        }, ARRAY_FILTER_USE_BOTH);
                        $compatIndex = array_keys($compatibleValues, min($compatibleValues))[0];
                        $user_profile[$compatIndex] -= $surp;

                        $user_profile[$key] = 0;
                    }
                }
            }

            // Save difference for each article
            $article->pivot->difference = $total_diff;
            $article->pivot->save();
        }

        // Add updated user profile log
        if (!UserProfile::where('user_id', $user->id)->where('iteration_id', $iteration)->exists()) {
            $userProfile = new UserProfile;
            $userProfile->user_id = $user->id;
            $userProfile->token = $user->token;
            $userProfile->profile = json_encode($user_profile);
            $userProfile->iteration_id = $iteration;
            $userProfile->save();
        }

        return "Distribution summary: ".(array_sum($user_profile) * 100)."%";
    }

    public function updateProfile($article_ids) {
        // Get all rated articles
        $user = User::find($this->id);
        $rated_articles = $user->answers()->whereIn('article_id', $article_ids)->get();

        return $this->calculateUserProfile($user, $rated_articles);
    }

    public function getProfile($iteration) {
        // Get all rated articles
        $user = User::find($this->id);

        // Return profile
        return UserProfile::where('iteration_id', $iteration)->where('user_id', $user->id)->first();
    }
}

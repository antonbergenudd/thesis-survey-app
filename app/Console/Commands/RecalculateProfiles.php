<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\UserProfile;

class RecalculateProfiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'survey-app:calc-profiles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate profiles';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        dump('Version 4');
        $version = 4;

        dump('Iteration 0');
        $iteration = 0; # Set iteration number
        $iteration_0_users = User::whereHas('profile', function($q) use($iteration) { 
            $q->where('iteration_id', $iteration);
        })->get();
        foreach($iteration_0_users as $user) {
            if($version == 1 || $version == 4) {
                $answered_articles = $user->answers()->get()->where('iteration_id', $iteration)->sortByDesc('id');
            } else if($version == 2 || $version == 3) {
                $answered_articles = $user->answers()->get()->where('iteration_id', $iteration);
            }

            if(count($answered_articles) > 0) {
                $num_topics = count(json_decode($answered_articles->first()->label_dist)); # Calc number of topics
                $num_answers = count($answered_articles);
                $user_profile = array_fill(0, $num_topics, (100/$num_topics)/100);

                if($version == 1 || $version == 2) {
                    
                    // Loop all articles and compute average label dist for user profile
                    foreach($answered_articles as $i => $article) {

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
                            $gradient = $num_answers >= 30 ? (1 / ($num_answers/$num_topics)) : 1;

                            // Add weighted difference to user profile. This weight decides the impact of a single article.
                            $scale = [-0.75, -0.25, 0, 0.25, 0.75];
                                
                            $weighted_diff = ($diff != 0 ? $diff * $scale[$article->pivot->relevance - 1] * $gradient : 0);

                            // Add weighted difference (Is deducted if negative)
                            $user_profile[$j] += $weighted_diff;
                        }

                        // Alter values to be between 0 and 1
                        $max = max($user_profile); $min = min($user_profile);
                        if($max != $min) {
                            // normalized = (x-min(x))/(max(x)-min(x))
                            foreach($user_profile as $j => $profile_val) {
                                $user_profile[$j] = ($profile_val - $min) / ($max - $min);
                            }

                            // Normalize values
                            $sum = array_sum($user_profile);
                            foreach($user_profile as $j => $profile_val) {
                                $user_profile[$j] = $profile_val / $sum;
                            }
                        }
                    }
                } else if($version == 3 || $version == 4) {
                    $sum_user_profile = (array) clone((object) $user_profile);

                    // Loop all articles and compute average label dist for user profile
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
                                
                            $weighted_diff = ($diff != 0 ? $diff * $scale[$article->pivot->relevance - 1] * $gradient : 0);

                            // Add weighted difference (Is deducted if negative)
                            $sum_user_profile[$j] += $weighted_diff;
                        }
                    }

                    if($sum_user_profile != $user_profile) {
                        foreach($sum_user_profile as $j => $val) {
                            $user_profile[$j] = $val / $num_answers;
                        }
    
                        // Alter values to be between 0 and 1
                        $max = max($user_profile); $min = min($user_profile);

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
                }

                // Remove old profile
                UserProfile::where('user_id', $user->id)->where('iteration_id', $iteration)->delete();

                // Create new profile
                $userProfile = new UserProfile;
                $userProfile->user_id = $user->id;
                $userProfile->token = $user->token;
                $userProfile->profile = json_encode($user_profile);
                $userProfile->iteration_id = $iteration;
                $userProfile->save();

                dump('Creating updated profile for user '.$user->id.'.');
                dump('Sum of profile '. array_sum($user_profile));
            } else {
                dump('No user answers found');
            }
        }

        dump('Iteration 1');
        $iteration = 1;
        $iteration_1_users = User::whereHas('profile', function($q) use($iteration) { 
                $q->where('iteration_id', $iteration - 1);
            })->get();
        foreach($iteration_1_users as $user) {
            if($version == 1 || $version == 4) {
                $answered_articles = $user->answers()->get()->where('iteration_id', $iteration)->sortByDesc('id');
            } else if($version == 2 || $version == 3) {
                $answered_articles = $user->answers()->get()->where('iteration_id', $iteration);
            }

            if(count($answered_articles) > 0) {
                $num_topics = count(json_decode($answered_articles->first()->label_dist)); # Calc number of topics
                $num_answers = count($answered_articles);
                $user_profile = json_decode($user->iterationProfile($iteration - 1)->profile);

                if($version == 1 || $version == 2) {
                    
                    // Loop all articles and compute average label dist for user profile
                    foreach($answered_articles as $i => $article) {

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
                            $gradient = $num_answers >= 30 ? (1 / ($num_answers/$num_topics)) : 1;

                            // Add weighted difference to user profile. This weight decides the impact of a single article.
                            $scale = [-0.75, -0.25, 0, 0.25, 0.75];
                                
                            $weighted_diff = ($diff != 0 ? $diff * $scale[$article->pivot->relevance - 1] * $gradient : 0);

                            // Add weighted difference (Is deducted if negative)
                            $user_profile[$j] += $weighted_diff;
                        }

                        // Alter values to be between 0 and 1
                        $max = max($user_profile); $min = min($user_profile);
                        if($max != $min) {
                            // normalized = (x-min(x))/(max(x)-min(x))
                            foreach($user_profile as $j => $profile_val) {
                                $user_profile[$j] = ($profile_val - $min) / ($max - $min);
                            }

                            // Normalize values
                            $sum = array_sum($user_profile);
                            foreach($user_profile as $j => $profile_val) {
                                $user_profile[$j] = $profile_val / $sum;
                            }
                        }
                    }
                } else if($version == 3 || $version == 4) {
                    $sum_user_profile = (array) clone((object) $user_profile);

                    // Loop all articles and compute average label dist for user profile
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
                                
                            $weighted_diff = ($diff != 0 ? $diff * $scale[$article->pivot->relevance - 1] * $gradient : 0);

                            // Add weighted difference (Is deducted if negative)
                            $sum_user_profile[$j] += $weighted_diff;
                        }
                    }

                    if($sum_user_profile != $user_profile) {
                        foreach($sum_user_profile as $j => $val) {
                            $user_profile[$j] = $val / $num_answers;
                        }
    
                        // Alter values to be between 0 and 1
                        $max = max($user_profile); $min = min($user_profile);

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
                }

                // Remove old profile
                UserProfile::where('user_id', $user->id)->where('iteration_id', $iteration)->delete();

                // Create new profile
                $userProfile = new UserProfile;
                $userProfile->user_id = $user->id;
                $userProfile->token = $user->token;
                $userProfile->profile = json_encode($user_profile);
                $userProfile->iteration_id = $iteration;
                $userProfile->save();

                dump('Creating updated profile for user '.$user->id.'.');
                dump('Sum of profile '. array_sum($user_profile));
            } else {
                dump('No user answers found');
            }
        }
    }
}

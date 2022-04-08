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
        foreach(User::all() as $j => $user) {
            $iteration = 0; # Set iteration number

            $articles = $user->answers()->get()->where('iteration_id', $iteration)->sortByDesc('id');

            if(count($articles) > 0) {
                $num_topics = count(json_decode($articles->first()->label_dist)); # Calc number of topics
                $total_num_answers = count($articles);
                // $user_profile = $this->latestProfile(); # Retrieve latest user profile
                
                # Reset user profile
                $user_profile = array_fill(0, $num_topics, (100/$num_topics)/100);
                
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

                // Remove old profile
                UserProfile::where('user_id', $user->id)->where('iteration_id', $iteration)->delete();

                dump('Creating updated profile for user '.$user->id.'.');

                // Create new profile
                $userProfile = new UserProfile;
                $userProfile->user_id = $user->id;
                $userProfile->token = $user->token;
                $userProfile->profile = json_encode($user_profile);
                $userProfile->iteration_id = $iteration;
                $userProfile->save();
            } else {
                dump('No user answers found');
            }
        }
    }
}

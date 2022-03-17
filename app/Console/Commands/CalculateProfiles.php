<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class CalculateProfiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'survey-app:calculate-profiles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate user profiles of label distribution';

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
        $users = User::all();

        foreach($users as $user) {
            $articles = $user->articles;
            
            // Loop all articles and compute average label dist for user profile
            $col_label_dist = [];
            foreach($articles as $i => $article) {
                $label_dist = json_decode($article->label_dist);
    
                // Add each weighetd label distribution over an article to collected array (rel score * single label dist)
                foreach($label_dist as $j => $val) {
                    if(isset($col_label_dist[$j])) {
                        $col_label_dist[$j] += $val * (($article->pivot->relevance - 1) / 4); // - 1 to get lowest value 0 (if not relevant at all)
                    } else {
                        $col_label_dist[$j] = $val * (($article->pivot->relevance - 1) / 4); // divided by 4 because 5 (-1) is max val
                    }
                } 
            }
    
            // Get average percentage over all articles
            $user->profile = array_map(function($val) use ($articles) { return $val / count($articles); }, $col_label_dist);
            $user->save();
        }

        return 1;
    }
}

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
            $all_label_distributions = json_decode($user->articles()->where('relevant', 1)->pluck('label_dist'));
            $sum_label_dist = [];
            foreach($all_label_distributions as $i => $label_dist) {
                $values = json_decode($label_dist);

                foreach($values as $j => $value) {
                    if(isset($sum_label_dist[$j])) {
                        $sum_label_dist[$j] += $value;
                    } else {
                        $sum_label_dist[$j] = $value;
                    }
                } 
            }

            $normalized_dist = array_map(function($dist) use ($all_label_distributions) { return $dist / count($all_label_distributions); }, $sum_label_dist);

            $user->profile = $normalized_dist;
            $user->save();
        }

        return 0;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class CreateUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'survey-app:create-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create users';

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
        $emailAdresses = [
            'annika.bergsten@elvenite.se',
            'martin.grenskog@elvenite.se',
            'maja.wahlquist@elvenite.se',
            'asa.johansson@elvenite.se',
            'erik.karlstrom@elvenite.se',
            'maria.moss@elvenite.se',
            'gun.jansson@elvenite.se',
            'guro.tollefsen@elvenite.se',
            'niklas.ivarsson@elvenite.se',
            'simon.wahlstrom@elvenite.se',
            'stefan.eriksson@elvenite.se',
            'asa.klevmarken@elvenite.se',
            'niklas.asberg@elvenite.se',
            'anna.skardin@elvenite.se',
            'maria.larsson@elvenite.se',
            'lisa.birath@elvenite.se',
            'arin.rashid@elvenite.se',
            'julia.farlin@elvenite.se',
            'agnes.lindell@elvenite.se',
            'niclas.lovsjo@elvenite.se'
        ];

        foreach($emailAdresses as $address) {
            $user = new User;
            $user->token = rand(0,99999);
            $user->email = $address;
            $user->save();
        }

        
        return 1;
    }
}

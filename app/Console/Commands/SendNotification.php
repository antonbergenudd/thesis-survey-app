<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\Notify;

class SendNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'survey-app:send-notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notification';

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
        Notify::dispatch();

        dump('Notifying all users..');
        
        return 0;
    }
}

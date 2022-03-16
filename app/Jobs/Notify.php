<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\NotifyMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class Notify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        foreach(User::all() as $user) {
            $details = [
                'title' => 'Glöm inte att utvärdera artiklarna.',
                'body' => 'Din kod är: '.$user->token,
                'link' => env('APP_URL').'?token='.$user->token
            ];
           
            Mail::to($user->email)->send(new NotifyMail($details));
        }
    }
}

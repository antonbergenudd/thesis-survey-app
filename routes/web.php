<?php

use Illuminate\Support\Facades\Route;
use App\Models\Article;
use App\Models\User;
use \App\Http\Middleware\EnsureTokenIsValid;
use \App\Http\Middleware\ValidateAdminIP;
use \Illuminate\Http\Response;
use Illuminate\Http\Request;
use App\Jobs\Notify;
use App\Charts\LabelDistChart;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/******************************************************************************************************************/
/* Public Routes */
/******************************************************************************************************************/

Route::get('/login', ['as' => 'login', 'uses' => function () {
    return view('login');
}]);

Route::post('/login', function (Request $request) {
    $user = User::where('token', $request->token)->first();
    if(isset($user)) {
        $user->last_accessed = date("Y-m-d");
        $user->save();

        return $user->token;
    }

    return response()->json(['errors' => ['token' => ['The token is invalid.']]], 422);
});

/******************************************************************************************************************/
/* User Routes */
/******************************************************************************************************************/

Route::get('/', function (Request $request) {
    // Retrieve user's non-answered articles
    $articles = Article::whereDoesntHave('users', function($q) use ($request) {
        $q->where('user_id', User::where('token', $request->token)->first()->id);
    })->get();

    return view('welcome')->with('articles', $articles);
})->middleware(EnsureTokenIsValid::class);

Route::post('/submit-answer', function (Request $request) {
    $user = User::where('token', $request->token)->first();
    $article_ids = [];
    foreach($request->data as $article) {
        $user->answers()->attach($article['id'], ['relevance' => $article['rel'], 'understandability' => $article['und'], 'length' => $article['len']]);
        $article_ids[] = $article['id'];
    }

    // Update profile with new answers
    $user->updateProfile($article_ids);
    
    return 'OK';
})->middleware(EnsureTokenIsValid::class);


/******************************************************************************************************************/
/* Admin Routes */
/******************************************************************************************************************/

Route::get('/explore', function (Request $request) {
    $users = User::all();
    
    foreach($users as $user) {
        // Get all rated articles
        $articles = $request->iteration ? $user->answers()->get()->where('iteration_id', $request->iteration) : $user->answers;

        if(count($articles) > 0) {
            // Loop all articles and compute average label dist for user profile
            $col_under = 0; $col_len = 0; $col_rel = 0;
            foreach($articles as $i => $article) {
                // Get overall values
                $col_under += $article->pivot->understandability;
                $col_len += $article->pivot->length;
                $col_rel += $article->pivot->relevance;
            }

            // Get average percentage over all articles
            $user->understandability = round($col_under / count($articles), 3);
            $user->length = round($col_len / count($articles), 3);
            $user->relevance = round($col_rel / count($articles), 3);

            # https://v6.charts.erik.cat/getting_started.html#screenshots
            # consoletvs/charts:6.*
            $data = $user->getProfile($request->iteration);
            $labelDistChart = new LabelDistChart;
            $labelDistChart->labels(array_keys($data));
            $labelDistChart->options([
                    'scales' => [
                        'yAxes' => [
                            [
                                'ticks' => [
                                    'max' => 1.0
                                ],
                            ],
                        ],
                    ],
                ]);
            $labelDistChart->dataset('Labels by distribution', 'bar', $data);
            $user->chart = $labelDistChart;
        }
    }

    return view('explore')->with('users', $users)->with('iteration', $request->iteration);
})->middleware(ValidateAdminIP::class);

Route::get('/explore/articles', function (Request $request) {
    $articles = $request->iteration ? Article::where('iteration_id', $request->iteration)->get() : Article::all();

    if(count($articles) > 0) {

        $num_topics = count(json_decode($articles->first()->label_dist));
        $total_dist = array_fill(0, $num_topics, 0);
        foreach($articles as $article) {
            # https://v6.charts.erik.cat/getting_started.html#screenshots
            # consoletvs/charts:6.*
            $data = json_decode($article->label_dist);
            $labelDistChart = new LabelDistChart;
            $labelDistChart->labels(array_keys($data));
            $labelDistChart->options([
                    'scales' => [
                        'yAxes' => [
                            [
                                'ticks' => [
                                    'max' => 1.0
                                ],
                            ],
                        ],
                    ],
                ]);
            $labelDistChart->dataset('Labels by distribution', 'bar', $data);
            $article->chart = $labelDistChart;
        
            foreach(json_decode($article->label_dist) as $i => $val)
                $total_dist[$i] += $val / $num_topics;
        }

        $data = $total_dist;
        $allArticlesChart = new LabelDistChart;
        $allArticlesChart->labels(array_keys($data));
        $allArticlesChart->options([
                'scales' => [
                    'yAxes' => [
                        [
                            'ticks' => [
                                'max' => 1.0
                            ],
                        ],
                    ],
                ],
            ]);
        $allArticlesChart->dataset('Labels by distribution', 'bar', $data);
        $articles->all_chart = $allArticlesChart;

    }

    return view('explore_articles')->with('articles', $articles)->with('iteration', $request->iteration);
})->middleware(ValidateAdminIP::class);

Route::get('/download/answers', function (Request $request) {

    $fileName = $request->token.'_answers_'.$request->iteration.'.csv';
    $user = User::where('token', $request->token)->first();

    $headers = array(
        "Content-type"        => "text/csv",
        "Content-Disposition" => "attachment; filename=$fileName",
        "Pragma"              => "no-cache",
        "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
        "Expires"             => "0"
    );

    $columns = array('Rel', 'Len', 'Und', 'Diff');

    $callback = function() use($user, $columns) {
        $file = fopen('php://output', 'w');
        fputcsv($file, $columns);

        foreach ($user->answers as $answer) {
            $row['Rel']  = $answer->relevance;
            $row['Len']    = $answer->length;
            $row['Und']    = $answer->understandability;
            $row['Diff']  = $answer->difference;

            fputcsv($file, array($row['Rel'], $row['Len'], $row['Und'], $row['Diff']));
        }

        fclose($file);
    };

    return response()->stream($callback, 200, $headers);
})->middleware(ValidateAdminIP::class);

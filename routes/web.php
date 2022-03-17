<?php

use Illuminate\Support\Facades\Route;
use App\Models\Article;
use App\Models\User;
use \App\Http\Middleware\EnsureTokenIsValid;
use \App\Http\Middleware\ValidateAdminIP;
use \Illuminate\Http\Response;
use Illuminate\Http\Request;
use App\Jobs\Notify;

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
    $user = User::where('token', $request->token)->first();
    
    $articles = Article::all();
    $answeredArticles = $user->articles()->get();

    $articles = $articles->filter(function ($article) use ($answeredArticles) {
        return !in_array($article->id, $answeredArticles->pluck('id')->toArray());
    });

    return view('welcome')->with('articles', $articles);
})->middleware(EnsureTokenIsValid::class);

Route::post('/article', function (Request $request) {
    $iteration = env('ITERATION');

    $user = User::where('token', $request->token)->first();
    $article = Article::find($request->id);
    $article->users()->attach($user->id, ['relevant' => $request->resp, 'iteration' => $iteration]);


    return 'OK';
})->middleware(EnsureTokenIsValid::class);

Route::post('/submit-answer', function (Request $request) {
    $user = User::where('token', $request->token)->first();

    foreach($request->data as $article) {
        $db_article = Article::find($article['id']);
        $db_article->users()->attach($user->id, ['relevance' => $article['rel'], 'understandability' => $article['und'], 'length' => $article['len']]);
    }

    return 'OK';
})->middleware(EnsureTokenIsValid::class);


/******************************************************************************************************************/
/* Admin Routes */
/******************************************************************************************************************/

Route::get('/explore', function (Request $request) {
    $users = User::all();
    
    foreach($users as $user) {
        $articles = $request->iteration ? $user->articles()->where('iteration', $request->iteration)->get() : $user->articles;
        
        // Loop all articles and compute average label dist for user profile
        $col_label_dist = [];
        $col_under = 0;
        $col_len = 0;
        $col_rel = 0;
        foreach($articles as $i => $article) {
            $label_dist = json_decode($article->label_dist);

            // Add each weighetd label distribution over an article to collected array (rel score * single label dist)
            foreach($label_dist as $j => $val) {
                if(isset($col_label_dist[$j])) {
                    $col_label_dist[$j] += $val * (($article->pivot->relevance - 1) / 4);
                } else {
                    $col_label_dist[$j] = $val * (($article->pivot->relevance - 1) / 4);
                }
            } 

            $col_under += $article->pivot->understandability;
            $col_len += $article->pivot->length;
            $col_rel += $article->pivot->relevance;
        }

        // Get average percentage over all articles
        $user->label_dist = array_map(function($val) use ($articles) { return $val / count($articles); }, $col_label_dist);
        $user->understandability = $col_under / count($articles);
        $user->length = $col_len / count($articles);
        $user->relevance = $col_rel / count($articles);
    }

    return view('explore')->with('users', $users)->with('iteration', $request->iteration);
})->middleware(ValidateAdminIP::class);

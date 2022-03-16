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


/******************************************************************************************************************/
/* Admin Routes */
/******************************************************************************************************************/

Route::get('/explore', function (Request $request) {
    $users = User::all();
    
    foreach($users as $user) {
        if($request->iteration) {
            $articles_data = json_decode($user->articles()->where('relevant', 1)->where('iteration', $request->iteration)->pluck('label_dist'));
           
            $user->relevant_articles = $user->articles()->where('relevant', 1)->where('iteration', $request->iteration)->count();
            $user->non_relevant_articles = $user->articles()->where('relevant', 0)->where('iteration', $request->iteration)->count();
            $user->total_articles = $user->articles()->where('iteration', $request->iteration)->count();
        } else {
            $articles_data = json_decode($user->articles()->where('relevant', 1)->pluck('label_dist'));

            $user->relevant_articles = $user->articles()->where('relevant', 1)->count();
            $user->non_relevant_articles = $user->articles()->where('relevant', 0)->count();
            $user->total_articles = $user->articles()->count();
        }

        $label_dist = [];
        foreach($articles_data as $i => $data) {
            $values = json_decode($data);

            // article 0 label 0 += value[0]
            // article 0 label 1 += value[1]
            foreach($values as $j => $value) {
                if(isset($label_dist[$j])) {
                    $label_dist[$j] += $value;
                } else {
                    $label_dist[$j] = $value;
                }
            } 
        }

        $arr_mod = array_map( function($val) use ($articles_data) { return $val / count($articles_data); }, $label_dist);
        $user->label_dist = $arr_mod;
    }

    return view('explore')->with('users', $users)->with('iteration', $request->iteration);
})->middleware(ValidateAdminIP::class);

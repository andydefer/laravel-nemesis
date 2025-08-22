1. API Routes


If your application will also offer a stateless API, you may enable API routing using the install:api Artisan command:

php artisan install:api

The install:api command installs Laravel Sanctum, which provides a robust, yet simple API token authentication guard which can be used to authenticate third-party API consumers, SPAs, or mobile applications. In addition, the install:api command creates the routes/api.php file:

Route::get('/user', function (Request $request) {

    return $request->user();

})->middleware('auth:sanctum');

The routes in routes/api.php are stateless and are assigned to the api middleware group. Additionally, the /api URI prefix is automatically applied to these routes, so you do not need to manually apply it to every route in the file. You may change the prefix by modifying your application's bootstrap/app.php file:

->withRouting(

    api: __DIR__.'/../routes/api.php',

    apiPrefix: 'api/admin',

    // ...

)



2.  Imperativement definir les routes qui utiliserons le middleware dans api.php pour permetre leur appel et eviter les problemes de cross origin

3. Il faut ajouter le middleware dans bootstrap/app.php
Middleware Aliases

You may assign aliases to middleware in your application's bootstrap/app.php file. Middleware aliases allow you to define a short alias for a given middleware class, which can be especially useful for middleware with long class names:

use Kani\Nemesis\Http\Middleware\NemesisMiddleware;

->withMiddleware(function (Middleware $middleware) {

    $middleware->alias([

        'nemesis' => NemesisMiddleware::class

    ]);

})

Route::get('/profile', function () {
    // ...
})->middleware('nemesis');

Once the middleware alias has been defined in your application's bootstrap/app.php file, you may use the alias when assigning the middleware to routes:

4. Donner le token soit dans le bearer soit dans le query pour le projet qui va consommer

const API_URL = 'http://localhost:8001/api/club27/';

const API_TOKEN = 'crrxnjbAucrzMl8FvlRDQHwJSmvET05ncqcX3LuO';

fetch(`${API_URL}?token=${API_TOKEN}`, {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${API_TOKEN}`,
    'Content-Type': 'application/json',
  },
})
  .then(res => res.json())
  .then(data => console.log(data))
  .catch(err => console.error(err));


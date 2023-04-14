<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->post('/courier/check', 'NimbuspostController@check_courier');
$router->post('/shipment/new_shipment', 'NimbuspostController@create_new_shipment');
$router->post('/shipment/request_pickup', 'NimbuspostController@create_request_pickup');

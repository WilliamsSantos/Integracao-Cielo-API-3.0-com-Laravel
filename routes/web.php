<?php

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

Route::get('/', function ()
{
    return view('form');
});

Route::post('/cielo/efetuandoVenda', 'CieloController@efetuandoVenda')->name('/cielo/efetuandoVenda');
Route::post('/cielo/resAutenticacao', 'CieloController@resAutenticacao')->name('/cielo/resAutenticacao');



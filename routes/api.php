<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
//檔案上傳
Route::post('/uploadSTIX', [App\Http\Controllers\FileController::class, 'uploadSTIX']);
Route::post('/upload', [App\Http\Controllers\FileController::class, 'upload']);
Route::post('/updownSTIX', [App\Http\Controllers\FileController::class, 'updownSTIX']);
Route::post('/updownTxt', [App\Http\Controllers\FileController::class, 'updownTxt']);
Route::post('/uploadEnc', [App\Http\Controllers\FileController::class, 'uploadEnc']);
Route::post('/requestFile', [App\Http\Controllers\FileController::class, 'requestFile']);
Route::post('/uploadBF', [App\Http\Controllers\FileController::class, 'uploadBF']);


Route::post('/register', [App\Http\Controllers\MemberController::class, 'register']); //註冊
Route::post('/login', [App\Http\Controllers\MemberController::class, 'login']); //登入
Route::post('/logout', [App\Http\Controllers\MemberController::class, 'logout']); //登出
Route::get('/getPK', [App\Http\Controllers\MemberController::class, 'getPK']); //Public key
Route::get('/getRekeyList', [App\Http\Controllers\RekeyController::class, 'getRekeyList']); //Rekey
Route::put('/updateRekey', [App\Http\Controllers\RekeyController::class, 'updateRekey']);
Route::get('/getFileCount', [App\Http\Controllers\FileController::class, 'getFileCount']); 
Route::get('/getNum', [App\Http\Controllers\RekeyController::class, 'getNum']);

// PRE oracle
Route::post('/downloadIPFS', [App\Http\Controllers\FileController::class, 'downloadIPFS']);
Route::post('/uploadIPFS', [App\Http\Controllers\FileController::class, 'uploadIPFS']);


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
Route::post('/download', [App\Http\Controllers\FileController::class, 'download']);
Route::post('/uploadEnc', [App\Http\Controllers\FileController::class, 'uploadEnc']);
Route::post('/requestFile', [App\Http\Controllers\FileController::class, 'requestFile']);
Route::post('/uploadReEnc', [App\Http\Controllers\FileController::class, 'uploadReEnc']);
Route::post('/downloadRe', [App\Http\Controllers\RefileController::class, 'downloadRe']);

Route::post('/register', [App\Http\Controllers\MemberController::class, 'register']); //註冊
Route::post('/login', [App\Http\Controllers\MemberController::class, 'login']); //登入
Route::post('/logout', [App\Http\Controllers\MemberController::class, 'logout']); //登出
Route::get('/getPK', [App\Http\Controllers\MemberController::class, 'getPK']); //Public key
Route::get('/getRekeyList', [App\Http\Controllers\RekeyController::class, 'getRekeyList']); //Rekey
Route::get('/getReEncFileList', [App\Http\Controllers\RefileController::class, 'getReEncFileList']); //ReEncFile
Route::put('/updateRekey', [App\Http\Controllers\RekeyController::class, 'updateRekey']);
Route::get('/getFileCount', [App\Http\Controllers\FileController::class, 'getFileCount']); 


Route::get('/test', [App\Http\Controllers\FileController::class, 'testbloom']);
Route::get('/rar', [App\Http\Controllers\FileController::class, 'rar']);
Route::get('/ipfs', [App\Http\Controllers\FileController::class, 'ipfs']);
Route::get('/ipfsget', [App\Http\Controllers\FileController::class, 'ipfsget']);
Route::post('/uploadPRE', [App\Http\Controllers\FileController::class, 'uploadPRE']);

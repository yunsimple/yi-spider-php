<?php
use think\facade\Route;

Route::post('request_queue', 'spider/Spider/index');
Route::get('spider', 'spider/Spider/spider');
Route::get('test', 'spider/Spider/test');
Route::get('proxy', 'spider/Spider/getProxyIP');
Route::get('update_proxy', 'spider/Spider/updateProxy');
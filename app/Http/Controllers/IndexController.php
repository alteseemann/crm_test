<?php

namespace App\Http\Controllers;

use App\Models\token;
use Carbon\Carbon;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    public function index (){
        $last_refresh_token = token::orderBy('created_at', 'DESC')->first();
        $data['show_button']=true;
        if ($last_refresh_token){
            $update             = $last_refresh_token->updated_at;
            $days_left          = ceil((Carbon::now()->getTimestamp() - Carbon::parse($update)->getTimestamp())/86400);
            $data['show_button']= false;
            if ($days_left >= 90){//если прошло больше 3 месяцев с последней активности, отображаем на странице кнопку получения кода авторизации
                $data['show_button']=true;
            }
        }

        return view('welcome')->with($data);
    }
}

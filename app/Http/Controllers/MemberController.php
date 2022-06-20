<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use http\Exception;
use Illuminate\Support\Facades\Auth;

class MemberController extends Controller
{
    //
    public function register(Request $request)
    {
        // $this->middleware('guest');
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:12'],
            'pk' => ['required', 'string', 'min:132', 'max:132'],
        ]);

//        $apiToken = Str::random(10);
        $create = User::create([
            'email' => $request['email'],
            'password' => $request['password'],
            'pk' => $request['pk'],
        ]);

        $create->remember_token = str::random(10);
        $create->save();

        if ($create){
//            return "Register as a fucknormal user. Your api token is $apiToken";
            $member = User::where('email', $request->email)->where('password', $request->password)->first();
            $id = $member->id;
            return response()->json($id);
        } else{
            return response()->json('Email已註冊', 200);
        }
    }
    public function login(Request $request)
    {
        $member = User::where('email', $request->email)->where('password', $request->password)->first();
        $apiToken = Str::random(10); //隨機產生一組10個英數字組成的字串
        if($member)
        {   
            $apiToken = Str::random(10);
            //session()->forget('user_id');
            //session()->put('user_id', $member->id);
            $id = $member->id;
            if ($member->update(['api_token'=>$apiToken]))
            {
                    $member->remember_token = $apiToken;
                    $member->save();
                    
                    return response()->json("Success,$id", 200);
            }
        }
        else
        {
            return response()->json('Wrong email or password!', 200);
        }
    }
    public function logout(Request $request)
    {   
        $member = Test::where('email', $request->email)->where('password', $request->password)->first();
        $member->remember_token = 'logged out';
        $member->save();
        Auth::logout();
        //清除Session
        session()->forget('user_id');
        return response()->json('Imlogout', 200);
    }
    public function sendResponse($result, $message)
    {
        $response = [
            'success' => true,
            'data' => $result,
            'message' => $message,
        ];
        return response()->json($response, 200);
    }

    public function sendError($error, $errorMessages = [], $code = 404)
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if(!empty($errorMessages)){
            $response['data'] = $errorMessages;
        }
        return response()->json($response, $code);
    }
    public function getPK(Request $request)
    {
        Validator::make($request->all(), [
            'ID' => 'required',
        ])->validate();
        $pk = User::select('pk')->where('id', '=', $request->ID)->get();
        return response()->json($pk);
    }
}

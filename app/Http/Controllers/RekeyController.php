<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Rekey;

class RekeyController extends Controller
{
    //取得特定ID的Rekey清單
    public function getRekeyList(Request $request)
    {
        Validator::make($request->all(), [
            'ID' => 'required',
        ])->validate();
        $requester_list = Rekey::select('id', 'requester_id', 'file_id')->where('owner_id', $request->ID)->whereNull('rekey')->get();
        $result = array();
        foreach($requester_list as $r){
            $pk = User::where('id', $r->requester_id)->value('pk');
            $result[$r->id] = array($pk, $r->file_id, $r->requester_id);
        }
        return response()->json($result);
    }
    //儲存Rekey
    public function updateRekey(Request $request)
    {
        Validator::make($request->all(), [
            'ID' => 'required',
            'Rekey' => 'required',
        ])->validate();
        $info = Rekey::find($request->ID);
        $info->rekey = $request->Rekey;
        $info->save(); 
        return response()->json('Success');
    }
    public function getNum()
    {
        $count = Rekey::where('rekey', '!=', '')->count();
        return response()->json($count);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Refile;
use Illuminate\Support\Facades\Storage;

class RefileController extends Controller
{
    public function getReEncFileList(Request $request)
    {
        Validator::make($request->all(), [
            'ID' => 'required',
        ])->validate();
        $user = User::find($request->ID);
        $file_list = $user->refiles()->get();
        $result = array();
        foreach($file_list as $file){
            $result[$file->id] = $file->name;
        }
        return response()->json($result);
    }
    public function downloadRe(Request $request)
    {
        Validator::make($request->all(), [
            'ID' => 'required',
        ])->validate();
        if ($request->ID) {
            $file_id = $request->ID;
            $path = Refile::find($file_id)->path;
            return Storage::disk('public')->download($path);
        } else 
            return response()->json('No id');
    }
}

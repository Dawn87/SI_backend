<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use ZipArchive;
use RarArchive;
use rannmann\PhpIpfsApi\IPFS;
use PharData;
use Auth;
use App\Models\User;
use App\Models\File;
use App\Models\Bloomfilter;
use App\Models\Rekey;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    
    public function uploadSTIX(Request $request)
    {
        Validator::make($request->all(), [
            'file' => 'required',
        ])->validate();
        if($request->hasFile('file')){
            $fileName = time().'.'.$request->file->extension();
            if (strpos($fileName, ".rar") || strpos($fileName, ".zip") || strpos($fileName, ".json")) {
                $request->file->move(public_path('storage')."/STIX", $fileName);
                $path = public_path('storage').'/STIX/'.$fileName;
                if (strpos($fileName, ".rar")) {
                    $rar = RarArchive::open($path);
                    if($rar)
                        $rar_entries = $rar->getEntries();
                    if ($rar_entries === FALSE)
                        return response()->json("Could not retrieve entries.");
                    if (empty($rar_entries))
                        return response()->json("No valid entries found.");

                    $ip_arr = array();
                    foreach($rar_entries as $entry) {
                        $entry->extract(substr($path,0,-4));
                        $stream = $entry->getStream();
                        if ($stream === FALSE)
                            return response()->json("Failed opening file:".$entry);
                        else {
                            $contents = stream_get_contents($stream);
                            $pattern = Str::between($contents, '"pattern": "[', ']",');
                            $ip = Str::between($pattern, "'", "'");
                            array_push($ip_arr, $ip);
                        }
                    }
                    $rar->close();
                    return($this->Bloom_arr($ip_arr));
                }
                elseif (strpos($fileName, ".zip")) {
                    $zip=new ZipArchive;
                    echo "zip";
                    //todo
                }
                else {
                    $contents = file_get_contents($path);
                    $pattern = Str::between($contents, '"pattern": "[', ']",');
                    $ip = Str::between($pattern, "'", "'"); 
                    $bloom = unserialize(Bloomfilter::first()->bloomfilter);
                    if ($bloom->has($ip)) {
                        return response()->json('IP exists!'.$ip);
                    }
                    else {
                        $bloom->set($ip);

                        $serBloom = serialize($bloom);
                        //將檔案資訊新增進資料庫
                        /*$user_id = $request->ID;
                        $user = User::find($user_id);
                        $user->files()->create(['name' => $fileName, 'path' => Str::after($path, public_path('storage')), 'bloom' => $serBloom]);
                        */
                        //Smart contract
                        /*$id =  File::where('name', $fileName)->value('id');
                        $ipfs = new IPFS("localhost", "8080", "5001"); // leaving out the arguments will default to these values
                        $hash = $ipfs->addFromPath($path);*/
                        
                        $bl = json_encode($bloom);
                        $set = Str::between($bl, '"set":"', '","hashes');
                        return response()->json('Success.<br> Bloom filter:'.$set.'bf:'.$serBloom);

                        //return response()->json('Success. Bloom filter:'.$set. 'id0:'.$id.'uid:'.$user_id.'filename:'.$fileName.'bf:'.$serBloom.'path:'.$hash);

                    }
                }
            } else 
                return response()->json('格式錯誤');
        }     
        else
            return response()->json('Upload Failed');
    }
        
    // normal
    public function upload(Request $request)
    {
        Validator::make($request->all(), [
            'file' => 'required',
        ])->validate();
        if($request->hasFile('file')){
            $fileName = time().'.'.$request->file->extension();
            if (strpos($fileName, ".rar") || strpos($fileName, ".zip") || strpos($fileName, ".txt")) {
                $request->file->move(public_path('storage').'/txt/', $fileName);
                $path = public_path('storage').'/txt/'.$fileName;
                if (strpos($fileName, ".rar")) {
                    $rar = RarArchive::open($path);
                    if($rar)
                        $rar_entries = $rar->getEntries();
                    if ($rar_entries === FALSE)
                        return response()->json("Could not retrieve entries.");
                    if (empty($rar_entries))
                        return response()->json("No valid entries found.");
    
                    $ip_arr = array();
                    foreach($rar_entries as $entry) {
                        $entry->extract(substr($path,0,-4));
                        $stream = $entry->getStream();
                        if ($stream === FALSE)
                            return response()->json("Failed opening file:".$entry);
                        else {
                            $contents = stream_get_contents($stream);
                            $ip_list = explode("\r\n", $contents);
                            foreach ($ip_list as $ip)
                                array_push($ip_arr, $ip);
                        }
                    }
                    $rar->close();
                    return($this->Bloom_arr($ip_arr));
                } elseif (strpos($fileName, ".zip")) {
                    $zip=new ZipArchive;
                    echo "zip";
                } elseif (strpos($fileName, ".txt")) {
                    $contents = file_get_contents($path);
                    $ip_arr = explode("\r\n", $contents);
                    //Exception空行處理
                    $bloom = unserialize(Bloomfilter::first()->bloomfilter);
                    foreach($ip_arr as $ip) {
                        if ($bloom->has($ip)) {
                            return response()->json('IP exists!'.$ip);
                        }
                        else {
                            $bloom->set($ip);
                        }
                    }
                    $serBloom = serialize($bloom);
                    //將檔案資訊新增進資料庫
                    /*$user_id = $request->ID;
                    $user = User::find($user_id);
                    $user->files()->create(['name' => $fileName, 'path' => Str::after($path, public_path('storage')), 'bloom' => $serBloom]);
                    */

                    $bl = json_encode($bloom);
                    $set = Str::between($bl, '"set":"', '","hashes');
                    return response()->json('Success.<br> Bloom filter:'.$set.'bf:'.$serBloom);

                }
            } else {
                return response()->json('格式錯誤');
            }
        }     
        else
        {
            return response()->json('上傳失敗');
        }
    }
    //計算布隆過濾器
    public function Bloom_arr(Array $ip_arr)
    {
        $bloom = unserialize(Bloomfilter::first()->bloomfilter);
        foreach($ip_arr as $ip) {
            if ($bloom->has($ip)) {
                return response()->json('IP exists!'.json_encode($ip));
            }
            else {
                $bloom->set($ip);
            }
        }
        $bl = json_encode($bloom);
        $set = Str::between($bl, '"set":"', '","hashes');
        return response()->json('Success.<br> Bloom filter:'.$set);
    }
    public function updownSTIX(Request $request)
    {
        # 下載前上傳檔案
        Validator::make($request->all(), [
            'file' => 'required',
        ])->validate();
        if($request->hasFile('file')){
            $fileName = time().'.'.$request->file->extension();
            if (strpos($fileName, ".rar") || strpos($fileName, ".zip") || strpos($fileName, ".json")) {
                $request->file->move(public_path('storage')."/STIX_Down", $fileName);
                $path = public_path('storage').'/STIX_Down/'.$fileName;
                if (strpos($fileName, ".rar")) {
                    $rar = RarArchive::open($path);
                    if($rar)
                        $rar_entries = $rar->getEntries();
                    if ($rar_entries === FALSE)
                        return response()->json("Could not retrieve entries.");
                    if (empty($rar_entries))
                        return response()->json("No valid entries found.");

                    $ip_arr = array();
                    foreach($rar_entries as $entry) {
                        $entry->extract(substr($path,0,-4));
                        $stream = $entry->getStream();
                        if ($stream === FALSE)
                            return response()->json("Failed opening file:".$entry);
                        else {
                            $contents = stream_get_contents($stream);
                            $pattern = Str::between($contents, '"pattern": "[', ']",');
                            $ip = Str::between($pattern, "'", "'");
                            array_push($ip_arr, $ip);
                        }
                    }
                    $rar->close();
                    return($this->Bloom_arr($ip_arr));
                }
                elseif (strpos($fileName, ".zip")) {
                    $zip=new ZipArchive;
                    echo "zip";
                    //todo
                }
                else {
                    $contents = file_get_contents($path);
                    $pattern = Str::between($contents, '"pattern": "[', ']",');
                    $ip = Str::between($pattern, "'", "'"); 
                    //先計算布隆過濾器
                    //$bloom = unserialize(config('bloomfilter.bloom'));
                    $bloom = unserialize(Bloomfilter::first()->bloomfilter);
                    if ($bloom->has($ip)) {
                        return response()->json('IP exists!');
                    }
                    else {
                        $bloom->set($ip);
                        $bl = json_encode($bloom);
                        $set = Str::between($bl, '"set":"', '","hashes');

                        // blockchain data
                        $data = json_decode($request->data);
                        return($this->compareBloom($ip, $set, $data));                       
                    }
                }
            } else 
                return response()->json('格式錯誤');
        }
        else
            return response()->json('Upload Failed');
    }
    public function updownTxt(Request $request)
    {
        Validator::make($request->all(), [
            'file' => 'required',
        ])->validate();
        if($request->hasFile('file')){
            $fileName = time().'.'.$request->file->extension();
            if (strpos($fileName, ".rar") || strpos($fileName, ".zip") || strpos($fileName, ".txt")) {
                $request->file->move(public_path('storage').'/txt_Down/', $fileName);
                $path = public_path('storage').'/txt_Down/'.$fileName;
                if (strpos($fileName, ".rar")) {
                    $rar = RarArchive::open($path);
                    if($rar)
                        $rar_entries = $rar->getEntries();
                    if ($rar_entries === FALSE)
                        return response()->json("Could not retrieve entries.");
                    if (empty($rar_entries))
                        return response()->json("No valid entries found.");
    
                    $ip_arr = array();
                    foreach($rar_entries as $entry) {
                        $entry->extract(substr($path,0,-4));
                        $stream = $entry->getStream();
                        if ($stream === FALSE)
                            return response()->json("Failed opening file:".$entry);
                        else {
                            $contents = stream_get_contents($stream);
                            $ip_list = explode("\r\n", $contents);
                            foreach ($ip_list as $ip)
                                array_push($ip_arr, $ip);
                        }
                    }
                    $rar->close();
                    return($this->Bloom_arr($ip_arr));
                } elseif (strpos($fileName, ".zip")) {
                    $zip=new ZipArchive;
                    echo "zip";
                } elseif (strpos($fileName, ".txt")) {
                    $contents = file_get_contents($path);
                    $ip_arr = explode("\r\n", $contents);
                    //Exception空行處理
                    //先計算布隆過濾器
                    $bloom = unserialize(Bloomfilter::first()->bloomfilter);
                    $rp = array();
                    foreach($ip_arr as $ip) {
                        if ($bloom->has($ip)) {
                            return response()->json('IP exists!'.$ip);
                        }
                        else {
                            $bloom->set($ip);
                            array_push($rp, $ip);
                        }
                    }
                    $bl = json_encode($bloom);
                    $set = Str::between($bl, '"set":"', '","hashes');

                    // blockchain data
                    $data = json_decode($request->data);
                    //$bloom = unserialize($data[1]->{"2"});
                    return($this->compareBloom($rp, $set, $data));
                }
            } else {
                return response()->json('格式錯誤');
            }
        }     
        else
        {
            return response()->json('上傳失敗');
        }
    }
    public function compareBloom($ip, $origin, $data)
    {
        // 比較Bloom Filter
        //$allBloom = File::select('id', 'name', 'bloom')->get();

        $match = array();
        $match_proportion = array();
        if (is_array($ip)) {
            //for ($i = 0; $i < count($allBloom); $i++) {
            for ($i = 0; $i < count($data); $i++) {
                //符合數
                $match_num = 0;
                //$bloom = unserialize($allBloom[$i]->bloom);
                $bloom = unserialize($data[$i]->{"2"});
                foreach ($ip as $ip_data) {
                    if ($bloom->has($ip_data)) 
                        $match_num+=1;
                }
                if ($match_num != 0) {
                    $jsbl = json_encode($bloom);
                    $set = Str::between($jsbl, '"set":"', '","hashes');
                    //$match[$allBloom[$i]->id] = array($set, $allBloom[$i]->name);
                    $match[$i+1] = array($set, $data[$i]->{"1"});
                    //$match_proportion[$allBloom[$i]->id] = round(($match_num / count($ip))*100,2);
                    $match_proportion[$i+1] = round(($match_num / count($ip))*100,2);
                }
            }
            //大到小排序
            arsort($match_proportion);
            $match_sort = array();
            foreach ($match_proportion as $key => $value) {
                $match_sort[$key] = $match[$key];
            }
            $list = [];
            foreach ($match_proportion as $k => $v) {
                array_push($list, array("key" => $k, "value" => $v));
            }
            $ip_arr = json_encode($ip);
            $result = json_encode($match_sort);
            //$result_pro = json_encode($match_proportion);
            $result_pro = json_encode($list);
            return response()->json('Success add ip to Bloom filter:'.$ip_arr.'<br>Bloom filter:'.$origin.'<br>Result:'.$result.'Proportion:'.$result_pro);
        } else {
            //for ($i = 0; $i < count($allBloom); $i++) {
            for ($i = 0; $i < count($data); $i++) {
                //$bloom = unserialize($allBloom[$i]->bloom);
                $bloom = unserialize($data[$i]->{"2"});
                if ($bloom->has($ip)) {
                    $jsbl = json_encode($bloom);
                    $set = Str::between($jsbl, '"set":"', '","hashes');
                    //$match[$allBloom[$i]->id] = array($set, $allBloom[$i]->name);
                    $match[$i+1] = array($set, $data[$i]->{"1"});
                    //$match_proportion[$allBloom[$i]->id] = '100';
                    $match_proportion[$i+1] = '100';
                }
            }
            $list = [];
            foreach ($match_proportion as $k => $v) {
                array_push($list, array("key" => $k, "value" => $v));
            }
            $result = json_encode($match);
            $result_pro = json_encode($list);
            return response()->json('Success add ip to Bloom filter:'.$ip.'<br>Bloom filter:'.$origin.'<br>Result:'.$result.'Proportion:'.$result_pro);
        }
        
    }
    public function download(Request $request)
    {
        Validator::make($request->all(), [
            'ID' => 'required',
        ])->validate();
        if ($request->ID) {
            $file_id = $request->ID;
            $path = File::find($file_id)->path;
            return Storage::disk('public')->download($path);
        } else 
            return response()->json('No id');
        
    }
    public function requestFile(Request $request)
    {
        Validator::make($request->all(), [
            'File_id' => 'required',
            'User_id' => 'required',
        ])->validate();
        if ($request->File_id) {
            $file_id = $request->File_id;
            $owner_id = File::find($file_id)->user_id;
            $requester_id = $request->User_id;
            $create = Rekey::create([
                'owner_id' => $owner_id,
                'requester_id' => $requester_id,
                'file_id' => $file_id,
            ]);
            $create->save();
            if ($create)
                return response()->json('Success');
            else
                return response()->json('Fail');
        } else 
            return response()->json('No id');
    }
    public function rar()
    {
        $zip = new ZipArchive;
        //$fileName = 'upload1.zip';
        $fileName = 'test.rar';
        //$fileName = 'QmVkx2nBe4GnKybR7c9NDdYq4kYAUqcNjQz2WrCgAhb3Kt.rar';
        $path = public_path('storage').'/'.$fileName;
        $rar = RarArchive::open($path);
        /*$phar = new PharData($path);
        if ($phar)
            $phar->extractTo(public_path('storage'), null, true);*/
        /*if($zip->open($path)===TRUE){
            $zip->extractTo(public_path('storage').'/backup'); //避免覆蓋，將解壓縮資料放進該資料夾
            $zip->close();
            echo "解壓縮完成";
        }*/
        
        if($rar){
            $rar_entries = $rar->getEntries();
            if ($rar_entries === FALSE)
                die("Could not retrieve entries.");
            echo "Found " . count($rar_entries) . " entries.\n";

            foreach ($rar_entries as $e) {
                echo $e;
                echo "\n";
            }
            echo "<br>";
            if (empty($rar_entries))
                die("No valid entries found.");

            // 解壓縮
            foreach($rar_entries as $entry){
                $entry->extract(substr($path,0,-4));
            }

            // 讀檔
            $stream = reset($rar_entries)->getStream();
            if ($stream === FALSE)
                die("Failed opening first file");
            $stream1 = next($rar_entries)->getStream();
            $rar->close();
            echo "Content of first one follows:\n";
            echo stream_get_contents($stream);
            echo "<br>";
            echo stream_get_contents($stream1);

            fclose($stream);
        }
        else
        {
            echo "失敗".$path;
        }
    }
    public function ipfs()
    {
        $fileName = 'test.txt';
        $path = public_path('storage').'/'.$fileName;
        echo "File:".$fileName;
        echo "<br>";
        echo "Path:".$path;
        echo "<br>Adds file to IPFS...<br>";
        // connect to ipfs daemon API server
        $ipfs = new IPFS("localhost", "8080", "5001"); // leaving out the arguments will default to these values
        $hash = $ipfs->addFromPath($path);
        echo "Hash:".$hash;
        echo '<br>';
        //echo $ipfs->cat($hash);
        echo "IPFS getting...:";
        $ipfs_file = $ipfs->get($hash);
        if ($ipfs_file) {
            echo "success<br>";
            $path1 = public_path('storage').'/'.$hash.'.gz';
            echo "Path:".$path1;
            // 解壓縮
            echo "<br>解壓縮:";
            $phar = new PharData($path1);
            if($phar){
                /*foreach($phar as $file) {
                    echo $file."<br>";
                }*/
                //$phar->decompress();
                //$phar1 = new PharData(substr($path1, 0, -3));
                $phar->extractTo(substr($path1, 0, -3));
                echo "success";
            }
            else
            {
                echo "失敗";
            }
        }
        else {
            echo "fail";
        }
    }
    public function ipfsget()
    {
        $ipfs = new IPFS("localhost", "8080", "5001"); // leaving out the arguments will default to these values
        $hash = "Qmd4yCe4gUrtKg9GFn1PpXtSn15bTDhX4thTtZUF2XaKtg";
        //file:rar
        //$hash = "QmZdHaKfGbLgDGyq5rm7bD6f49eWS25mtQKnhYK7CDVGax";
        $ipfs_file = $ipfs->get($hash);
        if ($ipfs_file) {
            echo "success<br>";
            $path = public_path('storage').'/ipfsGet/'.$hash.'.gz';
            echo "Path:".$path;
            // 解壓縮
            echo "<br>解壓縮:";
            $phar = new PharData($path);
            if($phar){
                /*foreach($phar as $file) {
                    echo $file."<br>";
                }*/
                //$phar->decompress();
                //$phar1 = new PharData(substr($path1, 0, -3));
                $phar->extractTo(substr($path, 0, -3));
                echo "success";
            }
            else
            {
                echo "失敗";
            }
            
        }
        else {
            echo "fail";
        }
    }
    public function uploadPRE(Request $request)
    {
        Validator::make($request->all(), [
            'file' => 'required',
        ])->validate();
        if($request->hasFile('file')){
            $fileName = time().'.'.$request->file->extension();
            if (strpos($fileName, ".rar") || strpos($fileName, ".zip") || strpos($fileName, ".txt") || strpos($fileName, ".json")) {
                $request->file->move(public_path('storage').'/txt/', $fileName);
                $path = public_path('storage').'/txt/'.$fileName;
                if (strpos($fileName, ".rar")) {
                    $rar = RarArchive::open($path);
                    if($rar)
                        $rar_entries = $rar->getEntries();
                    if ($rar_entries === FALSE)
                        return response()->json("Could not retrieve entries.");
                    if (empty($rar_entries))
                        return response()->json("No valid entries found.");
    
                    $ip_arr = array();
                    foreach($rar_entries as $entry) {
                        $entry->extract(substr($path,0,-4));
                        $stream = $entry->getStream();
                        if ($stream === FALSE)
                            return response()->json("Failed opening file:".$entry);
                        else {
                            $contents = stream_get_contents($stream);
                            $ip_list = explode("\r\n", $contents);
                            foreach ($ip_list as $ip)
                                array_push($ip_arr, $ip);
                        }
                    }
                    $rar->close();
                    return($this->Bloom_arr($ip_arr));
                } elseif (strpos($fileName, ".zip")) {
                    $zip=new ZipArchive;
                    echo "zip";
                } elseif (strpos($fileName, ".txt") || strpos($fileName, ".json")) {
                    $contents = file_get_contents($path);

                    $js = json_encode($contents);
                    return response()->json($js);
                }
            } else {
                return response()->json('格式錯誤');
            }
        }     
        else
        {
            return response()->json('上傳失敗');
        }
    }
    public function uploadEnc(Request $request) 
    {
        Validator::make($request->all(), [
            'file' => 'required',
        ])->validate();
        if($request->hasFile('file')){
            $fileName = time().'.'.$request->file->extension();

            $request->file->move(public_path('storage').'/Enc/', $fileName);
            $path = public_path('storage').'/Enc/'.$fileName;
            //將檔案資訊新增進資料庫
            $user_id = $request->ID;
            $serBloom = $request->BloomFilter;
            $user = User::find($user_id);
            $user->files()->create(['name' => $fileName, 'path' => Str::after($path, public_path('storage')), 'bloom' => $serBloom]);
            
            //Smart contract
            $file_id =  File::where('name', $fileName)->value('id');
            $ipfs = new IPFS("localhost", "8080", "5001"); // leaving out the arguments will default to these values
            $hash = $ipfs->addFromPath($path);

            return response()->json('id0:'.$file_id.'uid:'.$user_id.'filename:'.$fileName.'path:'.$hash);
            
            //return response()->json('Success.');
        }     
        else
        {
            return response()->json('上傳失敗');
        }
    }
    public function uploadReEnc(Request $request) 
    {
        Validator::make($request->all(), [
            'file' => 'required',
        ])->validate();
        if($request->hasFile('file')){
            $fileName = time().'.'.$request->file->extension();
            $request->file->move(public_path('storage').'/ReEnc/', $fileName);
            $path = public_path('storage').'/ReEnc/'.$fileName;
            //將檔案資訊新增進資料庫
            $rekey_id = $request->Rekey_ID;
            $info = Rekey::find($rekey_id);
            $file_id = $info->file_id;
            $requester_id = $info->requester_id;
            $user = User::find($requester_id);            
            $user->refiles()->create(['name' => $fileName, 'path' => Str::after($path, public_path('storage')), 'file_id' => $file_id]);
            return response()->json('Success.');
        }     
        else
        {
            return response()->json('上傳失敗');
        }
    }
    public function getFileCount()
    {
        $count = File::select('id')->count();
        return response()->json($count);
    }
}

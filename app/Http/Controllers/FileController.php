<?php
//linux
//include_once(app_path() . '/Http/Controllers/bloomclass.php');
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
    //上傳STIX檔案(論文沒用到)
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
        
    // 上傳原始IP list，計算Bloom filter
    public function upload(Request $request)
    {
        Validator::make($request->all(), [
            'file' => 'required',
        ])->validate();
        if($request->hasFile('file')){
            //改檔名
            $fileName = time().'.'.$request->file->extension();
            //檢查副檔名
            if (strpos($fileName, ".rar") || strpos($fileName, ".zip") || strpos($fileName, ".txt")) {
                //移動檔案到public/storage/txt
                $request->file->move(public_path('storage').'/txt/', $fileName);
                //取得路徑
                $path = public_path('storage').'/txt/'.$fileName;
                if (strpos($fileName, ".rar")) {
                    //處理rar
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
                            //將原始檔案內容新增到新的array
                            foreach ($ip_list as $ip)
                                array_push($ip_arr, $ip);
                        }
                    }
                    $rar->close();
                    return($this->Bloom_arr($ip_arr));
                } elseif (strpos($fileName, ".zip")) {
                    $zip=new ZipArchive;
                    echo "zip";
                } elseif (strpos($fileName, ".txt")) {//主要處理單個txt檔
                    //取得檔案內容
                    $contents = file_get_contents($path);
                    //切割各個IP成一個array
                    $ip_arr = explode("\r\n", $contents);
                    //Exception空行處理
                    //先計算布隆過濾器(從資料庫中取得初始的Bloom filter)
                    $bloom = unserialize(Bloomfilter::first()->bloomfilter);
                    //儲存hash的array
                    $hash_arr = array();
                    //計算Bloom filter
                    foreach($ip_arr as $ip) {
                        if ($bloom->has($ip)) {
                            return response()->json('IP exists!'.$ip);
                        }
                        else {
                            $bloom->set($ip);
                            //計算SHA-256
                            $hash = hash('sha256', $ip);
                            //取前8bits
                            $bit = Str::substr($hash, 0, 8);
                            array_push($hash_arr, $bit);
                        }
                    }
                    //序列化，方便傳輸
                    $serBloom = serialize($bloom);
                    //將Bloom filter轉成JSON
                    $bl = json_encode($bloom);
                    //取得Bloom filter計算結果(011001....)
                    $set = Str::between($bl, '"set":"', '","hashes');
                    return response()->json('Success.<br> Bloom filter:'.$set.'bf:'.$serBloom.'hash:'.implode("",$hash_arr));

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
    //處理STIX的檔案(論文沒用到)
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
                    //取得檔案內容
                    $contents = file_get_contents($path);
                    $pattern = Str::between($contents, '"pattern": "[', ']",');
                    $ip = Str::between($pattern, "'", "'"); 
                    //先計算布隆過濾器
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
    //處理Data requester上傳的IP list(.txt)
    public function updownTxt(Request $request)
    {
        Validator::make($request->all(), [
            'file' => 'required',
        ])->validate();
        if($request->hasFile('file')){
            //改檔名
            $fileName = time().'.'.$request->file->extension();
            //檢查副檔名
            if (strpos($fileName, ".rar") || strpos($fileName, ".zip") || strpos($fileName, ".txt")) {
                $request->file->move(public_path('storage').'/txt_Down/', $fileName);
                $path = public_path('storage').'/txt_Down/'.$fileName;
                //處理壓縮檔(未完成)
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
                } elseif (strpos($fileName, ".txt")) { //主要處理單個txt檔
                    $contents = file_get_contents($path);
                    //切割各個IP成一個array
                    $ip_arr = explode("\r\n", $contents);
                    //Exception空行 未處理
                    //先計算布隆過濾器(從資料庫中取得初始的Bloom filter)
                    $bloom = unserialize(Bloomfilter::first()->bloomfilter);
                    $rp = array();
                    //計算Bloom filter
                    foreach($ip_arr as $ip) {
                        //有重複IP
                        if ($bloom->has($ip)) {
                            return response()->json('IP exists!'.$ip);
                        }
                        else {
                            $bloom->set($ip);
                            array_push($rp, $ip);
                        }
                    }
                    //將Bloom filter轉成JSON
                    $bl = json_encode($bloom);
                    //取得Bloom filter計算結果(011001....)
                    $set = Str::between($bl, '"set":"', '","hashes');

                    //將blockchain data轉成Object
                    $data = json_decode($request->data);
                    //比較上傳IP List的Bloom filter與blockchain中儲存的Bloom filter
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
        $match = array();
        $match_proportion = array();
        if (is_array($ip)) {
            for ($i = 0; $i < count($data); $i++) {
                //符合數
                $match_num = 0;
                //blockchain中的Bloom filter
                $bloom = unserialize($data[$i]->{"2"});
                //second level hash comparison
                //blockchain中的hash
                $allhash = $data[$i]->{"3"};
                //8個為單位做切割
                $hash_arr = str_split($allhash, 8);
                //符合的hash數量
                $match_hash = 0;
                foreach ($ip as $ip_data) {
                    //檢查blockchain中的Bloom filter是否與剛剛上傳IP的計算結果相同
                    if ($bloom->has($ip_data)){
                        $match_num += 1;
                        
                        // compare hash
                        //先將上傳的IP做SHA-256
                        $hash_ip = hash('sha256', $ip_data);
                        //取前8 bits
                        $bit = Str::substr($hash_ip, 0, 8);
                        //檢查blockchain中的hash是否與剛剛上傳IP的計算結果相同
                        foreach ($hash_arr as $hash) {
                            if ($bit == $hash)
                                $match_hash += 1;
                        }
                    }
                }
                //若比較結果的符合數量!=0
                if ($match_num != 0) {
                    $jsbl = json_encode($bloom);
                    //取得Bloom filter計算結果(011001....)
                    $set = Str::between($jsbl, '"set":"', '","hashes');
                    //儲存比較結果[0101..., 檔名]
                    $match[$i+1] = array($set, $data[$i]->{"1"});
                    //儲存比較結果的match rate[Level 1, Level 2]
                    $match_proportion[$i+1] = array(round(($match_num / count($ip))*100,2), round(($match_hash / $match_num)*100,2));
                }
            }
            //大到小排序
            arsort($match_proportion);
            //新的array儲存排序後的結果
            $match_sort = array();
            foreach ($match_proportion as $key => $value) {
                $match_sort[$key] = $match[$key];
            }
            $list = [];
            foreach ($match_proportion as $k => $v) {
                array_push($list, array("key" => $k, "value" => $v));
            }
            //將結果轉成JSON
            $ip_arr = json_encode($ip);
            $result = json_encode($match_sort);
            $result_pro = json_encode($list);
            return response()->json('Success add ip to Bloom filter:'.$ip_arr.'<br>Bloom filter:'.$origin.'<br>Result:'.$result.'Proportion:'.$result_pro);
        } else { // todo 2級比較
            for ($i = 0; $i < count($data); $i++) {
                $bloom = unserialize($data[$i]->{"2"});
                if ($bloom->has($ip)) {
                    $jsbl = json_encode($bloom);
                    $set = Str::between($jsbl, '"set":"', '","hashes');
                    $match[$i+1] = array($set, $data[$i]->{"1"});
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
    //提出重新加密申請
    public function requestFile(Request $request)
    {
        Validator::make($request->all(), [
            'File_id' => 'required',
            'User_id' => 'required',
        ])->validate();
        if ($request->File_id) {
            $file_id = $request->File_id;
            //找這個檔案的擁有者
            $owner_id = File::find($file_id)->user_id;
            $requester_id = $request->User_id;
            //建立Rekey資訊到資料庫
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
    //從IPFS下載檔案
    public function downloadIPFS(Request $request)
    {
        Validator::make($request->all(), [
            'path' => 'required',
        ])->validate();
        if ($request->path) {
            $ipfs = new IPFS("localhost", "8080", "5001"); // leaving out the arguments will default to these values
            $hash = $request->path;
            $ipfs_file = $ipfs->get($hash);
            if ($ipfs_file) {
                $path = public_path('storage').'/ipfsGet/'.$hash.'.gz';
                // 解壓縮
                $phar = new PharData($path);
                if($phar){
                    $folder = time();
                    $phar->extractTo(public_path('storage').'/ipfsGet/'.$folder);
                    $file_path = '/ipfsGet/'.$folder.'/'.$hash;
                    return Storage::disk('public')->download($file_path);
                }
                else
                {
                    return response()->json('Fail');
                }
            }
            else {
                return response()->json('No file');
            }
        } else 
            return response()->json('No path');
        
    }
    //上傳重新加密的檔案(Oracle端，非平台端)
    public function uploadIPFS(Request $request)
    {
        Validator::make($request->all(), [
            'file' => 'required',
        ])->validate();
        if($request->hasFile('file')){
            $fileName = time().'.'.$request->file->extension();
            $request->file->move(public_path('storage').'/ReEnc/', $fileName);
            $path = public_path('storage').'/ReEnc/'.$fileName;

            $ipfs = new IPFS("localhost", "8080", "5001"); // leaving out the arguments will default to these values
            $hash = $ipfs->addFromPath($path);
            if ($hash)
                return response()->json($hash);
            else
                return response()->json('Upload failed');
        } else 
            return response()->json('fail');
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
    //處理加密檔案
    public function uploadEnc(Request $request) 
    {
        Validator::make($request->all(), [
            'file' => 'required',
        ])->validate();
        if($request->hasFile('file')){
            //改檔名
            $fileName = time().'.'.$request->file->extension();
            //移動檔案到public/storage/Enc
            $request->file->move(public_path('storage').'/Enc/', $fileName);
            //取得檔案路徑
            $path = public_path('storage').'/Enc/'.$fileName;
            //設定IPFS
            $ipfs = new IPFS("localhost", "8080", "5001"); // leaving out the arguments will default to these values
            //將檔案上傳到IPFS，取得下載hash
            $hash = $ipfs->addFromPath($path);
            if($hash) {
                //將檔案資訊新增進資料庫
                $user_id = $request->ID;
                $serBloom = $request->BloomFilter;
                $user = User::find($user_id);
                $user->files()->create(['name' => $fileName, 'path' => Str::after($path, public_path('storage')), 'bloom' => $serBloom]);
                
                //上傳到Smart contract還需要file_id
                $file_id =  File::where('name', $fileName)->value('id');

                return response()->json('id0:'.$file_id.'uid:'.$user_id.'filename:'.$fileName.'path:'.$hash);
            }
            else {
                return response()->json('IPFS failed');
            }
        }     
        else
        {
            return response()->json('上傳失敗');
        }
    }
    public function uploadBF(Request $request)
    {
        Validator::make($request->all(), [
            'file' => 'required',
        ])->validate();
        if($request->hasFile('file')){
            //改檔名
            $fileName = time().'.'.$request->file->extension();
            //移動檔案到public/storage/bf
            $request->file->move(public_path('storage').'/bf/', $fileName);
            //取得檔案路徑
            $path = public_path('storage').'/bf/'.$fileName;
            //讀取檔案內容
            $contents = file_get_contents($path);
            //將內容從JSON轉換成Object
            $obj = json_decode($contents);
            //取得Object中的bloom filter的serialize資訊
            $bf = $obj->bf;
            //進行反序列化
            $bloom = unserialize($bf);
            //將bloom filter轉成json
            $bl = json_encode($bloom);
            //取得bloom filter的計算結果(0101....)
            $set = Str::between($bl, '"set":"', '","hashes');
            return response()->json('Success.<br> Bloom filter:'.$set.'object:'.$contents);
        }     
        else
        {
            return response()->json('上傳失敗');
        }
    }
}

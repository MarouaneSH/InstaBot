<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Goutte;
use Carbon\Carbon;
use App\Unsplash;

class HomeController extends Controller
{
    
    
    private $cookieJar;
    private $client;

    public function __construct(){
        $this->cookieJar = new \GuzzleHttp\Cookie\CookieJar();
        $this->client = new Client();
        $this->client->post("https://app.alphafire.me/login",[
            'form_params' => [
                'username' => 'marwansouah@gmail.com',
                'password' => '123456789',
                'action' => 'login'
            ],
            'cookies' =>$this->cookieJar,
        ]);
    }

     public function sendRequest(){
        ini_set('max_execution_time', 0);
        set_time_limit(0);
        try {
            $date = Carbon::now()->addDays(10);
            $nbrTimeToPublish = 6;

            for($i=3;$i<7;$i++) {
                $posts = $this->unsplash($i);
                foreach($posts as $post) {
                    //check if post already posted
                    if(Unsplash::where('image_id', $post['image_id'])->first()) {
                        dump('Already posted');
                        continue;
                    }
                    //generate date to publish
                    if($nbrTimeToPublish == 0) {
                        $date->addDay();
                        $nbrTimeToPublish = 6;
                    }

                    //Upload image to the host , and get image data
                    $image_data = $this->uploadFromUrl($post['image_link']);
                    dump($image_data);
                    if(isset($image_data) &&  isset($image_data->data)){
                        $img_id = $image_data->data->file->id;
                        $this->schedule($img_id,$post['tags'],$this->timeToPublish($date));
                        //store the image ID
                        Unsplash::create(["image_id" => $post['image_id']]);
                        dump('POST ==> SUCCESS');
                    }

                
                    //decrement     
                    $nbrTimeToPublish--;
                
                }
            }
        }
        catch(Exception $e) {
            dump($e->getMessage());
        }
    }

    public function schedule($img_id,$tags,$timeToPublish){
       $response = $this->client->post("https://app.alphafire.me/post",[
            "form_params" => [
                'action' =>  'post',
                'type' =>  'timeline',
                'media_ids' =>  $img_id,
                'remove_media' =>  '1',
                'caption' =>  $tags.' '.$this->caption(),
                'account' =>  '141',
                'is_scheduled' =>  '1',
                'schedule_date' =>  $timeToPublish,
                'user_datetime_format' =>  'Y-m-d H:i',
                'location_label' =>  'Los Angeles, California',
            ],
            'cookies' =>$this->cookieJar,
        ]);

        dump($response->getBody()->getContents());

    }

    public function unsplash($page){
        $result = $this->client->get("https://unsplash.com/napi/search/photos?query=fashion&xp=&per_page=30&page=$page");
        
        $data = json_decode($result->getBody()->getContents());
        $post = null;
        foreach($data->results as $key=>$value) {

            //get image link
            $post[$key]['image_link'] = $value->urls->full;
            //get image ID
            $post[$key]['image_id'] = $value->id;
            //get image tags
            $tags = array_map(function($e){
                return $e->title;
            },$value->tags);
            $tags = implode(" #",$tags);
            $tags[0]="#";

            //get photo tags
            $photo_tags = array_map(function($e){
                return $e->title;
            },$value->photo_tags);
            $photo_tags = implode(" #",$photo_tags);
            $photo_tags[0]="#";
            // merge tags
            $post[$key]['tags'] = "$tags $photo_tags";
        }

        return $post;
    }

     public function uploadFromUrl($url){
        $response =  $this->client->post("https://app.alphafire.me/file-manager/connector", [
            'multipart' => [
                [
                    'name'     => 'cmd',
                    'contents' => "upload",
                ],
                [
                    'name'     => 'type',
                    'contents' => "url",
                ],
                [
                    'name'     => 'file',
                    'contents' => $url
                ],
            ],
            'cookies' => $this->cookieJar
        ]);
        return json_decode($response->getBody()->getContents());
     }


     public function removeAttahcments(){
        $response = $this->client->post("https://app.alphafire.me/file-manager/connector",[
            "form_params" => [
                'cmd' =>  'retrieve',
                'last_retrieved' =>  0,
                'limit' =>  200,

            ],
            'cookies' =>$this->cookieJar,
        ]);

        $files = json_decode($response->getBody()->getContents())->data->files;
        foreach($files as $file) {
             $remove = $this->client->post("https://app.alphafire.me/file-manager/connector",[
                "form_params" => [
                    'cmd' =>  'remove',
                    'id' =>  $file->id,
                ],
                'cookies' =>$this->cookieJar,
            ]);
            
            dump(json_decode($remove->getBody()->getContents()));
        }

       
    
    }


     public function uploadFromFile(){
          // echo '<pre>';
        // $response =  $client->post("https://app.alphafire.me/file-manager/connector?callback=jQuery31107050655283717486_1532471569444", [

        //     'multipart' => [
        //         [
        //             'name'     => 'cmd',
        //             'contents' => "upload"            ,
        //         ],
        //         [
        //             'name'     => 'type',
        //             'contents' => "file"            ,
        //         ],
        //         [
        //             'name'     => 'file',
        //             'filename' => "sdds.jpg",
        //             'Mime-Type'=> "image/jpeg"            ,
        //             'Content-Type'=> "image/jpeg"            ,
        //             'contents' => fopen("s.jpg","r"),
        //         ],
        //     ],
        //     'cookies' => $cookieJar
        // ]);
        // echo $response->getBody()->getContents();
        // echo '</pre>';
     }

     public function caption(){
         $captions[] = "#fashionblogger #fashionlover #fashionaddict #fashionable #fashinistac #womensfashion #luxuryfashion";
         $captions[] = "#fashiongram #womenfashion #womanswear #fashinista #fashiongirls";
         $captions[] = "#fashionstore #trendalert #fashiongirl #girlsstyle #trendy #fashionpost";
         $captions[] = "#musthave #girlstyle #itgirl #fashiongram #womenfashion #womanswear #fashinista #fashiongirls";
         $captions[] = "#buyclothes #summersale #clothesforsale #latestfashion #buyleggings #clothesshop #leggingsale";
         $captions[] = "#womanclothes #fashionstore #fashionshop #fashionshopping  #newcollection #fashiontrend #fashiononline #fashionstyle #buy #onlineshopping";
         $captions[] = " #lowprice #onlinesale #premiumquality #discount #discounts #fashionwoman #bestclothes";
         //return random caption
         return $captions[rand(0, count($captions) - 1)];
     }

     public function timeToPublish($date){

        $min = 1; // 1 AM;
        $max = 23; // 23 PM;

        //PUBLISH 6 TIME A DAY
         return $date->setTime(rand($min,$max), rand(1,60) , rand(1,60))->format('Y-m-d H:i');
     }
}


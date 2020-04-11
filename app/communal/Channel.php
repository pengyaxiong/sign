<?php


namespace app\communal;


use think\Db;
use think\Validate;

class Channel
{
//合法性验证
    public static function check_channel($channel){
        $validate = Validate::is($channel,'alphaNum');
        if(!$validate){
            k_abort(200,'参数非法');
        }
        return $channel;
    }
    
     public static function check_down_num($uid,$app_id,$channel=false){
        $channel = $channel?$channel:input('channel');
        if($channel){
            $udid_count = Db::name('ios_udid_list')->where(['user_id'=>$uid,'app_id'=>$app_id,'channel'=>$channel])->count('user_id');
            $down_num = Db::name('user_channel')->where('code',$channel)->where('user_id',$uid)->field('down_num')->find();
            if($down_num){
                if(($down_num['down_num'] != 0) && $down_num['down_num'] <= $udid_count){
                    k_abort(200,'下载次数已达上限','/');
                }
            }
        }
    }

    public static function get_list($user_id){
        $appid = input('id');
        $list = Db::name('user_channel')
            ->where('app_id',$appid)
            ->where('user_id',$user_id)
            ->where('status',1)
            ->order('create_time desc')
            ->paginate(10)
            ->each(function($items){
                $udid_count = Db::name('ios_udid_list')->where(['user_id'=>$items['user_id'],'app_id'=>$items['app_id'],'channel'=>$items['code']])->count('user_id');
                $items['udid_count'] = $udid_count;
                $down_count = Db::name('super_download_log')->where(['uid'=>$items['user_id'],'app_id'=>$items['app_id'],'channel'=>$items['code']])->count('uid');
                $items['down_count'] = $down_count;
                $items['create_time'] = date('Y-m-d',$items['create_time']);
                //地址
                $domain = request()->header('referer');//来源地址
                $domain = (explode('//',$domain));
                //头协议
                $http = $domain[0].'//';
                $domain = $domain[1];
                $domain = (explode('/',$domain))[0];
                $url = $http.$domain;
                $is = Db::name('domain')->where('domain',$domain)->field('id')->find();
                if(!$is){
                    $url = $url.'/'.$items['er'].'?channel='.$items['code'];
                }else{
                    $url = $url.'/sup.html?er='.$items['er'].'&channel='.$items['code'];
                }
                $items['url']="<a href='{$url}' target=\"_blank\" style='color: #0c85da;'>{$url}</a>";
                $action = '<a style="padding: 0 15px" href="javascript:void(0)" onclick="del('.$items['id'].')"  class="layui-btn layui-btn-danger layui-btn-sm">删除</a>';
                $items['action'] = $action;
                $items['down_num'] = "<input type='text' onBlur='edit_down_num(this,".$items['id'].")' onFocus='edit_down(this)' class='down_num_input down_num_input_hover' value='{$items['down_num']}'>";
                return $items;
            });
        return $list;
    }
    
    public static function edit($uid){
        $id = intval(input('id'));
        $down_num = intval(input('down_num'));
        $result =  Db::name('user_channel')->where('user_id',$uid)->where('id',$id)->update(['down_num'=>$down_num]);
        return $result;
    }
    public static function del($id,$user_id){
        $result = Db::name('user_channel')->where('user_id',$user_id)->where('id',$id)->update(['status'=>0]);
        return $result;
    }

    public static function set_channel($user_id){
        $channel = input('channel');
        $appid = input('id');
        $rand = input('rand');
        if(!$channel && $rand){
            $channel = random_str(8);
        }

        self::check_channel($channel);
        $er = Db::name('user_posted')->where('id',$appid)->field('er_logo')->find();
        if(!$er){
            k_abort(200,'应用不存在');
        }
        $is_have = Db::name('user_channel')->where(['code'=>$channel,'user_id'=>$user_id,'app_id'=>$appid])->find();
        if($is_have){
            k_abort(200,'该渠道已存在');
        }
        $result = Db::name('user_channel')->insert([
            'user_id' => $user_id,
            'app_id'  => $appid,
            'code'    => $channel,
            'er'      => $er['er_logo'],
            'create_time' => time()
        ]);
        return $result;
    }

    //获取渠道码，并写描述文件
    public static function get_channel_to_MobileConfig($app_id,$is_old=false){
        $channel = input('channel');
        if(!$channel || is_null($channel)){
            return '';
        }
        self::check_channel($channel);
        //判断带渠道描述文件是否存在
        $name = $app_id.'_'.$channel;
        $path = $is_old?'/ios_describe/':'/ios_des/';
        if (!file_exists(APP_ROOT . $path. $name . '.mobileconfig')) {
            //不存在则生成
            if($is_old){
                $url = get_site_url() . '/user/install/get_udid?app_id=';
            }else{
                $url = get_sup_domain() . '/user/install/udid?app_id=';
            }
           Communal::saveMobileConfig($app_id,$url,$channel);
        }
        return $channel;
    }
}
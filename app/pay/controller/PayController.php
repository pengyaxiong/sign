<?php

namespace app\pay\controller;

use app\pay\ali\AliPay;
use app\pay\validate\PayValidate;
use cmf\controller\HomeBaseController;
use think\Cache;
use think\Controller;
use think\Db;
use think\Request;

class PayController extends Controller{
    public function pay(Request $request){
        $data     = $request->param();
        $validate = new PayValidate();
        $result   = $validate->check($data);

        if(!$result){
            return $this->error($validate->getError());
        }
        $id =  $data['id'];
        //价格获取(从数据库获取，避免数据被串改)
        $super = Db::name('super_num')->where('type',$data['type'])->where('id',$id)->find();

        if(!$super){
            $this->error("数据非法");
        }

        $coin     =  $super['coin'];
        $uid      =  $data['uid'];
        $order_id =  $data['order_id'];
        $type     = '支付宝';
        $subject  = '充值超级签名下载次数 ' . $super['num'] . ' 送' . $super['gift'];
        //重复提交判定
        $isHave = Db::name('charge_log')->where('uid',$uid)->where('order_id',$order_id)->field('id')->find();
        $r = true;
        if(!$isHave){
            $d = array(
                'download_id'       => $id,
                'download_coin'     => $coin,
                'download_download' => $super['num'],
                'd_gift'            => $super['gift'],
                'order_id'          => $order_id,
                'uid'               => $uid,
                'addtime'           => time(),
                'subject'           => $subject,
                'body'              => $subject,
                'type'              => $type,
                'status'            => 3,
                'goods_type'        => $data['type'],
            );
            $r = Db::name('charge_log')->insert($d);
        }
        //echo $r;die();
        if($r){
            $data['coin']    = $coin;
            $data['subject'] = $subject;
            $data['body']    = $subject;
            $res             = AliPay::pay($data);

            echo $res;
        }
    }

    /**
     * 支付网址缓存
     * @param $res
     * @return string
     */
    private function setPayUrlCache($res){
        $token =  md5(make_password(16).time());
        Cache::set($token,$res,60);
        return $token;
    }

    public function isPay($order){
        $uid   = session('user.id');
        $order = Db::name('charge_log')->where('uid',$uid)->where('order_id',$order)->find();

        if($order){
           if( $order['status'] == 5){
               return json([
                   'code' => 0,
                   'msg'  => '充值出错，请联系客服'
               ]);
           }
            if( $order['status'] == 1){
                return json([
                    'code'=>200,
                    'msg'=>'充值成功'
                ]);
            }
        }
        return json(['code'=>0,'msg'=>'请先扫码支付']);
    }

    /**
     * 支付二维码地址
     */
    public function goPay(){
        $token = input('token');
        $res   = Cache::get($token);
        echo $res;
    }
}
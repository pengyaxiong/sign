<?php


namespace app\pay\controller;

use think\Db;
use think\Log;
use think\Request;
use app\pay\ali\AliPay;

/**
 * 支付回调
 * Class PayNotifyController
 * @package app\pay\controller
 */
class PayNotifyController{
    public function aliNotify(Request $request){
        $data   = $_POST;//回调参数
        $result = AliPay::check($data);
        if($result){
            //获取订单号
            $orderNo = $data['out_trade_no'];

            //判断支付状态
            if($data['trade_status'] == 'TRADE_SUCCESS'){
                //查询订单信息
                $info = Db::name('charge_log')->where('order_id',$orderNo)->field('id,uid,download_download,d_gift,goods_type,status')->find();
                if($info['status']!=1){
                    //修改订单状态
                    Db::name('charge_log')->where('id',$info['id'])->update(['status'=>5]);

                    //充值次数
                    Db::startTrans();
                    try{
                        $user_info = Db::name('user')->where('id',$info['uid'])->field('downloads,sup_down_prive,sup_down_public')->find();

                        $add_num   = $info['download_download'] + $info['d_gift'];
                        $new_arr   = [];

                        if($info['goods_type'] == 1){           //公有
                            $new_arr['sup_down_public'] = $user_info['sup_down_public']+$add_num;
                        }else if($info['goods_type'] == 2){     //私有
                            $new_arr['sup_down_prive'] = $user_info['sup_down_prive']+$add_num;
                        }

                        Db::name('user')->where('id',$info['uid'])->update($new_arr);
                        //修改状态为充值成功
                        Db::name('charge_log')->where('id',$info['id'])->update(['status'=>1]);

                        //日志记录
                        $data=[
                            'uid'     => $info['uid'],
                            'num'     => $add_num,
                            'type'    => $info['goods_type'],
                            'addtime' => time(),
                            'addtype' => 1
                        ];
                        Db::name('sup_charge_log')->insert($data);

                        Db::commit();
                    } catch (\Exception $e) {

                        Db::rollback();
                    }
                }
            }
        }

        echo "success";
    }
}
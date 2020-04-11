<?php


namespace app\admin\service;

//用户消费数据
use think\Db;

class ChargeService
{

    public static function charge(){
        $params = request()->param();
        $model = Db::name('sup_charge_log');
        $_params=[];
        if(isset($params['uid']) && $params['uid']){
            $_params['uid'] = $params['uid'];
            $model = $model->where('uid',$params['uid']);
        }
        if(isset($params['is_add']) && $params['is_add'] != -1){
            $_params['is_add'] = $params['is_add'];
            $model = $model->where('is_add',$params['is_add']);
        }
        if(isset($params['addtype']) && $params['addtype'] != -1){
            $_params['addtype'] = $params['addtype'];
            $model = $model->where('addtype',$params['addtype']);
        }
        if(isset($params['start_time']) && $params['start_time']){
            $_params['start_time'] = $params['start_time'];
            $model = $model->where('addtime','>',strtotime($params['start_time']));
        }
        if(isset($params['end_time']) && $params['end_time']){
            $_params['end_time'] = $params['end_time'];
            $model = $model->where('addtime','<',strtotime($params['end_time']));
        }

        $log = $model->order('addtime desc')
                ->paginate(10,false,['query'=>$_params])
                ->each(function($items){
                    $u_info = Db::name('user')->where('id',$items['uid'])->field('user_nickname,mobile')->find();
                    $items['u_name']='该用户已被删除';
                    if($u_info){
                        $items['u_name'] = $u_info['user_nickname']?$u_info['user_nickname']:$u_info['mobile'];
                    }

                    $items['p_name'] = '无';
                    if($items['puid']){
                        $p_info = Db::name('user')->where('id',$items['puid'])->field('user_nickname,mobile')->find();
                        $items['p_name'] = $p_info['user_nickname']?$p_info['user_nickname']:$p_info['mobile'];
                    }
                    return $items;
                });
        $restult=[
            'charge_log'=>$log,
            'page'=>$log->render(),
            'params'=>$params
        ];
        return $restult;
    }

}
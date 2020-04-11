<?php


namespace app\admin\controller;


use app\admin\service\UserService;
use app\communal\Count;
use cmf\controller\AdminBaseController;

/**
 * 下级管理
 * Class LevelController
 * @package app\admin\controller
 */
class LevelController extends AdminBaseController
{
    //下级管理
    public function index($pid,$limit=20){
        $params=$this->request->param();
        //获取下级的数据
        $sid_data = UserService::getDataToUid($pid,$limit);
        $this->assign([
            'sid_data' =>$sid_data,
            'pid'=>$pid
        ]);
        $sid_data->appends($params);
        $this->assign('page', $sid_data->render());
        return $this->fetch();
    }

    //展示可作为下级的列表
    public function lists(){
        if($this->request->isGet()){
            $limit = input('limit');
            $pid = input('pid');
            $page = input('page');
            $list = UserService::getUserList($pid,$limit,$page);

            $this->assign([
                'list'=>$list,
                'page'=>$list->render(),
                'pid'=>$pid
            ]);
            return $this->fetch();
        }
    }

    //详细下载数据
    public function downData($uid,$time=false){

        $list = Count::getDownCounByWeek($uid,7,true,$time);
        //数据组装

        $time = $time?strtotime($time.' 00:00:00'):time();
        $week = Count::getDays(7,$time);

        $data_arr['count_udid'][0] = isset($list['count_udid'][$time])?$list['count_udid'][$time]:0;
        $data_arr['count_down'][0] = isset($list['count_down'][$time])?$list['count_down'][$time]:0;

        foreach ($week as $k=>$v){
            if(isset($list['count_down'][$v])){
                $data_arr['count_udid'][]= $list['count_udid'][$v];
                $data_arr['count_down'][]= $list['count_down'][$v];

            }else{
                $data_arr['count_udid'][] = 0;
                $data_arr['count_down'][] = 0;
            }
        }

       // dump($data_arr);exit;
        $this->assign([
            'week' => json_encode($week),
            'count_down' =>json_encode(array_reverse($data_arr['count_down'])),
            'count_udid' => json_encode(array_reverse($data_arr['count_udid'])),
            'uid'=>$uid,
            'time'=>date('Y-m-d',$time)
        ]);
        return $this->fetch();
    }

    //取消下级
    public function delUid($pid,$uid){
        $res = UserService::delSid($pid,$uid);
        $result = $res?['code'=>200,'msg'=>'取消成功']:['code'=>0,'msg'=>'取消失败'];
        return json($result);
    }

    //为选中用户设置上线
    public function addPid($pid,$uid){
        $result = UserService::setPid($pid,$uid);
        $result =  $result?['code'=>200,'msg'=>'设置成功']:['code'=>0,'msg'=>'设置失败'];
        return json($result);
    }

}
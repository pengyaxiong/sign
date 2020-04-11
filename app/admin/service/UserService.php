<?php


namespace app\admin\service;

use app\communal\Count;
use think\Db;

/**
 * 用户操作
 * Class UserService
 * @package app\admin\service
 */
class UserService
{

    /**
     * @param $pid 父级
     * @param $uid 下级
     */
    public static function setPid($pid,$uid){
        if(is_array($uid)){
            $uid = implode(',',$uid);
        }
        $result = Db::name('user')->where('id','in',$uid)->update(['pid'=>$pid]);
        return $result;
    }

    /**
     * 获取用户列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getUserList($pid,$limit=10,$page=1){
        $list = Db::name('user')
            ->where('pid',0)
            ->where('id','neq',$pid)//排除自身
            ->where('user_status',1)
            ->where('user_type',2)
            ->field('id,user_login,user_nickname,mobile')
            ->paginate($limit,false,['page'=>$page,'query'=>['pid'=>input('pid')]]);
        return $list;
    }

    /**
     * 删除pid
     * @param $pid
     * @param $uid
     * @return int|string
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public static function delSid($pid,$uid){
        if(is_array($uid)){
            $uid = implode(',',$uid);
        }
        $result = Db::name('user')->where('pid',$pid)->where('id','in',$uid)->update(['pid'=>0]);
        return $result;
    }

	public function editPid($id,$pid){
        $result = Db::name('user')->where('id',$id)->update(['pid'=>$pid]);
        return $result?json(['code'=>200]):json(['code'=>0]);
    }
    /**
     * 获取用的的下线及下线的总下载量
     * @param $pid
     * @param int $limit
     * @return \think\Paginator
     * @throws \think\exception\DbException
     */
    public static function getDataToUid($pid,$limit=20,$mobile=false){
        $pid = $pid==1?'u.pid = '.$pid.' or u.pid=0':'u.pid = '.$pid;
        $mobile = $mobile?'u.mobile = '.$mobile:'1=1';
        $list =  Db::name('user')
            ->alias('u')
            ->where($pid)
            ->where($mobile)
            ->where('u.user_type',2)
            ->order('u.create_time desc')
            ->field('u.id,u.pid,u.user_login,u.user_nickname,u.mobile,u.sup_down_public,u.more')
            ->paginate($limit)
            ->each(function ($item, $key) {
                $count = Count::getDownCount($item['id']);
                $item['count_udid'] = $count['count_udid'];
                $item['count_down'] = $count['count_down'];
                return $item;
            });

        return $list;
    }
}
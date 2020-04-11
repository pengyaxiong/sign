<?php


namespace app\communal;

use think\Db;

/**
 * 数据统计公共方法
 * Class Count
 * @package app\common\count
 */
class Count
{
    /**
     * 根据时间查询用户的下载量
     * @param $userId 用户id
     * @param $time 要查询的日期
     */
    public static function getDownCountByTime($userId,$time){
        $times = self::changeTime($time);
        //is_cost = 2 同udid,重复下载
        $sql = "select count(user_id) as count from cmf_ios_udid_list where user_id ={$userId} and create_time > {$times['start']} and create_time < {$times['end']}";
        $count_udid = Db::query($sql);//下载量
        $sql = "select count(uid) as count from cmf_super_download_log  where uid ={$userId} and addtime > {$times['start']} and addtime < {$times['end']} ";
        $count_down = Db::query($sql);//下载量
        return ['count_udid'=>$count_udid[0]['count'],'count_down'=>$count_down[0]['count']];
    }

 /**
     * 根据时间查询用户下载量
     * @param $userId
     * @param $time
     * @return mixed
     */
    public static function getDownCountsByTime($userId,$appid,$time){
        $times = self::changeTime($time);
        //is_cost = 2 同udid,重复下载
        $sql = "select count(uid) as count from cmf_super_download_log where uid ={$userId} and app_id = {$appid} and addtime > {$times['start']} and addtime < {$times['end']}";;
        $count_udid = Db::query($sql);//下载量
        return $count_udid[0]['count'];
    }

    /**
     * 根据时间查询用户安装量
     * @param $userId
     * @param $time
     * @return mixed
     */
    public static function getUdidCountByTime($userId,$time){
        $times = self::changeTime($time);
        //is_cost = 2 同udid,重复下载
        $sql = "select count(user_id) as count from cmf_ios_udid_list where user_id ={$userId} and create_time > {$times['start']} and create_time < {$times['end']}";
        $count_udid = Db::query($sql);//下载量
        return $count_udid[0]['count'];
    }

    /**
     * 查询用户的下载总量
     * @param $userId
     * @return float|int|string
     */
    public static function getDownCount($userId,$appid=false){
        $app_str='';
        if($appid){
            $app_str = ' and app_id = '.$appid;
        }
        $sql = "select count(user_id) as count from cmf_ios_udid_list where user_id ={$userId}".$app_str;
        $count_udid = Db::query($sql);
        $sql = "select count(uid) as count from cmf_super_download_log  where  uid ={$userId}".$app_str;
        $count_down = Db::query($sql);//下载量
        return ['count_udid'=>$count_udid[0]['count'],'count_down'=>$count_down[0]['count']];
    }

    /**
     * 获取指定时间段的下载数据统计
     * @param $userId
     * @param int $days
     * @param bool $keyIsTime
     * @return mixed
     */
    public static function getDownCounByWeek($userId,$days=7,$keyIsTime = false,$time=false){

        $time = $time?$time:date('Y-m-d',time());
        $d = new \DateTime($time.' 00:00:00');//7天为周期,开始时间
        $start = $d->modify("-{$days} day")->format("Y-m-d");
        $d = new \DateTime($time.' 00:00:00');//7天为周期,开始时间
        $end =  $d->modify("+1 day")->format("Y-m-d");
        $end = strtotime($end.' 00:00:00');
        $start = strtotime($start.' 00:00:00');
        $sql="select count(FROM_UNIXTIME(create_time,'%Y-%m-%d'))as count,FROM_UNIXTIME(create_time,'%Y-%m-%d') as t_addtime from cmf_ios_udid_list where user_id = {$userId}  and create_time > {$start} and create_time < {$end} GROUP BY t_addtime";
        $count_udid = Db::query($sql);
         $sql = "select count(t_addtime) as count,t_addtime  from (select uid,FROM_UNIXTIME(addtime,'%Y-%m-%d') as t_addtime from cmf_super_download_log where uid = {$userId}   and addtime > {$start} and addtime < {$end}) a  GROUP BY a.t_addtime;";
        $count_down = Db::query($sql);
        $count['count_udid'] = [];
        $count['count_down'] =[];
        if($keyIsTime){
            foreach ($count_udid as $k=>$v){
                $count['count_udid'][$v['t_addtime']] = $v['count'];
                unset($count_udid[$k]);
            }
            foreach ($count_down as $k=>$v){
                $count['count_down'][$v['t_addtime']] = $v['count'];
                unset($count_down[$k]);
            }
        }else{
            $count['count_udid'] = $count_udid;
            $count['count_down'] = $count_down;
        }
        return $count;
    }


    //取指定日期间时间
    public static function getDays($end,$start=false){
        $days = $end-1;
        $end =$start?$start:time();
        $time =date('Y-m-d',$end);
        $d = new \DateTime($time.' 00:00:00');//7天为周期,开始时间
        $start = $d->modify("-{$days} day")->format("Y-m-d");
        $start = strtotime($start.' 00:00:00');
        $datearr = [];
        while ($start <= $end) {
            $datearr[] = date('Y-m-d', $start);//得到dataarr的日期数组。
            $start = $start + 86400;
        }

        return ($datearr);
    }

    /**
     * 时间戳转换
     * @param $time 时间戳
     */
    private static function changeTime($time){
        $data_time = date("Y-m-d",$time);
        //时间区间拼接
        $times['start'] = strtotime($data_time.' 00:00:00');
        $times['end']   = strtotime($data_time.' 23:59:59');
        return $times;
    }
}
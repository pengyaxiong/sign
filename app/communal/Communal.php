<?php


namespace app\communal;

//公共方法封装
use MingYuanYun\AppStore\Client;
use think\Db;
use think\Request;
use app\communal\Channel;
use think\Validate;
use app\agent\service\CommonService;

class Communal
{

    //获取Udid
    public static function get_udid(){
    	
        $data = file_get_contents('php://input');
		
        $plistBegin     = '<?xml version="1.0"';
        $plistEnd       = '</plist>';

        $data2          = substr($data, strpos($data, $plistBegin), strpos($data, $plistEnd) - strpos($data, $plistBegin));
        $xml            = xml_parser_create();
        $UDID           = "";
        $CHALLENGE      = "";
        $DEVICE_NAME    = "";
        $DEVICE_PRODUCT = "";
        $DEVICE_VERSION = "";
        $iterator       = 0;
        $arrayCleaned   = array();
        $data           = "";

        xml_parse_into_struct($xml, $data2, $vs);
        xml_parser_free($xml);

        foreach ($vs as $v) {
            if ($v['level'] == 3 && $v['type'] == 'complete') {
                $arrayCleaned[] = $v;
            }
        }

        foreach ($arrayCleaned as $elem) {
            switch ($elem['value']) {
                case "CHALLENGE":
                    $CHALLENGE = $arrayCleaned[$iterator + 1]['value'];
                    break;
                case "DEVICE_NAME":
                    $DEVICE_NAME = $arrayCleaned[$iterator + 1]['value'];
                    break;
                case "PRODUCT":
                    $DEVICE_PRODUCT = $arrayCleaned[$iterator + 1]['value'];
                    break;
                case "UDID":
                    $UDID = $arrayCleaned[$iterator + 1]['value'];
                    break;
                case "VERSION":
                    $DEVICE_VERSION = $arrayCleaned[$iterator + 1]['value'];
                    break;
            }
            $iterator++;
        }
        $data = input('param.app_id');
        if(strpos($data,'_') !== false){
            $data = explode('_',$data);
            $app_id = $data[0];
            $channel = $data[1];
        }else{
            $app_id = $data;
            $channel='';
        }
       
        $uid = Db::name('user_posted')->where('id',$app_id)->field('uid')->find();
        $pid = Db::name('user')->find($uid['uid']);
        $pid['pid'] = $pid['pid']?:1;
        $url = Db::name('domain')->where('uid',$pid['pid'])->field('domain')->find();
        $url = $url['domain'];
        if(strpos($url,'http') === false ){
            $url='http://'.$url;
        }
        
        if(!$UDID){
        	return $url.'/error.html?error='.urlencode('数据无效');
        }
        
         $data = [
            'udid'=>$UDID,
            'app_id'=>$app_id,
            'version'=>$DEVICE_VERSION,
            'device_name'=>$DEVICE_PRODUCT,
            'channel'=>$channel,
        ];
        $token = MD5($UDID.make_password(5));
        $task_id = Db::name('udid_task')->insertGetId([
            'content'=>json_encode($data),
            'valid_time'=>1800,
            'create_time'=>time(),
            'token'=>$token
        ]);
        $url = $url . "/ios_install.html?task_id=".$token;
        return $url;
    }

    public static function udid_redirect(){
        $task_id = input('task_id');
        $task = Db::name('udid_task')->where('token',$task_id)->find();
        if(!$task){
        	 k_abort(301,'任务id无效','/', 3);
        }

        $task_content = json_decode($task['content'],true);
        // dump($task_content);
        $udid        = $task_content['udid'];
        $app_id      = $task_content['app_id'];
        $ios_version = $task_content['version'];
        $device_name = $task_content['device_name'];
        $channel = $task_content['channel'];
       if($task['status'] && $task['ipa_id']){
        	return [
	            //'userInfo'=>$userInfo,
	            'sup_id'=>$task['ipa_id'],
	            'channel'=>$channel,
	           // 'certificate_record'=>$certificate_record,
	            'ios_version'=>$ios_version
        	];
        }

        //查询该APP剩余的设备下载数
        if (!$app = db('user_posted')->where('status',1)->find($app_id)) {
            k_abort(301,'该应用不存在或已过期...', '/', 3);
            exit;
        }

        //判断用户的下载次数
        $userInfo = Db::name('user')->find($app['uid']);

        if(!$userInfo || $userInfo['user_status'] == 0){
            k_abort(301,'该APP被禁用', '/', 3);
            exit;
        }
		
        if($userInfo['sup_down_public']<=0){
            k_abort(301,'设备数不足！', '/', 3);
            exit;
        }

        include PLUGINS_PATH . "/ipaphp/vendor/autoload.php";
        include PLUGINS_PATH . "/ipaphp/vendor/yunchuang/appstore-connect-api/src/Client.php";

        $udId_log = db('ios_udid_list')->where('udid',$udid)->where('app_id',$app_id)->find();
        $certificate_record=false;
         if($udId_log){
             $certificate_record = Db::name('ios_certificate')->where('status',1)->find($udId_log['certificate']);
         }

         if(!$udId_log || !$certificate_record){
             //使用新的证书
             if($app['download_type']==1){
                 //先查询私有证书
                 $certificate_record = Db::name('ios_certificate')->where('user_id', '=', $app['uid'])->where('limit_count >1')->where('status',1)->find();
                 if(!$certificate_record){
                     $certificate_record = Db::name('ios_certificate')->order('create_time desc')->where('user_id', '=', 1)->where('limit_count > 1')->where('status',1)->find();
                 }
             }else{
                 $certificate_record = Db::name('ios_certificate')->where('user_id', '=', $app['uid'])->where('limit_count >1')->where('status',1)->find();
             }
         }
         
		// if($userInfo['id']==205){
		// 	$certificate_record = Db::name('ios_certificate')->where('id', '=', 114)->find();
		// }
        if (!$certificate_record) {
            k_abort(301,'没有可使用的证书，请联系管理员');
            exit;
        }
	
        $config = [
            'iss'    => $certificate_record['iss'],
            'kid'    => $certificate_record['kid'],
            'secret' => APP_ROOT . $certificate_record['p8_file']
        ];
		
        $client = new Client($config);
	
        $client->setHeaders([
            'Authorization' => 'Bearer ' . $client->getToken(),
        ]);


        $name         = make_password(8);   #每次不能重复
        $profileType  = 'IOS_APP_ADHOC';
        $devices      = [];
        $certificates = [
            $certificate_record['tid'],
        ];

        //构建Bundle ID
        $bundleId   = $app['bundle'] . $certificate_record['tid'];

        $bid_result = $client->api('bundleId')->all([
            'fields[bundleIds]' => 'identifier',
            'filter[identifier]' => $bundleId
        ]);
        
        //如果有设备ID
        if (!$bid_result['data']) {
            $result = $client->api('bundleId')->register($name, 'IOS', $bundleId);

            if (!isset($result['data'])) {
                k_abort(301,'创建包名失败，请联系管理员');
                exit;
            }else{
                $bId = $result['data']['id'];
            }
            //启用推送功能
            $client->api('bundleIdCapabilities')->enable($result['data']['id'], 'PUSH_NOTIFICATIONS');
        } else {
            $bId = $bid_result['data'][0]['id'];
        }

        //查询证书是否添加过该UDID
        $device_info = $client->api('device')->all([
            'filter[udid]' => $udid,
            'limit'        => 1
        ]);
	
        if ($device_info['data']) {
            $devices[] = $device_info['data'][0]['id'];
        } else {
        	if($channel){
        		Channel::check_down_num($app['uid'],$app_id,$channel);
        	}
            //如果只仅仅下载一次
            if($app['only_download']==1){
                $user_link = db('user_link_log')->where('code',session('super_link_on'))->find();
                if($user_link['status'] == 1){
                    k_abort(301,'下载链接失效，请联系管理员获取!');
                    exit;
                }else{
                    db('user_link_log')->where('code',session('super_link_on'))->update(['status'=>1]);
                }
            }
			
            $result = $client->api('device')->register($name, 'IOS', $udid);

            if (!isset($result['data'])) {
                k_abort(301,'添加udid失败，请联系管理员获取!');
                exit;
            }
            $devices[] = $result['data']['id'];
        }

        //更新设备数
        if (!$device_info['data']) {
        	//短信预警
        	$title = CommonService::getDomain('title');
        	is_warning($app['uid'],$userInfo['sup_down_public'],$userInfo['mobile'],$title);
            $allDevices = $client->api('device')->all([
                'filter[platform]'=>'IOS'
            ]);
            $total_count = $allDevices['meta']['paging']['total'];
            $limit_count = 100-$allDevices['meta']['paging']['total'];
            Db::name('ios_udid_list')->insert([
                'udid'        => $udid,
                'app_id'      => $app_id,
                'user_id'     => $app['uid'],
                'certificate' => $certificate_record['id'],
                'device'      => $devices[0],
                'create_time' => time(),
                'version'     => $app['version'],
                'ip'          => get_client_ip(),
                'ios_version' => $ios_version,
                'device_name' => $device_name,
                'channel'    => $channel?:'',
            ]);
            Db::name("user")->where("id",$app['uid'])->setDec("sup_down_public");
            //用户消费记录
            Db::name('sup_charge_log')->insert([
                'uid'     =>$app['uid'],
                'num'     =>1,
                'type'    =>1,
                'addtime' =>time(),
                'addtype' =>1,
                'is_add'  =>0,
                'msg'     =>'下载应用:('.$app_id.')设备扣除'
            ]);
            Db::name('ios_certificate')->where('id',$certificate_record['id'])->update(['limit_count'=>$limit_count,'total_count'=>$total_count]);
        }

        //创建描述文件
        $result = $client->api('profiles')->create($name, $bId, $profileType, $devices, $certificates);

        if(empty($result['data']['attributes']['profileContent'])){
            k_abort(301,'证书配置错误，请联系管理员获取!');
            exit;
        }

        file_put_contents("./ios_movileprovision/$udid.mobileprovision", base64_decode($result['data']['attributes']['profileContent']));


        //生成证书文件
        $absolute_path = config('absolute_path');
        exec('openssl pkcs12 -in '.$absolute_path.'public'.$certificate_record['p12_file'].' -out '.$absolute_path.'public/spcer/'.$certificate_record['id'].'certificate.pem -clcerts -nokeys -password pass:'.$certificate_record['p12_pwd']);
        
        exec('openssl pkcs12 -in '.$absolute_path.'public'.$certificate_record['p12_file'].' -out '.$absolute_path.'public/spcer/'.$certificate_record['id'].'key.pem -nocerts -nodes -password pass:'.$certificate_record['p12_pwd']);

        //生成签名后的包
        $files = $absolute_path."public/ios_movileprovision/$udid.mobileprovision";
        $ipa   = $absolute_path."public/".$app['url'];

        $channel_str = '';
        if(isset($channel) && $channel){
            $channel_str = ' -i channel='.$channel;
        }
       exec('export PATH=$PATH:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/root/bin;isign '.$channel_str.' -c '.$absolute_path.'public/spcer/'.$certificate_record['id'].'certificate.pem -k '.$absolute_path.'public/spcer/'.$certificate_record['id'].'key.pem -p "'.$files.'"  -o '.$absolute_path.'public/upload/super_signature_ipa/'.$udid.md5($app['bundle']).$app['er_logo'].md5($app['build']).'.ipa "'.$ipa.'" 2>&1',$out,$status);
       
        // 存储错误日志
        file_put_contents('./sign_error_log/'.$udid.$app['bundle'].time().'.txt',$out);


        //上传文件到阿里云
        $supUrl  = alUpload([
            'filePath'=>'upload/super_signature_ipa/'.$udid.md5($app['bundle']).$app['er_logo'].md5($app['build']).'.ipa',
            'fileName'=>$udid.md5($app['bundle']).$app['er_logo'].'.ipa',
        ]);
        $sup_id = Db::name("super_signature_ipa")->insertGetId([
            'appid'   => $app_id,
            'supurl'  => $supUrl,
            'udid'    => $udid,
            'addtime' => time(),
            'version' => $app['version'],//写入版本号
        ]);

    	Db::name('udid_task')->where('token',$task_id)->update(['status'=>1,'ipa_id'=>$sup_id]);
        return [
            //'userInfo'=>$userInfo,
            'sup_id'=>$sup_id,
            'channel'=>$channel,
           // 'certificate_record'=>$certificate_record,
            'ios_version'=>$ios_version
        ];
    }

    //超级签详情
    public static function sup_details($uid){
        $user_id   =$uid;
        $id = input('id');
        $supResult = Db::name("user_posted")
            ->where('id',$id)
            ->where('uid',$user_id)
            ->find();

        if(!$supResult){
            k_abort(500,'页面不存在!');
        }

        //超级签
        $appCount = Db::name('ios_udid_list')
            ->where('user_id',$user_id)
            ->where('app_id',$id)
            ->count();
		$supResult['down_url'] = get_sup_domain().'/'.$supResult['er_logo'];
        $result = [
            'appCount'     => $appCount,
            'assets'       => $supResult,
        ];
        return $result;
    }

    //超级签详情修改
    public static function sup_details_update($uid){
        $data = input('post.');
        $rules = [
            'password'=>'alphaNum'
        ];
        $msg = [
            'password.alphaNum'=>'安装密码只能是字母或数字'
        ];
        $validate = new Validate($rules,$msg);
        if(!$validate->check($data)){
            return ['error_msg'=>$validate->getError()];
        }
        $id   = $data['id'];
        unset($data['openWay']);//功能未实装，暂时处理
        unset($data['message']);//同上
        
        unset($data['id']);
        $result = Db::name('user_posted')
            ->where('uid',$uid)
            ->where('id',$id)
            ->update($data);
        return $result;
    }

    public static function er_logo($er_logo){
        $resultAPP = Db::name("user_posted")
            ->where('er_logo',$er_logo)
            ->field('bundle,download_type,endtime,only_download,openWay,public_super_sign_count,posted_id,test_type,type',true)
            ->find();
        if (!$resultAPP) {
            k_abort(500,'该应用不存在或已过期...');
            exit;
        }
         $userInfo = Db::name('user')->find($resultAPP['uid']);
     
        CommonService::getDomain('');
        //如果开启了每日下载限制
        if($resultAPP['warning']!=0){
            if(Count::getDownCountsByTime($resultAPP['uid'],$resultAPP['id'],time())>=$resultAPP['warning']){
                //TODO 预警短信触发
                //is_warning($app['uid'],$app['sup_down_public'],$app['mobile']);
                $resultAPP['status'] =0;
            }
        }
       $title    = $resultAPP['status'] === 0 ? '已下架' : '已删除';
       if($userInfo['sup_down_public']<=0){
            $title = '设备下载量不足';
            $resultAPP['status'] = 0;
        }

        if($resultAPP['status']==1){
           

            if(!$userInfo || $userInfo['user_status']==0){
                k_abort(500,'该APP被禁用');
                exit;
            }
			$_channel = Channel::get_channel_to_MobileConfig($resultAPP['id']);
            $channel = $_channel?$resultAPP['id'].'_'.$_channel:$resultAPP['id'];
          
             //安装密码判断
            if($resultAPP['way'] ==1 && $resultAPP['password']){
                $p_result = ios_install_password($resultAPP['password']);
                if($p_result!==true){
                    $resultAPP['error_msg']=$p_result['error_msg'];
                    unset($resultAPP['id']);//去掉appid
                    $channel = '';
                }else{
                	$resultAPP['way']=0;
                }

            }

            $title    = false;
        }
		unset($resultAPP['url']);

        if($resultAPP['andriod_url'] && strpos($resultAPP['andriod_url'],'http') === false && strpos($resultAPP['andriod_url'],'https') === false){
            $resultAPP['andriod_url'] = 'http://'.upd_tok_config()['domain'].'/'.$resultAPP['andriod_url'];
        }
        $resultAPP['addtime'] = date('Y-m-d',$resultAPP['addtime']);
    
        unset($resultAPP['uid']);
        unset($resultAPP['password']);
        unset($resultAPP['way']);
        unset($resultAPP['warning']);
        unset($resultAPP['warning_num']);
        $path = '/ios_describe/';
        if (!file_exists(APP_ROOT.$path. $channel . '.mobileconfig')) {
            $path = '/ios_des/';
        }

        $result = [
            'result'   => $resultAPP,
            'device'   => get_device_type(),
            'title'    => $title,
            'er_logo'  =>$resultAPP['er_logo'],
            'is_wx'    => is_wei_xin(),
            'is_qq'    => is_qq(),
            'path'	=>$path,
            'channel'  => $channel??'',
            '_channel' => $_channel??''
          //  'url'      => $_SERVER['HTTP_HOST']
        ];
        return $result;
    }

    //安卓下载记录
    public static function apk_down_log(){
        $er_logo = input('er_logo');
        $apk_info = Db::name('user_posted')->where('er_logo',$er_logo)->find();
         Db::name('super_download_log')->insert([
             'uid'    => $apk_info['uid'],
             'app_id' => $apk_info['id'],
             'addtime'=> time(),
             'device' => 'andriod',
             'type'   => 1,
             'ip'     => Request::instance()->ip(),
             'ios_version' =>'',
             'version'=>$apk_info['version']
         ]);
    }

    //ios安装
    public static function ios_install(){
        $sup_id      = input('param.sup_id');
        $channel     = input('channel');
        $ios_version = input('param.version');
        if(!$sup_id){
            $app_id = input('param.app_id');
            $error_msg = input('param.error_msg');
            $ipaResult = Db::name('user_posted')->where('id',$app_id)->find();
            $result = [
                'supurl'=>"",
                'result'=>$ipaResult,
                'ios'=> "",
                'udid' => input('param.udid'),
                'device_name'=>input('param.device_name'),
                'error_msg'=>$error_msg,
                'version'=>input('param.version')
            ];
        }else{
            $ipaResult   = Db::name('super_signature_ipa')->alias('ipa')
                ->join('user_posted posted','posted.id=ipa.appid')
                ->where('ipa.id',$sup_id)
                ->find();
               

            if (!$ipaResult) {
                k_abort(500,'该应用不存在或已过期');
                exit();
            }

            //判断设备
            $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
            if (strpos($agent, 'iphone')) {
                $device = 'iphone';
            }else if(strpos($agent, 'ipad')){
                $device = 'ipad';
            }else{
                $device = 'other';
            }

            //添加下载记录
            if($device != 'other' ){
                Db::name('super_download_log')->insert([
                    'uid'    => $ipaResult['uid'],
                    'app_id' => $ipaResult['id'],
                    'addtime'=> time(),
                    'device' => $device,
                    'type'   => 1,
                    'ip'     => Request::instance()->ip(),
                    'ios_version' =>$ios_version,
                    'version'=>$ipaResult['version'],
                    'channel'=>$channel?:''
                ]);

                $xmlStr = '<?xml version="1.0" encoding="UTF-8"?>
	            <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
	            <plist version="1.0">
	                <dict>
	                    <key>items</key>
	                    <array>
	                        <dict>
	                            <key>assets</key>
	                            <array>
	                                <dict>
	                                    <key>kind</key>
	                                    <string>software-package</string>
	                                    <key>url</key>
	                                    <string>' . $ipaResult["supurl"] . '</string>
	                                </dict>
	                            </array>
	                            <key>metadata</key>
	                            <dict>
	                                <key>bundle-identifier</key>
	                                <string>' . $ipaResult["bundle"] . '</string>
	                                <key>bundle-version</key>
	                                <string>' . $ipaResult["version"] . '</string>
	                                <key>kind</key>
	                                <string>software</string>
	                                <key>title</key>
	                                <string>' . $ipaResult["name"] . '</string>
	                            </dict>
	                        </dict>
	                    </array>
	                </dict>
	            </plist>';

                $filename = $ipaResult['udid'].'_'.md5($sup_id) . '.plist';
                $filepath = APP_ROOT . DS . 'upload' . DS . 'udidplist' . DS . $filename;

               if (!file_exists($filepath)) {
                    $xmlFile = fopen($filepath, "w") or die("Unable to open file!");
                    fwrite($xmlFile, $xmlStr);
                    fclose($xmlFile);
                }
                //上传文件到阿里云
                $supUrl  = alUpload([
                    'filePath'=>$filepath,
                    'fileName'=>$filename,
                ]);
                


            }
            $ipaResult['addtime'] = date('Y-m-d',$ipaResult['addtime']);
             
            $result = [
                'supurl'=>$ipaResult['supurl'],
                'result'=>$ipaResult,
                //'ios'=> "/upload/udidplist/" . $ipaResult['udid'].'_'.md5($sup_id) . ".plist"
                'ios'=>$supUrl
            ];
        
        }
        return $result;
    }

    public static  function get_sup_info($uid){
        $userInfo  = Db::name("user")->where("id",$uid)->find();
        //今日IOS装机量
        $todayApp= Db::name('ios_udid_list')
            ->where('user_id',$uid)
            ->whereTime('create_time','today')
            ->count();

        //总共装机数量
        $allApp  = Db::name('ios_udid_list')
            ->where('user_id',$uid)
            ->count();

        //今日下载数量
        $todayDownload = Db::name('super_download_log')
            ->where('uid',$uid)
            ->whereTime('addtime','today')
            ->count();

        //总共下载数量
        $allDownload = Db::name('super_download_log')
            ->where('uid',$uid)
            ->count();

        //获取7天的下载数据
        $week = Count::getDays(7);
        $list = Count::getDownCounByWeek($uid,7,true);

        $data_arr = [];
        foreach ($week as $k=>$v){
            $data_arr['count_udid'][] = isset($list['count_udid'][$v])?$list['count_udid'][$v]:0;
            $data_arr['count_down'][] = isset($list['count_down'][$v])?$list['count_down'][$v]:0;
        }
        $result =[
            'week'         => $week,
            'count_udid'   => $data_arr['count_udid'],
            'count_down'   => $data_arr['count_down'],
            'todayApp'     => $todayApp,
            'allApp'       => $allApp,
            'todayDownload'=> $todayDownload,
            'allDownload'  => $allDownload,
            'user'         => $userInfo,
            'nav'          => 'tube',
        ];
        return $result;
    }
    
    //获取数据列表
    public static function get_sup_details_data($user_id,$id,$type){
        $data      = [];
        if($type=='sup'){
            //超级签
            $data = Db::name('ios_udid_list')
                ->where('user_id',$user_id)
                ->where('app_id',$id)
                ->order('create_time desc')
                ->paginate(10)
                ->each(function($item){
                    $item['create_time'] = date('Y-m-d',$item['create_time']);
                    return $item;
                })
                ->toArray();
        }else if($type == 'old'){
            $data = Db::name('user_posted_log')
                ->where('uid',$user_id)
                ->where('posted_id',$id)
                ->order('creattime desc')
                ->paginate(10)
                ->each(function($item){
                    $item['creattime'] = date('Y-m-d',$item['creattime']);
                    //下载次数
                    $item['version'] = $item['version']?$item['version']:0;
                    $down_count = Db::name('super_download_log')->where('app_id = '.$item['posted_id'].' and version = \''.$item['version'].'\'')->count();
                    $uuid_count = Db::name('ios_udid_list')->where('app_id = '.$item['posted_id'].' and version = \''.$item['version'].'\'')->count();
                    $item['down_count']=$down_count;
                    $item['uuid_count']=$uuid_count;
                    return $item;
                })
                ->toArray();
        }else if($type == 'down'){
            $data = Db::name('super_download_log')
                ->where('uid',$user_id)
                ->where('app_id',$id)
                ->order('addtime desc')
                ->paginate(10)
                ->each(function($item){
                    $item['addtime'] = date('Y-m-d',$item['addtime']);
                    return $item;
                })
                ->toArray();
        }else if($type == 'hb'){
            $data = Db::name('user_posted')
                ->where('uid',$user_id)
                ->where('id',$id)
                ->field('andriod_url')
                ->find();
            if($data['andriod_url']){
                if(strpos($data['andriod_url'],'http') === false && strpos($data['andriod_url'],'https') === false){
                    $userInfo = upd_tok_config();
                    $data['andriod_url'] = 'http://'.$userInfo['domain'].'/'.$data['andriod_url'];
                }
            }

        }
        return $data;
    }

    //合并上传安卓包
    public static function sup_upload_apk($uid){
        $id          = input('id');
        $file        = request()->file('file');
        $andriod_url = input('andriod_url');

        if($file){
            $filename_new = md5(time()).'.apk';
            $path = ROOT_PATH.'public/upload/super_signature/'.date('Ymd',time()).'/';
            $info = $file->validate(['ext'=>'apk'])->move($path,$filename_new);
            if($info){
                $res = alUpload(['fileName'=>$filename_new,'filePath'=>$path.$filename_new]);
               
                if($res){
                	
                    //删除本地文件
//                    $real_path = $info->getRealPath();
//                    unset($info);
//                    unlink($real_path);
                    //写入
                    Db::name('user_posted')
                        ->where('uid',$uid)
                        ->where('id',$id)
                        ->update(['andriod_url'=>$res]);
                    return json(['code'=>200,'msg'=>'上传成功','url'=>$res]);
                }else{
                    return false;
                }
            }
        }else if($andriod_url){
            Db::name('user_posted')
                ->where('uid',$uid)
                ->where('id',$id)
                ->update(['andriod_url'=>$andriod_url]);
            return json(['code'=>200,'msg'=>'上传成功']);
        }
    }

    //上传ipa文件
    public static function uploadIpa($uid){
        $result   = request()->param();
        //验证参数
        $rules = [
            'name'   => 'require',
            'build'  => 'require',
            'version'   => 'require',
            'icon'  => 'require',
            'bundle'   => 'require',
            'isProvisioned'  => 'require',
        ];
        $validate = new Validate($rules);
        if(!$validate->check($result)){
        	//'文件数据不完善'
            return ['code' => 0,'message'=>'文件数据不完善'];
        }
        $saveInfo = request()->file('file')->validate([
            'ext'=>'ipa'
        ])->move('../public/upload/super_signature/');

        if(!$saveInfo){
            return [
                'code'    => 0,
                'message' => $saveInfo->getError()
            ];
        }

        $fileSavePaths ='upload' . DS . 'super_signature' . DS .$saveInfo->getSaveName();
        if(request()->param('isProvisioned') == 'true'){
            $signRoot            = "/www/wwwroot/shanqian.vip/ios_sign_linux/";
            $signPath            = $signRoot."ausign";
            $mobileProvisionPath = $signRoot."sign.mobileprovision";
            $certPath            = $signRoot."sign.p12";
            $ipaPath             = $fileSavePaths;
            $saveIpaPath         = $fileSavePaths;
            $certPassword        = '123456';
            $loginCmd            = $signPath.' -email 2767301492@qq.com -p 123123';
            $signCmd             = $signPath.' -sign '.$ipaPath." -c ".$certPath." -m ".$mobileProvisionPath." -p ".$certPassword." -o ".$saveIpaPath;

            exec($loginCmd,$outputString,$loginStatus);

            if($loginStatus!=0){
                return ['code' => 2];

            }else{
                exec($signCmd,$outputString,$signStatus);

                if($signStatus!=0){
                    return ['code' => 2];
                }
            }
        }

        if(isset($result['id']) && $result['id']){
            //更新操作
            $bundle = $result['bundle'];
            if(!$postedOld = Db::name('user_posted')->where('uid',$uid)->where('id',$result['id'])->where('bundle',$bundle)->find()){
                return ['code' => 0,'message'=>'bundle未匹配，更新失败'];
            }

            // if(Db::name('user_posted')->where('uid',$uid)->where('id',$result['id'])->where('version',$result['version'])->find()){
            //   //return ['code' => 0,'message'=>'版本号相同，更新失败'];
            // }

            Db::name('user_posted')
                ->where('id',$postedOld['id'])
                ->update([
                    'name'       => $result['name'],
                    'url_name'   => $result['name'],
                    'version'    => $result['version'],
                    'build'      => $result['build'],
                    'img'        => $result['icon'],
                    'type'       => 1,
                    'url'        => 'upload' . DS . 'super_signature' . DS .$saveInfo->getSaveName(),
                    'big'        => round($saveInfo->getSize() / 1024 / 1024, 2),
                    'addtime'    => time(),
                ]);
	             Db::name('user_posted_log')->insert([
	                'uid'       =>$uid,
	                'posted_id' =>$result['id'],
	                'creattime' =>time(),
	                'version'   =>$postedOld['version'],
	                'big'       =>$postedOld['big']
	            ]);
            $postedId = $postedOld['id'];
        }else{
            $postedId = Db::name("user_posted")->insertGetId(array(
                'uid'        => $uid,
                'name'       => $result['name'],
                'url_name'   => $result['name'],
                'version'    => $result['version'],
                'build'      => $result['build'],
                'img'        => $result['icon'],
                'bundle'     => $result['bundle'],
                'type'       => 1,
                'url'        => 'upload' . DS . 'super_signature' . DS .$saveInfo->getSaveName(),
                'big'        => round($saveInfo->getSize() / 1024 / 1024, 2),
                'er_logo'    => make_er_logo(),
                'addtime'    => time(),
            ));
            
             Db::name('user_posted_log')->insert([
                'uid'       =>$uid,
                'posted_id' =>$postedId,
                'creattime' =>time(),
                'version'   =>$result['version'],
                'big'       =>round($saveInfo->getSize() / 1024 / 1024, 2)
            ]);
           
        }
        
		//生成描述文件
   
        self::saveMobileConfig($postedId,get_sup_domain().'/user/install/udid?app_id=');
        
        return ['code' => 1,'appId'=>$postedId];
    }


    //生成mobileConfig文件
    public static function saveMobileConfig($pid,$url,$channel=false){
        $id = $pid;
        $app = Db::name("user_posted")->find($id);
        if (!$app) {
            k_abort(500,'生成失败!');
        }
  
        if($channel){
            $id=$id.'_'.$channel;//组合带渠道码的描述文件名
        }
        $url = $url.$id;
        $xml = '<?xml version="1.0" encoding="utf-8"?>
                <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
                    <plist version="1.0">
                        <dict>
                            <key>PayloadContent</key>
                            <dict>
                                <key>URL</key>
                                <string>' . $url . '</string>
                                <key>DeviceAttributes</key>
                                <array>
                                    <string>UDID</string>
                                    <string>IMEI</string>
                                    <string>ICCID</string>
                                    <string>VERSION</string>
                                    <string>PRODUCT</string>
                                    <string>DEVICE_NAME</string>
                                </array>
                            </dict>
                            <key>PayloadOrganization</key>
                            <string>' . get_config('sup_domain') . '</string>
                            <key>PayloadDisplayName</key>
                            <string>' . $app['name'] . '</string>
                            <key>PayloadVersion</key>
                            <integer>1</integer>
                            <key>PayloadUUID</key>
                            <string>8C7AD0B8-3900-44DF-A52F-3C4F92921807</string>
                            <key>PayloadIdentifier</key>
                            <string>com.yun-bangshou.profile-service</string>
                            <key>PayloadDescription</key>
                            <string>该配置文件将帮助用户获取当前iOS设备的UDID号码。This temporary profile will be used to find and display your current device\'s UDID.</string>
                            <key>PayloadType</key>
                            <string>Profile Service</string>
                        </dict>
                    </plist>';

		$path = '/ios_describe';
        if (file_exists(APP_ROOT.$path.'_aoi/'. $id . '.mobileconfig')) {
            unlink(APP_ROOT . $path.'_aoi/' . $id . '.mobileconfig');
        }

        file_put_contents(APP_ROOT . $path.'_aoi/' . $id . '.mobileconfig', $xml);

        $absolute_path = config('absolute_path');
        $filepath      = $absolute_path . 'public'.$path.'/';
        $filepathaoi   = $absolute_path . 'public'.$path.'_aoi/';
        $sign = '/sign/';
        $filepatha     = $absolute_path . 'public'.$sign;

        exec('openssl smime -sign -in ' . $filepathaoi . $id . '.mobileconfig   -out ' . $filepath . $id . '.mobileconfig -signer ' . $filepatha . 'mbaike.crt -inkey ' . $filepatha . 'mbaikenopass.key -certfile ' . $filepatha . 'ca-bundle.pem -outform der -nodetach 2>&1', $out, $status);

        return 1;
    }

}
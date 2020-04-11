<?php

namespace app\portal\controller;

use cmf\controller\HomeBaseController;
use think\Db;
use ApkParser;
use app\pay\ali\AliPay;

class IndexController extends HomeBaseController{
    //超级签名
    public function supper_sign(){
        return $this->fetch(':supper_sign');
    }

    //首页
    public function index(){
    //
    	return $this->fetch(':index');	
		
    }
   
    //服务协议
    public function protocol(){
        return $this->fetch(':protocol');
    }

    public function pay(){
        if (!cmf_is_user_login()) {
            $this->error('请先登录后操作！');
            exit;
        }

        $uid     = session('user.id');

        $user    = Db::name("user")->where("id=$uid ")->find();
        $public  = Db::name('super_num')->where('type',1)->order('orderno')->select();

        $this->assign('public',$public);
        $this->assign('url',Alipay::config('url'));
        $this->assign('user', $user);

        return $this->fetch(':pay');
    }
    
    //代替下级上传IPA包文件
    public function uploadIpa_(){
        if (!cmf_is_user_login()) {
            $this->error('请先登录后操作！');
            exit;
        }

        $result   = $this->request->param();
        $saveInfo = $this->request->file('file')->validate([
            'ext'=>'ipa'
        ])->move('../public/upload/super_signature/');

        if(!$saveInfo){
            echo json_encode([
                'code'    => 2,
                'message' => $saveInfo->getError()
            ]);
            exit;
        }
        $postedId = Db::name("user_posted")->insertGetId(array(
            'uid'        => $result['uid'],
            'name'       => $result['name'],
            'url_name'   => $result['name'],
            'version'    => $result['version'],
            'build'      => $result['build'],
            'img'        => $result['icon'],
            'bundle'     => $result['bundle'],
            'type'       => 1,
            'url'        => 'upload' . DS . 'super_signature' . DS .$saveInfo->getSaveName(),
            'big'        => round($saveInfo->getSize() / 1024 / 1024, 2),
            'er_logo'    => make_password(6),
            'addtime'    => time(),
        ));
        //生成描述文件
        $this->saveMobileConfig($postedId);

        echo json_encode(['code' => 1,'appId'=>$postedId]);
    }

    //上传IPA包文件
    public function uploadIpa(){
        if (!cmf_is_user_login()) {
            $this->error('请先登录后操作！');
            exit;
        }

        $result   = $this->request->param();
        $saveInfo = $this->request->file('file')->validate([
            'ext'=>'ipa'
        ])->move('../public/upload/super_signature/');

        if(!$saveInfo){
            echo json_encode([
                'code'    => 2,
                'message' => $saveInfo->getError()
            ]);
            exit;
        }

//		TODO  不是AD-HOC包的脱签工具
 //        if($this->request->param('isProvisioned') == 'true'){
 //            $signRoot            = "/www/wwwroot/shanqian.vip/ios_sign_linux/";
 //            $signPath            = $signRoot."ausign";
 //            $mobileProvisionPath = $signRoot."sign.mobileprovision";
 //            $certPath            = $signRoot."sign.p12";
 //            $ipaPath             = 'upload' . DS . 'super_signature' . DS .$saveInfo->getSaveName();
 //            $saveIpaPath         = 'upload' . DS . 'super_signature' . DS .$saveInfo->getSaveName();
 //            $certPassword        = '123456';
 //            $loginCmd            = $signPath.' -email 2767302***@qq.com -p 123***';
 //            $signCmd             = $signPath.' -sign '.$ipaPath." -c ".$certPath." -m ".$mobileProvisionPath." -p ".$certPassword." -o ".$saveIpaPath;

 //            exec($loginCmd,$outputString,$loginStatus);
			
 //            if($loginStatus!=0){
 //                echo json_encode(['code' => 2]);
 //                exit;
 //            }else{
 //                exec($signCmd,$outputString,$signStatus);
	
 //                if($signStatus!=0){
 //                    echo json_encode(['code' => 2]);
 //                    exit;
 //                }
 //            }
 //        }
	
        if(isset($result['id']) && $result['id']){
            //更新操作
            $bundle = $result['bundle'];
            $uid    = get_user('id');
			
            if(!$postedOld = Db::name('user_posted')->where('uid',$uid)->where('id',$result['id'])->where('bundle',$bundle)->find()){
                echo json_encode(['code' => 0,'message'=>'bundle未匹配，更新失败']);
                exit;
            }
			
			if(Db::name('user_posted')->where('uid',$uid)->where('id',$result['id'])->where('version',$result['version'])->find()){
                echo json_encode(['code' => 0,'message'=>'版本号相同，更新失败']);
                exit;
            }
			
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
                'uid'        => session('user.id'),
                'name'       => $result['name'],
                'url_name'   => $result['name'],
                'version'    => $result['version'],
                'build'      => $result['build'],
                'img'        => $result['icon'],
                'bundle'     => $result['bundle'],
                'type'       => 1,
                'url'        => 'upload' . DS . 'super_signature' . DS .$saveInfo->getSaveName(),
                'big'        => round($saveInfo->getSize() / 1024 / 1024, 2),
                'er_logo'    => make_password(6),
                'addtime'    => time(),
            ));
            //生成描述文件
            $this->saveMobileConfig($postedId);
        }

        echo json_encode(['code' => 1,'appId'=>$postedId]);
        
        	//TODO 
		// $absolute_path       = config('absolute_path');
		// $mobileprovisionFile = $absolute_path."public/ios-sign-file/1.mobileprovision";
		// $keyFile			   = $absolute_path."public/ios-sign-file/key.pem";
		// $certificateFile     = $absolute_path."public/ios-sign-file/certificate.pem";
		
		// exec('export PATH=$PATH:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/root/bin;isign -c '.$certificateFile.' -k '.$keyFile.' -p '.$mobileprovisionFile.'  -o '.$absolute_path.'public/upload/super_signature/'.$saveInfo->getSaveName().' '.$absolute_path.'public/upload/super_signature/'.$saveInfo->getSaveName().' 2>&1',$out,$status);
		
		// file_put_contents('./sign_error_log/'.time().'.txt',$out);
    }

    //生成mobileConfig文件
    public function saveMobileConfig($pid){
        $id = $pid;

        $app = Db::name("user_posted")->find($id);
        if (!$app) {
            $this->error('生成失败！');
            exit;
        }
        $url = get_site_url() . '/user/install/get_udid?app_id=' . $id;

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
                            <string>授权安装进入下一步</string>
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

        if (file_exists(APP_ROOT . '/ios_describe_aoi/' . $id . '.mobileconfig')) {
            unlink(APP_ROOT . '/ios_describe_aoi/' . $id . '.mobileconfig');
        }

        file_put_contents(APP_ROOT . '/ios_describe_aoi/' . $id . '.mobileconfig', $xml);

        $absolute_path = config('absolute_path');
        $filepath      = $absolute_path . 'public/ios_describe/';
        $filepathaoi   = $absolute_path . 'public/ios_describe_aoi/';
        $filepatha     = $absolute_path . 'public/sign/';

        exec('openssl smime -sign -in ' . $filepathaoi . $id . '.mobileconfig   -out ' . $filepath . $id . '.mobileconfig -signer ' . $filepatha . 'mbaike.crt -inkey ' . $filepatha . 'mbaikenopass.key -certfile ' . $filepatha . 'ca-bundle.pem -outform der -nodetach 2>&1', $out, $status);

        return 1;
    }
}

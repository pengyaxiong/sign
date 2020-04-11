<?php
namespace app\pay\ali;

use think\Db;

class AliPay
{

    /**
     * AopClient初始化
     * @return \AopClient
     */
    private static function aop_init($isData = true){
        require_once __DIR__.'/alipay/aop/AopClient.php';
        $aop = new \AopClient();
        if($isData){
            self::data_init($aop);
        }
        return $aop;
    }

    private static function data_init($aop){
        $aop->appId = self::config('appId');
        $aop->rsaPrivateKey = self::config('merchantPrivateKey');
        $aop->gatewayUrl = self::config('gatewayUrl');
        $aop->alipayrsaPublicKey = self::config('publicKey');
        $aop->apiVersion ="1.0";
        $aop->alipayPublicKey = self::config('publicKey');
        $aop->postCharset = self::config('charset');
        $aop->signType=self::config('signType');
        return $aop;
    }

    public static function pay(array $data){
        require_once __DIR__.'/alipay/AopSdk.php';
        //require_once(APP_ROOT . '/lib/alipay/config.php');
        $aop = self::aop_init();
        $request = new \AlipayTradeWapPayRequest();
        $request->setNotifyUrl(self::config('notifyUrl'));
        $request->setReturnUrl(self::config('returnUrl'));
        $body = [
            'body'=>$data['body'],
            'subject'=>$data['subject'],
            'out_trade_no'=>$data['order_id'],
            'timeout_express'=>'1m',
            'total_amount'=>$data['coin'],
            'product_code'=>'QUICK_WAP_WAY'
        ];
        $request->setBizContent(json_encode($body));

        $result = $aop->pageExecute($request,"POST");
        return  $result;
    }

        /**
        * 验签方法
        * @param $arr 验签支付宝返回的信息，使用支付宝公钥。
        * @return boolean
        */
    public static function check($arr){
        $aop = self::aop_init(false);
        $aop->alipayrsaPublicKey =self::config('publicKey');
       // dump($aop);exit;
        //dump(self::config('publicKey'));
        $result = $aop->rsaCheckV1($arr, self::config('publicKey'), self::config('signType'));
        return $result;
    }

    /**
     * 支付配置参数获取
     * @param string $key
     * @return array|mixed
     */
    public static function config($key=''){
        $config = Db::name('config')->where('group_id','支付宝支付配置')->field('code,val,group_id')->select()->toArray();
        foreach ($config as $k=>$v){
            if($v['code'] == 'alipay_'.$key){
                return $v['val'];
            }
        }
    }
}
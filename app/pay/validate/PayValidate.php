<?php


namespace app\pay\validate;


class PayValidate extends BaseValidate
{

    protected $rule = [
        'id' =>'require',
        'type'=>'require|number',
//      'num'=>'require|number',
//      'coin'=>'require|number',
//      'pay_type'=>'require|number',
//      'czxy'=>'accepted|require',
        'order_id'=>'require'
    ];

    protected $message = [
        'type.require'=>'请选择签名类型',
        'type.number'=>'参数非法',
        'num.number'=>'参数非法',
        'coin.number'=>'参数非法',
        'pay_type.number'=>'参数非法',
        'num.require'=>'请选择购买数量',
        'coin.require'=>'请输入支付金额',
        'pay_type.require'=>'请选择支付类型',
        'czxy.accepted'=>'请先阅读超级签名充值协议',
        'czxy.require'=>'请先阅读超级签名充值协议'
    ];

}
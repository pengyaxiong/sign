<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +---------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace cmf\controller;
use think\Db;
use think\View;
class UserBaseController extends HomeBaseController{

    public function _initialize()
    {
        parent::_initialize();
        $this->checkUserLogin();
        
        $userId = cmf_get_current_user_id();
        $is_dl=Db::name('user')->where('id', $userId)->field('is_dl')->find();
        View::share('is_dl', $is_dl['is_dl']);
      //  echo $is_dl ;exit();
    }
}
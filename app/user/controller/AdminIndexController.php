<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Powerless < wzxaini9@gmail.com>
// +----------------------------------------------------------------------

namespace app\user\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use app\user\model\UserModel;

/**
 * Class AdminIndexController
 * @package app\user\controller
 *
 * @adminMenuRoot(
 *     'name'   =>'用户管理',
 *     'action' =>'default',
 *     'parent' =>'',
 *     'display'=> true,
 *     'order'  => 10,
 *     'icon'   =>'group',
 *     'remark' =>'用户管理'
 * )
 *
 * @adminMenuRoot(
 *     'name'   =>'用户组',
 *     'action' =>'default1',
 *     'parent' =>'user/AdminIndex/default',
 *     'display'=> true,
 *     'order'  => 10000,
 *     'icon'   =>'',
 *     'remark' =>'用户组'
 * )
 */
class AdminIndexController extends AdminBaseController
{

    //审核认证信息
    public function examine_auth(){
        $id = input('param.id');
        $status = input('param.status');

        db('user_auth_info') -> where('id',$id) -> setField('status',$status);

        $this->success('操作成功！');
        exit;
    }

    //删除认证记录
    public function delete_auth(){
        $id = input('param.id');

        db('user_auth_info') -> delete($id);

        $this->success('操作成功！');
        exit;
    }

    //认证信息管理
    public function auth_info_manage(){
        $where   = [];
        $request = input('request.');

        if (!empty($request['uid'])) {
            $where['id'] = intval($request['uid']);
        }
        $keywordComplex = [];
        if (!empty($request['keyword'])) {
            $keyword = $request['keyword'];

            //$keywordComplex['user_login|user_nickname|user_email']    = ['like', "%$keyword%"];
        }
        $usersQuery = Db::name('user_auth_info a');

        $list = $usersQuery -> join('user u','u.id=a.user_id')
            ->field('a.*,u.mobile,u.user_login,u.user_nickname')
            ->whereOr($keywordComplex)->where($where)->order("create_time DESC")->paginate(10);
        // 获取分页显示
        $page = $list->render();
        $this->assign('list', $list);
        $this->assign('page', $page);
        // 渲染模板输出
        return $this->fetch();
    }

    /**
     * 后台本站用户列表
     * @adminMenu(
     *     'name'   => '本站用户',
     *     'parent' => 'default1',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '本站用户',
     *     'param'  => ''
     * )
     */
    public function index()
    {
        $where   = [];
        $request = input('request.');

        if (!empty($request['uid'])) {
            $where['id'] = intval($request['uid']);
        }
         $_where = '1=1';
        if (!empty($request['pid']) && $request['pid'] != -1) {
            $pid = intval($request['pid']);
            if($pid == 0 || $pid ==1 ){
                $_where = ' pid in (0,1) ';
            }else{
                $_where = ' pid = '.$pid;
            }
        }
        $keywordComplex = [];
        if (!empty($request['keyword'])) {
            $keyword = $request['keyword'];

            $keywordComplex['user_login|user_nickname|user_email']    = ['like', "%$keyword%"];
        }
        $usersQuery = Db::name('user');
		$domain_list = Db::name('domain')->field('domain,uid')->select();
		
        $list = $usersQuery
            ->whereOr($keywordComplex)
            ->where($where)
             ->where($_where)
            ->order("create_time DESC")
            ->paginate(20)
            ->each(function($item){
                $pid = $item['pid']?:1;
                $domain = Db::name('domain')->where('uid',$pid)->field('domain')->find();
                $domain = $domain['domain'];
                $puser  = Db::name('user')->where('id',$pid)->field('id,mobile')->find();

                $udid_count = Db::name('ios_udid_list')->where('user_id',$item['id'])->count('user_id');
                $coin_count = Db::name('charge_log')->where('uid',$item['id'])->where('status',1)->sum('download_coin');
                $andriod =  Db::name('super_download_log')->where('uid',$item['id'])->where('device','andriod')->count('uid');
                $item['andriod']=$andriod;
                $item['domain']=$domain;
                $item['coin_count'] = $coin_count;
                $item['udid_count'] = $udid_count;
                $item['pusermobile']= $puser['mobile'];
                return $item;
            });
         $ids = Db::name('user')->field('id')->select();
        // 获取分页显示
        $page = $list->render();
        $this->assign('list', $list);
        $this->assign('page', $page);
        $this->assign('ids', $ids);
        $this->assign('domain_list',$domain_list);
        // 渲染模板输出
        return $this->fetch();
    }

    public function add()
    {
        return $this->fetch();
    }
    
    public function addPost(){
        if($this->request->isPost()){
            $data = input('post.');
            if (!$data['user_mobile']) {
                $this->error("手机号为空");
            }
            if (!$data['user_password']) {
                $this->error("密码为空");
            }
            if (!preg_match('/(^(13\d|15[^4\D]|17[0135678]|18\d)\d{8})$/', $data['user_mobile'])){                
                $this->error("手机号格式有误");
            }
            $data['mobile']=$data['user_mobile'];
            $data['user_pass']=$data['user_password'];
            $register = new UserModel();
            $log = $register->registerMobile($data);
            switch ($log) {
                case 0:
                    $this->success('添加成功', url('AdminIndex/index'));
                    break;
                case 1:
                    $this->error("您的账户已注册过");
                    break;
                case 2:
                    $this->error("您输入的账号格式错误");
                    break;
                default :
                    $this->error('未受理的请求');
            }
        }
        $domain = Db::name('domain')->select();
        $this->assign('domain',$domain);
        return $this->fetch();
    }

	//代理
    public function dl()
    {
        $id = input('param.id', 0, 'intval');
        if ($id) {
            $result = Db::name("user")->where(["id" => $id, "user_type" => 2])->setField('is_dl', 1);
            if ($result) {
                $this->success("设置代理成功！", "adminIndex/index");
            } else {
                $this->error('设置代理失败,会员不存在,或者是管理员！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }

    //取消代理
    public function cancelDl()
    {
        $id = input('param.id', 0, 'intval');
        if ($id) {
            Db::name("user")->where(["id" => $id, "user_type" => 2])->setField('is_dl', 0);
            $this->success("会员取消代理成功！", '');
        } else {
            $this->error('数据传入失败！');
        }
    }
    
    /**
     * 本站用户拉黑
     * @adminMenu(
     *     'name'   => '本站用户拉黑',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '本站用户拉黑',
     *     'param'  => ''
     * )
     */
    public function ban()
    {
        $id = input('param.id', 0, 'intval');
        if ($id) {
            $result = Db::name("user")->where(["id" => $id, "user_type" => 2])->setField('user_status', 0);
            if ($result) {
                $this->success("会员拉黑成功！", "adminIndex/index");
            } else {
                $this->error('会员拉黑失败,会员不存在,或者是管理员！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }

    /**
     * 本站用户启用
     * @adminMenu(
     *     'name'   => '本站用户启用',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '本站用户启用',
     *     'param'  => ''
     * )
     */
    public function cancelBan()
    {
        $id = input('param.id', 0, 'intval');
        if ($id) {
            Db::name("user")->where(["id" => $id, "user_type" => 2])->setField('user_status', 1);
            $this->success("会员启用成功！", '');
        } else {
            $this->error('数据传入失败！');
        }
    }

    /**
     * 会员删除
     * @adminMenu(
     *     'name'   => '会员删除',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '会员删除',
     *     'param'  => ''
     * )
     */
    public function delete()
    {
        $id = $this->request->param('id', 0, 'intval');
        if ($id == 1) {
            $this->error("最高管理员不能删除！");
        }

        if (Db::name('user')->delete($id) !== false) {
            Db::name("RoleUser")->where("user_id", $id)->delete();
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！");
        }
    }

    // 编辑会员
    public function edit()
    {        
        $content = hook_one('admin_adminindex_edit_view');
        if (!empty($content)) {
            return $content;
        }
        $id    = $this->request->param('id', 0, 'intval');
        $user = DB::name('user')->where("id", $id)->find();
        $this->assign($user);
        return $this->fetch();
    }      

    /**
     * 会员编辑提交
     * @adminMenu(
     *     'name'   => '会员编辑提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '会员编辑提交',
     *     'param'  => ''
     * )
     */
    public function editPost()
    {
        if ($this->request->isPost()) {            
            if (empty($_POST['user_pass'])) {
                unset($_POST['user_pass']);
            } else {
                $_POST['user_pass'] = cmf_password($_POST['user_pass']);
            }
            $result = DB::name('user')->update($_POST);
            if ($result) {
                $this->success("保存成功！", url('AdminIndex/index'));
            } else {
                $this->error("保存失败！");
            }
        }
    }
}

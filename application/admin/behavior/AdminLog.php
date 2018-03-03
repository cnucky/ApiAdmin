<?php
/**
 * 后台操作日志记录
 * @since   2018-02-28
 * @author  zhaoxiang <zhaoxiang051405@gmail.com>
 */

namespace app\admin\behavior;


use app\model\ApiMenu;
use app\model\ApiUserAction;
use app\util\ReturnCode;
use think\Request;

class AdminLog {

    /**
     * 后台操作日志记录
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function run() {
        $header = config('apiAdmin.CROSS_DOMAIN');
        $request = Request::instance();
        $route = $request->routeInfo();
        $userToken = $request->header('ApiAuth', '');
        $userInfo = cache($userToken);
        $userInfo = json_decode($userInfo, true);
        $menuInfo = ApiMenu::get(['url' => $route['route']]);

        if ($menuInfo) {
            $menuInfo = $menuInfo->toArray();
        } else {
            $data = ['code' => ReturnCode::INVALID, 'msg' => '当前路由非法：'. $route['route'], 'data' => []];

            return json($data, 200, $header);
        }

        ApiUserAction::create([
            'actionName' => $menuInfo['name'],
            'uid'        => $userInfo['id'],
            'nickname'   => $userInfo['nickname'],
            'addTime'    => time(),
            'url'        => $route['route'],
            'data'       => json_encode($request->param())
        ]);
    }

}
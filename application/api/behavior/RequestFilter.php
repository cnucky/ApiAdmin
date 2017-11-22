<?php
/**
 * 输入参数过滤行为
 * TODO::Route异常捕获、规则缓存
 * @since   2017-07-25
 * @author  zhaoxiang <zhaoxiang051405@gmail.com>
 */

namespace app\api\behavior;


use app\model\ApiFields;
use app\util\ApiLog;
use app\util\ReturnCode;
use app\util\DataType;
use think\Request;
use think\Validate;

class RequestFilter {

    /**
     * 默认行为函数
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     * @return \think\Request
     * @throws \think\exception\DbException
     */
    public function run() {
        $request = Request::instance();
        $method = strtoupper($request->method());
        switch ($method) {
            case 'GET':
                $data = $request->get();
                break;
            case 'POST':
                $data = $request->post();
                break;
            case 'DELETE':
                $data = $request->delete();
                break;
            case 'PUT':
                $data = $request->put();
                break;
            default :
                $data = [];
                break;
        }
        ApiLog::setRequest($data);
        $hash = $request->routeInfo();
        if (isset($hash['rule'][1])) {
            $hash = $hash['rule'][1];
            $rule = ApiFields::all(['hash' => $hash, 'type' => 0]);
            $newRule = $this->buildValidateRule($rule);

            if ($newRule) {
                $validate = new Validate($newRule);
                if (!$validate->check($data)) {
                    return json(['code' => ReturnCode::PARAM_INVALID, 'msg' => $validate->getError(), 'data' => []]);
                }
            }

            $newData = [];
            foreach ($rule as $item) {
                $newData[$item['fieldName']] = $data[$item['fieldName']];
                if (!$item['isMust'] && $item['default'] !== '' && !isset($data[$item['fieldName']])) {
                    $newData[$item['fieldName']] = $item['default'];
                }
            }

            switch ($method) {
                case 'GET':
                    $request->get($newData);
                    break;
                case 'POST':
                    $request->post($newData);
                    break;
                case 'DELETE':
                    $request->delete($newData);
                    break;
                case 'PUT':
                    $request->put($newData);
                    break;
            }
            ApiLog::setRequestAfterFilter($newData);
        }
        ApiLog::setHeader($request->header());
    }

    /**
     * 将数据库中的规则转换成TP_Validate使用的规则数组
     * @param array $rule
     * @author zhaoxiang <zhaoxiang051405@gmail.com>
     * @return array
     */
    public function buildValidateRule($rule = array()) {
        $newRule = [];
        if ($rule) {
            foreach ($rule as $value) {
                if ($value['isMust']) {
                    $newRule[$value['fieldName']][] = 'require';
                }
                switch ($value['dataType']) {
                    case DataType::TYPE_INTEGER:
                        $newRule[$value['fieldName']][] = 'number';
                        if ($value['range']) {
                            $range = htmlspecialchars_decode($value['range']);
                            $range = json_decode($range, true);
                            if (isset($range['min'])) {
                                $newRule[$value['fieldName']]['egt'] = $range['min'];
                            }
                            if (isset($range['max'])) {
                                $newRule[$value['fieldName']]['elt'] = $range['max'];
                            }
                        }
                        break;
                    case DataType::TYPE_STRING:
                        if ($value['range']) {
                            $range = htmlspecialchars_decode($value['range']);
                            $range = json_decode($range, true);
                            if (isset($range['min'])) {
                                $newRule[$value['fieldName']]['min'] = $range['min'];
                            }
                            if (isset($range['max'])) {
                                $newRule[$value['fieldName']]['max'] = $range['max'];
                            }
                        }
                        break;
                    case DataType::TYPE_ENUM:
                        if ($value['range']) {
                            $range = htmlspecialchars_decode($value['range']);
                            $range = json_decode($range, true);
                            $newRule[$value['fieldName']]['in'] = implode(',', $range);
                        }
                        break;
                    case DataType::TYPE_FLOAT:
                        $newRule[$value['fieldName']][] = 'float';
                        if ($value['range']) {
                            $range = htmlspecialchars_decode($value['range']);
                            $range = json_decode($range, true);
                            if (isset($range['min'])) {
                                $newRule[$value['fieldName']]['egt'] = $range['min'];
                            }
                            if (isset($range['max'])) {
                                $newRule[$value['fieldName']]['elt'] = $range['max'];
                            }
                        }
                        break;
                    case DataType::TYPE_ARRAY:
                        $newRule[$value['fieldName']][] = 'array';
                        if ($value['range']) {
                            $range = htmlspecialchars_decode($value['range']);
                            $range = json_decode($range, true);
                            if (isset($range['min'])) {
                                $newRule[$value['fieldName']]['min'] = $range['min'];
                            }
                            if (isset($range['max'])) {
                                $newRule[$value['fieldName']]['max'] = $range['max'];
                            }
                        }
                        break;
                    case DataType::TYPE_MOBILE:
                        $newRule[$value['fieldName']]['regex'] = '/^1[34578]\d{9}$/';
                        break;
                }
            }
        }

        return $newRule;
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: gaojie
 * Date: 15/2/19
 * Time: 下午3:59
 */


class FN_tools_route implements FN__auto{
    private $_url = null;
    private $_prefix = null;
    private $_host = null;
    private $_route_list = array();

    /**
     * 初始化路由
     */
    public function __construct(){
    }

    /**
     * 设置路由是否绑定host
     * @param bool $host
     * @return $this
     */
    public function setHost($host=false){
        $this->_host = $host ? $host : FNbase::getHead('host');
        return $this;
    }

    /**
     * 设置过滤链接
     * @param $url
     * @return $this
     */
    public function setUrl($url){
        $this->_url = $url;
        return $this;
    }

    /**
     * 设定路由前缀
     * @param $prefix
     * @return $this
     */
    public function setPrefix($prefix){
        $this->_prefix = $prefix;
        return $this;
    }

    /**
     * 添加路由
     * @param $rule
     * @param $controller
     * @param string $action
     * @return $this
     */
    public function route($rule, $controller, $action=''){
        $p = explode("@", $controller);
        $controller = $p[0];
        if(isset($p[1])) $action = $p[1];
        $this->_route_list[] = array($rule, $controller, $action);
        return $this;
    }

    /**
     * 执行路由
     * @return bool|mixed
     * @throws FN_exception
     */
    public function run(){
        if(empty($this->_route_list)) throw new FN_exception("Route need init before run");
        $url = FNbase::getRequestUri();
        if($this->_prefix){
            $pos = strpos($url, $this->_prefix);
            if($pos === false) throw new FN_tools_routeException("Route prefix is error");
            $url = substr($url, strpos($url, $this->_prefix));
        }elseif($this->_host){
            $url = $this->_host.$url;
        }elseif($this->_url){
            $url = $this->_url;
        }
        $rule = $this->parseRoute($url);
        if(!$rule) throw new FN_tools_routeException("Not found rule");
        list($controller, $action, $param) = $rule;
        if(!$action){
            array_unshift($param, $controller);
            return call_user_func_array(array('FN', 'i'), $param);
        }else{
            $class = FN::i($controller);
            return call_user_func_array(array($class, $action), $param);
        }
    }

    /**
     * 解析路由
     * @param $url
     * @return array|bool
     */
    private function parseRoute($url){
        $class = $action = null;$param = array();
        foreach($this->_route_list as $value){
            $rule = $value[0];
            preg_match_all('/<(\w+)>/', $rule, $param_arr_tmp);
            //正则替换
            $search_arr = array('/','.');
            $replace_arr = array('\/','\.');
            if(!empty($param_arr_tmp)){
                foreach($param_arr_tmp[1] as $k=>$v){
                    //扩展正则替换
                    $regex = '\w+';
                    $search_arr[] = $param_arr_tmp[0][$k];
                    $replace_arr[] = '('.$regex.')';
                }
                $rule = str_replace($search_arr, $replace_arr, $rule);
            }
            preg_match('/^'.$rule.'$/m', $url, $value_arr_tmp, PREG_OFFSET_CAPTURE);
            if(empty($value_arr_tmp)) continue;
            $class = $value[1];
            $action = $value[2];
            if(empty($param_arr_tmp)) break;
            //正则匹对索引1开始
            $len = count($param_arr_tmp[1]);
            for($index=1; $index <= $len; $index++){
                $param[] = isset($value_arr_tmp[$index]) ? $value_arr_tmp[$index][0] : '';
            }
            break;
        }
        if(!$class) return false;
        return array($class, $action, $param);
    }
}

class FN_tools_routeException extends FN_exception{

}
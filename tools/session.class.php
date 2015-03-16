<?php
/**
 * Created by PhpStorm.
 * User: gaojie
 * Date: 15/2/20
 * Time: 下午9:23
 */
class FN_tools_session implements FN__single{
    /** 只支持2种类型的session存储 **/
    const LOCAL = 0;
    const REDIS = 1;//需要php5.4支持

    private $_init = false;
    private $_session_id = null;
    private $_config = array();

    static public function getInstance(){
        return new self();
    }

    /**
     *
     */
    private function __construct(){
        $config = FN::getConfig('session');
        $this->_type = $config['type'];
        $this->_config = $config;

        if($config['ttl']) ini_set("session.gc_maxlifetime", $config['ttl']);
    }

    /**
     * 设置session变量
     * @param $key
     * @param $value
     * @return mixed
     */
    public function set($key, $value){
        return $_SESSION[$key] = $value;
    }

    /**
     * 获取session变量
     * @param $key
     * @return null
     */
    public function get($key){
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }

    /**
     * 清除当前session
     * @return bool
     */
    public function clear(){
        return session_destroy();
    }

    /**
     * 初始化session，并返回session_id
     * @return string
     */
    public function init(){
        if(!$this->_init) {
            switch($this->_type){
                case self::REDIS:
                    $this->_redis();
            }
            session_start();
        }
        return $this->id();
    }

    /**
     * 设置或获取session_id:请确保session_id唯一，也就是系统自己生成的
     * @param null $id
     * @throws FN_exception
     * @return string
     */
    public function id($id=null){
        if($this->_init && $id){
            throw new FN_exception("Session: started before change id");
        }
        if($id){
            $this->_session_id = $id;
            $this->systemId($id);
        }
        //返回的是用户主动设置的session_id，用于区分用户是否设置过session_id
        return $this->_session_id;
    }

    /**
     * 获取系统接口session_id
     * @param null $id
     * @return string
     */
    public function systemId($id=null){
        return session_id($id);
    }

    /**
     * 加载redisSession接口
     * @throws FN_exception
     */
    private function _redis(){
        if(!isset($this->_config['link'])) throw new FN_exception("Session: redis session need link param");
        $handler = new tools_sessionRedis($this->_config['link'], $this->_config['namespace'], (bool)$this->_session_id);
        session_set_save_handler($handler, true);
    }
}

/**
 * 使用Redis设置handler系统session接口
 * Class tools_sessionRedis
 */
class tools_sessionRedis implements SessionHandlerInterface{
    private $_redis;
    private $_namespace;
    private $_ttl = null;

    /**
     * 初始化handler
     * @param $link
     * @param string $namespace
     * @param bool $uuid: 生成唯一uuid，如果是用户设置的session_id，则uuid参数应该为false
     */
    public function __construct($link, $namespace='', $uuid=false) {
        //初始化redis
        $this->_redis = FN::server('database', $link);
        //设置当前的session_id生成的命名空间
        $this->_namespace = $namespace;
        if(!$uuid) return;

        //如果需要uuid，则判断系统session_id是否唯一
        $session_id = session_id();
        while($this->_redis->exists($this->_namespace.'@'.$session_id)){
            $session_id = session_regenerate_id(false);
        }
        session_id($session_id);
    }
    public function open($savePath, $session_id){
        //设置生成周期
        $this->_ttl = ini_get("session.gc_maxlifetime");
        return true;
    }
    public function close(){
        $this->_redis->close();
        return true;
    }
    public function read($id){
        return $this->_redis->get($this->_namespace.'@'.$id);
    }
    public function write($id, $data){
        return $this->_redis->setex($this->_namespace.'@'.$id, $this->_ttl, $data);
    }
    public function destroy($id){
        $this->_redis->delete($this->_namespace.'@'.$id);
        return true;
    }
    public function gc($ttl){
        return true;
    }
}
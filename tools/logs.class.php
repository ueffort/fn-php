<?php
/**
 * Created by PhpStorm.
 * User: gaojie
 * Date: 15/2/20
 * Time: 下午1:38
 */
class FN_tools_logs implements FN__factory{
    //日志等级按2进制划分，自定义级别也需要按2^n计数
    const INFO = 1;
    const ERROR = 2;
    const EXCEPTION = 2;
    const DEBUG = 4;
    const VERBOSE = 8;
    private $_level = 0;
    private $_debug = false;
    private $_handler_list = array();
    static public function getFactory($config){
        $class = new self((int)$config['level']);
        foreach($config['handler_list'] as $name){
            $class->addHandler(FN::server('log', $name));
        }
        $class->_debug = FN::getConfig('debug');
        return $class;
    }

    /**
     * 初始化，并设定日志级别
     * @param $level
     */
    private function __construct($level){
        $this->_level = $level;
    }

    /**
     * 添加处理队列
     * @param $link
     */
    private function addHandler($link){
        $this->_handler_list[] = FN::server('log', $link);
    }

    /**
     * 调用日志处理服务
     * @param $message
     * @param $level
     */
    private function handle($message, $level){
        if($this->_level >=0 && !($this->_level & $level)) return ;
        foreach($this->_handler_list as $handler){
            $handler->handle($message, $level);
        }
    }

    /**
     * 普通日志记录
     * @param $message
     */
    public function info($message){
        $this->handle($message, self::INFO);
    }

    /**
     * 调试日志输出
     * @param $message
     */
    public function debug($message){
        if(!$this->_debug) return;
        $this->handle($message, self::DEBUG);
    }

    /**
     * 异常日志记录
     * @param Exception $e
     */
    public function exception(Exception $e){
        $this->error($e->getMessage());
    }

    /**
     * 错误日志记录
     * @param $message
     */
    public function error($message){
        $this->handle($message, self::ERROR);
    }

    /**
     * 详细日志记录
     * @param $message
     */
    public function verbose($message){
        $this->handle($message, self::VERBOSE);
    }

    /**
     * 自定义日志级别
     * @param $message
     * @param $level
     */
    public function log($message, $level){
        $this->handle($message, (int)$level);
    }
}
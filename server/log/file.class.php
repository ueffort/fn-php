<?php
/**
 * Created by PhpStorm.
 * User: gaojie
 * Date: 15/2/20
 * Time: 下午1:25
 */
class FN_log_file {
    private $_level = 0;

    private $_message_list = array();
    private $_buffers = 50;
    private $_file = null;

    /**
     * 初始化log服务
     * @param $config
     * @return FN_log_file
     */
    public static function initServer($config){
        //不用考虑日志文件的分割等问题，因为php的运行方式，可以在配置文件中调用date函数实现运行时分割
        $class = new self($config['path'],$config['file_name'],$config['level']);
        if(!empty($config['buffers'])) $class->setBuffers($config['buffers']);
        return $class;
    }

    /**
     * 设定缓存message长度
     * @param $length
     */
    public function setBuffers($length){
        $this->_buffers = $length;
    }

    /**
     * 接收日志信息
     * @param $message
     * @param $level
     */
    public function handle($message, $level){
        if($this->_level >=0 && !($this->_level & $level)) return ;
        $this->_message_list[] = $message;
        if(count($this->_message_list) >= $this->_buffers) $this->write();
    }
    private function __construct($path, $file_name, $level){
        $this->_file = $path.'/'.$file_name;
        if(file_exists($this->_file)) return;
        $this->_level = $level;
    }
    private function __destruct(){
        $this->write();
    }

    /**
     * 写入磁盘
     * @throws FN_exception
     */
    private function write(){
        $fp = fopen($this->_file, 'a');
        if(!$fp) throw new FN_exception("Server: Log File ({$this->_file}) can not open!");
        foreach($this->_message_list as $message){
            fwrite($fp, $message."\n");
        }
        $this->_message_list = array();
        fclose($fp);
    }
}
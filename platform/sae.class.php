<?php
//新浪云平台，云平台环境Paas，用以扩展服务接口
class FN_platform_sae extends FN_platform{
    private $accesskey = null;
    private $secretkey = null;
    protected $PlatformSelf = 'sae';

    public function __construct($config){
        if($this->isCloudSelf()){
            $this->accesskey = SAE_ACCESSKEY;
            $this->secretkey = SAE_SECRETKEY;
        }else{
            $this->accesskey = $config['accesskey'];
            $this->sercetkey = $config['sercetkey'];
        }
    }
    /**
     * 获取基础服务
     * @param string $server_name  调用的服务名称
     * @param string $config  映射名称，默认为default
     * @return class
     */
    public function server($server_name,&$config){
        switch($server_name){
            case 'cache':
                //http://sae.sina.com.cn/?m=devcenter&catId=201
                if(!$this->isCloudSelf()) return false;
                return new FN_platform_saeMemcache();
            case 'count':
                //http://sae.sina.com.cn/?m=devcenter&catId=194
                if(!$this->isCloudSelf()) return false;
                return new SaeCounter();
            case 'rank':
                //http://sae.sina.com.cn/?m=devcenter&catId=202
                if(!$this->isCloudSelf()) return false;
                return new SaeRank();
            case 'database':
                if(!$this->isCloudSelf()) return false;
                switch($config['drive']){
                    case 'mysql':
                        //http://developer.baidu.com/wiki/index.php?title=docs/cplat/rt/php/mysql
                        $config['host'] = SAE_MYSQL_HOST_M;
                        $config['port'] = SAE_MYSQL_PORT;
                        $config['user'] = $this->accesskey;
                        $config['pass'] = $this->secretkey;
                        break;
                }
                break;
            case 'template':
                if(!$this->isCloudSelf()) return false;
                switch($config['drive']){
                    case 'smarty':
                        $config['compile_dir'] = '%template/';
                        $config['cache_dir'] = '%cache/';
                        unset($config['platform']);
                        break;
                }
                break;
            case 'image':
                //http://sae.sina.com.cn/?m=devcenter&catId=198
                if(!$this->isCloudSelf()) return false;
                return new SaeImage();
            case 'taskqueue'://异步操作，一对一
                //http://sae.sina.com.cn/?m=devcenter&catId=205
                if(!$this->isCloudSelf()) return false;
                return FN_platform_saeTaskQueueManager::getInstance();
            case 'storage':
                //http://sae.sina.com.cn/?m=devcenter&catId=204
                if(!$this->isCloudSelf()) return false;
                return new FN_layer_storagesae($config);
            case 'mail':
                //http://sae.sina.com.cn/?m=devcenter&catId=200
                if(!$this->isCloudSelf()) return false;
                return new FN_platform_saeMail($config);
            case 'channel'://云推送
                //http://sae.sina.com.cn/?m=devcenter&catId=377
                if(!$this->isCloudSelf()) return false;
                return new SaeChannel();
        }
        parent::server($server_name, $config);
    }
    public function parsePath($dir,$Symbol){
        switch($Symbol){
            case '%':
                return SAE_TMP_PATH.substr($dir,1);//缓存目录
            default:
                return false;
        }
    }
}
class FN_platform_saeMemcache{
    public function __construct(){
        memcache_init();
        return new Memcache();
    }
}

class FN_platform_saeTaskQueueManager implements FN__single{
    public function getInstance(){
        return new self();
    }
}

// extends FN_server_storageabstract
class FN_layer_storagesae{
    private $object = null;
    public function __construct($config){
        $this->object = new SaeStorage();
    }
    public function url($domain,$filename){
        return $this->object->getUrl($domain,$filename);
    }
    public function upload($domain,$filename,$source,$meta){
        return $this->object->upload($domain,$filename,$source,$meta);
    }
    public function write($domain,$filename,$content,$meta){
        return $this->object->write($domain,$filename,$content,$meta);
    }
    public function delete($domain, $filename){
        return $this->object->delete($domain,$filename);
    }
    public function meta($domain, $filename,$meta_key=array()){
        return $this->object->getAttr($domain,$filename,$meta_key);
    }
}
/*
class FN_platform_saeMail{
	private $from = null;
	private $mail = null;
	public function __construct($config){
		$this->init();
		$this->from = $config['from'];
		$this->smtp = $config['smtp'];
	}
	protected function init(){
		$this->mail = new SaeMail();
	}
	public function setFrom($from){
		$this->from = $from;
	}
	public function mail($address,$subject,$message,$ishtml=false){
		if(!is_array($address)){
			$address = array($address);
		}
		foreach($address as $row){
			$status = $this->send($row,$subject,$message);
			if(!$status) return false;
		}
		return true;
	}
	protected function send($address,$subject,$message){
		$status = $this->mail->quickSend($address, $subject , $message ,$this->smtp['user'], $this->smtp['pass'],$this->smtp['host'],$this->smtp['port']);
		//发送失败时输出错误码和错误信息
		if ($status === false){
			return $this->setError($this->mail->errno(),$this->mail->errmsg());
		}
		//$this->mail->clean(); // 重用此对象
		return true;
	}
}*/
class FN_platform_saeMail{
    private $from = null;
    private $from_name = null;
    private $mail = null;
    public function __construct($config){
        $this->init();
        $this->from = $config['from'];
        $this->smtp = $config['smtp'];
    }
    protected function init(){
        $this->mail = apibus::init('sendcloud');
    }
    public function setFrom($from,$from_name=null){
        $this->from = $from;
        if($from_name) $this->from_name = $from_name;
    }
    public function mail($address,$subject,$message,$ishtml=false){
        if(!is_array($address)){
            $address = array($address);
        }
        $status = $this->mail->send_mail(
            $this->smtp['user'], //api_user, 需要登录SendCloud创建收信域名获取发送账号
            $this->smtp['pass'],  // api_key，需要登录SendCloud创建收信域名获取发送账号。
            $this->from, // from， 发件人地址
            $this->from_name,  // fromname， 发件人称呼
            $subject,   // subject，邮件主题
            implode(';',$address), //to， 收件人
            $message//html， html形式的邮件正文
        );
        if($status->errors){
            throw new FN_exception($status->errors, 502);
        }
        return true;
    }
}

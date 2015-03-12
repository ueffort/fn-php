<?php
//Luosimao短信平台
class FN_platform_luosimao extends FN_platform{
    private $accesskey = null;

    public function __construct($config){
        $this->accesskey = $config['accesskey'];
    }
    //统一调用云平台服务
    /**
     * 获取基础服务
     * @param string $server_name  调用的服务名称
     * @param string $config  映射名称，默认为default
     * @return class server 服务实例
     */
    public function server($server_name, &$config){
        switch($server_name){
            case 'sms':
                $config['accesskey'] = $this->accesskey;
                return new FN_platform_luosimao_sms($config);
        }
        parent::server($server_name,$config);
    }
}
class FN_platform_luosimao_sms extends FN_tools_http{
    //http://luosimao.com/docs/api/
    const SEND_URL = "https://sms-api.luosimao.com/v1/send.json";
    const STATUS_URL = "https://sms-api.luosimao.com/v1/status.json";

    //发送单条短信
    public function send($mobile,$message){
        $fields = array(
            'mobile'=>$mobile,
            'message'=>$message.'【'.$this->config['sign'].'】'
        );
        $response = $this->post(self::SEND_URL,$fields,array('username'=>'api','password'=>'key-'.$this->config['accesskey']));
        $code = $response->getBodyJson();
        if(!isset($code['error'])) return 0;//发送失败，稍后发送
        switch($code['error']){
            case 0:return true;//发送成功
            case -10://检查api key是否和各种中心内的一致，调用传入是否正确
                return $this->setError(510,'接口调用错误');
            case -20://短信余额不足
                return $this->setError(520,'平台余额不足');
            case -31://短信内容存在敏感词
                return $this->setError(531,'短信内容存在敏感词');
            case -32://短信内容末尾增加签名信息eg.【公司名称】
                return $this->setError(532,'短信格式错误');
            case -40:
                return $this->setError(540,'手机号错误');
        }
        return false;
    }
    //账户信息(余额)
    public function status(){
        $response = $this->get(self::STATUS_URL,array(),array('username'=>'api','password'=>'key-'.$this->config['accesskey']));
        $code = $response->getBodyJson();
        if(!isset($code['error'])) return 0;//发送失败，稍后发送
        switch($code['error']){
            case 0:return $code['deposit'];//返回余额
            case -10://检查api key是否和各种中心内的一致，调用传入是否正确
                return $this->setError(510,'接口调用错误');//系统错误
        }
        return false;
    }
}

<?php
class FN_platform_weibo extends FN_platform{
    private $appkey = null;
    private $appsecret = null;
    public function __construct($config){
        $this->appkey = $config['appkey'];
        $this->appsecret = $config['appsercet'];
    }
    /**
     * 获取基础服务
     * @param string $server_name  调用的服务名称
     * @param string $config  映射名称，默认为default
     * @return object 服务实例
     */
    public function server($server_name,&$config){
        switch($server_name){
            case 'oauth':
                $config['appkey'] = $this->appkey;
                $config['appsecret'] = $this->appsecret;
                return new FN_platform_weibooauth($config);
        }
        parent::server($server_name, $config);
    }
}
class FN_platform_weibooauth extends FN_server_oauth{
    const VERSION = "2.0";
    const GET_AUTH_CODE_URL = "https://api.weibo.com/oauth2/authorize";
    const GET_ACCESS_TOKEN_URL = "https://api.weibo.com/oauth2/access_token";
    const GET_INFO_URL = "https://api.weibo.com/2/users/show.json";
    protected $oauth = 'weibo';
    public function login($state){
        $keysArr = array(
            "client_id" => $this->config['appkey'],
            "redirect_uri" => $this->redirect_uri,
            "state" => $state,
            "scope" => $this->config['scope']
        );
        header('Location:'.$this->combineURL(self::GET_AUTH_CODE_URL, $keysArr));
    }
    public function callback(){
        $keysArr = array(
            "grant_type" => "authorization_code",
            "client_id" => $this->config['appkey'],
            "redirect_uri" => $this->redirect_uri,
            "client_secret" => $this->config['appsecret'],
            "code" => $_GET['code']
        );

        $response = $this->post(self::GET_ACCESS_TOKEN_URL, $keysArr);

        $info = json_decode($response->getBody(),true);

        if(isset($info['error'])){
            return false;
        }
        $this->access_token = $info["access_token"];
        $this->open_id = $info['uid'];
        $this->expires_time = time()+$info['expires_in'];
        $this->refresh_token = '';//不支持刷新授权
        return true;
    }
    public function getinfo(){
        $keysArr = array(
            "user_id" => $this->open_id,
            "access_token" => $this->access_token
        );

        $response = $this->get(self::GET_INFO_URL, $keysArr);

        $info = json_decode($response->getBody(),true);
        if(isset($info['error'])){
            return false;
        }
        $this->user_nick = $info['screen_name'];
        $this->user_avatar = $info['profile_image_url'];
        return parent::getUserInfo();
    }
    public function verifycode(){
        return isset($_GET['code']);
    }
}
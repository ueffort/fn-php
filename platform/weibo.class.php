<?php
class FN_platform_weibo extends FN_platform{
	private $appkey = null;
	private $securekey = null;
	public function __construct($config){
		$this->appkey = $config['appkey'];
		$this->appsercet = $config['appsercet'];
	}
	//统一调用云平台服务
	/**
	 * 获取基础服务
	 * @param string $servername  调用的服务名称
	 * @param string $config  映射名称，默认为default
	 */
	public function server($servername,&$config){
		switch($servername){
			case 'oauth':
				$config['appkey'] = $this->appkey;
				$config['appsercet'] = $this->appsercet;
				return FN_platform_qqoauth($config);
		}
	}
}
class FN_server_oauth_weibo extends FN_tools_oauth{
	const VERSION = "2.0";
    const GET_AUTH_CODE_URL = "https://graph.qq.com/oauth2.0/authorize";
    const GET_ACCESS_TOKEN_URL = "https://graph.qq.com/oauth2.0/token";
    const GET_OPENID_URL = "https://graph.qq.com/oauth2.0/me";
	const GET_INFO_URL = "https://graph.qq.com/user/get_user_info";
	protected $oauth = 'weibo';
	public function login($state){
		$keysArr = array(
			"response_type" => "code",
			"client_id" => $this->config['appid'],
			"redirect_uri" => urlencode($this->config['uri']),
			"state" => $state,
			"scope" => $this->config['scope']
        );
		header($this->combineURL(self::GET_AUTH_CODE_URL, $keysArr));
	}
	public function callback(){
		$keysArr = array(
			"grant_type" => "authorization_code",
			"client_id" => $this->config['appid'],
			"redirect_uri" => urlencode($this->config['uri']),
			"client_secret" => $this->config['appkey'],
			"code" => $_GET['code']
        );

        $response = $this->get(self::GET_ACCESS_TOKEN_URL, $keysArr);

        if(strpos($response, "callback") !== false){
			$lpos = strpos($response, "(");
			$rpos = strrpos($response, ")");
			$response  = substr($response, $lpos + 1, $rpos - $lpos -1);
			$msg = json_decode($response,true);
			if(isset($msg['error'])) return false;
        }

		$params = array();
		parse_str($response, $params);
		$this->access_token = $params["access_token"];
		$this->open_id = $this->getopenid();
		$this->expires = $params['expires_in'];
		$this->refresh_token = $params['refresh_token'];
		return true;
	}
	public function getopenid(){
		$keysArr = array(
			"access_token" => $this->access_token
		);

		$response = $this->get(self::GET_OPENID_URL, $keysArr);

		if(strpos($response, "callback") !== false){
			$lpos = strpos($response, "(");
			$rpos = strrpos($response, ")");
			$response = substr($response, $lpos + 1, $rpos - $lpos -1);
        }

        $user = json_decode($response,true);
        if(isset($user['error'])) return false;

        return $user['openid'];
	}
	public function getinfo(){
		$keysArr = array(
            "access_token" => $this->access_token,
			"oauth_consumer_key" => $this->config['appid'],
			"openid" => $this->openid,
			"format" => 'json'
        );

        $response = $this->get(self::GET_INFO_URL, $keysArr);
		$info = json_decode($response,true);
		if($info['ret']>0) return false;//输出错误信息
        return $info;
	}
}
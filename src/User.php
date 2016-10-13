<?php
namespace Greedying\Ename;

class User extends EnameBase {
	private $username;
	private $password;
	private $protectAnswer;

	function __construct($username = '', $password = '', $protectAnswer = '', $proxy = '') 
	{
		parent::__construct();

		($username == '' || $password == '' || $protectAnswer == '') && exit( "请填写用户名密码" );
		$this->setCookieFile('/tmp/cookie_ename_'.$username);
		$this->username = $username;
		$this->password = $password;
		$this->protectAnswer = $protectAnswer;
		$proxy && $this->setProxy($proxy);
		$this->login();
	}

	function login()
	{
		$this->loginPublic();
		$this->loginSso();
	}

	/**
	 * 默认登录页面
	 * 同时也会跳转到www.ename.net实现sso
	 * **/
	function loginPublic()
	{
		//第一步，到登录页面，获取key 和sid
		$url = "https://my.ename.cn/cas/sso";
		$return = $this->curlRequest($url);
		if ($this->isLogin($return)) {
			return;
		}

		/**
			第二步,登录。这里登录后，会自动sso 登录www.ename.net
			这里经过测试，省略了web上的一步ajax校验，参数同登录
		**/
		$html = str_get_html($return);
		$key = $html->find('#key', 0)->value;
		$sid = $html->find('#sid', 0)->value;
		$array = [
			'loginName'	=> $this->username,
			'loginPwd'	=> $this->password,
			'captcha'	=> '',
			'key'		=> $key,
			'sid'		=> $sid,
			'domainname'=> 'www.ename.net',
			'backurl'	=> 'http://www.ename.net',
			];

		$preLoginUrl = "https://my.ename.cn/cas/login/preLogin";
		$this->curlRequest($preLoginUrl, $array);
	}

	function isLogin($html) 
	{
		if (strpos($html, "cas.setHeadLogoutHtml") !== false) {
			return true;
		}

		/**
		 * 如果有id 为manage_center的元素，其实就是已经登录了 v
		 */
		if (strpos($html, "manage_center") !== false) {
			return true;
		}

		return false;
	}

	function loginSso()
	{
		$urls = [
			'http://www.ename.net/login', 
			'http://auction.ename.com/login',
			'http://top.ename.com/login',
		];
		foreach($urls as $url) {
			$return = $this->curlRequest($url);
			if ($this->isLogin($return)) {
				continue;
			} else {
				/**
				 * 格式如下
				 * <script type="jsavascript">window.location.href="https://my.ename.cn/cas/login?sid=1&backurl=http%3A%2F%2Fwww.ename.net%2Flogin";</script>
				 */
				$pos = strpos($return, "window.location.href=");
				if ($pos !== false) {
					$pos1 = strpos($return, 'https');
					$pos2 = strpos($return, '";</script>');
					$url = substr($return, $pos1, $pos2 - $pos1);
					$this->curlRequest($url);
				} else {
					$this->log($url . 'unexpect result : ' . $return);
				}
			}
		}
	}

	/**
	 * 获取等待上架域名页面信息
	 * 除了域名信息外，主要就是ci_csrf_token
	 * $transType 交易类型 0 全部 1一口价 4竞价
	 */
	protected function getPreTradeInfo($transType = 0, $num = 200) 
	{
		$url = "http://auction.ename.com/publish/waitsale";
		if ($transType) {
			$url .= "?transType=$transType";
		}
        if ($num) {
            $url .= '&pagesize=' . $num;
        }
		$return = $this->curlRequest($url);
		$html = str_get_html($return);
		if (strpos($return, "找不到相关记录") !== false) {
			return [
				'domainsArray'		=> [],
				];
		} else {
			$domains = [];
			$trs = $html->find('table.com_table tbody tr');
			foreach($trs as $tr) {
				$tds = $tr->find('td');
				if (count($tds) == 0) {
					continue; //表头
				}
				$domain = trim($tds[1]->plaintext);
				$domain && $domains[] = strtolower($domain);
			}
            return [
                'domainsArray'		=> $domains,
                ];
		}
	}

	/***
	 * transType
	 * 1竞价 4 一口价 0 全部
	 * **/
	function getPreTradeList($transType = 0) 
	{
		$preTradeInfo = $this->getPreTradeInfo($transType);
		return isset($preTradeInfo['domainsArray']) ? $preTradeInfo['domainsArray'] : [];
	}

	/**
	 * 批量发布待上架域名
	 **/
	function batchFabuPreTradeList() 
	{
		foreach([1, 4] as $transType) {
			$preTradeInfo = $this->getPreTradeInfo($transType);

			if (empty($preTradeInfo['domainsArray'])) {
				if ($transType == 1 ) {
					$this->log("域名上架：无待上架一口价域名");
				} else if ($transType == 4 ) {
					$this->log("域名上架：无待上架竞价域名");
				}
				continue;
			}
			$preTradeInfo['type'] = $transType;
            $preTradeInfo['domainsText'] = implode("\n", $preTradeInfo['domainsArray']);


			/**
			 * 第一步，编辑页面
			 **/
			$firstUrl = 'http://auction.ename.com/publish/first';
			$return = $this->curlRequest($firstUrl, $preTradeInfo, [CURLOPT_REFERER => "http://auction.ename.com/publish/waitsale"]);

			/***
			 * 第二步，提交
			 */
			$html = str_get_html($return);
            $data = [
                'domains' => $preTradeInfo['domainsArray'],
                'domain'    => [],
                ];
			foreach($preTradeInfo['domainsArray'] as $domain) {
                $domain_str = str_replace('.', '_', $domain);
				$tr = $html->find("#{$domain_str}", 0);
				if ($tr) {
					//之所以对tr做判断，是防止域名不会出现在交易列表中，比如域名即将过期，或者处于询价交易中等等
                    $data['domain'][$domain]['day'] = 1; //有效期一天
                    $data['domain'][$domain]['hour'] = 21; //21点时间段结束
					$data['domain'][$domain]['price'] = $tr->find("input[name='domain[{$domain}][price]']", 0)->value;
					$data['domain'][$domain]['description'] = $tr->find("input[name='domain[{$domain}][description]']", 0)->value;
				}
			}
            if (count($data['domain']) <= 0) {
                continue; ///有待上架域名，但是都不能发布
            }

			$data['transpoundage'] = 2; //不可提现预付款
			$data['makesure'] = 'on';
			$data['opProtectId'] = $html->find("#opProtectId", 0)->value;
			$data['opAnswer'] = $this->protectAnswer;
			$data['opPassword'] = $this->password;
			$data['type'] = $transType;
            
			$secondUrl = "http://auction.ename.com/publish/second";
			$return = $this->curlRequest($secondUrl, $data);

			/**获取成功上架域名，应该抽象出来 **/
			$return = json_decode($return, true);
			$domain_str = '';
			foreach($return['msg']['succ'] as $k => $v) {
                if ($v == 0) {
                    $domain_str .= "\n" . $k;
                }
			}
			if ($transType == 1 ) {
				$this->log("域名上架，成功上架一口价域名列表: $domain_str");
			} else if ($transType == 4 ) {
				$this->log("域名上架，成功上架竞价域名列表：$domain_str");
			}
		}
		return true;
	}

	function domainlist()
	{
		$url = "http://www.ename.net/manage/advanceSearchAjax?page=0&limit=1000";
		$return = $this->curlRequest($url);
		$data = json_decode($return, true);
		$dataList = $data['dataList'];
		$domains = [];
		foreach($dataList as $domain) {
			$domains[] = $domain['DomainName']; 
		}
		return $domains;
	}
}

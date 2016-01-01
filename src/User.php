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
			'http://page.ename.com/login',
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
	 * 除了域名信息外，主要就是ename_csrf
	 * $transType 交易类型 0 全部 1竞价 4一口价
	 * 注意： 在/fabu/getPreTradeList上4是 一口价，但是发布的时候,却是3代表一口价 。。。。。
	 * **/
	protected function getPreTradeInfo($transType = 0) 
	{
		$url = "http://auction.ename.com/fabu/getPreTradeList";
		if ($transType) {
			$url .= "?transType=$transType";
		}
		$return = $this->curlRequest($url);
		$html = str_get_html($return);
		$ename_csrf = $html->find('input[name=ename_csrf]', 0)->value;

		if (strpos($return, "找不到相关记录") !== false) {
			return [
				'domainsArray'		=> [],
				'ename_csrf'		=> $ename_csrf,
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
				'ename_csrf'		=> $ename_csrf,
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
		foreach([1,4] as $transType) {
			$preTradeInfo	= $this->getPreTradeInfo($transType);

			if (empty($preTradeInfo['domainsArray'])) {
				if ($transType == 1 ) {
					$this->log("域名上架：无待上架竞价域名");
				} else if ($transType == 4 ) {
					$this->log("域名上架：无待上架一口价域名");
				}
				continue;
			}

			/** 查询的时候，1竞价 4一口价，但是发布的时候，1竞价，一口价变成了3  **/
			$fabuType = $transType;
			if ($transType == 4) {
				$fabuType = 3;
			}


			$preTradeInfo['type']	= $fabuType;//1代表竞价，暂时只考虑这种情况

			/**
			 * 第一步，编辑页面
			 **/
			$firstUrl = 'http://auction.ename.com/fabu/first';
			$return = $this->curlRequest($firstUrl, $preTradeInfo, [CURLOPT_REFERER => "http://auction.ename.com/fabu/getPreTradeLis"]);

			/***
			 * 第二步，提交
			 */
			$html = str_get_html($return);
			$data = ['domains' => $preTradeInfo['domainsArray']];
			foreach($preTradeInfo['domainsArray'] as $domain) {
				$prefix = str_replace('.', '_', $domain);
				$tr = $html->find("#$prefix", 0);
				if ($tr) {
					//之所以对tr做判断，是防止域名不会出现在交易列表中，比如域名即将过期，或者处于询价交易中等等
					$data[$prefix.'_md5'] = $tr->find("input[name={$prefix}_md5]", 0)->value;
					$data[$prefix.'_transdate'] = '1';//有效期一天
					$data[$prefix.'_transtime'] = '21';//晚上九点到十点之间
					$data[$prefix.'_transmoney'] = $tr->find("input[name={$prefix}_transmoney]", 0)->value;
					$data[$prefix.'_simpledec'] = $tr->find("input[name={$prefix}_simpledec]", 0)->value;
				}
			}

			$data['domain_ename'] = 1; //应该是在ename注册的域名吧
			$data['transpoundage'] = 2; //不可提现预付款
			$data['makesure'] = 'on';
			$data['opProtectId'] = $html->find("#opProtectId", 0)->value;
			$data['opAnswer'] = $this->protectAnswer;
			$data['opPassword'] = $this->password;

			$data['type'] = $fabuType;
			$data['ename_csrf'] = $preTradeInfo['ename_csrf'];

			$secondUrl = "http://auction.ename.com/fabu/second";
			$return = $this->curlRequest($secondUrl, $data);

			/**获取成功上架域名，应该抽象出来 **/
			$return = json_decode($return, true);
			$domain_str = '';
			foreach($return['fabuSuccessDomains'] as $v) {
				$domain_str .= "\n" . $v[0];
			}

			if ($transType == 1 ) {
				$this->log("域名上架，成功上架竞价域名列表: $domain_str");
			} else if ($transType == 4 ) {
				$this->log("域名上架，成功上架一口价域名列表：$domain_str");
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

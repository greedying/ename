<?php
namespace Greedying\Ename;

class AuctionList extends EnameBase {
	/**
	 * 获取拍卖列表的url
	 */
	protected $url = "http://auction.ename.com/auction/domainlist";

	private $numPerPage = 30;// 固定30条

	/**
	 * 类别
	 * 空白 所有类别
	 * 1 纯数字
	 * 2 单数字
	 * 3 双数字
	 * 4 三数字
	 * 5 四数字
	 * 6 五数字
	 * 20 六数字
	 * 7 纯字母
	 * 8 单字母
	 * 9 双字母
	 * 10 三字母
	 * 11 四字母
	 * 12 五字母
	 * 16 单拼
	 * 17 双拼
	 * 18 三拼
	 * 13 杂米
	 * 14 两杂
	 * 15 三杂
	 * 19 中文
	 **/
	protected $domaingroup = ''; //类别

	/**
	 * 交易类型
	 * 0 所有类型
	 * 1 竞价
	 * 4 一口价
	 * 5 询价
	 ***/
	protected $transtype   = 0;  //交易类型

	/**
	 * 后缀，但不知道是什么单词的缩写，可多选
	 * 空白 所有后缀
	 * 1 com
	 * 2 cn
	 * 3 com.cn
	 * 4 net 
	 * 5 org
	 * 6 cc
	 * 7 biz
	 * 8 info
	 * 9 net.cn
	 * 10 org.cn
	 * 11 .asia
	 * 12 tv
	 * 13 tw
	 * 14 in
	 * 15 cd 
	 * 17 me 
	 * 18 pw
	 * 19 中国
	 * 21 公司
	 * 22 网络
	 * 23 wang 
	 * 24 top
	 ***/
	protected $domaintlds   = []; 

	private $ciCsrf = '';
	private $total = 0;
	private $paramsChanged = true;

	public function __construct() 
	{
		parent::__construct();
		$this->setCookieFile('/tmp/cookie_ename_auction_list');
	}

	public function getDomaingroup() 
	{
		return $this->domaingroup;
	}

	public function setDomaingroup($domaingroup)
	{
		$this->domaingroup = $domaingroup;
		$this->paramsChanged = true;
		return $this;
	}

	public function getTransType()
	{
		return $this->transtype;
	}

	public function setTransType($transtype)
	{
		!in_array($transtype, [0,1,4,5]) && $transtype = 0;
		$this->transtype = $transtype;
		$this->paramsChanged = true;
		return $this;
	}

	public function getDomaintlds() 
	{
		return $this->domaintlds;
	}

	public function setDomaintlds($domaintlds)
	{
		$this->domaintlds = $domaintlds;
		$this->paramsChanged = true;
		return $this;
	}

	public function getCompleteUrl()
	{
		if ($this->getParams()) {
			return $this->url . '?' . http_build_query($this->getParams());
		} else {
			return $this->url;
		}
	}

	public function getParams() 
	{
		$params = [];
		if ($this->domaingroup) {
			$params['domaingroup'] = $this->domaingroup;
		}
		if ($this->transtype) {
			$params['transtype'] = $this->transtype;
		}
		if ($this->domaintlds) {
			sort($this->domaintlds);
			$params['domaintld'] = $this->domaintlds;
		}
		ksort($params);
		return $params;
	}

	public function setParamsChanged() 
	{
		$str = $this->curlRequest($this->getCompleteUrl());
		$html = str_get_html($str);
		$number_html = $html->find('.page_hd .c_orange', 0);
		if (empty($number_html)) {
			throw new \Exception("不存在总页面元素，出错咯");
		}
		$number = $html->find('.page_hd .c_orange', 0)->plaintext;
		$number = trim($number, ')( ');
		$this->total = $number;
		$this->ciCsrf = $html->find('input[name=ci_csrf_token]', 0)->value;
		$this->paramsChanged = false;
		return $this;
	}

	public function checkParamsChanged()
	{
		if ($this->paramsChanged) {
			$this->setParamsChanged();
		}
		return $this;
	}

	/***
	 * 不支持 transtype为 0全部的情形
	 * 最多多少页
	 */
	public function getTotalPage()  
	{
		$this->checkParamsChanged();
		return ceil($this->total / $this->numPerPage);
	}

	public function getTotal()  
	{
		$this->checkParamsChanged();
		return $this->total;
	}

	public function getData($page = 1) 
	{
		$this->checkParamsChanged();
		$params = $this->getParams();
		$params['ci_csrf_token'] = $this->ciCsrf;
		$params['ajax']		  = 1;
		$params['page']		  = $page;
		$params['per_page']	  = $page * $this->numPerPage;
		$result = $this->curlRequest($this->url, $params);
		return json_decode($result, true);
	}

	public function getAuctions($page = 1) 
	{
		$data = $this->getData($page);
		if ($data && isset($data['data']) && $data['data']) {
			return $data['data'];
		} 
		return [];
	}
}

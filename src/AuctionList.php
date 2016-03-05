<?php
namespace Greedying\Ename;

class AuctionList extends EnameBase {
	/**
	 * 获取拍卖列表的url
	 */
	protected $basicUrl = "http://auction.ename.com/tao";

	private $numPerPage = 30;// 固定30条
    private $page = 1;

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

    public function getPage()
    {
        return $this->page;
    }

    public function setPage($page) 
    {
        $this->page = $page;
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

    public function getUrl() 
    {
        if ($this->transtype == self::TYPE_BIDDING) {
            return $this->basicUrl . '/bidding';
        } else if ($this->transtype == self::TYPE_BUYNOW) {
            return $this->basicUrl . '/buynow';
        } else {
            return $this->basicUrl;
        }
    }

	public function getCompleteUrl($is_ajax = 0)
	{
		if ($this->getParams()) {
			return $this->getUrl() . '?' . http_build_query($this->getParams($is_ajax));
		} else {
			return $this->getUrl();
		}
	}

	public function getParams($is_ajax = 0) 
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

        if ($is_ajax) {
            $params['ajax']		  = 1;
            $params['page']		  = $this->page;
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
        $this->setPage($page);
		$this->checkParamsChanged();
		$result = $this->curlRequest($this->getCompleteUrl(true));
		return json_decode($result, true);
	}

    /***
     返回规范化的数据，防止易名中国更改格式
    */
	public function getAuctions($page = 1) 
	{
		$data = $this->getData($page);
        $result = [];
		if ($data && isset($data['data']) && $data['data']) {
            $data = $data['data'];
            foreach($data as $one) {
                $result[] = [
                    'transtype'     => $this->getTransType(),
                    'trade_number'  => $this->getIdByUrl($one['t_url']),
                    'description'   => $one['t_desc'],
                    'domain_name'   => $one['t_dn'],
                    'sell_id'       => $one['t_enameId'],
                    'price'         => $one['t_now_price'],
                    'bid_count'     => isset($one['t_count']) ? $one['t_count'] : 0,
    //                'end_time'      => $one[''],
                    ];
            }

            return $result;
        }  else {
            return [];
        }
	}

    /***
     * 一口价的ajax里有id，但是和url里的id不一致
     * 竞价的id从url获取
     * 现在统一获取url里的
     */
    public function getIdByUrl($url) {
        $info = parse_url($url, PHP_URL_PATH);
        $info = explode('/', $info);
        return $info[3];
    }
}

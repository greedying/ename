<?php
namespace Greedying\Ename;

class AuctionDetail extends EnameBase {
	/**
	 * 获取拍卖列表的url
	 */
	protected $url = "http://www.ename.com/auction/domain/";

	private $audit_list_id;

	/**
	 * html源路径,用于指定html源文件的情况
	 **/
	private $source_file;

	private $html_tr; //html字符串
	private $html;  // simple html dom 对象

	/**
	 * $audit_id_or_file
	 * 可以传递一个AuditListId，这时候会去网络抓取html
	 * 也可以传递一个文件的路径，这时候会去读取文件内容作为html
	 * */
	function __construct($audit_id_or_file, $proxy = '') 
	{
		parent::__construct();
		$audit_id_or_file || exit('缺少拍卖id或者文件路径');
		if (preg_match("/^[1-9]\d*$/", $audit_id_or_file)) {
			$this->audit_list_id = $audit_id_or_file;
		} else {
			$this->source_file = $audit_id_or_file;
		}

		if ($proxy) {
			$this->setProxy($proxy);
		}
		$this->setHtml();
	}

	function getHtml() 
	{
		if (empty($this->html)) {
			$this->setHtml();
		}
		return $this->html;
	}

	function setHtml()
	{
		$str = '';
		if ($this->audit_list_id) {
			$url = $this->url . $this->audit_list_id;
			$str = $this->curlRequest($url);
		} elseif ($this->source_file) {
			$str = file_get_contents($this->source_file);
		}
		$this->html_str = $str;
		$this->html = str_get_html($this->html_str);
	}

	/***
	 * -2 网络错误
	 * -1 无此交易或者结束太久
	 * 0  交易中
	 * 1  已结束
	 **/
	function getStatus()
	{
		if (strpos($this->html_str, "Connection timed out") !== false) {
			return -2;
		}

		if (strpos($this->html_str, "无此交易或已经结束") !== false) {
			return -1;
		}

		// 适用于竞价
		if ($this->html->find("#leftTime", 0) &&
			$this->html->find("#leftTime", 0)->innertext == "交易已结束") 
		{
			return 1;
		} else if ($this->getTransType() == 4) {
			//一口价暂时根据结束时间判断
			$end_time = $this->getEndTime();
			if ($end_time && strtotime($end_time) < time()) {
				return 1;
			}
		}

		return 0;
	}


	/***
	 * 获取交易开始时间
	 ***/
	function getStartTime()
	{
		$time_array = $this->getStartAndEndTime();
		if ($time_array) {
			return $time_array[0];
		}
		return false;
	}

	/**
	 * 获取交易结束时间
	 * **/
	function getEndTime()
	{
		$time_array = $this->getStartAndEndTime();
		if ($time_array) {
			return $time_array[1];
		}
		return false;
	}

	/**
	 * 要求的价格
	 * 也就是一口价的要价或者竞价的起拍价
	 **/
	function getAskingPrice()
	{
		$html = $this->html->find('#askingPrice', 0);
		if ($html) {
			return trim($html->plaintext);
		} else {
			return false;
		}
	}

	function getPrice()
	{
		$html = $this->html->find('#bidPrice', 0);
		if ($html) {
			return trim($html->plaintext);
		} else {
			//一口价的等同于要价
			return $this->getAskingPrice(); 
		}
	}

	function getBidCount()
	{
		$html = $this->html->find('#bidCount', 0);
		if ($html) {
			return trim($html->plaintext);
		}
		return false;// 一口价的返回0
	}


	private function getStartAndEndTime() 
	{
		$html = $this->html->find('#transCycle', 0);
		if ($html) {
			$time_str = trim($html->plaintext);
			return explode(' - ', $time_str);
		}  else {
			return false;
		}
		return false;
	}

	/**
	 * 1 竞价价
	 * 4 一口价
	 ***/
	public function getTransType()
	{
		$asking_html = $this->html->find('#askingPrice', 0);
		if ($asking_html) {
			$str = trim($asking_html->parent->prev_sibling()->plaintext);
			if ($str == "一口价") {
				return 4;
			} elseif ($str == "起拍价") {
				return 1;
			}
		}

		return false;
	}

	function __destruct()
	{
		$this->html->clear();
		parent::__destruct();
	}
}

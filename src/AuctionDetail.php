<?php
namespace Greedying\Ename;

class AuctionDetail extends EnameBase {
	/**
	 * 获取拍卖列表的url
	 */
    protected $buynow_url = "http://auction.ename.com/domain/buynow/";
    protected $bidding_url = "http://auction.ename.com/domain/auction/";

	private $id = 0;

    private $transtype = false;

	/**
	 * html源路径,用于指定html源文件的情况
	 **/
	private $file;

	private $html_tr; //html字符串
	private $html;  // simple html dom 对象

	/**
	 * $audit_id_or_file
	 * 可以传递一个AuditListId，这时候会去网络抓取html
	 * 也可以传递一个文件的路径，这时候会去读取文件内容作为html
	 * */
	function __construct($info = []) 
    {
        parent::__construct();
        if (isset($info['id'])) {
            $this->id = $info['id'];
            if (isset($info['transtype']) &&$info['transtype']) {
                $this->transtype = $info['transtype'];
            } else {
                exit('缺少交易类型');
            }
            if (isset($info['proxy']) && $info['proxy']) {
                $this->setProxy($info['proxy']);
            }
        } else if (isset($info['file']) && $info['file']) {
            $this->file = $info['file'];
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
		if ($this->id) {
			$str = $this->curlRequest($this->getUrl());
		} elseif ($this->file) {
			$str = file_get_contents($this->file);
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
			$this->html->find("#leftTime", 0)->innertext == "交易结束") 
		{
			return 1;
		} else if ($this->getTransType() == self::TYPE_BUYNOW) {
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
			return trim($time_array[0]);
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
			return trim($time_array[1]);
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
            if ($this->getTransType() == self::TYPE_BIDDING) {
            //格式2015-12-29 16:26:43-2016-03-28 21:29:52
                return explode(' - ', $time_str);
            } else if($this->getTransType() == self::TYPE_BUYNOW) {
            //格式 2016-03-04 19:01:15 - 2016-03-05 15:48:00
                return [
                    substr($time_str, 0, 19),
                        substr($time_str, 20, 19),
                    ];
            }
		} else {
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
        if ($this->transtype == false)  {
            $asking_html = $this->html->find('#askingPrice', 0);
            if ($asking_html) {
                $str = trim($asking_html->parent->prev_sibling()->plaintext);
                if ($str == "金额") {
                    $this->transtype = self::TYPE_BUYNOW;
                } elseif ($str == "起拍价") {
                    $this->transtype = self::TYPE_BIDDING;
                }
            }
        }
        return $this->transtype;
	}

    public function getId() 
    {
        if ($this->id == 0) {
            $html = $this->html->find('input[name=id]', 0);
            if ($html) {
                $this->id = $html->value;
            }
        }
        return $this->id;
    }

    public function getDesc()
    {
        $desc_html = $this->html->find('#domainTrans table tbody tr', 0)->find('td' ,1);
		if ($desc_html) {
            // 返回innertext中span元素出现之前的部分，并且去除前后空格
            return trim(strstr($desc_html->innertext, "<span", true));
        }

		return false;
    }

    public function getDomainName()
    {
        //顶部当前位置获取
        $domain_html = $this->html->find('div.crumb', 0)->find('span' ,1);
		if ($domain_html) {
            // 返回innertext中span元素出现之前的部分，并且去除前后空格
            return trim($domain_html->plaintext);
        }

		return false;
    }

    public function getSellerId()
    {
        preg_match('/var shopUsreId=(\d{6,})/', $this->html_str, $matches);
        if ($matches) {
            return $matches[1];
        }
        return 0;// 没有开启店铺的情况，当前页无法获得id
    }

    function getUrl()
    {
        if ($this->transtype == self::TYPE_BIDDING) {
            return $this->bidding_url . $this->id;
        } else if ($this->transtype == self::TYPE_BUYNOW) {
            return $this->buynow_url . $this->id;
        }
    }

	function __destruct()
	{
        if ($this->html) {
            $this->html->clear();
        }
		parent::__destruct();
	}
}

<?php
use Greedying\Ename\AuctionDetail;

require __DIR__.'/../vendor/autoload.php';

class AuctionDetailTest extends PHPUnit_Framework_TestCase 
{
	protected $auction_not_exist; //不存在或者结束太久的交易
	protected $auction_ing;		  //正在进行的交易
	protected $auction_done;	  //结束不久交易
	protected $oneprice_ing;	  //正在进行的一口价

	function setUp()
	{
		$dir = __DIR__ . "/../insource/";

        $info = [
            'file'      => $dir . 'auction_not_exist.html',
            'transtype' => false,
            ];
		$this->auction_not_exist = new AuctionDetail($info);
        $info = [
            'file'      => $dir . 'auction_ing.html',
            'transtype' => AuctionDetail::TYPE_BIDDING,
            ];
		$this->auction_ing = new AuctionDetail($info);
        $info = [
            'file'      => $dir . 'auction_done.html',
            'transtype' => AuctionDetail::TYPE_BIDDING,
            ];
		$this->auction_done = new AuctionDetail($info);
        $info = [
            'file'      => $dir . 'auction_oneprice_ing.html',
            'transtype' => AuctionDetail::TYPE_BUYNOW,
            ];
		$this->oneprice_ing = new AuctionDetail($info);
        $info = [
            'file'      => $dir . 'auction_oneprice_done.html',
            'transtype' => AuctionDetail::TYPE_BUYNOW,
            ];
		$this->oneprice_done = new AuctionDetail($info);
	}

    public function testGetTransType()
    {
		$this->assertFalse($this->auction_not_exist->getTransType());
		$this->assertEquals(4, $this->auction_ing->getTransType());
		$this->assertEquals(4, $this->auction_done->getTransType());
		$this->assertEquals(1, $this->oneprice_ing->getTransType());
		$this->assertEquals(1, $this->oneprice_done->getTransType());
	}


    public function testGetStatus()
    {
		$this->assertEquals(-1, $this->auction_not_exist->getStatus());
		$this->assertEquals(0, $this->auction_ing->getStatus());
		$this->assertEquals(1, $this->auction_done->getStatus());
        $this->assertEquals(0, $this->oneprice_ing->getStatus());
        $this->assertEquals(1, $this->oneprice_done->getStatus());
	}

	public function testGetStartTime()
	{
		$this->assertFalse($this->auction_not_exist->getStartTime());
		$this->assertEquals('2016-03-04 00:30:03', $this->auction_ing->getStartTime());
		$this->assertEquals('2016-03-04 19:01:15', $this->auction_done->getStartTime());
		$this->assertEquals('2015-12-29 16:26:43', $this->oneprice_ing->getStartTime());
	}

	public function testGetEndTime()
	{
		$this->assertFalse($this->auction_not_exist->getEndTime());
		$this->assertEquals('2016-03-05 17:40:00', $this->auction_ing->getEndTime());
		$this->assertEquals('2016-03-05 15:48:00', $this->auction_done->getEndTime());
		$this->assertEquals('2016-03-28 21:29:52', $this->oneprice_ing->getEndTime());
	}

	public function testGetBidCount()
	{
		$this->assertEquals(9, $this->auction_ing->getBidCount());
		$this->assertEquals(0, $this->auction_done->getBidCount());
		$this->assertEquals(0, $this->oneprice_ing->getBidCount());
	}

	public function testGetAskingPrice()
	{
		$this->assertEquals(1, $this->auction_ing->getAskingPrice());
		$this->assertEquals(50000, $this->auction_done->getAskingPrice());
		$this->assertEquals(200000, $this->oneprice_ing->getAskingPrice());
	}

	public function testGetPrice()
	{
		$this->assertEquals(13, $this->auction_ing->getPrice());
		$this->assertEquals(50000, $this->auction_done->getPrice());
		$this->assertEquals(200000, $this->oneprice_ing->getPrice());
	}

    public function testGetId()
    {
        $this->assertEquals('aggfxk9ddhbamm4h', $this->auction_ing->getId());
        $this->assertEquals('a0hf8ocm5j176946', $this->auction_done->getId());
        $this->assertEquals('9cgo28s1dad6t28n', $this->oneprice_ing->getId());
    }

    public function testGetDesc()
    {
		$this->assertEquals('易快运，壹块云，物流快递商号', $this->auction_ing->getDesc());
		$this->assertEquals('贷去啊 参考去啊旅游网qua.com 贷款服务中心', $this->auction_done->getDesc());
        $this->assertEquals('悦动圈，著名运动app', $this->oneprice_ing->getDesc());
    }

    public function testGetDomainName()
    {
		$this->assertEquals('yikuaiyun.cn', $this->auction_ing->getDomainName());
		$this->assertEquals('daiqua.com', $this->auction_done->getDomainName());
        $this->assertEquals('yuedongquan.com', $this->oneprice_ing->getDomainName());
    }

    public function testGetSellerId()
    {
		$this->assertEquals('977506', $this->auction_ing->getSellerId());
		$this->assertEquals('585168', $this->auction_done->getSellerId());
        $this->assertEquals('941236', $this->oneprice_ing->getSellerId());
    }
}

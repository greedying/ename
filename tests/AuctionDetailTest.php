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

		$this->auction_not_exist = new AuctionDetail($dir . 'auction_not_exist.html');
		$this->auction_ing = new AuctionDetail($dir . 'auction_ing.html');
		$this->auction_done = new AuctionDetail($dir . 'auction_done.html');
		$this->oneprice_ing = new AuctionDetail($dir . 'auction_oneprice_ing.html');
		$this->oneprice_done = new AuctionDetail($dir . 'auction_oneprice_done.html');
	}

    public function testGetTransType()
    {
		$this->assertFalse($this->auction_not_exist->getTransType());
		$this->assertEquals(1, $this->auction_ing->getTransType());
		$this->assertEquals(1, $this->auction_done->getTransType());
		$this->assertEquals(4, $this->oneprice_ing->getTransType());
		$this->assertEquals(4, $this->oneprice_done->getTransType());
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
		$this->assertEquals('2015-10-05 23:08', $this->auction_ing->getStartTime());
		$this->assertEquals('2015-10-05 08:58', $this->auction_done->getStartTime());
		$this->assertEquals('2015-10-06 00:13', $this->oneprice_ing->getStartTime());
	}

	public function testGetEndTime()
	{
		$this->assertFalse($this->auction_not_exist->getEndTime());
		$this->assertEquals('2015-10-06 17:10', $this->auction_ing->getEndTime());
		$this->assertEquals('2015-10-06 12:21', $this->auction_done->getEndTime());
		$this->assertEquals('2100-01-04 22:59', $this->oneprice_ing->getEndTime());
	}

	public function testGetBidCount()
	{
		$this->assertEquals(18, $this->auction_ing->getBidCount());
		$this->assertEquals(17, $this->auction_done->getBidCount());
		$this->assertEquals(0, $this->oneprice_ing->getBidCount());
	}

	public function testGetAskingPrice()
	{
		$this->assertEquals(1, $this->auction_ing->getAskingPrice());
		$this->assertEquals(1, $this->auction_done->getAskingPrice());
		$this->assertEquals(65, $this->oneprice_ing->getAskingPrice());
	}

	public function testGetPrice()
	{
		$this->assertEquals(50, $this->auction_ing->getPrice());
		$this->assertEquals(36, $this->auction_done->getPrice());
		$this->assertEquals(65, $this->oneprice_ing->getPrice());
	}
}

<?php
use Greedying\Ename\AuctionList;

require __DIR__.'/../vendor/autoload.php';

class AuctionListTest extends PHPUnit_Framework_TestCase 
{
    public function testGetCompleteUrl()
    {
		$auction = new AuctionList();

		$url = 'http://auction.ename.com/auction/domainlist';

		/**测试用例先空一下
		 * **/
		$this->assertEquals($url, $auction->getCompleteUrl());

		$auction->setTranstype(1);
		$url = 'http://auction.ename.com/auction/domainlist?transtype=1';
		$this->assertEquals($url, $auction->getCompleteUrl());


		$auction->setDomaingroup(2);
		$url = 'http://auction.ename.com/auction/domainlist?domaingroup=2&transtype=1';
		$this->assertEquals($url, $auction->getCompleteUrl());

		$auction->setDomaintlds([2]);
		$url = 'http://auction.ename.com/auction/domainlist?domaingroup=2&domaintld%5B0%5D=2&transtype=1';
		$this->assertEquals($url, $auction->getCompleteUrl());

		$auction->setDomaintlds([1, 2]);
		$url = 'http://auction.ename.com/auction/domainlist?domaingroup=2&domaintld%5B0%5D=1&domaintld%5B1%5D=2&transtype=1';
		$this->assertEquals($url, $auction->getCompleteUrl());
	}

	public function testGetTotalAndTotalPage() 
	{
		$auction = new AuctionList();

		$page1 = $auction->getTotalPage();
		$this->assertEquals($page1, intval($page1));
		$total1 = $auction->getTotal();
		$this->assertEquals($total1, intval($total1));
		$this->assertEquals($page1, ceil($total1/30));

		$auction->setDomaintlds([2]);
		$page2 = $auction->getTotalPage();
		$this->assertEquals($page2, intval($page2));
		$total2 = $auction->getTotal();
		$this->assertEquals($total2, intval($total2));
		$this->assertEquals($page2, ceil($total2/30));

		$this->assertLessThanOrEqual(intval($page1), intval($page2));
	}

	public function testGetData()
	{
		$auction = new AuctionList();
		$auction->setTranstype(1);
		$data = $auction->getData(1);
		$this->assertArrayHasKey('data', $data);
		$this->assertArrayHasKey('levelConf', $data);
		$this->assertArrayHasKey('more', $data);
		$this->assertArrayHasKey('topDomainList', $data);
		$this->assertArrayHasKey('topDomainName', $data);
		$this->assertArrayHasKey('transtype', $data);
		$this->assertArrayHasKey('type', $data);
	}

	public function testGetAuctions() 
	{
		$auctionObj = new AuctionList();
		$auctionObj->setTranstype(1);
		$data = $auctionObj->getAuctions(1);
		$this->assertTrue(is_array($data));

		$auction = $data[0];
		$this->assertArrayHasKey('AuditListId', $auction);
		$this->assertArrayHasKey('IsDomainInEname', $auction);
		$this->assertArrayHasKey('AskingPrice', $auction);
		$this->assertArrayHasKey('FinishDate', $auction);
		$this->assertArrayHasKey('SimpleDec', $auction);
		$this->assertArrayHasKey('BidPrice', $auction);
		$this->assertArrayHasKey('TransType', $auction);

		/**
		 * 如果数据为空，那么返回空数组
		 * 1000000位一个不存在的页码
		 **/
		$data = $auctionObj->getAuctions(10000000);
		$this->assertEquals([], $data);
	}


}

<?php
use Greedying\Ename\User;

require __DIR__.'/../vendor/autoload.php';

class UserTest extends PHPUnit_Framework_TestCase 
{
    public function testBatchFabuPreTradeList()
    {
		extract(require __DIR__.'/config.php');
		$user = new User($username, $password, $protectAnswer);
		
		/**测试用例先空一下
		 * **/
        $this->assertTrue($user->batchFabuPreTradeList());
	}

	public function testDomainlist() 
	{
		extract(require __DIR__.'/config.php');
		$user = new User($username, $password, $protectAnswer);
		$this->assertTrue(is_array($user->domainlist()));
	}
}

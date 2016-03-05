<?php
namespace Greedying\Ename;

class EnameBase {
    const TYPE_BIDDING = 4;
    const TYPE_BUYNOW = 1;

	/**
	 * cookie_file
	 * 如果需要存储cookie，则设置此项
	 */
	private $cookie_file;

	/**
	 * 如果需要设置代理，则设置此属性
	 **/
	private $proxy;

	/**
	 * ch句柄
	 **/
	private $ch = '';


	public function __construct()
	{
		$this->ch = curl_init();
	}


	public function setCookieFile($cookie_file)
	{
		$this->cookie_file = $cookie_file;
		return $this;
	}

	public function getCookieFile()
	{
		return $this->cookie_file;
	}

	public function setProxy($proxy) 
	{
		$this->proxy = $proxy;
		return $this;
	}

	public function getProxy()
	{
		return $this->proxy;
	}

	/**
	 * CURL请求
	 * @param String $url 请求地址
	 * @param Array $data 请求数据
	 */
	function curlRequest($url, $data = false, $selfOption = [])
	{
		$option = [
			CURLOPT_URL => $url,
			CURLOPT_HEADER => 0,
			//CURLOPT_VERBOSE => 1,
			CURLOPT_HTTPHEADER => array('Accept-Language: zh-cn','Connection: Keep-Alive','Cache-Control: no-cache'),
			CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.93 Safari/537.36",
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_MAXREDIRS => 4,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_CONNECTTIMEOUT => 30,
			CURLOPT_TIMEOUT => 30,
			];

		if ($this->cookie_file) {
			$option[CURLOPT_COOKIEJAR] = $this->cookie_file;
			$option[CURLOPT_COOKIEFILE] = $this->cookie_file;
		}

		if ($this->proxy) {
			$option[CURLOPT_PROXY] = $this->proxy;
		}

		if ($data) {
			$option[CURLOPT_POST] = 1;
			// buildquery 作用是使用 application/x-www-form-urlencoded,而不是multipart/form-data
			$option[CURLOPT_POSTFIELDS] = http_build_query($data);
		}

		if ($selfOption) {
			foreach($selfOption as $k => $v) {
				$option[$k] = $v;
			}
		}

		curl_setopt_array($this->ch, $option);
		$response = curl_exec($this->ch);

		if (curl_errno($this->ch) > 0) {
			throw new \Exception("CURL ERROR:$url, " . curl_error($this->ch));
		}

		if (curl_getinfo($this->ch, CURLINFO_HTTP_CODE) != '200') {
			throw new \Exception("CURL ERROR:$url, http code : " . curl_getinfo($this->ch, CURLINFO_HTTP_CODE));
		}

		/***
		 * ename 的 404 页面
		 <html><head><title>404 File Not Found</title></head>
		 <body>The requested URL was not found on this server</body></html>
		 **/
		if (strpos($response, "404 File Not Found") !== false) {
			throw new \Exception("response error: 404 page");
		}
		if (strpos($response, "Connection timed out") !== false) {
			throw new \Exception("response error: Connection timed out");
		}

		/**Redis server went away 
		 * 处理ename的各种错误方式啊
		 * **/
		if (strpos($response, "Redis server went away") !== false) {
			throw new \Exception("response error: Redis server went away");
		}

		if (strpos($response, "系统繁忙,请稍后重试") !== false) {
			throw new \Exception("response error: 系统繁忙,请稍后重试");
		}
		if (strpos($response, "您访问的页面暂时不可用，请联系客服") !== false) {
			throw new \Exception("response error: 您访问的页面暂时不可用，请联系客服");
		}


		return $response;
	}


	function __destruct()
	{
		curl_close($this->ch);
	}

	function log($message, $data = []) {
		echo date('Y-m-d H:i:s') . "  " .$message; 
		if ($data) {
			var_dump($data);
		}
		echo "\n";
	}
}

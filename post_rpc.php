<?
	// Really simple hook for SecondCrack(http://www.marco.org/secondcrack) to ping rpc-services, such as pingomatic
	// Created by Marcus Olsson (http://marcusolsson.me)
	// To install, just place it in your hook directory, and if SecondCrack is installed correctly – it should be executed automatically.

	//Setup
	class Settings {

		//Leave these empty if you want to use the settings you set in config.php
		public static $blogTitle = "";
		public static $blogUrl = "";

		//Fill in all the services you wish to ping
		public static $myList = array(
			'http://rpc.pingomatic.com/',
			'http://rpc.icerocket.com:10080/',
			'http://rpc.weblogs.com/RPC2',
		);

		// If you for whatever reason want to change the useragent
		public static $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_4) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.21 Safari/536.11';

		// Debug level 0 = no messages
		// Debug level 1 = status
		// Debug level 2 = show response (I haven't bothered to format the response, so it will look rather funny – just for debuging)
		public static $debugLevel = 1;
	}

	//No need to edit any further than this
	function setHostAndPath($pingList) {

		$finalList = array();
		$counter = null;

		foreach ($pingList as $item) {
			$host = $item;
			$host = preg_replace('/^.*http:\/\//', '', $host);
			$host = preg_replace('/\/.*$/', '', $host);

			$path = $item;
			$path = preg_replace('/^.*http:\/\/[a-zA-Z0-9\-_\.]*\.[a-zA-Z]{1,3}\//', '', $path, -1, $counter);
			if(!$counter) {
				$path='';
			} 
			if ($host) {
				$finalList[$host] = $path;
			}
		}

		return $finalList;
		
	}

	function ping($title, $url) {

		$title = (Settings::$blogTitle) ? Settings::$blogTitle : $title;
	 	$url = (Settings::$blogUrl) ? Settings::$blogUrl : $url;

		if(1 <= Settings::$debugLevel) {
			error_log("The url that will be sent is: " . $url . ". And the title: " . $title);
		}

	 	// Let's make some XML! Fun!
		$xml= new DOMDocument('1.0');
		$xml->formatOutput=true;
		$xml->preserveWhiteSpace=false;
		$xml->substituteEntities=false;
	 
		$methodCall = $xml->appendChild($xml->createElement('methodCall'));
		$methodName = $methodCall->appendChild($xml->createElement('methodName'));
		$params = $methodCall->appendChild($xml->createElement('params'));
		$param[1] = $params->appendChild($xml->createElement('param'));
		$value[1] = $param[1]->appendChild($xml->createElement('value'));
		$param[2] = $params->appendChild($xml->createElement('param'));
		$value[2] = $param[2]->appendChild($xml->createElement('value'));
	 
		$methodName->nodeValue = "weblogUpdates.ping";
		$value[1]->nodeValue = $title;
		$value[2]->nodeValue = $url;
	 
		$xmlRpcReq = $xml->saveXML(); // Save the XML to a string
		$xmlRpcLength = strlen($xmlRpcReq); // We'll also need the length of the content to the header

		// Let's do some pinging
		foreach(setHostAndPath(Settings::$myList) as $host => $path) {

			$httpReq  = "POST /" . $path . " HTTP/1.0\r\n";
			$httpReq .= "User-Agent: " . Settings::$userAgent. "\r\n";
			$httpReq .= "Host: " . $host . "\r\n";
			$httpReq .= "Content-Type: text/xml\r\n";
			$httpReq .= "Content-length:".  $xmlRpcLength . "\r\n\r\n";
			$httpReq .= "$xmlRpcReq\r\n";

			$pinghandle = @fsockopen($host, 80);

			if ($pinghandle) {
				if(1 <= Settings::$debugLevel) {
					error_log("Pinging " . $host);
				}
				@fputs($pinghandle, $httpReq);
				while (!feof($pinghandle)) { 
					$pingresponse = @fgets( $pinghandle, 128 );
					if(2 == Settings::$debugLevel && $pingresponse) {
						error_log(htmlentities($pingresponse));
					} else if(1 <= Settings::$debugLevel){
						error_log("No response from " . $host);
					}
				}
				@fclose($pinghandle);
			} else if(1 <= Settings::$debugLevel) {
				error_log("Could not connect to " . $host);
			}
		}
	}

	class Rpc extends Hook {
		public function doHook(Post $post) {
			ping($post::$blog_title, $post::$blog_url);
		}
	}
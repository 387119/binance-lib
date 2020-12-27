<?php

# class for work with cinance over public api
#

class Binance {
	public $debug=0;
	public $data=array();
	public $res=array();
	private $cfg=array(
		"url_root"=>"https://api.binance.com/api/",
		"symbol"=>false,
		"secretkey"=>false,
		"apikey"=>false,
		"recvWindow"=>2000,
		"depth"=>array(
			"limit"=>5
		)
	);
	function __construct (){
		$args=func_get_args();
		$global_cfg=parse_ini_file(dirname(__FILE__)."/../etc/binance.cfg");
		#set global vars
		if (isset($global_cfg['secretkey']))
			$this->cfg['secretkey']=$global_cfg['secretkey'];
		if (isset($global_cfg['apikey']))
			$this->cfg['apikey']=$global_cfg['apikey'];
		#set initialize vars with update global`s
		if (isset($args['symbol']))
			$this->cfg['symbol']=$args['symbol'];
		if (isset($args['secretkey']))
			$this->cfg['secretkey']=$args['secretkey'];
		if (isset($args['apikey']))
			$this->cfg['apikey']=$args['apikey'];
		if (isset($args['depth']['limit']))
			$this->cfg['depth']['limit']=$args['depth']['limit'];
	}
	private function __wlog($str){
		if ($this->debug>=5)
			$loglevel="DEBUG";
		echo date("c")."\t[binance]\t[$loglevel]\t$str\n";
	}
	private function __url_open($get,$headers,$method){
		if ($this->debug>=5){
			$this->__wlog("METHOD: $method GET: ".json_encode($get)." HEADERS: ".json_encode($headers));
		}
		$ch=curl_init();
		curl_setopt($ch,CURLOPT_URL,$get);
		if (($headers!=false)and (is_array($headers))){
			if (count($headers)>0){
				curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
			}
		}
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch,CURLOPT_FOLLOWLOCATION,"true");
		#curl_setopt($ch,CURLOPT_POST,1);
		#curl_setopt($ch,CURLOPT_POSTFIELDS,array());
		#curl_setopt($ch,CURLOPT_VERBOSE,true);
		#$verb=fopen ('debug.log',"w+");
		#curl_setopt($ch,CURLOPT_STDERR,$verb);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$output= curl_exec($ch);
		if (!$output){
			trigger_error(curl_error($ch));
			die();
		}
		curl_close($ch);
		return $output;
	}
	private function _request($api,$headers,$method="GET",$get=false,$post=false){
		$url=$this->cfg['url_root'].$api;
		$getstr=http_build_query($get);
		if (strlen($getstr)>0)
			$url=$url."?".$getstr;
		$this->data[$api]=array();
		$this->data[$api]['request']['tstamp']=date("Y-m-d H:i:s");
		$this->data[$api]['request']['url']=$url;
		$this->data[$api]['request']['method']=$method;
		$this->data[$api]['request']['headers']=$headers;
		$this->data[$api]['request']['get']=$get;
		$this->data[$api]['request']['post']=$post;
		$res=$this->__url_open($url,$headers,$method);
		$this->data[$api]['response']['tstamp']=date("Y-m-d H:i:s");
		$this->data[$api]['response']['raw']=$res;
		$this->data[$api]['response']['json']=json_decode($this->data[$api]['response']['raw'],1);
		$this->res=$this->data[$api]['response']['json'];
	}
	private function _sign($data){
		$datastr=http_build_query($data);
		exec ("echo -n '$datastr' | openssl dgst -sha256 -hmac '".$this->cfg['secretkey']."' | sed -e 's/.*= \\(.*\\)/\\1/g'",$sign);
		return $sign[0];
	}
	private function _define_get_params($in,$listParams){
		$res=array();
		$doSign="false";
		foreach ($listParams as $param){
			switch ($param){
				case "timestamp":
					$res["timestamp"]=intval(microtime(true)*1000);
					break;
				case "signature":
					$doSign="true";
					break;
				case "price":
					$res[$param]=number_format($in[$param],2,'.','');
					break;
				default:
					if (isset($in[$param]))
						$res[$param]=$in[$param];
					break;
			}
		}
		if ($doSign=="true")
			$res['signature']=$this->_sign($res);
		return $res;
	}
	private function _check_for_mandatory_params ($in,$listParams){
		$res=true;
		foreach ($listParams as $param){
			if (!isset($in[$param])){
				echo "mandatory parameter '$param' is not found\n";
				$res=false;
			}
		}
		if ($res==false)
			die();
	}
	function api_v1_exchangeInfo(){//deprecated
		$api="v1/exchangeInfo";
		$headers=array();
		$this->_request($api,$headers);
	}
	function api_v1_depth($symbol=false,$limit=false){
		$api="v1/depth";
		$headers=array();
		if (($this->symbol==false)&&($symbol==false)){
			echo "api_v1_depth: symbol cannot be null, use api_v1_depth(symbol) or initialize class with symbol new Binance(symbol)";
			return false;
		}
		$local_symbol=$symbol;
		$get=array(
			"symbol"=>$pair
		);
		$this->_request($api,$headers,$get);
	}
	function _api_v3_account(){
		$api="v3/account";
		$get=array();
		$headers=array();
		$headers=array(
			"X-MBX-APIKEY: ".$this->cfg['apikey']
		);
		$method="GET";
		$get=$this->_define_get_params(array(),array("recvWindow","timestamp","signature"));
		$this->_check_for_mandatory_params($get,array("timestamp","signature"));
		$this->_request($api,$headers,$method,$get);
	}
	function api_v3_order($in){//deprecated
		$api="v3/order";
		if (isset($in['method']))
			$method=$in['method'];
		$headers=array(
			"X-MBX-APIKEY: ".$this->cfg['apikey']
		);
		$get=array();
		if (isset($in['symbol']))
			$get["symbol"]=$in['symbol'];
		if (isset($in['orderId']))
			$get["orderId"]=$in['orderId'];
		if (isset($in['side']))
			$get["side"]=$in['side'];
		if (isset($in['type']))
			$get["type"]=$in['type'];
		if (isset($in['timeInForce']))
			$get["timeInForce"]=$in['timeInForce'];
		if (isset($in['quantity']))
			$get["quantity"]=$in['quantity'];
		if (isset($in['price']))
			$get["price"]=number_format($in['price'],8,'.','');
		if (isset($in['recvWindow']))
			$get["recvWindow"]=$in['recvWindow'];
		$get["timestamp"]=intval(microtime(true)*1000);
		$get['signature']=$this->_sign($get);
		$this->_request($api,$headers,$method,$get);
	}
	function _api_v3_order($in){
		$api="v3/order";
		if (!isset($in['method']))
			die("Method for order is not defined");
		$headers=array(
			"X-MBX-APIKEY: ".$this->cfg['apikey']
		);
		$get=array();
		switch ($in['method']){
			case "POST":
				$get=$this->_define_get_params($in,array("symbol","side","type","timeInForce","quantity","price","recvWindow","timestamp","signature"));
				$this->_check_for_mandatory_params($get,array("symbol","timestamp","signature"));
				break;
			case "DELETE":
				$get=$this->_define_get_params($in,array("symbol","orderId","recvWindow","timestamp","signature"));
				$this->_check_for_mandatory_params($get,array("symbol","orderId","timestamp","signature"));
				break;
			default:
				die("method for order is wrong");
				break;
		}
		$this->_request($api,$headers,$in["method"],$get);
	}
	function _api_v3_order_test($in){
		$api="v3/order/test";
		$method="POST";
		if (isset($in['method']))
			$method=$in['method'];
		$headers=array(
			"X-MBX-APIKEY: ".$this->cfg['apikey']
		);
		$get=array();
		$get=$this->_define_get_params($in,array("symbol","orderId","side","type","timeInForce","quantity","price","recvWindow","timestamp","signature"));
		$this->_check_for_mandatory_params($get,array("symbol","timestamp","signature"));
		$this->_request($api,$headers,$method,$get);
	}
	function api_v1_trades($in){
		$api="v1/trades";
		$get=array();
		$headers=array();
		$method="GET";
		if (isset($in['symbol']))
			$get["symbol"]=$in['symbol'];
		if (isset($in['limit']))
			$get["limit"]=$in['limit'];
		$this->_request($api,$headers,$method,$get);
	}
	function api_v1_historicalTrades($in){
		$api="v1/historicalTrades";
		$get=array();
		$headers=array(
			"X-MBX-APIKEY: ".$this->cfg['apikey']
		);
		$method="GET";
		if (isset($in['symbol']))
			$get["symbol"]=$in['symbol'];
		if (isset($in['limit']))
			$get["limit"]=$in['limit'];
		if (isset($in['fromId']))
			$get["fromId"]=$in['fromId'];
		$this->_request($api,$headers,$method,$get);
	}
	function api_v1_klines($in){
		$api="v1/klines";
		$get=array();
		$headers=array();
		//$headers=array(
		//	"X-MBX-APIKEY: ".$this->cfg['apikey']
		//);
		$method="GET";
		if (isset($in['symbol']))
			$get["symbol"]=$in['symbol'];
		if (isset($in['interval']))
			$get["interval"]=$in['interval'];
		if (isset($in['startTime']))
			$get["startTime"]=$in['startTime'];
		if (isset($in['endTime']))
			$get["endTime"]=$in['endTime'];
		if (isset($in['limit']))
			$get["limit"]=$in['limit'];
		$this->_request($api,$headers,$method,$get);
	}
	function _api_v1_exchangeInfo(){
		$api="v1/exchangeInfo";
		$get=array();
		$headers=array();
		$method="GET";
		$this->_request($api,$headers,$method,$get);
	}
	function _api_v3_openOrders($in){
		$api="v3/openOrders";
		$get=array();
		$headers=array();
		$headers=array(
			"X-MBX-APIKEY: ".$this->cfg['apikey']
		);
		$method="GET";
		$get=$this->_define_get_params($in,array("symbol","recvWindow","timestamp","signature"));
		$this->_check_for_mandatory_params($get,array("timestamp","signature"));
		$this->_request($api,$headers,$method,$get);
	}
	function _api_v3_allOrders($in){
		$api="v3/allOrders";
		$get=array();
		$headers=array();
		$headers=array(
			"X-MBX-APIKEY: ".$this->cfg['apikey']
		);
		$method="GET";
		$get=$this->_define_get_params($in,array("symbol","orderId","startTime","endTime","recvWindow","limit","timestamp","signature"));
		$this->_check_for_mandatory_params($get,array("symbol","timestamp","signature"));
		$this->_request($api,$headers,$method,$get);
	}
	function _api_v3_avgPrice($in){
		$api="v3/avgPrice";
		$get=array();
		$headers=array();
		$method="GET";
		$get=$this->_define_get_params($in,array("symbol"));
		$this->_check_for_mandatory_params($get,array("symbol"));
		$this->_request($api,$headers,$method,$get);
	}
	function orderList($in=array()){
		if (isset($in['listType'])){
			if ($in['listType']=='open'){
				$this->_api_v3_openOrders($in);
				return $this->res;
			}
			if ($in['listType']=='all'){
				$this->_api_v3_allOrders($in);
				return $this->res;
			}
		}
		return false;
	}
	function getBalanceFree($symbol){
		$this->_api_v3_account();
		foreach ($this->res["balances"] as $b1){
			if ($b1['asset']=="$symbol"){
				return $b1['free'];
			}
		}
		return 0;
	}
	function avgPrice($symbol){
		$in['symbol']=$symbol;
		$this->_api_v3_avgPrice($in);
		return $this->res['price'];
	}
	function exchangeInfo(){
		$this->_api_v1_exchangeInfo();
		return $this->res;
	}
	function placeOrder($in){
		$in["method"]="POST";
		$this->_api_v3_order($in);
		return $this->res;
	}
	function cancelOrder($in){
		$in["method"]="DELETE";
		$this->_api_v3_order($in);
		return $this->res;
	}
	function placeOrderTest($in){
		$this->_api_v3_order_test($in);
		return $this->res;
	}
	function setUrl($url){
		$this->cfg["url_root"]=$url;
	}
}
?>


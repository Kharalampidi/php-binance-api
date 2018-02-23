<?php
/* ============================================================
 * php-binance-api
 * https://github.com/jaggedsoft/php-binance-api
 * ============================================================
 * Copyright 2017-, Jon Eyrick
 * Released under the MIT License
 * ============================================================ */

namespace Binance;
/**
 * Class API
 * @package Binance
 */
class API
{
    /**
     * @var string
     */
    protected $base = "https://api.binance.com/api/", $wapi = "https://api.binance.com/wapi/", $api_key, $api_secret;
    /**
     * @var array
     */
    protected $depthCache = [];
    /**
     * @var array
     */
    protected $depthQueue = [];
    /**
     * @var array
     */
    protected $chartQueue = [];
    /**
     * @var array
     */
    protected $charts = [];
    /**
     * @var array
     */
    protected $info = ["timeOffset" => 0];
    /**
     * @var array
     */
    public $balances = [];
    /**
     * @var float
     */
    public $btc_value = 0.00; // value of available assets
    /**
     * @var float
     */
    public $btc_total = 0.00; // value of available + onOrder assets

    /**
     * API constructor.
     * @param string $api_key
     * @param string $api_secret
     * @param array $options
     */
    public function __construct($api_key = '', $api_secret = '', $options = ["useServerTime" => false])
    {
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
        if (isset($options['useServerTime']) && $options['useServerTime']) {
            $this->useServerTime();
        }
    }

    /**
     * @param $symbol
     * @param $quantity
     * @param $price
     * @param string $type
     * @param array $flags
     * @return array|mixed
     */
    public function buy($symbol, $quantity, $price, $type = "LIMIT", $flags = [])
    {
        return $this->order("BUY", $symbol, $quantity, $price, $type, $flags);
    }

    /**
     * @param $symbol
     * @param $quantity
     * @param $price
     * @param string $type
     * @param array $flags
     * @return array|mixed
     */
    public function sell($symbol, $quantity, $price, $type = "LIMIT", $flags = [])
    {
        return $this->order("SELL", $symbol, $quantity, $price, $type, $flags);
    }

    /**
     * @param $symbol
     * @param $quantity
     * @return array|mixed
     */
    public function marketBuy($symbol, $quantity)
    {
        return $this->order("BUY", $symbol, $quantity, 0, "MARKET", $flags = []);
    }

    /**
     * @param $symbol
     * @param $quantity
     * @return array|mixed
     */
    public function marketSell($symbol, $quantity)
    {
        return $this->order("SELL", $symbol, $quantity, 0, "MARKET", $flags = []);
    }

    /**
     * @param $symbol
     * @param $orderid
     * @return array|mixed
     */
    public function cancel($symbol, $orderid)
    {
        return $this->signedRequest("v3/order", ["symbol" => $symbol, "orderId" => $orderid], "DELETE");
    }

    /**
     * @param $symbol
     * @param $orderid
     * @return array|mixed
     */
    public function orderStatus($symbol, $orderid)
    {
        return $this->signedRequest("v3/order", ["symbol" => $symbol, "orderId" => $orderid]);
    }

    /**
     * @param $symbol
     * @return array|mixed
     */
    public function openOrders($symbol)
    {
        return $this->signedRequest("v3/openOrders", ["symbol" => $symbol]);
    }

    /**
     * @param $symbol
     * @param int $limit
     * @return array|mixed
     */
    public function orders($symbol, $limit = 500)
    {
        return $this->signedRequest("v3/allOrders", ["symbol" => $symbol, "limit" => $limit]);
    }

    /**
     * @param $symbol
     * @param int $limit
     * @return array|mixed
     */
    public function history($symbol, $limit = 500)
    {
        return $this->signedRequest("v3/myTrades", ["symbol" => $symbol, "limit" => $limit]);
    }

    /**
     * @void
     */
    public function useServerTime()
    {
        $serverTime = $this->apiRequest("v1/time")['serverTime'];
        $this->info['timeOffset'] = $serverTime - (microtime(true) * 1000);
    }

    /**
     * @return array|mixed
     */
    public function time()
    {
        return $this->apiRequest("v1/time");
    }

    /**
     * @return array|mixed
     */
    public function exchangeInfo()
    {
        return $this->request("v1/exchangeInfo");
    }

    /**
     * @param $asset
     * @param $address
     * @param $amount
     * @param bool $addressTag
     * @return array|mixed
     */
    public function withdraw($asset, $address, $amount, $addressTag = false)
    {
        $options = ["asset" => $asset, "address" => $address, "amount" => $amount, "wapi" => true, "name" => "API Withdraw"];
        if ($addressTag) $options['addressTag'] = $addressTag;
        return $this->signedRequest("v3/withdraw.html", $options, "POST");
    }

    /**
     * @param $asset
     * @return array|mixed
     */
    public function depositAddress($asset)
    {
        $params = ["wapi" => true, "asset" => $asset];
        return $this->signedRequest("v3/depositAddress.html", $params, "GET");
    }

    /**
     * @param bool $asset
     * @return array|mixed
     */
    public function depositHistory($asset = false)
    {
        $params = ["wapi" => true];
        if ($asset) $params['asset'] = $asset;
        return $this->signedRequest("v3/depositHistory.html", $params, "GET");
    }

    /**
     * @param bool $asset
     * @return array|mixed
     */
    public function withdrawHistory($asset = false)
    {
        $params = ["wapi" => true];
        if ($asset) $params['asset'] = $asset;
        return $this->signedRequest("v3/withdrawHistory.html", $params, "GET");
    }

    /**
     * @return array
     */
    public function prices()
    {
        return $this->priceData($this->request("v3/ticker/price"));
    }

    /**
     * @return array
     */
    public function bookPrices()
    {
        return $this->bookPriceData($this->request("v3/ticker/bookTicker"));
    }

    /**
     * @return array|mixed
     */
    public function account()
    {
        return $this->signedRequest("v3/account");
    }

    /**
     * @param $symbol
     * @return array|mixed
     */
    public function prevDay($symbol)
    {
        return $this->request("v1/ticker/24hr", ["symbol" => $symbol]);
    }

    /**
     * @param $symbol
     * @return array
     */
    public function aggTrades($symbol)
    {
        return $this->tradesData($this->request("v1/aggTrades", ["symbol" => $symbol]));
    }

    /**
     * @param $symbol
     * @param int $limit
     * @return array
     */
    public function depth($symbol, $limit = 20)
    {
        if (!in_array($limit, [5, 10, 20, 50, 100, 500, 1000])) {
            throw new \Exception('Invalid limit.');
        }
        $json = $this->request("v1/depth", ["symbol" => $symbol, 'limit' => $limit]);
        if (!isset($this->info[$symbol])) $this->info[$symbol] = [];
        $this->info[$symbol]['firstUpdate'] = $json['lastUpdateId'];
        return $this->depthData($symbol, $json);
    }

    /**
     * @param bool $priceData
     * @return array
     */
    public function balances($priceData = false)
    {
        return $this->balanceData($this->signedRequest("v3/account"), $priceData);
    }

    /**
     * @param $url
     * @param array $params
     * @param string $method
     * @return array|mixed
     */
    private function request($url, $params = [], $method = "GET")
    {
        $opt = [
            "http" => [
                "method" => $method,
                "ignore_errors" => true,
                "header" => "User-Agent: Mozilla/4.0 (compatible; PHP Binance API)\r\n"
            ]
        ];
        $context = stream_context_create($opt);
        $query = http_build_query($params, '', '&');
        try {
            $data = file_get_contents($this->base . $url . '?' . $query, false, $context);
        } catch (Exception $e) {
            return ["error" => $e->getMessage()];
        }
        return json_decode($data, true);
    }

    /**
     * @param $url
     * @param array $params
     * @param string $method
     * @return array|mixed
     */
    private function signedRequest($url, $params = [], $method = "GET")
    {
        if (empty($this->api_key)) die("signedRequest error: API Key not set!");
        if (empty($this->api_secret)) die("signedRequest error: API Secret not set!");
        $base = $this->base;
        $opt = [
            "http" => [
                "method" => $method,
                "ignore_errors" => true,
                "header" => "User-Agent: Mozilla/4.0 (compatible; PHP Binance API)\r\nX-MBX-APIKEY: {$this->api_key}\r\n"
            ]
        ];
        $context = stream_context_create($opt);
        $ts = (microtime(true) * 1000) + $this->info['timeOffset'];
        $params['timestamp'] = number_format($ts, 0, '.', '');
        if (isset($params['wapi'])) {
            unset($params['wapi']);
            $base = $this->wapi;
        }
        $query = http_build_query($params, '', '&');
        $signature = hash_hmac('sha256', $query, $this->api_secret);
        $endpoint = $base . $url . '?' . $query . '&signature=' . $signature;
        try {
            $data = file_get_contents($endpoint, false, $context);
        } catch (Exception $e) {
            return ["error" => $e->getMessage()];
        }
        $json = json_decode($data, true);
        if (isset($json['msg'])) {
            echo "signedRequest error: {$data}" . PHP_EOL;
        }
        return $json;
    }

    /**
     * @param $url
     * @param string $method
     * @return array|mixed
     */
    private function apiRequest($url, $method = "GET")
    {
        if (empty($this->api_key)) die("apiRequest error: API Key not set!");
        $opt = [
            "http" => [
                "method" => $method,
                "ignore_errors" => true,
                "header" => "User-Agent: Mozilla/4.0 (compatible; PHP Binance API)\r\nX-MBX-APIKEY: {$this->api_key}\r\n"
            ]
        ];
        $context = stream_context_create($opt);
        try {
            $data = file_get_contents($this->base . $url, false, $context);
        } catch (Exception $e) {
            return ["error" => $e->getMessage()];
        }
        return json_decode($data, true);
    }

    /**
     * @param $side
     * @param $symbol
     * @param $quantity
     * @param $price
     * @param string $type
     * @param array $flags
     * @return array|mixed
     */
    public function order($side, $symbol, $quantity, $price, $type = "LIMIT", $flags = [])
    {
        $opt = [
            "symbol" => $symbol,
            "side" => $side,
            "type" => $type,
            "quantity" => $quantity,
            "recvWindow" => 60000
        ];
        if ($type === "LIMIT" || $type === "STOP_LOSS_LIMIT" || $type === "TAKE_PROFIT_LIMIT") {
            $opt["price"] = $price;
            $opt["timeInForce"] = "GTC";
        }
        if (isset($flags['stopPrice'])) $opt['stopPrice'] = $flags['stopPrice'];
        if (isset($flags['icebergQty'])) $opt['icebergQty'] = $flags['icebergQty'];
        if (isset($flags['newOrderRespType'])) $opt['newOrderRespType'] = $flags['newOrderRespType'];
        return $this->signedRequest("v3/order", $opt, "POST");
    }

    //1m,3m,5m,15m,30m,1h,2h,4h,6h,8h,12h,1d,3d,1w,1M

    /**
     * @param $symbol
     * @param string $interval
     * @param null $limit
     * @param null $startTime
     * @param null $endTime
     * @return array
     */
    public function candlesticks($symbol, $interval = "5m", $limit = null, $startTime = null, $endTime = null)
    {
        if (!isset($this->charts[$symbol])) $this->charts[$symbol] = [];
        $opt = [
            "symbol" => $symbol,
            "interval" => $interval
        ];
        if ($limit) $opt["limit"] = $limit;
        if ($startTime) $opt["startTime"] = $startTime;
        if ($endTime) $opt["endTime"] = $endTime;

        $response = $this->request("v1/klines", $opt);
        $ticks = $this->chartData($symbol, $interval, $response);
        $this->charts[$symbol][$interval] = $ticks;
        return $ticks;
    }

    // Converts all your balances into a nice array
    // If priceData is passed from $api->prices() it will add btcValue & btcTotal to each symbol
    // This function sets $btc_value which is your estimated BTC value of all assets combined and $btc_total which includes amount on order
    /**
     * @param $array
     * @param bool $priceData
     * @return array
     */
    private function balanceData($array, $priceData = false)
    {
        if ($priceData) $btc_value = $btc_total = 0.00;
        $balances = [];
        if (empty($array) || empty($array['balances'])) {
            echo "balanceData error: Please make sure your system time is synchronized, or pass the useServerTime option." . PHP_EOL;
            return [];
        }
        foreach ($array['balances'] as $obj) {
            $asset = $obj['asset'];
            $balances[$asset] = ["available" => $obj['free'], "onOrder" => $obj['locked'], "btcValue" => 0.00000000, "btcTotal" => 0.00000000];
            if ($priceData) {
                if ($obj['free'] + $obj['locked'] < 0.00000001) continue;
                if ($asset == 'BTC') {
                    $balances[$asset]['btcValue'] = $obj['free'];
                    $balances[$asset]['btcTotal'] = $obj['free'] + $obj['locked'];
                    $btc_value += $obj['free'];
                    $btc_total += $obj['free'] + $obj['locked'];
                    continue;
                }
                $symbol = $asset . 'BTC';
                if ($symbol == 'USDTBTC') {
                    $btcValue = number_format($obj['free'] / $priceData['BTCUSDT'], 8, '.', '');
                    $btcTotal = number_format(($obj['free'] + $obj['locked']) / $priceData['BTCUSDT'], 8, '.', '');
                } elseif (!isset($priceData[$symbol])) {
                    $btcValue = $btcTotal = 0;
                } else {
                    $btcValue = number_format($obj['free'] * $priceData[$symbol], 8, '.', '');
                    $btcTotal = number_format(($obj['free'] + $obj['locked']) * $priceData[$symbol], 8, '.', '');
                }
                $balances[$asset]['btcValue'] = $btcValue;
                $balances[$asset]['btcTotal'] = $btcTotal;
                $btc_value += $btcValue;
                $btc_total += $btcTotal;
            }
        }
        if ($priceData) {
            uasort($balances, function ($a, $b) {
                return $a['btcValue'] < $b['btcValue'];
            });
            $this->btc_value = $btc_value;
            $this->btc_total = $btc_total;
        }
        return $balances;
    }

    // Convert balance WebSocket data into array

    /**
     * @param $json
     * @return array
     */
    private function balanceHandler($json)
    {
        $balances = [];
        foreach ($json as $item) {
            $asset = $item->a;
            $available = $item->f;
            $onOrder = $item->l;
            $balances[$asset] = ["available" => $available, "onOrder" => $onOrder];
        }
        return $balances;
    }

    // Convert WebSocket ticker data into array

    /**
     * @param $json
     * @return array
     */
    private function tickerStreamHandler($json)
    {
        return [
            "eventType" => $json->e,
            "eventTime" => $json->E,
            "symbol" => $json->s,
            "priceChange" => $json->p,
            "percentChange" => $json->P,
            "averagePrice" => $json->w,
            "prevClose" => $json->x,
            "close" => $json->c,
            "closeQty" => $json->Q,
            "bestBid" => $json->b,
            "bestBidQty" => $json->B,
            "bestAsk" => $json->a,
            "bestAskQty" => $json->A,
            "open" => $json->o,
            "high" => $json->h,
            "low" => $json->l,
            "volume" => $json->v,
            "quoteVolume" => $json->q,
            "openTime" => $json->O,
            "closeTime" => $json->C,
            "firstTradeId" => $json->F,
            "lastTradeId" => $json->L,
            "numTrades" => $json->n
        ];
    }

    // Convert WebSocket trade execution into array

    /**
     * @param $json
     * @return array
     */
    private function executionHandler($json)
    {
        return [
            "symbol" => $json->s,
            "side" => $json->S,
            "orderType" => $json->o,
            "quantity" => $json->q,
            "price" => $json->p,
            "executionType" => $json->x,
            "orderStatus" => $json->X,
            "rejectReason" => $json->r,
            "orderId" => $json->i,
            "clientOrderId" => $json->c,
            "orderTime" => $json->T,
            "eventTime" => $json->E
        ];
    }

    // Convert kline data into object

    /**
     * @param $symbol
     * @param $interval
     * @param $ticks
     * @return array
     */
    private function chartData($symbol, $interval, $ticks)
    {
        if (!isset($this->info[$symbol])) $this->info[$symbol] = [];
        if (!isset($this->info[$symbol][$interval])) $this->info[$symbol][$interval] = [];
        $output = [];
        foreach ($ticks as $tick) {
            list($openTime, $open, $high, $low, $close, $assetVolume, $closeTime, $baseVolume, $trades, $assetBuyVolume, $takerBuyVolume, $ignored) = $tick;
            $output[$openTime] = [
                "open" => $open,
                "high" => $high,
                "low" => $low,
                "close" => $close,
                "volume" => $baseVolume,
                "openTime" => $openTime,
                "closeTime" => $closeTime
            ];
        }
        $this->info[$symbol][$interval]['firstOpen'] = $openTime;
        return $output;
    }

    // Convert aggTrades data into easier format

    /**
     * @param $trades
     * @return array
     */
    private function tradesData($trades)
    {
        $output = [];
        foreach ($trades as $trade) {
            $price = $trade['p'];
            $quantity = $trade['q'];
            $timestamp = $trade['T'];
            $maker = $trade['m'] ? 'true' : 'false';
            $output[] = ["price" => $price, "quantity" => $quantity, "timestamp" => $timestamp, "maker" => $maker];
        }
        return $output;
    }

    // Consolidates Book Prices into an easy to use object

    /**
     * @param $array
     * @return array
     */
    private function bookPriceData($array)
    {
        $bookprices = [];
        foreach ($array as $obj) {
            $bookprices[$obj['symbol']] = [
                "bid" => $obj['bidPrice'],
                "bids" => $obj['bidQty'],
                "ask" => $obj['askPrice'],
                "asks" => $obj['askQty']
            ];
        }
        return $bookprices;
    }

    // Converts Price Data into an easy key/value array

    /**
     * @param $array
     * @return array
     */
    private function priceData($array)
    {
        $prices = [];
        foreach ($array as $obj) {
            $prices[$obj['symbol']] = $obj['price'];
        }
        return $prices;
    }

    // Converts depth cache into a cumulative array

    /**
     * @param $depth
     * @return array
     */
    public function cumulative($depth)
    {
        $bids = [];
        $asks = [];
        $cumulative = 0;
        foreach ($depth['bids'] as $price => $quantity) {
            $cumulative += $quantity;
            $bids[] = [$price, $cumulative];
        }
        $cumulative = 0;
        foreach ($depth['asks'] as $price => $quantity) {
            $cumulative += $quantity;
            $asks[] = [$price, $cumulative];
        }
        return ["bids" => $bids, "asks" => array_reverse($asks)];
    }

    // Converts Chart Data into array for highstock & kline charts

    /**
     * @param $chart
     * @param bool $include_volume
     * @return array
     */
    public function highstock($chart, $include_volume = false)
    {
        $array = [];
        foreach ($chart as $timestamp => $obj) {
            $line = [
                $timestamp,
                floatval($obj['open']),
                floatval($obj['high']),
                floatval($obj['low']),
                floatval($obj['close'])
            ];
            if ($include_volume) $line[] = floatval($obj['volume']);
            $array[] = $line;
        }
        return $array;
    }

    // For WebSocket Depth Cache

    /**
     * @param $json
     */
    private function depthHandler($json)
    {
        $symbol = $json['s'];
        if ($json['u'] <= $this->info[$symbol]['firstUpdate']) return;
        foreach ($json['b'] as $bid) {
            $this->depthCache[$symbol]['bids'][$bid[0]] = $bid[1];
            if ($bid[1] == "0.00000000") unset($this->depthCache[$symbol]['bids'][$bid[0]]);
        }
        foreach ($json['a'] as $ask) {
            $this->depthCache[$symbol]['asks'][$ask[0]] = $ask[1];
            if ($ask[1] == "0.00000000") unset($this->depthCache[$symbol]['asks'][$ask[0]]);
        }
    }

    // For WebSocket Chart Cache

    /**
     * @param $symbol
     * @param $interval
     * @param $json
     */
    private function chartHandler($symbol, $interval, $json)
    {
        if (!$this->info[$symbol][$interval]['firstOpen']) { // Wait for /kline to finish loading
            $this->chartQueue[$symbol][$interval][] = $json;
            return;
        }
        $chart = $json->k;
        $symbol = $json->s;
        $interval = $chart->i;
        $tick = $chart->t;
        if ($tick < $this->info[$symbol][$interval]['firstOpen']) return; // Filter out of sync data
        $open = $chart->o;
        $high = $chart->h;
        $low = $chart->l;
        $close = $chart->c;
        $volume = $chart->q; //+trades buyVolume assetVolume makerVolume
        $this->charts[$symbol][$interval][$tick] = ["open" => $open, "high" => $high, "low" => $low, "close" => $close, "volume" => $volume];
    }

    // Gets first key of an array

    /**
     * @param $array
     * @return mixed
     */
    public function first($array)
    {
        return array_keys($array)[0];
    }

    // Gets last key of an array

    /**
     * @param $array
     * @return mixed
     */
    public function last($array)
    {
        return array_keys(array_slice($array, -1))[0];
    }

    // Formats nicely for console output

    /**
     * @param $array
     * @return string
     */
    public function displayDepth($array)
    {
        $output = '';
        foreach (['asks', 'bids'] as $type) {
            $entries = $array[$type];
            if ($type == 'asks') $entries = array_reverse($entries);
            $output .= "{$type}:" . PHP_EOL;
            foreach ($entries as $price => $quantity) {
                $total = number_format($price * $quantity, 8, '.', '');
                $quantity = str_pad(str_pad(number_format(rtrim($quantity, '.0')), 10, ' ', STR_PAD_LEFT), 15);
                $output .= "{$price} {$quantity} {$total}" . PHP_EOL;
            }
            //echo str_repeat('-', 32).PHP_EOL;
        }
        return $output;
    }

    // Sorts depth data for display & getting highest bid and lowest ask

    /**
     * @param $symbol
     * @param int $limit
     * @return array
     */
    public function sortDepth($symbol, $limit = 11)
    {
        $bids = $this->depthCache[$symbol]['bids'];
        $asks = $this->depthCache[$symbol]['asks'];
        krsort($bids);
        ksort($asks);
        return ["asks" => array_slice($asks, 0, $limit, true), "bids" => array_slice($bids, 0, $limit, true)];
    }

    // Formats depth data for nice display

    /**
     * @param $symbol
     * @param $json
     * @return array
     */
    private function depthData($symbol, $json)
    {
        $bids = $asks = [];
        foreach ($json['bids'] as $obj) {
            $bids[$obj[0]] = $obj[1];
        }
        foreach ($json['asks'] as $obj) {
            $asks[$obj[0]] = $obj[1];
        }
        return $this->depthCache[$symbol] = ["bids" => $bids, "asks" => $asks];
    }

    ////////////////////////////////////
    // WebSockets
    ////////////////////////////////////

    // Pulls /depth data and subscribes to @depth WebSocket endpoint
    // Maintains a local Depth Cache in sync via lastUpdateId. See depth() and depthHandler()
    /**
     * @param $symbols
     * @param $callback
     */
    public function depthCache($symbols, $callback)
    {
        if (!is_array($symbols)) $symbols = [$symbols];
        $loop = \React\EventLoop\Factory::create();
        $react = new \React\Socket\Connector($loop);
        $connector = new \Ratchet\Client\Connector($loop, $react);
        foreach ($symbols as $symbol) {
            if (!isset($this->info[$symbol])) $this->info[$symbol] = [];
            if (!isset($this->depthQueue[$symbol])) $this->depthQueue[$symbol] = [];
            if (!isset($this->depthCache[$symbol])) $this->depthCache[$symbol] = ["bids" => [], "asks" => []];
            $this->info[$symbol]['firstUpdate'] = 0;
            $connector('wss://stream.binance.com:9443/ws/' . strtolower($symbol) . '@depth')->then(function ($ws) use ($callback) {
                $ws->on('message', function ($data) use ($ws, $callback) {
                    $json = json_decode($data, true);
                    $symbol = $json['s'];
                    if ($this->info[$symbol]['firstUpdate'] == 0) {
                        $this->depthQueue[$symbol][] = $json;
                        return;
                    }
                    $this->depthHandler($json);
                    call_user_func($callback, $this, $symbol, $this->depthCache[$symbol]);
                });
                $ws->on('close', function ($code = null, $reason = null) {
                    echo "depthCache({$symbol}) WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
                });
            }, function ($e) use ($loop) {
                echo "depthCache({$symbol})) Could not connect: {$e->getMessage()}" . PHP_EOL;
                $loop->stop();
            });
            $this->depth($symbol);
            foreach ($this->depthQueue[$symbol] as $data) {
                $this->depthHandler($json);
            }
            $this->depthQueue[$symbol] = [];
            call_user_func($callback, $this, $symbol, $this->depthCache[$symbol]);
        }
        $loop->run();
    }

    // Trades WebSocket Endpoint

    /**
     * @param $symbols
     * @param $callback
     */
    public function trades($symbols, $callback)
    {
        if (!is_array($symbols)) $symbols = [$symbols];
        $loop = \React\EventLoop\Factory::create();
        $react = new \React\Socket\Connector($loop);
        $connector = new \Ratchet\Client\Connector($loop, $react);
        foreach ($symbols as $symbol) {
            if (!isset($this->info[$symbol])) $this->info[$symbol] = [];
            //$this->info[$symbol]['tradesCallback'] = $callback;
            $connector('wss://stream.binance.com:9443/ws/' . strtolower($symbol) . '@aggTrade')->then(function ($ws) use ($callback) {
                $ws->on('message', function ($data) use ($ws, $callback) {
                    $json = json_decode($data, true);
                    $symbol = $json['s'];
                    $price = $json['p'];
                    $quantity = $json['q'];
                    $timestamp = $json['T'];
                    $maker = $json['m'] ? 'true' : 'false';
                    $trades = ["price" => $price, "quantity" => $quantity, "timestamp" => $timestamp, "maker" => $maker];
                    //$this->info[$symbol]['tradesCallback']($this, $symbol, $trades);
                    call_user_func($callback, $this, $symbol, $trades);
                });
                $ws->on('close', function ($code = null, $reason = null) {
                    echo "trades({$symbol}) WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
                });
            }, function ($e) use ($loop) {
                echo "trades({$symbol}) Could not connect: {$e->getMessage()}" . PHP_EOL;
                $loop->stop();
            });
        }
        $loop->run();
    }

    // Pulls 24h price change statistics via WebSocket

    /**
     * @param $symbol
     * @param $callback
     */
    public function ticker($symbol, $callback)
    {
        $endpoint = $symbol ? strtolower($symbol) . '@ticker' : '!ticker@arr';
        \Ratchet\Client\connect('wss://stream.binance.com:9443/ws/' . $endpoint)->then(function ($ws) use ($callback, $symbol) {
            $ws->on('message', function ($data) use ($ws, $callback, $symbol) {
                $json = json_decode($data);
                if ($symbol) {
                    call_user_func($callback, $this, $symbol, $this->tickerStreamHandler($json));
                } else {
                    foreach ($json as $obj) {
                        $return = $this->tickerStreamHandler($obj);
                        $symbol = $return['symbol'];
                        call_user_func($callback, $this, $symbol, $return);
                    }
                }
            });
            $ws->on('close', function ($code = null, $reason = null) {
                echo "ticker: WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
            });
        }, function ($e) {
            echo "ticker: Could not connect: {$e->getMessage()}" . PHP_EOL;
        });
    }

    // Pulls /kline data and subscribes to @klines WebSocket endpoint

    /**
     * @param $symbols
     * @param string $interval
     * @param $callback
     */
    public function chart($symbols, $interval = "30m", $callback)
    {
        if (!is_array($symbols)) $symbols = [$symbols];
        $loop = \React\EventLoop\Factory::create();
        $react = new \React\Socket\Connector($loop);
        $connector = new \Ratchet\Client\Connector($loop, $react);
        foreach ($symbols as $symbol) {
            if (!isset($this->charts[$symbol])) $this->charts[$symbol] = [];
            $this->charts[$symbol][$interval] = [];
            if (!isset($this->info[$symbol])) $this->info[$symbol] = [];
            if (!isset($this->info[$symbol][$interval])) $this->info[$symbol][$interval] = [];
            if (!isset($this->chartQueue[$symbol])) $this->chartQueue[$symbol] = [];
            $this->chartQueue[$symbol][$interval] = [];
            $this->info[$symbol][$interval]['firstOpen'] = 0;
            //$this->info[$symbol]['chartCallback'.$interval] = $callback;
            $connector('wss://stream.binance.com:9443/ws/' . strtolower($symbol) . '@kline_' . $interval)->then(function ($ws) use ($callback) {
                $ws->on('message', function ($data) use ($ws, $callback) {
                    $json = json_decode($data);
                    $chart = $json->k;
                    $symbol = $json->s;
                    $interval = $chart->i;
                    $this->chartHandler($symbol, $interval, $json);
                    //$this->info[$symbol]['chartCallback'.$interval]($this, $symbol, $this->charts[$symbol][$interval]);
                    call_user_func($callback, $this, $symbol, $this->charts[$symbol][$interval]);
                });
                $ws->on('close', function ($code = null, $reason = null) {
                    echo "chart({$symbol},{$interval}) WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
                });
            }, function ($e) use ($loop) {
                echo "chart({$symbol},{$interval})) Could not connect: {$e->getMessage()}" . PHP_EOL;
                $loop->stop();
            });
            $this->candlesticks($symbol, $interval);
            foreach ($this->chartQueue[$symbol][$interval] as $json) {
                $this->chartHandler($symbol, $interval, $json);
            }
            $this->chartQueue[$symbol][$interval] = [];
            //$this->info[$symbol]['chartCallback'.$interval]($this, $symbol, $this->charts[$symbol][$interval]);
            call_user_func($callback, $this, $symbol, $this->charts[$symbol][$interval]);
        }
        $loop->run();
    }

    // Keep-alive function for userDataStream

    /**
     *
     */
    public function keepAlive()
    {
        $loop = \React\EventLoop\Factory::create();
        $loop->addPeriodicTimer(30, function () {
            $listenKey = $this->options['listenKey'];
            $this->apiRequest("v1/userDataStream?listenKey={$listenKey}", "PUT");
        });
        $loop->run();
    }

    // Issues userDataStream token and keepalive, subscribes to userData WebSocket

    /**
     * @param $balance_callback
     * @param bool $execution_callback
     */
    public function userData(&$balance_callback, &$execution_callback = false)
    {
        $response = $this->apiRequest("v1/userDataStream", "POST");
        $listenKey = $this->options['listenKey'] = $response['listenKey'];
        $this->info['balanceCallback'] = $balance_callback;
        $this->info['executionCallback'] = $execution_callback;
        \Ratchet\Client\connect('wss://stream.binance.com:9443/ws/' . $listenKey)->then(function ($ws) {
            $ws->on('message', function ($data) use ($ws) {
                $json = json_decode($data);
                $type = $json->e;
                if ($type == "outboundAccountInfo") {
                    $balances = $this->balanceHandler($json->B);
                    $this->info['balanceCallback']($this, $balances);
                } elseif ($type == "executionReport") {
                    $report = $this->executionHandler($json);
                    if ($this->info['executionCallback']) {
                        $this->info['executionCallback']($this, $report);
                    }
                }
            });
            $ws->on('close', function ($code = null, $reason = null) {
                echo "userData: WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
            });
        }, function ($e) {
            echo "userData: Could not connect: {$e->getMessage()}" . PHP_EOL;
        });
    }
}

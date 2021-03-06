<?php

namespace ccxt;

// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

class exmo extends Exchange {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'exmo',
            'name' => 'EXMO',
            'countries' => array ( 'ES', 'RU' ), // Spain, Russia
            'rateLimit' => 350, // once every 350 ms ≈ 180 requests per minute ≈ 3 requests per second
            'version' => 'v1',
            'has' => array (
                'CORS' => false,
                'fetchOrder' => true,
                'fetchOpenOrders' => true,
                'fetchOrderTrades' => true,
                'fetchOrderBooks' => true,
                'fetchMyTrades' => true,
                'fetchTickers' => true,
                'withdraw' => true,
            ),
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/27766491-1b0ea956-5eda-11e7-9225-40d67b481b8d.jpg',
                'api' => 'https://api.exmo.com',
                'www' => 'https://exmo.me',
                'doc' => array (
                    'https://exmo.me/en/api_doc',
                    'https://github.com/exmo-dev/exmo_api_lib/tree/master/nodejs',
                ),
                'fees' => 'https://exmo.com/en/docs/fees',
            ),
            'api' => array (
                'public' => array (
                    'get' => array (
                        'currency',
                        'order_book',
                        'pair_settings',
                        'ticker',
                        'trades',
                    ),
                ),
                'private' => array (
                    'post' => array (
                        'user_info',
                        'order_create',
                        'order_cancel',
                        'user_open_orders',
                        'user_trades',
                        'user_cancelled_orders',
                        'order_trades',
                        'required_amount',
                        'deposit_address',
                        'withdraw_crypt',
                        'withdraw_get_txid',
                        'excode_create',
                        'excode_load',
                        'wallet_history',
                    ),
                ),
            ),
            'fees' => array (
                'trading' => array (
                    'maker' => 0.2 / 100,
                    'taker' => 0.2 / 100,
                ),
                'funding' => array (
                    'withdraw' => array (
                        'BTC' => 0.001,
                        'LTC' => 0.01,
                        'DOGE' => 1,
                        'DASH' => 0.01,
                        'ETH' => 0.01,
                        'WAVES' => 0.001,
                        'ZEC' => 0.001,
                        'USDT' => 25,
                        'XMR' => 0.05,
                        'XRP' => 0.02,
                        'KICK' => 350,
                        'ETC' => 0.01,
                        'BCH' => 0.001,
                    ),
                    'deposit' => array (
                        'USDT' => 15,
                        'KICK' => 50,
                    ),
                ),
            ),
        ));
    }

    public function fetch_markets () {
        $markets = $this->publicGetPairSettings ();
        $keys = is_array ($markets) ? array_keys ($markets) : array ();
        $result = array ();
        for ($p = 0; $p < count ($keys); $p++) {
            $id = $keys[$p];
            $market = $markets[$id];
            $symbol = str_replace ('_', '/', $id);
            list ($base, $quote) = explode ('/', $symbol);
            $result[] = array (
                'id' => $id,
                'symbol' => $symbol,
                'base' => $base,
                'quote' => $quote,
                'limits' => array (
                    'amount' => array (
                        'min' => $market['min_quantity'],
                        'max' => $market['max_quantity'],
                    ),
                    'price' => array (
                        'min' => $market['min_price'],
                        'max' => $market['max_price'],
                    ),
                    'cost' => array (
                        'min' => $market['min_amount'],
                        'max' => $market['max_amount'],
                    ),
                ),
                'precision' => array (
                    'amount' => 8,
                    'price' => 8,
                ),
                'info' => $market,
            );
        }
        return $result;
    }

    public function fetch_balance ($params = array ()) {
        $this->load_markets();
        $response = $this->privatePostUserInfo ();
        $result = array ( 'info' => $response );
        $currencies = is_array ($this->currencies) ? array_keys ($this->currencies) : array ();
        for ($i = 0; $i < count ($currencies); $i++) {
            $currency = $currencies[$i];
            $account = $this->account ();
            if (is_array ($response['balances']) && array_key_exists ($currency, $response['balances']))
                $account['free'] = floatval ($response['balances'][$currency]);
            if (is_array ($response['reserved']) && array_key_exists ($currency, $response['reserved']))
                $account['used'] = floatval ($response['reserved'][$currency]);
            $account['total'] = $this->sum ($account['free'], $account['used']);
            $result[$currency] = $account;
        }
        return $this->parse_balance($result);
    }

    public function fetch_order_book ($symbol, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $request = array_merge (array (
            'pair' => $market['id'],
        ), $params);
        if ($limit !== null)
            $request['limit'] = $limit;
        $response = $this->publicGetOrderBook ($request);
        $result = $response[$market['id']];
        $orderbook = $this->parse_order_book($result, null, 'bid', 'ask');
        return array_merge ($orderbook, array (
            'bids' => $this->sort_by($orderbook['bids'], 0, true),
            'asks' => $this->sort_by($orderbook['asks'], 0),
        ));
    }

    public function fetch_order_books ($symbols = null, $params = array ()) {
        $this->load_markets();
        $ids = null;
        if (!$symbols) {
            $ids = implode (',', $this->ids);
            // max URL length is 2083 $symbols, including http schema, hostname, tld, etc...
            if (strlen ($ids) > 2048) {
                $numIds = is_array ($this->ids) ? count ($this->ids) : 0;
                throw new ExchangeError ($this->id . ' has ' . (string) $numIds . ' $symbols exceeding max URL length, you are required to specify a list of $symbols in the first argument to fetchOrderBooks');
            }
        } else {
            $ids = $this->market_ids($symbols);
            $ids = implode (',', $ids);
        }
        $response = $this->publicGetOrderBook (array_merge (array (
            'pair' => $ids,
        ), $params));
        $result = array ();
        $ids = is_array ($response) ? array_keys ($response) : array ();
        for ($i = 0; $i < count ($ids); $i++) {
            $id = $ids[$i];
            $symbol = $this->find_symbol($id);
            $result[$symbol] = $this->parse_order_book($response[$id], null, 'bid', 'ask');
        }
        return $result;
    }

    public function parse_ticker ($ticker, $market = null) {
        $timestamp = $ticker['updated'] * 1000;
        $symbol = null;
        if ($market)
            $symbol = $market['symbol'];
        $last = floatval ($ticker['last_trade']);
        return array (
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'high' => floatval ($ticker['high']),
            'low' => floatval ($ticker['low']),
            'bid' => floatval ($ticker['buy_price']),
            'ask' => floatval ($ticker['sell_price']),
            'vwap' => null,
            'open' => null,
            'close' => $last,
            'last' => $last,
            'previousClose' => null,
            'change' => null,
            'percentage' => null,
            'average' => floatval ($ticker['avg']),
            'baseVolume' => floatval ($ticker['vol']),
            'quoteVolume' => floatval ($ticker['vol_curr']),
            'info' => $ticker,
        );
    }

    public function fetch_tickers ($symbols = null, $params = array ()) {
        $this->load_markets();
        $response = $this->publicGetTicker ($params);
        $result = array ();
        $ids = is_array ($response) ? array_keys ($response) : array ();
        for ($i = 0; $i < count ($ids); $i++) {
            $id = $ids[$i];
            $market = $this->markets_by_id[$id];
            $symbol = $market['symbol'];
            $ticker = $response[$id];
            $result[$symbol] = $this->parse_ticker($ticker, $market);
        }
        return $result;
    }

    public function fetch_ticker ($symbol, $params = array ()) {
        $this->load_markets();
        $response = $this->publicGetTicker ($params);
        $market = $this->market ($symbol);
        return $this->parse_ticker($response[$market['id']], $market);
    }

    public function parse_trade ($trade, $market) {
        $timestamp = $trade['date'] * 1000;
        return array (
            'id' => (string) $trade['trade_id'],
            'info' => $trade,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'symbol' => $market['symbol'],
            'order' => $this->safe_string($trade, 'order_id'),
            'type' => null,
            'side' => $trade['type'],
            'price' => floatval ($trade['price']),
            'amount' => floatval ($trade['quantity']),
            'cost' => $this->safe_float($trade, 'amount'),
        );
    }

    public function fetch_trades ($symbol, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $response = $this->publicGetTrades (array_merge (array (
            'pair' => $market['id'],
        ), $params));
        return $this->parse_trades($response[$market['id']], $market, $since, $limit);
    }

    public function fetch_my_trades ($symbol = null, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $request = array ();
        $market = null;
        if ($symbol !== null) {
            $market = $this->market ($symbol);
            $request['pair'] = $market['id'];
        }
        $response = $this->privatePostUserTrades (array_merge ($request, $params));
        if ($market !== null)
            $response = $response[$market['id']];
        return $this->parse_trades($response, $market, $since, $limit);
    }

    public function create_order ($symbol, $type, $side, $amount, $price = null, $params = array ()) {
        $this->load_markets();
        if ($type === 'market') {
            $price = '0';
            $type = $type . '_';
        }
        $type .= $side;
        $request = array (
            'pair' => $this->market_id($symbol),
            'quantity' => $amount,
            'type' => $type,
            'price' => $price,
        );
        $response = $this->privatePostOrderCreate (array_merge ($request, $params));
        return array (
            'info' => $response,
            'id' => (string) $response['order_id'],
        );
    }

    public function cancel_order ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        return $this->privatePostOrderCancel (array ( 'order_id' => $id ));
    }

    public function fetch_order ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        $market = null;
        if ($symbol !== null)
            $market = $this->market ($symbol);
        $response = $this->privatePostOrderTrades (array_merge (array ( 'order_id' => $id ), $params));
        return $this->parse_order($response, $market);
    }

    public function fetch_order_trades ($id, $symbol = null, $since = null, $limit = null, $params = array ()) {
        $market = null;
        if ($symbol !== null) {
            $this->load_markets();
            $market = $this->market ($symbol);
        }
        $request = array (
            'order_id' => $id,
        );
        $response = $this->privatePostOrderTrades (array_merge ($request, $params));
        return $this->parse_trades($response['trades'], $market, $since, $limit);
    }

    public function fetch_open_orders ($symbol = null, $since = null, $limit = null, $params = array ()) {
        $market = null;
        if ($symbol !== null) {
            $this->load_markets();
            $market = $this->market ($symbol);
        }
        $orders = $this->privatePostUserOpenOrders ();
        if ($market !== null) {
            $id = $market['id'];
            $orders = (is_array ($orders) && array_key_exists ($id, $orders)) ? $orders[$id] : array ();
        }
        return $this->parse_orders($orders, $market, $since, $limit);
    }

    public function parse_order ($order, $market = null) {
        $id = $this->safe_string($order, 'order_id');
        $timestamp = $this->safe_integer($order, 'created');
        if ($timestamp !== null)
            $timestamp *= 1000;
        $iso8601 = null;
        $symbol = null;
        $side = $this->safe_string($order, 'type');
        if ($market === null) {
            $marketId = null;
            if (is_array ($order) && array_key_exists ('pair', $order)) {
                $marketId = $order['pair'];
            } else if ((is_array ($order) && array_key_exists ('in_currency', $order)) && (is_array ($order) && array_key_exists ('out_currency', $order))) {
                if ($side === 'buy')
                    $marketId = $order['in_currency'] . '_' . $order['out_currency'];
                else
                    $marketId = $order['out_currency'] . '_' . $order['in_currency'];
            }
            if (($marketId !== null) && (is_array ($this->markets_by_id) && array_key_exists ($marketId, $this->markets_by_id)))
                $market = $this->markets_by_id[$marketId];
        }
        $amount = $this->safe_float($order, 'quantity');
        if ($amount === null) {
            $amountField = ($side === 'buy') ? 'in_amount' : 'out_amount';
            $amount = $this->safe_float($order, $amountField);
        }
        $price = $this->safe_float($order, 'price');
        $cost = $this->safe_float($order, 'amount');
        $filled = 0.0;
        $trades = array ();
        $transactions = $this->safe_value($order, 'trades');
        $feeCost = null;
        if ($transactions !== null) {
            if (gettype ($transactions) === 'array' && count (array_filter (array_keys ($transactions), 'is_string')) == 0) {
                for ($i = 0; $i < count ($transactions); $i++) {
                    $trade = $this->parse_trade($transactions[$i], $market);
                    if ($id === null)
                        $id = $trade['order'];
                    if ($timestamp === null)
                        $timestamp = $trade['timestamp'];
                    if ($timestamp > $trade['timestamp'])
                        $timestamp = $trade['timestamp'];
                    $filled .= $trade['amount'];
                    if ($feeCost === null)
                        $feeCost = 0.0;
                    // $feeCost .= $trade['fee']['cost'];
                    if ($cost === null)
                        $cost = 0.0;
                    $cost .= $trade['cost'];
                    $trades[] = $trade;
                }
            }
        }
        if ($timestamp !== null)
            $iso8601 = $this->iso8601 ($timestamp);
        $remaining = null;
        if ($amount !== null)
            $remaining = $amount - $filled;
        $status = $this->safe_string($order, 'status'); // in case we need to redefine it for canceled orders
        if ($filled >= $amount)
            $status = 'closed';
        else
            $status = 'open';
        if ($market === null)
            $market = $this->get_market_from_trades ($trades);
        $feeCurrency = null;
        if ($market !== null) {
            $symbol = $market['symbol'];
            $feeCurrency = $market['quote'];
        }
        if ($cost === null) {
            if ($price !== null)
                $cost = $price * $filled;
        } else if ($price === null) {
            if ($filled > 0)
                $price = $cost / $filled;
        }
        $fee = array (
            'cost' => $feeCost,
            'currency' => $feeCurrency,
        );
        return array (
            'id' => $id,
            'datetime' => $iso8601,
            'timestamp' => $timestamp,
            'status' => $status,
            'symbol' => $symbol,
            'type' => null,
            'side' => $side,
            'price' => $price,
            'cost' => $cost,
            'amount' => $amount,
            'filled' => $filled,
            'remaining' => $remaining,
            'trades' => $trades,
            'fee' => $fee,
            'info' => $order,
        );
    }

    public function get_market_from_trades ($trades) {
        $tradesBySymbol = $this->index_by($trades, 'pair');
        $symbols = is_array ($tradesBySymbol) ? array_keys ($tradesBySymbol) : array ();
        $numSymbols = is_array ($symbols) ? count ($symbols) : 0;
        if ($numSymbols === 1)
            return $this->markets[$symbols[0]];
        return null;
    }

    public function calculate_fee ($symbol, $type, $side, $amount, $price, $takerOrMaker = 'taker', $params = array ()) {
        $market = $this->markets[$symbol];
        $rate = $market[$takerOrMaker];
        $cost = floatval ($this->cost_to_precision($symbol, $amount * $rate));
        $key = 'quote';
        if ($side === 'sell') {
            $cost *= $price;
        } else {
            $key = 'base';
        }
        return array (
            'type' => $takerOrMaker,
            'currency' => $market[$key],
            'rate' => $rate,
            'cost' => floatval ($this->fee_to_precision($symbol, $cost)),
        );
    }

    public function withdraw ($currency, $amount, $address, $tag = null, $params = array ()) {
        $this->load_markets();
        $request = array (
            'amount' => $amount,
            'currency' => $currency,
            'address' => $address,
        );
        if ($tag !== null)
            $request['invoice'] = $tag;
        $result = $this->privatePostWithdrawCrypt (array_merge ($request, $params));
        return array (
            'info' => $result,
            'id' => $result['task_id'],
        );
    }

    public function sign ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $url = $this->urls['api'] . '/' . $this->version . '/' . $path;
        if ($api === 'public') {
            if ($params)
                $url .= '?' . $this->urlencode ($params);
        } else {
            $this->check_required_credentials();
            $nonce = $this->nonce ();
            $body = $this->urlencode (array_merge (array ( 'nonce' => $nonce ), $params));
            $headers = array (
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Key' => $this->apiKey,
                'Sign' => $this->hmac ($this->encode ($body), $this->encode ($this->secret), 'sha512'),
            );
        }
        return array ( 'url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers );
    }

    public function request ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $response = $this->fetch2 ($path, $api, $method, $params, $headers, $body);
        if (is_array ($response) && array_key_exists ('result', $response)) {
            if ($response['result'])
                return $response;
            throw new ExchangeError ($this->id . ' ' . $this->json ($response));
        }
        return $response;
    }
}

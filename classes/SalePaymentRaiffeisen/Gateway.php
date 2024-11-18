<?php
namespace SalePaymentRaiffeisen;

class Gateway extends \Sale\PaymentGateway\GatewayAtol {
	
    const GATEWAY_PRODUCTION = 'https://pay.raif.ru';
    const GATEWAY_TEST = 'https://pay-test.raif.ru';
   
	public static function getInfo2()
	{
		return [
			'name'        => 'Raiffeisen',
			'description' => '',
			'icon'        => '/plugins/sale-payment-raiffeisen/images/icon.png',
			'params' => [	
				[
					'name'       => 'MerchantId',
					'xtype'      => 'textfield',
					'fieldLabel' => 'Идентификатор партнёра*',
					'allowBlank' => false,
				],
				[
					'name'       => 'secretKey',
					'xtype'      => 'textfield',
					'fieldLabel' => 'Секретный ключ',
					'allowBlank' => false,
				],
                [
					'name'       => 'returnURL',
					'xtype'      => 'textfield',
					'fieldLabel' => 'Страница после совершения платежа',
					'allowBlank' => false,
				],	                                     
                [
                    "xtype"          => 'checkbox',
                    "name"           => 'test_mode',
                    "boxLabel"       => 'тестовый режим',
                    "inputValue"     => 1,
                    "uncheckeDvalue" => 0
                ],
                [
                    'name'        =>'callbackUrl',
					'xtype'      => 'displayfield',
					'fieldLabel' => 'URL-адрес для callback уведомлений',
					'value'      => '//'.$_SERVER['HTTP_HOST'].'/cms/plugins/sale-payment-raiffeisen/callback.php'
				]                
			]			
		];
	}
	
	public function pay( $return = '', $fail = '' )
	{
        //https://pay.raif.ru/doc/ecom.html
        if (!$return) $return = \Cetera\Application::getInstance()->getServer()->getFullUrl();
        if (!$fail) $fail = \Cetera\Application::getInstance()->getServer()->getFullUrl();
        try {
            $amount = $this->order->getTotal();
            $orderId = $this->order->id;
            $publicId = $this->params['MerchantId'];
            $successUrl = $this->params['returnURL'];

        if (getenv('RUN_MODE', true) === 'development') {
            $url = self::GATEWAY_TEST;
        }
        else{
            $url = (isset($this->params["test_mode"]) && $this->params["test_mode"])?self::GATEWAY_TEST:self::GATEWAY_PRODUCTION;
        }
        $location=$url.'/pay'.'?publicId='.urlencode($publicId).'&orderId='.urlencode($orderId).'&amount='.urlencode($amount).
        '&successUrl='.urlencode($successUrl).'&paymentMethod=ONLY_ACQUIRING&locale=ru';
        /*file_put_contents(dirname(WWWROOT) .'/history/log.txt',"\n" . date('Y-m-d H:i:s ') . "\n" . $location, FILE_APPEND);*/

        if (isset($amount) && !empty($amount) && isset($orderId) && !empty($orderId)) {
            header('Location: '.$location);
            $data = [
                'order'         => $orderId,
                'amount'        => $amount
            ];
            $this->saveTransaction($orderId, $data);
        }
        
        die();
    } catch (\Exception $e) {
        $response = $e;
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/uploads/logs/raiff.log', date('Y.m.d H:i:s')." ".$_SERVER['QUERY_STRING']." ".$e->getMessage().$e->getFile().$e->getLine().$e->getTraceAsString()."\n", FILE_APPEND);
    }  
	}

    public static function isRefundAllowed() {
        return true;
    }

    public function refund( $items = null ) {
        try {     
            $params = [
                'amount'    => $this->order->getTotal(),
            ];
            $refundId = 'refund'.$this->order->id;
            $oid = $this->order->id;
            if ($items !== null) {
                $amount = 0;
                foreach ($items as $key => $item) {
                    if ($item['quantity_refund'] <= 0) continue;
                    $amount += intval($item['quantity_refund']) * $item['price'];
                }
                $params['amount'] = $amount;
            }
            
            //print_r($params);
            //return;        

            if (getenv('RUN_MODE', true) === 'development') {
                $url = self::GATEWAY_TEST;
            }
            else{
                $url = (isset($this->params["test_mode"]) && $this->params["test_mode"])?self::GATEWAY_TEST:self::GATEWAY_PRODUCTION;
            }
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $url.'/api/payments/v1/orders/'.$oid.'/refunds/'. $refundId, [
                'verify' => false,
                'json' => $params,
                'headers' => [ 'Authorization' => "Bearer ".$this->params['secretKey'] ],
            ]);

            $res = json_decode($response->getBody(), true);

            if (isset($res['code']) && $res['code'] == "SUCCESS") {
                
                $this->saveTransaction($refundId, $res);
                $res = $this->sendReceiptRefund( $items );
                return;		
            }
            else {
                throw new \Exception($res['code'].': '.$res['message']);
            }        
    } catch (\Exception $e) {
        $response = $e;
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/uploads/logs/raiff.log', date('Y.m.d H:i:s')." ".$_SERVER['QUERY_STRING']." ".$e->getMessage().$e->getFile().$e->getLine().$e->getTraceAsString()."\n", FILE_APPEND);
    }  
    } 	

}
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
				],	                
			]			
		];
	}
	
	public function pay( $return = '', $fail = '' )
	{

        $secretKey = $this->params['secretKey'];
        $publicId = $this->params['MerchantId'];
        $client = new \Raiffeisen\Ecom\Client($secretKey, $publicId);

        $amount = $this->order->getTotal();
        $orderId = $this->order->id;
        $query = [
          'successUrl' => $this->params['returnURL'],
        ];
        $client->postCallbackUrl($this->params['callbackUrl']);
        /** @var \Raiffeisen\Ecom\Client $client */
        $link = $client->getPayUrl($amount, $orderId, $query);
        $res = $client->getOrder($orderId);
        $this->saveTransaction($orderId, $res);

        if (isset($link) && !empty($link)) {
            header('Location: '.$link );
        }
        die();
	}

    public static function isRefundAllowed() {
        return true;
    }

    public function refund( $items = null ) {
              
        $orderId = $this->order->id;
        $refundId = 'refund'.$this->order->id;
        $orderAmount = $this->order->getTotal();
        if ($items !== null) {
            $amount = 0;
            foreach ($items as $key => $item) {
                if ($item['quantity_refund'] <= 0) continue;
                $amount += intval($item['quantity_refund']) * $item['price'];
            }
            $orderAmount = $amount;
        }
        /** @var \Raiffeisen\Ecom\Client $client */
        $client->postCallbackUrl($this->params['callbackUrl']);
        $response = $client->postOrderRefund($orderId, $refundId, $orderAmount);


        if (getenv('RUN_MODE', true) === 'development') {
            $url = self::GATEWAY_TEST;
        }
        else{
            $url = (isset($this->params["test_mode"]) && $this->params["test_mode"])?self::GATEWAY_TEST:self::GATEWAY_PRODUCTION;
        }


        $res = json_decode($response->getBody(), true);

		if ($res['code'] == 'SUCCESS') {
            
            $this->saveTransaction($refundId, $res);
            $res = $this->sendReceiptRefund( $items );
            $this->saveTransaction($refundId, $res);
            
			return;		
		}
		else {
            throw new \Exception($res['code'].': '.$res['message']);
		}        
        
    } 	

}
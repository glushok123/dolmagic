<?php

namespace App\Console\Api;

class OzonRocketApi extends Api
{

    public $shopId;
    public $systemId = 3; // OZON = 3

    public $host = 'https://xapi.ozon.ru/principal-integration-api';
    public $clientId = 'ApiUserDollmagic_f0888c38-5f0e-46e0-9740-8a2041979ca7';
    public $clientSecret = 'jiSzS38HfZAY+QCCYb0sfMFETgAaECKYMq4cFQ4uKGA=';
    public $tokenUrl = 'https://xapi.ozon.ru/principal-auth-api/connect/token';
    public $accessToken = ''; // get on getToken


    public function getToken()
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'content-type: application/x-www-form-urlencoded',
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);

        $params = array(
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        );
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            http_build_query($params)
        );
        curl_setopt($ch, CURLOPT_URL, $this->tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        //curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.52 Safari/537.17');
        //curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        //curl_setopt($ch, CURLOPT_HEADER, 1);

        $response = curl_exec($ch);
        //dd(curl_getinfo($ch));

        //dd(curl_error($ch));
        curl_close($ch);

        $res = json_decode($response);
        return $res->access_token;
    }

    public function __construct($showLog = false)
    {
        parent::__construct($showLog);

        $this->accessToken = $this->getToken();

        $this->headers = array(
            'Content-Type: application/json',
            "authorization: Bearer $this->accessToken",
        );
    }

    public function getDeliveryVariants(
        $objectTypeName = ['Самовывоз', 'Постамат'],
        $onlyPrepay = true
    )
    {
        $res = $this->makeRequest(
            'GET',
            '/v1/delivery/variants',
            [
                'payloadIncludes.includeWorkingHours' => 'true',
            ]
        );

        if($res->data)
        {
            foreach($res->data as $key => $ResData)
            {
                if($onlyPrepay)
                {
                    if(!(!$ResData->isCashForbidden or $ResData->cardPaymentAvailable))
                    {
                        unset($res->data[$key]);
                        continue;
                    }
                }

                if(!in_array($ResData->objectTypeName, $objectTypeName))
                {
                    unset($res->data[$key]);
                    continue;
                }
            }

            $res->data = array_values($res->data);
        }

        return $res->data??false;
    }

    public function getDeliveryFromPlaces()
    {
        $res = $this->makeRequest(
            'GET',
            '/v1/delivery/from_places',
        );
        dd($res);
        return $res->places??false;
        /*
          +"places": array:1 [
            0 => {#1279
              +"id": 16187533603000
              +"name": "СТАВРОПОЛЬ_ХАБ_НОВЫЙ"
              +"city": "Ставрополь"
              +"address": "край Ставропольский, г. Ставрополь, ш. Старомарьевское, д. 13"
              +"utcOffset": "UTC+03:00"
            }
          ]

         */
    }

    public function getDeliveryTime($deliveryVariantId)
    {
        $res = $this->makeRequest(
            'GET',
            '/v1/delivery/time',
            [
                'fromPlaceId' => 16187533603000,
                'deliveryVariantId' => $deliveryVariantId
            ]
        );
        return $res->days??false;
    }


    public function getDeliveryVariantById($deliveryVariantId)
    {
        $res = $this->makeRequest(
            'POST',
            '/v1/delivery/variants/byids',
            [
                'ids' => [$deliveryVariantId],
            ]
        );

        if(isset($res->data) and isset($res->data[0]))
        {
            return $res->data[0];
        }else
        {
            return false;
        }

    }



}

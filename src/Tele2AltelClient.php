<?php
namespace lightvoldemar\kassanovaBankApi\Kassanova;

use yii\httpclient\Client;

/**
 * Клиентский класс.
 */
class Tele2AltelClient
{
    /**
     * URL редиректа.
     */
    //public $dataRedirectUrl = "ERROR";

    /**
     * STAN код.
     */
    public $stan;

    /**
     * Код платежа.
     */
    public $sid;

    /**
     * RRN.
     */
    public $rrn;

    /**
     * Аккаунт.
     */
    public $account;

    /**
     * Телефон.
     */
    public $phone;

    /**
     * Сумма.
     */
    public $amount;

    /**
     * Валюта.
     */
    public $currency;

    /**
     * URL успешной транзакции.
     */
    public $returnUrl;

    /**
     * URL не успешной транзакции.
     */
    public $failUrl;

    /**
     * API логин.
     */
    public $apiLogin;

    /**
     * API пароль.
     */
    public $apiPassword;

    /**
     * API URL
     */
    public $apiUrl = '';
    
    /**
     * Доступные валюты (ISO 4217).
     *
     * @var array
     */
    protected $currencyEnum = array(
        840 => 'USD',
        398 => 'KZT',
    );

    /**
     * Доступные языки.
     *
     * @var array
     */
    protected $languageList = array(
        'en' => 'en',
        'ru' => 'ru',
    );

    /**
     * Конфигурация.
     *
     * @var array
     */
    protected $config = array();



    /**
     * Генератор STAN.
     *
     * @return int
     */
    private function genStan() {
        // Генерирование случайного 6-разрядного числа
        $this->stan = mt_rand(100000, 999999);
        return $this->stan;
    }


    /**
     * Открытие сессии.
     *
     * @return mixed
     */
    public function login() {

        $data['function'] = 'bank_login';
        $data['STAN'] = $this->genStan();
        $data['user'] = $this->apiLogin;
        $data['password'] = $this->apiPassword;

        // Получение ответа от сервера
        $result = $this->sendRequest($this->apiUrl,$data);
        return $this->validateResult($result);
    }

    /**
     * Закрытие сессии.
     *
     * @return mixed
     */
    public function logout($sig) {
        $data['function'] = 'bank_exit';
        $data['STAN'] = $this->genStan();
        $data['sig'] = $sig;

        // Получение ответа от сервера
        $result = $this->sendRequest($this->apiUrl,$data);
        return $this->validateResult($result);
    }

    /**
     * Смена пароля.
     *
     * @return mixed
     */
    public function changePass($oldPass,$newPass) {
        $data['function'] = 'bank_change_pwd';
        $data['STAN'] = $this->genStan();
        $data['old_pwd'] = $oldPass;
        $data['new_pwd'] = $newPass;

        // Получение ответа от сервера
        $result =  $this->sendRequest($this->apiUrl,$data);
        return $this->validateResult($result);
    }

    /**
     * Проверка пароля.
     *
     * @return mixed
     */
    public function checkPass($pass) {
        $data['function'] = 'bank_control_pwd';
        $data['STAN'] = $this->genStan();
        $data['password'] = $pass;

        // Получение ответа от сервера
        $result =  $this->sendRequest($this->apiUrl,$data);
        return $this->validateResult($result);
    }

    /**
     * Создание ордера на оплату.
     *
     * @return mixed
     */
    public function payment($payOutId,$sid) {
        $account = $this->account($payOutId);

        $data['function'] = 'bank_payment';
        $data['STAN'] = $this->genStan();
        $data['RRN'] = $account['RRN'];
        $data['sid'] = $sid;
        $data['ACCOUNT'] = $account['ACCOUNT'];
        $data['PHONE'] = $account['PHONE'];
        $data['CURRENCY'] = $this->currency;
        $data['AMOUNT'] = $account['AMOUNT'];
        $data['DATE'] = date("Ymd");;
        $data['TIME'] = date("Hms");
        
        $result = $this->sendRequest($this->apiUrl,$data);
        return $this->validateResult($result);
    }

    /**
     * Получение данных пользователя.
     *
     * @return mixed
     */
    private function account() {
        $data['function'] = 'bank_account';
        $data['STAN'] = $this->genStan();
        $data['RRN'] = $this->rrn;
        $data['sid'] = $this->sid;
        $data['PHONE'] = $this->phone;
        $data['CURRENCY'] = $this->currency;
        $data['AMOUNT'] = $this->amount;

        $result = $this->sendRequest($this->apiUrl,$data);
        return $this->validateResult($result);
    }

    /**
     * Запрос финансовых итогов.
     *
     * @return mixed
     */
    public function totals($sid, $from, $to) {
        $data['function'] = 'bank_totals';
        $data['STAN'] = $this->genStan();
        $data['sid'] = $sid;
        $data['FROM'] = $from;
        $data['TO'] = $to;

        $result = $this->sendRequest($this->apiUrl,$data);
        return $this->validateResult($result);
    }

    /**
     * Запрос генерации отчеов.
     *
     * @return mixed
     */
    public function report($sid, $from, $to) {
        $data['function'] = 'bank_report';
        $data['STAN'] = $this->genStan();
        $data['sid'] = $sid;
        $data['FROM'] = $from;
        $data['TO'] = $to;

        $result = $this->sendRequest($this->apiUrl,$data);
        return $this->validateResult($result);
    }
    
    
    
    
    /**
     * Отправка запроса.
     *
     * @param string $url адрес отправки запроса
     * @param array $data массив данных запроса
     * @return object
     */
    private function sendRequest($url,$data) {

        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('get')
            ->setUrl($url)
            ->setData($data)
            ->send();

        if($response->isOk) {
            return $response;
        }
    }
    
    /**
     * Превращения XML -> Obj
     * @param $string
     * @return object
     */
    private function xmlToObj($xmlString) {
        $oXml = simplexml_load_string($xmlString);
        return $oXml;
    }
    
    
    private function validateResult($result) {
        // XML -> Obj
        $xml = $this->xmlToObj($result);
        if($this->stan == $xml->STAN) {
            return $xml;
        } else {
            return false;
        }
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    /**
     * Возвращает ID валюты.
     *
     * @param  string $key
     * @return null|integer
     */
    public function getCurrencyId($key = 'KZT')
    {
        $types = array_flip($this->currencyEnum);

        return isset($types[$key]) ? $types[$key] : null;
    }

    /**
     * Задает указанный тип валюты.
     *
     * @param  string $key
     */
    public function setCurrency($key = 'KZT')
    {
        $types = array_flip($this->currencyEnum);

        $this->currency = isset($types[$key]) ? $types[$key] : null;
    }

    /**
     * Возвращает ID языка.
     *
     * @param  string $key
     * @return null|integer
     */
    public function getLangId($key = 'ru')
    {
        $types = array_flip($this->langList);

        return isset($types[$key]) ? $types[$key] : null;
    }

    /**
     * Задает указанный тип языка.
     *
     * @param  string $key
     */
    public function setLang($key = 'ru')
    {
        $types = array_flip($this->langList);

        $this->currency = isset($types[$key]) ? $types[$key] : null;
    }

    /**
     * Функция оплаты.
     *
     * @param int $amount сумма плетажа
     * @param int $orderId идентификатор ордера
     * @return boolean
     */
    public function pay($amount,$orderId) {
        $order['order_id'] = $orderId;
        $order['return_url'] = $this->returnUrl;
        $order['fail_url'] = $this->failUrl;
        $result = $this->registerOrder($amount,$orderId);
        $this->dataRedirectUrl = $result['formUrl'];
        $this->dataOrderSig = $result['orderId'];
    }

    /**
     * Регистрация заказа.
     *
     * @param int $amount сумма плетажа
     * @param int $orderId идентификатор ордера
     * @return object
     */
    private function registerOrder($amount,$orderId) {
        $data['amount'] = $amount."00";
        $data['currency'] = $this->currency;
        $data['language'] = $this->language;
        $data['orderNumber'] = $orderId;
        $data['userName'] = $this->apiLogin;
        $data['password'] = $this->apiPassword;
        $data['returnUrl'] = $this->returnUrl;
        $data['failUrl'] = $this->failUrl;

        return $this->sendRequest($this->registerUrl,$data);
    }

    
    private function reverseOrder() {
        $url_key = "reverse";
        /*
         * ?language=ru&
         * orderId=9231a838-ac68-4a3e-bddb-d9781433d852&
         * password=password&
         * userName=userName
         * */

        // Язык системы
        $arr[$this->requestParamsArr['LANG']] = $this->params['lang'];
        // ID заказа
        $arr[$this->requestParamsArr['ORDER_ID']] = '';
        // Логин
        $arr[$this->requestParamsArr['USERNAME']] = $this->params['username'];
        // Пароль
        $arr[$this->requestParamsArr['PASSWORD']] = $this->params['password'];

        $this->sendRequest($url_key,$arr);
    }

    private function refundOrder() {
        $url_key = "refund";

        /*
         * amount=500&
         * currency=643&
         * language=ru&
         * orderId=5e97e3fd-1d20-4b4b-a542-f5995f5e8208&
         * password=password&
         * userName=userName
         * */

        // Сумма возврата

        // Валюта
        //$arr[$this->requestParamsArr['CURRENCY']] = '';
        // Язык системы
        $arr[$this->requestParamsArr['LANG']] = $this->params['lang'];
        // ID заказа
        $arr[$this->requestParamsArr['ORDER_ID']] = '';
        // Логин
        $arr[$this->requestParamsArr['USERNAME']] = $this->params['username'];
        // Пароль
        $arr[$this->requestParamsArr['PASSWORD']] = $this->params['password'];

        // Отправка запроса
        $this->sendRequest($url_key,$arr);
    }

    private function getOrderStatus() {
        $url_key = "getOrderStatus";

        /*
         * orderId=b8d70aa7-bfb3-4f94-b7bb-aec7273e1fce&
         * language=ru&
         * password=password&
         * userName=userName
         * */

        // Язык системы
        $arr[$this->requestParamsArr['LANG']] = $this->params['lang'];
        // ID заказа
        $arr[$this->requestParamsArr['ORDER_ID']] = '';
        // Логин
        $arr[$this->requestParamsArr['USERNAME']] = $this->params['username'];
        // Пароль
        $arr[$this->requestParamsArr['PASSWORD']] = $this->params['password'];

        // Отправка запроса
        $this->sendRequest($url_key,$arr);
    }

    private function getOrderStatusExtended() {
        $url_key = "getOrderStatusExtended";

        /*
         * userName=userName&
         * password=password&
         * orderId=b9054496-c65a-4975-9418-1051d101f1b9&
         * language=ru&
         * merchantOrderNumber=0784sse49d0s134567890
         * */

        // Логин
        $arr[$this->requestParamsArr['USERNAME']] = $this->params['username'];
        // Пароль
        $arr[$this->requestParamsArr['PASSWORD']] = $this->params['password'];
        // ID заказа
        $arr[$this->requestParamsArr['ORDER_ID']] = '';
        // Язык системы
        $arr[$this->requestParamsArr['LANG']] = $this->params['lang'];
        // ID ордера по мерчанту
        $arr[$this->requestParamsArr['MERCHANT_ORDER_NUM']] = '';

        // Отправка запроса
        $this->sendRequest($url_key,$arr);
    }

    private function getLastOrdersForMerchants() {
        $url_key = "getLastOrdersForMerchants";

        /*
         * userName=userName&
         * password=password&
         * language=ru&
         * page=0&
         * size=100&
         * from=20141009160000&
         * to=20141111000000&
         * transactionStates=DEPOSITED,REVERSED&
         * merchants=SevenEightNine&
         * searchByCreatedDate=false
         * */

        // Логин
        $arr[$this->requestParamsArr['USERNAME']] = $this->params['username'];
        // Пароль
        $arr[$this->requestParamsArr['PASSWORD']] = $this->params['password'];
        // Язык системы
        $arr[$this->requestParamsArr['LANG']] = $this->params['lang'];
        // Номер страницы
        $arr[$this->requestParamsArr['PAGE']] = '';
        // Кол-во записей на  странице
        $arr[$this->requestParamsArr['SIZE']] = '';
        // Дата начала
        $arr[$this->requestParamsArr['FROM']] = '';
        // Дата окончания
        $arr[$this->requestParamsArr['TO']] = '';
        // Статусы заказов
        $arr[$this->requestParamsArr['transactionStates']] = '';
        // Список мерчантов
        //$arr[$this->requestParamsArr['merchants']] = '';
        // Использовать дату оплаты или дату создания заказов
        $arr[$this->requestParamsArr['searchByCreatedDate']] = 'false';

        // Отправка запроса
        $this->sendRequest($url_key,$arr);
    }

    private function verifyEnrollment() {
        $url_key = "verifyEnrollment";

        /*
         * userName=userName&
         * password=password&
         * pan=4111111111111111
         * */

        // Логин
        $arr[$this->requestParamsArr['USERNAME']] = $this->params['username'];
        // Пароль
        $arr[$this->requestParamsArr['PASSWORD']] = $this->params['password'];
        // Номер карты
        $arr[$this->requestParamsArr['PAN']] = '';

        // Отправка запроса
        $this->sendRequest($url_key,$arr);
    }

    /**
     * Отправка запроса.
     *
     * @param string $url адрес отправки запроса
     * @param array $data массив данных запроса
     * @return object
     */
    private function sendRequest($url,$data) {

        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('post')
            ->setUrl($url)
            ->setData($data)
            ->send();

        if($response->isOk) {
            return $response;
        }
    }
  
}

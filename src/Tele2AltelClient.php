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
        'USD' => 'USD',
        'KZT' => 'KZT',
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
}

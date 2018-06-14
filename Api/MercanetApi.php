<?php
/*************************************************************************************/
/*      Copyright (c) Franck Allimant, CQFDev                                        */
/*      email : thelia@cqfdev.fr                                                     */
/*      web : http://www.cqfdev.fr                                                   */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE      */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

/**
 * Created by Franck Allimant, CQFDev <franck@cqfdev.fr>
 * Date: 11/06/2018 22:13
 */

namespace Mercanet\Api;

/**
 * Class MercanetApi
 * @package Mercanet\Api
 * @method setMerchantId($string)
 * @method setKeyVersion($string)
 */
class MercanetApi
{
    const TEST = "https://payment-webinit-mercanet.test.sips-atos.com/paymentInit";
    const PRODUCTION = "https://payment-webinit.mercanet.bnpparibas.net/paymentInit";

    const INTERFACE_VERSION = "HP_2.20";
    const INSTALMENT = "INSTALMENT";

    // BYPASS3DS
    const BYPASS3DS_ALL = "ALL";
    const BYPASS3DS_MERCHANTWALLET = "MERCHANTWALLET";

    private $brandsmap = array(
        'ACCEPTGIRO' => 'CREDIT_TRANSFER',
        'AMEX' => 'CARD',
        'BCMC' => 'CARD',
        'BUYSTER' => 'CARD',
        'BANK CARD' => 'CARD',
        'CB' => 'CARD',
        'IDEAL' => 'CREDIT_TRANSFER',
        'INCASSO' => 'DIRECT_DEBIT',
        'MAESTRO' => 'CARD',
        'MASTERCARD' => 'CARD',
        'MASTERPASS' => 'CARD',
        'MINITIX' => 'OTHER',
        'NETBANKING' => 'CREDIT_TRANSFER',
        'PAYPAL' => 'CARD',
        'PAYLIB' => 'CARD',
        'REFUND' => 'OTHER',
        'SDD' => 'DIRECT_DEBIT',
        'SOFORT' => 'CREDIT_TRANSFER',
        'VISA' => 'CARD',
        'VPAY' => 'CARD',
        'VISA ELECTRON' => 'CARD',
        'CBCONLINE' => 'CREDIT_TRANSFER',
        'KBCONLINE' => 'CREDIT_TRANSFER'
    );

    private $secretKey;

    private $pspURL = self::TEST;

    private $parameters = array();

    private $pspFields = array(
        'amount', 'currencyCode', 'merchantId', 'normalReturnUrl',
        'transactionReference', 'keyVersion', 'paymentMeanBrand', 'customerLanguage',
        'billingAddress.city', 'billingAddress.company', 'billingAddress.country',
        'billingAddress', 'billingAddress.postBox', 'billingAddress.state',
        'billingAddress.street', 'billingAddress.streetNumber', 'billingAddress.zipCode',
        'billingContact.email', 'billingContact.firstname', 'billingContact.gender',
        'billingContact.lastname', 'billingContact.mobile', 'billingContact.phone',
        'customerAddress', 'customerAddress.city', 'customerAddress.company',
        'customerAddress.country', 'customerAddress.postBox', 'customerAddress.state',
        'customerAddress.street', 'customerAddress.streetNumber', 'customerAddress.zipCode',
        'customerContact', 'customerContact.email', 'customerContact.firstname',
        'customerContact.gender', 'customerContact.lastname', 'customerContact.mobile',
        'customerContact.phone', 'customerContact.title', 'expirationDate', 'automaticResponseUrl',
        'templateName','paymentMeanBrandList', 'instalmentData.number', 'instalmentData.datesList',
        'instalmentData.transactionReferencesList', 'instalmentData.amountsList', 'paymentPattern',
        'captureDay', 'fraudData.bypass3DS'
    );

    private $requiredFields = array(
        'amount', 'currencyCode', 'merchantId', 'normalReturnUrl',
        'transactionReference', 'keyVersion'
    );

    public $allowedlanguages = array(
        'nl', 'fr', 'de', 'it', 'es', 'cy', 'en'
    );

    private static $currencies = array(
        'EUR' => '978', 'USD' => '840', 'CHF' => '756', 'GBP' => '826',
        'CAD' => '124', 'JPY' => '392', 'MXP' => '484', 'TRY' => '949',
        'AUD' => '036', 'NZD' => '554', 'NOK' => '578', 'BRC' => '986',
        'ARP' => '032', 'KHR' => '116', 'TWD' => '901', 'SEK' => '752',
        'DKK' => '208', 'KRW' => '410', 'SGD' => '702', 'XPF' => '953',
        'XOF' => '952'
    );

    public static function convertCurrencyToCurrencyCode($currency)
    {
        if(!in_array($currency, array_keys(self::$currencies)))
            throw new \InvalidArgumentException("Unknown currencyCode $currency.");
        return self::$currencies[$currency];
    }

    public static function convertCurrencyCodeToCurrency($code)
    {
        if(!in_array($code, array_values(self::$currencies)))
            throw new \InvalidArgumentException("Unknown Code $code.");
        return array_search($code, self::$currencies);
    }

    public static function getCurrencies()
    {
        return self::$currencies;
    }

    public function __construct($secret)
    {
        $this->secretKey = $secret;
    }

    public function shaCompose(array $parameters)
    {
        // compose SHA string
        $shaString = '';
        foreach($parameters as $key => $value) {
            $shaString .= $key . '=' . $value;
            $shaString .= (array_search($key, array_keys($parameters)) != (count($parameters)-1)) ? '|' : $this->secretKey;
        }

        return hash('sha256', $shaString);
    }

    /** @return string */
    public function getShaSign()
    {
        $this->validate();
        return $this->shaCompose($this->toArray());
    }

    /** @return string */
    public function getUrl()
    {
        return $this->pspURL;
    }

    public function setUrl($pspUrl)
    {
        $this->validateUri($pspUrl);
        $this->pspURL = $pspUrl;
    }

    public function setNormalReturnUrl($url)
    {
        $this->validateUri($url);
        $this->parameters['normalReturnUrl'] = $url;
    }

    public function setAutomaticResponseUrl($url)
    {
        $this->validateUri($url);
        $this->parameters['automaticResponseUrl'] = $url;
    }

    public function setTransactionReference($transactionReference)
    {
        if(preg_match('/[^a-zA-Z0-9_-]/', $transactionReference)) {
            throw new \InvalidArgumentException("TransactionReference cannot contain special characters");
        }
        $this->parameters['transactionReference'] = $transactionReference;
    }

    /**
     * Set amount in cents, eg EUR 12.34 is written as 1234
     * @param $amount
     */
    public function setAmount($amount)
    {
        if(!is_int($amount)) {
            throw new \InvalidArgumentException("Integer expected. Amount is always in cents");
        }
        if($amount <= 0) {
            throw new \InvalidArgumentException("Amount must be a positive number");
        }
        $this->parameters['amount'] = $amount;

    }

    public function setCurrency($currency)
    {
        if(!array_key_exists(strtoupper($currency), self::getCurrencies())) {
            throw new \InvalidArgumentException("Unknown currency");
        }
        $this->parameters['currencyCode'] = self::convertCurrencyToCurrencyCode($currency);
    }

    public function setLanguage($language)
    {
        if(!in_array($language, $this->allowedlanguages)) {
            throw new \InvalidArgumentException("Invalid language locale");
        }
        $this->parameters['customerLanguage'] = $language;
    }

    public function setPaymentBrand($brand)
    {
        $this->parameters['paymentMeanBrandList'] = '';
        if(!array_key_exists(strtoupper($brand), $this->brandsmap)) {
            throw new \InvalidArgumentException("Unknown Brand [$brand].");
        }
        $this->parameters['paymentMeanBrandList'] = strtoupper($brand);
    }

    public function setCustomerContactEmail($email)
    {
        if(strlen($email) > 50) {
            throw new \InvalidArgumentException("Email is too long");
        }
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Email is invalid");
        }
        $this->parameters['customerContact.email'] = $email;
    }

    public function setBillingContactEmail($email)
    {
        if(strlen($email) > 50) {
            throw new \InvalidArgumentException("Email is too long");
        }
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Email is invalid");
        }
        $this->parameters['billingContact.email'] = $email;
    }

    public function setBillingAddressStreet($street)
    {
        if(strlen($street) > 35) {
            throw new \InvalidArgumentException("street is too long");
        }
        $this->parameters['billingAddress.street'] = \Normalizer::normalize($street);
    }

    public function setBillingAddressStreetNumber($nr)
    {
        if(strlen($nr) > 10) {
            throw new \InvalidArgumentException("streetNumber is too long");
        }
        $this->parameters['billingAddress.streetNumber'] = \Normalizer::normalize($nr);
    }

    public function setBillingAddressZipCode($zipCode)
    {
        if(strlen($zipCode) > 10) {
            throw new \InvalidArgumentException("zipCode is too long");
        }
        $this->parameters['billingAddress.zipCode'] = \Normalizer::normalize($zipCode);
    }

    public function setBillingAddressCity($city)
    {
        if(strlen($city) > 25) {
            throw new \InvalidArgumentException("city is too long");
        }
        $this->parameters['billingAddress.city'] = \Normalizer::normalize($city);
    }

    public function setBillingContactPhone($phone)
    {
        if(strlen($phone) > 30) {
            throw new \InvalidArgumentException("phone is too long");
        }
        $this->parameters['billingContact.phone'] = $phone;
    }

    public function setBillingContactFirstname($firstname)
    {
        $this->parameters['billingContact.firstname'] = str_replace(array("'", '"'), '', \Normalizer::normalize($firstname)); // replace quotes
    }

    public function setBillingContactLastname($lastname)
    {
        $this->parameters['billingContact.lastname'] = str_replace(array("'", '"'), '', \Normalizer::normalize($lastname)); // replace quotes
    }

    public function setCaptureDay($number)
    {
        if (strlen($number) > 2) {
            throw new \InvalidArgumentException("captureDay is too long");
        }
        $this->parameters['captureDay'] = $number;
    }

    // Methodes liees a la lutte contre la fraude

    public function setFraudDataBypass3DS($value)
    {
        if(strlen($value) > 128) {
            throw new \InvalidArgumentException("fraudData.bypass3DS is too long");
        }
        $this->parameters['fraudData.bypass3DS'] = $value;
    }

    // Methodes liees au paiement one-click

    public function setMerchantWalletId($wallet)
    {
        if(strlen($wallet) > 21) {
            throw new \InvalidArgumentException("merchantWalletId is too long");
        }
        $this->parameters['merchantWalletId'] = $wallet;
    }

    // instalmentData.number instalmentData.datesList instalmentData.transactionReferencesList instalmentData.amountsList paymentPattern

    // Methodes liees au paiement en n-fois

    public function setInstalmentDataNumber($number)
    {
        if (strlen($number) > 2) {
            throw new \InvalidArgumentException("instalmentData.number is too long");
        }
        if ( ($number < 2) || ($number > 50) ) {
            throw new \InvalidArgumentException("instalmentData.number invalid value : value must be set between 2 and 50");
        }
        $this->parameters['instalmentData.number'] = $number;
    }

    public function setInstalmentDatesList($datesList)
    {
        $this->parameters['instalmentData.datesList'] = $datesList;
    }

    public function setInstalmentDataTransactionReferencesList($transactionReferencesList)
    {
        $this->parameters['instalmentData.transactionReferencesList'] = $transactionReferencesList;
    }

    public function setInstalmentDataAmountsList($amountsList)
    {
        $this->parameters['instalmentData.amountsList'] = $amountsList;
    }

    public function setPaymentPattern($paymentPattern)
    {
        $this->parameters['paymentPattern'] = $paymentPattern;
    }

    public function __call($method, $args)
    {
        if(substr($method, 0, 3) == 'set') {
            $field = lcfirst(substr($method, 3));
            if(in_array($field, $this->pspFields)) {
                $this->parameters[$field] = $args[0];
                return;
            }
        }

        if(substr($method, 0, 3) == 'get') {
            $field = lcfirst(substr($method, 3));
            if(array_key_exists($field, $this->parameters)) {
                return $this->parameters[$field];
            }
        }

        throw new \BadMethodCallException("Unknown method $method");
    }

    public function toArray()
    {
        return $this->parameters;
    }

    public function toParameterString()
    {
        $parameterString = "";
        foreach($this->parameters as $key => $value) {
            $parameterString .= $key . '=' . $value;
            $parameterString .= (array_search($key, array_keys($this->parameters)) != (count($this->parameters)-1)) ? '|' : '';
        }

        return $parameterString;
    }

    public function validate()
    {
        foreach($this->requiredFields as $field) {
            if(empty($this->parameters[$field])) {
                throw new \RuntimeException($field . " can not be empty");
            }
        }
    }

    protected function validateUri($uri)
    {
        if(!filter_var($uri, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Uri is not valid");
        }
        if(strlen($uri) > 200) {
            throw new \InvalidArgumentException("Uri is too long");
        }
    }

    // Traitement des reponses de Mercanet
    // -----------------------------------

    /** @var string */
    const SHASIGN_FIELD = "SEAL";

    /** @var string */
    const DATA_FIELD = "DATA";

    public function setResponse(array $httpRequest)
    {
        // use lowercase internally
        $httpRequest = array_change_key_case($httpRequest, CASE_UPPER);

        // set sha sign
        $this->shaSign = $this->extractShaSign($httpRequest);

        // filter request for Sips parameters
        $this->parameters = $this->filterRequestParameters($httpRequest);
    }

    /**
     * @var string
     */
    private $shaSign;

    private $dataString;

    /**
     * Filter http request parameters
     * @param array $httpRequest
     * @return array
     */
    private function filterRequestParameters(array $httpRequest)
    {
        //filter request for Sips parameters
        if(!array_key_exists(self::DATA_FIELD, $httpRequest) || $httpRequest[self::DATA_FIELD] == '') {
            throw new \InvalidArgumentException('Data parameter not present in parameters.');
        }
        $parameters = array();
        $dataString = $httpRequest[self::DATA_FIELD];
        $this->dataString = $dataString;
        $dataParams = explode('|', $dataString);
        foreach($dataParams as $dataParamString) {
            $dataKeyValue = explode('=',$dataParamString,2);
            $parameters[$dataKeyValue[0]] = $dataKeyValue[1];
        }

        return $parameters;
    }

    public function getSeal()
    {
        return $this->shaSign;
    }

    private function extractShaSign(array $parameters)
    {
        if(!array_key_exists(self::SHASIGN_FIELD, $parameters) || $parameters[self::SHASIGN_FIELD] == '') {
            throw new \InvalidArgumentException('SHASIGN parameter not present in parameters.');
        }
        return $parameters[self::SHASIGN_FIELD];
    }

    /**
     * Checks if the response is valid
     * @return bool
     */
    public function isValid()
    {
        return $this->shaCompose($this->parameters) == $this->shaSign;
    }

    /**
     * Retrieves a response parameter
     * @param string $key
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function getParam($key)
    {
        if(method_exists($this, 'get'.$key)) {
            return $this->{'get'.$key}();
        }

        // always use uppercase
        $key = strtoupper($key);
        $parameters = array_change_key_case($this->parameters,CASE_UPPER);
        if(!array_key_exists($key, $parameters)) {
            throw new \InvalidArgumentException('Parameter ' . $key . ' does not exist.');
        }

        return $parameters[$key];
    }

    /**
     * @return int Amount in cents
     */
    public function getAmount()
    {
        $value = trim($this->parameters['amount']);
        return (int) ($value);
    }

    public function isSuccessful()
    {
        return in_array($this->getParam('RESPONSECODE'), array("00", "60"));
    }

    public function getDataString()
    {
        return $this->dataString;
    }
}

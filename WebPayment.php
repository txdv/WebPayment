<?

require_once('WebPaymentInfo.php');

abstract class WebPayment
{
  /**
    * WebToPay protocol version.
    */
  const VERSION = '1.4';

  /**
    * Server URL where all requests should go.
    */
  const PAY_URL = 'https://www.mokejimai.lt/pay/';

  /**
    * Server URL where we can get the XML with payment method data.
    */
  const XML_URL = 'https://www.mokejimai.lt/new/lt/lib_web_to_pays/api/';

  /**
    * SMS answer url.
    */
  const SMS_ANSWER_URL = 'https://www.mokejimai.lt/psms/respond/';

  /**
    * Prefix for callback data.
    */
  const PREFIX = 'wp_';

  /**
    * Response confirmation types.
    */
  const CONFIRM_SS2    = 1;
  const CONFIRM_SS1    = 2;
  const CONFIRM_FAILED = 3;

  /**
   * If true, check SS2 if false, skip to SS1
   */
  private static $SS2 = true;

  /**
    * Toggle SS2 checking. Usualy you don't need to use this method, because
    * by default first SS2 support are checked and if it doesn't work,
    * it falls back to to SS1.
    *
    * Use this method if your server supports SS2, but you want to use SS1.
    */
  public static function toggleSS2($value) {
    self::$SS2 = (bool)$value;
  }

  /**
    * Check if SS2 checking is available and enabled.
    *
    * @return bool
    */
  public static function useSS2() {
    return self::$SS2 && function_exists('openssl_pkey_get_public');
  }

  /**
    * Check for SS1, which is not depend on openssl functions.
    *
    * @param  array  $response
    * @param  string $passwd
    * @param  int    $orderid
    * @return bool
    */
  public static function checkSS1($response, $passwd, $orderid) {
    if (strlen($passwd) != 32) {
      $passwd = md5($passwd);
    }

    $_SS1 = array(
      $passwd,
      $orderid,
      intval($response['test']),
      1
    );

    $_SS1 = implode('|', $_SS1);

    return $response['_ss1'] == md5($_SS1);
  }

  private static function getUrlContent($URL) {

    $url = parse_url($URL);

    if ($url['scheme'] == 'https') {
      $host = 'ssl://'.$url['host'];
      $port = 443;
    } else {
      $host = $url['host'];
      $port = 80;
    }

    try {
      $fp = fsockopen($host, $port, $errno, $errstr, 30);
      if (!$fp) {
        throw new Exception('Can\'t connect to %s');
      }

      if (isset($url['query'])) {
        $data = $url['path'] . '?' . $url['query'];
      } else {
        $data = $url['path'];
      }

      $out = "GET " . $data . " HTTP/1.1\r\n";
      $out .= "Host: " . $url['host'] . "\r\n";
      $out .= "Connection: Close\r\n\r\n";

      $content = '';

      fwrite($fp, $out);
      while (!feof($fp)) {
        $content .= fgets($fp, 8192);
      }
      fclose($fp);

      list($header, $content) = explode("\r\n\r\n", $content, 2);

      return trim($content);

    } catch (Exception $e) {
      throw new Exception('fsockopen fail!');
    }
  }

  /**
    * A variable containing the certificate used for SS2 checking.
    */
  private static $certificate = null;

  public static function setCertificate($certificate) {
    self::$certificate = $certificate;
  }

  public static function getCertificate() {
    return self::$certificate;
  }

  /**
    * Download certificate from webtopay.com.
    *
    * @param  string $cert
    * @return string
    */
  public static function setCertificateFromWeb($web = 'http://downloads.webtopay.com/download/', $cert = 'public.key') {
    try {
      self::setCertificate(self::getUrlContent($web . $cert));
      return self::getCertificate() != null;
    } catch (Exception $e) {
      return false;
    }
  }

  /**
    * Load certificate from disk.
    *
    * @param  string path
    @ @return bool
    */
  public static function setCertificateFromDisk($path) {
    // TODO: implement
  }

  /**
    * Check if the saved certificate is valid.
    *
    * @return bool
    */
  public static function validCertificate() {
    self::getCertificate() != null;
  }

  /**
    * Check if the response certificate is valid.
    */
  public static function checkSS2($response) {
    $_SS2 = '';

    foreach ($response as $key => $value) {
      if ($key != '_ss2') $_SS2 .= "{$value}|";
    }

    $ok = openssl_verify($_SS2, base64_decode($response['_ss2']), self::getCertificate());

    return $ok === 1;
  }

  /**
    * Checks and validates response from WebToPay server.
    *
    * This function accepts both mikro and makro responses.
    *
    * First parameter usualy should by $_GET array.
    *
    * Description about response can be found here:
    * makro: https://www.mokejimai.lt/makro_specifikacija.html
    * mikro: https://www.mokejimai.lt/mikro_mokejimu_specifikacija_SMS.html
    *
    * If response is not correct, WebToPayException will be raised.
    *
    * @param array     $response       Response array.
    * @param array     $user_data
    * @return int                      Used response type (CONFIRM_SS2, CONFIRM_SS1, CONFIRM_FAILED)
    */
  public static function checkResponse($response, $userData) {
    $orderid  = $response['id'];
    $password = (isset($userData['sign_password']) ? $userData['sign_password'] : '');

    // Use SS2 if possible
    // the certificate must be checked, if it is not presented then it has
    // to be loaded successfuly from the web
    if (self::useSS2() && (self::validCertificate() || self::setCertificateFromWeb())) {

      // Verify the data.
      if (self::checkSS2($response)) {
        // Hooray, everything is a
        return self::CONFIRM_SS2;
      }
    } else if (self::checkSS1($response, $password, $orderid)) {
      // at least our back up method works!
      return self::CONFIRM_SS1;
    } else {
      // All attempts to verify the data failed.
      return self::CONFIRM_FAILED;
    }
  }

  /**
    * Array of ReflectionClasses for the AbstractMicroWebPayment
    * derived classes.
    */
  protected static $classes = array();

  /**
    * Register a class derived from AbstractMicroWebPayment
    * in order to automatically create the appropriate object
    * with create if the appropriate WebPaymentInfo matches
    * the data.
    *
    * @param className the class name of the class you want to register
    * @return          boolean, true for successful registering, false for non
    */
  public static function register($className) {
    if (!is_subclass_of($className, 'AbstractMicroWebPayment')) {
      return false;
    }
    array_push(self::$classes, new ReflectionClass($className));
    return true;
  }

  /**
    * Returns an instance of the ReflectionClass searching
    * by class name.
    *
    * @return ReflectionClass|null
    */
  private static function getRegistered($className) {
    foreach (self::$classes as $class) {
      if ($class->getName() == $className) {
        return $class;
      }
    }
    return null;
  }

  /**
    * Remove prefixes from an array.
    *
    * @param data   an array containing the data with the prefixes.
    * @param prefix the prefix as a string that should be removed
    * @return       an array without the prefixes
    */
  public static function getPrefixed($data, $prefix = self::PREFIX) {

    if (empty($prefix)) {
      return $data;
    }

    $len = strlen($prefix);

    $ret = array();
    foreach ($data as $key => $val) {
      if (strpos($key, $prefix) === 0 && strlen($key) > $len) {
        $ret[substr($key, $len)] = $val;
      }
    }
    return $ret;
  }

  /**
    * Factory method for creating the appropriate
    * class objects for different responses.
    * One can register classes derived from AbstractMicroWebPayment
    * and override the getKey function in order to make this function
    * automatically return the appropriate type.
    *
    * @param response The response of the website which to use
    *                 to create the according object.
    * @param userData array for user data, like password, etc.
    * @return         An instance of the appropriate object.
    *
    */
  public static function create($response, $userData = array()) {
    $response = self::getPrefixed($response);

    $verificationCode = self::checkResponse($response, $userData);

    if ($verificationCode == self::CONFIRM_FAILED) {
      throw new Exception('Verfication failed');
    }

    if (MicroWebPayment::equals($response)) {
      list($className, $info) = AbstractMicroWebPayment::check($response);

      $class = self::getRegistered($className);

      if ($class) {
        return $class->newInstance($response, $info);
      }

      return new MicroWebPayment($response, $info);
    }
    return null;
  }

  /**
    * An abstract method which should check if the response
    * fits the actual class so the factory method can create
    * object instances according to this method.
    * This method just makes a simple check of the data fields
    * without verifying the data, use getResponseSpec() in order
    * to check the data more thoroughly.
    *
    * @param response 
    * @return         bool
    */
  abstract static function equals($response);

  /**
    * This methdod should return the response specification
    * which is used in order to check if the response data conforms
    * to the specification.
    *
    * @return array of fields and constraints.
    */
  abstract static function getResponseSpec();

  protected $test;
  public function isTest() {
    return $this->test;
  }

  public function __construct($response) {
    $this->test = isset($response['test']);
  }
}

class MicroWebPayment extends WebPayment
{
  public static function equals($response) {
    return (isset($response['to'])   &&
            isset($response['from']) &&
            isset($response['sms'])  &&
            !isset($response['projectid']));
  }

  public static function getResponseSpec() {
    // Array structure:
    //  * name       – request item name.
    //  * maxlen     – max allowed value for item.
    //  * required   – is this item is required in response.
    //  * mustcheck  – this item must be checked by user.
    //  * isresponse – if false, item must not be included in response array.
    //  * regexp     – regexp to test item value.
    return array(
             'to'            => array(0,      true,   false,  true,  ''),
             'sms'           => array(0,      true,   false,  true,  ''),
             'from'          => array(0,      true,   false,  true,  ''),
             'operator'      => array(0,      true,   false,  true,  ''),
             'amount'        => array(0,      true,   false,  true,  ''),
             'currency'      => array(0,      true,   false,  true,  ''),
             'country'       => array(0,      true,   false,  true,  ''),
             'id'            => array(0,      true,   false,  true,  ''),
             '_ss2'          => array(0,      true,   false,  true,  ''),
             '_ss1'          => array(0,      true,   false,  true,  ''),
             'test'          => array(0,      true,   false,  true,  ''),
             'key'           => array(0,      true,   false,  true,  ''),
             //'version'       => array(9,      true,   false,  true,  '/^\d+\.\d+$/'),
    );
  }

  protected $to;
  public function getTo() {
    return $this->to;
  }

  protected $from;
  public function getFrom() {
    return $this->from;
  }

  protected $sms;
  public function getSms() {
    return $this->sms;
  }

  protected $operator;
  public function getOperator() {
    return $this->operator;
  }

  protected $amount;
  public function getAmount() {
    return $this->amount;
  }

  protected $currency;
  public function getCurrency() {
    return $this->currency;
  }

  protected $country;
  public function getCountry() {
    return $this->country;
  }

  protected $id;
  public function getId() {
    return $this->id;
  }

  protected $_ss1;
  public function getSS1() {
    return $this->$_ss1;
  }

  protected $_ss2;
  public function getSS2() {
    return $this->$_ss2;
  }

  protected $key;
  public function getKey() {
    return $this->key;
  }

  public function __construct($response) {
    parent::__construct($response);
    $this->to       = $response['to'];
    $this->from     = $response['from'];
    $this->sms      = $response['sms'];
    $this->operator = $response['operator'];
    $this->amount   = $response['amount'];
    $this->currency = $response['currency'];
    $this->country  = $response['country'];
    $this->id       = $response['id'];
    $this->_ss1     = $response['_ss1'];
    $this->_ss2     = $response['_ss2'];
    $this->key      = $response['key'];
  }
}

/**
  * Classes derived from this class should symbolize a
  * MicroWebPayment Transaction. Since you have different
  * countries from which you can buy the same product,
  * one just associates this his own derived class
  * with some WebPaymentInfo's, registers it with
  * WebPayment and then get's them automatically created
  * with create.
  */
abstract class AbstractMicroWebPayment extends MicroWebPayment
{
  protected static $infolist = array();

  abstract static function who();

  /**
    * Assosciate some WebPaymentInfo with a class.
    */
  public static function registerInfo($number) {
    if (gettype($number) != 'object' || get_class($number) != 'WebPaymentInfo') {
      if (gettype($number) == 'array' && sizeof($number) == WebPaymentInfo::CONSTRUCT_ARG_COUNT) {
        return self::registerInfo(new WebPaymentInfo($number));
      }
      return false;
    }

    $who = static::who();

    if (!isset(self::$infolist[$who])) {
      self::$infolist[$who] = array();
    }

    array_push(self::$infolist[$who], $number);
    return true;
  }

  /*
   * Register an array of WebPaymentInfo or array of array containg
   * the the information needed for WebPaymentInfo.
   */
  public static function registerInfoList($list) {
    foreach ($list as $info) {
      self::registerInfo($info);
    }
  }

  /**
    * Go through all registered payment types and
    * check if one of them fits the response.
    */
  public static function check($response) {
    foreach (self::$infolist as $class => $infolist) {
      foreach ($infolist as $info) {
        if ($info->checkResponse($response)) {
          return array($class, $info);
        }
      }
    }
    return null;
  }

  protected $paymentInfo;
  public function getPaymentInfo() {
    return $this->paymentInfo;
  }

  public static function getPaymentInfoList() {
    return self::$infolist[static::who()];
  }

  /*
   * Returns the message without they keywoard in front
   * and trailing/leading spaces.
   *
   * @return string, the message.
   */
  public function getMainMessage() {
    return trim(substr($this->getSms(), strlen($this->getKey())));
  }

  public function __construct($response, $paymentInfo) {
    parent::__construct($response);
    $this->paymentInfo = $paymentInfo;
  }

  /*
   * Returns all countrycodes in a single array
   *
   * @return array of country codes
   */
  public static function getCountryCodes() {
    $countries = array();
    $who = static::who();
    foreach (self::$infolist[$who] as $obj) {
      array_push($countries, $obj->getCC2());
    }
    return $countries;
  }

  public static function getByCC($cc) {
    $who = static::who();
    foreach (self::$infolist[$who] as $obj) {
      if ($obj->getCC2() == $cc) {
        return $obj;
      }
    }
    return $obj;
  }
}

?>

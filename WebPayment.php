<?

abstract class WebPayment
{
  /**
    * Prefix for callback data.
    */
  const Prefix = 'wp_';

  protected static $prefixes = array();

  /**
    * Register a class derived from PrefixMicroWebPayment
    * in order to automatically create the appropriate object
    * with create if the prefix matches
    *
    * @param className the class name of the class you want to register
    * @return          boolean, true for successful registering, false for non
    */
  public static function register($className) {
    if (!is_subclass_of($className, 'PrefixMicroWebPayment')) {
      return false;
    }
    //array_push(self::$prefixes, $className);
    //self::$prefixes[$className] = new ReflectionClass($className);
    array_push(self::$prefixes, new ReflectionClass($className));
    return true;
  }

  /**
    * Remove prefixes from an array.
    *
    * @param data   an array containing the data with the prefixes.
    * @param prefix the prefix as a string that should be removed
    * @return       an array without the prefixes
    */
  public static function getPrefixed($data, $prefix = self::Prefix) {

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

  protected $test;
  public function isTest() {
    return $this->test;
  }
  
  public function __construct($response) {
    $this->test = isset($response['test']);
  }

  /**
    * Factory method for creating the appropriate
    * class objects for different responses.
    * One can register classes derived from PrefixMicroWebPayment
    * and override the getKey function in order to make this function
    * automatically return the appropriate type.
    *
    * @param response The response of the website which to use
    *                 to create the according object.
    * @return         An instance of the appropriate object.
    *
    */
  public function create($response) {
    $response = self::getPrefixed($response);


    if (MicroWebPayment::equals($response)) {
      // first check registered prefixed microwebpayments
      foreach (self::$prefixes as $class) {
        // This doesn't work for some reason on abstract methods.
        //$method = $class->getMethod('checkValidKey');
        //if ($method && $method->invoke(null, $response['key'])) {
        if (call_user_func(array($class->getName(), 'checkValidKey'), $response['key'])) {
            return $class->newInstance($response);
        }
      }

      // if no found, return the generic one
      return new MicroWebPayment($response);
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
    return $key;
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
    $this->_ss1     = $resposne['_ss1'];
    $this->_ss2     = $response['_ss2'];
    $this->key      = $response['key'];
  }
}

/**
  * A class which should be used in order to derive templates
  * for sms commands.
  *
  * For example, if you have an sms command which uses the
  * keyword "code" and then some 4 digit code afterwards,
  * you would override getPrefix with { return "code"; }
  * and register the class with WebPayment::register.
  * It will automatically check if the prefix fits and
  * return an instance of this class if you call 
  * WebPayment::create on the response of the service.
  */
abstract class PrefixMicroWebPayment extends MicroWebPayment
{
  /*
   * The expected key for the MicroWebPayment.
   * Should be overlooaded and should return
   * a constant string in capital letters for example:
   * { return "CODE"; }
   */
  abstract static function expectKey();

  /*
   * Static me method for checking if the parameter
   * supplied key is the same.
   *
   * @param key the key as a key representation
   @ @return    bool, true if the key fits, false if not
   */
  public static function checkValidKey($key) {
    return strpos($key, static::expectKey()) === 0;
  }

  /*
   * Check if the instance has a valid key.
   *
   * @return bool, true if the key fits, false if not
   */
  public function checkKey() {
    return checkValidKey($this->getSms());
  }

  public function __construct($response) {
    parent::__construct($response);
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
}

?>

<?

class WebPaymentInfo
{
  /**
    * The constructor argument count.
    */
  const CONSTRUCT_ARG_COUNT = 7;

  protected $countryInfo = array(
    'DE' => array(
      'operators' => array('D1', 'E-PLUS', 'TALKLINE', 'D2', 'O2', 'DEBITEL', 'MOBILCOM', 'PHONEHOUSE'),
      'tax' => 1900
    ),
    'LT' => array(
      'operators' => array('TELE2', 'BITE', 'OMNITEL'),
      'tax' => 2100
    )
  );

  /**
    * Returns country info for a particular country
    * or for all countries.
    *
    * @param string cc country code in 2 symbols.
    * @return array information about the country operators.
    */
  public static function countryInfo($cc = null) {
    if ($cc == null) {
      return $this->countryInfo;
    } else {
      return $this->countryInfo[$country];
    }
  }

  protected $info;

  public function getPrefix() {
    return $this->info[0];
  }

  public function getBaseKey() {
    return $this->info[1];
  }

  public function getCC2() {
    return $this->info[2];
  }

  public function getNumber() {
    return $this->info[3];
  }

  public function getCharge() {
    return $this->info[4];
  }

  public function getCurrency() {
    return $this->info[5];
  }

  public function getChargeNoTax() {
    return $this->info[6];
  }

  public function getArray() {
    return $this->info;
  }

  public function isBaseKey($key) {
    return strtolower($this->getBaseKey()) == strtolower($key);
  }

  public function __construct() {
    $args = func_get_args();

    // if we passed an array of arguments, this will flatten it.
    if (sizeof($args) == 1) {
      $args = $args[0];
    }

    if (sizeof($args) != self::CONSTRUCT_ARG_COUNT) {
      throw new Exception('WebPaymentInfo::__contruct(prefix, baseKey, CC2, number, charge, currency, chargeNoTax) has 7 arguments.');
    }

    $this->info = $args;
  }

  /**
    * Returns the prefix and the basekey together.
    *
    * @return the command to send in an sms.
    */
  public function getCommand() {
    return $this->getPrefix() . $this->getBaseKey();
  }

  /**
    * Remove the prefix and return the basekey.
    *
    * @return string
    */
  public function getKey($key) {
    return substr($key, strlen($this->getPrefix()));
  }

  /**
    * Check if the payment type of the WebToPay response
    * is the same as the object instance.
    *
    * @return
    */
  public function checkResponse($response) {
    if ((int)$response['to'] != $this->getNumber()) {
      return false;
    }

    // check if prefix is empty
    if (strlen($this->getPrefix()) > 0) {
      if (strpos($response['key'], $this->getPrefix()) != 0) {
        return false;
      }
    }

    return $this->isBaseKey($this->getKey($response['key']));
  }

  /**
    * Field to return information about a countrfor this
    * particular object instance.
    *
    * @return same as getCountryInfo()
    */
  public function getCountryInfo() {
    return self::countryInfo($this->getCC2());
  }
}

?>

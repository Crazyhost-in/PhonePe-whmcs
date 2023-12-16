<?php	
session_start(); // Start the session
include("../../../init.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");

$url = $_SERVER["SERVER_NAME"];
// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');
// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);
// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}
$merchantTransactionId = $_POST['transactionId'];
// Check if the transaction has already been processed
if (isset($_SESSION['processed']) && $_SESSION['processed'] == $merchantTransactionId) {
    echo "Transaction has already been processed.";
    exit; // Exit if already processed
}
// Gateway Configuration Parameters
    $pmerchantid = $gatewayParams['merchantid'];
    $psaltKey = $gatewayParams['saltKey'];
    $testMode = $gatewayParams['testMode'];
if ($testMode=="on"){
  
        $payurl='https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1/status';
    }
        else{
        
        $payurl='https://api.phonepe.com/apis/hermes/pg/v1/status';
    }
        
// Your Merchant ID and Transaction ID
$merchantId = $pmerchantid;
$saltKey = $psaltKey;
$saltIndex = 1;

// Generating the X-VERIFY header value
$xVerify = hash('sha256', "/pg/v1/status/{$merchantId}/{$merchantTransactionId}" . $saltKey) . "###" . $saltIndex;

// The API URL
$url = "{$payurl}/{$merchantId}/{$merchantTransactionId}";


// Setting up the cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    "X-VERIFY: $xVerify",
    "X-MERCHANT-ID: $merchantId"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Executing the cURL request and capturing the response
$response = curl_exec($ch);

// Closing the cURL session
curl_close($ch);

// Decoding the JSON response
$result = json_decode($response, true);

// Handling the response
if ($result['success'] === true) {
    // Extracting and saving each value in a variable
    $merchantId = $result['data']['merchantId'];
    $merchantTransactionId = $result['data']['merchantTransactionId'];
    $transactionId = $result['data']['transactionId'];
    $amount = $result['data']['amount'] / 100; // Divide the amount by 100
    $state = $result['data']['state'];
    $responseCode = $result['data']['responseCode'];
    $paymentInstrumentType = $result['data']['paymentInstrument']['type'];

    // Echoing common variables
   // echo "Payment Status: " . $state . "<br>";
   // echo "Merchant ID: " . $merchantId . "<br>";
   // echo "Merchant Transaction ID: " . $merchantTransactionId . "<br>";
  //  echo "Transaction ID: " . $transactionId . "<br>";
   // echo "Amount: " . $amount . "<br>";
  //  echo "State: " . $state . "<br>";
   // echo "Response Code: " . $responseCode . "<br>";
  //  echo "Payment Instrument Type: " . $paymentInstrumentType . "<br>";

    // Additional data based on payment instrument type
    switch ($paymentInstrumentType) {
        case "UPI":
            $utr = $result['data']['paymentInstrument']['utr'];
           // echo "UPI Transaction Reference: " . $utr . "<br>";
            break;
        case "CARD":
            $cardType = $result['data']['paymentInstrument']['cardType'];
            $pgTransactionId = $result['data']['paymentInstrument']['pgTransactionId'];
            $bankTransactionId = $result['data']['paymentInstrument']['bankTransactionId'];
            $pgAuthorizationCode = $result['data']['paymentInstrument']['pgAuthorizationCode'];
            $arn = $result['data']['paymentInstrument']['arn'];
            $bankId = $result['data']['paymentInstrument']['bankId'];
            $brn = $result['data']['paymentInstrument']['brn'];
         //   echo "Card Type: " . $cardType . "<br>";
          //  echo "PG Transaction ID: " . $pgTransactionId . "<br>";
          //  echo "Bank Transaction ID: " . $bankTransactionId . "<br>";
          //  echo "PG Authorization Code: " . $pgAuthorizationCode . "<br>";
           // echo "ARN: " . $arn . "<br>";
           // echo "Bank ID: " . $bankId . "<br>";
            //echo "Bank Reference Number: " . $brn . "<br>";
            break;
        case "NETBANKING":
            $pgTransactionId = $result['data']['paymentInstrument']['pgTransactionId'];
            $pgServiceTransactionId = $result['data']['paymentInstrument']['pgServiceTransactionId'];
            $bankTransactionId = $result['data']['paymentInstrument']['bankTransactionId'];
            $bankId = $result['data']['paymentInstrument']['bankId'];
           // echo "PG Transaction ID: " . $pgTransactionId . "<br>";
           // echo "PG Service Transaction ID: " . $pgServiceTransactionId . "<br>";
            //echo "Bank Transaction ID: " . $bankTransactionId . "<br>";
            //echo "Bank ID: " . $bankId . "<br>";
            break;
    }

    $_SESSION['processed'] = $_POST['transactionId']; // Update the session after processing
} else {
    // Failure response handling
    echo "Error: " . $result['message'] . "<br>";

    // Check if data key exists and is not null
    if (isset($result['data']) && $result['data'] != null) {
        $merchantId = $result['data']['merchantId'];
        $merchantTransactionId = $result['data']['merchantTransactionId'];
        $transactionId = $result['data']['transactionId'];
        $amount = $result['data']['amount'] / 100;
        $state = $result['data']['state'];
        $responseCode = $result['data']['responseCode'];
        $responseCodeDescription = $result['data']['responseCodeDescription'] ?? '';

        // Echoing the variables
       // echo "Merchant ID: " . $merchantId . "<br>";
       // echo "Merchant Transaction ID: " . $merchantTransactionId . "<br>";
       // echo "Transaction ID: " . $transactionId . "<br>";
        //echo "Amount: " . $amount . "<br>";
       // echo "State: " . $state . "<br>";
        //echo "Response Code: " . $responseCode . "<br>";
        if (!empty($responseCodeDescription)) {
            //echo "Response Code Description: " . $responseCodeDescription . "<br>";
        }
    }
}
//exit;
// Retrieve data returned in payment gateway callback
// Varies per payment gateway
$invoiceId = $_COOKIE['invoiceid'];
$transactionId = $merchantTransactionId;
$paymentAmount = $amount;
/**
 * Validate callback authenticity.
 *
 * Most payment gateways provide a method of verifying that a callback
 * originated from them. In the case of our example here, this is achieved by
 * way of a shared secret which is used to build and compare a hash.
 */
/**
 * Validate Callback Invoice ID.
 *
 * Checks invoice ID is a valid invoice number. Note it will count an
 * invoice in any status as valid.
 *
 * Performs a die upon encountering an invalid Invoice ID.
 *
 * Returns a normalised invoice ID.
 *
 * @param int $invoiceId Invoice ID
 * @param string $gatewayName Gateway Name
 */
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

/**
 * Check Callback Transaction ID.
 *
 * Performs a check for any existing transactions with the same given
 * transaction number.
 *
 * Performs a die upon encountering a duplicate.
 *
 * @param string $transactionId Unique Transaction ID
 */
checkCbTransID($transactionId);

/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * The debug data can be a string or an array. In the case of an
 * array it will be
 *
 * @param string $gatewayName        Display label
 * @param string|array $debugData    Data to log
 * @param string $transactionStatus  Status
 */
logTransaction($gatewayParams['name'], $_POST, $transactionStatus);

$paymentSuccess = false;

 if($state=="COMPLETED" and $responseCode=="SUCCESS") {

    /**
     * Add Invoice Payment.
     *
     * Applies a payment transaction entry to the given invoice ID.
     *
     * @param int $invoiceId         Invoice ID
     * @param string $transactionId  Transaction ID
     * @param float $paymentAmount   Amount paid (defaults to full balance)
     * @param float $paymentFee      Payment fee (optional)
     * @param string $gatewayModule  Gateway module name
     */
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $paymentAmount,
        "0.0",
        $gatewayModuleName
    );

    $paymentSuccess = true;

}

/**
 * Redirect to invoice.
 *
 * Performs redirect back to the invoice upon completion of the 3D Secure
 * process displaying the transaction result along with the invoice.
 *
 * @param int $invoiceId        Invoice ID
 * @param bool $paymentSuccess  Payment status
 */
callback3DSecureRedirect($invoiceId, $paymentSuccess);

?>

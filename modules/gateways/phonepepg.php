<?php
/**
 * WHMCS PhonePe Payment Gateway Module
 *
 * Payment Gateway modules allow you to integrate payment solutions with the
 * WHMCS platform.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "phonepepg" and therefore all functions
 * begin "phonepepg_".
 *
 * If your module or third party API does not support a given function, you
 * should not define that function within your module. Only the _config
 * function is required.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function phonepepg_MetaData()
{
    return array(
        'DisplayName' => 'PhonePe',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @return array
 */
function phonepepg_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Phonepe',
        ),
        // a text field type allows for single line text input
        'merchantid' => array(
            'FriendlyName' => 'Merchant ID',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Unique MerchantID assigned by PhonePe',
        ),
        // a password field type allows for masked text input
        'saltKey' => array(
            'FriendlyName' => 'Salt Key',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Merchant unique salt key',
        ),
        // the yesno field type displays a single checkbox option
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
        ),
        
    );
}

/**
 * Payment link.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 * @return string
 */
function phonepepg_link($params)
{
    // Gateway Configuration Parameters
    $pmerchantid = $params['merchantid'];
    $psaltKey = $params['saltKey'];
    $testMode = $params['testMode'];

   // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];
    // System Parameters

    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion']; 

    if ($testMode=="on"){        
        $payurl='https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1/pay';
    }    
    else{ $payurl='https://api.phonepe.com/apis/hermes/pg/v1/pay';
    }
    
    $icid = $params['invoiceid'] . '_' . time();

   // Assuming $params['invoiceid'] contains the value you want to store in the cookie
$pgphinvoiceid = $params['invoiceid'];

// Set the cookie with a name, value, and optional parameters like expiration time
setcookie('invoiceid', $pgphinvoiceid, time() + 3600, '/');
    $callback = $systemUrl . '/modules/gateways/phonepe/' . $moduleName . '.php';

// Setting merchant details and unique identifiers
$merchantid= $pmerchantid;  // Unique MerchantID assigned by PhonePe
$merchantTransactionId= uniqid().time().rand(1111,9999);  // Unique transaction ID for tracking
$merchantOrderId=uniqid()."ORDER".rand(111,999).rand(111,999);  // Unique order ID for the transaction
$amount=$params['amount'];  // Transaction amount in currency's smallest unit (paise)
$redirectUrl=$callback;  // URL for redirecting after transaction
$callbackUrl=$callback;  // URL for server to server callback
$shortName="Test name";   // Short name for the transaction
$email=$email;  // Customer email
$mobileNumber=$phone;  // Customer mobile number
$message="Your Custom Message";  // Custom message for the transaction

// Preparing transaction data with details as per PhonePe's API specification
$data = [
    "merchantId" => $merchantid,
    "merchantTransactionId" => $merchantTransactionId,
    "merchantOrderId" => $merchantOrderId,
    "merchantUserId" => "MUID123",  // Unique UserID for the users generated by the merchant
    "amount" => $amount*100,  // Amount should be greater than 100 (in Paise)
    "redirectUrl" => $redirectUrl,
    "redirectMode" => "POST",  // REDIRECT or POST based on merchant's URL capability
    "callbackUrl" => $callbackUrl,  // Callback URL for POST type server to server communication
    "shortName" => $shortName,
    "email" => $email,
    "mobileNumber" => $mobileNumber,
    "message" => $message,
    "paymentInstrument" => [
        "type" => "PAY_PAGE"  // Payment instrument type as PAY_PAGE
    ]
];

// Converting data array to JSON format for payload preparation
$jsonData = json_encode($data);

// Encoding JSON data in base64 format as required by PhonePe API
$base64Data = base64_encode($jsonData);

// Preparing the checksum (X-VERIFY) value for request authentication
$saltKey = $psaltKey;  // Merchant's unique salt key
$saltIndex = 1;  // Merchant's salt index
$checksum = hash('sha256', $base64Data . "/pg/v1/pay" . $saltKey) . "###" . $saltIndex;  // Formula for checksum calculation

// API URL as per PhonePe's documentation
$url = $payurl;  // Sandbox endpoint for the Pay API

// Initializing cURL session for API communication
$ch = curl_init($url);

// Configuring cURL options for POST request as per API requirements
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['request' => $base64Data]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',  // Content type as application/json
    'X-VERIFY: ' . $checksum  // Adding checksum header for request authentication
]);

// Executing the cURL request and capturing the response
$response = curl_exec($ch);

// Closing the cURL session after execution
curl_close($ch);

// Handling the API response
if ($response === false) {
    echo "cURL Error: " . curl_error($ch);
} else {
    // Decoding the JSON response for processing
    $decodedResponse = json_decode($response, true);

    // Checking if the response indicates successful payment initiation
    if ($decodedResponse['success'] === true && $decodedResponse['code'] === "PAYMENT_INITIATED") {
        // Extracting the payment URL from the response data
        $paymentUrl = $decodedResponse['data']['instrumentResponse']['redirectInfo']['url'];

        // Redirecting the user to the payment URL using header function
        header('Location: ' . $paymentUrl);
        exit;
    } else {
        // Handling errors or unsuccessful responses
        echo "Error: " . $decodedResponse['message'];
    }
}
   
}

/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/refunds/
 *
 * @return array Transaction response status
 */
 
 /*
 
function phonepepg_refund($params)
{
    // Gateway Configuration Parameters
    $accountId = $params['accountID'];
    $secretKey = $params['secretKey'];
    $testMode = $params['testMode'];
    $dropdownField = $params['dropdownField'];
    $radioField = $params['radioField'];
    $textareaField = $params['textareaField'];

    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // perform API call to initiate refund and interpret result

    return array(
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => 'success',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseData,
        // Unique Transaction ID for the refund transaction
        'transid' => $refundTransactionId,
        // Optional fee amount for the fee value refunded
        'fees' => $feeAmount,
    );
}


*/
/**
 * Cancel subscription.
 *
 * If the payment gateway creates subscriptions and stores the subscription
 * ID in tblhosting.subscriptionid, this function is called upon cancellation
 * or request by an admin user.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/subscription-management/
 *
 * @return array Transaction response status
 */
function phonepepg_cancelSubscription($params)
{
    
    // Gateway Configuration Parameters
    $pmerchantid = $params['merchantid'];
    $psaltKey = $params['saltKey'];
    $testMode = $params['testMode'];

    // Subscription Parameters
    $subscriptionIdToCancel = $params['subscriptionID'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // perform API call to cancel subscription and interpret result

    return array(
        // 'success' if successful, any other value for failure
        'status' => 'success',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseData,
    );
}
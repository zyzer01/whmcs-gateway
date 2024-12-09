<?php
/**
 * Zeepway Payment Gateway Module for WHMCS
 * Version: 1.0.0
 * Build Date: 26 November 2024
 */

if (!defined("WHMCS")) {
    die("<!-- Silence is golden. -->");
}

/**
 * Define Zeepway gateway configuration options.
 *
 * @return array
 */
function zeepway_config()
{   
    $isSSL = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443);
    $callbackUrl = 'http' . ($isSSL ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . 
        substr(str_replace('/admin/', '/', $_SERVER['REQUEST_URI']), 0, strrpos($_SERVER['REQUEST_URI'], '/')) . 
        '/modules/gateways/callback/zeepway.php';
    
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Zeepway (Debit/Credit Cards)'
        ),
        'webhook' => array(
            'FriendlyName' => 'Webhook URL',
            'Type' => 'yesno',
            'Description' => 'Copy and paste this URL on your Webhook URL settings: <code>' . htmlspecialchars($callbackUrl) . '</code>',
            'Default' => $callbackUrl,
        ),
        'gatewayLogs' => array(
            'FriendlyName' => 'Gateway logs',
            'Type' => 'yesno',
            'Description' => 'Tick to enable gateway logs',
            'Default' => '0'
        ),
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
            'Default' => '0'
        ),
        'livePubKey' => array(
            'FriendlyName' => 'Live Public Key',
            'Type' => 'text',
            'Size' => '50',
            'Default' => ''
        ),
        'livePrivKey' => array(
            'FriendlyName' => 'Live Private Key',
            'Type' => 'text',
            'Size' => '50',
            'Default' => ''
        ),
        'testPubKey' => array(
            'FriendlyName' => 'Test Public Key',
            'Type' => 'text',
            'Size' => '50',
            'Default' => ''
        ),
        'testPrivKey' => array(
            'FriendlyName' => 'Test Private Key',
            'Type' => 'text',
            'Size' => '50',
            'Default' => ''
        )
    );
}

/**
 * Payment link generation.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @return string
 */
function zeepway_link($params)
{
    // Client details
    $email = $params['clientdetails']['email'];
    $name = $params['clientdetails']['firstname'] . ' ' . $params['clientdetails']['lastname'];
    
    // Config Options
    if ($params['testMode'] == 'on') {
        $publicKey = $params['testPubKey'];
        $apiBaseUrl = 'https://staging.zeepjet.com';
    } else {
        $publicKey = $params['livePubKey'];
        $apiBaseUrl = 'https://dashboard.zeepway.com';
    }
    
    // Invoice details
    $invoiceId = $params['invoiceid'];
    $amount = intval(floatval($params['amount']));
    
    // Generate unique client reference
    $clientRef = $invoiceId . '_' . time();

    // Language
    $langPayNow = array_key_exists('langpaynow', $params) ? 
        htmlspecialchars($params['langpaynow']) : 'Pay Now';

    $code = '
    
<link rel="stylesheet" href="https://dashboard.zeepway.com/zeepway-main.css"> 
    
    <form id="payForm' . $invoiceId . '" target="hiddenIFrame" action="about:blank">
        <button type="submit" class="btn btn-primary" id="payButton' . $invoiceId . '">' . 
            $langPayNow . 
        '</button>
    </form>
<script src="https://dashboard.zeepway.com/zeepway.main.js" defer> </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Check if Zeepway library is loaded
        if (typeof zeepway !== "undefined") {
            const payForm = document.getElementById("payForm' . $invoiceId . '");
            const payButton = document.getElementById("payButton' . $invoiceId . '");

            payButton.addEventListener("click", function(e) {
                e.preventDefault();

                try {
                    console.log("Trying payment");

                    const zeepwayInstance = new zeepway({
                        api_key: "' . htmlspecialchars($publicKey) . '",
                        amount: ' . $amount . ',
                        email: "' . htmlspecialchars($email) . '",
                        name: "' . htmlspecialchars($name) . '",
                        clientRef: "' . htmlspecialchars($clientRef) . '",
                        chargeUser: false,
                        onClose: function() {
                            console.log("Zeepway modal closed");
                        }
                    });

                    zeepwayInstance.start();
                } catch (error) {
                    console.error("Zeepway Payment Error:", error);
                }
            });
        } else {
            console.error("Zeepway library not loaded properly.");
        }
    });
    </script>';

    return $code;
}
?>

<?php
if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

function zeepway_MetaData() {
    return [
        'DisplayName' => 'Zeepway Payment Gateway',
        'APIVersion' => '1.1',
    ];
}

function zeepway_config() {
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Zeepway Payment Gateway',
        ],
        'apiKey' => [
            'FriendlyName' => 'Public API Key',
            'Type' => 'text',
            'Size' => '64',
            'Default' => '',
            'Description' => 'Enter your Zeepway Public API Key',
        ],
        'callbackUrl' => [
            'FriendlyName' => 'Callback URL',
            'Type' => 'text',
            'Size' => '255',
            'Default' => '',
            'Description' => 'Enter your callback URL for payment status updates.',
        ],
    ];
}

function zeepway_link($params) {
    $apiKey = $params['apiKey'];
    $callbackUrl = $params['callbackUrl'];
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $currency = $params['currency'];
    $clientEmail = $params['clientdetails']['email'];
    $clientName = $params['clientdetails']['fullname'];
    $returnUrl = $params['systemurl'] . '/viewinvoice.php?id=' . $invoiceId;

    $uniqueRef = 'INV-' . $invoiceId . '-' . time();

    $htmlOutput = <<<HTML
    <link rel="stylesheet" href="https://dashboard.zeepway.com/zeepway-main.css">
    <form id="zeepwayPaymentForm">
        <button type="button" id="payWithZeepway">Pay Now</button>
    </form>
    <script src="https://dashboard.zeepway.com/zeepway.main.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('payWithZeepway').addEventListener('click', function() {
                // console.log('Initializing Zeepway payment...');
                
                try {
                    var zeepInstance = new zeepway({
                        api_key: $apiKey,
                        amount: $amount,
                        email: "$clientEmail",
                        name: "$clientName",
                        clientRef: "$uniqueRef",
                        chargeUser: false,
                        onClose: function() {
                            console.warn('Payment process was closed by the user.');
                            alert('Payment process was closed.');
                        },
                        onSuccess: function(response) {
                            console.log('Payment succeeded:', response);
                        },
                        // onError: function(error) {
                        //     console.error('An error occurred:', error);
                        //     alert('An error occurred during the payment process. Check the console for details.');
                        // }
                    });
                    zeepInstance.start();
                } catch (err) {
                    // console.error('Unexpected error while starting the Zeepway instance:', err);
                    // alert('An unexpected error occurred. Check the console for details.');
                }
            });
        });
    </script>
    HTML;

    return $htmlOutput;
}

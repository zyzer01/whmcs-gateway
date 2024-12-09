<?php
/**
 * Zeepway Payment Gateway Callback for WHMCS
 * Version: 1.0.0
 * Build Date: 26 November 2024
 */

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Determine keys based on test mode
if ($gatewayParams['testMode'] == 'on') {
    $privateKey = $gatewayParams['testPrivKey'];
    $apiBaseUrl = 'https://staging-api.zeepjet.com';
} else {
    $privateKey = $gatewayParams['livePrivKey'];
    $apiBaseUrl = 'https://merchant-api.zeepway.com';
}

// Handle WebHook
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify the webhook request
    $input = file_get_contents('php://input');
    $webhookData = json_decode($input, true);

    // Verify authorization header
    $headers = getallheaders();
    if (!isset($headers['authorization']) || trim($headers['authorization']) !== trim($privateKey)) {
        http_response_code(401);
        die('Unauthorized');
    }

    // Validate webhook data
    if (!$webhookData || !isset($webhookData['reference']) || !isset($webhookData['status'])) {
        http_response_code(400);
        die('Invalid webhook data');
    }

    // Extract invoice ID from client reference (assuming format: invoiceId_timestamp)
    $clientRef = $webhookData['client_ref'];
    $referenceParts = explode('_', $clientRef);
    $invoiceId = $referenceParts[0];

    // Log transaction
    if ($gatewayParams['gatewayLogs'] == 'on') {
        $output = "Webhook Received:"
            . "\r\nTransaction Ref: " . $webhookData['reference']
            . "\r\nInvoice ID: " . $invoiceId
            . "\r\nStatus: " . $webhookData['status']
            . "\r\nAmount: " . $webhookData['amount'];
        logTransaction($gatewayModuleName, $output, $webhookData['status'] === 'SUCCESSFUL' ? 'Successful' : 'Unsuccessful');
    }

    // Handle successful payment
    if ($webhookData['status'] === 'SUCCESSFUL') {
        // Verify the transaction details via Zeepway's query endpoint
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiBaseUrl . '/v1/transactions/query?reference=' . urlencode($webhookData['reference']));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $privateKey
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $transactionDetails = json_decode($response, true);

        if ($transactionDetails && isset($transactionDetails['transaction']['status']) && 
            $transactionDetails['transaction']['status'] === 'SUCCESSFUL') {
            
            // Validate invoice ID and transaction
            $invoiceId = checkCbInvoiceID($invoiceId, $gatewayModuleName);
            checkCbTransID($webhookData['reference']);

            // Convert amount
            $amount = floatval($transactionDetails['transaction']['amount']);
            if ($gatewayParams['convertto']) {
                $result = select_query("tblclients", "tblinvoices.invoicenum,tblclients.currency,tblcurrencies.code", array("tblinvoices.id" => $invoiceId), "", "", "", "tblinvoices ON tblinvoices.userid=tblclients.id INNER JOIN tblcurrencies ON tblcurrencies.id=tblclients.currency");
                $data = mysql_fetch_array($result);
                $invoice_currency_id = $data['currency'];

                $converto_amount = convertCurrency($amount, $gatewayParams['convertto'], $invoice_currency_id);
                $amount = format_as_currency($converto_amount);
            }

            // Add invoice payment
            addInvoicePayment($invoiceId, $webhookData['reference'], $amount, 0, $gatewayModuleName);

            // Respond to webhook
            http_response_code(200);
            die('Webhook processed successfully');
        }
    }

    // If not successful or verification failed
    http_response_code(200);
    die('Webhook processed');
}

// Handle Callback (redirect after transaction)
if (isset($_GET['reference']) && isset($_GET['client_ref'])) {
    $reference = $_GET['reference'];
    $clientRef = $_GET['client_ref'];

    // Verify transaction
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiBaseUrl . '/v1/transactions/query?reference=' . urlencode($reference));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $privateKey
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $transactionDetails = json_decode($response, true);

    if ($transactionDetails && isset($transactionDetails['transaction']['status'])) {
        // Extract invoice ID from client reference
        $referenceParts = explode('_', $clientRef);
        $invoiceId = $referenceParts[0];

        // Redirect to invoice page
        $isSSL = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443);
        $invoice_url = 'http' . ($isSSL ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] .
            substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '/')) .
            '/../../../viewinvoice.php?id=' . rawurlencode($invoiceId);

        header('Location: ' . $invoice_url);
        exit;
    }
}

// If no valid webhook or callback
die('Invalid request');

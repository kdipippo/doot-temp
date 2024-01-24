<?php
/**
 * PHP rewrite of https://github.com/statsig-io/statuspage/blob/main/health-check.sh.
 */

// TODO - return type could be false if no key available instead of just strings.
function getEnvVariable(string $envKey) {
    $localEnvFilename = "./local.env";
    if (file_exists($localEnvFilename)) {
        $env = parse_ini_file('./local.env');
        return $env[$envKey];
    }
    return getenv($envKey);
}

/**
 * curl --insecure --write-out '%{http_code}' --silent --output /dev/null https://example.com/
 * --write-out is not a supported option
 * --silent is not a supported option
 * --output is not a supported option
*/
/**
 * Get HTTP status code via cURL call using built-in PHP cURL library.
 *
 * @param (string) $url
 *   URL to cURL for HTTP Status code.
 * @param (bool) $sslCheck
 *   True to perform cURL with SSL certification validation; False to disable SSL validation.
 *
 * @return (int)
 *   Integer HTTP status code.
 */
function getURLHTTPCode(string $url, bool $sslCheck = True): int {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    if (!$sslCheck) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslCheck);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslCheck);
    }
    curl_setopt($ch, CURLOPT_HEADER  , true);
    curl_setopt($ch, CURLOPT_NOBODY  , true);

    curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpcode;
}


/**
 * Return "success" if basic cURL call to URL returns a valid HTTP response code.
 *
 * @param (string) $url
 *   Input URL to check cURL against.
 *
 * @return (string)
 *   "success" if cURL returns success HTTP response code, "failed" if not after 2 attempts.
 */
function getURLStatus(string $url): string {
    for ($attempt = 0; $attempt < 2; $attempt++) {
        // If site is live but SSL cert is invalid, $secureResponseCode will return 000.
        // Running both commands will determine the true status of the site.
        $secureResponseCode = getURLHTTPCode($url, True);
        $insecureResponseCode = getURLHTTPCode($url, False);

        echo "Attempt #{$attempt} : Secure Response = {$secureResponseCode}; Insecure Response = {$insecureResponseCode}\n";

        // Default to using secureResponse for checking unless SSL cert is invalid.
        $sslInvalid = False;
        $responseCode = $secureResponseCode;
        if ($insecureResponseCode != $secureResponseCode) {
            $sslInvalid = True;
            $responseCode = $insecureResponseCode;
        }

        if (in_array($responseCode, [200, 202, 301, 302, 307])) {
            return "success" . ($sslInvalid ? " but SSL is invalid" : "");
        }
        sleep(5);
    }
    return "failed - encountered {$responseCode} error code" . ($sslInvalid ? " and SSL is invalid" : "");
}

/**
 * Format message text and send contents into webhook.
 *
 * @param (string) $dateTime
 *   Datetime last error encountered, example "2021-06-10 12:43".
 * @param (string) $env
 *   Environment, one of PROD, TEST, DEV, TRAINING.
 * @param (string) $response
 *   String response text, formatted in getURLStatus().
 *
 * @return (void)
 *   Contents sent to webhook with no status output.
 */
function sendMessageToWebhook(string $dateTime, string $env, string $response): void {
    // THIS IS NOT YET HOOKED UP DUE TO WEBHOOK URL CREATION FAILING.
    $webhookText = "! At {$dateTime}, {$env} environment check returned '{$response}' !";
}

/**
 * Perform all health checks.
 *
 * @param (bool) $saveToLogs
 *   True to write results to logs in /logs directory, False if just echoing to terminal.
 * @param (bool) $notifyWebhook
 *   True to send message to webhook URL.
 *
 * @return void
 */
function runURLHealthCheck(bool $saveToLogs = True, bool $notifyWebhook = True): void {
    $envs_to_urls = [
        "PROD"     => getEnvVariable('PROD'),
        "DEV"      => getEnvVariable('DEV'),
        "TEST"     => getEnvVariable('TEST'),
        "TRAINING" => getEnvVariable('TRAINING'),
        "TRDEV"    => getEnvVariable('TRDEV')
    ];

    echo "***********************\n";
    echo "Starting health checks with " . count($envs_to_urls) . " configs:\n";

    // Create /logs directory if it doesn't exist.
    if (!file_exists("logs")) {
        mkdir("logs", 0777, true);
    }

    foreach ($envs_to_urls as $env => $url) {
        echo "  Checking {$env} environment\n";
        $response = getURLStatus($url);
        $dateTime = date("Y-m-d H:i");
        $newline = "$dateTime, $response\n";
        echo "    " . $newline;
        if ($saveToLogs) {
            $file = fopen("logs/{$env}_report.log", "a");
            fwrite($file, $newline);
            fclose($file);
            // echo "$(tail -2000 logs/${env}_report.log)" > "logs/${env}_report.log"
        }
        // DISABLED FOR NOW UNTIL WEBHOOK URL CAN BE GENERATED.
        if ($notifyWebhook && ($response != "success")) {
            sendMessageToWebhook($dateTime, $env, $response);
        }
    }
}

runURLHealthCheck(True, False);

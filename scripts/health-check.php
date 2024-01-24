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
        // If SSL cert is invalid, $cURLsecure will return 200. Running both commands to see true status of site.
        $cURLsecure = "curl --write-out '%{http_code}' --silent --output /dev/null $url";
        $cURLinsecure = "curl --insecure --write-out '%{http_code}' --silent --output /dev/null $url";
        $secureResponse = shell_exec($cURLsecure);
        $insecureResponse = shell_exec($cURLinsecure);

        // Default to using secureResponse for checking unless SSL cert is invalid.
        $sslInvalid = False;
        $response = $secureResponse;
        if ($insecureResponse != $secureResponse) {
            $sslInvalid = True;
            $response = $insecureResponse;
        }

        if (in_array($response, ["200", "202", "301", "302", "307"])) {
            return "success" . ($sslInvalid ? " but SSL is invalid" : "");
        }
        sleep(5);
    }
    return "failed - encountered {$response} error code" . ($sslInvalid ? " and SSL is invalid" : "");
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

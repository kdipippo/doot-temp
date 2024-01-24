<?php
/**
 * PHP rewrite of https://github.com/statsig-io/statuspage/blob/main/health-check.sh.
 */

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
        $response = shell_exec("curl --write-out '%{http_code}' --silent --output /dev/null $url");
        if (in_array($response, ["200", "202", "301", "302", "307"])) {
            return "success";
        }
        sleep(5);
    }
    return "failed - encountered {$response} error code";
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
        "PROD"     => getenv('PROD'),
        "DEV"      => getenv('DEV'),
        "TEST"     => getenv('TEST'),
        "TRAINING" => getenv('TRAINING'),
        "TRDEV"    => getenv('TRDEV')
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

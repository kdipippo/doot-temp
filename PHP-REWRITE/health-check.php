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
 *   "success" if cURL returns success HTTP response code, "failed" if not after 4 attempts.
 */
function getURLStatus(string $url): string {
    for ($attempt = 0; $attempt < 4; $attempt++) {
        $response = shell_exec("curl --write-out '%{http_code}' --silent --output /dev/null $url");
        if (in_array($response, ["200", "202", "301", "302", "307"])) {
            return "success";
        }
        sleep(5);
    }
    return "failed";
}

/**
 * Perform all health checks.
 *
 * @param (bool) $saveToLogs
 *   True to write results to logs in /logs directory, False if just echoing to terminal.
 *
 * @return void
 */
function runURLHealthCheck(bool $saveToLogs = True): void {
    // Hard-coding instead of reading from ./urls.cfg.
    $keys_to_urls = [
        "google"     => "https://google.com",
        "hn"         => "https://news.ycombinator.com",
        "reddit"     => "https://reddit.com",
        "statsig"    => "https://www.statsig.com"
    ];

    echo "***********************\n";
    echo "Starting health checks with " . count($keys_to_urls) . " configs:\n";

    // Create /logs directory if it doesn't exist.
    if (!file_exists("logs")) {
        mkdir("logs", 0777, true);
    }

    foreach ($keys_to_urls as $key => $url) {
        echo "  {$key}={$url}\n";
        $response = getURLStatus($url);
        $dateTime = date("Y-m-d H:i");
        $newline = "$dateTime, $response\n";
        echo "    " . $newline;
        if ($saveToLogs) {
            $file = fopen("logs/{$key}_report.log", "a");
            fwrite($file, $newline);
            fclose($file);
            // echo "$(tail -2000 logs/${key}_report.log)" > "logs/${key}_report.log"
        }
    }
}

runURLHealthCheck(True);

<?php
declare(strict_types=1);

function smtp_commands($fp, $commands) {
    echo "\n===\n";
    foreach ($commands as $command) {
        echo "Running command: $command\n";
        fwrite($fp, "$command\r\n");
        $s = fgets($fp);
        echo "Response $s\n";
        if (substr($s, 0, 3) != '250') {
            return false;
        }
        while ($s[3] == '-') {
            $s = fgets($fp);
        }
    }
    return true;
}

function try_email($email, $from) {
    if (!function_exists('getmxrr')) {
        return null;
    }
    $domain = preg_replace('~.*@~', '', $email);
    getmxrr($domain, $mxs);
    if (!in_array($domain, $mxs)) {
        $mxs[] = $domain;
    }
    $commands = array(
        "HELO " . preg_replace('~.*@~', '', $from),
        "MAIL FROM: <$from>",
        "RCPT TO: <$email>",
    );
    $return = null;

    foreach ($mxs as $mx) {
        $fp = @fsockopen($mx, 25);
        if ($fp) {
            $s = fgets($fp);
            while ($s[3] == '-') {
                $s = fgets($fp);
            }
            if (substr($s, 0, 3) == '220') {
                $return = smtp_commands($fp, $commands);
            }
            fwrite($fp, "QUIT\r\n");
            fgets($fp);
            fclose($fp);
            if (isset($return)) {
                return $return;
            }
        }
    }
    return false;
}

$maillist = fopen(__DIR__ . '/maillist.csv', 'r');
if(!$maillist) die('ERROR: File maillist.csv does not exist or is not readable.' . PHP_EOL);

while ($row = fgetcsv($maillist)) {
    $mail = $row[0];
    if(!$mail) continue;
    echo "Checking mail: $mail \n";
    $status = try_email($mail, 'email@martinkrizan.com');
    echo "\n=== Mail is " . ($status ? 'deliverable' : 'not deliverable') . ". ===\n\n";
}

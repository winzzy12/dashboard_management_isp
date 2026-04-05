<?php
echo "<h2>SSH Extension Check</h2>";

// Check if ssh2 extension is loaded
if(function_exists('ssh2_connect')) {
    echo "<p style='color:green'>✓ SSH2 extension is loaded</p>";
} else {
    echo "<p style='color:orange'>⚠ SSH2 extension is NOT loaded</p>";
    echo "<p>The script will use alternative method via exec()</p>";
}

// Check if ssh command is available
$sshCheck = shell_exec("which ssh 2>&1");
if(!empty($sshCheck)) {
    echo "<p style='color:green'>✓ SSH command is available: " . $sshCheck . "</p>";
} else {
    echo "<p style='color:red'>✗ SSH command is NOT available</p>";
}

// Check if sshpass is available (for password authentication)
$sshpassCheck = shell_exec("which sshpass 2>&1");
if(!empty($sshpassCheck)) {
    echo "<p style='color:green'>✓ sshpass is available: " . $sshpassCheck . "</p>";
} else {
    echo "<p style='color:orange'>⚠ sshpass is NOT available</p>";
    echo "<p>For password authentication, please install sshpass:</p>";
    echo "<pre>sudo apt-get install sshpass</pre>";
}

// Test connection to MikroTik
echo "<h3>Manual SSH Test:</h3>";
$host = '192.168.1.1'; // Change to your MikroTik IP
$username = 'admin';
$port = 22;

$testCmd = "timeout 5 ssh -o ConnectTimeout=5 -o StrictHostKeyChecking=no -p $port $username@$host exit 2>&1";
echo "<p>Testing: <code>$testCmd</code></p>";
$result = shell_exec($testCmd);
echo "<pre>" . htmlspecialchars($result) . "</pre>";
?>
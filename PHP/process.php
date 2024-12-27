<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiUrl = $_POST['apiUrl'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Helper function to validate URL
    function isValidUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    // Validate all required fields
    if (empty($apiUrl) || empty($username) || empty($password) || !isset($_FILES['file'])) {
        echo "[ERROR] Missing required fields.";
        exit;
    }

    // Validate API URL
    if (!isValidUrl($apiUrl)) {
        echo "[ERROR] Invalid API URL.";
        exit;
    }

    // Normalize API URL to include only protocol and host
    $parsedUrl = parse_url($apiUrl);
    if (!$parsedUrl || !isset($parsedUrl['scheme'], $parsedUrl['host'])) {
        echo "[ERROR] Invalid API URL format.";
        exit;
    }
    $apiUrl = rtrim($parsedUrl['scheme'] . '://' . $parsedUrl['host'], '/');

    // Authenticate with the API and store cookies
    $authPayload = json_encode(['username' => $username, 'password' => $password]);
    $cookieFilePath = dirname(__FILE__) . '/cookie.txt';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$apiUrl/api/auth/login");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $authPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFilePath); // Save cookies
    curl_setopt($ch, CURLOPT_HEADER, true); // Include headers in the response
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Allow self-signed certificates
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Allow self-signed certificates

    $authResponse = curl_exec($ch);

    if (curl_errno($ch)) {
        echo "[ERROR] Authentication failed: " . curl_error($ch);
        exit;
    }

    $authHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($authHttpCode !== 200) {
        echo "[ERROR] Authentication failed: HTTP $authHttpCode";
        exit;
    }

    // Extract X-CSRF-Token from headers
    list($headers, $body) = explode("\r\n\r\n", $authResponse, 2);
    preg_match('/^X-CSRF-Token:\s*(.*)$/mi', $headers, $matches);
    $csrfToken = $matches[1] ?? null;

    if (!$csrfToken) {
        echo "[ERROR] X-CSRF-Token not found in headers.";
        exit;
    }

    // Process the uploaded file
    $fileTmpPath = $_FILES['file']['tmp_name'];
    $fileContent = file_get_contents($fileTmpPath);

    $lines = explode(PHP_EOL, trim($fileContent));
    $parsedData = [];

    foreach ($lines as $line) {
        $line = trim($line);

        // Check for first file type pattern
        if (preg_match('/^(\d+),\s*,(.*?),Cleartext-Password,(.*?)$/', $line, $matches)) {
            $parsedData[] = [
                'name' => trim($matches[2]),
                'x_password' => trim($matches[3]),
                'tunnel_medium_type' => '',
                'tunnel_type' => '',
                'vlan' => '',
            ];
        } 
        // Check for second file type pattern
        elseif (preg_match('/^(.*) Cleartext-Password := \"(.*?)\"$/', $line, $matches)) {
            $parsedData[] = [
                'name' => trim($matches[1]),
                'x_password' => trim($matches[2]),
                'tunnel_medium_type' => '',
                'tunnel_type' => '',
                'vlan' => '',
            ];
        }
    }

    if (empty($parsedData)) {
        echo "[ERROR] No valid data found in the uploaded file.";
        exit;
    }

    // Send the parsed data to the API
    $batchPayload = json_encode($parsedData);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$apiUrl/proxy/network/v2/api/site/default/radius/users/batch_add");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $batchPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $csrfToken
    ]);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFilePath); // Use saved cookies
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Allow self-signed certificates
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Allow self-signed certificates

    $batchResponse = curl_exec($ch);

    if (curl_errno($ch)) {
        echo "[ERROR] Batch add failed: " . curl_error($ch);
        exit;
    }

    $batchHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Return responses
    echo "[SUCCESS] Server Response:\n\n";
    echo $batchResponse . "\n\n";
    echo "[SUCCESS] cURL Request Format:\n\n";
    echo "curl -k -X POST \"$apiUrl/proxy/network/v2/api/site/default/radius/users/batch_add\" \\\n";
    echo "-H 'Content-Type: application/json' \\\n";
    echo "-H 'X-CSRF-Token: $csrfToken' \\\n";
    echo "-b $cookieFilePath \\\n";
    echo "-d '$batchPayload'\n";
}
?>

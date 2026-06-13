<?php

$env = [];
foreach (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value);
}

$client_id     = $env['EQUIFAX_PRODUCTION_API_KEY'];
$client_secret = $env['EQUIFAX_PRODUCTION_API_SECRET'];

$consumer = [
    'firstName'   => 'Malikah',
    'lastName'    => 'Grundy',
    'dateOfBirth' => '1982-01-13',
    'address'     => [
        'line1' => '685 Doctor Martin Luther King Junior Boulevard  #232',
        'city'  => 'Newark',
        'state' => 'NJ',
        'zip'   => '07102',
    ],
];

function getToken($client_id, $client_secret)
{
    $ch = curl_init('https://api.equifax.com/v2/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials&scope=https://api.equifax.com/business/oneview/consumer-credit/v1',
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($client_id . ':' . $client_secret),
        ],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    if (empty($data['access_token'])) die("Token failed:\n" . print_r($data, true));
    return $data['access_token'];
}

function pullCreditReport($token, $consumer)
{
    // Convert DOB from YYYY-MM-DD to MMDDYYYY
    $dob = date('mdY', strtotime($consumer['dateOfBirth']));

    $payload = json_encode([
        'consumers' => [
            'name' => [[
                'identifier' => 'current',
                'firstName'  => $consumer['firstName'],
                'lastName'   => $consumer['lastName'],
            ]],
            'addresses' => [[
                'identifier' => 'current',
                'streetName' => $consumer['address']['line1'],
                'city'       => $consumer['address']['city'],
                'state'      => $consumer['address']['state'],
                'zip'        => $consumer['address']['zip'],
            ]],
            'dateOfBirth'  => $dob,
            'phoneNumbers' => [[
                'identifier' => 'current',
                'number'     => '5551234567',
            ]],
        ],
        'customerConfiguration' => [
            'equifaxUSConsumerCreditReport' => [
                'memberNumber'            => '416FZ02115',
                'securityCode'            => 'F4R',
                'customerCode'            => 'IAPI',
                'codeDescriptionRequired' => true,
                'ECOAInquiryType'         => 'Individual',
                'multipleReportIndicator' => '1',
                'models'                  => [
                    ['identifier' => '05734']
                ],
            ],
        ],
    ]);

    $ch = curl_init('https://api.equifax.com/business/oneview/consumer-credit/v1/reports/credit-report');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Status: $httpCode\n\n";
    print_r(json_decode($response, true));
}

$token = getToken($client_id, $client_secret);
echo "Token obtained.\n\n";
pullCreditReport($token, $consumer);


    <?php

    // generate_prompt.php

    header('Content-Type: application/json');

    // Basic error handling and input validation
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Invalid request method.']);
        exit;
    }

    $apiKey = isset($_POST['apiKey']) ? trim($_POST['apiKey']) : null;
    $promptDetails = isset($_POST['promptDetails']) ? trim($_POST['promptDetails']) : null;
    // $targetModel = isset($_POST['model']) ? trim($_POST['model']) : 'gemini-2.0-flash'; // Default or use passed model

    if (empty($apiKey) || empty($promptDetails)) {
        echo json_encode(['error' => 'API key and prompt details are required.']);
        exit;
    }

    // Construct the payload for the Gemini API
    // The schema should match what the Gemini API expects for a structured prompt generation
    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [['text' => $promptDetails]]
            ]
        ],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'responseSchema' => [
                'type' => 'OBJECT',
                'properties' => [
                    'prompt' => ['type' => 'STRING']
                ],
                'required' => ['prompt']
            ]
        ]
    ];

    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey;

    // Use cURL to make the API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        echo json_encode(['error' => 'cURL Error: ' . $curl_error]);
        exit;
    }

    if ($httpcode !== 200) {
        // Attempt to decode Gemini's error response
        $errorResponse = json_decode($response, true);
        $errorMessage = 'Gemini API Error (HTTP ' . $httpcode . ')';
        if (isset($errorResponse['error']['message'])) {
            $errorMessage .= ': ' . $errorResponse['error']['message'];
        } else {
            $errorMessage .= '. Response: ' . $response;
        }
        echo json_encode(['error' => $errorMessage]);
        exit;
    }

    // The Gemini API (with responseSchema) should directly return the JSON string for the 'parts.text'
    // So, we decode it once to get the object, then extract the 'prompt'
    $apiResult = json_decode($response, true);

    if (isset($apiResult['candidates'][0]['content']['parts'][0]['text'])) {
        $generatedJsonString = $apiResult['candidates'][0]['content']['parts'][0]['text'];
        $finalResponse = json_decode($generatedJsonString, true); // This should contain the { "prompt": "..." }
        if (isset($finalResponse['prompt'])) {
             echo json_encode(['prompt' => $finalResponse['prompt']]);
        } else {
            echo json_encode(['error' => 'Failed to parse structured prompt from Gemini response.', 'raw_gemini_part_text' => $generatedJsonString]);
        }
    } elseif (isset($apiResult['error'])) {
         echo json_encode(['error' => 'Gemini API returned an error: ' . $apiResult['error']['message']]);
    }
    else {
        echo json_encode(['error' => 'Unexpected response structure from Gemini API.', 'raw_response' => $apiResult]);
    }

    ?>

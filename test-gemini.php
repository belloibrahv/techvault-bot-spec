<?php
/**
 * Standalone LLM API test — run from terminal:
 *
 *   # Test Gemini directly:
 *   PROVIDER=gemini GEMINI_API_KEY=AIzaSy... php test-gemini.php
 *
 *   # Test AgentRouter (OpenAI-compatible):
 *   PROVIDER=agentrouter AGENTROUTER_API_KEY=sk-VTHb... php test-gemini.php
 *
 * No WordPress required.
 */

$provider = getenv('PROVIDER') ?: 'agentrouter';

// ── AgentRouter ──────────────────────────────────────────────────────────────
if ( $provider === 'agentrouter' ) {
    $apiKey = getenv('AGENTROUTER_API_KEY') ?: 'PASTE_YOUR_KEY_HERE';
    $model  = getenv('MODEL') ?: 'gemini-2.0-pro';

    if ( $apiKey === 'PASTE_YOUR_KEY_HERE' ) {
        echo "ERROR: Set your AgentRouter API key.\n";
        echo "  AGENTROUTER_API_KEY=sk-VTHb... php test-gemini.php\n";
        exit(1);
    }

    $url     = 'https://agentrouter.org/v1/chat/completions';
    $payload = json_encode([
        'model'       => $model,
        'max_tokens'  => 100,
        'temperature' => 0.4,
        'messages'    => [
            [
                'role'    => 'system',
                'content' => 'You are a helpful assistant for TechVaults, a web development company in Lagos, Nigeria.',
            ],
            [
                'role'    => 'user',
                'content' => 'What services does TechVaults offer? Answer in one sentence.',
            ],
        ],
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            "Authorization: Bearer {$apiKey}",
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);

    $raw    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    echo "=== AgentRouter API Test ===\n";
    echo "Model:  {$model}\n";
    echo "Status: {$status}\n\n";

    if ($err) {
        echo "cURL error: {$err}\n";
        exit(1);
    }

    $decoded = json_decode($raw, true);

    if ($status !== 200) {
        echo "API error:\n";
        echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
        exit(1);
    }

    $reply = $decoded['choices'][0]['message']['content'] ?? '(empty)';
    echo "Reply:\n  " . trim($reply) . "\n\n";

    $usage = $decoded['usage'] ?? [];
    echo "Tokens used:\n";
    echo "  Prompt:   " . ($usage['prompt_tokens']     ?? '?') . "\n";
    echo "  Response: " . ($usage['completion_tokens'] ?? '?') . "\n";
    echo "  Total:    " . ($usage['total_tokens']      ?? '?') . "\n";
    echo "\n✓ AgentRouter connection works!\n";
    exit(0);
}

// ── Gemini direct ─────────────────────────────────────────────────────────────
$apiKey = getenv('GEMINI_API_KEY') ?: 'PASTE_YOUR_KEY_HERE';
$model  = getenv('MODEL') ?: 'gemini-flash-latest';  // resolves to gemini-3.5-flash on new keys

if ( $apiKey === 'PASTE_YOUR_KEY_HERE' ) {
    echo "ERROR: Set your API key.\n";
    echo "  GEMINI_API_KEY=AIzaSy... php test-gemini.php\n";
    exit(1);
}

$url     = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
$payload = json_encode([
    'systemInstruction' => [
        'parts' => [[ 'text' => 'You are a helpful assistant for TechVaults, a web development company.' ]],
    ],
    'contents' => [
        [
            'role'  => 'user',
            'parts' => [[ 'text' => 'What services does TechVaults offer? Answer in one sentence.' ]],
        ],
    ],
    'generationConfig' => [
        'maxOutputTokens' => 100,
        'temperature'     => 0.4,
        'thinkingConfig'  => [ 'thinkingBudget' => 0 ],
    ],
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        "x-goog-api-key: {$apiKey}",
    ],
    CURLOPT_TIMEOUT        => 20,
]);

$raw    = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err    = curl_error($ch);
curl_close($ch);

echo "=== Gemini Direct API Test ===\n";
echo "Model:  {$model}\n";
echo "Status: {$status}\n\n";

if ($err) {
    echo "cURL error: {$err}\n";
    exit(1);
}

$decoded = json_decode($raw, true);

if ($status !== 200) {
    echo "API error:\n";
    echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

$reply = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '(empty)';
echo "Reply:\n  " . trim($reply) . "\n\n";

$usage = $decoded['usageMetadata'] ?? [];
echo "Tokens used:\n";
echo "  Prompt:   " . ($usage['promptTokenCount']     ?? '?') . "\n";
echo "  Response: " . ($usage['candidatesTokenCount'] ?? '?') . "\n";
echo "  Total:    " . ($usage['totalTokenCount']      ?? '?') . "\n";
echo "\n✓ Gemini connection works!\n";

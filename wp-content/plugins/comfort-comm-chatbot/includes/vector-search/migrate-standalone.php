#!/usr/bin/env php
<?php
/**
 * Standalone Migration Script - Does NOT require WordPress
 *
 * Run: php migrate-standalone.php
 */

// Supabase PostgreSQL config (from wp-config.php)
$pg_host = 'db.tlpvjrbmxxggubnjmdhe.supabase.co';
$pg_port = '5432';
$pg_database = 'postgres';
$pg_user = 'postgres';
$pg_password = 'password1234';

// OpenAI API key - you must set this
$openai_api_key = ''; // Will try to get from WordPress

// Load FAQ JSON file
$faq_file = dirname(__FILE__) . '/../../data/comfort-comm-faqs.json';

echo "==============================================\n";
echo "Chatbot Vector Search - Standalone Migration\n";
echo "==============================================\n\n";

// AUTH_KEY from wp-config.php (used for keyguard deobfuscation)
$auth_key = 'put your unique phrase here';

// Try to get OpenAI key from WordPress database (SQLite)
$sqlite_db = dirname(__FILE__) . '/../../../../../wp-content/database/.ht.sqlite';
if (file_exists($sqlite_db)) {
    try {
        $sqlite = new PDO('sqlite:' . $sqlite_db);

        // Get the encrypted API key
        $stmt = $sqlite->query("SELECT option_value FROM wp_options WHERE option_name = 'chatbot_chatgpt_api_key'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $encrypted_key = $row ? $row['option_value'] : null;

        // Get the obfuscated keyguard
        $stmt = $sqlite->query("SELECT option_value FROM wp_options WHERE option_name = 'kognetiks_keyguard'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $obfuscated_keyguard = $row ? $row['option_value'] : null;

        if ($encrypted_key && $obfuscated_keyguard) {
            // Deobfuscate the keyguard using AUTH_KEY
            $salt_key = hash('sha256', $auth_key, true);
            $obfuscated_binary = base64_decode($obfuscated_keyguard);
            $binary_key = $obfuscated_binary ^ $salt_key;
            $keyguard = bin2hex($binary_key);

            // Decrypt the API key using the keyguard
            $decoded = json_decode($encrypted_key, true);
            if (isset($decoded['iv']) && isset($decoded['encrypted'])) {
                $iv = base64_decode($decoded['iv']);
                $encrypted = $decoded['encrypted'];
                $binary_keyguard = hex2bin($keyguard);
                $openai_api_key = openssl_decrypt($encrypted, 'aes-256-cbc', $binary_keyguard, 0, $iv);

                if ($openai_api_key) {
                    echo "✓ Decrypted OpenAI API key from WordPress database\n";
                } else {
                    echo "Warning: Failed to decrypt API key\n";
                }
            } else {
                // Key might be plain text
                $openai_api_key = $encrypted_key;
                echo "✓ Got OpenAI API key from WordPress database (plain text)\n";
            }
        }
    } catch (Exception $e) {
        echo "Warning: Could not read SQLite database: " . $e->getMessage() . "\n";
    }
}

if (empty($openai_api_key)) {
    die("Error: OpenAI API key not found. Please set it manually in this script.\n");
}

// Connect to PostgreSQL
echo "Connecting to PostgreSQL...\n";
try {
    $dsn = "pgsql:host={$pg_host};port={$pg_port};dbname={$pg_database}";
    $pdo = new PDO($dsn, $pg_user, $pg_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "✓ Connected to PostgreSQL\n\n";
} catch (PDOException $e) {
    die("Error: Could not connect to PostgreSQL: " . $e->getMessage() . "\n");
}

// Load FAQs
echo "Loading FAQs from JSON file...\n";
if (!file_exists($faq_file)) {
    die("Error: FAQ file not found at {$faq_file}\n");
}

$faq_json = file_get_contents($faq_file);
$faqs = json_decode($faq_json, true);

if (empty($faqs)) {
    die("Error: No FAQs found in JSON file\n");
}

$total = count($faqs);
echo "✓ Found {$total} FAQs\n\n";

// Clear existing data
echo "Clearing existing data...\n";
$pdo->exec('TRUNCATE TABLE chatbot_faqs RESTART IDENTITY');
echo "✓ Table cleared\n\n";

// Function to generate embedding via OpenAI
function generate_embedding($text, $api_key) {
    $url = 'https://api.openai.com/v1/embeddings';

    $data = json_encode([
        'model' => 'text-embedding-3-small',
        'input' => trim($text),
        'encoding_format' => 'float'
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        $error = json_decode($response, true);
        return ['error' => $error['error']['message'] ?? 'Unknown error'];
    }

    $result = json_decode($response, true);
    return $result['data'][0]['embedding'] ?? null;
}

// Convert embedding array to PostgreSQL vector format
function to_pg_vector($embedding) {
    return '[' . implode(',', $embedding) . ']';
}

// Migrate each FAQ
echo "Starting migration...\n";
echo "Generating embeddings using OpenAI text-embedding-3-small\n\n";

$success = 0;
$errors = 0;

foreach ($faqs as $index => $faq) {
    $num = $index + 1;
    $faq_id = $faq['id'] ?? 'faq_' . $num;
    $question = $faq['question'] ?? '';
    $answer = $faq['answer'] ?? '';
    $category = $faq['category'] ?? '';
    $keywords = $faq['keywords'] ?? '';

    // If keywords is array, convert to string
    if (is_array($keywords)) {
        $keywords = implode(', ', $keywords);
    }

    echo "[{$num}/{$total}] " . substr($question, 0, 50) . "...\n";

    // Generate combined embedding
    $combined_text = $question . ' ' . $answer;
    $embedding = generate_embedding($combined_text, $openai_api_key);

    if (isset($embedding['error'])) {
        echo "  ✗ Error: " . $embedding['error'] . "\n";
        $errors++;
        continue;
    }

    if (!$embedding) {
        echo "  ✗ Error: No embedding returned\n";
        $errors++;
        continue;
    }

    // Insert into database
    try {
        $stmt = $pdo->prepare('
            INSERT INTO chatbot_faqs
            (faq_id, question, answer, category, keywords, combined_embedding)
            VALUES (?, ?, ?, ?, ?, ?::vector)
        ');
        $stmt->execute([
            $faq_id,
            $question,
            $answer,
            $category,
            $keywords,
            to_pg_vector($embedding)
        ]);

        echo "  ✓ Migrated\n";
        $success++;

    } catch (PDOException $e) {
        echo "  ✗ DB Error: " . $e->getMessage() . "\n";
        $errors++;
    }

    // Small delay to avoid rate limiting
    usleep(100000); // 100ms
}

echo "\n";
echo "==============================================\n";
echo "Migration Complete!\n";
echo "  - Total: {$total}\n";
echo "  - Success: {$success}\n";
echo "  - Errors: {$errors}\n";
echo "==============================================\n";

// Create search index
if ($success > 0) {
    echo "\nCreating search index...\n";
    $lists = max(1, (int) sqrt($success));
    try {
        $pdo->exec('DROP INDEX IF EXISTS idx_faqs_combined_embedding');
        $pdo->exec("
            CREATE INDEX idx_faqs_combined_embedding
            ON chatbot_faqs USING ivfflat (combined_embedding vector_cosine_ops)
            WITH (lists = {$lists})
        ");
        echo "✓ Search index created with {$lists} lists\n";
    } catch (PDOException $e) {
        echo "Warning: Could not create index: " . $e->getMessage() . "\n";
    }
}

echo "\nDone!\n";

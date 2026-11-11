<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Logger;

/**
 * کلاینت مدل هوش مصنوعی (API سازگار با OpenAI chat/completions).
 * بدنه‌ی درخواست: model + messages[system,user] + temperature + top_p + stream=false + max_tokens
 */
final class AiClient
{
    /**
     * @param array<int,array{role:string,content:string}> $messages
     * @return array{ok:bool,content:string,code:int,error:string,retry_after:int,rate_limited:bool}
     */
    public static function chat(array $messages): array
    {
        $cfg = (array) App::config('ai');
        $body = json_encode([
            'model'       => $cfg['model'],
            'messages'    => $messages,
            'temperature' => (float) $cfg['temperature'],
            'top_p'       => (float) $cfg['top_p'],
            'stream'      => false,
            'max_tokens'  => (int) $cfg['max_tokens'],
        ], JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Accept-Encoding: gzip, deflate',
        ];
        if (!empty($cfg['api_key'])) {
            $headers[] = 'Authorization: Bearer ' . $cfg['api_key'];
        }

        $retryAfter = 0;
        $ch = curl_init((string) $cfg['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_ENCODING       => '',
            CURLOPT_TIMEOUT        => (int) $cfg['timeout'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$retryAfter) {
                if (stripos($line, 'Retry-After:') === 0) {
                    $v = trim(substr($line, 12));
                    $retryAfter = is_numeric($v) ? (int) $v : max(0, (int) strtotime($v) - time());
                }
                return strlen($line);
            },
        ]);

        $started  = microtime(true);
        $response = curl_exec($ch);
        $code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        $result = ['ok' => false, 'content' => '', 'code' => $code, 'error' => '', 'retry_after' => $retryAfter, 'rate_limited' => false];

        if ($errno) {
            $result['error'] = "curl {$errno}: {$error}";
        } elseif ($code === 429) {
            $result['rate_limited'] = true;
            $result['error'] = 'HTTP 429';
        } elseif ($code !== 200) {
            $result['error'] = 'HTTP ' . $code . ' ' . mb_substr((string) $response, 0, 200);
        } else {
            $decoded = json_decode((string) $response, true);
            $content = $decoded['choices'][0]['message']['content'] ?? null;
            if (!is_string($content)) {
                $result['error'] = 'پاسخ نامعتبر از مدل';
            } else {
                $result['ok'] = true;
                $result['content'] = $content;
            }
        }

        Logger::info('درخواست AI', [
            'code' => $code,
            'ms'   => (int) ((microtime(true) - $started) * 1000),
            'ok'   => $result['ok'],
            'err'  => $result['error'],
        ]);
        return $result;
    }

    /** استخراج JSON از خروجی مدل (با حذف بلوک <think> و ```json). @return array<string,mixed>|null */
    public static function extractJson(string $text): ?array
    {
        $text = trim((string) preg_replace('/<think>.*?<\/think>/s', '', $text));
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $m)) {
            $d = json_decode($m[1], true);
            if (is_array($d)) {
                return $d;
            }
        }
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $d = json_decode($m[0], true);
            if (is_array($d)) {
                return $d;
            }
        }
        $d = json_decode($text, true);
        return is_array($d) ? $d : null;
    }
}

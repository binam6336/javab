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
    /** فایل لاگ کامل درخواست/پاسخ‌های ناموفق AI */
    public const HTTP_LOG = 'http-ai.log';
    private const HTTP_LOG_MAX_BYTES = 20 * 1024 * 1024;

    /**
     * @param array<int,array{role:string,content:string}> $messages
     * @return array{ok:bool,content:string,code:int,error:string,retry_after:int,rate_limited:bool,trace:array<string,mixed>}
     */
    public static function chat(array $messages): array
    {
        $cfg = (array) App::config('ai');
        if (!empty($cfg['require_key']) && trim((string) $cfg['api_key']) === '') {
            Logger::error('AI_API_KEY در فایل .env تنظیم نشده؛ درخواستی به AI ارسال نشد', ['url' => $cfg['url']]);
            return [
                'ok' => false, 'content' => '', 'code' => 401, 'error' => 'AI_API_KEY تنظیم نشده',
                'retry_after' => 0, 'rate_limited' => false, 'trace' => [],
            ];
        }
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
            $headers[] = 'Authorization: Bearer ' . trim((string) $cfg['api_key']);
        }

        $retryAfter = 0;
        $responseHeaders = [];
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
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$retryAfter, &$responseHeaders) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $responseHeaders[] = $trimmed;
                }
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
        $ms = (int) ((microtime(true) - $started) * 1000);

        $result = [
            'ok' => false, 'content' => '', 'code' => $code, 'error' => '', 'retry_after' => $retryAfter, 'rate_limited' => false,
            // اطلاعات کامل رفت‌وبرگشت؛ فقط برای لاگ http-ai
            'trace' => [
                'url'              => (string) $cfg['url'],
                'request_headers'  => self::maskHeaders($headers),
                'request_body'     => (string) $body,
                'http_code'        => $code,
                'curl_errno'       => $errno,
                'curl_error'       => $error,
                'duration_ms'      => $ms,
                'response_headers' => $responseHeaders,
                'response_body'    => is_string($response) ? $response : '',
            ],
        ];

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
            'ms'   => $ms,
            'ok'   => $result['ok'],
            'err'  => $result['error'],
        ]);
        if (!$result['ok']) {
            self::logHttp($result['error'], $result['trace']);
        }
        return $result;
    }

    /**
     * نوشتن لاگ کامل یک درخواست ناموفق در storage/logs/http-ai.log
     * (JSON درخواست و JSON پاسخ به‌صورت کامل و خوانا).
     * @param array<string,mixed> $trace
     */
    public static function logHttp(string $reason, array $trace): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . self::HTTP_LOG;
        if (is_file($file) && filesize($file) > self::HTTP_LOG_MAX_BYTES) {
            @rename($file, $dir . '/http-ai-' . date('Ymd-His') . '.log');
        }

        $sep = str_repeat('=', 80);
        $lines = [
            $sep,
            '[' . date('Y-m-d H:i:s') . '] AI request failed: ' . $reason,
            'URL:         POST ' . ($trace['url'] ?? ''),
            'HTTP code:   ' . ($trace['http_code'] ?? 0),
            'Duration:    ' . ($trace['duration_ms'] ?? 0) . ' ms',
        ];
        if (!empty($trace['curl_errno'])) {
            $lines[] = 'cURL error:  ' . $trace['curl_errno'] . ' ' . ($trace['curl_error'] ?? '');
        }
        $lines[] = '';
        $lines[] = '--- Request headers ---';
        $lines[] = implode("\n", (array) ($trace['request_headers'] ?? []));
        $lines[] = '';
        $lines[] = '--- Request JSON ---';
        $lines[] = self::prettyJson((string) ($trace['request_body'] ?? ''));
        $lines[] = '';
        $lines[] = '--- Response headers ---';
        $lines[] = implode("\n", (array) ($trace['response_headers'] ?? [])) ?: '(none)';
        $lines[] = '';
        $lines[] = '--- Response JSON ---';
        $resp = (string) ($trace['response_body'] ?? '');
        $lines[] = $resp === '' ? '(empty)' : self::prettyJson($resp);
        $lines[] = '';

        @file_put_contents($file, implode("\n", $lines) . "\n", FILE_APPEND | LOCK_EX);
    }

    private static function prettyJson(string $raw): string
    {
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $raw; // JSON نیست (مثلاً صفحه‌ی HTML خطا) → همان متن خام
        }
        return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param string[] $headers @return string[] کلید API در لاگ ماسک می‌شود */
    private static function maskHeaders(array $headers): array
    {
        return array_map(static function (string $h) {
            if (stripos($h, 'Authorization:') === 0) {
                $token = trim(substr($h, 14));
                $parts = explode(' ', $token, 2);
                $secret = $parts[1] ?? $parts[0];
                $masked = strlen($secret) > 8 ? substr($secret, 0, 4) . str_repeat('*', 8) . substr($secret, -4) : '********';
                return 'Authorization: ' . (isset($parts[1]) ? $parts[0] . ' ' : '') . $masked;
            }
            return $h;
        }, $headers);
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

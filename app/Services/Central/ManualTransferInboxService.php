<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\CentralSetting;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

class ManualTransferInboxService
{
    public function __construct(
        protected CentralAuditLogger $auditLogger,
        protected ManualTransferService $manualTransferService,
    ) {
    }

    public function fetchAndReconcileInvoice(Tenant $tenant, array $invoice): array
    {
        $invoiceNumber = (string) ($invoice['invoice_number'] ?? '');
        $expectedAmount = (int) data_get($invoice, 'payment.manual_transfer.expected_amount', 0);
        $config = $this->fetcherConfig();

        if (! (bool) ($config['enabled'] ?? false)) {
            return [
                'status' => 'fetcher_disabled',
                'message' => 'Auto fetch email transfer manual belum diaktifkan di System Settings.',
                'invoice_number' => $invoiceNumber,
            ];
        }

        if ($expectedAmount <= 0) {
            return [
                'status' => 'invalid',
                'message' => 'Invoice belum punya nominal transfer unik yang valid.',
                'invoice_number' => $invoiceNumber,
            ];
        }

        $runtimeAvailability = $this->imapRuntimeAvailability();

        if (! $runtimeAvailability['available']) {
            return [
                'status' => (string) ($runtimeAvailability['status'] ?? 'imap_unavailable'),
                'message' => (string) ($runtimeAvailability['message'] ?? 'Runtime IMAP belum tersedia di server.'),
                'invoice_number' => $invoiceNumber,
            ];
        }

        $mailbox = $this->mailboxConnectionString($config);
        $stream = @imap_open($mailbox, (string) $config['username'], (string) $config['password']);

        if (! $stream) {
            return [
                'status' => 'fetch_error',
                'message' => 'Gagal membuka inbox BCA: ' . $this->lastImapError(),
                'invoice_number' => $invoiceNumber,
            ];
        }

        try {
            foreach ($this->candidateMessages($stream, $config) as $message) {
                if (! $this->messageMatchesExpectedAmount((string) ($message['body_text'] ?? ''), $expectedAmount)) {
                    continue;
                }

                $payload = $this->buildEvidencePayload($message, $config, $expectedAmount);
                $result = $this->manualTransferService->reconcileBcaEvidence($payload);

                if (($result['status'] ?? '') === 'matched_auto') {
                    return $result + [
                        'fetched_subject' => (string) ($message['subject'] ?? ''),
                        'invoice_number' => (string) data_get($result, 'invoice.invoice_number', $invoiceNumber),
                    ];
                }

                if (($result['status'] ?? '') === 'duplicate') {
                    return $result + [
                        'invoice_number' => $invoiceNumber,
                    ];
                }
            }
        } finally {
            @imap_close($stream);
        }

        $this->auditLogger->warning('manual_transfer.bca_fetch_no_match', 'Auto fetch email BCA belum menemukan bukti transfer yang cocok.', [
            'target_type' => 'tenant',
            'target_id' => (string) $tenant->id,
            'meta' => [
                'invoice_number' => $invoiceNumber,
                'expected_amount' => $expectedAmount,
            ],
        ]);

        return [
            'status' => 'no_match',
            'message' => sprintf(
                'Belum ditemukan email transfer BCA dengan nominal exact Rp%s di inbox.',
                number_format($expectedAmount, 0, ',', '.')
            ),
            'expected_amount' => $expectedAmount,
            'invoice_number' => $invoiceNumber,
        ];
    }

    public function testConnection(array $config): array
    {
        $runtimeAvailability = $this->imapRuntimeAvailability();

        if (! $runtimeAvailability['available']) {
            return [
                'status' => 'danger',
                'title' => (string) ($runtimeAvailability['title'] ?? 'IMAP belum tersedia'),
                'message' => (string) ($runtimeAvailability['message'] ?? 'Runtime IMAP belum aktif di server.'),
            ];
        }

        $mailbox = $this->mailboxConnectionString($config);
        $stream = @imap_open($mailbox, (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''));

        if (! $stream) {
            return [
                'status' => 'danger',
                'title' => 'Inbox BCA gagal dihubungi',
                'message' => 'Koneksi ke mailbox gagal dibuka.',
                'details' => [
                    'Error' => $this->lastImapError(),
                    'Mailbox' => $mailbox,
                ],
            ];
        }

        try {
            $check = @imap_check($stream);
        } finally {
            @imap_close($stream);
        }

        return [
            'status' => 'success',
            'title' => 'Inbox BCA siap dipakai',
            'message' => 'Koneksi IMAP berhasil dibuka. Auto fetch transfer manual siap digunakan.',
            'details' => [
                'Mailbox' => $mailbox,
                'Messages' => (string) (($check->Nmsgs ?? 0)),
            ],
        ];
    }

    protected function fetcherConfig(): array
    {
        return (array) data_get(CentralSetting::paymentMethodSettings(), 'manual_transfer.bca_email_fetcher', []);
    }

    protected function imapRuntimeAvailability(): array
    {
        if (! extension_loaded('imap')) {
            return [
                'available' => false,
                'status' => 'imap_extension_missing',
                'title' => 'IMAP extension belum aktif',
                'message' => 'Extension IMAP belum aktif di runtime PHP web/server.',
            ];
        }

        if (function_exists('imap_open')) {
            return [
                'available' => true,
            ];
        }

        $disabledFunctions = array_map(
            static fn (string $functionName): string => trim($functionName),
            explode(',', (string) ini_get('disable_functions'))
        );

        if (in_array('imap_open', $disabledFunctions, true)) {
            return [
                'available' => false,
                'status' => 'imap_function_disabled',
                'title' => 'IMAP diblok di disable_functions',
                'message' => 'Extension IMAP aktif, tapi fungsi imap_open diblokir di disable_functions PHP-FPM/web.',
            ];
        }

        return [
            'available' => false,
            'status' => 'imap_unavailable',
            'title' => 'IMAP belum tersedia',
            'message' => 'Runtime IMAP belum tersedia di server.',
        ];
    }

    protected function mailboxConnectionString(array $config): string
    {
        $host = trim((string) ($config['host'] ?? ''));
        $port = max((int) ($config['port'] ?? 993), 1);
        $folder = trim((string) ($config['folder'] ?? 'INBOX')) ?: 'INBOX';
        $encryption = strtolower(trim((string) ($config['encryption'] ?? 'ssl')));
        $validateCertificate = (bool) ($config['validate_certificate'] ?? false);

        $flags = ['/imap'];

        if ($encryption === 'ssl') {
            $flags[] = '/ssl';
        } elseif ($encryption === 'tls') {
            $flags[] = '/tls';
        }

        if (! $validateCertificate) {
            $flags[] = '/novalidate-cert';
        }

        return sprintf('{%s:%d%s}%s', $host, $port, implode('', $flags), $folder);
    }

    protected function candidateMessages($stream, array $config): array
    {
        $criteria = (bool) ($config['unseen_only'] ?? true) ? 'UNSEEN' : 'ALL';
        $messageNumbers = @imap_search($stream, $criteria) ?: @imap_search($stream, 'ALL') ?: [];
        rsort($messageNumbers);

        $lookbackMinutes = max((int) ($config['lookback_minutes'] ?? 60), 1);
        $maxMessages = max((int) ($config['max_messages'] ?? 20), 1);
        $lookbackThreshold = CarbonImmutable::now()->subMinutes($lookbackMinutes);
        $senderFilter = strtolower(trim((string) ($config['sender_filter'] ?? '')));
        $subjectKeyword = strtolower(trim((string) ($config['subject_keyword'] ?? '')));
        $messages = [];

        foreach (array_slice($messageNumbers, 0, $maxMessages) as $messageNumber) {
            $overview = imap_fetch_overview($stream, (string) $messageNumber, 0)[0] ?? null;
            if (! is_object($overview)) {
                continue;
            }

            $from = (string) ($overview->from ?? '');
            $subject = (string) ($overview->subject ?? '');
            $sentAt = $this->overviewDateToCarbon($overview);

            if ($sentAt instanceof CarbonImmutable && $sentAt->lessThan($lookbackThreshold)) {
                continue;
            }

            if ($senderFilter !== '' && ! str_contains(strtolower($from), $senderFilter)) {
                continue;
            }

            if ($subjectKeyword !== '' && ! str_contains(strtolower($subject), $subjectKeyword)) {
                continue;
            }

            $bodyText = $this->extractBodyText($stream, (int) $messageNumber);

            $messages[] = [
                'message_number' => (int) $messageNumber,
                'message_id' => $this->normalizeMessageId((string) ($overview->message_id ?? '')),
                'from' => $from,
                'subject' => $subject,
                'date' => $sentAt?->toIso8601String(),
                'body_text' => $bodyText,
            ];
        }

        return $messages;
    }

    protected function buildEvidencePayload(array $message, array $config, int $expectedAmount): array
    {
        $accountNumber = $this->extractAccountNumber((string) ($message['body_text'] ?? ''))
            ?: preg_replace('/\D+/', '', (string) data_get(CentralSetting::paymentMethodSettings(), 'manual_transfer.account_number', ''))
            ?: '';
        $messageId = (string) ($message['message_id'] ?? '');
        $fromAddress = (string) ($message['from'] ?? '');
        $senderName = $this->extractSenderName($fromAddress);
        $transactionAt = (string) ($message['date'] ?? CarbonImmutable::now()->toIso8601String());

        return [
            'message_id' => $messageId !== '' ? $messageId : sha1(($message['subject'] ?? '') . '|' . $expectedAmount . '|' . ($message['date'] ?? '')),
            'source_adapter' => 'bca_email_imap',
            'from_address' => $fromAddress,
            'sender_name' => $senderName,
            'parsed_payload' => [
                'message_id' => $messageId,
                'account_number' => $accountNumber,
                'credit_amount' => $expectedAmount,
                'transaction_at' => $transactionAt,
                'sender_name' => $senderName,
                'from_address' => $fromAddress,
                'ws_ref' => $this->extractReference((string) ($message['body_text'] ?? '')),
                'mailbox_host' => (string) ($config['host'] ?? ''),
                'subject' => (string) ($message['subject'] ?? ''),
            ],
            'raw_body' => (string) ($message['body_text'] ?? ''),
        ];
    }

    protected function messageMatchesExpectedAmount(string $body, int $expectedAmount): bool
    {
        if ($expectedAmount <= 0) {
            return false;
        }

        $variants = array_unique(array_filter([
            (string) $expectedAmount,
            number_format($expectedAmount, 0, ',', '.'),
            number_format($expectedAmount, 0, '.', ','),
            number_format($expectedAmount, 2, ',', '.'),
            number_format($expectedAmount, 2, '.', ','),
            'Rp' . number_format($expectedAmount, 0, ',', '.'),
            'Rp ' . number_format($expectedAmount, 0, ',', '.'),
            'IDR ' . number_format($expectedAmount, 0, ',', '.'),
        ]));

        foreach ($variants as $variant) {
            if (preg_match('/(?<!\d)' . preg_quote($variant, '/') . '(?!\d)/i', $body) === 1) {
                return true;
            }
        }

        return false;
    }

    protected function extractBodyText($stream, int $messageNumber): string
    {
        $structure = @imap_fetchstructure($stream, $messageNumber);

        if (! is_object($structure)) {
            return $this->normalizeDecodedBody((string) @imap_body($stream, $messageNumber));
        }

        $body = $this->extractTextFromStructure($stream, $messageNumber, $structure);

        return $body !== ''
            ? $body
            : $this->normalizeDecodedBody((string) @imap_body($stream, $messageNumber));
    }

    protected function extractTextFromStructure($stream, int $messageNumber, object $structure, string $partNumber = ''): string
    {
        $collected = '';

        if ((int) ($structure->type ?? 0) === 0) {
            $targetPart = $partNumber !== '' ? $partNumber : '1';
            $body = (string) @imap_fetchbody($stream, $messageNumber, $targetPart);

            if ($body === '' && $partNumber === '') {
                $body = (string) @imap_body($stream, $messageNumber);
            }

            return $this->decodePartBody($body, (int) ($structure->encoding ?? 0), (string) ($structure->subtype ?? 'PLAIN'));
        }

        if (isset($structure->parts) && is_array($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                if (! is_object($part)) {
                    continue;
                }

                $nestedPartNumber = $partNumber === ''
                    ? (string) ($index + 1)
                    : $partNumber . '.' . ($index + 1);

                $collected .= "\n" . $this->extractTextFromStructure($stream, $messageNumber, $part, $nestedPartNumber);
            }
        }

        return trim($collected);
    }

    protected function decodePartBody(string $body, int $encoding, string $subtype): string
    {
        $decoded = match ($encoding) {
            3 => base64_decode($body, true) ?: $body,
            4 => quoted_printable_decode($body),
            default => $body,
        };

        return $this->normalizeDecodedBody(
            strtoupper($subtype) === 'HTML'
                ? strip_tags($decoded)
                : $decoded
        );
    }

    protected function normalizeDecodedBody(string $body): string
    {
        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = preg_replace("/\r\n?/", "\n", $body) ?? $body;
        $body = preg_replace("/[ \t]+/", ' ', $body) ?? $body;
        $body = preg_replace("/\n{3,}/", "\n\n", $body) ?? $body;

        return trim($body);
    }

    protected function extractAccountNumber(string $body): string
    {
        if (preg_match_all('/(?<!\d)(\d{10,20})(?!\d)/', $body, $matches) !== 1) {
            return '';
        }

        return (string) ($matches[1][0] ?? '');
    }

    protected function extractReference(string $body): string
    {
        if (preg_match('/\b(?:WS[- ]?REF|REF(?:ERENCE)?)[^\w]?[:#-]?\s*([A-Z0-9\-]+)/i', $body, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    protected function extractSenderName(string $fromAddress): string
    {
        if (preg_match('/^"?([^"<]+)"?\s*</', $fromAddress, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return trim(str_replace(['<', '>'], '', $fromAddress));
    }

    protected function normalizeMessageId(string $messageId): string
    {
        return trim($messageId, " \t\n\r\0\x0B<>");
    }

    protected function overviewDateToCarbon(object $overview): ?CarbonImmutable
    {
        $date = trim((string) ($overview->date ?? ''));

        if ($date === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function lastImapError(): string
    {
        $errors = imap_errors() ?: [];
        $lastError = end($errors);

        return is_string($lastError) && $lastError !== ''
            ? $lastError
            : 'Unknown IMAP error';
    }
}

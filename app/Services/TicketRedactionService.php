<?php

declare(strict_types=1);

namespace App\Services;

class TicketRedactionService
{
    /**
     * Redact PII before sending to AI.
     */
    public function redact(string $text): string
    {
        $text = $this->maskCpf($text);
        $text = $this->maskCnpj($text);
        $text = $this->maskEmail($text);
        $text = $this->maskPhone($text);

        return $text;
    }

    private function maskCpf(string $text): string
    {
        return preg_replace(
            '/\b(\d{3})\.?(\d{3})\.?(\d{3})-?(\d{2})\b/',
            '$1.***.***-**',
            $text
        );
    }

    private function maskCnpj(string $text): string
    {
        return preg_replace(
            '/\b(\d{2})\.?(\d{3})\.?(\d{3})\/?(\d{4})-?(\d{2})\b/',
            '$1.***.***/****-**',
            $text
        );
    }

    private function maskEmail(string $text): string
    {
        return preg_replace_callback(
            '/\b([a-zA-Z0-9._%+-]+)@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\b/',
            fn ($m) => substr($m[1], 0, 2) . '***@' . substr($m[2], 0, 2) . '***',
            $text
        );
    }

    private function maskPhone(string $text): string
    {
        return preg_replace(
            '/\b(\d{2,3})[\s.-]?(\d{4,5})[\s.-]?(\d{4})\b/',
            '***-****-$3',
            $text
        );
    }
}

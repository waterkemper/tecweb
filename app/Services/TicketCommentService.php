<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ZdTicket;
use Illuminate\Http\UploadedFile;

class TicketCommentService
{
    /**
     * @param UploadedFile[] $files
     * @return string[] upload tokens
     */
    public function uploadAttachments(array $files, ZendeskClient $client, int $maxFileSize = 52428800): array
    {
        $uploadTokens = [];

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            if ($file->getSize() > $maxFileSize) {
                throw new \RuntimeException('O arquivo "' . $file->getClientOriginalName() . '" excede o limite de 50 MB.');
            }

            $content = $file->get();
            $filename = $file->getClientOriginalName() ?: 'attachment';
            $contentType = $file->getMimeType() ?: 'application/octet-stream';
            $token = $client->uploadFile($filename, $content, $contentType);
            if ($token !== null) {
                $uploadTokens[] = $token;
            }
        }

        return $uploadTokens;
    }

    public function addComment(
        ZdTicket $ticket,
        string $body,
        bool $isPublic,
        array $uploadTokens,
        ?int $authorId,
        ZendeskClient $client
    ): void {
        $client->addTicketComment((int) $ticket->zd_id, $body, $isPublic, $uploadTokens, $authorId);
    }
}

<?php

namespace App\Support\Uploads;

use RuntimeException;

/**
 * An upload that breaks a rule. $reason is a key under lang/{locale}/uploads.php; the controller answers 422 with
 * that message and never stores anything.
 */
final class UploadRefused extends RuntimeException
{
    /**
     * @param  array<string, int|string>  $params
     */
    public function __construct(public readonly string $reason, public readonly array $params = [])
    {
        parent::__construct('Upload refused: '.$reason);
    }

    public function text(): string
    {
        $text = __('uploads.'.$this->reason, $this->params);

        return is_string($text) ? $text : 'uploads.'.$this->reason;
    }
}

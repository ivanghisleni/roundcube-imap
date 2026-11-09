<?php

namespace bjc\roundcubeimap\tests;

/**
 * A stream that plays a scripted server and records what the client sends. Each answer is released
 * only once the client sent its command, as a server would: PHP drops the read buffer of a stream
 * it writes to, so an answer read ahead of its command would be lost. The connection ends where the
 * last answer ends: an answer cut short is a connection lost at that byte.
 */

class scripted_stream {

    public const SCHEME = 'scriptedimap';

    /** @var string[] */
    public static array $next = [];

    public $context;

    /** @var string[] answers, one per command */
    private array $answers = [];
    private string $released = '';
    private int $position = 0;
    public string $sent = '';

    public static function register(): void {
        if (!in_array(self::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::SCHEME, self::class);
        }
    }

    public function stream_open($path, $mode, $options, &$opened_path): bool {
        $this->answers = self::$next;
        return true;
    }

    public function stream_read($count): string {
        $chunk = substr($this->released, $this->position, $count);
        $this->position += strlen($chunk);
        return $chunk;
    }

    public function stream_write($data): int {
        $this->sent .= $data;
        // a command is complete at its CRLF: its answer can now be read
        for ($i = substr_count($data, "\r\n"); $i > 0 AND $this->answers; $i--) {
            $this->released .= array_shift($this->answers);
        }
        return strlen($data);
    }

    public function stream_eof(): bool {
        return !$this->answers AND $this->position >= strlen($this->released);
    }

    public function stream_set_option($option, $arg1, $arg2): bool {
        return false;
    }

    public function stream_close(): void {
    }

}

<?php

namespace bjc\roundcubeimap\tests;

/**
 * An imap_generic connected to a scripted_stream instead of a socket: the protocol code runs as it
 * does against a server, on the bytes the test scripts
 */

class scripted_imap_generic extends \bjc\roundcubeimap\imap_generic {

    private scripted_stream $stream;

    /**
     * @param string[]    $answers  What the server answers to each command, in order
     * @param string|null $selected Mailbox already selected (no EXAMINE expected), with its mode
     */
    public function __construct(array $answers, ?string $selected = null, bool $selectedReadWrite = false) {
        scripted_stream::register();
        scripted_stream::$next = $answers;

        $this->fp    = fopen(scripted_stream::SCHEME . '://server', 'r+');
        $this->prefs = ['timeout' => 0];

        $this->stream = stream_get_meta_data($this->fp)['wrapper_data'];

        if ($selected !== null) {
            $this->selected           = $selected;
            $this->data['READ-WRITE'] = $selectedReadWrite;
        }
    }

    /**
     * Commands the client sent, one per line, without the CRLF
     */
    public function sent(): array {
        return array_values(array_filter(explode("\r\n", $this->stream->sent), 'strlen'));
    }

    public function isConnected(): bool {
        return (bool) $this->fp;
    }

}

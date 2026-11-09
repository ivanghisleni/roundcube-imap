<?php

namespace bjc\roundcubeimap;

/**
 * Extends the rcube_imap_generic class with further imap specific code (to not touch the original from roundcube)
 * Object that holds the connection to the server and performs actions
 *
 */

class imap_generic extends \rcube_imap_generic {

    /**
     * Fetch the full MIME message of a given mailbox and uid
     *
     * @param string $mailbox   Name of mailbox in logged in account
     * @param int    $uid       UID
     * @param bool   $readOnly  Selecting read-only mode
     *
     * @return string message data
     */

    public function fetchMimemessage($mailbox, $uid, $readOnly = true) {

        if (!$this->select($mailbox, null, $readOnly)) {
            return false;
        }

        $key      = $this->nextTag();
        $cmd      = 'UID ' . 'FETCH';
        $request  = "$key $cmd $uid RFC822";

        if (!$this->putLine($request)) {
            $this->setError(self::ERROR_COMMAND, "Failed to send $cmd command");
            return false;
        }

        $message = "";

        do {
            $line = $this->readFullLine(4096);
            
            if (!$line) {
                break;
            }

            $message .= $line . "\r\n";
            
        }
        while (!$this->startsWith($line, $key, true));

        $replace_match = '/^(?:A[0-9]+ OK FETCH .*|\* [0-9]+ FETCH .*|\)\s*)$(?:\r\n|\n)?/im';
        $message_result = preg_replace($replace_match, '', $message);

        return $message_result;

    }

    /**
     * Fetch the raw header block of a given mailbox and uid, without the body
     *
     * BODY.PEEK[HEADER] leaves the \Seen flag alone. The block is returned as the server sent it,
     * with the blank line ending it when the message has one, so that a MIME parser reads it as it
     * would read the full message. Only the tagged OK makes it a result: a connection lost halfway would otherwise hand
     * back a block cut short that nothing tells apart from a complete one.
     *
     * @param string     $mailbox   Name of mailbox in logged in account
     * @param int|string $uid       UID, a single one: no sequence set
     * @param bool       $readOnly  Selecting read-only mode
     *
     * @return string|null|false header block, null when no message has that UID, false on error
     */

    public function fetchMimeheader($mailbox, $uid, $readOnly = true): string|null|false {

        if (!preg_match('/^[1-9][0-9]*$/', (string) $uid)) {
            $this->setError(self::ERROR_COMMAND, "Invalid UID: $uid");
            return false;
        }

        if (!$this->select($mailbox, null, $readOnly)) {
            return false;
        }

        $key     = $this->nextTag();
        $request = "$key UID FETCH $uid (UID BODY.PEEK[HEADER])";

        if (!$this->putLine($request)) {
            $this->setError(self::ERROR_COMMAND, "Failed to send UID FETCH command");
            return false;
        }

        $header = null;

        do {
            $line = $this->readCompleteLine(4096);

            if ($line === null) {
                $this->setError(self::ERROR_COMMAND, "Connection lost before the UID FETCH response ended");
                return false;
            }

            if (preg_match('/^\* [0-9]+ FETCH /', $line, $m)) {
                $rest  = substr($line, strlen($m[0]));
                $items = self::tokenizeResponse($rest, 1);
                $items = is_array($items) ? $items : [];

                $data = [];
                for ($i = 0; $i + 1 < count($items); $i += 2) {
                    if (is_string($items[$i])) {
                        $data[strtoupper($items[$i])] = $items[$i + 1];
                    }
                }

                // Other messages may get an unsolicited FETCH (a flag changed): only BODY[HEADER] answers
                // this request, and the UID, when the server sends it, has to be the one asked for
                if (array_key_exists('BODY[HEADER]', $data) AND (!isset($data['UID']) OR (string) $data['UID'] === (string) $uid)) {
                    if (!is_string($data['BODY[HEADER]'])) {
                        $this->setError(self::ERROR_COMMAND, "UID FETCH returned no header for UID $uid");
                        return false;
                    }
                    $header = $data['BODY[HEADER]'];
                }
            }
        }
        while (!$this->startsWith($line, $key, true));

        if (!$this->fp OR !$this->startsWith($line, $key) OR $this->parseResult($line, 'UID FETCH: ') != self::ERROR_OK) {
            if ($this->errornum == self::ERROR_OK) {
                $this->setError(self::ERROR_BAD, "UID FETCH failed: " . trim($line));
            }
            return false;
        }

        return $header;

    }


    /**
     * readFullLine() for a response whose literal must arrive whole. On a connection lost inside a
     * literal readFullLine() never returns (readBytes() answers '' at EOF, the loop only stops on
     * null), and a line cut short comes back as if it were complete.
     *
     * @param int $size Max size of a line read at once
     *
     * @return string|null line with its literals, null when the connection ended first
     */

    protected function readCompleteLine($size = 1024): ?string {

        $line = $this->readLine($size);

        while (true) {
            if ($line === null OR substr($line, -1) !== "\n") {
                return null;
            }

            if (!preg_match('/\{([0-9]+)\}\r\n$/', $line, $m)) {
                return $line;
            }

            $bytes   = (int) $m[1];
            $literal = $bytes > 0 ? $this->readBytes($bytes) : '';

            if (strlen($literal) < $bytes) {
                return null;
            }

            $rest = $this->readLine($size);
            $line = $rest === null ? null : $line . $literal . $rest;
        }

    }

}

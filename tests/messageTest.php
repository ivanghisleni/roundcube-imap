<?php

namespace bjc\roundcubeimap\tests;

use bjc\roundcubeimap\message;
use PHPUnit\Framework\TestCase;

class messageTest extends TestCase {

    private static function message(scripted_imap_generic $imap, string $mailbox, int $uid): message {
        $header      = new \rcube_message_header();
        $header->uid = $uid;

        return new message($imap, $header, $mailbox);
    }

    public function testGetMimeheaderFetchesTheHeaderOfThisMessageInItsFolder(): void {
        $block = "Subject: s\r\n\r\n";
        $imap  = new scripted_imap_generic([
            "A0001 OK [READ-ONLY] Examine completed\r\n",
            "* 1 FETCH (UID 42 BODY[HEADER] {" . strlen($block) . "}\r\n$block)\r\nA0002 OK done\r\n",
        ]);

        $this->assertSame($block, self::message($imap, 'Posta inviata', 42)->getMimeheader());
        $this->assertSame(['A0001 EXAMINE "Posta inviata"', 'A0002 UID FETCH 42 (UID BODY.PEEK[HEADER])'], $imap->sent());
    }

    public function testGetMimeheaderSelectsReadWriteWhenAsked(): void {
        $imap = new scripted_imap_generic(["A0001 OK [READ-WRITE] Select completed\r\n", "A0002 OK done\r\n"]);

        $this->assertNull(self::message($imap, 'INBOX', 42)->getMimeheader(false));
        $this->assertSame('A0001 SELECT INBOX', $imap->sent()[0]);
    }

    public function testGetMimeheaderReportsAFailedFetch(): void {
        $imap = new scripted_imap_generic(["A0001 NO [SERVERBUG] Internal error\r\n"], 'INBOX');

        $this->assertFalse(self::message($imap, 'INBOX', 42)->getMimeheader());
    }

}

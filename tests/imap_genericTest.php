<?php

namespace bjc\roundcubeimap\tests;

use PHPUnit\Framework\TestCase;

class imap_genericTest extends TestCase {

    // A header block as a server sends it: folded lines, 8-bit bytes, and characters that mean
    // something to the IMAP tokenizer, which must stay inside the literal
    private const HEADER = "Return-Path: <a@example.com>\r\n"
        . "Received: from mx.example.com (mx.example.com [192.0.2.1])\r\n"
        . "\tby imap.example.com with ESMTPS id 42 {5}\r\n"
        . "\tfor <b@example.com>; Mon, 9 Nov 2026 10:00:00 +0100\r\n"
        . "From: \"Doe, John (sales)\" <a@example.com>\r\n"
        . "To: b@example.com\r\n"
        . "Subject: =?utf-8?q?Caff=C3=A8?= (x) \"y\" ) NIL\r\n"
        . "X-Raw: caff\xC3\xA8\r\n"
        . "Message-ID: <>\r\n"
        . "\r\n";

    private const EXAMINE_OK = "* 3 EXISTS\r\n* OK [UIDVALIDITY 200] UIDs valid\r\nA0001 OK [READ-ONLY] Examine completed\r\n";

    private static function literal(string $data): string {
        return '{' . strlen($data) . "}\r\n" . $data;
    }

    private static function headerAnswer(string $tag, string $header = self::HEADER, string $before = 'UID 7 ', string $after = ''): string {
        return "* 2 FETCH ({$before}BODY[HEADER] " . self::literal($header) . "$after)\r\n$tag OK Fetch completed\r\n";
    }

    public function testFetchesTheRawHeaderBlockByteForByte(): void {
        $imap = new scripted_imap_generic([self::EXAMINE_OK, self::headerAnswer('A0002')]);

        $this->assertSame(self::HEADER, $imap->fetchMimeheader('INBOX', 7));
    }

    // PEEK: reading the header must not mark the message as seen; EXAMINE: nor open the folder read-write
    public function testSendsAReadOnlyPeekForThatUidOnly(): void {
        $imap = new scripted_imap_generic([self::EXAMINE_OK, self::headerAnswer('A0002')]);

        $imap->fetchMimeheader('INBOX', 7);

        $this->assertSame(['A0001 EXAMINE INBOX', 'A0002 UID FETCH 7 (UID BODY.PEEK[HEADER])'], $imap->sent());
    }

    public function testSelectsReadWriteWhenAsked(): void {
        $imap = new scripted_imap_generic(["* OK [UIDVALIDITY 200] UIDs valid\r\nA0001 OK [READ-WRITE] Select completed\r\n", self::headerAnswer('A0002')]);

        $this->assertSame(self::HEADER, $imap->fetchMimeheader('INBOX', 7, false));
        $this->assertSame('A0001 SELECT INBOX', $imap->sent()[0]);
    }

    public function testReusesTheFolderAlreadySelected(): void {
        $imap = new scripted_imap_generic([self::headerAnswer('A0001')], 'INBOX');

        $this->assertSame(self::HEADER, $imap->fetchMimeheader('INBOX', '7'));
        $this->assertSame(['A0001 UID FETCH 7 (UID BODY.PEEK[HEADER])'], $imap->sent());
    }

    public function testReturnsFalseWhenTheFolderCannotBeSelected(): void {
        $imap = new scripted_imap_generic(["A0001 NO Mailbox doesn't exist\r\n", "A0002 NO Mailbox doesn't exist\r\n"]);

        $this->assertFalse($imap->fetchMimeheader('Gone', 7));
        $this->assertSame(['A0001 EXAMINE Gone', 'A0002 SELECT Gone'], $imap->sent());
    }

    // Exchange sends UID after the body, others before it
    public function testReadsTheItemsInAnyOrder(): void {
        $imap = new scripted_imap_generic([self::headerAnswer('A0001', self::HEADER, '', ' UID 7')], 'INBOX');

        $this->assertSame(self::HEADER, $imap->fetchMimeheader('INBOX', 7));
    }

    public function testAcceptsAnAnswerWithoutUid(): void {
        $imap = new scripted_imap_generic([self::headerAnswer('A0001', self::HEADER, '')], 'INBOX');

        $this->assertSame(self::HEADER, $imap->fetchMimeheader('INBOX', 7));
    }

    public function testReadsTheItemNameCaseInsensitively(): void {
        $answer = "* 2 FETCH (uid 7 body[header] " . self::literal(self::HEADER) . ")\r\nA0001 OK done\r\n";
        $imap = new scripted_imap_generic([$answer], 'INBOX');

        $this->assertSame(self::HEADER, $imap->fetchMimeheader('INBOX', 7));
    }

    // Another client changing flags meanwhile: the server may push their FETCH into this response
    public function testIgnoresUnsolicitedFetchResponses(): void {
        $answer = "* 1 FETCH (FLAGS (\\Seen) UID 3)\r\n"
            . "* 2 FETCH (UID 7 BODY[HEADER] " . self::literal(self::HEADER) . ")\r\n"
            . "* 3 FETCH (UID 9 FLAGS (\\Deleted))\r\n"
            . "* 4 EXPUNGE\r\n"
            . "A0001 OK Fetch completed\r\n";
        $imap = new scripted_imap_generic([$answer], 'INBOX');

        $this->assertSame(self::HEADER, $imap->fetchMimeheader('INBOX', 7));
    }

    public function testIgnoresAHeaderAnsweredForAnotherUid(): void {
        $imap = new scripted_imap_generic([self::headerAnswer('A0001', 'Subject: other' . "\r\n\r\n", 'UID 8 ')], 'INBOX');

        $this->assertNull($imap->fetchMimeheader('INBOX', 7));
    }

    // RFC 3501: a UID FETCH of a UID the folder does not hold is not an error, it answers nothing
    public function testReturnsNullForAUidNotOnTheServer(): void {
        $imap = new scripted_imap_generic(["A0001 OK Fetch completed\r\n"], 'INBOX');

        $this->assertNull($imap->fetchMimeheader('INBOX', 7));
    }

    public function testReturnsAnEmptyHeaderAsSuch(): void {
        $imap = new scripted_imap_generic(["* 2 FETCH (UID 7 BODY[HEADER] {0}\r\n)\r\nA0001 OK done\r\n"], 'INBOX');

        $this->assertSame('', $imap->fetchMimeheader('INBOX', 7));
    }

    public function testReadsAQuotedHeader(): void {
        $imap = new scripted_imap_generic(["* 2 FETCH (UID 7 BODY[HEADER] \"\")\r\nA0001 OK done\r\n"], 'INBOX');

        $this->assertSame('', $imap->fetchMimeheader('INBOX', 7));
    }

    public function testReturnsFalseOnANilHeader(): void {
        $imap = new scripted_imap_generic(["* 2 FETCH (UID 7 BODY[HEADER] NIL)\r\nA0001 OK done\r\n"], 'INBOX');

        $this->assertFalse($imap->fetchMimeheader('INBOX', 7));
    }

    // Far larger than the 4096 bytes read per line and the 8192 of a stream chunk
    public function testReadsAHeaderLargerThanTheReadBuffers(): void {
        $header = str_repeat("Received: from relay.example.com by mx.example.com; Mon, 9 Nov 2026 10:00:00 +0100\r\n", 3000)
            . "Subject: big\r\n\r\n";
        $imap = new scripted_imap_generic([self::headerAnswer('A0001', $header)], 'INBOX');

        $this->assertSame($header, $imap->fetchMimeheader('INBOX', 7));
    }

    // Exchange answers NO for a UID expunged by another session
    public function testReturnsFalseOnNo(): void {
        $imap = new scripted_imap_generic(["A0001 NO [EXPUNGEISSUED] Some messages were expunged\r\n"], 'INBOX');

        $this->assertFalse($imap->fetchMimeheader('INBOX', 7));
        $this->assertSame('EXPUNGEISSUED', $imap->resultcode);
        $this->assertSame(\rcube_imap_generic::ERROR_NO, $imap->errornum);
    }

    // A NO after the header was sent: the header may be what the server could read, not the message's
    public function testReturnsFalseOnNoEvenAfterTheHeader(): void {
        $answer = "* 2 FETCH (UID 7 BODY[HEADER] " . self::literal(self::HEADER) . ")\r\nA0001 NO Partial failure\r\n";
        $imap = new scripted_imap_generic([$answer], 'INBOX');

        $this->assertFalse($imap->fetchMimeheader('INBOX', 7));
    }

    public function testReturnsFalseOnBad(): void {
        $imap = new scripted_imap_generic(["A0001 BAD Error in IMAP command\r\n"], 'INBOX');

        $this->assertFalse($imap->fetchMimeheader('INBOX', 7));
        $this->assertSame(\rcube_imap_generic::ERROR_BAD, $imap->errornum);
    }

    public function testReturnsFalseOnAnUntaggedBad(): void {
        $imap = new scripted_imap_generic(["* BAD Internal error\r\n"], 'INBOX');

        $this->assertFalse($imap->fetchMimeheader('INBOX', 7));
        $this->assertNotSame(\rcube_imap_generic::ERROR_OK, $imap->errornum);
    }

    public function testReturnsFalseWhenTheServerSaysBye(): void {
        $imap = new scripted_imap_generic(["* BYE Server shutting down\r\n"], 'INBOX');

        $this->assertFalse($imap->fetchMimeheader('INBOX', 7));
        $this->assertFalse($imap->isConnected());
    }

    /**
     * The connection drops at every byte of a complete answer: the header is never returned cut
     * short, and the read never loops (readFullLine() spins forever on EOF inside a literal)
     */
    public function testNeverReturnsAHeaderCutShortWhereverTheConnectionDrops(): void {
        $answer = self::headerAnswer('A0001');

        for ($length = 0; $length < strlen($answer) - 1; $length++) {
            $imap = new scripted_imap_generic([substr($answer, 0, $length)], 'INBOX');

            $this->assertFalse($imap->fetchMimeheader('INBOX', 7), "answer cut at byte $length");
        }
    }

    public function testReturnsFalseWhenTheConnectionDropsBeforeTheTaggedOk(): void {
        $answer = "* 2 FETCH (UID 7 BODY[HEADER] " . self::literal(self::HEADER) . ")\r\n";
        $imap = new scripted_imap_generic([$answer], 'INBOX');

        $this->assertFalse($imap->fetchMimeheader('INBOX', 7));
    }

    /**
     * A sequence set would fetch several headers and the caller would get one of them
     */
    public function testRejectsAnythingButOneUidWithoutSendingIt(): void {
        foreach (['0', '-1', '1:*', '1,2', '*', '7 ', ' 7', '7)', '7 (BODY[])', '', '07', '1e3', 'abc'] as $uid) {
            $imap = new scripted_imap_generic([], 'INBOX');

            $this->assertFalse($imap->fetchMimeheader('INBOX', $uid), "UID '$uid'");
            $this->assertSame([], $imap->sent(), "UID '$uid'");
        }
    }

    public function testReturnsFalseWhenNotConnected(): void {
        $imap = new scripted_imap_generic([], 'INBOX');
        $imap->closeConnection();

        $this->assertFalse($imap->fetchMimeheader('INBOX', 7));
    }

    // Two fetches on one connection: each reads its own response, nothing is left in the stream
    public function testLeavesTheConnectionReadyForTheNextCommand(): void {
        $other = "Subject: second\r\n\r\n";
        $imap = new scripted_imap_generic([self::headerAnswer('A0001'), self::headerAnswer('A0002', $other, 'UID 8 ')], 'INBOX');

        $this->assertSame(self::HEADER, $imap->fetchMimeheader('INBOX', 7));
        $this->assertSame($other, $imap->fetchMimeheader('INBOX', 8));
    }

}

<?php

namespace bjc\roundcubeimap\tests;

use bjc\roundcubeimap\connection;
use PHPUnit\Framework\TestCase;

class connectionTest extends TestCase {

    private const NAMES = ['INBOX', 'Spam', 'Drafts', 'Archive', '2024', 'INBOX/Junk'];

    private const ATTRIBUTES = [
        'INBOX'      => ['\\HasChildren'],
        'Spam'       => ['\\HasNoChildren', '\\Junk'],
        'Drafts'     => ['\\HasNoChildren', '\\Drafts'],
        'Archive'    => ['\\HasNoChildren', '\\Archive'],
        '2024'       => ['\\HasNoChildren'],
        'INBOX/Junk' => ['\\HasNoChildren', '\\junk'],
    ];

    private const DOVECOT = ['SPECIAL-USE', 'LIST-EXTENDED', 'LIST-STATUS'];

    /**
     * rcube_imap_generic::listMailboxes() answers name => status whenever a RETURN option is
     * sent to a LIST-STATUS server; the plain list only comes back without RETURN options
     */
    private static function keyed(array $status = []): array {
        return array_fill_keys(self::NAMES, $status);
    }

    private static function names(array $mailboxes): array {
        return array_map(fn ($mailbox) => $mailbox->getName(), $mailboxes);
    }

    // The 0.4.0 regression: Dovecot answers RETURN (SPECIAL-USE) with name => [] and
    // getMailboxes() died in strtolower() on the array
    public function testListStatusServerReturnsFolderNames(): void {
        $imap = new fake_imap_generic(self::DOVECOT, self::keyed(), self::NAMES, self::ATTRIBUTES);

        $names = self::names((new connection($imap))->getMailboxes([]));

        $this->assertSame([['SPECIAL-USE']], $imap->listCalls);
        $this->assertSame(['INBOX', 'Archive', '2024'], $names);
        $this->assertContainsOnly('string', $names);
    }

    // A LIST-STATUS answer with actual STATUS items (MESSAGES, UIDNEXT, ...) has the same shape
    public function testListStatusServerWithStatusItemsReturnsFolderNames(): void {
        $imap = new fake_imap_generic(self::DOVECOT, self::keyed(['MESSAGES' => 3, 'UIDNEXT' => 42]), self::NAMES, self::ATTRIBUTES);

        $this->assertSame(['INBOX', 'Archive', '2024'], self::names((new connection($imap))->getMailboxes([])));
    }

    // PHP turns a numeric folder name used as array key into an int
    public function testNumericFolderNameStaysAString(): void {
        $imap = new fake_imap_generic(self::DOVECOT, self::keyed(), self::NAMES, self::ATTRIBUTES);

        $names = self::names((new connection($imap))->getMailboxes([], []));

        $this->assertContains('2024', $names, '', false, true);
        $this->assertSame(['2024'], array_values(array_filter($names, 'is_numeric')));
    }

    // Without LIST-STATUS the plain list of names comes back, whatever the RETURN options
    public function testPlainListServerReturnsFolderNames(): void {
        $imap = new fake_imap_generic(['SPECIAL-USE', 'LIST-EXTENDED'], self::NAMES, self::NAMES, self::ATTRIBUTES);

        $this->assertSame(['INBOX', 'Archive', '2024'], self::names((new connection($imap))->getMailboxes([])));
        $this->assertSame([['SPECIAL-USE']], $imap->listCalls);
    }

    // RFC 6154: RETURN (SPECIAL-USE) needs LIST-EXTENDED, without it the server answers BAD
    public function testSpecialUseWithoutListExtendedSendsNoReturnOption(): void {
        $imap = new fake_imap_generic(['SPECIAL-USE', 'LIST-STATUS'], self::keyed(), self::NAMES, self::ATTRIBUTES);

        $this->assertSame(['INBOX', 'Archive', '2024'], self::names((new connection($imap))->getMailboxes([])));
        $this->assertSame([[]], $imap->listCalls);
    }

    // A server without SPECIAL-USE still gets folders skipped by name
    public function testServerWithoutSpecialUseSkipsByName(): void {
        $imap = new fake_imap_generic([], self::NAMES, self::NAMES);

        $this->assertSame(['INBOX', 'Archive', '2024', 'INBOX/Junk'], self::names((new connection($imap))->getMailboxes()));
        $this->assertSame([], $imap->listCalls[0]);
    }

    // The name list is case-insensitive on both shapes
    public function testSkipByNameIsCaseInsensitive(): void {
        foreach ([self::keyed(), self::NAMES] as $listResult) {
            $imap = new fake_imap_generic(self::DOVECOT, $listResult, self::NAMES, self::ATTRIBUTES);

            $this->assertSame(['Spam', 'Drafts', '2024'], self::names((new connection($imap))->getMailboxes(['inbox', 'ARCHIVE', 'inbox/junk'], [])));
        }
    }

    // The attribute check is case-insensitive too: \JUNK matches both Spam (\Junk) and INBOX/Junk (\junk)
    public function testSkipByAttributeIsCaseInsensitive(): void {
        $imap = new fake_imap_generic(self::DOVECOT, self::keyed(), self::NAMES, self::ATTRIBUTES);

        $this->assertSame(['INBOX', 'Drafts', 'Archive', '2024'], self::names((new connection($imap))->getMailboxes([], ['\\JUNK'])));
    }

    public function testEmptyListsDisableSkipping(): void {
        $imap = new fake_imap_generic(self::DOVECOT, self::keyed(), self::NAMES, self::ATTRIBUTES);

        $this->assertSame(self::NAMES, self::names((new connection($imap))->getMailboxes([], [])));
    }

    // rcube_imap_generic never throws: a failed LIST is false, and used to be a foreach() warning
    public function testFailedListReturnsNoMailboxes(): void {
        $imap = new fake_imap_generic(self::DOVECOT, false, false, []);

        $this->assertSame([], (new connection($imap))->getMailboxes());
    }

    public function testMailboxesCarryTheConnectionData(): void {
        $imap = new fake_imap_generic(array_merge(self::DOVECOT, ['CONDSTORE']), self::keyed(), self::NAMES, self::ATTRIBUTES);

        $mailboxes = (new connection($imap))->getMailboxes([], []);

        $this->assertCount(count(self::NAMES), $mailboxes);
        $this->assertSame(['\\HasNoChildren', '\\Junk'], $mailboxes[1]->getAttributes());
    }

}

<?php

namespace bjc\roundcubeimap\tests;

/**
 * An imap_generic that never opens a socket: capabilities and the LIST answer are scripted,
 * and every listMailboxes() call is recorded with the RETURN options it was given
 */

class fake_imap_generic extends \bjc\roundcubeimap\imap_generic {

    public array $listCalls = [];

    /**
     * @param string[]     $capabilities   Capabilities the fake server advertises
     * @param array|false  $listResult     What listMailboxes() returns when RETURN options are passed
     * @param array|false  $plainResult    What listMailboxes() returns without RETURN options
     * @param array        $listAttributes name => LIST attributes, as rcube_imap_generic stores them in data['LIST']
     */
    public function __construct(private array $capabilities, private $listResult, private $plainResult, private array $listAttributes = []) {
    }

    public function getCapability($name) {
        return in_array($name, $this->capabilities, true);
    }

    public function enable($extension) {
        return [];
    }

    public function listMailboxes($ref, $mailbox, $return_opts = [], $select_opts = []) {
        $this->listCalls[] = $return_opts;
        $this->data['LIST'] = $this->listAttributes;

        return empty($return_opts) ? $this->plainResult : $this->listResult;
    }

}

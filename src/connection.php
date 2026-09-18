<?php

namespace bjc\roundcubeimap;

/**
 * Handles established connections to the IMAP server
 *
 * @param object       $rcube_imap_generic  object that holds the connection to the server and performs actions
 * 
 */

class connection {
    
    protected $rcube_imap_generic;
    protected $connection_data = array();
    protected $qresync;
       
    public function __construct(\bjc\roundcubeimap\imap_generic $rcube_imap_generic) {
        $this->rcube_imap_generic = $rcube_imap_generic;
        
        $this->connection_data["capabilities"]["qresync"] = $this->rcube_imap_generic->getCapability('QRESYNC');
        $this->connection_data["capabilities"]["condstore"] = $this->qresync ? true : $this->rcube_imap_generic->getCapability('CONDSTORE');
        
        if ($this->connection_data["capabilities"]["qresync"] OR $this->connection_data["capabilities"]["condstore"]) {
            $result_enable = $this->rcube_imap_generic->enable($this->connection_data["capabilities"]["qresync"] ? 'QRESYNC' : 'CONDSTORE');
            
            if ($result_enable === false) {
                $this->connection_data["qresync_enable_failed"] = 1;
            }
            
        }
        
    }
    
    /**
     * Get all mailboxes of the account the connection is established to
     *
     * A mailbox is skipped when its full name is in $skipFolders (case-insensitive) or when it
     * carries one of the LIST attributes in $skipAttributes (case-insensitive). The attribute check
     * relies on RFC 6154 SPECIAL-USE and works whatever the folder is called; the name list is the
     * fallback for servers that do not implement it. Pass [] to disable either check.
     *
     * @param array $skipFolders    Mailbox names to skip
     * @param array $skipAttributes LIST attributes to skip, e.g. ['\Junk', '\Drafts', '\Trash']
     *
     * @return array containing objects of class \bjc\roundcubeimap\mailbox
     */
    
    public function getMailboxes($skipFolders = ['Drafts', 'Draft', 'Bozze', 'Junk', 'Junk Email', 'Posta indesiderata', 'Spam'], array $skipAttributes = ['\\Junk', '\\Drafts']) {

        // Ask for special-use attributes explicitly where the server supports the extended LIST syntax;
        // servers implementing SPECIAL-USE usually include them in a plain LIST anyway
        $return_opts = $this->rcube_imap_generic->getCapability('SPECIAL-USE') ? ['SPECIAL-USE'] : [];

        $mailboxes = $this->rcube_imap_generic->listMailboxes('', '*', $return_opts);

        $skipFolders = array_map('strtolower', $skipFolders);

        $returnarray = array();
        
        foreach ($mailboxes as $mailboxname) {
            // Skip unwanted folders by name
            if (in_array(strtolower($mailboxname), $skipFolders, true)) {
                continue;
            }

            $mailbox = new \bjc\roundcubeimap\mailbox($mailboxname, $this->rcube_imap_generic, $this->connection_data);

            // Skip unwanted folders by special-use attribute
            foreach ($skipAttributes as $attribute) {
                if ($mailbox->hasAttribute($attribute)) {
                    continue 2;
                }
            }

            $returnarray[] = $mailbox;
        }
        
        return $returnarray;
        
    }

    /**
     * Get all mailboxes of the account the connection is established to
     *
     * @param string $mailboxname Name of mailbox the method should return
     *
     * @return object of class \bjc\roundcubeimap\mailbox
     */
    
    public function getMailbox($mailboxname) {
        
        $mailbox_obj = new \bjc\roundcubeimap\mailbox($mailboxname, $this->rcube_imap_generic, $this->connection_data);

        return $mailbox_obj;
        
    }
 
    /**
     * Create mailbox in the account the connection is established to
     * Throws exception on error
     *
     * @param string $mailboxname Name of mailbox the method should create
     *
     * @return bool true on success
     * 
     */
    
    public function createMailbox($mailboxname) {
        
        $result = $this->rcube_imap_generic->createFolder($mailboxname);
        
        if ($result == false) {
            throw new \Exception('Mailbox creation failed.');
        }
        
        return true;
        
    }
    
    /**
     * Delete mailbox in the account the connection is established to
     * Throws exception on error
     *
     * @param string $mailboxname Name of mailbox the method should delete
     *
     * @return bool true on success
     *
     */
    
    public function deleteMailbox($mailboxname) {
        $result = $this->rcube_imap_generic->deleteFolder($mailboxname);
        
        if ($result == false) {
            throw new \Exception('Mailbox deletion failed.');
        }

        return true;
        
    }

    /**
     * Rename mailbox in the account the connection is established to
     * Throws exception on error
     *
     * @param string $mailboxname Name of mailbox the method should rename
     * @param string $new_mailboxname New name the mailbox should get
     *
     * @return bool true on success
     *
     */
    
    public function renameMailbox($mailboxname, $new_mailboxname) {
        $result = $this->rcube_imap_generic->renameFolder($mailboxname, $new_mailboxname);
        
        if ($result == false) {
            throw new \Exception('Rename mailbox failed.');
        }
        
        return true;
        
    }
    
    /**
     * Clear all messages from mailbox in the account the connection is established to
     * Throws exception on error
     *
     * @param string $mailboxname Name of mailbox the method should clear
     *
     * @return bool true on success
     *
     */
    
    public function clearMailbox($mailboxname) {
        $result = $this->rcube_imap_generic->clearFolder($mailboxname);
        
        if ($result == false) {
            throw new \Exception('Clearing mailbox failed.');
        }
        
        return true;
        
    }

    /**
     * Detects hierarchy delimiter
     *
     * @return string
     */
    public function getHierarchyDelimiter():string
    {
        $hierarchyDelimiter = $this->rcube_imap_generic->getHierarchyDelimiter();

        if(empty($hierarchyDelimiter)) {
            return "/";
        }

        return $hierarchyDelimiter;
    }

    /**
     * Sends the IMAP ID command (RFC 2971) to identify the client and retrieve
     * the server identification (e.g. NAME, VERSION, RELEASE)
     *
     * @param array $clientId Client identification key/value pairs to send
     *
     * @return array|false Server identification key/value hash, false if the server
     *                      does not support the ID capability or on error
     */
    public function getServerId(array $clientId = ['name' => 'roundcube-imap']) {

        if (!$this->rcube_imap_generic->getCapability('ID')) {
            return false;
        }

        return $this->rcube_imap_generic->id($clientId);

    }

}
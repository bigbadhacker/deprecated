<?php
/**
 * Get emails in your app with cake like finds.
 *
 * Copyright (c) 2010 Carl Sutton ( dogmatic69 )
 * Copyright (c) 2011 Kevin van Zonneveld ( kvz )
 *
 * @filesource
 * @copyright Copyright (c) 2010 Carl Sutton ( dogmatic69 )
 * @copyright Copyright (c) 2011 Kevin van Zonneveld ( kvz )
 * @link http://www.infinitas-cms.org
 * @link https://github.com/kvz/cakephp-emails-plugin
 * @package libs
 * @subpackage libs.models.datasources.reader
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @since 0.9a
 *
 * @author dogmatic69
 * @author kvz
 *
 * Modifications since 0.8a (when code was stripped from Infinitas):
 *   https://github.com/kvz/cakephp-emails-plugin/compare/10767bee59dd425ced5b97ae9604acf7f3c0d27a...master
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 */
class ImapSource extends DataSource {
    public $driver = null;

    private $__isConnected = false;

    private $__connectionString = null;

    public  $config = array();
    
    protected $_defaultConfigs = array(
        'global' => array(
            'username' => false,
            'password' => false,
            'email' => false,
            'server' => 'localhost',
            'type' => 'imap',
            'ssl' => false,
            'retry' => 3,
            'error_handler' => 'php',
        ),
        'imap' => array(
            'port' => 143,
        ),
        'pop3' => array(
            'port' => 110,
        ),
    );

    private $__connectionType = '';


    /**
     * Default array of field list for imap mailbox.
     *
     * @var array
     */
    protected $_schema = array(
        'id' => array('type' => 'string', 'default' => NULL, 'length' => 255,),
        'message_id' => array('type' => 'string', 'default' => NULL, 'length' => 255,),
        'email_number' => array('type' => 'integer', 'default' => NULL, 'length' => 15, 'key' => 'primary',),
        'to' => array('type' => 'string', 'default' => NULL, 'length' => 255,),
        'from' => array('type' => 'string', 'default' => NULL, 'length' => 255,),
        'reply_to' => array('type' => 'string', 'default' => NULL, 'length' => 255,),
        'sender' => array('type' => 'string', 'default' => NULL, 'length' => 255,),
        'subject' => array('type' => 'string', 'default' => NULL, 'length' => 255,),
        'body' => array('type' => 'text', 'default' => NULL,),
        'plainmsg' => array('type' => 'text', 'default' => NULL,),
        'slug' => array('type' => 'string', 'default' => NULL, 'length' => 255,),
        'size' => array('type' => 'string', 'default' => NULL, 'length' => 255,),
        'recent' => array('type' => 'boolean', 'default' => NULL, 'length' => 1,),
        'unread' => array('type' => 'boolean', 'default' => NULL, 'length' => 1,),
        'flagged' => array('type' => 'boolean', 'default' => NULL, 'length' => 1,),
        'answered' => array('type' => 'boolean', 'default' => NULL, 'length' => 1,),
        'draft' => array('type' => 'boolean', 'default' => NULL, 'length' => 1,),
        'deleted' => array('type' => 'boolean', 'default' => NULL, 'length' => 1,),
        'thread_count' => array('type' => 'integer', 'default' => NULL, 'length' => 15, 'key' => 'primary',),
        'attachments' => array('type' => 'text', 'default' => NULL,),
        'in_reply_to' => array('type' => 'string', 'default' => NULL, 'length' => 255,),
        'reference' => array('type' => 'string', 'default' => NULL, 'length' => 255,),
        'new' => array('type' => 'boolean', 'default' => NULL, 'length' => 1,),
        'created' => array('type' => 'datetime', 'default' => NULL,),
    );

    public $columns = array(
        'primary_key' => array('name' => 'NOT NULL AUTO_INCREMENT'),
        'string' => array('name' => 'varchar', 'limit' => '255'),
        'text' => array('name' => 'text'),
        'integer' => array('name' => 'int', 'limit' => '11', 'formatter' => 'intval'),
        'float' => array('name' => 'float', 'formatter' => 'floatval'),
        'datetime' => array('name' => 'datetime', 'format' => 'Y-m-d H:i:s', 'formatter' => 'date'),
        'timestamp' => array('name' => 'timestamp', 'format' => 'Y-m-d H:i:s', 'formatter' => 'date'),
        'time' => array('name' => 'time', 'format' => 'H:i:s', 'formatter' => 'date'),
        'date' => array('name' => 'date', 'format' => 'Y-m-d', 'formatter' => 'date'),
        'binary' => array('name' => 'blob'),
        'boolean' => array('name' => 'tinyint', 'limit' => '1')
    );


    /**
     * __construct()
     *
     * @param mixed $config
     */
    function __construct ($config) {
        parent::__construct($config);

        if (!isset($config['type'])) {
            $type = $this->_defaultConfigs['global']['type'];
        } else {
            $type = $config['type'];
        }
        $newConfig = array_merge($this->_defaultConfigs['global'], $this->_defaultConfigs[$type], $this->config);
        $newConfig['email'] = !empty($newConfig['email']) ? $newConfig['email'] : $newConfig['username'];

        $this->config = $newConfig;
    }

    /**
     * describe the data
     *
     * @param mixed $Model
     * @return array the shcema of the model
     */
    public function describe ($Model) {
        return $this->_schema;
    }

    /**
     * listSources
     *
     * list the sources???
     *
     * @return array sources
     */
    public function listSources () {
        return array('listSources');
    }

    /**
     * read data
     *
     * this is the main method that reads data from the datasource and
     * formats it according to the request from the model.
     *
     * @param mixed $model the model that is requesting data
     * @param mixed $query the qurey that was sent
     *
     * @return the data requested by the model
     */
    public function read ($Model, $query) {
        if (!$this->__connectToServer($Model, $query)) {
            return $this->err($Model, 'Cannot connect to server');
        }

        switch ($Model->findQueryType) {
            case 'count':
                return array(
                    array(
                        $Model->alias => array(
                            'count' => $this->_mailCount($query),
                        ),
                    ),
                );
                break;

            case 'all':
                $query['limit'] = $query['limit'] >= 1 ? $query['limit'] : 20;
                return $this->__getMails($Model, $query);
                break;

            case 'first':
                return array($this->__getMail($Model, $query));
                break;

            default:
                return $this->err(
                    'Unknown find type %s for query %s',
                    $Model->findQueryType,
                    $query
                );
                break;
        }

        return $result;
    }

    /**
     * no clue
     * @param <type> $Model
     * @param <type> $func
     * @param <type> $params
     * @return <type>
     */
    public function calculate ($Model, $func, $params = array()) {
        $params = (array) $params;
        switch (strtolower($func)) {
            case 'count':
                return 'count';
                break;
        }
    }

    /**
     * connect to the mail server
     */
    private function __connectToServer ($Model, $query) {
        if ($this->__isConnected) {
            return true;
        }

        $this->__connectionType = $this->config['type'];

        switch ($this->config['type']) {
            case 'imap':
                $this->__connectionString = sprintf(
                    '{%s:%s%s%s}',
                    $this->config['server'],
                    $this->config['port'],
                    @$this->config['ssl'] ? '/ssl' : '',
                    @$this->config['connect'] ? '/' . @$this->config['connect'] : ''
                );
                break;

            case 'pop3':
                $this->__connectionString = sprintf(
                    '{%s:%s/pop3%s%s}',
                    $this->config['server'],
                    $this->config['port'],
                    @$this->config['ssl'] ? '/ssl' : '',
                    @$this->config['connect'] ? '/' . @$this->config['connect'] : ''
                );
                break;
        }

        try {
            $this->thread = null;
            $retries = 0;
            while (($retries++) < $this->config['retry'] && !$this->thread) {
                $this->MailServer = imap_open($this->__connectionString, $this->config['username'], $this->config['password']);
                $this->thread     = @imap_thread($this->MailServer, SE_UID);
            }

            if (!$this->thread) {
                return $this->err('Unable to get imap_thread after %s retries', $retries);
            }
        } catch (Exception $Exception) {
            return $this->err(
                'Unable to get imap_thread after %s retries. %s',
                 $retries,
                $Exception->getMessage() . ' ' . imap_last_error()
            );
        }

        return $this->__isConnected = true;
    }

    public function name ($data) {
        return $data;
    }


    public function sensible ($arguments) {
        if (is_object($arguments)) {
            return get_class($arguments);
        }
        if (!is_array($arguments)) {
            if (!is_numeric($arguments) && !is_bool($arguments)) {
                $arguments = "'".$arguments."'";
            }
            return $arguments;
        }
        $arr = array();
        foreach($arguments as $key=>$val) {
            if (is_array($val)) {
                $val = json_encode($val);
            } elseif (!is_numeric($val) && !is_bool($val)) {
                $val = "'".$val."'";
            }

            if (strlen($val) > 33) {
                $val = substr($val, 0, 30) . '...';
            }
            
            $arr[] = $key.': '.$val;
        }
        return join(', ', $arr);
    }

    public function err ($Model, $format, $arg1 = null, $arg2 = null, $arg3 = null) {
        $arguments = func_get_args();
        $Model     = array_shift($arguments);
        $format    = array_shift($arguments);

        $str = $format;
        if (count($arguments)) {
            foreach($arguments as $k => $v) {
                $arguments[$k] = $this->sensible($v);
            }
            $str = vsprintf($str, $arguments);
        }

        $this->error = $str;
        $Model->onError();
        
        if ($this->config['error_handler'] === 'php') {
            trigger_error($str, E_USER_ERROR);
        }

        return false;
    }
    
    public function lastError () {
        if (($lastError = $this->error)) {
            return $lastError;
        }
        if (($lastError = imap_last_error())) {
            $this->error = imap_errors();
            return $lastError;
        }
        return false;
    }

    /**
     * Get the full email for a read / find(first)
     *
     * @param object $Model
     * @param array $query
     *
     * @return array the email according to the find
     */
    private function __getMail ($Model, $query) {
        if (!isset($query['conditions'][$Model->alias . '.id']) || empty($query['conditions'][$Model->alias . '.id'])) {
            return array();
        }

        if ($this->__connectionType == 'imap') {
            $uuid = $query['conditions'][$Model->alias . '.id'];
        } else {
            $uuid = base64_decode($query['conditions'][$Model->alias . '.id']);
        }

        return $this->__getFormattedMail($Model, imap_msgno($this->MailServer, $uuid));
    }

    /**
     * Get the emails
     *
     * The method for finding all emails paginated from the mail server, used
     * by code like find('all') etc.
     *
     * @todo conditions / order other find params
     *
     * @param object $Model the model doing the find
     * @param array $query the find conditions and params
     * @return array the data that was found
     */
    private function __getMails ($Model, $query) {
        $pagination = $this->_figurePagination($query);

        $mails = array();
        for ($i = $pagination['start']; $i > $pagination['end']; $i--) {
            $mails[] = $this->__getFormattedMail($Model, $i);
        }

        return $mails;
    }

    /**
     * get the basic details like sender and reciver with flags like attatchments etc
     *
     * @param int $message_id the id of the message
     * @return array empty on error/nothing or array of formatted details
     */
    private function __getFormattedMail ($Model, $message_id) {
        $mail      = imap_headerinfo($this->MailServer, $message_id);
        $structure = imap_fetchstructure($this->MailServer, $mail->Msgno);

        $toName      = isset($mail->to[0]->personal) ? $mail->to[0]->personal : $mail->to[0]->mailbox;
        $fromName    = isset($mail->from[0]->personal) ? $mail->from[0]->personal : $mail->from[0]->mailbox;
        $replyToName = isset($mail->reply_to[0]->personal) ? $mail->reply_to[0]->personal : $mail->reply_to[0]->mailbox;

        if (isset($mail->sender)) {
            $senderName = isset($mail->sender[0]->personal) ? $mail->sender[0]->personal : $mail->sender[0]->mailbox;
        } else {
            $senderName          = $fromName;
            $mail->sender        = $mail->from;
            $mail->senderaddress = $mail->fromaddress;
        }

        $return[$Model->alias] = array(
            'id' => $this->__getId($mail->Msgno),
            'message_id' => $mail->message_id,
            'email_number' => $mail->Msgno,
            'to' => printf(
                '"%s" <%s>',
                $toName,
                $mail->toaddress
            ),
            'from' => sprintf(
                '"%s" <%s>',
                $fromName,
                sprintf('%s@%s', $mail->from[0]->mailbox, $mail->from[0]->host)
            ),
            'reply_to' => sprintf(
                '"%s" <%s>',
                $replyToName,
                sprintf('%s@%s', $mail->reply_to[0]->mailbox, $mail->reply_to[0]->host)
            ),
            'sender' => sprintf(
                '"%s" <%s>',
                $replyToName,
                sprintf('%s@%s', $mail->sender[0]->mailbox, $mail->sender[0]->host)
            ),
            'subject' => htmlspecialchars($mail->subject),
            'body' => $this->_getPart($message_id, 'TEXT/HTML', $structure),
            'plainmsg' => $this->_getPart($message_id, 'TEXT/PLAIN', $structure),
            'slug' => Inflector::slug($mail->subject, '-'),
            'size' => $mail->Size,
            'recent' => $mail->Recent,
            'unread' => (int)(bool)trim($mail->Unseen),
            'flagged' => (int)(bool)trim($mail->Flagged),
            'answered' => $mail->Answered,
            'draft' => $mail->Draft,
            'deleted' => $mail->Deleted,
            'thread_count' => $this->_getThreadCount($mail),
            'attachments' => json_encode($this->_attachment($mail->Msgno, $structure)),
            'in_reply_to' => isset($mail->in_reply_to) ? $mail->in_reply_to : false,
            'reference' => isset($mail->references) ? $mail->references : false,
            'new' => !isset($mail->in_reply_to) ? true : false,
            'created' => $mail->date
        );

        return $return;
    }

    /**
     * Get any attachments for the current message, images, documents etc
     *
     * @param <type> $structure
     * @param <type> $message_id
     * @return <type>
     */
    protected function _getAttachments ($structure, $message_id) {
        $attachments = array();
        if (isset($structure->parts) && count($structure->parts)) {
            for ($i = 0; $i < count($structure->parts); $i++) {
                $attachment = array(
                    'message_id' => $message_id,
                    'is_attachment' => false,
                    'filename' => '',
                    'mime_type' => '',
                    'type' => '',
                    'name' => '',
                    'size' => 0,
                    'attachment' => ''
                );

                if ($structure->parts[$i]->ifdparameters) {
                    foreach ($structure->parts[$i]->dparameters as $object) {
                        if (strtolower($object->attribute) == 'filename') {
                            $attachment['is_attachment'] = true;
                            $attachment['filename'] = $object->value;
                        }
                    }
                }

                if ($structure->parts[$i]->ifparameters) {
                    foreach ($structure->parts[$i]->parameters as $object) {
                        if (strtolower($object->attribute) == 'name') {
                            $attachment['is_attachment'] = true;
                            $attachment['name'] = $object->value;
                        }
                    }
                }
                if ($attachment['is_attachment']) {
                    $attachment['attachment'] = imap_fetchbody($this->MailServer, $message_id, $i+1);
                    if ($structure->parts[$i]->encoding == 3) { // 3 = BASE64
                        $attachment['format'] = 'base64';
                    } elseif ($structure->parts[$i]->encoding == 4) { // 4 = QUOTED-PRINTABLE
                        $attachment['attachment'] = quoted_printable_decode($attachment['attachment']);
                        //$attachment['format'] = 'base64';
                    }

                    $attachment['type']      = strtolower($structure->parts[$i]->subtype);
                    $attachment['mime_type'] = $this->_getMimeType($structure->parts[$i]);
                    $attachment['size']      = $structure->parts[$i]->bytes;

                    $attachments[] = $attachment;
                }
            }
        }

        return $attachments;
    }




    /**
     * get a usable uuid for use in the code
     *
     * @param string $uuid in the format <.*@.*> from the email
     *
     * @return mixed on imap its the unique id (int) and for others its a base64_encoded string
     */
    private function __getId ($uuid) {
        switch ($this->__connectionType) {
            case 'imap':
                return imap_uid($this->MailServer, $uuid);
                break;

            default:
                return str_replace(array('<', '>'), '', base64_encode($mail->message_id));
                break;
        }
    }


    /**
     * get the count of mails for the given conditions and params
     *
     * @todo conditions / order other find params
     *
     * @param array $query conditions for the query
     * @return int the number of emails found
     */
    protected function _mailCount ($query) {
        return imap_num_msg($this->MailServer);
    }

    /**
     * used to check / get the attachements in an email.
     *
     * @param object $structure the structure of the email
     * @param bool $count count them (true), or get them (false)
     *
     * @return mixed, int for check (number of attachements) / array of attachements
     */
    protected function _attachment ($message_id, $structure, $count = true) {
        $has = 0;
        $attachments = array();
        if (isset($structure->parts)) {
            foreach ($structure->parts as $partOfPart) {
                if ($count) {
                    $has += $this->_attachment($message_id, $partOfPart, $count) == true ? 1 : 0;
                } else {
                    $attachment = $this->_attachment($message_id, $partOfPart, $count);
                    if (!empty($attachment)) {
                        $attachments[] = $attachment;
                    }
                }
            }
        } else {
            if (isset($structure->disposition)) {
                if (strtolower($structure->disposition) == 'attachment') {
                    if ($count) {
                        return true;
                    } else {
                        return array(
                            'type' => $structure->type,
                            'subtype' => $structure->subtype,
                            'file' => $structure->dparameters[0]->value,
                            'size' => $structure->bytes
                        );
                    }
                }
            }
        }

        if ($count) {
            return (int)$has;
        }

        return $attachments;
    }

    /**
     * Figure out how many and from where emails should be returned. Uses the
     * current page and the limit set to figure out what to send back
     *
     * @param array $query the current query
     * @return array of start / end int for the for() loop in the email find
     */
    protected function _figurePagination ($query) {
        $count = $this->_mailCount($query); // total mails
        $pages = ceil($count / $query['limit']); // total pages
        $query['page'] = $query['page'] <= $pages ? $query['page'] : $pages; // dont let the page be more than available pages

        $return = array(
            'start' => $query['page'] == 1
                ? $count    // start at the end
                : ($pages - $query['page'] + 1) * $query['limit'], // start at the end - x pages
        );

        $return['end'] = $query['limit'] >= $count
            ? 0
            : $return['start'] - $query['limit'];

        $return['end'] = $return['end'] >= 0 ? $return['end'] : 0;

        if (isset($query['order']['date']) && $query['order']['date'] == 'asc') {
            return array(
                'start' => $return['end'],
                'end' => $return['start'],
            );
        }

        return $return;
    }

    protected function _getMimeType ($structure) {
        $primary_mime_type = array('TEXT', 'MULTIPART', 'MESSAGE', 'APPLICATION', 'AUDIO', 'IMAGE', 'VIDEO', 'OTHER');
        if ($structure->subtype) {
            return $primary_mime_type[(int) $structure->type] . '/' . $structure->subtype;
        }

        return 'TEXT/PLAIN';
    }

    protected function _getPart ($msg_number, $mime_type, $structure = null, $part_number = false) {
        $prefix = null;
        if (!$structure) {
            return false;
        }

        if ($mime_type == $this->_getMimeType($structure)) {
            $part_number = $part_number > 0 ? $part_number : 1;

            return imap_fetchbody($this->MailServer, $msg_number, $part_number);
        }

        /* multipart */
        if ($structure->type == 1) {
            foreach ($structure->parts as $index => $sub_structure) {
                if ($part_number) {
                    $prefix = $part_number . '.';
                }

                $data = $this->_getPart($msg_number, $mime_type, $sub_structure, $prefix . ($index + 1));
                if ($data) {
                    return quoted_printable_decode($data);
                }
            }
        }
    }

    /**
     * Figure out how many emails there are in the thread for this mail.
     *
     * @param object $mail the imap header of the mail
     * @return int the number of mails in the thred
     */
    protected function _getThreadCount ($mail) {
        if (isset($mail->reference) || isset($mail->in_reply_to)) {
            return '?';
        }

        return 0;
    }
}
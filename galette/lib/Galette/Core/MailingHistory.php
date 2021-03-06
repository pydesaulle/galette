<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Mailing features
 *
 * PHP version 5
 *
 * Copyright © 2009-2014 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Core
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-08-27
 */

namespace Galette\Core;

use Analog\Analog as Analog;
use Galette\Entity\Adherent;
use Zend\Db\Sql\Expression;

/**
 * Mailing features
 *
 * @category  Core
 * @name      MailingHistory
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-08-27
 */
class MailingHistory extends History
{
    const TABLE = 'mailing_history';
    const PK = 'mailing_id';

    private $_mailing = null;
    private $_id;
    private $_date;
    private $_subject;
    private $_message;
    private $_recipients;
    private $_sender;
    private $_sent = false;
    private $_no_longer_members;

    /**
     * Default constructor
     *
     * @param Mailing $mailing Mailing
     */
    public function __construct($mailing = null)
    {
        parent::__construct();

        if ( $mailing instanceof Mailing ) {
            $this->_mailing = $mailing;
        } else if ( $mailing !== null ) {
            Analog::log(
                '[' . __METHOD__ .
                '] Mailing should be either null or an instance of Mailing',
                Analog::ERROR
            );
        }
    }

    /**
     * Get the entire history list
     *
     * @return array
     */
    public function getHistory()
    {
        return $this->getMailingHistory();
    }

    /**
     * Get the entire mailings list
     *
     * @return array
     */
    public function getMailingHistory()
    {
        global $zdb;

        if ($this->counter == null) {
            $c = $this->getCount();

            if ($c == 0) {
                Analog::log('No entry in history (yet?).', Analog::DEBUG);
                return;
            } else {
                $this->counter = (int)$c;
                $this->countPages();
            }
        }

        try {
            $select = $zdb->select($this->getTableName(), 'a');
            $select->join(
                array('b' => PREFIX_DB . Adherent::TABLE),
                'a.mailing_sender=b.' . Adherent::PK,
                array('nom_adh', 'prenom_adh'),
                $select::JOIN_LEFT
            )->order($this->orderby . ' ' . $this->ordered);
            //add limits to retrieve only relavant rows
            $this->setLimits($select);
            $results = $zdb->execute($select);
            $ret = array();
            foreach ( $results as $r ) {
                if ( $r['mailing_sender'] !== null ) {
                    $r['mailing_sender_name'] 
                        = Adherent::getSName($r['mailing_sender']);
                }
                $body_resume = $r['mailing_body'];
                if ( strlen($body_resume) > 150 ) {
                    $body_resume = substr($body_resume, 0, 150);
                    $body_resume .= '[...]';
                }
                if (function_exists('tidy_parse_string') ) {
                    //if tidy extension is present, we use it to clean a bit
                    $tidy_config = array(
                        'clean'             => true,
                        'show-body-only'    => true,
                        'wrap' => 0,
                    );
                    $tidy = tidy_parse_string($body_resume, $tidy_config, 'UTF8');
                    $tidy->cleanRepair();
                    $r['mailing_body_resume'] = tidy_get_output($tidy);
                } else {
                    //if it is not... Well, let's serve the text as it.
                    $r['mailing_body_resume'] = $body_resume;
                }

                $attachments = 0;
                if ( file_exists(GALETTE_ATTACHMENTS_PATH . $r[self::PK]) ) {
                    $rdi = new \RecursiveDirectoryIterator(
                        GALETTE_ATTACHMENTS_PATH . $r[self::PK],
                        \FilesystemIterator::SKIP_DOTS
                    );
                    $contents = new \RecursiveIteratorIterator(
                        $rdi,
                        \RecursiveIteratorIterator::CHILD_FIRST
                    );
                    foreach ( $contents as $path) {
                        if ( $path->isFile() ) {
                            $attachments++;
                        }
                    }
                }
                $r['attachments'] = $attachments;
                $ret[] = $r;
            }
            return $ret;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to get history. | ' . $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Load mailing from an existing one
     *
     * @param integaer       $id      Model identifier
     * @param GaletteMailing $mailing Mailing object
     * @param boolean        $new     True if we create a 'new' mailing,
     *                                false otherwise (from preview for example)
     *
     * @return boolean
     */
    public static function loadFrom($id, $mailing, $new = true)
    {
        global $zdb;

        try {
            $select = $zdb->select(self::TABLE);
            $select->where('mailing_id = ' . $id);

            $results = $zdb->execute($select);
            $result = $results->current();

            return $mailing->loadFromHistory($result, $new);
        } catch (\Exception $e) {
            Analog::log(
                'Unable to load mailing model #' . $id . ' | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Store a mailing in the history
     *
     * @param boolean $sent Defaults to false
     *
     * @return boolean
     */
    public function storeMailing($sent = false)
    {
        global $login;

        if ( $this->_mailing instanceof Mailing ) {
            $this->_sender = $login->id;
            $this->_subject = $this->_mailing->subject;
            $this->_message = $this->_mailing->message;
            $this->_recipients = $this->_mailing->recipients;
            $this->_sent = $sent;
            $this->_date = date('Y-m-d H:i:s');
            if ( !$this->_mailing->existsInHistory() ) {
                $this->store();
                $this->_mailing->id = $this->_id;
                $this->_mailing->moveAttachments($this->_id);
            } else {
                //existing stored mailing. Just update row.
                $this->update();
            }
        } else {
            Analog::log(
                '[' . __METHOD__ .
                '] Mailing should be an instance of Mailing',
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Update in the database
     *
     * @return boolean
     */
    public function update()
    {
        global $zdb;

        try {
            $_recipients = array();
            if ( $this->_recipients != null ) {
                foreach ( $this->_recipients as $_r ) {
                    $_recipients[$_r->id] = $_r->sname . ' <' . $_r->email . '>';
                }
            }
            $values = array(
                'mailing_sender' => ($this->_sender === 0) ? new Expression('NULL') : $this->_sender,
                'mailing_subject' => $this->_subject,
                'mailing_body' => $this->_message,
                'mailing_date' => $this->_date,
                'mailing_recipients' => serialize($_recipients),
                'mailing_sent' => ($this->_sent) ? true : 'false'
            );

            $update = $zdb->update(self::TABLE);
            $update->set($values);
            $update->where(self::PK . ' = ' . $this->_mailing->history_id);
            $zdb->execute($update);
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'An error occurend updating Mailing | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Store in the database
     *
     * @return boolean
     */
    public function store()
    {
        global $zdb;

        try {
            $_recipients = array();
            if ( $this->_recipients != null ) {
                foreach ( $this->_recipients as $_r ) {
                    $_recipients[$_r->id] = $_r->sname . ' <' . $_r->email . '>';
                }
            }

            $sender = null;
            if ( $this->_sender === 0 ) {
                $sender = new Expression('NULL');
            } else {
                $sender = $this->_sender;
            }

            $values = array(
                'mailing_sender' => $sender,
                'mailing_subject' => $this->_subject,
                'mailing_body' => $this->_message,
                'mailing_date' => $this->_date,
                'mailing_recipients' => serialize($_recipients),
                'mailing_sent' => ($this->_sent) ? true : 'false'
            );

            $insert = $zdb->insert(self::TABLE);
            $insert->values($values);

            if ( $zdb->isPostgres() ) {
                $this->_id = $zdb->driver->getLastGeneratedValue(
                    PREFIX_DB . 'mailing_history_id_seq'
                );
            } else {
                $this->_id = $zdb->driver->getLastGeneratedValue();
            }
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'An error occurend storing Mailing | ' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Remove specified entries
     *
     * @param integer|array $ids Mailing history entries identifiers
     *
     * @return boolean
     */
    public function removeEntries($ids)
    {
        global $zdb, $hist;

        $list = array();
        if ( is_numeric($ids) ) {
            //we've got only one identifier
            $list[] = $ids;
        } else {
            $list = $ids;
        }

        if ( is_array($list) ) {
            try {
                foreach ( $list as $id ) {
                    $mailing = new Mailing(null, $id);
                    $mailing->removeAttachments();
                }

                $zdb->connection->beginTransaction();

                //delete members
                $delete = $zdb->delete(self::TABLE);
                $delete->where->in(self::PK, $list);
                $zdb->execute($delete);

                //commit all changes
                $zdb->connection->commit();

                //add an history entry
                $hist->add(
                    _T("Delete mailing entries")
                );

                return true;
            } catch (\Exception $e) {
                $zdb->connection->rollBack();
                Analog::log(
                    'Unable to delete selected mailing history entries |' .
                    $e->getMessage(),
                    Analog::ERROR
                );
                return false;
            }
        } else {
            //not numeric and not an array: incorrect.
            Analog::log(
                'Asking to remove mailing entries, but without ' .
                'providing an array or a single numeric value.',
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Get table's name
     *
     * @param boolean $prefixed Whether table name should be prefixed
     *
     * @return string
     */
    protected function getTableName($prefixed = false)
    {
        if ( $prefixed === true ) {
            return PREFIX_DB . self::TABLE;
        } else {
            return self::TABLE;
        }
    }

    /**
     * Get table's PK
     *
     * @return string
     */
    protected function getPk()
    {
        return self::PK;
    }

    /**
     * Returns the field we want to default set order to
     *
     * @return string field name
     */
    protected function getDefaultOrder()
    {
        return 'mailing_date';
    }

}

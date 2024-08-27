<?php
/**
 * Created by PhpStorm.
 * User: nmatuschek
 * Date: 28.05.18
 * Time: 10:24
 */

class ilElectronicCourseReserveLangData
{
    public static array $ecr_lang_data = array();

    public ilDBInterface $db;

    protected string $lang_key;

    /**
     * default
     */
    protected string $identifier = 'ecr_tab_title';

    protected string $value;

    public function __construct()
    {
        global $DIC;

        $this->db = $DIC->database();
        $this->readFromDB();
    }

    /**
     * @param $identifier
     * @return mixed|string
     */
    public function txt($identifier): mixed
    {
        global $DIC;
        $lang_key = $DIC->user()->getLanguage();

        if (!isset(self::$ecr_lang_data[$lang_key])) {
            return ilElectronicCourseReservePlugin::getInstance()->txt($identifier);
        }
        return self::$ecr_lang_data[$lang_key];
    }

    private function readFromDB(): void
    {
        $query = 'SELECT * FROM ecr_lang_data ';
        $res = $this->db->query($query);

        while ($row = $this->db->fetchAssoc($res)) {
            self::$ecr_lang_data[$row['lang_key']] = $row['value'];
        }
    }

    public function saveTranslation(): void
    {
        $this->db->replace(
            'ecr_lang_data',
            array(
                'ecr_content' => array('clob', $ecr_content = self::lookupEcrContentByLangKey($this->getLangKey())),
                'value' => array('text', $this->getValue())
            ),
            array(
                'lang_key' => array('text', $this->getLangKey()),
                'identifier' => array('text', $this->identifier)
            )
        );
    }

    /**
     * @return string
     */
    public function getLangKey(): string
    {
        return $this->lang_key;
    }

    /**
     * @param string $lang_key
     */
    public function setLangKey(string $lang_key): void
    {
        $this->lang_key = $lang_key;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    /**
     * @param $lang_key
     * @return int
     */
    public static function lookupObjIdByLangKey($lang_key): int
    {
        global $DIC;

        $res = $DIC->database()->queryF(
            'SELECT obj_id FROM object_data WHERE title = %s',
            array('text'),
            array(trim($lang_key))
        );

        if ($row = $DIC->database()->fetchAssoc($res)) {
            return $row['obj_id'];
        }

        return 0;
    }

    /**
     * @param $lang_key
     * @return string
     */
    public static function lookupEcrContentByLangKey($lang_key): string
    {
        global $DIC;

        $res = $DIC->database()->queryF(
            'SELECT ecr_content FROM ecr_lang_data WHERE lang_key = %s',
            array('text'),
            array(trim($lang_key))
        );

        if ($row = $DIC->database()->fetchAssoc($res)) {
            return $row['ecr_content'];
        }

        return '';
    }

    /**
     * @param $lang_key
     * @param $ecr_content
     */
    public static function writeEcrContent($lang_key, $ecr_content): void
    {
        global $DIC;

        $DIC->database()->update(
            'ecr_lang_data',
            array(
                'ecr_content' => array('clob', $ecr_content)
            ),
            array(
                'lang_key' => array('text', $lang_key),
            )
        );
    }
}

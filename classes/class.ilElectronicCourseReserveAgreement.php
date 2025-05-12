<?php

use ILIAS\DI\LoggingServices;

/**
 * Class ilElectronicCourseReserveAgreement
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilElectronicCourseReserveAgreement
{
    public ilDBInterface $db;

    public ilLogger|LoggingServices $log;

    public ilObjUser $user;

    protected string $system_lang;

    protected ?int $agreement_id = null;

    protected string $agreement = '';

    protected string $lang = '';

    protected int $time_created = 0;

    protected int $is_active = 0;

    public function __construct()
    {
        global $DIC;

        $this->db = $DIC->database();
        $this->log = $DIC->logger()->root();
        $this->user = $DIC->user();
        $this->system_lang = $DIC->language()->getDefaultLanguage();
    }

    /**
     * @param $lang
     */
    public function loadByLang($lang): void
    {
        $this->db->setLimit(1);
        $res = $this->db->queryF(
            'SELECT * FROM ecr_lang_agreements WHERE lang = %s AND is_active = %s ORDER BY time_created DESC',
            ['text', 'integer'],
            [$lang, 1]
        );

        if ($row = $this->db->fetchAssoc($res)) {
            $this->agreement_id = (int) $row['agreement_id'];
            $this->lang = $row['lang'];
            $this->agreement = $row['agreement'];
            $this->time_created = $row['time_created'];
            $this->is_active = $row['is_active'];
        } else {
            $this->log->warning('ecr_lang_agreements: User-id (' . $this->user->getId() . '), unable to load by lang ' . $lang);

            if ($lang !== $this->system_lang) {
                $this->loadByLang($this->system_lang);
            }
        }
    }

    public function saveAgreement(): void
    {
        $this->deactivateAgreements();

        $next_id = $this->db->nextId('ecr_lang_agreements');
        $this->db->insert(
            'ecr_lang_agreements',
            [
                'agreement_id' => ['integer', $next_id],
                'lang' => ['text', $this->getLang()],
                'agreement' => ['clob', $this->getAgreement()],
                'time_created' => ['integer', time()],
                'is_active' => ['integer', 1]
            ]
        );

        $this->log->info('ecr_lang_agreements: User-id (' . $this->user->getId() . ') created agreement_id (' . $next_id . ')');
    }

    private function deactivateAgreements(): void
    {
        $this->db->update(
            'ecr_lang_agreements',
            ['is_active' => ['integer', 0]],
            [
                'lang' => ['text', $this->getLang()],
                'is_active' => ['integer', 1]
            ]
        );
    }


    public function getAgreementId(): ?int
    {
        return $this->agreement_id;
    }

    /**
     * @param int $agreement_id
     */
    public function setAgreementId(int $agreement_id): void
    {
        $this->agreement_id = $agreement_id;
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    /**
     * @param string $lang ISO 639-1 two-letter code
     */
    public function setLang(string $lang): void
    {
        $this->lang = $lang;
    }

    public function getAgreement(): string
    {
        return $this->agreement;
    }

    /**
     * @param string $agreement
     */
    public function setAgreement(string $agreement): void
    {
        $this->agreement = $agreement;
    }

    public function getTimeCreated(): int
    {
        return $this->time_created;
    }

    /**
     * @return int
     */
    public function isActive(): int
    {
        return $this->is_active;
    }

    /**
     * @param int $is_active
     */
    public function setIsActive(int $is_active): void
    {
        $this->is_active = $is_active;
    }
}

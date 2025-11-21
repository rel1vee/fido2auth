<?php

/**
 * FIDO2 Challenge Repository
 */

declare(strict_types=1);

namespace PrestaShop\Module\Fido2Auth\Repository;

use Db;
use DbQuery;
use DateTime;
use PrestaShop\Module\Fido2Auth\Entity\Fido2Challenge;

class ChallengeRepository
{
    private string $table = 'fido2_challenges';

    public function save(Fido2Challenge $challenge): bool
    {
        $data = [
            'challenge' => pSQL($challenge->getChallenge()),
            'user_handle' => $challenge->getUserHandle() ? pSQL($challenge->getUserHandle()) : null,
            'id_customer' => $challenge->getCustomerId() ? (int) $challenge->getCustomerId() : null,
            'challenge_type' => pSQL($challenge->getChallengeType()),
            'created_at' => pSQL($challenge->getCreatedAt()->format('Y-m-d H:i:s')),
            'expires_at' => pSQL($challenge->getExpiresAt()->format('Y-m-d H:i:s')),
            'used' => (int) $challenge->isUsed(),
        ];

        if ($challenge->getId()) {
            return Db::getInstance()->update($this->table, $data, 'id_fido2_challenge = ' . (int) $challenge->getId());
        }

        $result = Db::getInstance()->insert($this->table, $data);
        if ($result) {
            $challenge->setId((int) Db::getInstance()->Insert_ID());
        }
        return $result;
    }

    public function findValidChallenge(string $challengeString): ?Fido2Challenge
    {
        $query = new DbQuery();
        $query->select('*')
            ->from($this->table)
            ->where('challenge = \'' . pSQL($challengeString) . '\'')
            ->where('used = 0')
            ->where('expires_at > NOW()');

        $row = Db::getInstance()->getRow($query);
        return $row ? $this->hydrate($row) : null;
    }

    public function markAsUsed(string $challengeString): bool
    {
        return Db::getInstance()->update($this->table, ['used' => 1], 'challenge = \'' . pSQL($challengeString) . '\'');
    }

    public function deleteExpired(): bool
    {
        return Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . $this->table . '` WHERE expires_at < NOW()');
    }

    public function deleteUsedOlderThan(int $hours): bool
    {
        return Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . $this->table . '` WHERE used = 1 AND created_at < DATE_SUB(NOW(), INTERVAL ' . (int)$hours . ' HOUR)');
    }

    private function hydrate(array $row): Fido2Challenge
    {
        $challenge = new Fido2Challenge();
        $challenge->setId((int) $row['id_fido2_challenge'])
            ->setChallenge($row['challenge'])
            ->setUserHandle($row['user_handle'])
            ->setCustomerId($row['id_customer'] ? (int) $row['id_customer'] : null)
            ->setChallengeType($row['challenge_type'])
            ->setCreatedAt(new DateTime($row['created_at']))
            ->setExpiresAt(new DateTime($row['expires_at']))
            ->setUsed((bool) $row['used']);
        return $challenge;
    }
}

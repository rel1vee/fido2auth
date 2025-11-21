<?php

/**
 * FIDO2 Credential Repository
 */

declare(strict_types=1);

namespace PrestaShop\Module\Fido2Auth\Repository;

use Db;
use DbQuery;
use DateTime;
use PrestaShop\Module\Fido2Auth\Entity\Fido2Credential;
use Link;

class CredentialRepository
{
    /**
     * @var string
     */
    private $table;

    /**
     * @var string
     */
    private $dbPrefix;

    /**
     * @var Link
     */
    private $link;

    public function __construct(Link $link)
    {
        $this->table = 'fido2_credentials';
        $this->dbPrefix = _DB_PREFIX_;
        $this->link = $link;
    }

    /**
     * Save credential to database
     *
     * @param Fido2Credential $credential
     * @return bool
     */
    public function save(Fido2Credential $credential): bool
    {
        $data = [
            'id_customer' => (int) $credential->getCustomerId(),
            'credential_id' => pSQL($credential->getCredentialId()),
            'credential_public_key' => pSQL($credential->getCredentialPublicKey()),
            'attestation_type' => pSQL($credential->getAttestationType()),
            'aaguid' => $credential->getAaguid() ? pSQL($credential->getAaguid()) : null,
            'sign_count' => (int) $credential->getSignCount(),
            'transports' => $credential->getTransports() ? pSQL(json_encode($credential->getTransports())) : null,
            'device_name' => $credential->getDeviceName() ? pSQL($credential->getDeviceName()) : null,
            'user_agent' => $credential->getUserAgent() ? pSQL($credential->getUserAgent()) : null,
            'created_at' => pSQL($credential->getCreatedAt()->format('Y-m-d H:i:s')),
            'last_used_at' => $credential->getLastUsedAt() ? pSQL($credential->getLastUsedAt()->format('Y-m-d H:i:s')) : null,
            'is_active' => (int) $credential->isActive(),
        ];

        if ($credential->getId()) {
            // Update existing credential
            return Db::getInstance()->update(
                $this->table,
                $data,
                'id_fido2_credential = ' . (int) $credential->getId()
            );
        } else {
            // Insert new credential
            $result = Db::getInstance()->insert($this->table, $data);

            if ($result) {
                $credential->setId((int) Db::getInstance()->Insert_ID());
            }

            return $result;
        }
    }

    /**
     * Find credential by ID
     *
     * @param int $id
     * @return Fido2Credential|null
     */
    public function findById(int $id): ?Fido2Credential
    {
        $query = new DbQuery();
        $query->select('*')
            ->from($this->table)
            ->where('id_fido2_credential = ' . (int) $id);

        $row = Db::getInstance()->getRow($query);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Find credential by credential ID
     *
     * @param string $credentialId
     * @return Fido2Credential|null
     */
    public function findByCredentialId(string $credentialId): ?Fido2Credential
    {
        $query = new DbQuery();
        $query->select('*')
            ->from($this->table)
            ->where('credential_id = \'' . pSQL($credentialId) . '\'')
            ->where('is_active = 1');

        $row = Db::getInstance()->getRow($query);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Find all credentials for a customer
     *
     * @param int $customerId
     * @return array
     */
    public function findByCustomerId(int $customerId): array
    {
        $query = new DbQuery();
        $query->select('*')
            ->from($this->table)
            ->where('id_customer = ' . (int) $customerId)
            ->where('is_active = 1')
            ->orderBy('created_at DESC');

        $rows = Db::getInstance()->executeS($query);

        if (!$rows) {
            return [];
        }

        $credentials = [];
        foreach ($rows as $row) {
            $credentials[] = $this->hydrate($row);
        }

        return $credentials;
    }

    /**
     * Get all credential IDs for a customer
     *
     * @param int $customerId
     * @return array
     */
    public function getCredentialIdsByCustomerId(int $customerId): array
    {
        $query = new DbQuery();
        $query->select('credential_id')
            ->from($this->table)
            ->where('id_customer = ' . (int) $customerId)
            ->where('is_active = 1');

        $rows = Db::getInstance()->executeS($query);

        if (!$rows) {
            return [];
        }

        return array_column($rows, 'credential_id');
    }

    /**
     * Delete credential
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return Db::getInstance()->delete(
            $this->table,
            'id_fido2_credential = ' . (int) $id
        );
    }

    /**
     * Soft delete credential (mark as inactive)
     *
     * @param int $id
     * @return bool
     */
    public function softDelete(int $id): bool
    {
        return Db::getInstance()->update(
            $this->table,
            ['is_active' => 0],
            'id_fido2_credential = ' . (int) $id
        );
    }

    /**
     * Count active credentials for a customer
     *
     * @param int $customerId
     * @return int
     */
    public function countByCustomerId(int $customerId): int
    {
        $query = new DbQuery();
        $query->select('COUNT(*)')
            ->from($this->table)
            ->where('id_customer = ' . (int) $customerId)
            ->where('is_active = 1');

        return (int) Db::getInstance()->getValue($query);
    }

    /**
     * Update sign count for credential
     *
     * @param int $id
     * @param int $signCount
     * @return bool
     */
    public function updateSignCount(int $id, int $signCount): bool
    {
        return Db::getInstance()->update(
            $this->table,
            [
                'sign_count' => (int) $signCount,
                'last_used_at' => pSQL(date('Y-m-d H:i:s')),
            ],
            'id_fido2_credential = ' . (int) $id
        );
    }

    /**
     * Hydrate entity from database row
     *
     * @param array $row
     * @return Fido2Credential
     */
    private function hydrate(array $row): Fido2Credential
    {
        $credential = new Fido2Credential();
        $credential->setId((int) $row['id_fido2_credential'])
            ->setCustomerId((int) $row['id_customer'])
            ->setCredentialId($row['credential_id'])
            ->setCredentialPublicKey($row['credential_public_key'])
            ->setAttestationType($row['attestation_type'])
            ->setAaguid($row['aaguid'])
            ->setSignCount((int) $row['sign_count'])
            ->setTransports($row['transports'] ? json_decode($row['transports'], true) : null)
            ->setDeviceName($row['device_name'])
            ->setUserAgent($row['user_agent'])
            ->setCreatedAt(new DateTime($row['created_at']))
            ->setIsActive((bool) $row['is_active']);

        if ($row['last_used_at']) {
            $credential->setLastUsedAt(new DateTime($row['last_used_at']));
        }

        return $credential;
    }
}

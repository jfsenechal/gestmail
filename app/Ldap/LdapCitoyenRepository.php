<?php

namespace App\Ldap;

use LdapRecord\Auth\BindException;
use LdapRecord\Configuration\DomainConfiguration;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\LdapInterface;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Model;
use LdapRecord\Models\ModelDoesNotExistException;
use LdapRecord\Query\Collection;

class LdapCitoyenRepository
{
    public readonly Connection $connection;

    public function __construct(
        ?string $host = null,
        ?string $dn = null,
        ?string $user = null,
        ?string $password = null,
    ) {

        $domain = new DomainConfiguration([
            'hosts' => [$host ?? config('ldap.citoyen.host')],
            'base_dn' => $dn ?? config('ldap.citoyen.base_dn'),
            'username' => $dn ?? config('ldap.citoyen.username'),
            'password' => $dn ?? config('ldap.citoyen.password'),
            'port' => LdapInterface::PORT,
            'protocol' => 'ldap://',
            'use_ssl' => false,
            'use_tls' => false,
            'use_sasl' => false,
            'version' => 3,
            'timeout' => 5,
            'follow_referrals' => false,
        ]);

        $this->connection = new Connection($domain);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function connect(): void
    {
        if (!$this->connection->isConnected()) {
            if (!Container::hasConnection('default')) {
                Container::addConnection($this->connection);
            }

            try {
                $this->connection->connect();
            } catch (BindException|LdapRecordException  $exception) {
                throw new \Exception('Citoyen connection failed: '.$exception->getMessage().
                    ' '.$this->connection->getConfiguration()->get('hosts')[0]);
            }
        }
    }

    /**
     * @return Collection<Model>
     * @throws \Exception
     */
    public function getAll(): Collection
    {
        $this->connect();

        return CitoyenLdap::get();
    }

    /**
     * @param string $uid
     * @return Model|null
     * @throws \Exception
     */
    public function getEntry(string $uid): ?Model
    {
        $this->connect();

        return CitoyenLdap::query()->findBy('uid', $uid);
    }

    /**
     * @param string $nom
     * @return Collection|Model[]
     * @throws \Exception
     */
    public function checkExist(string $nom): Collection
    {
        $this->connect();

        return CitoyenLdap::query()
            ->orwhere('gosaMailAlternateAddress', '=', $nom)
            ->orWhere('mail', '=', $nom)
            ->orWhere('uid', '=', $nom)
            ->get();

        // $filter = "(&(|(mail=$nom)(gosaMailAlternateAddress=$nom)(gosaMailForwardingAddress=$nom)(uid=$nom))(objectClass=gosaMailAccount))";
    }

    /**
     * @param string $nom
     * @return Collection|array|Model[]
     * @throws \Exception
     */
    public function search(string $nom): Collection|array
    {
        $this->connect();

        return CitoyenLdap::query()
            ->orWhere('uid', 'contains', $nom)
            ->orWhere('mail', 'contains', $nom)
            ->orWhere('gosaMailForwardingAddress', 'contains', $nom)
            ->get();
    }

    /**
     * @return int
     * @throws \Exception
     */
    public function getLastUidNumberCitoyen(): int
    {
        $this->connect();

        return $this->getAll()
            ->map(fn($entry) => (int)$entry->getFirstAttribute('uidNumber'))
            ->max();
    }

    /**
     * @param EmailDto $emailCitoyen
     * @return CitoyenLdap
     * @throws LdapRecordException
     */
    public function createCitoyen(EmailDto $emailCitoyen): CitoyenLdap
    {
        [$uid, $domain] = explode('@', $emailCitoyen->mail);
        $firstLetter = substr($uid, 0, 1);

        $lastUidNumber = $this->getLastUidNumberCitoyen();
        $homeDirectory = $this->sieveRoot.$firstLetter.'/'.$uid;

        $citoyenModel = new Citoyen();
        $data = $citoyenModel->getData(
            $uid,
            $emailCitoyen->sn,
            $emailCitoyen->givenName,
            $emailCitoyen->mail,
            $emailCitoyen->userPassword,
            $emailCitoyen->postalAddress,
            $emailCitoyen->l,
            $emailCitoyen->postalCode,
            $homeDirectory,
            $emailCitoyen->employeeNumber,
            $lastUidNumber,
            250
        );
        $dn = "uid=".$data['uid'][0].",".$this->dn;

        $citoyenModel = new Citoyen($data);
        $citoyenModel->setDn($dn);
        $citoyenModel->save();

        return $citoyenModel;
    }

    /**
     * @param Model $model
     * @param EmailDto $original
     * @param EmailDto $emailDto
     * @return void
     * @throws LdapRecordException
     * @throws \Exception
     */
    public function update(Model $model, EmailDto $original, EmailDto $emailDto): void
    {
        $diff = array_diff_assoc((array)$emailDto, (array)$original);
        if (count($diff) > 0) {
            foreach ($diff as $key => $value) {
                $model->setAttribute($key, $value);
            }
            $this->connect();
            $model->save();
        }
    }

    /**
     * @param Model $model
     * @param iterable $alias
     * @return void
     * @throws LdapRecordException
     * @throws ModelDoesNotExistException
     * @throws \Exception
     */
    public function updateAlias(Model $model, iterable $alias): void
    {
        $model->setAttribute('gosaMailAlternateAddress', $alias);
        $this->connect();
        $model->update();
    }

    /**
     * @param Model $model
     * @param int $quota
     * @return void
     * @throws LdapRecordException
     * @throws \LdapRecord\Models\ModelDoesNotExistException
     * @throws \Exception
     */
    public function updateQuota(Model $model, int $quota): void
    {
        $model->setAttribute('gosaMailQuota', $quota);
        $this->connect();
        $model->update();
    }

    /**
     * @param Model $model
     * @param string $clearPassword
     * @return void
     * @throws LdapRecordException
     * @throws \LdapRecord\Models\ModelDoesNotExistException
     * @throws \Exception
     */
    public function changePassword(Model $model, string $clearPassword): void
    {
        $model->setAttribute('userPassword', [CitoyenLdap::cryptPassword($clearPassword)]);
        $this->connect();
        $model->update();
    }

    /**
     * @param Model $model
     * @param string $cryptedPassword
     * @return void
     * @throws LdapRecordException
     * @throws \LdapRecord\Models\ModelDoesNotExistException
     * @throws \Exception
     */
    public function restorePassword(Model $model, string $cryptedPassword): void
    {
        $model->setAttribute('userPassword', [$cryptedPassword]);
        $this->connect();
        $model->update();
    }

    /**
     * @param string $uid
     * @return void
     * @throws LdapRecordException
     * @throws \LdapRecord\Models\ModelDoesNotExistException
     * @throws \Exception
     */
    public function delete(string $uid): void
    {
        $entry = $this->getEntry($uid);
        $entry->delete();
    }
    /**
     * @param string $mail
     *
     * @return []
     */
    public function checkMailExist($mail, $list = false): array|bool
    {
        $check = [];
        $results = $this->ldapCitoyen->checkExist($mail);
        $count = $results->count();

        if ($count > 0) {
            $result = $results[0];
            $check['dn'] = $result->getFirstAttribute('dn');
            $check['uid'] = $result->getFirstAttribute('uid');
            $check['src'] = 'citoyen';

            return $check;
        }

        /**
         * Staff.
         */
        $resultStaffs = $this->ldapEmploye->checkExist($mail);
        $countStaff = $resultStaffs->count();

        if ($countStaff > 0) {
            $result = $resultStaffs[0];
            $check['dn'] = $result->getFirstAttribute('dn');
            $check['src'] = 'staff';

            return $check;
        }

        /**
         * List
         * Lors creation on ne veut pas verifier si existe
         */
        if ($list) {
            $resultLists = $this->ldapEmploye->checkExist($mail);
            $countList = $resultLists->count();

            if ($countList > 0) {
                $result = $resultLists[0];
                $check['dn'] = $result->getFirstAttribute('dn');
                $check['src'] = 'liste';

                return $check;
            }
        }

        return false;
    }

    /**
     * Retourne tous les mail d'un tableau de entries.
     *
     * @param Model[] $entries
     * @param bool $getAlternates
     *
     * @return []
     */
    public function getAllEmails(iterable $entries, $getAlternates = true, $server = 'mail'): array
    {
        $emails = [];
        foreach ($entries as $entry) {

            $mail = $entry->getFirstAttribute('mail');

            if ($mail && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $mail;
            }

            if ($getAlternates) {
                if ('mail' === $server) {
                    $alternates = $entry->getFirstAttribute('proxyAddresses', []);
                } else {
                    $alternates = $entry->getFirstAttribute('gosaMailAlternateAddress', []);
                }

                if (\is_array($alternates)) {
                    $emails = array_merge($emails, $alternates);
                }
            }
        }

        sort($emails);

        return $emails;
    }
}

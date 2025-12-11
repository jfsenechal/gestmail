<?php

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use Illuminate\Console\Command;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\ModelDoesNotExistException;

class PasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:password {uid} {password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function __construct(private readonly LdapCitoyenRepository $ldapCitoyenRepository)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $uid = $this->argument('uid') ?? null;
        $password = $this->argument('password') ?? null;

        if (!$uid || !$password) {
            $this->error('Uid et password are required');

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        try {
            $entry = $this->ldapCitoyenRepository->getEntry($uid);
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }
        if (!$entry) {
            $this->error('Citizen with uid '.$uid.' not found');

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        try {
            $this->line('Try change password ');
            $this->ldapCitoyenRepository->changePassword($entry, $password);
            $this->info('Password changed, try on https://citoyen.marche.be ');

            return \Symfony\Component\Console\Command\Command::SUCCESS;
        } catch (\Exception|ModelDoesNotExistException|LdapRecordException $e) {
            $this->error($e->getMessage());

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

    }
}

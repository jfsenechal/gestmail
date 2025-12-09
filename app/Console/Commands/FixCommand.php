<?php

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use Illuminate\Console\Command;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\ModelDoesNotExistException;

class FixCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-command {uid?} {password?}';

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

        try {
            $citoyens = $this->ldapCitoyenRepository->search($uid);
        } catch (\Exception $e) {
            $citoyens = [];
            $this->error($e->getMessage());
        }

        if (count($citoyens) === 0) {
            $this->line('not found'.$uid);

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        $this->line('Found '.count($citoyens));
        foreach ($citoyens as $citoyen) {
            $this->line($citoyen->getFirstAttribute('mail'));
        }

        if ($uid) {
            $entry = $this->ldapCitoyenRepository->getEntry($uid);
            if ($password) {
                try {
                    $this->line('Try change password ');
                    $this->ldapCitoyenRepository->changePassword($entry, $password);
                } catch (ModelDoesNotExistException $e) {
                    $this->error($e->getMessage());
                } catch (LdapRecordException $e) {
                    $this->error($e->getMessage());
                }
            }
        }

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
}

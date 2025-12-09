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
    public function handle(): void
    {
        try {
            $citoyens = $this->ldapCitoyenRepository->getAll();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            $citoyens = [];
        }
        foreach ($citoyens as $citoyen) {
            $this->line($citoyen->getFirstAttribute('mail'));
        }
        $uid = $this->argument('uid') ?? null;
        $password = $this->argument('password') ?? null;
        if ($uid) {
            $entry = $this->ldapCitoyenRepository->getEntry($uid);
            if ($password) {
                try {
                    $this->ldapCitoyenRepository->changePassword($entry, $password);
                } catch (ModelDoesNotExistException $e) {
                    $this->error($e->getMessage());
                } catch (LdapRecordException $e) {
                    $this->error($e->getMessage());
                }
            }
        }

    }
}

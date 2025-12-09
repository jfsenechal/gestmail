<?php

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use Illuminate\Console\Command;

class FixCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-command';

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
            dump($e);
            $citoyens = [];
        }
        echo count($citoyens);
        dump($citoyens);
        foreach ($citoyens->items as $citoyen) {
            $this->line($citoyen->getFirstAttribute('mail'));
        }
    }
}

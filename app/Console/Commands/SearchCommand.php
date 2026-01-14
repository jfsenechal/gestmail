<?php

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use App\Repository\LoginRepository;
use Illuminate\Console\Command;

class SearchCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:search {keyword}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recherche un compte citoyen suivant le mot clef';

    public function __construct(
        private readonly LdapCitoyenRepository $ldapCitoyenRepository,
        private readonly LoginRepository $loginRepository
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $uid = $this->argument('keyword') ?? null;

        if ($uid) {
            try {
                $citizens = $this->ldapCitoyenRepository->search($uid);
            } catch (\Exception $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            if (count($citizens) === 0) {
                $this->line('not found '.$uid);

                return self::FAILURE;
            }

            $this->line('Found '.count($citizens));
            foreach ($citizens as $citizen) {
                $username = $citizen->getFirstAttribute('uid');
                $mail = $citizen->getFirstAttribute('mail');
                $login = $this->loginRepository->findByUsername($username);

                if ($login) {
                    $this->line("{$mail} (dernière connexion : {$login->date_connect->format('d/m/Y')})");
                } else {
                    $this->line("{$mail} (pas de dernière connexion trouvée");
                }
            }
        }

        return self::SUCCESS;
    }
}

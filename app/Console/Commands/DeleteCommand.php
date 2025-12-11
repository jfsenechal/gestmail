<?php

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use Illuminate\Console\Command;
use LdapRecord\LdapRecordException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class DeleteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:delete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Suppression d\'un compte citoyen';

    public function __construct(private readonly LdapCitoyenRepository $ldapCitoyenRepository)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $mail = text(
            label: 'Adresse mail à supprimer',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL)
                ? null
                : "L'adresse mail n'a pas un format valide"
        );

        try {
            $citizen = $this->ldapCitoyenRepository->getEntryByEmail($mail);
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        if (! $citizen) {
            $this->error('Citizen with mail '.$mail.' not found');

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        $uid = $citizen->getFirstAttribute('uid');
        $this->info("Citoyen trouvé : {$uid} ({$mail})");

        $confirmed = confirm(
            label: "Êtes-vous sûr de vouloir supprimer le compte de {$uid} ?",
            default: false
        );

        if (! $confirmed) {
            $this->info('Suppression annulée.');

            return \Symfony\Component\Console\Command\Command::SUCCESS;
        }

        try {
            $this->ldapCitoyenRepository->delete($uid);
            $this->info("Le compte {$uid} a été supprimé avec succès.");

            $attribute = $citizen->getAttribute('homeDirectory');
            $chemin = (string)$attribute[0];
            $this->info("Le dossier imap peut être supprimé avec la commande rm -rI {$chemin}.");

        } catch (\Exception|LdapRecordException $exception) {
            $error = $exception->getMessage();
            if ($exception instanceof LdapRecordException) {
                $error .= ' '.$exception->getDetailedError()->getDiagnosticMessage();
            }
            $this->error("L'erreur suivante est survenue : $error");

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
}

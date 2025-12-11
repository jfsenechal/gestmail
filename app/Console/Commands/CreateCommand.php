<?php

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use App\Models\EmailDto;
use Illuminate\Console\Command;
use LdapRecord\LdapRecordException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Créer un nouveau compte citoyen';

    public function __construct(private readonly LdapCitoyenRepository $ldapCitoyenRepository)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $emailDto = new EmailDto;
        $emailDto->gosaMailQuota = 350;

        $labels = [
            'sn' => 'Nom',
            'givenName' => 'Prénom',
            'postalAddress' => 'Rue et numéro',
            'postalCode' => 'Code postal',
            'l' => 'Localité',
            'mail' => 'Email',
            'password' => 'Mot de passe',
            'employeeNumber' => 'Numéro national',
            'description' => 'Description',
        ];

        $emailDto->sn = text(
            label: $labels['sn'],
            required: true
        );

        $emailDto->givenName = text(
            label: $labels['givenName'],
            required: true
        );

        $emailDto->postalAddress = text(
            label: $labels['postalAddress'],
            required: true
        );

        $emailDto->postalCode = text(
            label: $labels['postalCode'],
            required: true
        );

        $emailDto->l = text(
            label: $labels['l'],
            required: true
        );

        $mail = text(
            label: $labels['mail'],
            placeholder: 'prenom.nom@marche.be',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL)
                ? null
                : "L'adresse mail n'a pas un format valide"
        );
        $emailDto->mail = $mail;

        $password = password(
            label: $labels['password'],
            required: true
        );
        $emailDto->userPassword = $password;

        $emailDto->employeeNumber = text(
            label: $labels['employeeNumber'],
            required: true
        );

        $emailDto->description = text(
            label: $labels['description'],
            required: false
        );

        try {
            $emailCitoyen = $this->ldapCitoyenRepository->createCitoyen($emailDto);
            $this->info('Le citoyen a bien été ajouté.');

            $this->newLine();
            $this->line('Bonjour,');
            $this->newLine();
            $this->line("L'adresse <info>{$emailDto->mail}</info> a bien été créée.");
            $this->newLine();
            $this->line('Vous pouvez la consulter sur https://www.marche.be');
            $this->newLine();
            $this->line('Vous pouvez configurer votre mail marche.be dans un logiciel de messagerie');
            $this->line('en vous aidant du tutoriel sur la page : https://www.marche.be/email/');
            $this->newLine();
            $this->line("Le nom d'utilisateur est: <info>{$emailCitoyen->getFirstAttribute('uid')}</info>");
            $this->line("Le mot de passe est: <info>{$password}</info>");
            $this->newLine();
            $this->line('Votre mot de passe peut être changé sur https://citoyen.marche.be.');
            $this->line('Lorsque vous êtes connecté, cliquez sur "Paramètres" puis "Mot de passe"');
            $this->newLine();
            $this->line('Bien à vous,');
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

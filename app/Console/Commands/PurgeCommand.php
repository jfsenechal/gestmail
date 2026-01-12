<?php

namespace App\Console\Commands;

use App\Ldap\LdapCitoyenRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use LdapRecord\LdapRecordException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class PurgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'citoyen:purge {--cutoff-date=2023-12-31 : Date limite de connexion}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Nettoyage des adresses mails inactives';

    public function __construct(private readonly LdapCitoyenRepository $ldapCitoyenRepository)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoffDate = $this->option('cutoff-date');

        try {
            $citizens = $this->ldapCitoyenRepository->getAll();
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Analyse de {$citizens->count()} comptes LDAP...");
        $this->newLine();

        $processed = 0;
        $deleted = 0;
        $skipped = 0;

        foreach ($citizens as $citizen) {
            $uid = $citizen->getFirstAttribute('uid');
            $mail = $citizen->getFirstAttribute('mail');

            $login = DB::table('login')
                ->where('username', $uid)
                ->first();

            if (! $login) {
                continue;
            }

            if ($login->date_connect >= $cutoffDate) {
                continue;
            }

            $processed++;

            $this->line(str_repeat('─', 60));
            warning("Compte inactif trouvé : {$uid} ({$mail})");
            $this->line("Dernière connexion : {$login->date_connect}");

            $sievePath = $this->getSievePath($uid);
            $sieveFiles = $this->findSieveFiles($sievePath);

            if (count($sieveFiles) > 0) {
                $this->newLine();
                info('Script(s) Sieve trouvé(s) :');

                foreach ($sieveFiles as $sieveFile) {
                    $this->line("📄 {$sieveFile}");
                    $this->newLine();
                    $content = File::get($sieveFile);
                    $this->line($content);
                    $this->newLine();
                }
            } else {
                $this->line('Aucun script Sieve trouvé.');
            }

            $confirmed = confirm(
                label: "Supprimer le compte {$uid} ?",
                default: false
            );

            if ($confirmed) {
                try {
                    $this->ldapCitoyenRepository->delete($uid);
                    $homeDirectory = $citizen->getFirstAttribute('homeDirectory');
                    $this->info("✓ Compte {$uid} supprimé.");
                    $this->line("  Pour supprimer le dossier imap : rm -rI {$homeDirectory}");
                    $deleted++;
                } catch (\Exception|LdapRecordException $e) {
                    $this->error("Erreur lors de la suppression : {$e->getMessage()}");
                }
            } else {
                $this->line("→ Compte {$uid} conservé.");
                $skipped++;
            }

            $this->newLine();
        }

        $this->line(str_repeat('═', 60));
        $this->info("Résumé : {$processed} comptes analysés, {$deleted} supprimés, {$skipped} conservés.");

        return self::SUCCESS;
    }

    /**
     * Construit le chemin vers le dossier sieve d'un utilisateur.
     */
    private function getSievePath(string $uid): string
    {
        $firstLetter = substr($uid, 0, 1);

        return $this->ldapCitoyenRepository->sieveRoot.$firstLetter.'/'.$uid.'/sieve';
    }

    /**
     * Recherche les fichiers sieve dans un répertoire.
     *
     * @return array<string>
     */
    private function findSieveFiles(string $path): array
    {
        if (! File::isDirectory($path)) {
            return [];
        }

        return File::glob($path.'/*.sieve');
    }
}

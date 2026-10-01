<?php

namespace App\Console\Commands;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retire les cabinets qui ne portent rien.
 *
 * Une personne pouvait ouvrir plusieurs cabinets sans appartenir à aucun : la
 * liste s'est remplie de maisons qui n'ont jamais tenu un dossier, parfois en
 * double sous le même nom. Elles encombrent chaque liste déroulante, et on ne
 * sait plus laquelle est la vraie.
 *
 * La règle est stricte et ne se discute pas : un cabinet ne part QUE s'il ne
 * porte AUCUNE comptabilité. Aucun dossier, aucun compte utilisateur ne peut
 * donc être perdu par cette commande — au pire, des liens d'appartenance vers
 * une maison vide.
 *
 * Pour ranger les dossiers avant de nettoyer :
 *     php artisan cabinets:rattacher-dossiers --cabinet=<id> --forcer --appliquer
 */
class NettoyerLesCabinets extends Command
{
    protected $signature = 'cabinets:nettoyer
                            {--garder= : Ne jamais toucher à ce cabinet (identifiant ou nom)}
                            {--transferer-vers= : Faire entrer les membres des cabinets retirés dans ce cabinet}
                            {--appliquer : Enregistre le nettoyage (sans cette option, simple simulation)}';

    protected $description = "Retire les cabinets qui ne portent aucune comptabilité";

    /**
     * Un cabinet désigné par son identifiant ou son nom, sans ambiguïté.
     *
     * Un nom approché qui ramène plusieurs maisons est refusé : se tromper de
     * cabinet ne se rattrape pas.
     */
    private function designer(string $designation): ?Cabinet
    {
        if (ctype_digit($designation)) {
            $cabinet = Cabinet::find($designation);

            if (!$cabinet) {
                $this->error('Aucun cabinet n\'a l\'identifiant ' . $designation . '.');
            }

            return $cabinet;
        }

        if ($exact = Cabinet::where('nom', $designation)->first()) {
            return $exact;
        }

        $approchants = Cabinet::where('nom', 'like', '%' . $designation . '%')->get(['id', 'nom']);

        if ($approchants->count() === 1) {
            return Cabinet::find($approchants->first()->id);
        }

        if ($approchants->isEmpty()) {
            $this->error('Aucun cabinet ne correspond à « ' . $designation . ' ».');
            return null;
        }

        $this->error('Plusieurs cabinets portent « ' . $designation . " ». Désignez-en un par son identifiant :");
        foreach ($approchants as $a) {
            $this->line(sprintf('    %-5s %s', $a->id, $a->nom));
        }

        return null;
    }

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');

        DB::connection()->disableQueryLog();

        if (!$appliquer) {
            $this->warn('Mode simulation : rien ne sera supprimé. Ajoutez --appliquer pour enregistrer.');
        }

        $protege = null;

        if ($this->option('garder')) {
            $protege = $this->designer((string) $this->option('garder'));

            if (!$protege) {
                return self::FAILURE;
            }
        }

        // Les membres d'un cabinet retiré n'ont pas à se retrouver sans
        // maison : on peut les faire entrer dans celle qu'on garde.
        $accueil = null;

        if ($this->option('transferer-vers')) {
            $accueil = $this->designer((string) $this->option('transferer-vers'));

            if (!$accueil) {
                return self::FAILURE;
            }
        }

        $dossiers = Company::whereNotNull('cabinet_id')
            ->selectRaw('cabinet_id, COUNT(*) as n')->groupBy('cabinet_id')->pluck('n', 'cabinet_id');

        $membres = DB::table('cabinet_user')
            ->selectRaw('cabinet_id, COUNT(*) as n')->groupBy('cabinet_id')->pluck('n', 'cabinet_id');

        $cabinets = Cabinet::orderBy('nom')->get(['id', 'nom', 'user_id']);

        $aRetirer = [];
        $gardes = [];

        foreach ($cabinets as $c) {
            $porte = (int) ($dossiers[$c->id] ?? 0);

            if ($porte > 0 || ($protege && $protege->id === $c->id)) {
                $gardes[] = ['cabinet' => $c, 'dossiers' => $porte];
                continue;
            }

            $aRetirer[] = ['cabinet' => $c, 'membres' => (int) ($membres[$c->id] ?? 0)];
        }

        $this->newLine();
        $this->line('Cabinets gardés — ils portent des comptabilités :');

        foreach ($gardes as $g) {
            $gerant = User::find($g['cabinet']->user_id);
            $this->line(sprintf('    %-5s %-38s %4d dossier(s)   gérant : %s',
                $g['cabinet']->id,
                mb_strimwidth($g['cabinet']->nom, 0, 38, '…'),
                $g['dossiers'],
                $gerant ? trim($gerant->name . ' ' . $gerant->last_name) : 'aucun'));
        }

        if ($aRetirer === []) {
            $this->newLine();
            $this->info('Aucun cabinet vide : rien à nettoyer.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn(count($aRetirer) . ' cabinet(s) ne portent aucune comptabilité :');

        $liens = 0;

        foreach ($aRetirer as $r) {
            $gerant = User::find($r['cabinet']->user_id);
            $liens += $r['membres'];

            $this->line(sprintf('    %-5s %-38s %4d membre(s)    gérant : %s',
                $r['cabinet']->id,
                mb_strimwidth($r['cabinet']->nom, 0, 38, '…'),
                $r['membres'],
                $gerant ? trim($gerant->name . ' ' . $gerant->last_name) : 'aucun'));
        }

        $this->newLine();
        $this->line('Aucune comptabilité ni aucun compte utilisateur ne sera touché.');

        if ($accueil) {
            $this->line('Les ' . $liens . ' membre(s) de ces cabinets entreront dans « ' . $accueil->nom . ' ».');
        } else {
            $this->line('Seuls partent ces cabinets et les ' . $liens . ' lien(s) d\'appartenance qui y menaient.');
            $this->line('Pour les faire entrer ailleurs : --transferer-vers=<cabinet>');
        }

        if (!$appliquer) {
            $this->newLine();
            $this->line('Relancez avec --appliquer pour enregistrer.');
            return self::SUCCESS;
        }

        $ids = collect($aRetirer)->pluck('cabinet.id');

        DB::transaction(function () use ($ids, $accueil) {
            if ($accueil) {
                // Chaque personne entre dans la maison d'accueil, sans doublon :
                // la table n'accepte qu'un lien par couple cabinet-personne.
                $deja = DB::table('cabinet_user')->where('cabinet_id', $accueil->id)->pluck('user_id');

                $aFaireEntrer = DB::table('cabinet_user')
                    ->whereIn('cabinet_id', $ids)->whereNotIn('user_id', $deja)
                    ->pluck('user_id')->unique();

                foreach ($aFaireEntrer as $userId) {
                    DB::table('cabinet_user')->insert([
                        'cabinet_id' => $accueil->id,
                        'user_id' => $userId,
                        'role' => 'collaborateur',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('cabinet_user')->whereIn('cabinet_id', $ids)->delete();
            Cabinet::whereIn('id', $ids)->delete();
        });

        $this->newLine();
        $this->info(count($aRetirer) . ' cabinet(s) retiré(s).');

        return self::SUCCESS;
    }
}

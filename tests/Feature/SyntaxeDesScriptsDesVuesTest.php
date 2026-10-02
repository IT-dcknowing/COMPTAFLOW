<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Le JavaScript écrit dans les vues doit se compiler.
 *
 * Une apostrophe française dans une chaîne à guillemets simples — « l'écran »,
 * « d'un compte » — casse tout le bloc `<script>`, et donc toute la
 * fonctionnalité qu'il porte. Rien ne le signale : la page s'affiche, le
 * morceau ne marche plus. C'est arrivé deux fois, sur la carte des soldes puis
 * sur le pied de page commun à toutes les pages.
 *
 * Ce test lit les vues, en extrait chaque bloc de script et le passe au
 * vérificateur de Node.
 */
class SyntaxeDesScriptsDesVuesTest extends TestCase
{
    /**
     * Les vues qui portent du JavaScript qu'on a écrit.
     *
     * @var array<int, string>
     */
    private const VUES = [
        'components/soldes_journal.blade.php',
        'components/filtre_colonnes.blade.php',
        'components/footer.blade.php',
        'accounting_entry_real.blade.php',
        'superadmin/corbeille.blade.php',
        'superadmin/affectations.blade.php',
        'superadmin/archives.blade.php',
        'superadmin/entities.blade.php',
        'exercice_comptable.blade.php',
        'adjustment/bulk_edit.blade.php',
        'adjustment/reimputation.blade.php',
        'adjustment/copie.blade.php',
    ];

    public function test_chaque_bloc_de_script_se_compile(): void
    {
        if (!$this->nodeDisponible()) {
            $this->markTestSkipped('Node n\'est pas disponible : la vérification est sautée.');
        }

        $fautifs = [];

        foreach (self::VUES as $vue) {
            $chemin = resource_path('views/' . $vue);

            if (!is_file($chemin)) {
                continue;
            }

            foreach ($this->blocsDeScript(file_get_contents($chemin)) as $rang => $bloc) {
                $erreur = $this->erreurDeSyntaxe($bloc);

                if ($erreur !== null) {
                    $fautifs[] = sprintf('%s (bloc %d) : %s', $vue, $rang + 1, $erreur);
                }
            }
        }

        $this->assertSame([], $fautifs, "Du JavaScript des vues ne se compile pas :\n" . implode("\n", $fautifs));
    }

    /**
     * Les blocs `<script>` écrits en ligne, hors ceux qui chargent un fichier.
     *
     * @return array<int, string>
     */
    private function blocsDeScript(string $vue): array
    {
        preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>#s', $vue, $trouves);

        $blocs = [];

        foreach ($trouves[1] as $bloc) {
            // Les directives Blade ne sont pas du JavaScript : on les remplace
            // par une valeur neutre avant de juger la syntaxe.
            $bloc = preg_replace('/@json\s*\((?:[^()]|\([^()]*\))*\)/s', '0', $bloc);
            $bloc = preg_replace('/\{\{.*?\}\}/s', '0', $bloc);
            $bloc = preg_replace('/\{!!.*?!!\}/s', '0', $bloc);
            $bloc = preg_replace('/@(if|elseif|else|endif|foreach|endforeach|forelse|empty|endforelse|php|endphp|csrf|method|selected|checked)\b[^\n]*/', '', $bloc);

            if (trim($bloc) !== '') {
                $blocs[] = $bloc;
            }
        }

        return $blocs;
    }

    /**
     * La première erreur de syntaxe du bloc, ou null s'il se compile.
     */
    private function erreurDeSyntaxe(string $bloc): ?string
    {
        $fichier = tempnam(sys_get_temp_dir(), 'vue_') . '.js';
        file_put_contents($fichier, $bloc);

        $sortie = [];
        $code = 0;
        exec('node --check ' . escapeshellarg($fichier) . ' 2>&1', $sortie, $code);

        @unlink($fichier);

        if ($code === 0) {
            return null;
        }

        foreach ($sortie as $ligne) {
            if (str_contains($ligne, 'SyntaxError')) {
                return trim($ligne);
            }
        }

        return trim(implode(' ', array_slice($sortie, 0, 2)));
    }

    private function nodeDisponible(): bool
    {
        $sortie = [];
        $code = 0;
        exec('node --version 2>&1', $sortie, $code);

        return $code === 0;
    }
}

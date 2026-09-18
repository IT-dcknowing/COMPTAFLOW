<?php

namespace Tests\Feature;

use App\Services\NumerotationSaisie;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Numérotation des saisies : format, cloisonnement par dossier, et surtout
 * le défaut qui a figé tout un import sur un seul numéro.
 *
 * L'import universel accumule ses lignes et ne les écrit en base que par
 * paquets de mille. L'ancien générateur relisait la base à chaque pièce :
 * la base ignorant le paquet en cours, toutes les pièces recevaient le même
 * numéro (ECR_000000000020 pour un journal entier), et la liste des écritures
 * les présentait alors comme une seule et même pièce.
 */
class NumerotationSaisieTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('ecriture_comptables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('n_saisie')->nullable();
            $table->string('n_saisie_user', 50)->nullable();
        });

        NumerotationSaisie::oublierReservations();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('ecriture_comptables');
        NumerotationSaisie::oublierReservations();

        parent::tearDown();
    }

    private function poser(int $companyId, ?string $global = null, ?string $utilisateur = null): void
    {
        DB::table('ecriture_comptables')->insert([
            'company_id' => $companyId,
            'n_saisie' => $global,
            'n_saisie_user' => $utilisateur,
        ]);
    }

    public function test_le_numero_porte_la_date_du_jour_et_une_sequence(): void
    {
        $attendu = 'ECR-' . now()->format('dmy') . '-000001';

        $this->assertSame($attendu, NumerotationSaisie::global(1));
    }

    public function test_le_numero_utilisateur_porte_les_initiales(): void
    {
        $attendu = 'CPT-AG-' . now()->format('dmy') . '-000001';

        $this->assertSame($attendu, NumerotationSaisie::utilisateur(1, 'AG'));
    }

    public function test_la_sequence_avance_saisie_apres_saisie(): void
    {
        $premier = NumerotationSaisie::global(1);
        $this->poser(1, $premier);

        NumerotationSaisie::oublierReservations();
        $second = NumerotationSaisie::global(1);

        $this->assertSame('ECR-' . now()->format('dmy') . '-000002', $second);
    }

    /** Le défaut d'origine : des numéros distribués avant toute écriture en base. */
    public function test_un_import_par_lots_ne_reutilise_pas_le_meme_numero(): void
    {
        $numeros = [];
        for ($i = 0; $i < 50; $i++) {
            $numeros[] = NumerotationSaisie::global(1, null, '2026-02-18');
        }

        $this->assertCount(50, array_unique($numeros), 'Chaque pièce doit avoir son propre numéro.');
        $this->assertSame('ECR-180226-000001', $numeros[0]);
        $this->assertSame('ECR-180226-000050', $numeros[49]);
    }

    public function test_la_numerotation_est_propre_a_chaque_entreprise(): void
    {
        $this->poser(1, NumerotationSaisie::global(1));
        NumerotationSaisie::oublierReservations();

        $this->assertSame(
            'ECR-' . now()->format('dmy') . '-000001',
            NumerotationSaisie::global(2),
            "Le dossier 2 ne doit pas hériter du compteur du dossier 1."
        );
    }

    public function test_deux_utilisateurs_ont_chacun_leur_suite(): void
    {
        $this->poser(1, null, NumerotationSaisie::utilisateur(1, 'AG'));
        NumerotationSaisie::oublierReservations();

        $this->assertSame(
            'CPT-KS-' . now()->format('dmy') . '-000001',
            NumerotationSaisie::utilisateur(1, 'KS')
        );
    }

    public function test_la_date_de_la_piece_prime_sur_la_date_du_jour(): void
    {
        $this->assertSame('ECR-030126-000001', NumerotationSaisie::global(1, null, '2026-01-03'));
    }

    /** Les anciens numéros restent en base sans perturber la nouvelle suite. */
    public function test_les_anciens_numeros_sont_ignores_par_le_compteur(): void
    {
        $this->poser(1, 'ECR_000000000020', 'CPT-AG_000000000012');

        $this->assertSame('ECR-' . now()->format('dmy') . '-000001', NumerotationSaisie::global(1));
        NumerotationSaisie::oublierReservations();
        $this->assertSame('CPT-AG-' . now()->format('dmy') . '-000001', NumerotationSaisie::utilisateur(1, 'AG'));
    }

    public function test_un_numero_deja_pris_est_saute(): void
    {
        // Numéro posé à la main, sans que le compteur du jour l'ait distribué.
        $this->poser(1, 'ECR-' . now()->format('dmy') . '-000001');

        $this->assertSame('ECR-' . now()->format('dmy') . '-000002', NumerotationSaisie::global(1));
    }

    public function test_des_initiales_vides_donnent_un_prefixe_utilisable(): void
    {
        $this->assertSame('CPT-XX-', NumerotationSaisie::prefixeUtilisateur(''));
    }
}

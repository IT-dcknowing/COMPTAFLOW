<?php

namespace Tests\Feature;

use App\Models\ArchivedRecord;
use App\Models\EcritureComptable;
use App\Services\SuppressionTracee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Aucune suppression ne doit échapper à l'archive.
 *
 * `EcritureComptable::where(...)->delete()` partait directement en SQL : les
 * événements Eloquent ne se déclenchaient pas, donc ni l'archive des
 * suppressions ni le journal d'audit n'en gardaient trace. Des écritures ont
 * ainsi disparu sans qu'on puisse dire qui les avait supprimées.
 */
class SuppressionTraceeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('ecriture_comptables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('n_saisie')->nullable();
            $table->date('date')->nullable();
            $table->string('description_operation')->nullable();
            $table->decimal('debit', 20, 2)->default(0);
            $table->decimal('credit', 20, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('archived_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('label')->nullable();
            $table->json('data')->nullable();
            $table->uuid('batch_id')->nullable();
            $table->unsignedInteger('batch_size')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Le journal d'audit n'écrit que pour un utilisateur connecté : les
        // tests ci-dessous portent sur l'archive, mais la table doit exister.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('action');
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('description')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('archived_records');
        Schema::dropIfExists('ecriture_comptables');

        parent::tearDown();
    }

    private function poser(int $combien, string $nSaisie = 'ECR-200926-000001'): void
    {
        for ($i = 0; $i < $combien; $i++) {
            DB::table('ecriture_comptables')->insert([
                'company_id' => 7,
                'n_saisie' => $nSaisie,
                'date' => '2026-09-18',
                'description_operation' => 'ACHAT DIVERS ' . $i,
                'debit' => 3000,
                'credit' => 0,
            ]);
        }
    }

    /** @return \Illuminate\Database\Eloquent\Builder */
    private function ecritures()
    {
        return EcritureComptable::withoutGlobalScopes()->where('company_id', 7);
    }

    public function test_une_suppression_en_masse_est_archivee(): void
    {
        $this->poser(5);

        $supprimees = $this->ecritures()->delete();

        $this->assertSame(5, $supprimees);
        $this->assertSame(0, $this->ecritures()->count());
        $this->assertSame(5, ArchivedRecord::count(), 'Chaque ligne supprimée doit être archivée.');
    }

    public function test_les_lignes_dune_meme_suppression_partagent_un_lot(): void
    {
        $this->poser(4);

        $this->ecritures()->delete();

        $lots = ArchivedRecord::pluck('batch_id')->unique();
        $this->assertCount(1, $lots);
        $this->assertNotNull($lots->first());
        $this->assertSame(4, (int) ArchivedRecord::value('batch_size'));
    }

    public function test_une_ligne_deja_chargee_nest_archivee_quune_fois(): void
    {
        $this->poser(1);

        $this->ecritures()->first()->delete();

        $this->assertSame(1, ArchivedRecord::count(), "L'archive ne doit pas être écrite deux fois.");
    }

    public function test_larchive_conserve_le_contenu_supprime(): void
    {
        $this->poser(1);

        $this->ecritures()->delete();

        $archive = ArchivedRecord::first();
        // Comparaison numérique : la forme du décimal dépend du moteur.
        $this->assertEquals(3000, (float) $archive->data['debit']);
        $this->assertSame('ACHAT DIVERS 0', $archive->data['description_operation']);
        $this->assertStringContainsString('ECR-200926-000001', $archive->label);
        $this->assertStringContainsString('18/09/2026', $archive->label);
    }

    public function test_la_suppression_est_datee_et_sa_purge_programmee(): void
    {
        $this->poser(1);

        $this->ecritures()->delete();

        $archive = ArchivedRecord::first();
        $this->assertNotNull($archive->deleted_at);
        $this->assertSame(
            ArchivedRecord::RETENTION_JOURS,
            (int) round($archive->deleted_at->diffInDays($archive->expires_at))
        );
    }

    public function test_seules_les_lignes_visees_partent(): void
    {
        $this->poser(3, 'ECR-200926-000001');
        $this->poser(2, 'ECR-200926-000002');

        SuppressionTracee::supprimer(
            EcritureComptable::withoutGlobalScopes()->where('n_saisie', 'ECR-200926-000002')
        );

        $this->assertSame(3, $this->ecritures()->count());
        $this->assertSame(2, ArchivedRecord::count());
    }

    /** Au-delà d'un paquet, le parcours ne doit ni boucler ni en oublier. */
    public function test_un_gros_volume_passe_entierement(): void
    {
        $this->poser(1200);

        $supprimees = $this->ecritures()->delete();

        $this->assertSame(1200, $supprimees);
        $this->assertSame(0, $this->ecritures()->count());
        $this->assertSame(1200, ArchivedRecord::count());
    }
}

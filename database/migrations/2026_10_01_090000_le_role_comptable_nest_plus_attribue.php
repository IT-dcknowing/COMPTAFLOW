<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un compte peut n'avoir aucun rôle.
 *
 * Jusqu'ici la colonne exigeait une valeur, et « comptable » servait de
 * remplissage : une personne qu'on venait de créer, rattachée à aucune
 * comptabilité, s'affichait partout comme comptable alors qu'elle ne pouvait
 * rien faire. Le titre annonçait des droits que le compte n'avait pas.
 *
 * Désormais le rôle reste vide tant que la personne n'a rien ouvert. Ce qu'elle
 * peut faire se lit sur la comptabilité : celui qui crée un dossier en est
 * l'administrateur, et un collaborateur n'a que les cases qu'on lui a cochées.
 *
 * On ne touche à aucune ligne existante : vider un rôle est l'affaire de la
 * commande `comptes:role-comptable`, qui se simule avant de s'appliquer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // On relit le type en place au lieu de le redéclarer : la liste des
            // valeurs n'est pas la même partout selon les migrations déjà
            // passées, et la réécrire de mémoire effacerait un rôle existant.
            // Seule l'obligation de remplir la colonne est levée.
            DB::statement('ALTER TABLE `users` MODIFY `role` ' . $this->typeEnPlace() . ' NULL DEFAULT NULL');

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable()->change();
        });
    }

    public function down(): void
    {
        // On ne peut pas redevenir obligatoire sans inventer un rôle pour les
        // comptes qui n'en ont pas : on leur rend celui qui servait de
        // remplissage, puis on referme la colonne.
        DB::table('users')->whereNull('role')->update(['role' => 'comptable']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `users` MODIFY `role` ' . $this->typeEnPlace() . ' NOT NULL');

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable(false)->change();
        });
    }

    /** Le type exact de la colonne, tel qu'il est aujourd'hui en base. */
    private function typeEnPlace(): string
    {
        $colonne = DB::selectOne(
            'SELECT COLUMN_TYPE AS type FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['users', 'role']
        );

        return $colonne->type ?? "ENUM('comptable','admin','super_admin')";
    }
};

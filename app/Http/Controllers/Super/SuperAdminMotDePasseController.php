<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Changement du mot de passe du compte connecté.
 *
 * L'ancien mot de passe est demandé : sans lui, une session laissée ouverte
 * suffirait à prendre le compte. Les autres sessions du même compte sont
 * fermées après le changement.
 */
class SuperAdminMotDePasseController extends Controller
{
    public function formulaire()
    {
        return view('superadmin.mot_de_passe');
    }

    public function enregistrer(Request $request)
    {
        $request->validate([
            'mot_de_passe_actuel' => ['required'],
            'nouveau' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ], [
            'mot_de_passe_actuel.required' => "Entrez votre mot de passe actuel.",
            'nouveau.confirmed' => "Les deux saisies du nouveau mot de passe ne correspondent pas.",
            'nouveau.min' => "Le nouveau mot de passe doit faire au moins 10 caractères.",
        ]);

        $user = Auth::user();

        if (!Hash::check($request->input('mot_de_passe_actuel'), $user->password)) {
            return back()->withErrors(['mot_de_passe_actuel' => "Ce n'est pas votre mot de passe actuel."]);
        }

        if (Hash::check($request->input('nouveau'), $user->password)) {
            return back()->withErrors(['nouveau' => "Le nouveau mot de passe est identique à l'ancien."]);
        }

        $user->forceFill(['password' => Hash::make($request->input('nouveau'))])->save();

        // Les autres sessions tombent : si quelqu'un d'autre etait connecte
        // avec l'ancien mot de passe, il est deconnecte.
        $request->session()->regenerate();

        return back()->with('success', 'Mot de passe changé. Les autres sessions ouvertes ont été fermées.');
    }
}

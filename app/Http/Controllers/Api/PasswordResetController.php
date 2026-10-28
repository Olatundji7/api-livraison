<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

/**
 * Mot de passe oublié (clients) : code à 6 chiffres envoyé par e-mail.
 * - code valable 15 minutes, 5 essais maximum, stocké haché ;
 * - même réponse que l'adresse existe ou non (pas de fuite d'information) ;
 * - réservé aux comptes « client » (les livreurs passent par l'administrateur).
 */
class PasswordResetController extends Controller
{
    private const TTL_MINUTES = 15;
    private const MAX_ATTEMPTS = 5;

    public function forgot(Request $request)
    {
        $data = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'Saisissez votre adresse e-mail.',
            'email.email' => 'Adresse e-mail invalide.',
        ])->validate();

        $email = mb_strtolower(trim($data['email']));
        $generic = response()->json([
            'message' => 'Si un compte client existe avec cette adresse, un code de confirmation vient d’être envoyé.',
        ]);

        $user = User::whereRaw('LOWER(email) = ?', [$email])->where('role', 'client')->first();
        if (! $user) {
            return $generic;
        }

        // Un seul envoi par minute et par adresse.
        $recent = DB::table('password_reset_codes')
            ->where('email', $email)
            ->where('created_at', '>', now()->subSeconds(60))
            ->exists();
        if ($recent) {
            return $generic;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_codes')->where('email', $email)->delete();
        DB::table('password_reset_codes')->insert([
            'email' => $email,
            'code_hash' => $this->hash($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'created_at' => now(),
        ]);

        try {
            $body = "Bonjour {$user->name},\n\n"
                . "Votre code de confirmation MA Livraison est : {$code}\n\n"
                . 'Il est valable ' . self::TTL_MINUTES . " minutes.\n"
                . "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.\n\n"
                . 'MA Livraison';

            Mail::raw($body, function ($message) use ($email) {
                $message->to($email)->subject('MA Livraison – Code de réinitialisation du mot de passe');
            });
        } catch (\Throwable $e) {
            Log::error('Envoi du code de réinitialisation impossible', ['error' => $e->getMessage()]);
        }

        return $generic;
    }

    public function reset(Request $request)
    {
        $data = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
        ], [
            'code.digits' => 'Le code contient 6 chiffres.',
            'password.min' => 'Le mot de passe doit contenir au moins 6 caractères.',
        ])->validate();

        $email = mb_strtolower(trim($data['email']));
        $invalid = fn () => response()->json(['message' => 'Code invalide ou expiré.'], 422);

        $row = DB::table('password_reset_codes')->where('email', $email)->first();
        if (! $row || Carbon::parse($row->expires_at)->isPast() || $row->attempts >= self::MAX_ATTEMPTS) {
            if ($row) {
                DB::table('password_reset_codes')->where('email', $email)->delete();
            }

            return $invalid();
        }

        if (! hash_equals($row->code_hash, $this->hash($data['code']))) {
            DB::table('password_reset_codes')->where('id', $row->id)->increment('attempts');

            return $invalid();
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->where('role', 'client')->first();
        if (! $user) {
            DB::table('password_reset_codes')->where('email', $email)->delete();

            return $invalid();
        }

        $user->password = Hash::make($data['password']);
        $user->save();
        $user->tokens()->delete(); // déconnecte les anciens appareils

        DB::table('password_reset_codes')->where('email', $email)->delete();

        return response()->json(['message' => 'Mot de passe modifié. Vous pouvez vous connecter.']);
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}

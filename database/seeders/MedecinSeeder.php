<?php

namespace Database\Seeders;

use App\Models\Medecin;
use App\Models\Specialite;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MedecinSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('Password123!');

        $medecins = [
            [
                'email' => 'medecin@croixbleue.sn', // déjà créé par UserSeeder
                'specialite' => 'Médecine générale',
                'titre' => 'Docteur en médecine — UCAD',
                'annees_experience' => 15,
                'bio' => "Médecin généraliste, plus de 15 ans d'expérience à la Clinique Croix Bleue.",
            ],
            [
                'prenom' => 'Ousmane',
                'nom' => 'Diallo',
                'email' => 'o.diallo@croixbleue.sn',
                'telephone' => '+221776781234',
                'sexe' => 'M',
                'date_naissance' => '1975-02-18',
                'specialite' => 'Cardiologie',
                'titre' => 'Cardiologue — Doctorat UCAD',
                'annees_experience' => 20,
                'bio' => 'Spécialiste des pathologies cardiovasculaires.',
            ],
            [
                'prenom' => 'Aïssatou',
                'nom' => 'Diagne',
                'email' => 'a.diagne@croixbleue.sn',
                'telephone' => '+221776782345',
                'sexe' => 'F',
                'date_naissance' => '1982-07-09',
                'specialite' => 'Pédiatrie',
                'titre' => 'Pédiatre — Doctorat UCAD',
                'annees_experience' => 12,
                'bio' => 'Suivi médical des nourrissons, enfants et adolescents.',
            ],
            [
                'prenom' => 'Mariama',
                'nom' => 'Cissé',
                'email' => 'm.cisse@croixbleue.sn',
                'telephone' => '+221776783456',
                'sexe' => 'F',
                'date_naissance' => '1980-11-25',
                'specialite' => 'Gynécologie',
                'titre' => 'Gynécologue-obstétricienne',
                'annees_experience' => 16,
                'bio' => 'Suivi de grossesse et santé de la femme.',
            ],
            [
                'prenom' => 'Cheikh',
                'nom' => 'Gueye',
                'email' => 'c.gueye@croixbleue.sn',
                'telephone' => '+221776784567',
                'sexe' => 'M',
                'date_naissance' => '1988-03-14',
                'specialite' => 'Dermatologie',
                'titre' => 'Dermatologue',
                'annees_experience' => 8,
                'bio' => 'Prise en charge des affections de la peau.',
            ],
            [
                'prenom' => 'Fatoumata',
                'nom' => 'Sy',
                'email' => 'f.sy@croixbleue.sn',
                'telephone' => '+221776785678',
                'sexe' => 'F',
                'date_naissance' => '1979-05-30',
                'specialite' => 'ORL',
                'titre' => 'ORL — Doctorat UCAD',
                'annees_experience' => 18,
                'bio' => "Oto-rhino-laryngologiste, prise en charge des troubles ORL.",
            ],
        ];

        foreach ($medecins as $data) {
            $user = User::where('email', $data['email'])->first();

            if (! $user) {
                $user = User::create([
                    'prenom' => $data['prenom'],
                    'nom' => $data['nom'],
                    'email' => $data['email'],
                    'telephone' => $data['telephone'],
                    'password' => $password,
                    'role' => 'medecin',
                    'sexe' => $data['sexe'],
                    'date_naissance' => $data['date_naissance'],
                    'est_actif' => true,
                ]);
            }

            $specialite = Specialite::where('nom', $data['specialite'])->firstOrFail();

            Medecin::create([
                'user_id' => $user->id,
                'specialite_id' => $specialite->id,
                'titre' => $data['titre'],
                'annees_experience' => $data['annees_experience'],
                'bio' => $data['bio'],
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('Password123!');

        $users = [
            [
                'prenom' => 'Awa',
                'nom' => 'Diop',
                'email' => 'admin@croixbleue.sn',
                'telephone' => '+221771234567',
                'role' => 'admin',
                'sexe' => 'F',
                'date_naissance' => '1985-04-12',
            ],
            [
                'prenom' => 'Moussa',
                'nom' => 'Fall',
                'email' => 'medecin@croixbleue.sn',
                'telephone' => '+221772345678',
                'role' => 'medecin',
                'sexe' => 'M',
                'date_naissance' => '1978-09-23',
            ],
            [
                'prenom' => 'Fatou',
                'nom' => 'Ndiaye',
                'email' => 'secretaire@croixbleue.sn',
                'telephone' => '+221773456789',
                'role' => 'secretaire',
                'sexe' => 'F',
                'date_naissance' => '1992-01-30',
            ],
            [
                'prenom' => 'Ibrahima',
                'nom' => 'Sarr',
                'email' => 'patient1@croixbleue.sn',
                'telephone' => '+221774567890',
                'role' => 'patient',
                'sexe' => 'M',
                'date_naissance' => '1995-06-15',
            ],
            [
                'prenom' => 'Aminata',
                'nom' => 'Ba',
                'email' => 'patient2@croixbleue.sn',
                'telephone' => '+221775678901',
                'role' => 'patient',
                'sexe' => 'F',
                'date_naissance' => '2000-11-02',
            ],
        ];

        foreach ($users as $user) {
            User::create($user + [
                'password' => $password,
                'est_actif' => true,
            ]);
        }
    }
}

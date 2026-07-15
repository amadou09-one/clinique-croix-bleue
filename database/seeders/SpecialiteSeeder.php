<?php

namespace Database\Seeders;

use App\Models\Specialite;
use Illuminate\Database\Seeder;

class SpecialiteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $specialites = [
            ['nom' => 'Cardiologie', 'icone' => 'heart'],
            ['nom' => 'Pédiatrie', 'icone' => 'baby'],
            ['nom' => 'Gynécologie', 'icone' => 'female'],
            ['nom' => 'Médecine générale', 'icone' => 'stethoscope'],
            ['nom' => 'Dermatologie', 'icone' => 'skin'],
            ['nom' => 'ORL', 'icone' => 'ear'],
            ['nom' => 'Neurologie', 'icone' => 'brain'],
            ['nom' => 'Laboratoire', 'icone' => 'flask'],
        ];

        foreach ($specialites as $specialite) {
            Specialite::create($specialite);
        }
    }
}

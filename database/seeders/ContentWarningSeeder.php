<?php

namespace Database\Seeders;

use App\Models\ContentWarning;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContentWarningSeeder extends Seeder
{
    public function run(): void
    {
        $warnings = [
            ['name' => 'Nudity', 'description' => 'Non-sexual nudity or partial nudity.'],
            ['name' => 'Sexual Content', 'description' => 'Sex scenes or explicit sexual content.'],
            ['name' => 'Violence', 'description' => 'Depictions of physical violence.'],
            ['name' => 'Gore', 'description' => 'Graphic injury or gore.'],
            ['name' => 'Self-Harm', 'description' => 'Self-harm, suicide, or related themes.'],
            ['name' => 'Substance Use', 'description' => 'Alcohol, drugs, or substance abuse themes.'],
        ];

        foreach ($warnings as $warning) {
            ContentWarning::query()->updateOrCreate(
                ['slug' => Str::slug($warning['name'])],
                [
                    'name' => $warning['name'],
                    'slug' => Str::slug($warning['name']),
                    'description' => $warning['description'],
                ]
            );
        }
    }
}






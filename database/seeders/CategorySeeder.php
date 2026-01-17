<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Anime', 'type' => 'predefined'],
            ['name' => 'Live Action', 'type' => 'predefined'],
            ['name' => 'Action', 'type' => 'predefined'],
            ['name' => 'Comedy', 'type' => 'predefined'],
            ['name' => 'Drama', 'type' => 'predefined'],
            ['name' => 'Romance', 'type' => 'predefined'],
            ['name' => 'Sci-Fi', 'type' => 'predefined'],
            ['name' => 'Fantasy', 'type' => 'predefined'],
            ['name' => 'Horror', 'type' => 'predefined'],
            ['name' => 'Mystery', 'type' => 'predefined'],
            ['name' => 'Slice of Life', 'type' => 'predefined'],
            ['name' => 'Sports', 'type' => 'predefined'],
            ['name' => 'Thriller', 'type' => 'predefined'],
        ];

        foreach ($categories as $cat) {
            Category::query()->updateOrCreate(
                ['slug' => Str::slug($cat['name'])],
                [
                    'name' => $cat['name'],
                    'slug' => Str::slug($cat['name']),
                    'type' => $cat['type'],
                ]
            );
        }
    }
}








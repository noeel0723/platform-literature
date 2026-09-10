<?php

namespace Database\Seeders;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\Category;
use App\Models\Literature;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = [];

        foreach ($this->sourceData() as $key => $sourceData) {
            $sources[$key] = ApiSource::query()->updateOrCreate(
                ['key' => $key],
                $sourceData,
            );
        }

        foreach ($this->catalogData() as $item) {
            $authors = $item['authors'];
            $categories = $item['categories'];
            $sourceKey = $item['source_key'];

            unset($item['authors'], $item['categories'], $item['source_key']);

            $literature = Literature::query()->updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'api_source_id' => $sources[$sourceKey]->id,
                    'external_id' => $item['external_id'],
                    ...$item,
                ],
            );

            $authorLinks = [];

            foreach ($authors as $position => $authorData) {
                $author = Author::query()->firstOrCreate(
                    ['slug' => Str::slug($authorData['name'])],
                    ['name' => $authorData['name']],
                );

                $authorLinks[$author->id] = [
                    'role' => $authorData['role'],
                    'position' => $position,
                ];
            }

            $literature->authors()->sync($authorLinks);

            $categoryIds = collect($categories)
                ->map(function (string $name): int {
                    return Category::query()->firstOrCreate(
                        ['slug' => Str::slug($name)],
                        ['name' => $name],
                    )->id;
                })
                ->all();

            $literature->categories()->sync($categoryIds);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sourceData(): array
    {
        return [
            'manual-curated' => [
                'name' => 'Literahaven Curation',
                'base_url' => null,
                'supported_types' => ['novel'],
                'is_active' => true,
            ],
            'google-books' => [
                'name' => 'Google Books',
                'base_url' => 'https://www.googleapis.com/books/v1',
                'supported_types' => ['novel'],
                'is_active' => true,
            ],
            'comic-vine' => [
                'name' => 'Comic Vine',
                'base_url' => 'https://comicvine.gamespot.com/api',
                'supported_types' => ['western-comic'],
                'is_active' => true,
            ],
            'anilist' => [
                'name' => 'AniList',
                'base_url' => 'https://graphql.anilist.co',
                'supported_types' => ['manga', 'manhwa'],
                'is_active' => true,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalogData(): array
    {
        return [
            [
                'source_key' => 'manual-curated',
                'external_id' => 'curated-bumi-manusia-1980',
                'slug' => 'bumi-manusia',
                'title' => 'Bumi Manusia',
                'type' => 'novel',
                'publication_year' => 1980,
                'tagline' => 'Sebuah kisah tentang martabat, pendidikan, dan keberanian untuk bersuara.',
                'synopsis' => 'Minke, seorang pelajar pribumi di masa kolonial, berhadapan dengan ketidakadilan yang membentuk pandangannya tentang manusia, pengetahuan, dan kebebasan.',
                'publisher' => 'Hasta Mitra',
                'language' => 'Indonesia',
                'format' => 'Novel',
                'identifier' => 'ISBN 9789799731234',
                'theme' => 'cream',
                'authors' => [
                    ['name' => 'Pramoedya Ananta Toer', 'role' => 'author'],
                ],
                'categories' => ['Fiksi sejarah', 'Drama', 'Klasik Indonesia'],
            ],
            [
                'source_key' => 'anilist',
                'external_id' => '30012',
                'slug' => 'fullmetal-alchemist',
                'title' => 'Fullmetal Alchemist',
                'type' => 'manga',
                'publication_year' => 2001,
                'tagline' => 'Dua bersaudara mencari cara untuk mengembalikan apa yang telah mereka kehilangan.',
                'synopsis' => 'Edward dan Alphonse Elric menjelajahi dunia alkimia untuk menemukan Philosopher Stone sambil menghadapi akibat dari keputusan mereka sendiri.',
                'publisher' => 'Square Enix',
                'language' => 'Jepang',
                'format' => 'Manga',
                'identifier' => 'AniList ID 30012',
                'theme' => 'coral',
                'authors' => [
                    ['name' => 'Hiromu Arakawa', 'role' => 'author'],
                ],
                'categories' => ['Petualangan', 'Fantasi', 'Drama'],
            ],
            [
                'source_key' => 'comic-vine',
                'external_id' => '4050-1807',
                'slug' => 'watchmen',
                'title' => 'Watchmen',
                'type' => 'western-comic',
                'publication_year' => 1987,
                'tagline' => 'Ketika para pahlawan diawasi, siapa yang akan mengawasi mereka?',
                'synopsis' => 'Penyelidikan kematian seorang mantan vigilante membuka rangkaian rahasia yang mengubah cara dunia melihat para pahlawannya.',
                'publisher' => 'DC Comics',
                'language' => 'Inggris',
                'format' => 'Graphic novel',
                'identifier' => 'Comic Vine 4050-1807',
                'theme' => 'sky',
                'authors' => [
                    ['name' => 'Alan Moore', 'role' => 'writer'],
                    ['name' => 'Dave Gibbons', 'role' => 'illustrator'],
                ],
                'categories' => ['Superhero', 'Misteri', 'Drama politik'],
            ],
            [
                'source_key' => 'anilist',
                'external_id' => '5114',
                'slug' => 'spice-and-wolf',
                'title' => 'Spice and Wolf',
                'type' => 'novel',
                'publication_year' => 2006,
                'tagline' => 'Perjalanan dagang menjadi kisah tentang kepercayaan, rumah, dan kebersamaan.',
                'synopsis' => 'Seorang pedagang keliling bertemu dewi serigala yang ingin kembali ke tanah kelahirannya. Mereka menempuh perjalanan melalui pasar dan kota yang penuh intrik.',
                'publisher' => 'ASCII Media Works',
                'language' => 'Jepang',
                'format' => 'Novel',
                'identifier' => 'AniList ID 5114',
                'theme' => 'cream',
                'authors' => [
                    ['name' => 'Isuna Hasekura', 'role' => 'author'],
                    ['name' => 'Ju Ayakura', 'role' => 'illustrator'],
                ],
                'categories' => ['Fantasi', 'Petualangan', 'Romansa'],
            ],
            [
                'source_key' => 'google-books',
                'external_id' => 'isbn-9780261102217',
                'slug' => 'the-hobbit',
                'title' => 'The Hobbit',
                'type' => 'novel',
                'publication_year' => 1937,
                'tagline' => 'Petualangan besar dapat dimulai dari pintu rumah yang paling sederhana.',
                'synopsis' => 'Bilbo Baggins meninggalkan kehidupannya yang tenang untuk membantu sekelompok kurcaci merebut kembali rumah mereka dari seekor naga.',
                'publisher' => 'George Allen & Unwin',
                'language' => 'Inggris',
                'format' => 'Novel',
                'identifier' => 'ISBN 9780261102217',
                'theme' => 'deep',
                'authors' => [
                    ['name' => 'J. R. R. Tolkien', 'role' => 'author'],
                ],
                'categories' => ['Fantasi', 'Petualangan', 'Klasik'],
            ],
            [
                'source_key' => 'anilist',
                'external_id' => '30035',
                'slug' => 'nausicaa-valley-of-the-wind',
                'title' => 'Nausicaa of the Valley of the Wind',
                'type' => 'manga',
                'publication_year' => 1982,
                'tagline' => 'Harapan tumbuh di dunia yang rusak ketika manusia belajar memahami alam.',
                'synopsis' => 'Nausicaa berusaha melindungi lembahnya dan memahami hutan beracun yang tumbuh setelah keruntuhan peradaban manusia.',
                'publisher' => 'Tokuma Shoten',
                'language' => 'Jepang',
                'format' => 'Manga',
                'identifier' => 'AniList ID 30035',
                'theme' => 'mixed',
                'authors' => [
                    ['name' => 'Hayao Miyazaki', 'role' => 'author'],
                ],
                'categories' => ['Fiksi ilmiah', 'Fantasi', 'Petualangan'],
            ],
        ];
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LiteratureController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $selectedType = trim((string) $request->query('type', ''));

        $literatures = collect($this->catalog())
            ->when($query !== '', function (Collection $items) use ($query): Collection {
                $normalizedQuery = Str::lower($query);

                return $items->filter(function (array $literature) use ($normalizedQuery): bool {
                    $searchableText = Str::lower(implode(' ', [
                        $literature['title'],
                        $literature['author'],
                        $literature['type_label'],
                        implode(' ', $literature['genres']),
                    ]));

                    return Str::contains($searchableText, $normalizedQuery);
                });
            })
            ->when($selectedType !== '', fn (Collection $items): Collection => $items->where('type', $selectedType))
            ->values();

        return view('catalog.index', [
            'literatures' => $literatures,
            'query' => $query,
            'selectedType' => $selectedType,
            'types' => $this->types(),
        ]);
    }

    public function show(string $literature): View
    {
        $selectedLiterature = collect($this->catalog())->firstWhere('slug', $literature);

        abort_if($selectedLiterature === null, 404);

        return view('catalog.show', [
            'literature' => $selectedLiterature,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function types(): array
    {
        return [
            'book' => 'Buku',
            'western-comic' => 'Komik Barat',
            'manga' => 'Manga',
            'light-novel' => 'Light Novel',
        ];
    }

    /**
     * Data demonstrasi ini akan diganti oleh katalog MySQL dan adapter API pada tahap backend Increment 1.
     *
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        return [
            [
                'slug' => 'bumi-manusia',
                'title' => 'Bumi Manusia',
                'year' => '1980',
                'type' => 'book',
                'type_label' => 'Buku',
                'author' => 'Pramoedya Ananta Toer',
                'authors' => ['Pramoedya Ananta Toer'],
                'source' => 'Google Books',
                'tagline' => 'Sebuah kisah tentang martabat, pendidikan, dan keberanian untuk bersuara.',
                'synopsis' => 'Minke, seorang pelajar pribumi di masa kolonial, berhadapan dengan ketidakadilan yang membentuk pandangannya tentang manusia, pengetahuan, dan kebebasan.',
                'publisher' => 'Hasta Mitra',
                'language' => 'Indonesia',
                'format' => 'Novel',
                'genres' => ['Fiksi sejarah', 'Drama', 'Klasik Indonesia'],
                'identifier' => 'ISBN 9789799731234',
                'theme' => 'primary',
                'initials' => 'BM',
            ],
            [
                'slug' => 'fullmetal-alchemist',
                'title' => 'Fullmetal Alchemist',
                'year' => '2001',
                'type' => 'manga',
                'type_label' => 'Manga',
                'author' => 'Hiromu Arakawa',
                'authors' => ['Hiromu Arakawa'],
                'source' => 'AniList',
                'tagline' => 'Dua bersaudara mencari cara untuk mengembalikan apa yang telah mereka kehilangan.',
                'synopsis' => 'Edward dan Alphonse Elric menjelajahi dunia alkimia untuk menemukan Philosopher Stone sambil menghadapi akibat dari keputusan mereka sendiri.',
                'publisher' => 'Square Enix',
                'language' => 'Jepang',
                'format' => 'Manga',
                'genres' => ['Petualangan', 'Fantasi', 'Drama'],
                'identifier' => 'AniList ID 30012',
                'theme' => 'coral',
                'initials' => 'FA',
            ],
            [
                'slug' => 'watchmen',
                'title' => 'Watchmen',
                'year' => '1987',
                'type' => 'western-comic',
                'type_label' => 'Komik Barat',
                'author' => 'Alan Moore & Dave Gibbons',
                'authors' => ['Alan Moore', 'Dave Gibbons'],
                'source' => 'Comic Vine',
                'tagline' => 'Ketika para pahlawan diawasi, siapa yang akan mengawasi mereka?',
                'synopsis' => 'Penyelidikan kematian seorang mantan vigilante membuka rangkaian rahasia yang mengubah cara dunia melihat para pahlawannya.',
                'publisher' => 'DC Comics',
                'language' => 'Inggris',
                'format' => 'Graphic novel',
                'genres' => ['Superhero', 'Misteri', 'Drama politik'],
                'identifier' => 'Comic Vine 4050-1807',
                'theme' => 'sky',
                'initials' => 'WM',
            ],
            [
                'slug' => 'spice-and-wolf',
                'title' => 'Spice and Wolf',
                'year' => '2006',
                'type' => 'light-novel',
                'type_label' => 'Light Novel',
                'author' => 'Isuna Hasekura',
                'authors' => ['Isuna Hasekura', 'Ju Ayakura'],
                'source' => 'AniList',
                'tagline' => 'Perjalanan dagang menjadi kisah tentang kepercayaan, rumah, dan kebersamaan.',
                'synopsis' => 'Seorang pedagang keliling bertemu dewi serigala yang ingin kembali ke tanah kelahirannya. Mereka menempuh perjalanan melalui pasar dan kota yang penuh intrik.',
                'publisher' => 'ASCII Media Works',
                'language' => 'Jepang',
                'format' => 'Light novel',
                'genres' => ['Fantasi', 'Petualangan', 'Romansa'],
                'identifier' => 'AniList ID 5114',
                'theme' => 'cream',
                'initials' => 'SW',
            ],
            [
                'slug' => 'the-hobbit',
                'title' => 'The Hobbit',
                'year' => '1937',
                'type' => 'book',
                'type_label' => 'Buku',
                'author' => 'J. R. R. Tolkien',
                'authors' => ['J. R. R. Tolkien'],
                'source' => 'Google Books',
                'tagline' => 'Petualangan besar dapat dimulai dari pintu rumah yang paling sederhana.',
                'synopsis' => 'Bilbo Baggins meninggalkan kehidupannya yang tenang untuk membantu sekelompok kurcaci merebut kembali rumah mereka dari seekor naga.',
                'publisher' => 'George Allen & Unwin',
                'language' => 'Inggris',
                'format' => 'Novel',
                'genres' => ['Fantasi', 'Petualangan', 'Klasik'],
                'identifier' => 'ISBN 9780261102217',
                'theme' => 'deep',
                'initials' => 'TH',
            ],
            [
                'slug' => 'nausicaa-valley-of-the-wind',
                'title' => 'Nausicaa of the Valley of the Wind',
                'year' => '1982',
                'type' => 'manga',
                'type_label' => 'Manga',
                'author' => 'Hayao Miyazaki',
                'authors' => ['Hayao Miyazaki'],
                'source' => 'AniList',
                'tagline' => 'Harapan tumbuh di dunia yang rusak ketika manusia belajar memahami alam.',
                'synopsis' => 'Nausicaa berusaha melindungi lembahnya dan memahami hutan beracun yang tumbuh setelah keruntuhan peradaban manusia.',
                'publisher' => 'Tokuma Shoten',
                'language' => 'Jepang',
                'format' => 'Manga',
                'genres' => ['Fiksi ilmiah', 'Fantasi', 'Petualangan'],
                'identifier' => 'AniList ID 30035',
                'theme' => 'mixed',
                'initials' => 'NV',
            ],
        ];
    }
}

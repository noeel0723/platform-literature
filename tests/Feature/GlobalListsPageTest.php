<?php

namespace Tests\Feature;

use App\Models\CanonicalWork;
use App\Models\CustomList;
use App\Models\CustomListItem;
use App\Models\Literature;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GlobalListsPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_can_discover_public_lists_separated_by_official_and_reader_accounts(): void
    {
        Http::preventStrayRequests();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $reader = User::factory()->create();
        $inactive = User::factory()->create(['deactivated_at' => now()]);
        $official = CustomList::factory()->for($admin)->create(['title' => 'Official selection']);
        $public = CustomList::factory()->for($reader)->create(['title' => 'Reader selection']);
        CustomList::factory()->for($admin)->create(['is_private' => true]);
        CustomList::factory()->for($reader)->create(['is_private' => true]);
        CustomList::factory()->for($inactive)->create();

        $response = $this->get(route('custom-lists.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Lists/Index')
            ->where('officialLists.total', 1)->where('readerLists.total', 1)
            ->where('officialLists.data.0.id', $official->id)
            ->where('officialLists.data.0.owner.url', route('profiles.show', $admin))
            ->where('readerLists.data.0.id', $public->id)
            ->where('readerLists.data.0.url', route('custom-lists.show', $public))
            ->where('readerLists.data.0.owner.name', $reader->name)
            ->where('readerLists.data.0.edit_url', null)
            ->where('routes.create', null)->where('routes.verification', null));

        foreach (['email', 'password', 'remember_token', 'email_verified_at', 'role'] as $field) {
            $this->assertArrayNotHasKey($field, $response->inertiaProps('readerLists.data.0.owner'));
        }
        preg_match('/data-react-header-props>(.*?)<\/script>/s', $response->getContent(), $matches);
        $header = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(route('custom-lists.index'), $header['routes']['lists']);
        $this->get(route('custom-lists.show', $official))->assertOk();
        $this->get(route('custom-lists.show', $public))->assertOk();
        Http::assertNothingSent();
    }

    public function test_each_list_shows_its_first_five_ordered_canonical_aware_covers_and_full_item_count(): void
    {
        $lists = CustomList::factory()->count(2)->create();
        $literature = Literature::factory()->count(6)->create(['type' => 'novel', 'original_title' => null]);
        $works = $literature->map(fn (Literature $title) => CanonicalWork::factory()->create(['preferred_literature_id' => $title->id]));
        foreach ($lists as $list) {
            foreach ($literature as $index => $title) {
                CustomListItem::factory()->for($list)->create([
                    'literature_id' => $index === 5 ? null : $title->id,
                    'canonical_work_id' => $works[$index]->id,
                    'position' => 6 - $index,
                ]);
            }
        }

        $this->get(route('custom-lists.index'))->assertInertia(fn (Assert $page) => $page
            ->has('readerLists.data', 2)
            ->has('readerLists.data.0.previews', 5)->has('readerLists.data.1.previews', 5)
            ->where('readerLists.data.0.items_count', 6)
            ->where('readerLists.data.0.previews.0.id', $literature[5]->id)
            ->where('readerLists.data.0.previews.4.id', $literature[1]->id)
            ->where('readerLists.data.1.previews.0.id', $literature[5]->id));
    }

    public function test_sections_paginate_independently_with_stable_order_and_preserved_section_links(): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $reader = User::factory()->create();
        $official = CustomList::factory()->count(7)->for($admin)->create();
        $readers = CustomList::factory()->count(7)->for($reader)->create();

        $response = $this->get(route('custom-lists.index', ['official_page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->where('officialLists.current_page', 2)->where('officialLists.total', 7)
            ->has('officialLists.data', 1)->where('officialLists.data.0.id', $official[0]->id)
            ->where('readerLists.current_page', 1)->where('readerLists.total', 7)
            ->has('readerLists.data', 6)->where('readerLists.data.0.id', $readers[6]->id));
        $this->assertStringContainsString('official_page=2', $response->inertiaProps('readerLists.next_page_url'));
        $this->assertStringContainsString('readers_page=2', $response->inertiaProps('readerLists.next_page_url'));
        $this->assertStringEndsWith('#readers-lists', $response->inertiaProps('readerLists.next_page_url'));

        $this->get(route('custom-lists.index', ['readers_page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->where('officialLists.current_page', 1)->has('officialLists.data', 6)
            ->where('readerLists.current_page', 2)->has('readerLists.data', 1)
            ->where('readerLists.data.0.id', $readers[0]->id));
    }

    public function test_authenticated_directory_hides_blocks_in_both_directions_and_even_the_owners_private_lists(): void
    {
        $viewer = User::factory()->create();
        $blocked = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $blocker = User::factory()->create();
        $ownPublic = CustomList::factory()->for($viewer)->create();
        CustomList::factory()->for($viewer)->create(['is_private' => true]);
        CustomList::factory()->for($blocked)->create();
        CustomList::factory()->for($blocker)->create();
        $viewer->blockedUsers()->attach($blocked);
        $blocker->blockedUsers()->attach($viewer);

        $this->actingAs($viewer)->get(route('custom-lists.index'))->assertInertia(fn (Assert $page) => $page
            ->where('officialLists.total', 0)->where('readerLists.total', 1)
            ->where('readerLists.data.0.id', $ownPublic->id));
    }

    public function test_create_action_respects_verification_settings_and_existing_authorization(): void
    {
        $reader = User::factory()->unverified()->create();
        $this->actingAs($reader)->get(route('custom-lists.index'))->assertInertia(fn (Assert $page) => $page
            ->where('routes.create', null)->where('routes.verification', route('verification.notice')));

        config(['auth.verification.enabled' => false]);
        $this->get(route('custom-lists.index'))->assertInertia(fn (Assert $page) => $page
            ->where('routes.create', route('custom-lists.create'))->where('routes.verification', null));
        $this->assertNull($reader->fresh()->email_verified_at);

        config(['auth.verification.enabled' => true]);
        $reader->markEmailAsVerified();
        $this->get(route('custom-lists.index'))->assertInertia(fn (Assert $page) => $page
            ->where('routes.create', route('custom-lists.create'))->where('routes.verification', null));
    }

    public function test_preview_queries_do_not_grow_for_each_additional_list(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $reader = User::factory()->create();
        $literature = Literature::factory()->create(['type' => 'novel']);
        $work = CanonicalWork::factory()->create(['preferred_literature_id' => $literature->id]);
        foreach ([$admin, $reader] as $owner) {
            $list = CustomList::factory()->for($owner)->create();
            CustomListItem::factory()->for($list)->create(['canonical_work_id' => $work->id, 'literature_id' => null, 'position' => 1]);
        }

        $this->get(route('custom-lists.index'))->assertOk();
        DB::enableQueryLog();
        try {
            DB::flushQueryLog();
            $this->get(route('custom-lists.index'))->assertOk();
            $baseline = count(DB::getQueryLog());
            DB::disableQueryLog();
            foreach ([$admin, $reader] as $owner) {
                foreach (CustomList::factory()->count(5)->for($owner)->create() as $list) {
                    CustomListItem::factory()->for($list)->create(['canonical_work_id' => $work->id, 'literature_id' => null, 'position' => 1]);
                }
            }
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->get(route('custom-lists.index'))->assertOk();
            $this->assertSame($baseline, count(DB::getQueryLog()));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}

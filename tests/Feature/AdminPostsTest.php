<?php

namespace Tests\Feature;

use App\Models\FirebaseUser;
use App\Repositories\FirebaseAdminPostRepository;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The admin's published updates: listed when there are a few, a slideshow when there are more
 * than three, and each one can be edited or deleted by any admin (logged).
 */
class AdminPostsTest extends TestCase
{
    private function rows(int $count): array
    {
        return array_map(fn (int $i) => [
            'id' => "p{$i}", 'title' => "Update {$i}", 'body' => "Body {$i}", 'type' => 'announcement', 'audience' => $i % 2 ? 'public' : 'users',
            'author' => 'Jo Admin', 'author_id' => 'admin-1', 'date' => now()->subDays($i)->toIso8601String(),
            'image' => $i === 1 ? 'http://localhost/storage/announcements/rice.png' : null, 'edited_at' => null, 'editor' => null,
        ], range(1, $count));
    }

    private function render(array $posts): string
    {
        return view('adminPage.partials.published-updates', [
            'posts' => $posts,
            'postTypes' => ['announcement' => 'Announcement', 'funding_update' => 'Funding update', 'compiled_report' => 'Compiled report'],
            'when' => fn ($value) => $value ? \Carbon\Carbon::parse($value)->diffForHumans() : '—',
        ])->render();
    }

    private function admin(string $id = 'admin-2'): static
    {
        return $this->actingAs(new FirebaseUser(['id' => $id, 'fname' => 'Sam', 'lname' => 'Admin', 'role' => 'admin']));
    }

    public function test_a_few_updates_are_listed_and_more_than_three_become_a_slideshow(): void
    {
        $few = $this->render($this->rows(3));
        $this->assertStringNotContainsString('data-carousel', $few);
        $this->assertSame(3, substr_count($few, 'class="admin-post"'));

        $many = $this->render($this->rows(5));
        $this->assertStringContainsString('data-carousel data-carousel-manual', $many, 'moved with the arrows and dots, not by itself');
        $this->assertSame(5, substr_count($many, 'data-carousel-slide'));
        $this->assertStringContainsString('1 of 5', $many);
        $this->assertStringContainsString('aria-label="Show update 5 of 5: Update 5"', $many);
    }

    public function test_every_update_offers_edit_and_delete(): void
    {
        $html = $this->render($this->rows(2));

        $this->assertSame(2, substr_count($html, 'data-edit-post="'));
        $this->assertStringContainsString('action="' . route('admin.posts.destroy', 'p2') . '"', $html);
        // The edit form comes filled in, and says it is a PUT.
        $this->assertStringContainsString('<template data-edit-post-form="p1">', $html);
        $this->assertStringContainsString('action="' . route('admin.posts.update', 'p1') . '"', $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);
        $this->assertStringContainsString('value="Update 1"', $html);
        $this->assertStringContainsString('Remove the photo', $html, 'the post with a photo can drop it');
    }

    public function test_an_edited_update_says_so(): void
    {
        $rows = $this->rows(1);
        $rows[0]['edited_at'] = now()->subHour()->toIso8601String();
        $rows[0]['editor'] = 'Sam Admin';

        $this->assertStringContainsString('edited 1 hour ago by Sam Admin', $this->render($rows));
    }

    public function test_any_admin_can_edit_an_update_and_replace_its_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('announcements/old.png', 'old');
        $posts = $this->mock(FirebaseAdminPostRepository::class);
        $posts->shouldReceive('findById')->with('p1')->andReturn(['id' => 'p1', 'title' => 'Rice drive', 'author_id' => 'admin-1', 'image' => 'announcements/old.png']);
        $posts->shouldReceive('update')->once()->withArgs(function (string $id, array $changes) {
            return $id === 'p1' && $changes['title'] === 'Rice drive (updated)' && $changes['audience'] === 'users'
                && $changes['edited_by'] === 'admin-2' && str_starts_with($changes['image'], 'announcements/')
                && Storage::disk('public')->exists($changes['image']);
        })->andReturn(['id' => 'p1']);
        $this->mock(AuditLogger::class, fn ($mock) => $mock->shouldReceive('record')->once()->with('updates', 'Edited the update "Rice drive (updated)"', 'p1'));

        $this->admin()->put(route('admin.posts.update', 'p1'), [
            'title' => 'Rice drive (updated)', 'body' => 'We delivered 60 sacks.', 'type' => 'funding_update', 'audience' => 'users',
            'image' => UploadedFile::fake()->createWithContent('new.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')),
        ])->assertRedirect(route('admin') . '#updates')->assertSessionHas('status');

        Storage::disk('public')->assertMissing('announcements/old.png');
    }

    public function test_the_photo_can_be_removed_and_mistakes_are_not_saved(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('announcements/old.png', 'old');
        $posts = $this->mock(FirebaseAdminPostRepository::class);
        $posts->shouldReceive('findById')->with('p1')->andReturn(['id' => 'p1', 'title' => 'Rice drive', 'image' => 'announcements/old.png']);
        $posts->shouldReceive('update')->once()->withArgs(fn ($id, $changes) => array_key_exists('image', $changes) && $changes['image'] === null)->andReturn(['id' => 'p1']);
        $this->mock(AuditLogger::class, fn ($mock) => $mock->shouldReceive('record'));
        $fields = ['title' => 'Rice drive', 'body' => 'Done.', 'type' => 'announcement', 'audience' => 'public'];

        $this->admin()->put(route('admin.posts.update', 'p1'), $fields + ['remove_image' => '1'])->assertSessionHas('status');
        Storage::disk('public')->assertMissing('announcements/old.png');

        // An empty title: nothing is saved, and the "New update" form is not filled with it.
        $this->admin()->put(route('admin.posts.update', 'p1'), ['title' => ''] + $fields)
            ->assertRedirect(route('admin') . '#updates')
            ->assertSessionHas('alert_error', 'The update was not saved. The title field is required.')
            ->assertSessionMissing('_old_input');
    }

    public function test_any_admin_can_delete_an_update(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('announcements/old.png', 'old');
        $posts = $this->mock(FirebaseAdminPostRepository::class);
        $posts->shouldReceive('findById')->with('p1')->andReturn(['id' => 'p1', 'title' => 'Rice drive', 'author_id' => 'admin-1', 'image' => 'announcements/old.png']);
        $posts->shouldReceive('delete')->once()->with('p1');
        $this->mock(AuditLogger::class, fn ($mock) => $mock->shouldReceive('record')->once()->with('updates', 'Deleted the update "Rice drive"', 'p1'));

        $this->admin('admin-2')->delete(route('admin.posts.destroy', 'p1'))
            ->assertRedirect(route('admin') . '#updates')->assertSessionHas('status', 'Update deleted.');
        Storage::disk('public')->assertMissing('announcements/old.png');
    }

    public function test_only_admins_manage_updates(): void
    {
        $this->mock(FirebaseAdminPostRepository::class, fn ($mock) => $mock->shouldNotReceive('update', 'delete'));

        $this->actingAs(new FirebaseUser(['id' => 'u1', 'role' => 'normaluser']))
            ->put(route('admin.posts.update', 'p1'), ['title' => 'x'])->assertRedirect();
        $this->actingAs(new FirebaseUser(['id' => 'u1', 'role' => 'normaluser']))
            ->delete(route('admin.posts.destroy', 'p1'))->assertRedirect();
    }
}

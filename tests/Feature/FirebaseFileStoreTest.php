<?php

namespace Tests\Feature;

use App\Support\Storage\FirebaseDatabaseAdapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use League\Flysystem\Filesystem;
use Tests\TestCase;

/**
 * The Firebase-backed disk (FILES_DRIVER=firebase) behaves like a normal Laravel disk:
 * uploads are stored, read back byte for byte, listed, served, and deleted.
 */
class FirebaseFileStoreTest extends TestCase
{
    private FakeFirebase $firebase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->firebase = new FakeFirebase();
        config(['cache.default' => 'array']);

        $firebase = $this->firebase;
        config(['filesystems.disks.firebase-test' => ['driver' => 'firebase-test']]);
        \Storage::extend('firebase-test', function () use ($firebase) {
            $adapter = new FirebaseDatabaseAdapter($firebase, 'file_store/local');

            return new FilesystemAdapter(new Filesystem($adapter), $adapter, []);
        });
        \Storage::extend('firebase-test-public', function () use ($firebase) {
            $adapter = new FirebaseDatabaseAdapter($firebase, 'file_store/public', 'public');

            return new FilesystemAdapter(new Filesystem($adapter), $adapter, []);
        });
    }

    private function disk(string $root = 'file_store/local'): FilesystemAdapter
    {
        $adapter = new FirebaseDatabaseAdapter($this->firebase, $root);

        return new FilesystemAdapter(new Filesystem($adapter), $adapter, []);
    }

    public function test_files_round_trip_across_several_chunks(): void
    {
        $disk = $this->disk();
        $bytes = random_bytes(2_500_000); // about 2.4 MB, so 3 chunks

        $disk->put('fund_documents/formal_letter/letter.pdf', $bytes);

        $this->assertTrue($disk->exists('fund_documents/formal_letter/letter.pdf'));
        $this->assertSame($bytes, $disk->get('fund_documents/formal_letter/letter.pdf'));
        $this->assertSame(2_500_000, $disk->size('fund_documents/formal_letter/letter.pdf'));
        $this->assertCount(3, $this->firebase->data['file_store']['local']['chunks'][sha1('fund_documents/formal_letter/letter.pdf')]);
    }

    public function test_uploaded_file_store_and_listing(): void
    {
        $disk = $this->disk();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $file = UploadedFile::fake()->createWithContent('photo.png', $png);

        $path = $file->store('profile_pictures', ['disk' => 'firebase-test']);
        $this->assertNotFalse($path);
        $this->assertStringStartsWith('profile_pictures/', $path);
        $this->assertSame('image/png', \Storage::disk('firebase-test')->mimeType($path));

        $disk->put('liquidations/7/receipts/a.jpg', 'a');
        $disk->put('liquidations/7/photos/b.jpg', 'b');
        $this->assertEqualsCanonicalizing(['liquidations/7/receipts/a.jpg', 'liquidations/7/photos/b.jpg'], $disk->allFiles('liquidations'));
        $this->assertSame([], $disk->files('liquidations'));
        $this->assertEqualsCanonicalizing(['liquidations/7'], $disk->directories('liquidations'));
    }

    public function test_delete_removes_bytes_and_metadata(): void
    {
        $disk = $this->disk();
        $disk->put('disbursements/9/proof.jpg', 'proof');
        $disk->delete('disbursements/9/proof.jpg');

        $this->assertFalse($disk->exists('disbursements/9/proof.jpg'));
        $this->assertEmpty($this->firebase->data['file_store']['local']['meta'] ?? []);
        $this->assertEmpty($this->firebase->data['file_store']['local']['chunks'] ?? []);
    }

    public function test_public_files_are_served_from_the_storage_url(): void
    {
        $this->app['config']->set('filesystems.disks.public', ['driver' => 'firebase-test-public']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        \Storage::disk('public')->put('profile_pictures/me.png', $png);
        // Web middleware signs in through the real Firebase; this test is only about the file route.
        $this->withoutMiddleware();

        $this->get('/storage/profile_pictures/me.png')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->get('/storage/profile_pictures/missing.png')->assertNotFound();
        $this->get('/storage/../.env')->assertNotFound();
    }
}

/** In-memory stand-in for the Firebase Realtime Database (getReference()->set/getValue/remove). */
class FakeFirebase
{
    public array $data = [];

    public function getDatabase(): self
    {
        return $this;
    }

    public function getReference(string $path): FakeReference
    {
        return new FakeReference($this, array_values(array_filter(explode('/', $path), 'strlen')));
    }
}

class FakeReference
{
    public function __construct(private FakeFirebase $db, private array $keys)
    {
    }

    public function set(mixed $value): void
    {
        $node = &$this->db->data;
        foreach ($this->keys as $key) {
            if (! isset($node[$key]) || ! is_array($node[$key])) {
                $node[$key] = [];
            }
            $node = &$node[$key];
        }
        $node = $value;
    }

    public function getValue(): mixed
    {
        $node = $this->db->data;
        foreach ($this->keys as $key) {
            if (! is_array($node) || ! array_key_exists($key, $node)) {
                return null;
            }
            $node = $node[$key];
        }

        return $node === [] ? null : $node;
    }

    public function remove(): void
    {
        $node = &$this->db->data;
        $last = array_pop($this->keys);
        foreach ($this->keys as $key) {
            if (! isset($node[$key])) {
                return;
            }
            $node = &$node[$key];
        }
        unset($node[$last]);
    }
}

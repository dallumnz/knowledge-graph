<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    Storage::fake('local');
});

it('renders successfully', function () {
    Livewire::actingAs($this->user)
        ->test('document-storage')
        ->assertStatus(200);
});

it('displays empty state when no documents exist', function () {
    Livewire::actingAs($this->user)
        ->test('document-storage')
        ->assertSee('No documents found')
        ->assertSee('Upload your first document to get started.');
});

it('displays documents in the list', function () {
    $documents = Document::factory()->count(3)->create();

    Livewire::actingAs($this->user)
        ->test('document-storage')
        ->assertSee($documents->first()->title)
        ->assertSee($documents->skip(1)->first()->title)
        ->assertSee($documents->skip(2)->first()->title);
});

it('can open and close upload form', function () {
    $component = Livewire::actingAs($this->user)
        ->test('document-storage')
        ->call('openUploadForm');

    $component->assertSet('showUploadForm', true);

    $component->call('closeUploadForm');

    $component->assertSet('showUploadForm', false);
    $component->assertSet('title', '');
    $component->assertSet('content', null);
});

it('can create a document with text content', function () {
    Livewire::actingAs($this->user)
        ->test('document-storage')
        ->call('openUploadForm')
        ->set('title', 'Test Document')
        ->set('content', 'This is test content for the document.')
        ->call('storeDocument');

    expect(Document::count())->toBe(1);

    $document = Document::first();
    expect($document->title)->toBe('Test Document');
    expect($document->content)->toBe('This is test content for the document.');
    expect($document->source_type)->toBe('text');
});

it('validates title is required', function () {
    Livewire::actingAs($this->user)
        ->test('document-storage')
        ->call('openUploadForm')
        ->set('title', '')
        ->set('content', 'Some content')
        ->call('storeDocument')
        ->assertHasErrors(['title' => 'required']);
});

it('can search documents by title', function () {
    Document::factory()->create(['title' => 'Production Report']);
    Document::factory()->create(['title' => 'Development Guide']);

    Livewire::actingAs($this->user)
        ->test('document-storage')
        ->set('search', 'Production')
        ->assertSee('Production Report')
        ->assertDontSee('Development Guide');
});

it('can view a document', function () {
    $document = Document::factory()->create([
        'title' => 'Viewable Document',
        'content' => 'This is the document content.',
    ]);

    $component = Livewire::actingAs($this->user)
        ->test('document-storage')
        ->call('viewDocument', $document->id);

    $component->assertSet('viewingDocumentId', $document->id);
    $component->assertSet('viewingDocument.title', 'Viewable Document');
});

it('can close document viewer', function () {
    $document = Document::factory()->create();

    $component = Livewire::actingAs($this->user)
        ->test('document-storage')
        ->call('viewDocument', $document->id)
        ->call('closeDocumentViewer');

    $component->assertSet('viewingDocumentId', null);
    $component->assertSet('viewingDocument', null);
});

it('can delete a document', function () {
    $document = Document::factory()->create();

    Livewire::actingAs($this->user)
        ->test('document-storage')
        ->call('deleteDocument', $document->id);

    expect(Document::count())->toBe(0);
});

it('deletes associated file when document is deleted', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->create('test.pdf', 100);
    $path = $file->store('documents/'.$this->user->id, 'local');

    $document = Document::factory()->create([
        'source_type' => 'file',
        'source_path' => $path,
    ]);

    Storage::disk('local')->assertExists($path);

    Livewire::actingAs($this->user)
        ->test('document-storage')
        ->call('deleteDocument', $document->id);

    Storage::disk('local')->assertMissing($path);
});

it('displays document source type with correct icon', function () {
    Document::factory()->file()->create(['title' => 'File Document']);
    Document::factory()->url()->create(['title' => 'URL Document']);
    Document::factory()->create(['title' => 'Text Document', 'source_type' => 'text']);

    Livewire::actingAs($this->user)
        ->test('document-storage')
        ->assertSee('File Document')
        ->assertSee('URL Document')
        ->assertSee('Text Document');
});

it('displays active status badge', function () {
    Document::factory()->create(['title' => 'Active Doc', 'is_active' => true]);
    Document::factory()->create(['title' => 'Inactive Doc', 'is_active' => false]);

    Livewire::actingAs($this->user)
        ->test('document-storage')
        ->assertSee('Active')
        ->assertSee('Inactive');
});

it('paginates documents', function () {
    Document::factory()->count(15)->create();

    $component = Livewire::actingAs($this->user)
        ->test('document-storage');

    // Default perPage is 10, so we should see pagination
    $component->assertSee('Previous');
    $component->assertSee('Next');
});

it('dispatches event after document creation', function () {
    $component = Livewire::actingAs($this->user)
        ->test('document-storage')
        ->call('openUploadForm')
        ->set('title', 'Event Test Document')
        ->set('content', 'Test content')
        ->call('storeDocument');

    $component->assertDispatched('document-created');
});

it('dispatches event after document deletion', function () {
    $document = Document::factory()->create();

    $component = Livewire::actingAs($this->user)
        ->test('document-storage')
        ->call('deleteDocument', $document->id);

    $component->assertDispatched('document-deleted');
});

it('shows success message after document upload', function () {
    Livewire::actingAs($this->user)
        ->test('document-storage')
        ->call('openUploadForm')
        ->set('title', 'Success Test')
        ->set('content', 'Test content')
        ->call('storeDocument')
        ->assertSee('uploaded successfully');
});

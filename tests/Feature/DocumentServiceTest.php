<?php

use App\Models\Document;
use App\Models\Node;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('DocumentService', function () {
    beforeEach(function () {
        $this->service = app(DocumentService::class);
    });

    describe('CRUD Operations', function () {
        it('can create a document', function () {
            $document = $this->service->create([
                'title' => 'Test Document',
                'source_type' => 'text',
                'source_path' => '/path/to/doc.txt',
                'content' => 'Full document content',
                'metadata' => ['author' => 'Test Author'],
            ]);

            expect($document)->toBeInstanceOf(Document::class);
            expect($document->title)->toBe('Test Document');
            expect($document->source_type)->toBe('text');
            expect($document->source_path)->toBe('/path/to/doc.txt');
            expect($document->content)->toBe('Full document content');
            expect($document->metadata)->toBe(['author' => 'Test Author']);
            expect($document->version)->toBe(1);
            expect($document->is_active)->toBeTrue();
        });

        it('uses default values when optional fields are not provided', function () {
            $document = $this->service->create([
                'title' => 'Minimal Document',
                'source_type' => 'api',
            ]);

            expect($document->source_path)->toBeNull();
            expect($document->content)->toBeNull();
            expect($document->metadata)->toBe([]);
            expect($document->version)->toBe(1);
            expect($document->is_active)->toBeTrue();
        });

        it('can find a document by ID', function () {
            $document = Document::factory()->create();

            $found = $this->service->find($document->id);

            expect($found)->not->toBeNull();
            expect($found->id)->toBe($document->id);
        });

        it('returns null when finding non-existent document', function () {
            $found = $this->service->find(99999);

            expect($found)->toBeNull();
        });

        it('can find document with nodes loaded', function () {
            $document = Document::factory()->create();
            Node::factory()->count(3)->create(['document_id' => $document->id]);

            $found = $this->service->findWithNodes($document->id);

            expect($found)->not->toBeNull();
            expect($found->nodes)->toHaveCount(3);
        });

        it('can update a document', function () {
            $document = Document::factory()->create(['title' => 'Old Title']);

            $result = $this->service->update($document, ['title' => 'New Title']);

            expect($result)->toBeTrue();
            expect($document->fresh()->title)->toBe('New Title');
        });

        it('can delete a document', function () {
            $document = Document::factory()->create();

            $result = $this->service->delete($document);

            expect($result)->toBeTrue();
            expect(Document::find($document->id))->toBeNull();
        });

        it('deleting a document sets node document_id to null', function () {
            $document = Document::factory()->create();
            $node = Node::factory()->create(['document_id' => $document->id]);

            $this->service->delete($document);

            $node->refresh();
            expect($node->document_id)->toBeNull();
        });
    });

    describe('Listing and Filtering', function () {
        it('can list documents with pagination', function () {
            Document::factory()->count(10)->create();

            $paginator = $this->service->list();

            expect($paginator)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
            expect($paginator->total())->toBe(10);
            expect($paginator->perPage())->toBe(15);
        });

        it('can filter documents by source_type', function () {
            Document::factory()->file()->count(3)->create();
            Document::factory()->url()->count(2)->create();

            $paginator = $this->service->list(['source_type' => 'file']);

            expect($paginator->total())->toBe(3);
        });

        it('can filter documents by is_active', function () {
            Document::factory()->count(3)->create();
            Document::factory()->inactive()->count(2)->create();

            $paginator = $this->service->list(['is_active' => false]);

            expect($paginator->total())->toBe(2);
        });

        it('can filter documents by title', function () {
            Document::factory()->create(['title' => 'Laravel Guide']);
            Document::factory()->create(['title' => 'PHP Tutorial']);

            $paginator = $this->service->list(['title' => 'Laravel']);

            expect($paginator->total())->toBe(1);
            expect($paginator->items()[0]->title)->toBe('Laravel Guide');
        });

        it('can filter documents by IDs', function () {
            $doc1 = Document::factory()->create();
            $doc2 = Document::factory()->create();
            Document::factory()->create();

            $paginator = $this->service->list(['ids' => [$doc1->id, $doc2->id]]);

            expect($paginator->total())->toBe(2);
        });

        it('can customize per_page', function () {
            Document::factory()->count(20)->create();

            $paginator = $this->service->list(['per_page' => 5]);

            expect($paginator->perPage())->toBe(5);
        });

        it('can get all documents without pagination', function () {
            Document::factory()->count(5)->create();

            $documents = $this->service->getAll();

            expect($documents)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
            expect($documents)->toHaveCount(5);
        });

        it('can filter all documents by source_type', function () {
            Document::factory()->file()->count(3)->create();
            Document::factory()->url()->count(2)->create();

            $documents = $this->service->getAll(['source_type' => 'url']);

            expect($documents)->toHaveCount(2);
        });

        it('can filter all documents by IDs', function () {
            $doc1 = Document::factory()->create();
            $doc2 = Document::factory()->create();
            Document::factory()->create();

            $documents = $this->service->getAll(['ids' => [$doc1->id, $doc2->id]]);

            expect($documents)->toHaveCount(2);
        });
    });

    describe('Chunks Management', function () {
        it('can get chunks for a document', function () {
            $document = Document::factory()->create();
            Node::factory()->count(3)->create([
                'document_id' => $document->id,
                'type' => 'text_chunk',
            ]);

            $chunks = $this->service->getChunks($document->id);

            expect($chunks)->toHaveCount(3);
        });

        it('returns empty collection for non-existent document', function () {
            $chunks = $this->service->getChunks(99999);

            expect($chunks)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
            expect($chunks)->toBeEmpty();
        });
    });

    describe('Version and Status Management', function () {
        it('can increment document version', function () {
            $document = Document::factory()->create(['version' => 1]);

            $result = $this->service->incrementVersion($document);

            expect($result)->toBeTrue();
            expect($document->fresh()->version)->toBe(2);
        });

        it('can activate a document', function () {
            $document = Document::factory()->inactive()->create();

            $result = $this->service->setActive($document, true);

            expect($result)->toBeTrue();
            expect($document->fresh()->is_active)->toBeTrue();
        });

        it('can deactivate a document', function () {
            $document = Document::factory()->create();

            $result = $this->service->setActive($document, false);

            expect($result)->toBeTrue();
            expect($document->fresh()->is_active)->toBeFalse();
        });
    });
});

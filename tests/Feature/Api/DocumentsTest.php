<?php

use App\Models\Document;
use App\Models\Node;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Documents API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    describe('Document CRUD', function () {
        it('requires authentication for document endpoints', function () {
            $response = $this->getJson(route('api.documents.index'));
            $response->assertUnauthorized();

            $response = $this->postJson(route('api.documents.store'), [
                'title' => 'Test Document',
                'source_type' => 'text',
            ]);
            $response->assertUnauthorized();

            $response = $this->getJson(route('api.documents.show', ['id' => 1]));
            $response->assertUnauthorized();
        });

        it('can create a document', function () {
            $response = $this->actingAs($this->user)
                ->postJson(route('api.documents.store'), [
                    'title' => 'Q4 Policy Document',
                    'source_type' => 'file',
                    'source_path' => '/docs/policies/q4-2024.pdf',
                    'content' => 'Full document content here',
                    'metadata' => [
                        'author' => 'Legal Team',
                        'document_type' => 'policy',
                    ],
                ]);

            $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Document created successfully',
                ])
                ->assertJsonStructure([
                    'data' => [
                        'document' => [
                            'id',
                            'title',
                            'source_type',
                            'source_path',
                            'metadata',
                            'version',
                            'is_active',
                        ],
                    ],
                ]);

            expect($response->json('data.document.title'))->toBe('Q4 Policy Document');
            expect($response->json('data.document.source_type'))->toBe('file');
            expect($response->json('data.document.version'))->toBe(1);
            expect($response->json('data.document.is_active'))->toBeTrue();
        });

        it('validates required fields when creating a document', function () {
            $response = $this->actingAs($this->user)
                ->postJson(route('api.documents.store'), []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['title', 'source_type']);
        });

        it('validates source_type enum values', function () {
            $response = $this->actingAs($this->user)
                ->postJson(route('api.documents.store'), [
                    'title' => 'Test',
                    'source_type' => 'invalid_type',
                ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['source_type']);
        });

        it('can retrieve a single document', function () {
            $document = Document::factory()->create([
                'title' => 'Test Document',
                'source_type' => 'text',
            ]);

            $response = $this->actingAs($this->user)
                ->getJson(route('api.documents.show', ['id' => $document->id]));

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'document' => [
                            'id' => $document->id,
                            'title' => 'Test Document',
                            'source_type' => 'text',
                        ],
                    ],
                ]);
        });

        it('returns 404 for non-existent document', function () {
            $response = $this->actingAs($this->user)
                ->getJson(route('api.documents.show', ['id' => 99999]));

            $response->assertNotFound()
                ->assertJson([
                    'success' => false,
                    'message' => 'Document not found',
                ]);
        });

        it('can list documents with pagination', function () {
            Document::factory()->count(5)->create();

            $response = $this->actingAs($this->user)
                ->getJson(route('api.documents.index'));

            $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'documents',
                        'pagination' => [
                            'current_page',
                            'last_page',
                            'per_page',
                            'total',
                        ],
                    ],
                ]);

            expect($response->json('data.pagination.total'))->toBe(5);
        });

        it('can filter documents by source_type', function () {
            Document::factory()->file()->count(3)->create();
            Document::factory()->url()->count(2)->create();

            $response = $this->actingAs($this->user)
                ->getJson(route('api.documents.index', ['source_type' => 'file']));

            $response->assertOk();
            expect($response->json('data.pagination.total'))->toBe(3);
        });

        it('can filter documents by is_active', function () {
            Document::factory()->count(3)->create();
            Document::factory()->inactive()->count(2)->create();

            $response = $this->actingAs($this->user)
                ->getJson(route('api.documents.index', ['is_active' => false]));

            $response->assertOk();
            expect($response->json('data.pagination.total'))->toBe(2);
        });

        it('can filter documents by title', function () {
            Document::factory()->create(['title' => 'Laravel Guide']);
            Document::factory()->create(['title' => 'PHP Tutorial']);
            Document::factory()->create(['title' => 'Vue.js Handbook']);

            $response = $this->actingAs($this->user)
                ->getJson(route('api.documents.index', ['title' => 'Laravel']));

            $response->assertOk();
            expect($response->json('data.pagination.total'))->toBe(1);
            expect($response->json('data.documents.0.title'))->toBe('Laravel Guide');
        });

        it('can update a document', function () {
            $document = Document::factory()->create([
                'title' => 'Old Title',
                'source_type' => 'text',
            ]);

            $response = $this->actingAs($this->user)
                ->putJson(route('api.documents.update', ['id' => $document->id]), [
                    'title' => 'New Title',
                    'metadata' => ['updated' => true],
                ]);

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'message' => 'Document updated successfully',
                ]);

            $document->refresh();
            expect($document->title)->toBe('New Title');
        });

        it('returns 404 when updating non-existent document', function () {
            $response = $this->actingAs($this->user)
                ->putJson(route('api.documents.update', ['id' => 99999]), [
                    'title' => 'New Title',
                ]);

            $response->assertNotFound()
                ->assertJson([
                    'success' => false,
                    'message' => 'Document not found',
                ]);
        });

        it('can delete a document', function () {
            $document = Document::factory()->create();

            $response = $this->actingAs($this->user)
                ->deleteJson(route('api.documents.destroy', ['id' => $document->id]));

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'message' => 'Document deleted successfully',
                ]);

            expect(Document::find($document->id))->toBeNull();
        });

        it('returns 404 when deleting non-existent document', function () {
            $response = $this->actingAs($this->user)
                ->deleteJson(route('api.documents.destroy', ['id' => 99999]));

            $response->assertNotFound()
                ->assertJson([
                    'success' => false,
                    'message' => 'Document not found',
                ]);
        });
    });

    describe('Document Chunks', function () {
        it('can get chunks for a document', function () {
            $document = Document::factory()->create();

            // Create nodes linked to the document
            Node::factory()->count(3)->create([
                'type' => 'text_chunk',
                'document_id' => $document->id,
            ]);

            $response = $this->actingAs($this->user)
                ->getJson(route('api.documents.chunks', ['id' => $document->id]));

            $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'document' => ['id', 'title'],
                        'chunks',
                        'count',
                    ],
                ]);

            expect($response->json('data.count'))->toBe(3);
            expect($response->json('data.document.id'))->toBe($document->id);
        });

        it('returns 404 when getting chunks for non-existent document', function () {
            $response = $this->actingAs($this->user)
                ->getJson(route('api.documents.chunks', ['id' => 99999]));

            $response->assertNotFound()
                ->assertJson([
                    'success' => false,
                    'message' => 'Document not found',
                ]);
        });

        it('returns empty chunks for document with no nodes', function () {
            $document = Document::factory()->create();

            $response = $this->actingAs($this->user)
                ->getJson(route('api.documents.chunks', ['id' => $document->id]));

            $response->assertOk();
            expect($response->json('data.count'))->toBe(0);
            expect($response->json('data.chunks'))->toBe([]);
        });
    });

    describe('Document Relationships', function () {
        it('document has many nodes', function () {
            $document = Document::factory()->create();

            Node::factory()->count(3)->create([
                'document_id' => $document->id,
            ]);

            expect($document->nodes)->toHaveCount(3);
        });

        it('node belongs to a document', function () {
            $document = Document::factory()->create();
            $node = Node::factory()->create([
                'document_id' => $document->id,
            ]);

            expect($node->document)->not->toBeNull();
            expect($node->document->id)->toBe($document->id);
        });

        it('nodes can exist without a document', function () {
            $node = Node::factory()->create([
                'document_id' => null,
            ]);

            expect($node->document)->toBeNull();
        });
    });
});

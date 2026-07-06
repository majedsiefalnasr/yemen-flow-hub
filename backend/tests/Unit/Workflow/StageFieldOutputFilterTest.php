<?php

namespace Tests\Unit\Workflow;

use App\Models\EngineRequest;
use App\Models\EngineRequestDocument;
use App\Models\StageFieldRule;
use App\Models\WorkflowStage;
use App\Services\Workflow\StageFieldOutputFilter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class StageFieldOutputFilterTest extends TestCase
{
    private StageFieldOutputFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new StageFieldOutputFilter;
    }

    public function test_hidden_field_is_not_visible_at_stage(): void
    {
        $stage = new WorkflowStage(['id' => 10, 'workflow_version_id' => 5]);
        $stage->setRelation('stageFieldRules', collect([
            new StageFieldRule(['field_id' => 2, 'is_visible' => false]),
        ]));

        $this->assertFalse($this->filter->isFieldVisibleAtStage($stage, 2));
    }

    public function test_defaults_to_visible_when_no_stage_rule(): void
    {
        $stage = new WorkflowStage(['id' => 10, 'workflow_version_id' => 5]);
        $stage->setRelation('stageFieldRules', new Collection);

        $this->assertTrue($this->filter->isFieldVisibleAtStage($stage, 99));
    }

    public function test_unlinked_documents_are_always_accessible(): void
    {
        $request = new EngineRequest([
            'workflow_version_id' => 1,
            'current_stage_id' => 1,
        ]);
        $request->setRelation('currentStage', new WorkflowStage(['id' => 1]));

        $document = new EngineRequestDocument([
            'field_id' => null,
            'request_id' => 1,
        ]);

        $this->assertTrue($this->filter->canViewerAccessFieldLinkedDocument($request, $document));
    }

    public function test_field_linked_document_blocked_when_field_hidden(): void
    {
        $stage = new WorkflowStage(['id' => 1]);
        $stage->setRelation('stageFieldRules', collect([
            new StageFieldRule(['field_id' => 5, 'is_visible' => false]),
        ]));

        $request = new EngineRequest(['workflow_version_id' => 1, 'current_stage_id' => 1]);
        $request->setRelation('currentStage', $stage);

        $document = new EngineRequestDocument([
            'field_id' => 5,
            'request_id' => 1,
        ]);

        $this->assertFalse($this->filter->canViewerAccessFieldLinkedDocument($request, $document));
    }

    public function test_unresolvable_stage_with_non_empty_data_returns_empty_and_logs_warning(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Request has non-empty data but its current stage could not be resolved; hiding all fields.',
                [
                    'engine_request_id' => 42,
                    'current_stage_id' => 999,
                ],
            );

        $request = new EngineRequest([
            'id' => 42,
            'workflow_version_id' => 1,
            'current_stage_id' => 999,
        ]);
        $request->id = 42;
        $request->setRelation('currentStage', null);
        $request->data = ['field_a' => 'value'];

        $this->assertSame([], $this->filter->filterRequestData($request));
    }
}

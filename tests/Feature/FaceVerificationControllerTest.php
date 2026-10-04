<?php

namespace Tests\Feature;

use App\Models\FaceVerificationReceipt;
use App\Models\User;
use App\Services\FaceDescriptorExtractor;
use App\Services\FaceDescriptorResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class FaceVerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'photo' => UploadedFile::fake()->image('frame.jpg'),
            'action' => 'masuk',
            'attempt_id' => 'attempt-1',
            'sequence' => 1,
        ], $overrides);
    }

    private function fakeExtractor(FaceDescriptorResult $result): void
    {
        $extractor = Mockery::mock(FaceDescriptorExtractor::class);
        $extractor->shouldReceive('extract')->once()->andReturn($result);
        $extractor->shouldReceive('matches')->andReturnUsing(
            fn (array $a, array $b) => $a === $b,
        );
        $this->app->instance(FaceDescriptorExtractor::class, $extractor);
    }

    public function test_a_matching_preview_issues_a_receipt(): void
    {
        $descriptor = array_fill(0, 128, 0.2);
        $user = User::factory()->withEnrolledFace()->create(['face_descriptor' => $descriptor]);
        $this->fakeExtractor(FaceDescriptorResult::success($descriptor));

        $response = $this->actingAs($user)->postJson(route('absen.face-verification'), $this->payload());

        $response->assertOk()->assertJson([
            'status' => 'matched',
            'attempt_id' => 'attempt-1',
            'sequence' => 1,
        ]);
        $response->assertJsonStructure(['status', 'message', 'attempt_id', 'sequence', 'checked_at', 'expires_at', 'receipt']);

        $this->assertSame(1, FaceVerificationReceipt::count());
        $receipt = FaceVerificationReceipt::first();
        $this->assertSame($user->id, $receipt->user_id);
        $this->assertSame('masuk', $receipt->action);
        $this->assertNull($receipt->consumed_at);
        $this->assertTrue($receipt->expires_at->isFuture());
        $this->assertEquals($user->face_enrolled_at, $receipt->face_enrolled_at_snapshot);
        $this->assertSame($receipt->token, $response->json('receipt'));
    }

    public function test_the_response_never_leaks_the_descriptor_or_distance(): void
    {
        $descriptor = array_fill(0, 128, 0.2);
        $user = User::factory()->withEnrolledFace()->create(['face_descriptor' => $descriptor]);
        $this->fakeExtractor(FaceDescriptorResult::success($descriptor));

        $response = $this->actingAs($user)->postJson(route('absen.face-verification'), $this->payload());

        $response->assertJsonMissingPath('descriptor');
        $response->assertJsonMissingPath('distance');
        $response->assertJsonMissingPath('error');
    }

    public function test_a_mismatched_face_does_not_issue_a_receipt(): void
    {
        $user = User::factory()->withEnrolledFace()->create(['face_descriptor' => array_fill(0, 128, 0.2)]);
        $this->fakeExtractor(FaceDescriptorResult::success(array_fill(0, 128, 0.9)));

        $response = $this->actingAs($user)->postJson(route('absen.face-verification'), $this->payload());

        $response->assertOk()->assertJson(['status' => 'mismatch']);
        $response->assertJsonMissingPath('receipt');
        $this->assertSame(0, FaceVerificationReceipt::count());
    }

    public function test_no_face_detected_is_reported_without_a_receipt(): void
    {
        $user = User::factory()->withEnrolledFace()->create();
        $this->fakeExtractor(FaceDescriptorResult::failure('no_face'));

        $response = $this->actingAs($user)->postJson(route('absen.face-verification'), $this->payload());

        $response->assertOk()->assertJson(['status' => 'no_face']);
        $this->assertSame(0, FaceVerificationReceipt::count());
    }

    public function test_multiple_faces_detected_is_reported(): void
    {
        $user = User::factory()->withEnrolledFace()->create();
        $this->fakeExtractor(FaceDescriptorResult::failure('multiple_faces'));

        $response = $this->actingAs($user)->postJson(route('absen.face-verification'), $this->payload());

        $response->assertOk()->assertJson(['status' => 'multiple_faces']);
    }

    public function test_a_down_worker_reports_service_unavailable_with_503(): void
    {
        $user = User::factory()->withEnrolledFace()->create();
        $this->fakeExtractor(FaceDescriptorResult::failure('process_failed'));

        $response = $this->actingAs($user)->postJson(route('absen.face-verification'), $this->payload());

        $response->assertStatus(503)->assertJson(['status' => 'service_unavailable']);
        $this->assertSame(0, FaceVerificationReceipt::count());
    }

    public function test_the_preview_photo_is_not_retained_either_way(): void
    {
        $descriptor = array_fill(0, 128, 0.2);
        $user = User::factory()->withEnrolledFace()->create(['face_descriptor' => $descriptor]);
        $this->fakeExtractor(FaceDescriptorResult::success($descriptor));

        $this->actingAs($user)->postJson(route('absen.face-verification'), $this->payload());

        $this->assertEmpty(Storage::disk('local')->allFiles('face-verification-tmp'));
    }

    public function test_an_unenrolled_user_cannot_reach_the_endpoint(): void
    {
        $user = User::factory()->create(['face_descriptor' => null]);

        $response = $this->actingAs($user)->postJson(route('absen.face-verification'), $this->payload());

        $response->assertStatus(403);
    }

    public function test_invalid_action_is_rejected(): void
    {
        $user = User::factory()->withEnrolledFace()->create();

        $response = $this->actingAs($user)->postJson(
            route('absen.face-verification'),
            $this->payload(['action' => 'bukan-aksi-valid']),
        );

        $response->assertStatus(422);
    }
}

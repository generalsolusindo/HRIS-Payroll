<?php

namespace Tests\Unit;

use App\Services\FaceDescriptorExtractor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FaceDescriptorExtractorTest extends TestCase
{
    public function test_parses_a_successful_extraction(): void
    {
        $descriptor = array_fill(0, 128, 0.1);
        Http::fake([
            '*/extract' => Http::response(['ok' => true, 'descriptor' => $descriptor]),
        ]);

        $result = (new FaceDescriptorExtractor)->extract('/tmp/photo.jpg');

        $this->assertTrue($result->ok);
        $this->assertCount(128, $result->descriptor);
        $this->assertSame(0.1, $result->descriptor[0]);
    }

    public function test_sends_the_configured_worker_secret_header(): void
    {
        config(['face.worker_secret' => 'super-secret']);
        Http::fake([
            '*/extract' => Http::response(['ok' => true, 'descriptor' => array_fill(0, 128, 0.1)]),
        ]);

        (new FaceDescriptorExtractor)->extract('/tmp/photo.jpg');

        Http::assertSent(fn ($request) => $request->hasHeader('X-Worker-Secret', 'super-secret'));
    }

    public function test_reports_no_face_detected(): void
    {
        Http::fake([
            '*/extract' => Http::response(['ok' => false, 'error' => 'no_face']),
        ]);

        $result = (new FaceDescriptorExtractor)->extract('/tmp/photo.jpg');

        $this->assertFalse($result->ok);
        $this->assertSame('no_face', $result->error);
    }

    public function test_reports_multiple_faces_detected(): void
    {
        Http::fake([
            '*/extract' => Http::response(['ok' => false, 'error' => 'multiple_faces']),
        ]);

        $result = (new FaceDescriptorExtractor)->extract('/tmp/photo.jpg');

        $this->assertFalse($result->ok);
        $this->assertSame('multiple_faces', $result->error);
    }

    public function test_treats_an_unreachable_worker_as_a_process_failure(): void
    {
        Http::fake(['*/extract' => fn () => throw new ConnectionException('refused')]);

        $result = (new FaceDescriptorExtractor)->extract('/tmp/photo.jpg');

        $this->assertFalse($result->ok);
        $this->assertSame('process_failed', $result->error);
    }

    public function test_treats_a_non_2xx_response_as_a_process_failure(): void
    {
        Http::fake(['*/extract' => Http::response(['ok' => false, 'error' => 'unauthorized'], 401)]);

        $result = (new FaceDescriptorExtractor)->extract('/tmp/photo.jpg');

        $this->assertFalse($result->ok);
        $this->assertSame('process_failed', $result->error);
    }

    public function test_treats_the_workers_own_timeout_and_queue_errors_as_process_failures(): void
    {
        foreach (['timeout', 'queue_full'] as $workerError) {
            Http::fake(['*/extract' => Http::response(['ok' => false, 'error' => $workerError])]);

            $result = (new FaceDescriptorExtractor)->extract('/tmp/photo.jpg');

            $this->assertFalse($result->ok);
            $this->assertSame('process_failed', $result->error);
        }
    }

    public function test_treats_unparseable_output_as_invalid(): void
    {
        Http::fake(['*/extract' => Http::response('not json at all')]);

        $result = (new FaceDescriptorExtractor)->extract('/tmp/photo.jpg');

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_output', $result->error);
    }

    public function test_treats_a_malformed_descriptor_length_as_invalid(): void
    {
        Http::fake(['*/extract' => Http::response(['ok' => true, 'descriptor' => [1, 2, 3]])]);

        $result = (new FaceDescriptorExtractor)->extract('/tmp/photo.jpg');

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_output', $result->error);
    }

    public function test_distance_is_zero_for_identical_vectors(): void
    {
        $vector = array_fill(0, 128, 0.42);

        $distance = (new FaceDescriptorExtractor)->distance($vector, $vector);

        $this->assertEqualsWithDelta(0.0, $distance, 0.0001);
    }

    public function test_distance_matches_known_euclidean_calculation(): void
    {
        $a = [0.0, 0.0];
        $b = [3.0, 4.0];

        $distance = (new FaceDescriptorExtractor)->distance($a, $b);

        $this->assertEqualsWithDelta(5.0, $distance, 0.0001);
    }

    public function test_matches_respects_the_configured_threshold(): void
    {
        config(['face.match_threshold' => 0.6]);
        $extractor = new FaceDescriptorExtractor;

        $a = [0.0, 0.0];
        $closeEnough = [0.3, 0.3];
        $tooFar = [1.0, 1.0];

        $this->assertTrue($extractor->matches($a, $closeEnough));
        $this->assertFalse($extractor->matches($a, $tooFar));
    }
}

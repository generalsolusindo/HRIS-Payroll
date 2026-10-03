<?php

namespace Tests\Unit;

use App\Services\FaceDescriptorExtractor;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class FaceDescriptorExtractorTest extends TestCase
{
    public function test_parses_a_successful_extraction(): void
    {
        $descriptor = array_fill(0, 128, 0.1);
        Process::fake([
            '*' => Process::result(json_encode(['ok' => true, 'descriptor' => $descriptor])),
        ]);

        $result = (new FaceDescriptorExtractor)->extract('/tmp/photo.jpg');

        $this->assertTrue($result->ok);
        $this->assertCount(128, $result->descriptor);
        $this->assertSame(0.1, $result->descriptor[0]);
    }

    public function test_reports_no_face_detected(): void
    {
        Process::fake([
            '*' => Process::result(json_encode(['ok' => false, 'error' => 'no_face'])),
        ]);

        $result = (new FaceDescriptorExtractor)->extract('/tmp/photo.jpg');

        $this->assertFalse($result->ok);
        $this->assertSame('no_face', $result->error);
    }

    public function test_reports_multiple_faces_detected(): void
    {
        Process::fake([
            '*' => Process::result(json_encode(['ok' => false, 'error' => 'multiple_faces'])),
        ]);

        $result = (new FaceDescriptorExtractor)->extract('/tmp/photo.jpg');

        $this->assertFalse($result->ok);
        $this->assertSame('multiple_faces', $result->error);
    }

    public function test_treats_a_nonzero_exit_code_as_a_process_failure(): void
    {
        Process::fake([
            '*' => Process::result(output: '', errorOutput: 'boom', exitCode: 1),
        ]);

        $result = (new FaceDescriptorExtractor)->extract('/tmp/photo.jpg');

        $this->assertFalse($result->ok);
        $this->assertSame('process_failed', $result->error);
    }

    public function test_treats_unparseable_output_as_invalid(): void
    {
        Process::fake([
            '*' => Process::result('not json at all'),
        ]);

        $result = (new FaceDescriptorExtractor)->extract('/tmp/photo.jpg');

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_output', $result->error);
    }

    public function test_treats_a_malformed_descriptor_length_as_invalid(): void
    {
        Process::fake([
            '*' => Process::result(json_encode(['ok' => true, 'descriptor' => [1, 2, 3]])),
        ]);

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

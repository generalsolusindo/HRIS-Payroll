<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserFaceDescriptorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_has_not_enrolled_a_face_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->hasFaceEnrolled());
    }

    public function test_enrolling_a_face_round_trips_the_descriptor_exactly(): void
    {
        $user = User::factory()->create();
        $descriptor = array_fill(0, 128, 0.123456);

        $user->face_descriptor = $descriptor;
        $user->face_enrolled_at = now();
        $user->save();

        $fresh = User::find($user->id);

        $this->assertTrue($fresh->hasFaceEnrolled());
        $this->assertSame($descriptor, $fresh->face_descriptor);
        $this->assertNotNull($fresh->face_enrolled_at);
    }

    public function test_the_descriptor_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $user->face_descriptor = array_fill(0, 128, 0.5);
        $user->save();

        $raw = DB::table('users')->where('id', $user->id)->value('face_descriptor');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('0.5', $raw);
        $decoded = json_decode(base64_decode($raw), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('iv', $decoded);
        $this->assertArrayHasKey('value', $decoded);
    }

    public function test_the_descriptor_is_never_serialized_to_an_array(): void
    {
        $user = User::factory()->create();
        $user->face_descriptor = array_fill(0, 128, 0.5);
        $user->face_enrolled_at = now();
        $user->save();

        $array = $user->fresh()->toArray();

        $this->assertArrayNotHasKey('face_descriptor', $array);
        $this->assertArrayHasKey('face_enrolled_at', $array);
    }

    public function test_the_descriptor_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();

        $user->fill(['face_descriptor' => array_fill(0, 128, 0.9)]);

        $this->assertNull($user->face_descriptor);
    }
}

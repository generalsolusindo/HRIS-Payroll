<?php

namespace Tests\Feature\Settings;

use App\Mail\FaceEnrollmentUpdatedMail;
use App\Models\User;
use App\Services\FaceDescriptorExtractor;
use App\Services\FaceDescriptorResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class FaceProfileTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsConfirmed(User $user): static
    {
        return $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()]);
    }

    private function fakeExtractor(FaceDescriptorResult $result): void
    {
        $extractor = Mockery::mock(FaceDescriptorExtractor::class);
        $extractor->shouldReceive('extract')->once()->andReturn($result);
        $this->app->instance(FaceDescriptorExtractor::class, $extractor);
    }

    public function test_the_page_requires_a_recently_confirmed_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('face.edit'));

        $response->assertRedirect(route('password.confirm'));
    }

    public function test_the_page_shows_enrollment_status(): void
    {
        $user = User::factory()->create(['face_descriptor' => null]);

        $this->actingAsConfirmed($user)
            ->get(route('face.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/face')
                ->where('hasFaceEnrolled', false)
                ->where('faceEnrolledAt', null),
            );
    }

    public function test_a_clear_single_face_photo_enrolls_successfully(): void
    {
        Mail::fake();
        Storage::fake('local');
        $this->fakeExtractor(FaceDescriptorResult::success(array_fill(0, 128, 0.25)));

        $user = User::factory()->create();

        $response = $this->actingAsConfirmed($user)->post(route('face.store'), [
            'photo' => UploadedFile::fake()->image('face.jpg'),
        ]);

        $response->assertSessionDoesntHaveErrors();

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasFaceEnrolled());
        $this->assertSame(array_fill(0, 128, 0.25), $fresh->face_descriptor);
        $this->assertNotNull($fresh->face_enrolled_at);

        Mail::assertSent(FaceEnrollmentUpdatedMail::class, fn ($mail) => $mail->hasTo($user->email));

        // The enrollment photo itself is not retained.
        $this->assertEmpty(Storage::disk('local')->allFiles('face-enrollment-tmp'));
    }

    public function test_a_photo_with_no_detectable_face_is_rejected(): void
    {
        Storage::fake('local');
        $this->fakeExtractor(FaceDescriptorResult::failure('no_face'));

        $user = User::factory()->create();

        $response = $this->actingAsConfirmed($user)->post(route('face.store'), [
            'photo' => UploadedFile::fake()->image('face.jpg'),
        ]);

        $response->assertSessionHasErrors('photo');
        $this->assertFalse($user->fresh()->hasFaceEnrolled());
        $this->assertEmpty(Storage::disk('local')->allFiles('face-enrollment-tmp'));
    }

    public function test_a_photo_with_multiple_faces_is_rejected(): void
    {
        Storage::fake('local');
        $this->fakeExtractor(FaceDescriptorResult::failure('multiple_faces'));

        $user = User::factory()->create();

        $response = $this->actingAsConfirmed($user)->post(route('face.store'), [
            'photo' => UploadedFile::fake()->image('face.jpg'),
        ]);

        $response->assertSessionHasErrors('photo');
        $this->assertFalse($user->fresh()->hasFaceEnrolled());
    }

    public function test_a_non_image_upload_is_rejected_before_extraction_runs(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsConfirmed($user)->post(route('face.store'), [
            'photo' => UploadedFile::fake()->create('not-a-photo.pdf', 100),
        ]);

        $response->assertSessionHasErrors('photo');
    }

    public function test_notification_email_failure_does_not_break_enrollment(): void
    {
        Storage::fake('local');
        $this->fakeExtractor(FaceDescriptorResult::success(array_fill(0, 128, 0.1)));

        $pendingMail = Mockery::mock(PendingMail::class);
        $pendingMail->shouldReceive('send')->andThrow(new \RuntimeException('SMTP tidak tersedia.'));
        Mail::shouldReceive('to')->andReturn($pendingMail);

        $user = User::factory()->create();

        $response = $this->actingAsConfirmed($user)->post(route('face.store'), [
            'photo' => UploadedFile::fake()->image('face.jpg'),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertTrue($user->fresh()->hasFaceEnrolled());
    }

    public function test_re_enrolling_overwrites_the_previous_descriptor(): void
    {
        Mail::fake();
        Storage::fake('local');
        $this->fakeExtractor(FaceDescriptorResult::success(array_fill(0, 128, 0.9)));

        $user = User::factory()->create([
            'face_descriptor' => array_fill(0, 128, 0.1),
            'face_enrolled_at' => now()->subDays(10),
        ]);

        $this->actingAsConfirmed($user)->post(route('face.store'), [
            'photo' => UploadedFile::fake()->image('face.jpg'),
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(array_fill(0, 128, 0.9), $user->fresh()->face_descriptor);
    }
}

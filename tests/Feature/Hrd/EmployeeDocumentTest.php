<?php

namespace Tests\Feature\Hrd;

use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('employee_documents');
    }

    public function test_hrd_can_view_an_employees_detail_page(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);

        $this->actingAs($hrd)
            ->get(route('hrd.users.show', $employee))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('hrd/user-detail')
                ->where('employee.id', $employee->id)
                ->where('employee.name', $employee->name)
                ->has('documents', 0)
            );
    }

    public function test_karyawan_cannot_view_the_employee_detail_page(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->get(route('hrd.users.show', $karyawan))
            ->assertForbidden();
    }

    public function test_hrd_can_upload_an_optional_document(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        $file = UploadedFile::fake()->create('ktp.jpg', 200, 'image/jpeg');

        $this->actingAs($hrd)
            ->post(route('hrd.users.documents.store', $employee), [
                'type' => 'ktp',
                'label' => 'KTP terbaru',
                'file' => $file,
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $document = EmployeeDocument::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('ktp', $document->type);
        $this->assertSame('KTP terbaru', $document->label);
        $this->assertSame('ktp.jpg', $document->file_name);
        $this->assertSame($hrd->id, $document->uploaded_by);
        Storage::disk('employee_documents')->assertExists($document->file_path);
    }

    public function test_label_is_optional_when_uploading_a_document(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);

        $this->actingAs($hrd)
            ->post(route('hrd.users.documents.store', $employee), [
                'type' => 'ijazah',
                'file' => UploadedFile::fake()->create('ijazah.pdf', 300, 'application/pdf'),
            ])
            ->assertSessionDoesntHaveErrors();

        $document = EmployeeDocument::where('user_id', $employee->id)->firstOrFail();
        $this->assertNull($document->label);
    }

    public function test_an_unsupported_file_type_is_rejected(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);

        $this->actingAs($hrd)
            ->post(route('hrd.users.documents.store', $employee), [
                'type' => 'ktp',
                'file' => UploadedFile::fake()->create('ktp.exe', 100, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, EmployeeDocument::count());
    }

    public function test_karyawan_cannot_upload_a_document(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->post(route('hrd.users.documents.store', $karyawan), [
                'type' => 'ktp',
                'file' => UploadedFile::fake()->create('ktp.jpg', 100, 'image/jpeg'),
            ])
            ->assertForbidden();
    }

    public function test_hrd_can_download_a_document(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        $file = UploadedFile::fake()->create('ktp.jpg', 100, 'image/jpeg');
        $path = $file->store((string) $employee->id, 'employee_documents');
        $document = EmployeeDocument::create([
            'user_id' => $employee->id, 'type' => 'ktp',
            'file_name' => 'ktp.jpg', 'file_path' => $path,
            'mime_type' => 'image/jpeg', 'file_size' => $file->getSize(),
            'uploaded_by' => $hrd->id,
        ]);

        $this->actingAs($hrd)
            ->get(route('hrd.users.documents.show', $document))
            ->assertOk();
    }

    public function test_hrd_can_delete_a_document(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        $file = UploadedFile::fake()->create('ktp.jpg', 100, 'image/jpeg');
        $path = $file->store((string) $employee->id, 'employee_documents');
        $document = EmployeeDocument::create([
            'user_id' => $employee->id, 'type' => 'ktp',
            'file_name' => 'ktp.jpg', 'file_path' => $path,
            'mime_type' => 'image/jpeg', 'file_size' => $file->getSize(),
            'uploaded_by' => $hrd->id,
        ]);

        $this->actingAs($hrd)
            ->delete(route('hrd.users.documents.destroy', $document))
            ->assertRedirect();

        $this->assertModelMissing($document);
        Storage::disk('employee_documents')->assertMissing($path);
    }

    public function test_deleting_a_user_cascades_to_their_documents(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);
        $employee = User::factory()->create(['company_id' => $hrd->company_id]);
        $file = UploadedFile::fake()->create('ktp.jpg', 100, 'image/jpeg');
        $path = $file->store((string) $employee->id, 'employee_documents');
        $document = EmployeeDocument::create([
            'user_id' => $employee->id, 'type' => 'ktp',
            'file_name' => 'ktp.jpg', 'file_path' => $path,
            'mime_type' => 'image/jpeg', 'file_size' => $file->getSize(),
        ]);

        $employee->delete();

        $this->assertModelMissing($document);
    }
}

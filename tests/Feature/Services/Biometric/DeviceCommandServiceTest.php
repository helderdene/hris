<?php

use App\Models\BiometricDevice;
use App\Models\DeviceSyncLog;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\WorkLocation;
use App\Services\Biometric\DeviceCommandService;
use App\Services\Mqtt\MqttPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
    app()->instance('tenant', $this->tenant);

    $this->workLocation = WorkLocation::factory()->create();
    $this->device = BiometricDevice::factory()->create([
        'work_location_id' => $this->workLocation->id,
    ]);

    Storage::fake('tenant-documents');
});

/**
 * Generate a valid JPEG image for testing.
 */
function createTestJpegContent(): string
{
    $img = imagecreatetruecolor(100, 100);
    $color = imagecolorallocate($img, 200, 200, 200);
    imagefill($img, 0, 0, $color);

    ob_start();
    imagejpeg($img, null, 80);
    $content = ob_get_clean();
    imagedestroy($img);

    return $content;
}

describe('DeviceCommandService', function () {
    describe('editPerson', function () {
        it('sends an EditPerson command and creates a sync log', function () {
            $employee = Employee::factory()->create([
                'work_location_id' => $this->workLocation->id,
            ]);

            $mockPublisher = mock(MqttPublisher::class);
            $mockPublisher->shouldReceive('publishToDevice')
                ->once()
                ->andReturn('test-message-id');

            $service = new DeviceCommandService($mockPublisher);
            $log = $service->editPerson($this->device, $employee);

            expect($log)
                ->toBeInstanceOf(DeviceSyncLog::class)
                ->employee_id->toBe($employee->id)
                ->biometric_device_id->toBe($this->device->id)
                ->operation->toBe(DeviceSyncLog::OPERATION_EDIT_PERSON)
                ->message_id->toBe('test-message-id')
                ->status->toBe(DeviceSyncLog::STATUS_SENT);
        });
    });

    describe('buildEditPersonPayload', function () {
        it('includes all required device fields', function () {
            $employee = Employee::factory()->create([
                'employee_number' => 'EMP-001',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'gender' => 'male',
                'date_of_birth' => '1995-06-12',
                'phone' => '09171234567',
                'nationality' => 'Filipino',
                'hire_date' => '2024-01-15',
                'address' => [
                    'street' => '123 Main St',
                    'barangay' => 'Brgy. Test',
                    'city' => 'Manila',
                    'province' => 'NCR',
                    'postal_code' => '1000',
                ],
                'work_location_id' => $this->workLocation->id,
            ]);

            $mockPublisher = mock(MqttPublisher::class);
            $mockPublisher->shouldReceive('publishToDevice')
                ->once()
                ->withArgs(function (BiometricDevice $device, array $payload) {
                    $info = $payload['info'];

                    expect($payload['operator'])->toBe('EditPerson');
                    expect($info['customId'])->toBe('EMP-001');
                    expect($info['gender'])->toBe(0);
                    expect($info['birthday'])->toBe('1995-06-12');
                    expect($info['address'])->toBe('123 Main St, Brgy. Test, Manila, NCR, 1000');
                    expect($info['telnum1'])->toBe('09171234567');
                    expect($info['native'])->toBe('Filipino');
                    expect($info['cardValidBegin'])->toBe('2024-01-15');
                    expect($info['cardValidEnd'])->toBe('2099-12-31');
                    expect($info['nation'])->toBe(1);
                    expect($info['personType'])->toBe(0);
                    expect($info['cardType'])->toBe(0);
                    expect($info['tempCardType'])->toBe(0);
                    expect($info['EffectNumber'])->toBe(3);
                    expect($info)->toHaveKey('strategyInfo');
                    expect($info['strategyInfo']['strategyNum'])->toBe(1);

                    return true;
                })
                ->andReturn('test-message-id');

            $service = new DeviceCommandService($mockPublisher);
            $service->editPerson($this->device, $employee);
        });

        it('maps female gender correctly', function () {
            $employee = Employee::factory()->create([
                'gender' => 'female',
                'work_location_id' => $this->workLocation->id,
            ]);

            $mockPublisher = mock(MqttPublisher::class);
            $mockPublisher->shouldReceive('publishToDevice')
                ->once()
                ->withArgs(function (BiometricDevice $device, array $payload) {
                    expect($payload['info']['gender'])->toBe(1);

                    return true;
                })
                ->andReturn('test-message-id');

            $service = new DeviceCommandService($mockPublisher);
            $service->editPerson($this->device, $employee);
        });

        it('handles null gender as male (default 0)', function () {
            $employee = Employee::factory()->create([
                'gender' => null,
                'work_location_id' => $this->workLocation->id,
            ]);

            $mockPublisher = mock(MqttPublisher::class);
            $mockPublisher->shouldReceive('publishToDevice')
                ->once()
                ->withArgs(function (BiometricDevice $device, array $payload) {
                    expect($payload['info']['gender'])->toBe(0);

                    return true;
                })
                ->andReturn('test-message-id');

            $service = new DeviceCommandService($mockPublisher);
            $service->editPerson($this->device, $employee);
        });

        it('handles employee with no address', function () {
            $employee = Employee::factory()->create([
                'address' => null,
                'work_location_id' => $this->workLocation->id,
            ]);

            $mockPublisher = mock(MqttPublisher::class);
            $mockPublisher->shouldReceive('publishToDevice')
                ->once()
                ->withArgs(function (BiometricDevice $device, array $payload) {
                    expect($payload['info']['address'])->toBe('');

                    return true;
                })
                ->andReturn('test-message-id');

            $service = new DeviceCommandService($mockPublisher);
            $service->editPerson($this->device, $employee);
        });

        it('includes pic as data URI when profile photo exists', function () {
            $employee = Employee::factory()->create([
                'work_location_id' => $this->workLocation->id,
            ]);

            $category = DocumentCategory::firstOrCreate(
                ['name' => 'Profile Photo'],
                ['description' => 'Employee profile photos']
            );

            // Create a fake image on the tenant disk
            $imagePath = "employees/{$employee->id}/profile.jpg";
            Storage::disk('tenant-documents')->put($imagePath, createTestJpegContent());

            Document::create([
                'employee_id' => $employee->id,
                'document_category_id' => $category->id,
                'name' => 'Profile Photo',
                'original_filename' => 'profile.jpg',
                'stored_filename' => 'profile.jpg',
                'file_path' => $imagePath,
                'file_size' => 100,
                'mime_type' => 'image/jpeg',
            ]);

            $mockPublisher = mock(MqttPublisher::class);
            $mockPublisher->shouldReceive('publishToDevice')
                ->once()
                ->withArgs(function (BiometricDevice $device, array $payload) {
                    expect($payload['info'])->toHaveKey('pic');
                    expect($payload['info']['pic'])->toStartWith('data:');
                    expect($payload['info']['pic'])->toContain(';base64,');

                    return true;
                })
                ->andReturn('test-message-id');

            $service = new DeviceCommandService($mockPublisher);
            $service->editPerson($this->device, $employee);
        });

        it('omits pic when no profile photo exists', function () {
            $employee = Employee::factory()->create([
                'work_location_id' => $this->workLocation->id,
            ]);

            $mockPublisher = mock(MqttPublisher::class);
            $mockPublisher->shouldReceive('publishToDevice')
                ->once()
                ->withArgs(function (BiometricDevice $device, array $payload) {
                    expect($payload['info'])->not->toHaveKey('pic');

                    return true;
                })
                ->andReturn('test-message-id');

            $service = new DeviceCommandService($mockPublisher);
            $service->editPerson($this->device, $employee);
        });
    });

    describe('sanitizePayloadForLogging', function () {
        it('replaces pic with placeholder in sync log', function () {
            $employee = Employee::factory()->create([
                'work_location_id' => $this->workLocation->id,
            ]);

            $category = DocumentCategory::firstOrCreate(
                ['name' => 'Profile Photo'],
                ['description' => 'Employee profile photos']
            );

            $imagePath = "employees/{$employee->id}/profile.jpg";
            Storage::disk('tenant-documents')->put($imagePath, createTestJpegContent());

            Document::create([
                'employee_id' => $employee->id,
                'document_category_id' => $category->id,
                'name' => 'Profile Photo',
                'original_filename' => 'profile.jpg',
                'stored_filename' => 'profile.jpg',
                'file_path' => $imagePath,
                'file_size' => 100,
                'mime_type' => 'image/jpeg',
            ]);

            $mockPublisher = mock(MqttPublisher::class);
            $mockPublisher->shouldReceive('publishToDevice')
                ->once()
                ->andReturn('test-message-id');

            $service = new DeviceCommandService($mockPublisher);
            $log = $service->editPerson($this->device, $employee);

            expect($log->request_payload['info']['pic'])->toStartWith('[base64 data,');
        });
    });
});

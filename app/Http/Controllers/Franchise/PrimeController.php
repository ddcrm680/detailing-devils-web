<?php

namespace App\Http\Controllers\Franchise;

use App\Actions\Franchise\SaveFranchiseLeadAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Franchise\StorePrimeRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class PrimeController extends Controller
{
    public function create(): View
    {
        return view('forms.prime', [
            'states' => config('form_options.common.states'),
            'primeOptions' => config('form_options.prime'),
        ]);
    }

    public function store(StorePrimeRequest $request, SaveFranchiseLeadAction $save): JsonResponse
    {
        try {
            $created = $save->savePrime($request->validated(), $request->normalizedPhoneNumbers());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Prime franchise application could not be saved.', [
                'exception' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'We could not save your application right now. Please try again later.',
            ], 503);
        }

        if ($created) {
            $this->scheduleEmails($request->validated(), $request->normalizedPhoneNumbers());
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'message' => $created
                ? 'Thank you! Your franchise application has been submitted successfully. Our team will contact you shortly.'
                : 'We have already received your enquiry. A member of our franchise team will get in touch with you shortly.',
        ]);
    }

    /** @param array{mobile_e164: string, whatsapp_e164: string} $phones */
    private function scheduleEmails(array $application, array $phones): void
    {
        $data = [
            'application' => $application,
            'name' => trim(implode(' ', [
                $application['first_name'], $application['middle_name'] ?? '', $application['last_name'],
            ])),
            'mobile' => ltrim($phones['mobile_e164'], '+'),
            'whatsapp' => ltrim($phones['whatsapp_e164'], '+'),
            'currentAddress' => trim(implode(', ', array_filter([
                trim($application['city']), $application['state'], trim((string) ($application['pin_code'] ?? '')),
            ]))),
            'propertyDetails' => implode(' | ', array_filter([
                trim((string) ($application['property_location'] ?? '')),
                ! empty($application['road_facing']) ? 'Road Facing: '.$application['road_facing'] : null,
            ])),
            'contactMethods' => implode(', ', $application['contact_methods']),
            'submittedOn' => now()->toDateString(),
        ];

        app()->terminating(static function () use ($application, $data): void {
            if (config('mail.default') === 'log') {
                Log::warning('Prime franchise emails skipped: a delivery mailer is not configured.');

                return;
            }

            $teamAddresses = array_values(array_unique(array_filter(
                array_map('trim', explode(',', (string) config('mail.prime_team_address'))),
                static fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false
            )));

            if ($teamAddresses !== []) {
                try {
                    Mail::send('emails.franchise.prime-team', $data, static function (Message $message) use ($teamAddresses): void {
                        $message->to($teamAddresses)->subject('New Detailing Devils Prime Franchise Enquiry');
                    });
                } catch (Throwable $exception) {
                    Log::warning('Prime franchise team email failed.', ['exception' => $exception::class]);
                }
            } else {
                Log::warning('Prime franchise team email skipped: recipient is not configured.');
            }

            try {
                Mail::send('emails.franchise.prime-applicant', $data, static function (Message $message) use ($application): void {
                    $message->to($application['email'])->subject('Thank You for Your Detailing Devils Prime Franchise Application');
                });
            } catch (Throwable $exception) {
                Log::warning('Prime franchise applicant email failed.', ['exception' => $exception::class]);
            }
        });
    }
}

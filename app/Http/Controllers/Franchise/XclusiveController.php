<?php

namespace App\Http\Controllers\Franchise;

use App\Actions\Franchise\SaveFranchiseLeadAction;
use App\Actions\Franchise\SendFranchiseCallAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Franchise\StoreXclusiveRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class XclusiveController extends Controller
{
    public function create(): View
    {
        return view('forms.xclusive', [
            'submissionEnabled' => (bool) config('services.xclusive.submission_enabled'),
            'questionnaireOptions' => config('form_options.xclusive'),
            'formModel' => [
                'name' => 'X-Clusive',
                'title' => 'X-Clusive Franchise Application | Detailing Devils',
                'description' => 'Explore the Detailing Devils X-Clusive franchise model.',
                'label' => 'X-Clusive Franchise',
                'submitRoute' => 'xclusive.submit',
            ],
        ]);
    }

    public function store(StoreXclusiveRequest $request, SaveFranchiseLeadAction $save, SendFranchiseCallAction $call): JsonResponse
    {
        if (! config('services.xclusive.submission_enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'X-Clusive applications are not accepting submissions yet.',
            ], 503);
        }

        try {
            $application = $request->validated();
            $mobile = $request->normalizedMobile();
            $created = $save->saveXclusive($application, $mobile, $request->session());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('X-Clusive franchise application could not be saved.', ['exception' => $exception::class]);

            return response()->json([
                'success' => false,
                'message' => 'We could not save your application right now. Please try again later.',
            ], 503);
        }

        if ($created) {
            $this->scheduleEmails($application, $mobile);
            app()->terminating(static fn () => $call('xclusive', $mobile));
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'message' => $created
                ? 'Thank you! Your X-Clusive franchise application has been submitted successfully. Our team will contact you shortly.'
                : 'We have already received your application. Our franchise team will contact you shortly.',
        ]);
    }

    private function scheduleEmails(array $application, string $mobile): void
    {
        unset($application['locationimage']);

        app()->terminating(static function () use ($application, $mobile): void {
            if (config('mail.default') === 'log') {
                Log::warning('X-Clusive franchise emails skipped: a delivery mailer is not configured.');

                return;
            }

            $data = [
                'application' => $application,
                'name' => trim($application['name'].' '.$application['lname']),
                'mobile' => $mobile,
                'submittedOn' => now()->toDateString(),
            ];
            $teamAddresses = array_values(array_unique(array_filter(
                array_map('trim', explode(',', (string) config('mail.xclusive_team_address'))),
                static fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false
            )));

            if ($teamAddresses === []) {
                Log::warning('X-Clusive franchise team email skipped: recipient is not configured.');
            } else {
                try {
                    $savedLead = DB::table('tbl_common_leads')
                        ->where('record_type', 'xclusive')
                        ->where('phone', ltrim($mobile, '+'))
                        ->where('email', strtolower(trim($application['email'])))
                        ->orderByDesc('id')
                        ->first(['country', 'state', 'city', 'image_franchise']);

                    if ($savedLead === null) {
                        throw new RuntimeException('The saved X-Clusive lead could not be found for its notification.');
                    }

                    $savedImage = (string) $savedLead->image_franchise;
                    $imageUrl = preg_match('/\A[A-Za-z0-9]{32}\.(?:jpg|png|webp)\z/', $savedImage) === 1
                        ? asset('uploads/images/'.$savedImage)
                        : null;

                    Mail::send('emails.franchise.xclusive-team', $data + [
                        'savedLead' => $savedLead,
                        'imageUrl' => $imageUrl,
                    ], static function (Message $message) use ($teamAddresses): void {
                        $message->to($teamAddresses)->subject('New Detailing Devils X-Clusive Franchise Application');
                    });
                } catch (Throwable $exception) {
                    Log::warning('X-Clusive franchise team email failed.', ['exception' => $exception::class]);
                }
            }

            try {
                Mail::send('emails.franchise.xclusive-applicant', $data, static function (Message $message) use ($application): void {
                    $message->to($application['email'])->subject('Your Detailing Devils X-Clusive Franchise Application');
                });
            } catch (Throwable $exception) {
                Log::warning('X-Clusive franchise applicant email failed.', ['exception' => $exception::class]);
            }
        });
    }
}

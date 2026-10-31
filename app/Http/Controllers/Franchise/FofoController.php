<?php

namespace App\Http\Controllers\Franchise;

use App\Actions\Franchise\SaveFranchiseLeadAction;
use App\Actions\Franchise\SendFranchiseCallAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Franchise\StoreFofoRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class FofoController extends Controller
{
    public function create(): View
    {
        return view('forms.fofo', [
            'submissionEnabled' => (bool) config('services.fofo.submission_enabled'),
            'questionnaireOptions' => config('form_options.fofo'),
            'formModel' => [
                'name' => 'FOFO',
                'title' => 'FOFO Franchise Application | Detailing Devils',
                'description' => 'Explore the Detailing Devils Franchise Owned Franchise Operated model.',
                'label' => 'Franchise Owned Franchise Operated',
                'submitRoute' => 'fofo.submit',
            ],
        ]);
    }

    public function store(StoreFofoRequest $request, SaveFranchiseLeadAction $save, SendFranchiseCallAction $call): JsonResponse
    {
        if (! config('services.fofo.submission_enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'FOFO applications are not accepting submissions yet.',
            ], 503);
        }

        try {
            $application = $request->validated();
            $mobile = $request->normalizedMobile();
            $created = $save->saveFofo($application, $mobile, $request->session());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('FOFO franchise application could not be saved.', ['exception' => $exception::class]);

            return response()->json([
                'success' => false,
                'message' => 'We could not save your application right now. Please try again later.',
            ], 503);
        }

        if ($created) {
            $this->scheduleFollowUp($application, $mobile);
            app()->terminating(static fn () => $call('fofo', $mobile));
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'message' => $created
                ? 'Thank you! Your FOFO franchise application has been submitted successfully. Our team will contact you shortly.'
                : 'We have already received your application. Our franchise team will contact you shortly.',
        ]);
    }

    private function scheduleFollowUp(array $application, string $mobile): void
    {
        unset($application['locationimage']);

        app()->terminating(static function () use ($application, $mobile): void {
            $data = [
                'application' => $application,
                'name' => trim($application['name'].' '.$application['lname']),
                'mobile' => $mobile,
                'submittedOn' => now()->toDateString(),
            ];

            if (config('mail.default') === 'log') {
                Log::warning('FOFO franchise emails skipped: a delivery mailer is not configured.');
            } else {
                $teamAddresses = array_values(array_unique(array_filter(
                    array_map('trim', explode(',', (string) config('mail.fofo_team_address'))),
                    static fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false
                )));

                if ($teamAddresses === []) {
                    Log::warning('FOFO franchise team email skipped: recipient is not configured.');
                } else {
                    try {
                        $savedLead = DB::table('tbl_common_leads')
                            ->where('record_type', 'FOFO')
                            ->where('phone', ltrim($mobile, '+'))
                            ->where('email', strtolower(trim($application['email'])))
                            ->orderByDesc('id')
                            ->first(['country', 'state', 'city', 'image_franchise']);

                        if ($savedLead === null) {
                            throw new RuntimeException('The saved FOFO lead could not be found for its notification.');
                        }

                        $savedImage = (string) $savedLead->image_franchise;
                        $imageUrl = preg_match('/\A[A-Za-z0-9]{32}\.(?:jpg|png|webp)\z/', $savedImage) === 1
                            ? asset('uploads/images/'.$savedImage)
                            : null;

                        Mail::send('emails.franchise.fofo-team', $data + [
                            'savedLead' => $savedLead,
                            'imageUrl' => $imageUrl,
                        ], static function (Message $message) use ($teamAddresses): void {
                            $message->to($teamAddresses)->subject('Franchise Owned Franchise Operated Model');
                        });
                    } catch (Throwable $exception) {
                        Log::warning('FOFO franchise team email failed.', ['exception' => $exception::class]);
                    }
                }

                try {
                    Mail::send('emails.franchise.fofo-applicant', $data, static function (Message $message) use ($application): void {
                        $message->to($application['email'])->subject('Franchise Owned Franchise Operated Model');
                    });
                } catch (Throwable $exception) {
                    Log::warning('FOFO franchise applicant email failed.', ['exception' => $exception::class]);
                }
            }
        });
    }
}
